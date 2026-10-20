<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\MessageSafety;
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
                'orphan_id' => $this->sponsorship->orphan_id,
                'orphan_name' => $this->sponsorship->relationLoaded('orphan')
                    ? $this->sponsorship->orphan?->first_name
                    : null,
                'orphan_code' => $this->sponsorship->relationLoaded('orphan') ? $this->sponsorship->orphan?->reference_code : null,
                'orphan_photo' => $this->sponsorship->relationLoaded('orphan') && $this->sponsorship->orphan?->photo_path
                    ? asset('storage/'.$this->sponsorship->orphan->photo_path) : null,
            ]),
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer?->name),
            // للموظفين: الكافل في الكفالة، وتنبيهات حماية الطفل في النص
            'sponsor_name' => $this->when(
                $this->relationLoaded('sponsorship') && $this->sponsorship->relationLoaded('sponsor'),
                fn () => $this->sponsorship->sponsor?->user?->name ?? $this->sponsorship->sponsor?->sponsor_code,
            ),
            'flags' => $this->when(
                (bool) $request->user()?->can('messages.review'),
                fn () => app(MessageSafety::class)->flags($this->body),
            ),
        ];
    }
}
