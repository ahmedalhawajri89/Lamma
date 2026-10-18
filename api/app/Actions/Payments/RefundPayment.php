<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * استرداد دفعة.
 *
 * كان تغييرَ حالة وحده: سببه يُكتب فوق `failure_reason`، ولا يُعرف متى ولا
 * من، وموعد القسط القادم يبقى متقدّماً شهراً كأن الدفعة ما زالت قائمة،
 * فيبدو الكافل مسدَّداً وهو ليس كذلك.
 *
 * والدفعة لا تُحذف ولا يُعدَّل مبلغها: حذف صفٍّ مالي يجعل التدقيق مستحيلاً.
 */
class RefundPayment
{
    public function handle(Payment $payment, User $by, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $by, $reason): Payment {
            // قفل الصف: مستردّان في اللحظة نفسها لا يردّان المبلغ مرتين
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            abort_unless($payment->status === PaymentStatus::Succeeded, 409, 'هذه الدفعة ليست قائمة فتُستردّ.');

            $payment->forceFill([
                'status' => PaymentStatus::Refunded,
                'refunded_at' => now(),
                'refunded_by' => $by->id,
                'refund_reason' => $reason,
            ])->save();

            /*
             * الدفعة قدّمت موعد القسط بما غطّته من أشهر، فاستردادها يعيده بالقدر
             * نفسه. كان يعيده شهراً واحداً دائماً، فاسترداد دفعة سنوية يترك
             * الكافل «مسدَّداً» أحد عشر شهراً لم يدفعها.
             */
            $sponsorship = $payment->sponsorship()->lockForUpdate()->first();
            if ($sponsorship !== null && $sponsorship->status->isLive() && $sponsorship->next_due_at !== null) {
                $sponsorship->rewindDueDate($sponsorship->monthsCoveredBy((float) $payment->amount));
                $sponsorship->save();
            }

            activity()->performedOn($payment)->causedBy($by)->event('refunded')
                ->withProperties(['amount' => (float) $payment->amount, 'currency' => $payment->currency, 'reason' => $reason])
                ->log('استردّ دفعة');

            return $payment;
        });
    }
}
