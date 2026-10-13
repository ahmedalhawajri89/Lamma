<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Orphans\VerifyOrphan;
use App\Enums\OrphanStatus;
use App\Enums\SponsorshipStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOrphanRequest;
use App\Http\Requests\Admin\UpdateOrphanRequest;
use App\Http\Resources\OrphanResource;
use App\Models\Family;
use App\Models\Orphan;
use App\Models\Sponsorship;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrphanController extends Controller
{
    private const SORTS = [
        'created_at', 'funded_percent', 'monthly_need', 'date_of_birth', 'reference_code', 'first_name',
    ];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Orphan::class);

        $query = $this->filtered($request)
            ->with([
                'program',
                'family', 'family.country', 'family.city', 'family.guardian',
            ])
            ->withCount(['sponsorships', 'reports', 'documents'])
            ->withSum(['sponsorships as funded_amount' => fn ($q) => $q->whereIn(
                'status', array_filter(SponsorshipStatus::cases(), fn ($c) => $c->countsTowardFunding()),
            )], 'monthly_amount')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')));

        $sort = in_array($request->string('sort')->toString(), self::SORTS, true)
            ? $request->string('sort')->toString()
            : 'created_at';

        $orphans = $query
            ->orderBy($sort, $request->string('dir')->toString() === 'asc' ? 'asc' : 'desc')
            ->orderBy('id')
            ->paginate($this->perPage($request, 20))
            ->withQueryString();

        return response()->json([
            'data' => OrphanResource::collection($orphans->items()),
            'meta' => [
                'total' => $orphans->total(),
                'per_page' => $orphans->perPage(),
                'current_page' => $orphans->currentPage(),
                'last_page' => $orphans->lastPage(),
                ...$this->facets($request),
            ],
        ]);
    }

    /**
     * ما يجيب عنه رأس الشاشة قبل الجدول.
     *
     * عدد كل حالة تحت الفلاتر الأخرى نفسها (فتبويب «بانتظار التوثيق» يقول كم
     * ينتظر قبل أن يُضغط)، وكم ينقص الأيتامَ النشطين من حاجتهم الشهرية: الرقم
     * الذي يقرّر عليه فريق التسويق أين يوجّه الكفلاء.
     */
    private function facets(Request $request): array
    {
        $base = $this->filtered($request);

        $counts = (clone $base)->toBase()
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($n) => (int) $n);

        $active = (clone $base)->where('status', OrphanStatus::Active);
        $need = (float) (clone $active)->sum('monthly_need');
        $covered = (float) Sponsorship::query()
            ->whereIn('orphan_id', (clone $active)->select('orphans.id'))
            ->whereIn('status', array_filter(SponsorshipStatus::cases(), fn ($c) => $c->countsTowardFunding()))
            ->sum('monthly_amount');

        return [
            'status_counts' => $counts,
            'all_count' => $counts->sum(),
            'urgent_count' => (clone $base)->where('is_urgent', true)->whereIn('status', [OrphanStatus::Active, OrphanStatus::PendingVerification])->count(),
            'funding_gap' => round(max(0, $need - $covered), 2),
        ];
    }

    /**
     * الفلاتر المشتركة بين الجدول وعدّاداته والتصدير، عدا الحالة.
     * تقييد المشرف ببلده هنا: على مستوى الاستعلام لا بعد الجلب، فلا يتسرّب
     * عددٌ ولا صفٌّ من بلد آخر في أيٍّ منها.
     */
    private function filtered(Request $request): Builder
    {
        $query = Orphan::query()->search($request->string('q')->toString());

        if (($countryId = $request->user()->assignedCountryId()) !== null && ! $request->user()->isAdmin()) {
            $query->whereHas('family', fn ($q) => $q->where('country_id', $countryId));
        }

        return $query
            ->when($request->filled('program_id'), fn ($q) => $q->where('program_id', $request->integer('program_id')))
            // أيتام أسرة بعينها (من شاشة الأسر)
            ->when($request->filled('family_id'), fn ($q) => $q->where('family_id', $request->integer('family_id')))
            ->when($request->filled('country_id'), fn ($q) => $q->whereHas(
                'family',
                fn ($f) => $f->where('country_id', $request->integer('country_id')),
            ))
            ->when($request->boolean('urgent'), fn ($q) => $q->where('is_urgent', true))
            ->when($request->boolean('unfunded'), fn ($q) => $q->where('funded_percent', '<', 100))
            ->when($request->boolean('unverified'), fn ($q) => $q->whereNull('verified_at'));
    }

    /**
     * تصدير القائمة كما تُرى (بفلاترها) إلى CSV يفتحه Excel بالعربية.
     *
     * بلا بيانات الأسرة الحساسة (الهوية، الهاتف، الدخل): الملف يخرج من
     * المنصة، فيحمل ما يلزم للمتابعة لا أكثر. والتصدير يُسجَّل في سجل النشاط.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Orphan::class);

        $query = $this->filtered($request)
            ->with(['program', 'family.country'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('reference_code');

        activity('orphans')
            ->causedBy($request->user())
            ->withProperties(['filters' => $request->only(['q', 'status', 'program_id', 'country_id', 'urgent', 'unfunded', 'unverified']), 'rows' => (clone $query)->count()])
            ->log('صدّر قائمة الأيتام');

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            // BOM: بدونه يفتح Excel العربية رموزاً
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['المعرّف', 'الاسم', 'العمر', 'الجنس', 'الدولة', 'البرنامج', 'الحالة', 'عاجل', 'موثّق', 'منشور', 'الحاجة الشهرية', 'نسبة التمويل']);

            $query->chunk(200, function ($rows) use ($out): void {
                foreach ($rows as $o) {
                    fputcsv($out, [
                        $o->reference_code,
                        $o->full_name,
                        $o->age,
                        $o->gender?->label(),
                        $o->family?->country?->name_ar,
                        $o->program?->name_ar,
                        $o->status->label(),
                        $o->is_urgent ? 'نعم' : 'لا',
                        $o->verified_at ? 'نعم' : 'لا',
                        $o->published_at ? 'نعم' : 'لا',
                        number_format((float) $o->monthly_need, 2, '.', ''),
                        $o->funded_percent.'%',
                    ]);
                }
            });

            fclose($out);
        }, 'orphans-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function show(Orphan $orphan): JsonResponse
    {
        $this->authorize('view', $orphan);

        return response()->json([
            'data' => new OrphanResource($orphan->load([
                'program.features',
                'family.country', 'family.city', 'family.guardian',
                'documents',
                'sponsorships.sponsor.user:id,name,email',
                'reports' => fn ($q) => $q->latest('period_year')->latest('period_quarter'),
                'fieldVisits' => fn ($q) => $q->withHasLater()->latest('visited_at')->limit(10),
                'fieldVisits.supervisor:id,name',
                'familyMembers',
            ])),
        ]);
    }

    public function store(StoreOrphanRequest $request, VerifyOrphan $action): JsonResponse
    {
        /**
         * السياسة كانت معرَّفة ولا تُستدعى.
         *
         * حراسة المسار `role:admin|accountant|field_supervisor` تمرّر المحاسبَ
         * إلى هنا، وهو لا يملك `orphans.create`. فكان بابٌ مفتوحاً على مصراعيه
         * خلف سياسةٍ مكتوبة لا تعمل — وهو أسوأ من غياب السياسة، لأنه يبدو
         * محروساً في المراجعة.
         */
        $family = Family::findOrFail($request->validated('family_id'));
        $this->authorize('create', [Orphan::class, $family]);

        $orphan = DB::transaction(function () use ($request): Orphan {
            $data = $request->safe()->except('photo');
            $data['reference_code'] = $this->nextReference();

            $orphan = Orphan::create($data);

            if ($request->hasFile('photo')) {
                $orphan->update([
                    'photo_path' => $request->file('photo')->store('orphans', 'public'),
                ]);
            }

            return $orphan;
        });

        return response()->json([
            'message' => 'أُنشئ الملف. ارفع مستندات التحقق ثم وثّقه لينشر.',
            'data' => new OrphanResource($orphan->load(['program', 'family'])),
        ], 201);
    }

    public function update(UpdateOrphanRequest $request, Orphan $orphan): JsonResponse
    {
        $this->authorize('update', $orphan);

        $data = $request->safe()->except('photo');
        $user = $request->user();

        // نقل الطفل إلى أسرة أخرى يمرّ بقيد البلد نفسه الذي يمرّ به الإنشاء،
        // وإلا نقل المشرف طفلاً إلى بلد آخر ثم فقد رؤيته
        if (isset($data['family_id']) && (int) $data['family_id'] !== $orphan->family_id) {
            $this->authorize('create', [Orphan::class, Family::findOrFail($data['family_id'])]);
        }

        // بيانات الهوية في ملف موثّق هي ما وُثّق: يغيّرها المدير وحده، ويبقى
        // التغيير في سجل النشاط
        $identity = array_intersect_key($data, array_flip(['first_name', 'last_name', 'date_of_birth', 'family_id', 'gender']));
        $current = fn (string $k) => match (true) {
            $orphan->{$k} instanceof \BackedEnum => $orphan->{$k}->value,
            $orphan->{$k} instanceof \DateTimeInterface => $orphan->{$k}->format('Y-m-d'),
            default => (string) $orphan->{$k},
        };
        $changed = collect($identity)->filter(fn ($v, $k) => (string) $v !== $current($k));
        abort_if(
            $orphan->verified_at !== null && $changed->isNotEmpty() && ! $user->isAdmin(),
            422,
            'هذا ملف موثّق: الاسم وتاريخ الميلاد والأسرة يغيّرها المدير وحده.',
        );

        if ($request->hasFile('photo')) {
            // الصورة القديمة تُحذف: تركها يراكم ملفات يتيمة على القرص
            if ($orphan->photo_path) {
                Storage::disk('public')->delete($orphan->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('orphans', 'public');
        }

        $orphan->update($data);

        return response()->json([
            'message' => 'حُفظت التعديلات.',
            'data' => new OrphanResource($orphan->fresh()->load(['program', 'family'])),
        ]);
    }

    /**
     * تغيير مسار الملف: تخرّج أو وفاة. للمدير وحده، وبسبب يُحفظ في السجل.
     *
     * كانت الحالة حقلاً في نموذج التعديل يقبل أي قيمة. الانتقالات هنا محدودة
     * بما له معنى، والنشطة والمكتملة التمويل تُحسبان من الكفالات لا تُكتبان.
     */
    public function changeStatus(Request $request, Orphan $orphan): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $validated = $request->validate([
            'status' => ['required', Rule::in([OrphanStatus::Graduated->value, OrphanStatus::Deceased->value])],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'اكتب سبب تغيير المسار، يبقى في سجل الملف.',
        ]);

        abort_if(
            in_array($orphan->status, [OrphanStatus::Deceased, OrphanStatus::Withdrawn], true),
            422,
            'مسار هذا الملف مغلق.',
        );

        $old = $orphan->status->value;
        $orphan->forceFill(['status' => $validated['status'], 'published_at' => null])->save();

        activity()->performedOn($orphan)->causedBy($request->user())->event('status_changed')
            ->withProperties(['old' => ['status' => $old], 'attributes' => ['status' => $validated['status']], 'reason' => $validated['reason']])
            ->log('غيّر مسار الملف');

        return response()->json([
            'message' => 'غُيّر مسار الملف وأُخفي من الموقع.',
            'data' => new OrphanResource($orphan->fresh()->load(['program', 'family'])),
        ]);
    }

    public function verify(Orphan $orphan, VerifyOrphan $action): JsonResponse
    {
        $this->authorize('verify', $orphan);

        return response()->json([
            'message' => 'وُثّق الملف.',
            'data' => new OrphanResource($action->verify($orphan, request()->user())),
        ]);
    }

    public function publish(Orphan $orphan, VerifyOrphan $action): JsonResponse
    {
        $this->authorize('publish', $orphan);

        return response()->json([
            'message' => 'نُشر الملف على الموقع.',
            'data' => new OrphanResource($action->publish($orphan)),
        ]);
    }

    public function unpublish(Orphan $orphan, VerifyOrphan $action): JsonResponse
    {
        $this->authorize('publish', $orphan);

        return response()->json([
            'message' => 'أُخفي الملف عن الموقع.',
            'data' => new OrphanResource($action->unpublish($orphan)),
        ]);
    }

    /** أرشفة ناعمة — الحذف الصلب يكسر كل تقرير مالي وتاريخي مرتبط. */
    public function destroy(Orphan $orphan): JsonResponse
    {
        $this->authorize('delete', $orphan);

        $orphan->update(['status' => OrphanStatus::Withdrawn, 'published_at' => null]);
        $orphan->delete();

        return response()->json(['message' => 'أُرشف الملف. السجل المالي المرتبط به باقٍ كما هو.']);
    }

    private function nextReference(): string
    {
        // الأكبر رقماً لا نصّاً: نصّياً «KFL-9999» أكبر من «KFL-10000»، فيتكرر الرمز بعد 9999
        $last = (int) Orphan::withTrashed()->lockForUpdate()->max(DB::raw('CAST(SUBSTRING(reference_code, 5) AS UNSIGNED)'));
        $next = $last ? $last + 1 : 1042;

        return 'KFL-'.max(1042, $next);
    }
}
