<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sender_type' => $this->sender_type,
            'body' => $this->body,
            'audio_url' => $this->audio_path ? asset('storage/'.$this->audio_path) : null,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'rejection_reason' => $this->rejection_reason,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            'sponsorship' => $this->whenLoaded('sponsorship', fn () => [
                'id' => $this->sponsorship->id,
                'reference_code' => $this->sponsorship->reference_code,
                'orphan_name' => $this->sponsorship->relationLoaded('orphan')
                    ? $this->sponsorship->orphan?->first_name
                    : null,
            ]),
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer?->name),
        ];
    }
}
