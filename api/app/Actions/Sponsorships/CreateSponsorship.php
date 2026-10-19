<?php

declare(strict_types=1);

namespace App\Actions\Sponsorships;

use App\Enums\SponsorshipStatus;
use App\Models\Orphan;
use App\Models\Program;
use App\Models\Sponsor;
use App\Models\Sponsorship;
use Illuminate\Support\Carbon;
use App\Support\Fx;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * إنشاء كفالة (حجزٌ بانتظار أول دفعة).
 *
 * القواعد تعيش هنا لا في المتحكّم، لأن الكفالة تُنشأ من ثلاثة مسارات
 * (صفحة الكفالة على الموقع، ولوحة الكافل، ولوحة الإدارة) ولا يجوز أن تختلف:
 *
 *  1. المبلغ لا يتجاوز ما تبقّى من حاجة الطفل، والمتبقي يُحسب من مبالغ
 *     الكفالات القائمة لا من نسبة التمويل المقرَّبة.
 *  2. يوم الفوترة 1..28 فلا يختفي القسط في فبراير، والدورة 1 أو 3 أو 12 شهراً.
 *  3. لا كفالة على ملف لا يقبلها، ويُعاد الفحص بعد القفل.
 *  4. الحجز يُمسك حصة الطفل، فلا يُكرَّر للطفل نفسه، ولا تتجاوز حجوزات
 *     الكافل غير المدفوعة حدّاً صغيراً. بلا هذا كان حسابٌ واحد يحجز كل
 *     الأطفال فيظهرون «مكتملي التمويل» ويختفون من الموقع أياماً.
 */
class CreateSponsorship
{
    /** أقصى حجوزات غير مدفوعة لكافل واحد في وقت واحد */
    public const MAX_OPEN_HOLDS = 3;

    public const INTERVALS = [1, 3, 12];

