<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrphanStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\OrphanResource;
use App\Models\Orphan;
use App\Models\User;
use App\Services\FinanceDashboardService;
use App\Services\MetricsService;
use App\Services\SinceLastVisitService;
use App\Services\SupervisorDashboardService;
use App\Services\TransparencyService;
use App\Services\WorkQueueService;
use App\Support\StaffScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

/**
 * لوحة الإدارة.
 *
 * استجابة واحدة تحمل كل ما تحتاجه الشاشة الأولى، لكنها **بحسب صاحبها**:
 *
 *  - «بانتظارك» (`tasks`) من صلاحياته.
 *  - الأرقام المالية والمخططات لمن يملك `analytics.view` وحده، والمالية
 *    منها لمن يملك `payments.view`. كانت تُرسل للجميع، فيستلمها المشرف
 *    الميداني وهو لا يملك صلاحية المدفوعات.
 *  - المشرف الميداني يرى أرقام بلده المُسنَد وحالاته العاجلة فقط.
 */
class DashboardController extends Controller
{
    /** مفاتيح `kpis` المالية: لا تُرسل لمن لا يملك رؤية المدفوعات. */
    private const FINANCIAL_KPIS = [
        'monthly_recurring', 'revenue_month', 'revenue_month_change',
        'disbursed_month', 'failed_payments_month',
    ];

    public function __construct(
        private readonly MetricsService $metrics,
        private readonly TransparencyService $transparency,
        private readonly WorkQueueService $queue,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Orphan::class);

        $user = $request->user();
        $scope = StaffScope::for($user);
        $analytics = $user->can('analytics.view');
        $months = min(24, max(6, $request->integer('months', 12)));

        return response()->json([
            'data' => [
                'scope' => $this->scope($user, $scope),

                // لوحة المحاسب: مال الشهر وما يُتابَع منه
                'finance' => $user->hasRole(UserRole::Accountant->value) ? app(FinanceDashboardService::class)->build() : null,

                // لوحة المشرف الميداني: زيارات بلده وتقاريره وملفاته
                'field' => $user->hasRole(UserRole::FieldSupervisor->value) && ! $scope->isUnrestricted()
                    ? app(SupervisorDashboardService::class)->build($user) : null,
                'tasks' => $this->queue->tasks($user),
                'kpis' => $this->kpis($user, $scope),

                // التحليلات لمن يملكها؛ غيره يستلم قوائم فارغة فلا تُرسم مخططاتها
                'monthly' => $analytics ? $this->metrics->monthlySeries($months) : [],
                'by_country' => $analytics ? $this->metrics->byCountry() : [],
                'by_program' => $analytics ? $this->metrics->byProgram() : [],
                'funding_buckets' => $analytics ? $this->metrics->fundingBuckets() : [],
                'retention' => $analytics ? $this->metrics->retention() : null,
                'spending' => $analytics && $user->can('payments.view')
                    ? $this->transparency->spendingBreakdown()
                    : [],

                'urgent_cases' => OrphanResource::collection(
                    $scope->through(Orphan::query(), 'family')
                        ->urgentUnfunded()
                        ->where('status', OrphanStatus::Active)
                        ->with(['program', 'family', 'family.country'])
                        ->orderBy('funded_percent')
                        ->orderBy('published_at')
                        ->limit(6)
                        ->get(),
                )->resolve(),

                'recent_activity' => $user->can('activity.view')
                    ? ActivityResource::collection(
                        Activity::query()->with('causer:id,name')->latest()->limit(10)->get(),
                    )->resolve()
                    : [],
            ],
        ]);
    }

    /** «بانتظارك» وحدها: للشريط الجانبي، أخفّ من النظرة العامة كلها. */
    public function queue(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Orphan::class);

        return response()->json(['data' => $this->queue->tasks($request->user())]);
    }

    /** «منذ آخر دخول»: ما جرى والموظف غائب، مما لا يدخل طابور مهامه. */
    public function since(Request $request, SinceLastVisitService $service): JsonResponse
    {
        $this->authorize('viewAny', Orphan::class);

        return response()->json(['data' => $service->for($request->user())]);
    }

    /** @return array<string, mixed> */
    private function scope(User $user, StaffScope $scope): array
    {
        $role = collect([UserRole::Admin, UserRole::Accountant, UserRole::FieldSupervisor])
            ->first(fn (UserRole $r) => $user->hasRole($r->value));

        return [
            'role' => $role?->value,
            'role_label' => $role?->label(),
            'country' => $scope->countryId ? \App\Models\Country::whereKey($scope->countryId)->value('name_ar') : null,
            // موظف الجمعية يرى اسم جمعيته فوق لوحته
            'association' => $scope->associationId ? $user->staffProfile?->association?->name_ar : null,
        ];
    }

    /** @return array<string, mixed> */
    private function kpis(User $user, StaffScope $scope): array
    {
        // المشرف (بلد أو جمعية، بلا تحليلات): أرقام نطاقه وحدها، بلا مال
        if (! $scope->isUnrestricted() && ! $user->can('analytics.view')) {
            return $this->metrics->scopedKpis($scope);
        }

        $kpis = $this->metrics->kpis();

        if (! $user->can('payments.view')) {
            $kpis = array_diff_key($kpis, array_flip(self::FINANCIAL_KPIS));
        }

        return $kpis;
    }
}
