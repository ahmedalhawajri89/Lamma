<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SponsorshipStatus;
use App\Models\Payment;
use App\Models\Sponsorship;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * تسجيل دفعة.
 *
 * `idempotency_key` هو كل الحكاية: الكافل يضغط «ادفع» مرتين، أو تعيد البوابة
 * إرسال الـ webhook بعد مهلة، فيصل الطلب نفسه مرتين. القيد الفريد على العمود
 * يجعل المحاولة الثانية تفشل في المحرّك، ونحن نلتقط ذلك ونعيد الدفعة الأولى
 * بدل أن نخصم مرتين.
 *
 * لاحظ أن الحماية في قاعدة البيانات لا في هذا الكود: لو نُسي هذا الفحص يوماً
 * فسيقع خطأ صريح، لا خصمٌ مزدوج صامت.
 */
class RecordPayment
{
    public function handle(
        Sponsorship $sponsorship,
        string $idempotencyKey,
        ?float $amount = null,
        PaymentMethod $method = PaymentMethod::Card,
        ?string $cardBrand = null,
        ?string $cardLast4 = null,
        float $fxRate = 1.0,
        ?string $gatewayReference = null,
        ?\DateTimeInterface $paidAt = null,
        ?int $recordedBy = null,
    ): Payment {
        $existing = Payment::where('idempotency_key', $idempotencyKey)->first();

        if ($existing !== null) {
            return $existing;                       // الطلب نفسه وصل مرتين — لا تخصم ثانيةً
        }

        try {
            return $this->record($sponsorship, $idempotencyKey, $amount, $method, $cardBrand, $cardLast4, $fxRate, $gatewayReference, $paidAt, $recordedBy);
        } catch (UniqueConstraintViolationException) {
            /*
             * طلبان متزامنان (تبويبان، أو webhook يسابق الخصم الآلي) مرّا كلاهما
             * من الفحص أعلاه، وسبق أحدهما إلى القيد الفريد. الخاسر يعيد دفعة
             * الرابح بدل خطأ 500.
             */
            return Payment::where('idempotency_key', $idempotencyKey)->firstOrFail();
        }
    }

    private function record(
        Sponsorship $sponsorship,
        string $idempotencyKey,
        ?float $amount,
        PaymentMethod $method,
        ?string $cardBrand,
        ?string $cardLast4,
        float $fxRate,
        ?string $gatewayReference,
        ?\DateTimeInterface $paidAt,
        ?int $recordedBy,
    ): Payment {
        return DB::transaction(function () use (
            $sponsorship, $idempotencyKey, $amount, $method, $cardBrand, $cardLast4, $fxRate, $gatewayReference, $paidAt, $recordedBy
        ): Payment {
            // الافتراضي دورة كاملة (شهر، أو ثلاثة، أو سنة)
            $value = $amount ?? $sponsorship->cycleAmount();

            $payment = Payment::create([
                'reference_code' => $this->nextReference(),
                'sponsorship_id' => $sponsorship->id,
                'sponsor_id' => $sponsorship->sponsor_id,
                'amount' => $value,
                'currency' => $sponsorship->currency,
                'fx_rate' => $fxRate,
                'amount_base' => round($value * $fxRate, 2),
                'method' => $method,
                'gateway' => config('kafala.gateway', 'manual'),
                // رقم الحوالة أو الإيصال كما كتبه المحاسب: به تُطابَق الدفعات مع كشف البنك
                'gateway_reference' => $gatewayReference,
                'recorded_by' => $recordedBy,
                'status' => PaymentStatus::Succeeded,
                'card_brand' => $cardBrand,

                // آخر أربعة أرقام فقط — لا نملك غيرها ولا نريد
                'card_last4' => $cardLast4 ? substr($cardLast4, -4) : null,

                // تاريخ وصول المال فعلاً، لا تاريخ إدخاله: حوالة وصلت الأسبوع الماضي
                // تُحسب في فترتها
                'paid_at' => $paidAt ?? now(),
                'receipt_number' => $this->nextReceipt(),
                'idempotency_key' => $idempotencyKey,
            ]);

            // أول دفعة تُفعّل الكفالة، وكل دفعة تدفع موعد الاستحقاق شهراً
            if ($sponsorship->status === SponsorshipStatus::Pending) {
                $sponsorship->status = SponsorshipStatus::Active;
            }

            /*
             * الدفعة تقدّم الموعد بما تغطيه من أشهر: دفعة يدوية بمبلغ ستة أقساط
             * تغطي ستة أشهر، لا شهراً واحداً فتصير الكفالة متأخرة بعده.
             */
            $months = max(1, (int) floor(($value + 0.005) / max(0.01, (float) $sponsorship->monthly_amount)));
            $sponsorship->advanceDueDate($months);
            $sponsorship->save();

            return $payment;
        });
    }

    /** يُستدعى داخل معاملة: القفل يمنع رقمين متطابقين لطلبين متزامنين. */
    public function nextReference(): string
    {
        $last = Payment::query()->lockForUpdate()->max('reference_code');
        $next = $last ? ((int) substr($last, 4)) + 1 : 1;

        return 'PAY-'.str_pad((string) $next, 7, '0', STR_PAD_LEFT);
    }

    private function nextReceipt(): string
    {
        $last = Payment::query()->whereNotNull('receipt_number')->lockForUpdate()->max('receipt_number');
        $next = $last ? ((int) substr($last, 4)) + 1 : 1;

        return 'RCP-'.str_pad((string) $next, 7, '0', STR_PAD_LEFT);
    }
}
