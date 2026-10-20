<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrphanStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\FieldVisitResource;
use App\Models\FieldVisit;
use App\Models\Orphan;
use App\Models\Setting;
use App\Models\User;
use App\Support\StaffScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FieldVisitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('visits.view'), 403);

        // المشرف يرى زيارات بلده وحده، والعدّاد «متأخرة» يتبع القائمة نفسها
        $scoped = fn () => StaffScope::for($request->user())->through(FieldVisit::query(), 'orphan.family');

        $visits = $scoped()
            ->withHasLater()
            ->with(['orphan:id,reference_code,first_name', 'supervisor:id,name'])
            ->when($request->filled('orphan_id'), fn ($q) => $q->where('orphan_id', $request->integer('orphan_id')))
            ->when($request->filled('supervisor_id'), fn ($q) => $q->where('supervisor_id', $request->integer('supervisor_id')))
            ->when($request->filled('from'), fn ($q) => $q->where('visited_at', '>=', $this->localDay($request->input('from'))))
            ->when($request->filled('to'), fn ($q) => $q->where('visited_at', '<=', $this->localDay($request->input('to'), end: true)))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('orphan', function ($o) use ($request): void {
                /** @var Builder<Orphan> $o */
                $o->search($request->string('q')->toString());
            }))
            ->when($request->boolean('overdue'), fn ($q) => $q->overdue())
            ->when($request->filled('need'), fn ($q) => $this->whereNeed($q, $request->string('need')->toString()))
            ->latest('visited_at')
            ->paginate($this->perPage($request, 20));

        /*
         * الاحتياجات المرصودة في الفترة نفسها: «كم طفلاً يحتاج دعماً نفسياً؟»
         * سؤالٌ وُجدت الأعلام المغلقة لأجله، ولم يكن يُجاب عنه في أي مكان.
         */
        $needs = $scoped()
            ->when($request->filled('supervisor_id'), fn ($q) => $q->where('supervisor_id', $request->integer('supervisor_id')))
            ->when($request->filled('from'), fn ($q) => $q->where('visited_at', '>=', $this->localDay($request->input('from'))))
            ->when($request->filled('to'), fn ($q) => $q->where('visited_at', '<=', $this->localDay($request->input('to'), end: true)))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('orphan', function ($o) use ($request): void {
                /** @var Builder<Orphan> $o */
                $o->search($request->string('q')->toString());
            }))
            ->whereNotNull('needs_flagged')
            ->get(['orphan_id', 'needs_flagged'])
            ->flatMap(fn (FieldVisit $v) => collect($v->needs_flagged ?? [])->map(fn ($n) => ['need' => $n, 'orphan_id' => $v->orphan_id]))
            ->groupBy('need')
            // أطفالٌ لا زيارات: طفلٌ رُصد احتياجه في زيارتين يُعدّ مرة
            ->map(fn ($rows) => $rows->pluck('orphan_id')->unique()->count())
            ->sortDesc();

        return response()->json([
            'data' => FieldVisitResource::collection($visits->items()),
            'meta' => [
                'total' => $visits->total(),
                'current_page' => $visits->currentPage(),
                'last_page' => $visits->lastPage(),
                'overdue' => $scoped()->overdue()->count(),
                'needs' => $needs,
                // لفلتر المشرف عند من يرى أكثر من مشرف
                'supervisors' => User::role('field_supervisor')->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    /**
     * خطة الزيارات: كل طفل مفتوح الملف في نطاق من يرى، بموعد زيارته القادمة.
     *
     * السجل يجيب «ماذا حدث؟»، وهذا يجيب سؤال المشرف كل صباح: «من أزور الآن؟».
     * والطفل الذي لم يُزر قط لا يظهر في السجل أصلاً، فكان يُنسى تماماً.
     */
    public function schedule(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('visits.view'), 403);

        $today = now(config('kafala.display_timezone'))->toDateString();
        $week = now(config('kafala.display_timezone'))->addDays(7)->toDateString();
        $month = now(config('kafala.display_timezone'))->addDays(30)->toDateString();

        $latest = fn (string $column) => FieldVisit::query()->select($column)
            ->whereColumn('field_visits.orphan_id', 'orphans.id')
            ->latest('visited_at')->latest('id')->limit(1);

        $base = Orphan::query()
            ->whereIn('orphans.status', [OrphanStatus::Active, OrphanStatus::FullyFunded, OrphanStatus::PendingVerification])
            ->tap(fn ($q) => StaffScope::for($request->user())->through($q, 'family'))
            ->when($request->filled('q'), fn ($q) => $q->search($request->string('q')->toString()));

        // الموعد القادم = آخر زيارة، والطفل بلا زيارة «لم يُزر»
        $withDue = fn () => (clone $base)->select('orphans.*')
            ->addSelect(['next_due' => $latest('next_visit_due'), 'last_visited_at' => $latest('visited_at'), 'last_supervisor_id' => $latest('supervisor_id')]);

        $bucket = fn (string $b) => match ($b) {
            'overdue' => $withDue()->having('next_due', '<', $today),
            'never' => $withDue()->havingNull('last_visited_at'),
            'week' => $withDue()->having('next_due', '>=', $today)->having('next_due', '<=', $week),
            'month' => $withDue()->having('next_due', '>', $week)->having('next_due', '<=', $month),
            'later' => $withDue()->having('next_due', '>', $month),
            default => $withDue(),
        };

        $counts = collect(['overdue', 'never', 'week', 'month', 'later'])
            ->mapWithKeys(fn ($b) => [$b => DB::query()->fromSub($bucket($b), 'x')->count()]);

        $rows = $bucket($request->string('bucket')->toString())
            ->with(['family:id,family_code,city_id', 'family.city:id,name_ar'])
            // الأقدم استحقاقاً أولاً، ومن لم يُزر قط في المقدمة
            ->orderByRaw('last_visited_at IS NOT NULL')
            ->orderBy('next_due')
            ->paginate($this->perPage($request, 25));

        $supervisors = User::whereIn('id', collect($rows->items())->pluck('last_supervisor_id')->filter()->unique())->pluck('name', 'id');

        return response()->json([
            'data' => collect($rows->items())->map(fn (Orphan $o) => [
                'orphan_id' => $o->id,
                'orphan' => [
                    'id' => $o->id, 'reference_code' => $o->reference_code, 'name' => $o->first_name, 'full_name' => $o->full_name,
                    'photo_url' => $o->photo_path ? asset('storage/'.$o->photo_path) : null,
                    'status' => $o->status->value, 'status_label' => $o->status->label(),
                    'city' => $o->family?->city?->name_ar,
                ],
                'next_due' => $o->getAttribute('next_due'),
                'last_visited_at' => $o->getAttribute('last_visited_at') ? Carbon::parse($o->getAttribute('last_visited_at'))->toIso8601String() : null,
                'last_supervisor' => $supervisors[$o->getAttribute('last_supervisor_id')] ?? null,
                'days' => $o->getAttribute('next_due') ? (int) now(config('kafala.display_timezone'))->startOfDay()->diffInDays(Carbon::parse($o->getAttribute('next_due')), false) : null,
            ]),
            'meta' => [
                'total' => $rows->total(), 'current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(),
                'bucket_counts' => $counts,
                'all_count' => (clone $base)->count(),
            ],
        ]);
    }

    /**
     * زيارةٌ رُصد فيها احتياج بعينه. الأعلام مخزّنة JSON بترميز \uXXXX،
     * و JSON_CONTAINS في MariaDB لا يطابق العربية المرمّزة، فالمطابقة بالنصّ
     * بصيغتيه، والعلم بين علامتي تنصيص فلا يطابق جزءاً من علم آخر.
     */
    private function whereNeed($query, string $need)
    {
        $escaped = trim(json_encode($need), '"');

        return $query->where(fn ($q) => $q
            ->where('needs_flagged', 'like', '%"'.addcslashes($need, '\\%_').'"%')
            ->orWhere('needs_flagged', 'like', '%"'.addcslashes($escaped, '\\%_').'"%'));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('visits.record'), 403);

        $validated = $request->validate([
            // المحذوف (المؤرشف) لا يُزار، فلا يمرّ من التحقق ليسقط بعده بخطأ 404
            'orphan_id' => ['required', 'integer', Rule::exists('orphans', 'id')->whereNull('deleted_at')],
            'visited_at' => ['required', 'date', 'before_or_equal:now'],
            'findings' => ['required', 'string', 'min:10', 'max:4000'],
            'needs_flagged' => ['nullable', 'array'],
            'needs_flagged.*' => ['string', 'max:40'],
            // هل يعيش الطفل حيث يقول ملفه؟ نعم تجدّد إقرار الرعاية، ولا تُسقطه
            'care_confirmed' => ['nullable', 'boolean'],
            'next_visit_due' => ['nullable', 'date', 'after:today'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ], [
            'visited_at.before_or_equal' => 'لا تُسجَّل زيارة بتاريخ مستقبلي.',
            'findings.min' => 'اكتب ملاحظات الزيارة، فسطر واحد لا يوثّق شيئاً.',
        ]);

        // لا تُسجَّل زيارة ليتيم خارج منطقة المشرف (السياسة نفسها التي تحجب ملفه عنه)
        $orphan = Orphan::findOrFail($validated['orphan_id']);
        $this->authorize('view', $orphan);

        // ملفٌ مغلق (متخرّج، متوفّى، منسحب) لا تُسجَّل له زيارة جديدة
        abort_if(
            in_array($orphan->status, [OrphanStatus::Graduated, OrphanStatus::Deceased, OrphanStatus::Withdrawn], true),
            422,
            "هذا ملف مغلق ({$orphan->status->label()}). لا تُسجَّل له زيارة.",
        );

        /*
         * موعد الزيارة القادمة إن لم يُكتب: بعد دورية التقارير في الإعدادات.
         * زيارةٌ بلا موعد قادم لا تصير «متأخرة» أبداً، فيُنسى الطفل.
         */
        $validated['next_visit_due'] ??= Carbon::parse($validated['visited_at'])
            ->addMonthsNoOverflow((int) (Setting::cached()['policy.report_cadence_months'] ?? 3))
            ->toDateString();

        $visit = FieldVisit::create([
            ...$validated,
            'supervisor_id' => $request->user()->id,
        ]);

        /*
         * الزيارة هي ما يُقرّ ترتيب الرعاية: تأكيدٌ يجدّده اثني عشر شهراً،
         * ونفيٌ يُسقطه فلا يُعاد نشر الطفل حتى يُصحَّح ملف أسرته.
         */
        $family = $orphan->family;
        if ($family !== null && array_key_exists('care_confirmed', $validated) && $validated['care_confirmed'] !== null) {
            if ($validated['care_confirmed'] && $family->care_arrangement !== null) {
                $family->attestCare($family->care_arrangement, $request->user());
            } elseif (! $validated['care_confirmed']) {
                $family->forceFill(['care_attested_at' => null, 'care_attested_by' => null, 'care_review_due_at' => null])->save();
            }
        }

        return response()->json([
            'message' => 'سُجّلت الزيارة.',
            'data' => new FieldVisitResource($visit->load('orphan')),
        ], 201);
    }
}
