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

        $reports = Report::query()
            ->with(['orphan:id,reference_code,first_name,photo_path', 'supervisor:id,name'])
            ->withCount('views')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('orphan_id'), fn ($q) => $q->where('orphan_id', $request->integer('orphan_id')))
            ->when($request->filled('year'), fn ($q) => $q->where('period_year', $request->integer('year')))
            ->when($request->boolean('queue'), fn ($q) => $q->where('status', ReportStatus::Submitted))
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

        $report->update(['status' => ReportStatus::Submitted]);

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

    public function publish(Report $report, PublishReport $action): JsonResponse
    {
        $this->authorize('publish', $report);

        return response()->json([
            'message' => 'نُشر التقرير. سيراه الكفلاء في لوحاتهم.',
            'data' => new ReportResource($action->publish($report)),
        ]);
    }
}
