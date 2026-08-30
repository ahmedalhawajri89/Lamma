<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MessageStatus;
use App\Enums\OrphanStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReportStatus;
use App\Enums\SponsorshipStatus;
use App\Models\ContactMessage;
use App\Models\DailyMetric;
use App\Models\Disbursement;
use App\Models\FieldVisit;
use App\Models\Message;
use App\Models\Orphan;
use App\Models\Payment;
use App\Models\Report;
use App\Models\Sponsor;
use App\Models\Sponsorship;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * تحليلات لوحة الإدارة.
 *
 * القاعدة الحاكمة هنا: **السلاسل الزمنية تُقرأ من `daily_metrics`، والحالة
 * اللحظية تُحسب حيّة.**
 *
 * لماذا؟ «نمو الكفالات آخر ١٢ شهراً» محسوباً على `payments` مباشرة يمسح كل
 * صفوف الجدول عند كل فتح للوحة — يعمل جيداً على ١٧٠٠ صف، ويتجمّد على مليون.
 * والقراءة من ٣٦٥ صفاً في العام تبقى دون عشرة أجزاء من الألف من الثانية مهما
 * كبر النظام. أما «كم يتيماً ينتظر التحقق الآن» فلا معنى لقراءته من الأمس.
 */
class MetricsService
{
    private const CACHE_TTL = 300;

    /* ================================================================
       ١. المؤشرات اللحظية — بطاقات رأس اللوحة
       ================================================================ */

    /** @return array<string, mixed> */
    public function kpis(): array
    {
        return Cache::remember('metrics.kpis', self::CACHE_TTL, function (): array {
            $monthStart = now()->startOfMonth();
            $prevStart = now()->subMonthNoOverflow()->startOfMonth();
            $prevEnd = now()->subMonthNoOverflow()->endOfMonth();

            $revenueThisMonth = (float) Payment::settled()
                ->where('paid_at', '>=', $monthStart)
                ->sum('amount_base');

            $revenuePrevMonth = (float) Payment::settled()
                ->whereBetween('paid_at', [$prevStart, $prevEnd])
                ->sum('amount_base');

            $sponsoredNow = Orphan::whereHas('sponsorships', fn ($q) => $q->active())->count();

            return [
                'orphans_total' => Orphan::count(),
                'orphans_sponsored' => $sponsoredNow,
                'orphans_awaiting' => Orphan::query()
                    ->where('status', OrphanStatus::Active)
                    ->where('funded_percent', '<', 100)
                    ->count(),
                'orphans_pending_verification' => Orphan::query()
                    ->where('status', OrphanStatus::PendingVerification)
                    ->count(),
                'urgent_unfunded' => Orphan::urgentUnfunded()
                    ->where('status', OrphanStatus::Active)
                    ->count(),

                'sponsors_active' => Sponsor::active()->count(),
                'sponsorships_active' => Sponsorship::active()->count(),

                // الالتزام الشهري المتكرّر — أهم رقم مالي في نظام اشتراكات
                'monthly_recurring' => round((float) Sponsorship::active()->sum('monthly_amount'), 2),

                'revenue_month' => round($revenueThisMonth, 2),
                'revenue_month_change' => $revenuePrevMonth > 0
                    ? round(($revenueThisMonth - $revenuePrevMonth) / $revenuePrevMonth * 100, 1)
                    : null,

                'disbursed_month' => round((float) Disbursement::approved()
                    ->where('spent_on', '>=', $monthStart->toDateString())
                    ->sum('amount_base'), 2),

                // طوابير العمل — ما ينتظر قراراً بشرياً اليوم
                'queue_reports' => Report::where('status', ReportStatus::Submitted)->count(),
                'queue_messages' => Message::where('status', MessageStatus::PendingReview)->count(),
                'queue_disbursements' => Disbursement::whereNull('approved_at')->count(),
                'queue_contacts' => ContactMessage::open()->count(),
                'overdue_visits' => FieldVisit::overdue()->count(),

                'failed_payments_month' => Payment::query()
                    ->where('status', PaymentStatus::Failed)
                    ->where('created_at', '>=', $monthStart)
                    ->count(),
            ];
        });
    }

    /* ================================================================
       ٢. السلاسل الزمنية — من جدول التجميع
       ================================================================ */

