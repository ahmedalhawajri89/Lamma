<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrphanStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\OrphanResource;
use App\Models\Orphan;
use App\Services\MetricsService;
use App\Services\TransparencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

/**
 * لوحة الإدارة.
 *
 * استجابة واحدة تحمل كل ما تحتاجه الشاشة الأولى. عشر نقاط نهاية منفصلة
 * تعني عشرة طلبات متوازية عند كل فتح، وعشر حالات تحميل في الواجهة —
 * وكلها ستنتظر أبطأها على أي حال.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly MetricsService $metrics,
        private readonly TransparencyService $transparency,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Orphan::class);

        $months = min(24, max(6, $request->integer('months', 12)));

        return response()->json([
            'data' => [
                'kpis' => $this->metrics->kpis(),
                'monthly' => $this->metrics->monthlySeries($months),
                'by_country' => $this->metrics->byCountry(),
                'by_program' => $this->metrics->byProgram(),
                'funding_buckets' => $this->metrics->fundingBuckets(),
                'retention' => $this->metrics->retention(),
                'spending' => $this->transparency->spendingBreakdown(),

                // الحالات التي تنتظر كافلاً منذ أطول مدة — أول ما ينبغي أن
                // يراه المدير صباحاً، لا آخر ما يبحث عنه
                'urgent_cases' => OrphanResource::collection(
                    Orphan::query()
                        ->urgentUnfunded()
                        ->where('status', OrphanStatus::Active)
                        ->with(['program', 'family', 'family.country'])
                        ->orderBy('funded_percent')
                        ->orderBy('published_at')
                        ->limit(6)
                        ->get(),
                )->resolve(),

                'recent_activity' => $request->user()->can('activity.view')
                    ? ActivityResource::collection(
                        Activity::query()->with('causer:id,name')->latest()->limit(10)->get(),
                    )->resolve()
                    : [],
            ],
        ]);
    }
}
