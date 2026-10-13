<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $staff = (bool) $request->user()?->can('payments.view');

        return [
            'id' => $this->id,
            'reference_code' => $this->reference_code,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'amount_base' => (float) $this->amount_base,
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),

            // لا PAN ولا CVV — العلامة وآخر أربعة أرقام فقط، وهذا كل ما نملكه أصلاً
            'card' => $this->maskedCard(),

            'paid_at' => $this->paid_at?->toIso8601String(),
            // للموظفين النصّ كما وصل والمرجع الداخلي؛ وللكافل جملة مفهومة بلا تفاصيل البوابة
            'failure_reason' => $this->failure_reason === null ? null
                : ($staff ? $this->failure_reason : 'تعذّر إتمام الدفع من البطاقة. جرّب بطاقة أخرى أو تواصل مع بنكك.'),
            'gateway_reference' => $this->when($staff, $this->gateway_reference),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'refund_reason' => $this->refund_reason,
            'receipt_number' => $this->receipt_number,
            'created_at' => $this->created_at?->toIso8601String(),

            'sponsorship' => new SponsorshipResource($this->whenLoaded('sponsorship')),
            'sponsor' => new SponsorResource($this->whenLoaded('sponsor')),
        ];
    }
}