    /**
     * نمو الكفالات والإيراد شهرياً.
     *
     * نجمع الصفوف اليومية إلى شهرية في الاستعلام: الرسم الشهري لا يحتاج
     * دقة يومية، وإرسال ٣٦٥ نقطة إلى Chart.js لرسم اثنتي عشرة عمودياً هدرٌ
     * في الشبكة وفي المتصفح معاً.
     *
     * @return array<int, array<string, mixed>>
     */
    public function monthlySeries(int $months = 12): array
    {
        return Cache::remember("metrics.monthly.{$months}", self::CACHE_TTL, function () use ($months): array {
            $from = now()->subMonthsNoOverflow($months - 1)->startOfMonth();

            $rows = DailyMetric::query()
                ->where('date', '>=', $from->toDateString())
                ->selectRaw("DATE_FORMAT(`date`, '%Y-%m') as month")
                ->selectRaw('SUM(new_sponsorships) as new_sponsorships')
                ->selectRaw('SUM(cancelled_sponsorships) as cancelled_sponsorships')
                ->selectRaw('SUM(revenue_base) as revenue')
                ->selectRaw('SUM(disbursed_base) as disbursed')
                ->selectRaw('MAX(sponsored_count) as sponsored')
                ->selectRaw('MAX(active_sponsors) as sponsors')
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->keyBy('month');

            // نملأ الشهور الغائبة بأصفار: فجوة في المحور الزمني تجعل الرسم يكذب
            $series = [];
            for ($i = 0; $i < $months; $i++) {
                $cursor = $from->copy()->addMonthsNoOverflow($i);
                $key = $cursor->format('Y-m');
                $row = $rows->get($key);

                $series[] = [
                    'month' => $key,
                    'label' => $cursor->translatedFormat('M Y'),
                    'new_sponsorships' => (int) ($row->new_sponsorships ?? 0),
                    'cancelled_sponsorships' => (int) ($row->cancelled_sponsorships ?? 0),
                    'net' => (int) (($row->new_sponsorships ?? 0) - ($row->cancelled_sponsorships ?? 0)),
                    'revenue' => round((float) ($row->revenue ?? 0), 2),
                    'disbursed' => round((float) ($row->disbursed ?? 0), 2),
                    'sponsored' => (int) ($row->sponsored ?? 0),
                    'sponsors' => (int) ($row->sponsors ?? 0),
                ];
            }

            return $series;
        });
    }

    /* ================================================================
       ٣. التوزيعات
       ================================================================ */

    /** @return array<int, array{label: string, value: int, extra: mixed}> */
    public function byCountry(): array
    {
        return Cache::remember('metrics.by_country', self::CACHE_TTL, fn (): array => DB::table('orphans')
            ->join('families', 'families.id', '=', 'orphans.family_id')
            ->join('countries', 'countries.id', '=', 'families.country_id')
            ->whereNull('orphans.deleted_at')
            ->selectRaw('countries.name_ar as label, countries.flag_emoji as extra')
            ->selectRaw('COUNT(*) as value')
            ->selectRaw('ROUND(AVG(orphans.funded_percent)) as funded')
            ->groupBy('countries.id', 'countries.name_ar', 'countries.flag_emoji')
            ->orderByDesc('value')
            ->get()
            ->map(static fn ($r): array => [
                'label' => $r->label,
                'value' => (int) $r->value,
                'extra' => $r->extra,
                'funded' => (int) $r->funded,
            ])
            ->all());
    }

    /** @return array<int, array<string, mixed>> */
    public function byProgram(): array
    {
        return Cache::remember('metrics.by_program', self::CACHE_TTL, fn (): array => DB::table('programs')
            ->leftJoin('orphans', function ($join): void {
                $join->on('orphans.program_id', '=', 'programs.id')
                    ->whereNull('orphans.deleted_at');
            })
            ->leftJoin('sponsorships', function ($join): void {
                $join->on('sponsorships.program_id', '=', 'programs.id')
                    ->where('sponsorships.status', '=', SponsorshipStatus::Active->value)
                    ->whereNull('sponsorships.deleted_at');
            })
            ->selectRaw('programs.slug, programs.name_ar as label, programs.accent')
            ->selectRaw('COUNT(DISTINCT orphans.id) as orphans')
            ->selectRaw('COUNT(DISTINCT sponsorships.id) as sponsorships')
            ->selectRaw('COALESCE(SUM(DISTINCT sponsorships.monthly_amount), 0) as amount')
            ->groupBy('programs.id', 'programs.slug', 'programs.name_ar', 'programs.accent')
            ->orderBy('programs.sort_order')
            ->get()
            ->map(static fn ($r): array => [
                'slug' => $r->slug,
                'label' => $r->label,
                'accent' => $r->accent,
                'orphans' => (int) $r->orphans,
                'sponsorships' => (int) $r->sponsorships,
            ])
            ->all());
    }

    /**
     * توزيع الحالات على شرائح التمويل.
     * الشريحة الأولى (بلا كفالة) هي ما يجب أن تنظر إليه الإدارة كل صباح.
     *
     * @return array<int, array{label: string, value: int}>
     */
    public function fundingBuckets(): array
    {
        return Cache::remember('metrics.funding_buckets', self::CACHE_TTL, function (): array {
            $row = Orphan::query()
                ->where('status', OrphanStatus::Active)
                ->selectRaw('SUM(funded_percent = 0) as none')
                ->selectRaw('SUM(funded_percent > 0 AND funded_percent < 50) as low')
                ->selectRaw('SUM(funded_percent >= 50 AND funded_percent < 100) as mid')
                ->selectRaw('SUM(funded_percent >= 100) as full')
                ->first();

            return [
                ['label' => 'بلا كفالة', 'value' => (int) $row->none],
                ['label' => 'أقل من ٥٠٪', 'value' => (int) $row->low],
                ['label' => '٥٠٪ إلى ٩٩٪', 'value' => (int) $row->mid],
                ['label' => 'مكتمل', 'value' => (int) $row->full],
            ];
        });
    }

