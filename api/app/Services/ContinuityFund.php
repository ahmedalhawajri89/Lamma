<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Sponsorships\CancelSponsorship;
use App\Enums\FundEntryKind;
use App\Enums\PaymentStatus;
use App\Enums\SponsorshipStatus;
use App\Mail\PaymentIssueMail;
use App\Models\ContinuityFundEntry;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Sponsorship;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * صندوق الاستمرارية: لا ينقطع ما يصل الطفل لأن بطاقة كافله رُفضت.
 *
 * · الدخل: حصةٌ شهرية من نسبة التشغيل نفسها (finance.continuity_fund_percent
 *   من كل ما وصل، داخل الـ7٪ لا فوقها)، وتبرّعاتٌ يسجّلها المحاسب.
 * · التغطية: قسطٌ استنفد محاولاته ومضى على استحقاقه recovery_days يغطّيه
 *   الصندوق شهراً، فيُحوَّل مع تحويل الجمعية ويتقدّم موعد الكفالة شهراً.
 *   حتى max_months شهراً متتالية بلا دفعة من الكافل، ثم تنتهي الكفالة ويعود
 *   الطفل إلى الموقع ينتظر من يقف معه. وكذلك إن لم يكفِ الرصيد.
 */
final class ContinuityFund
{
    public const DEFAULT_PERCENT = 1.0;

    /** النسبة من كل ما وصل في الشهر، ولا تتجاوز نسبة التشغيل التي تُقتطع منها. */
    public static function percent(): float
    {
        $value = Setting::cached()['finance.continuity_fund_percent'] ?? null;
        $percent = is_numeric($value) ? (float) $value : self::DEFAULT_PERCENT;

        return max(0.0, min($percent, Payment::currentFeePercent()));
    }

    public static function maxMonths(): int
    {
        return (int) config('kafala.continuity.max_months', 3);
    }

    /**
     * ما يُنشر على صفحة الشفافية: الرصيد، وما دخل وما غطّى في السنة، وكم
     * طفلاً لم ينقطع عنه شيء بفضله. أرقامٌ من القيود نفسها.
     *
     * @return array<string, mixed>
     */
    public function publicSummary(int $year): array
    {
        $from = Carbon::create($year, 1, 1)->startOfDay();
        $to = $from->copy()->addYear();
        // بشهر القيد لا بيوم كتابته: حصة ديسمبر تُقيَّد أول يناير وهي من سنتها
        $inYear = fn (array $kinds) => ContinuityFundEntry::query()->whereIn('kind', $kinds)->whereBetween('period', [$from->toDateString(), $to->copy()->subDay()->toDateString()]);

        return [
            'year' => $year,
            'balance' => ContinuityFundEntry::balance(),
            'percent' => self::percent(),
            'max_months' => self::maxMonths(),
            'in' => round((float) $inYear([FundEntryKind::Share->value, FundEntryKind::Donation->value])->sum('amount_base'), 2),
            'covered' => round((float) $inYear([FundEntryKind::Grant->value])->sum('amount_base'), 2),
            'months_covered' => $inYear([FundEntryKind::Grant->value])->count(),
            'children' => $inYear([FundEntryKind::Grant->value])->distinct()->count('orphan_id'),
        ];
    }

    /** حصة الشهر من التشغيل: تُعاد بأمان، فتُحدَّث بما استجدّ من دفعاته. */
    public function accrueShare(Carbon $month): ?ContinuityFundEntry
    {
        $start = $month->copy()->startOfMonth();
        $collected = (float) Payment::query()
            ->where('status', PaymentStatus::Succeeded)
            ->where('paid_at', '>=', $start)
            ->where('paid_at', '<', $start->copy()->addMonth())
            ->sum('amount_base');

        $percent = self::percent();
        $amount = round($collected * $percent / 100, 2);

        return DB::transaction(function () use ($start, $amount, $percent, $collected): ?ContinuityFundEntry {
            $entry = ContinuityFundEntry::query()
                ->where('kind', FundEntryKind::Share)
                ->whereDate('period', $start->toDateString())
                ->lockForUpdate()
                ->first();

            if ($entry === null && $amount <= 0) {
                return null;
            }

            $entry ??= new ContinuityFundEntry(['kind' => FundEntryKind::Share, 'period' => $start->toDateString()]);
            $entry->fill([
                'amount_base' => $amount,
                'note' => sprintf('%s٪ من %s وصلت في الشهر', rtrim(rtrim(number_format($percent, 2), '0'), '.'), number_format($collected, 2)),
            ])->save();

            return $entry;
        });
    }

