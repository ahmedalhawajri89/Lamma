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
use App\Services\MetricsService;
use App\Services\SinceLastVisitService;
use App\Services\TransparencyService;
use App\Services\WorkQueueService;
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
        $country = $user->isAdmin() ? null : $user->assignedCountryId();
        $analytics = $user->can('analytics.view');
        $months = min(24, max(6, $request->integer('months', 12)));

        return response()->json([
            'data' => [
                'scope' => $this->scope($user, $country),
                'tasks' => $this->queue->tasks($user),
                'kpis' => $this->kpis($user, $country),

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
                    Orphan::query()
                        ->urgentUnfunded()
                        ->where('status', OrphanStatus::Active)
                        ->when($country !== null, fn ($q) => $q->whereHas(
                            'family',
                            fn ($f) => $f->where('country_id', $country),
                        ))
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
    private function scope(User $user, ?int $country): array
    {
        $role = collect([UserRole::Admin, UserRole::Accountant, UserRole::FieldSupervisor])
            ->first(fn (UserRole $r) => $user->hasRole($r->value));

        return [
            'role' => $role?->value,
            'role_label' => $role?->label(),
            'country' => $country !== null ? $user->staffProfile?->assignedCountry?->name_ar : null,
        ];
    }

    /** @return array<string, mixed> */
    private function kpis(User $user, ?int $country): array
    {
        // المشرف (بلد مُسنَد وبلا تحليلات): أرقام بلده وحدها، بلا مال
        if ($country !== null && ! $user->can('analytics.view')) {
            return $this->metrics->countryKpis($country);
        }

        $kpis = $this->metrics->kpis();

        if (! $user->can('payments.view')) {
            $kpis = array_diff_key($kpis, array_flip(self::FINANCIAL_KPIS));
        }

        return $kpis;
    }
}