    /**
     * معدّل الاستمرار: كم شهراً تدوم الكفالة وسطياً، وكم نسبة الانقطاع.
     * الرقم الذي يقيس صحة المنصة فعلاً — لا عدد الكفالات الجديدة وحده.
     *
     * @return array<string, mixed>
     */
    public function retention(): array
    {
        return Cache::remember('metrics.retention', self::CACHE_TTL, function (): array {
            $ended = Sponsorship::query()
                ->whereNotNull('ended_at')
                ->whereNotNull('started_at')
                ->selectRaw('COUNT(*) as n')
                ->selectRaw('AVG(TIMESTAMPDIFF(MONTH, started_at, ended_at)) as avg_months')
                ->first();

            $live = Sponsorship::query()
                ->live()
                ->whereNotNull('started_at')
                ->selectRaw('COUNT(*) as n')
                ->selectRaw('AVG(TIMESTAMPDIFF(MONTH, started_at, NOW())) as avg_months')
                ->first();

            $total = (int) $ended->n + (int) $live->n;

            return [
                'active_count' => (int) $live->n,
                'ended_count' => (int) $ended->n,
                'churn_percent' => $total > 0 ? round((int) $ended->n * 100 / $total, 1) : 0.0,
                'avg_active_months' => round((float) $live->avg_months, 1),
                'avg_lifetime_months' => round((float) $ended->avg_months, 1),
                'reasons' => Sponsorship::query()
                    ->whereNotNull('cancellation_reason')
                    ->selectRaw('cancellation_reason as label, COUNT(*) as value')
                    ->groupBy('cancellation_reason')
                    ->orderByDesc('value')
                    ->limit(6)
                    ->get()
                    ->map(static fn ($r): array => ['label' => $r->label, 'value' => (int) $r->value])
                    ->all(),
            ];
        });
    }

    /* ================================================================
       ٤. بناء صفّ يوم واحد في جدول التجميع
       ================================================================ */

    /**
     * يستدعيه أمر `metrics:rollup`.
     *
     * `sponsored_count` و `active_sponsors` تُحسبان **كما كانتا في ذلك اليوم**
     * لا كما هما اليوم: الكفالة تُحتسب إن بدأت قبل التاريخ ولم تنتهِ قبله.
     * الحساب بالحالة الراهنة يجعل كل تاريخ في الرسم البياني يعرض رقم اليوم،
     * فيخرج خطٌّ مسطّح كاذب.
     */
    public function rollupDay(Carbon $date): DailyMetric
    {
        $day = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();
        $dateString = $day->toDateString();

        $liveOnDate = Sponsorship::withTrashed()
            ->where('started_at', '<=', $dateString)
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', $dayEnd));

        $metric = DailyMetric::query()->firstOrNew(['date' => $dateString]);

        $metric->fill([
            'sponsored_count' => (clone $liveOnDate)->distinct()->count('orphan_id'),
            'active_sponsors' => (clone $liveOnDate)->distinct()->count('sponsor_id'),

            'new_sponsorships' => Sponsorship::withTrashed()
                ->whereDate('started_at', $dateString)
                ->count(),

            'cancelled_sponsorships' => Sponsorship::withTrashed()
                ->whereBetween('ended_at', [$day, $dayEnd])
                ->count(),

            'revenue_base' => (float) Payment::query()
                ->where('status', PaymentStatus::Succeeded)
                ->whereBetween('paid_at', [$day, $dayEnd])
                ->sum('amount_base'),

            'disbursed_base' => (float) Disbursement::query()
                ->whereNotNull('approved_at')
                ->whereDate('spent_on', $dateString)
                ->sum('amount_base'),

            'pending_orphans' => Orphan::withTrashed()
                ->where('status', OrphanStatus::PendingVerification)
                ->where('created_at', '<=', $dayEnd)
                ->count(),

            'urgent_unfunded' => Orphan::withTrashed()
                ->where('is_urgent', true)
                ->where('funded_percent', '<', 100)
                ->where('created_at', '<=', $dayEnd)
                ->count(),

            'pending_reports' => Report::query()
                ->where('status', ReportStatus::Submitted)
                ->where('created_at', '<=', $dayEnd)
                ->count(),

            'pending_messages' => Message::query()
                ->where('status', MessageStatus::PendingReview)
                ->where('created_at', '<=', $dayEnd)
                ->count(),
        ]);

        $metric->save();

        return $metric;
    }

    public function flushCache(): void
    {
        foreach (['kpis', 'by_country', 'by_program', 'funding_buckets', 'retention'] as $key) {
            Cache::forget("metrics.{$key}");
        }

        foreach ([6, 12, 24] as $months) {
            Cache::forget("metrics.monthly.{$months}");
        }
    }
}