    /**
     * أشهرٌ غطّاها الصندوق لهذه الكفالة منذ آخر دفعةٍ وصلت من كافلها.
     * دفعةٌ واحدة منه تعيد العدّ.
     */
    public function monthsCovered(Sponsorship $sponsorship): int
    {
        $lastPaid = $sponsorship->payments()->where('status', PaymentStatus::Succeeded)->max('paid_at');

        return ContinuityFundEntry::query()
            ->where('kind', FundEntryKind::Grant)
            ->where('sponsorship_id', $sponsorship->id)
            ->when($lastPaid, fn ($q) => $q->where('created_at', '>', $lastPaid))
            ->count();
    }

    /**
     * التغطية اليومية (kafala:continuity-cover بعد billing:run).
     *
     * @return array{covered: int, ended: int, total: float}
     */
    public function coverDue(): array
    {
        $summary = ['covered' => 0, 'ended' => 0, 'total' => 0.0];

        $due = Sponsorship::query()
            ->where('status', SponsorshipStatus::Active)
            ->whereNotNull('payment_issue_since')
            ->where('payment_failures', '>=', Sponsorship::maxPaymentAttempts())
            ->whereDate('next_due_at', '<=', today()->subDays((int) config('kafala.continuity.recovery_days', 14)))
            ->with(['orphan:id,first_name,family_id,translations,reference_code', 'orphan.family:id,association_id', 'sponsor:id,user_id', 'sponsor.user:id,name,email,locale'])
            ->get();

        foreach ($due as $sponsorship) {
            $covered = $this->monthsCovered($sponsorship);
            $amount = round((float) $sponsorship->monthly_amount, 2);

            if ($covered >= self::maxMonths() || ContinuityFundEntry::balance() < $amount) {
                $this->end($sponsorship, $covered);
                $summary['ended']++;

                continue;
            }

            DB::transaction(function () use ($sponsorship, $amount): void {
                ContinuityFundEntry::create([
                    'kind' => FundEntryKind::Grant,
                    'amount_base' => $amount,
                    // الشهر المغطّى: قسطٌ واحد لكل كفالة في كل شهر استحقاق
                    'period' => $sponsorship->next_due_at->copy()->startOfMonth()->toDateString(),
                    'sponsorship_id' => $sponsorship->id,
                    'orphan_id' => $sponsorship->orphan_id,
                    'association_id' => $sponsorship->orphan?->family?->association_id,
                    'note' => "قسط {$sponsorship->reference_code} المتعثّر",
                ]);

                $sponsorship->advanceDueDate(1);
                $sponsorship->clearPaymentIssue();
                $sponsorship->save();
            });

            $summary['covered']++;
            $summary['total'] += $amount;
            $this->notify($sponsorship, 'covered', self::maxMonths() - $covered - 1);
        }

        $summary['total'] = round($summary['total'], 2);

        return $summary;
    }

    /** نفدت أشهر التغطية أو الرصيد: تنتهي الكفالة ويعود الطفل إلى الموقع. */
    private function end(Sponsorship $sponsorship, int $covered): void
    {
        // الطفل محمّلٌ بأعمدةٍ قليلة، ومراقب الكفالة يعيد حساب تمويله كاملاً
        $sponsorship->unsetRelation('orphan');
        app(CancelSponsorship::class)->handle($sponsorship, $covered > 0
            ? "تعذّر تحصيل القسط بعد أن غطّى صندوق الاستمرارية {$covered} أشهر"
            : 'تعذّر تحصيل القسط ولم يكفِ رصيد صندوق الاستمرارية');

        $sponsorship->clearPaymentIssue();
        $sponsorship->save();

        activity('sponsorships')
            ->performedOn($sponsorship)
            ->withProperties(['by' => 'continuity_fund', 'months_covered' => $covered])
            ->log('انتهت الكفالة لتعذّر تحصيل القسط');

        // في «ended» يحمل monthsLeft عدد الأشهر التي غُطّيت: البريد لا يدّعي تغطيةً لم تكن
        $this->notify($sponsorship, 'ended', $covered);
    }

    private function notify(Sponsorship $sponsorship, string $stage, ?int $monthsLeft = null): void
    {
        $user = $sponsorship->sponsor?->user;
        if (! $user?->email) {
            return;
        }

        try {
            Mail::to($user)->send(new PaymentIssueMail($sponsorship, '', $stage, $monthsLeft));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
