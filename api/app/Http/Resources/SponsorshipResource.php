<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SponsorshipResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /*
         * المال لمن يملك رؤيته، وللكافل في بوابته. المشرف الميداني بلا صلاحية
         * مالية كان يرى مبلغ كل كفالة وموعد قسطها وعدد دفعاتها.
         */
        $user = $request->user();
        $money = $user === null || $user->can('payments.view') || $user->sponsor !== null;

        return [
            'id' => $this->id,
            'reference_code' => $this->reference_code,
            'monthly_amount' => $this->when($money, fn () => (float) $this->monthly_amount),
            'currency' => $this->currency,
            'share_percent' => $this->share_percent,
            'is_zakat' => $this->is_zakat,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'billing_day' => $this->when($money, $this->billing_day),
            'interval_months' => (int) ($this->interval_months ?? 1),
            'cycle_amount' => $this->when($money, fn () => $this->cycleAmount()),
            'payment_method' => $this->payment_method,
            'paused_at' => $this->paused_at?->toDateString(),
            // الحجز بانتظار أول دفعة يسقط بعد مهلة: يعرف الكافل حتى متى
            'hold_expires_at' => $this->when(
                $this->status === \App\Enums\SponsorshipStatus::Pending,
                fn () => $this->created_at?->copy()->addDays((int) config('kafala.billing.grace_days'))->toDateString(),
            ),
            'started_at' => $this->started_at?->toDateString(),
            'next_due_at' => $this->when($money, fn () => $this->next_due_at?->toDateString()),
            // القسط المتعثّر: يعرف الكافل منذ متى، والمحاولة التالية، وسبب آخر رفض
            'payment_issue' => $this->when($money && $this->hasPaymentIssue(), fn () => [
                'since' => $this->payment_issue_since->toDateString(),
                'failures' => (int) $this->payment_failures,
                'next_retry_at' => $this->next_retry_at?->toDateString(),
                'retries_left' => max(0, \App\Models\Sponsorship::maxPaymentAttempts() - (int) $this->payment_failures),
                'amount' => $this->cycleAmount(),
                'card_last4' => $this->card_last4,
                'reason' => $this->payments()->where('status', \App\Enums\PaymentStatus::Failed)->latest('id')->value('failure_reason'),
            ]),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,

            'orphan' => $this->whenLoaded('orphan', fn () => [
                'id' => $this->orphan->id,
                'reference_code' => $this->orphan->reference_code,
                'name' => $this->orphan->first_name,
                'age' => $this->orphan->age,
                'photo_url' => $this->orphan->photo_path
                    ? asset('storage/'.$this->orphan->photo_path)
                    : null,
                // بديلٌ ثابت حين لا صورة: الرسم نفسه الذي يراه الموقع العام. يُحسب من
                // الجنس، فلا يظهر حيث جُلب الطفل بأعمدةٍ لا تشمله
                'illustration_url' => $this->when(
                    array_key_exists('gender', $this->orphan->getAttributes()),
                    fn () => $this->orphan->illustrationUrl(),
                ),
                'funded_percent' => $this->orphan->funded_percent,
            ]),
            'sponsor' => new SponsorResource($this->whenLoaded('sponsor')),
            'program' => new ProgramResource($this->whenLoaded('program')),
            // سجل دفعات الكفالة (صفحة تفاصيلها) لمن يرى المال
            'payments' => $this->when($money, fn () => PaymentResource::collection($this->whenLoaded('payments'))),

            'payments_count' => $this->when($money, fn () => $this->whenCounted('payments')),
            'total_paid' => $this->when(
                isset($this->payments_sum_amount_base),
                fn () => (float) $this->payments_sum_amount_base,
            ),
        ];
    }
}