    public function handle(
        Sponsor $sponsor,
        Orphan $orphan,
        ?float $monthlyAmount = null,
        bool $isZakat = false,
        ?int $billingDay = null,
        int $intervalMonths = 1,
        ?string $paymentMethod = null,
        ?string $currency = null,
    ): Sponsorship {
        if (! in_array($intervalMonths, self::INTERVALS, true)) {
            throw ValidationException::withMessages(['interval_months' => 'دورة الدفع شهرية أو كل 3 أشهر أو سنوية.']);
        }

        // عملة الكافل: مبلغه بها، ويُحسب ما يعادله بالأساس (App\Support\Fx)
        $currency = $currency ? strtoupper($currency) : null;
        if ($currency !== null && ! Fx::isSponsorCurrency($currency)) {
            throw ValidationException::withMessages(['currency' => __('لا نقبل هذه العملة بعد. اختر من القائمة.')]);
        }

        return DB::transaction(function () use ($sponsor, $orphan, $monthlyAmount, $isZakat, $billingDay, $intervalMonths, $paymentMethod, $currency): Sponsorship {
            /**
             * قفل صف الطفل يمنع سباقاً واقعياً: كافلان يضغطان «اكفل» في اللحظة
             * نفسها. بلا القفل يمرّ الاثنان من فحص التمويل ويتجاوز المجموع الحاجة.
             */
            $orphan = Orphan::query()->lockForUpdate()->findOrFail($orphan->id);

            // الحالة بعد القفل: قد يُخفى الملف أو يتخرّج بين فتح الصفحة والضغط
            if (! $orphan->status->acceptsSponsorship()) {
                throw ValidationException::withMessages([
                    'orphan_reference' => 'هذا الملف لا يقبل كفالة جديدة حالياً.',
                ]);
            }

            $mine = $sponsor->sponsorships()->where('orphan_id', $orphan->id)->live()->first();
            if ($mine !== null) {
                throw ValidationException::withMessages([
                    'orphan_reference' => $mine->status === SponsorshipStatus::Pending
                        ? 'لديك حجز قائم لهذا الطفل بانتظار الدفع. أكمل دفعته من لوحتك.'
                        : 'أنت تكفل هذا الطفل بالفعل.',
                ]);
            }

            $holds = $sponsor->sponsorships()->where('status', SponsorshipStatus::Pending)->count();
            if ($holds >= self::MAX_OPEN_HOLDS) {
                throw ValidationException::withMessages([
                    'orphan_reference' => 'لديك '.self::MAX_OPEN_HOLDS.' حجوزات بانتظار الدفع. أكمل دفعها أو ألغِها قبل حجز طفل آخر.',
                ]);
            }

            $program = Program::findOrFail($orphan->program_id);

            $remaining = $orphan->remainingNeed();
            $foreign = $currency !== null && $currency !== $program->currency;

            if ($remaining <= 0) {
                throw ValidationException::withMessages([
                    'orphan_reference' => 'اكتمل تمويل هذا الطفل. اختر حالة أخرى تنتظر كافلاً.',
                ]);
            }

            if ($foreign) {
                // بعملة الكافل: الحدود نفسها محوّلةً ومقرّبة لوحدةٍ كاملة لصالح الطفل
                $maxCharge = Fx::friendly($remaining, $currency);
                $charge = round($monthlyAmount ?? Fx::friendly(min((float) $program->monthly_price, $remaining), $currency), 2);

                if ($charge > $maxCharge) {
                    throw ValidationException::withMessages([
                        'monthly_amount' => __('المتبقي من حاجة هذا الطفل :amount شهرياً. اختر مبلغاً لا يتجاوزه.', ['amount' => number_format($maxCharge, 2).' '.$currency]),
                    ]);
                }
                $minCharge = Fx::friendly(5, $currency);
                if ($charge < $minCharge && $charge < $maxCharge) {
                    throw ValidationException::withMessages([
                        'monthly_amount' => __('أقل مبلغ للكفالة :amount شهرياً.', ['amount' => number_format($minCharge, 0).' '.$currency]),
                    ]);
                }

                $amount = Fx::toBase($charge, $currency);
            } else {
                $charge = null;
                $amount = round($monthlyAmount ?? min((float) $program->monthly_price, $remaining), 2);
            }

            // مبلغٌ أكبر من المتبقي يُرفض بوضوح: كان يُخفَّض بصمت فيُخصم غير ما رآه الكافل
            if (! $foreign && $amount > $remaining) {
                throw ValidationException::withMessages([
                    'monthly_amount' => 'المتبقي من حاجة هذا الطفل '.number_format($remaining, 2).' '.$program->currency.' شهرياً. اختر مبلغاً لا يتجاوزه.',
                ]);
            }

            // الحدّ الأدنى 5، إلا أن يكون هو كل ما تبقّى (فلا يبقى كسرٌ لا يكفله أحد)
            if (! $foreign && $amount < 5 && $amount < $remaining) {
                throw ValidationException::withMessages([
                    'monthly_amount' => 'أقل مبلغ للكفالة 5 '.$program->currency.' شهرياً.',
                ]);
            }

            $day = max(1, min((int) config('kafala.billing.max_billing_day'), $billingDay ?? (int) now()->day));

            $sponsorship = new Sponsorship([
                'sponsor_id' => $sponsor->id,
                'orphan_id' => $orphan->id,
                'program_id' => $program->id,
                'monthly_amount' => $amount,
                'currency' => $program->currency,
                'charge_currency' => $foreign ? $currency : null,
                'charge_amount' => $charge,
                'share_percent' => (int) min(100, round($amount / (float) $orphan->monthly_need * 100)),
                'is_zakat' => $isZakat,
                'status' => SponsorshipStatus::Pending,
                'billing_day' => $day,
                'interval_months' => $intervalMonths,
                'payment_method' => $paymentMethod,
                'started_at' => now()->toDateString(),
                // أول قسط اليوم: الدفعة الأولى تُحسب منه وتقدّمه دورةً كاملة
                'next_due_at' => now()->toDateString(),
            ]);

            $sponsorship->reference_code = $this->nextReference();
            // SponsorshipObserver يعيد حساب تمويل الطفل بعد الحفظ
            $sponsorship->save();

            return $sponsorship;
        });
    }

    /**
     * الترقيم داخل المعاملة نفسها وبقفل — وإلا حصل طلبان متزامنان على
     * الرقم نفسه وفشل القيد الفريد على reference_code.
     */
    private function nextReference(): string
    {
        $year = now()->year;
        $last = Sponsorship::query()
            ->where('reference_code', 'like', "KFS-{$year}-%")
            ->lockForUpdate()
            ->max('reference_code');

        $next = $last ? ((int) substr($last, -5)) + 1 : 1;

        return sprintf('KFS-%d-%05d', $year, $next);
    }
}
