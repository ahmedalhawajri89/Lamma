<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use App\Models\ReportView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sponsor = $request->user()->sponsor;
        abort_if($sponsor === null, 403);

        $reports = Report::query()
            ->published()
            ->whereIn('orphan_id', $sponsor->sponsorships()->live()->select('orphan_id'))
            ->with(['orphan:id,reference_code,first_name,photo_path', 'media'])
            ->withCount(['views' => fn ($q) => $q->where('sponsor_id', $sponsor->id)])
            ->latest('published_at')
            ->paginate($this->perPage($request, 12));

        return response()->json([
            'data' => ReportResource::collection($reports->items()),
            'meta' => ['total' => $reports->total(), 'current_page' => $reports->currentPage(), 'last_page' => $reports->lastPage()],
        ]);
    }

    /**
     * فتح التقرير يسجّل القراءة تلقائياً.
     *
     * `firstOrCreate` على قيد `UNIQUE(report_id, sponsor_id)` يجعل إعادة الفتح
     * لا تنشئ صفاً ثانياً، ويحفظ **أول** مرة فُتح فيها لا آخرها — وهذا ما
     * نريد قياسه: متى وصل التقرير إلى الكافل.
     */
    public function show(Request $request, Report $report): JsonResponse
    {
        $this->authorize('view', $report);

        $sponsor = $request->user()->sponsor;

        if ($sponsor !== null) {
            ReportView::firstOrCreate(
                ['report_id' => $report->id, 'sponsor_id' => $sponsor->id],
                ['viewed_at' => now()],
            );
        }

        return response()->json([
            'data' => new ReportResource(
                $report->load(['orphan:id,reference_code,first_name,photo_path', 'media', 'supervisor:id,name']),
            ),
        ]);
    }
}
