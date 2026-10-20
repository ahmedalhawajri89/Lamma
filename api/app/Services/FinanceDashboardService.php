<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\SponsorshipStatus;
use App\Models\Disbursement;
use App\Models\Payment;
use App\Models\Sponsorship;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * لوحة المحاسب: المال هذا الشهر، لا صورة المنصة كلها.
 *
 * الأسئلة التي يفتح بها المحاسب يومه: كم حصّلنا من المتوقع؟ ما الذي فشل
 * ولم يُستدرك (ومَن أتصل به)؟ ما الأقساط القادمة؟ كم صُرف، وما الذي ينتظر
 * اعتماداً أو إيصالاً؟ وهل التشغيل ضمن هدفه؟ كل رقم هنا يقود إلى قائمته.
 */
class FinanceDashboardService
{
    /** @return array<string, mixed> */
    public function build(): array
    {
        $tz = config('kafala.display_timezone');
        $now = Carbon::now($tz);
        $monthStart = $now->copy()->startOfMonth()->utc();
        $monthEnd = $now->copy()->endOfMonth()->utc();
        $today = $now->copy()->startOfDay();

        $paid = Payment::where('status', PaymentStatus::Succeeded)->whereBetween('paid_at', [$monthStart, $monthEnd]);
        $collected = (float) (clone $paid)->sum('amount_base');

        // الشهر الماضي حتى اليوم نفسه منه: مقارنة عادلة لشهرٍ لم يكتمل
        $lastStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $lastSame = (float) Payment::where('status', PaymentStatus::Succeeded)
            ->whereBetween('paid_at', [$lastStart->copy()->utc(), $lastStart->copy()->addDays($now->day - 1)->endOfDay()->utc()])
            ->sum('amount_base');

        // ما بقي من أقساط هذا الشهر على كفالات قائمة
        $upcomingMonth = Sponsorship::where('status', SponsorshipStatus::Active)
            ->whereBetween('next_due_at', [$today->toDateString(), $now->copy()->endOfMonth()->toDateString()]);
        $upcoming = $this->cycleTotal($upcomingMonth);

        $unrecovered = $this->unrecovered(Payment::query());
        $unrecoveredMonth = $this->unrecovered(Payment::query()->whereBetween('payments.created_at', [$monthStart, $monthEnd]));
        $unrecoveredAmount = (float) (clone $unrecoveredMonth)->sum('payments.amount_base');

        $expected = $collected + $upcoming + $unrecoveredAmount;

        // متأخرة: كفالة قائمة فات موعد قسطها ولم يُسجَّل
        $overdue = Sponsorship::where('status', SponsorshipStatus::Active)->where('next_due_at', '<', $today->toDateString());

        $disbMonth = Disbursement::whereBetween('spent_on', [$now->copy()->startOfMonth()->toDateString(), $now->toDateString()]);
        $pending = Disbursement::whereNull('approved_at');

        return [
            'month' => [
                'label' => $now->locale('ar')->translatedFormat('F Y'),
                'day' => $now->day,
                'days_in_month' => $now->daysInMonth,
                'collected' => round($collected, 2),
                'collected_count' => (clone $paid)->count(),
                'upcoming' => round($upcoming, 2),
                'upcoming_count' => (clone $upcomingMonth)->count(),
                'unrecovered' => round($unrecoveredAmount, 2),
                'unrecovered_count' => (clone $unrecoveredMonth)->count(),
                'expected' => round($expected, 2),
                'rate' => $expected > 0 ? round($collected * 100 / $expected, 1) : null,
                'change' => $lastSame > 0 ? round(($collected - $lastSame) * 100 / $lastSame, 1) : null,
                'refunded' => round((float) Payment::where('status', PaymentStatus::Refunded)->whereBetween('refunded_at', [$monthStart, $monthEnd])->sum('amount_base'), 2),
                'by_method' => (clone $paid)->selectRaw('method, count(*) as n, sum(amount_base) as total')->groupBy('method')->orderByDesc('total')->get()
                    // `method` مصبوب إلى PaymentMethod في الموديل: تسميته منه
                    ->map(fn ($r) => ['method' => $r->method->value, 'label' => $r->method->label(), 'count' => (int) $r->getAttribute('n'), 'amount' => round((float) $r->getAttribute('total'), 2)])->values(),
            ],

            'upcoming_days' => $this->upcomingDays($today, 30),

            'overdue' => ['count' => (clone $overdue)->count(), 'amount' => round($this->cycleTotal($overdue), 2)],

            // من يُتصل به: الأحدث فشلاً أولاً، بسببه وكم مضى عليه
            'to_recover' => (clone $unrecovered)
                ->with(['sponsor:id,user_id,sponsor_code', 'sponsor.user:id,name', 'sponsorship:id,reference_code'])
                ->latest('payments.created_at')->limit(5)->get()
                ->map(fn (Payment $p) => [
                    'id' => $p->id,
                    'reference' => $p->reference_code,
                    'sponsor' => $p->sponsor->user->name ?? $p->sponsor?->sponsor_code,
                    'sponsor_code' => $p->sponsor?->sponsor_code,
                    'sponsor_id' => $p->sponsor_id,
                    'amount' => round((float) $p->amount_base, 2),
                    'reason' => $p->failure_reason,
                    'failed_at' => $p->created_at?->toIso8601String(),
                ])->values(),
            'to_recover_total' => ['count' => (clone $unrecovered)->count(), 'amount' => round((float) (clone $unrecovered)->sum('payments.amount_base'), 2)],

            'disbursements' => [
                'month_total' => round((float) (clone $disbMonth)->whereNotNull('approved_at')->sum('amount_base'), 2),
                'month_count' => (clone $disbMonth)->count(),
                'pending_count' => (clone $pending)->count(),
                'pending_amount' => round((float) (clone $pending)->sum('amount_base'), 2),
                'no_receipt_month' => (clone $disbMonth)->whereNull('receipt_path')->count(),
            ],

            'overhead' => app(TransparencyService::class)->overhead($now->year),
        ];
    }

