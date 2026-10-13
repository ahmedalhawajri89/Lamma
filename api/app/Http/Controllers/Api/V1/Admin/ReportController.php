<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Reports\PublishReport;
use App\Enums\OrphanStatus;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Orphan;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Report::class);

        $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'quarter' => ['nullable', 'integer', 'min:1', 'max:4'],
            'supervisor_id' => ['nullable', 'integer'],
        ]);

        $status = $request->string('status')->toString();
        $queue = $request->boolean('queue') || $status === ReportStatus::Submitted->value;

        $reports = $this->filtered($request)
            ->with(['orphan:id,reference_code,first_name,last_name,photo_path', 'supervisor:id,name'])
            ->withCount('views')
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($request->boolean('queue'), fn ($q) => $q->where('status', ReportStatus::Submitted))
            // الطابور الأقدم أولاً: من انتظر أطول يُراجَع قبل غيره
            ->when($queue, fn ($q) => $q->oldest('updated_at'), fn ($q) => $q->latest('period_year')->latest('period_quarter')->latest('id'))
            ->paginate($this->perPage($request, 20));

        $base = $this->filtered($request);

        return response()->json([
            'data' => ReportResource::collection($reports->items()),
            'meta' => [
                'total' => $reports->total(), 'current_page' => $reports->currentPage(), 'last_page' => $reports->lastPage(),
                'status_counts' => (clone $base)->toBase()->select('status', DB::raw('COUNT(*) as aggregate'))
                    ->groupBy('status')->pluck('aggregate', 'status')->map(fn ($n) => (int) $n),
                'all_count' => (clone $base)->count(),
                // للفلتر: من كتب تقارير في نطاق من يرى
                'supervisors' => User::query()
                    ->whereIn('id', $this->scoped($request)->select('supervisor_id')->distinct())
                    ->orderBy('name')->get(['id', 'name']),
                'coverage' => $this->coverage($request),
            ],
        ]);
    }

    /** نطاق من يرى: المشرف بلده وحده، وهو قيد `ReportPolicy::view` نفسه */
    private function scoped(Request $request): Builder
    {
        $user = $request->user();
        $country = $user->isAdmin() ? null : $user->assignedCountryId();

        return Report::query()->when($country !== null, fn ($q) => $q->whereHas(
            'orphan.family', fn ($f) => $f->where('country_id', $country),
        ));
    }

    /** الفلاتر المشتركة بين الجدول وعدّاداته، عدا الحالة */
    private function filtered(Request $request): Builder
    {
        $user = $request->user();

        return $this->scoped($request)
            ->when($request->filled('q'), fn ($q) => $q->whereHas('orphan', fn ($o) => $o->search($request->string('q')->toString())))
            ->when($request->filled('orphan_id'), fn ($q) => $q->where('orphan_id', $request->integer('orphan_id')))
            ->when($request->filled('supervisor_id'), fn ($q) => $q->where('supervisor_id', $request->integer('supervisor_id')))
            ->when($request->filled('year'), fn ($q) => $q->where('period_year', $request->integer('year')))
            ->when($request->filled('quarter'), fn ($q) => $q->where('period_quarter', $request->integer('quarter')))
            // «أُرجعت إليك»: لكاتبها وحده، والمراجع يرى كل ما أرجعه
            ->when($request->boolean('returned'), fn ($q) => $q->whereNotNull('returned_at')
                ->where('status', ReportStatus::Draft)
                ->when(! $user->can('reports.approve'), fn ($q) => $q->where('supervisor_id', $user->id)));
    }

    /**
     * تغطية الربع: الوعد «تقرير كل ثلاثة أشهر لكل طفل مكفول» بالأرقام.
     *
     * كم طفلاً نشطاً في نطاق من يرى، وكم منهم له تقرير منشور أو في الطريق،
     * وكم لا تقرير له أصلاً. هذا ما يُتابَع قبل نهاية الربع لا بعده.
     */
    private function coverage(Request $request): array
    {
        $now = now(config('kafala.display_timezone'));
        $year = $request->integer('year') ?: $now->year;
        $quarter = $request->integer('quarter') ?: $now->quarter;

        $user = $request->user();
        $country = $user->isAdmin() ? null : $user->assignedCountryId();

        $orphans = Orphan::query()
            ->whereIn('status', [OrphanStatus::Active, OrphanStatus::FullyFunded])
            ->when($country !== null, fn ($q) => $q->whereHas('family', fn ($f) => $f->where('country_id', $country)));

        $byStatus = Report::query()
            ->whereIn('orphan_id', (clone $orphans)->select('id'))
            ->where('period_year', $year)->where('period_quarter', $quarter)
            ->toBase()->select('status', DB::raw('COUNT(DISTINCT orphan_id) as aggregate'))
            ->groupBy('status')->pluck('aggregate', 'status')->map(fn ($n) => (int) $n);

        $total = (clone $orphans)->count();
        $missing = (clone $orphans)->whereDoesntHave('reports', fn ($r) => $r->where('period_year', $year)->where('period_quarter', $quarter))->count();

        return [
            'year' => $year,
            'quarter' => $quarter,
            'orphans' => $total,
            'published' => $byStatus[ReportStatus::Published->value] ?? 0,
            'approved' => $byStatus[ReportStatus::Approved->value] ?? 0,
            'submitted' => $byStatus[ReportStatus::Submitted->value] ?? 0,
            'draft' => $byStatus[ReportStatus::Draft->value] ?? 0,
            'missing' => $missing,
        ];
    }

    public function show(Report $report): JsonResponse
    {
        $this->authorize('view', $report);

        return response()->json([
            'data' => new ReportResource($report->load(['orphan', 'media', 'supervisor:id,name', 'approver:id,name'])),
        ]);
    }

    public function store(StoreReportRequest $request): JsonResponse
    {
        $report = Report::create([
            ...$request->validated(),
            'supervisor_id' => $request->user()->id,
            'status' => ReportStatus::Draft,
        ]);

        return response()->json([
            'message' => 'أُنشئت مسوّدة التقرير.',
            'data' => new ReportResource($report->load('orphan')),
        ], 201);
    }

    public function update(Request $request, Report $report): JsonResponse
    {
        $this->authorize('update', $report);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:180'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'academic_note' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'health_note' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'psychosocial_note' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'visit_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
        ]);

        $report->update($validated);

        return response()->json([
            'message' => 'حُفظت التعديلات.',
            'data' => new ReportResource($report->fresh()->load('orphan')),
        ]);
    }

    /** المشرف يرسل تقريره للمراجعة — وبعدها لا يعدّله (تفرضه ReportPolicy). */
    public function submit(Report $report): JsonResponse
    {
        $this->authorize('update', $report);

        abort_if($report->status !== ReportStatus::Draft, 422, 'لا يُرسَل إلا تقرير في حالة مسوّدة.');

        // تبقى ملاحظة المراجع ليرى من يراجعه بعدها ما طُلب، أما علامة الإرجاع فتزول
        $report->update(['status' => ReportStatus::Submitted, 'returned_at' => null]);

        return response()->json([
            'message' => 'أُرسل التقرير للمراجعة.',
            'data' => new ReportResource($report->fresh()),
        ]);
    }

    public function approve(Report $report, PublishReport $action): JsonResponse
    {
        $this->authorize('approve', $report);

        return response()->json([
            'message' => 'اعتُمد التقرير، وأُخذت لقطة من صرف الربع.',
            'data' => new ReportResource($action->approve($report, request()->user())),
        ]);
    }

    /**
     * المراجع يُرجع التقرير لكاتبه مع ملاحظة بما ينقصه.
     *
     * يعود مسوّدةً فيعدّله المشرف ثم يرسله من جديد. والملاحظة إلزامية: إرجاعٌ
     * بلا سبب يترك الكاتب يخمّن، ويعود التقرير كما هو.
     */
    public function return(Request $request, Report $report): JsonResponse
    {
        $this->authorize('return', $report);

        abort_if($report->status !== ReportStatus::Submitted, 422, 'لا يُرجَع إلا تقرير مُرسل للمراجعة.');

        $validated = $request->validate([
            'note' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'note.required' => 'اكتب ما يحتاج التقرير إلى تعديله.',
            'note.min' => 'الملاحظة قصيرة جداً، وضّح ما المطلوب.',
        ]);

        $report->update([
            'status' => ReportStatus::Draft,
            'review_note' => $validated['note'],
            'returned_at' => now(),
        ]);

        activity()
            ->performedOn($report)
            ->causedBy($request->user())
            ->withProperties(['note' => $validated['note']])
            ->log('أرجع تقريراً للتعديل');

        return response()->json([
            'message' => 'أُرجع التقرير لكاتبه مع ملاحظتك.',
            'data' => new ReportResource($report->fresh()),
        ]);
    }

    public function publish(Report $report, PublishReport $action): JsonResponse
    {
        $this->authorize('publish', $report);

        return response()->json([
            'message' => 'نُشر التقرير. سيراه الكفلاء في لوحاتهم.',
            'data' => new ReportResource($action->publish($report)),
        ]);
    }
}
