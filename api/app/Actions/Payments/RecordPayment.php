<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SponsorshipStatus;
use App\Models\Payment;
use App\Models\Sponsorship;
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
    ): Payment {
        $existing = Payment::where('idempotency_key', $idempotencyKey)->first();

        if ($existing !== null) {
            return $existing;                       // الطلب نفسه وصل مرتين — لا تخصم ثانيةً
        }

        return DB::transaction(function () use (
            $sponsorship, $idempotencyKey, $amount, $method, $cardBrand, $cardLast4, $fxRate
        ): Payment {
            $value = $amount ?? (float) $sponsorship->monthly_amount;

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
                'status' => PaymentStatus::Succeeded,
                'card_brand' => $cardBrand,

                // آخر أربعة أرقام فقط — لا نملك غيرها ولا نريد
                'card_last4' => $cardLast4 ? substr($cardLast4, -4) : null,

                'paid_at' => now(),
                'receipt_number' => $this->nextReceipt(),
                'idempotency_key' => $idempotencyKey,
            ]);

            // أول دفعة تُفعّل الكفالة، وكل دفعة تدفع موعد الاستحقاق شهراً
            if ($sponsorship->status === SponsorshipStatus::Pending) {
                $sponsorship->status = SponsorshipStatus::Active;
            }

            $sponsorship->advanceDueDate();
            $sponsorship->save();

            return $payment;
        });
    }

    private function nextReference(): string
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