    /** مجموع الأقساط: المبلغ الشهري في عدد أشهر الدورة */
    private function cycleTotal(Builder $query): float
    {
        return (float) (clone $query)->selectRaw('COALESCE(SUM(monthly_amount * GREATEST(interval_months, 1)), 0) as t')->value('t');
    }

    /** @return array<int, array{date: string, count: int, amount: float}> */
    private function upcomingDays(Carbon $today, int $days): array
    {
        $rows = Sponsorship::where('status', SponsorshipStatus::Active)
            ->whereBetween('next_due_at', [$today->toDateString(), $today->copy()->addDays($days - 1)->toDateString()])
            ->selectRaw('DATE(next_due_at) as d, count(*) as n, sum(monthly_amount * GREATEST(interval_months, 1)) as total')
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        return collect(range(0, $days - 1))->map(function ($i) use ($today, $rows) {
            $d = $today->copy()->addDays($i)->toDateString();

            return ['date' => $d, 'count' => (int) ($rows[$d]->n ?? 0), 'amount' => round((float) ($rows[$d]->total ?? 0), 2)];
        })->all();
    }

    /**
     * فشلت ولم تُستدرك: دفعة فاشلة لكفالة ما زالت قائمة، ولم تأتِ بعدها دفعة
     * ناجحة على الكفالة نفسها. التعريف نفسه في شاشة المدفوعات.
     *
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    private function unrecovered(Builder $query): Builder
    {
        return $query->where('payments.status', PaymentStatus::Failed)
            ->whereHas('sponsorship', fn ($s) => $s->whereIn('status', ['active', 'pending', 'paused']))
            ->whereNotExists(fn ($later) => $later->from('payments as later')
                ->whereColumn('later.sponsorship_id', 'payments.sponsorship_id')
                ->where('later.status', PaymentStatus::Succeeded->value)
                ->whereRaw('COALESCE(later.paid_at, later.created_at) > payments.created_at'));
    }
}
