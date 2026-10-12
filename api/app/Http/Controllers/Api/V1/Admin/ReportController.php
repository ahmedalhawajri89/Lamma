<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Reports\PublishReport;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Report::class);

        $user = $request->user();
        $country = $user->isAdmin() ? null : $user->assignedCountryId();

        $reports = Report::query()
            // المشرف يرى تقارير بلده وحده؛ وهو نفس قيد `ReportPolicy::view`
            ->when($country !== null, fn ($q) => $q->whereHas(
                'orphan.family', fn ($f) => $f->where('country_id', $country),
            ))
            ->with(['orphan:id,reference_code,first_name,photo_path', 'supervisor:id,name'])
            ->withCount('views')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('orphan_id'), fn ($q) => $q->where('orphan_id', $request->integer('orphan_id')))
            ->when($request->filled('year'), fn ($q) => $q->where('period_year', $request->integer('year')))
            ->when($request->boolean('queue'), fn ($q) => $q->where('status', ReportStatus::Submitted))
            // «أُرجعت إليك»: لكاتبها وحده، والمراجع يرى كل ما أرجعه
            ->when($request->boolean('returned'), fn ($q) => $q->whereNotNull('returned_at')
                ->when(! $user->can('reports.approve'), fn ($q) => $q->where('supervisor_id', $user->id)))
            ->latest('period_year')
            ->latest('period_quarter')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => ReportResource::collection($reports->items()),
            'meta' => ['total' => $reports->total(), 'current_page' => $reports->currentPage(), 'last_page' => $reports->lastPage()],
        ]);
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
