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
        return [
            'id' => $this->id,
            'reference_code' => $this->reference_code,
            'monthly_amount' => (float) $this->monthly_amount,
            'currency' => $this->currency,
            'share_percent' => $this->share_percent,
            'is_zakat' => $this->is_zakat,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'billing_day' => $this->billing_day,
            'started_at' => $this->started_at?->toDateString(),
            'next_due_at' => $this->next_due_at?->toDateString(),
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
                'funded_percent' => $this->orphan->funded_percent,
            ]),
            'sponsor' => new SponsorResource($this->whenLoaded('sponsor')),
            'program' => new ProgramResource($this->whenLoaded('program')),

            'payments_count' => $this->whenCounted('payments'),
            'total_paid' => $this->when(
                isset($this->payments_sum_amount_base),
                fn () => (float) $this->payments_sum_amount_base,
            ),
        ];
    }
}
