<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** سجل النشاط — من غيّر ماذا ومتى، وقيمة الحقل قبل التغيير وبعده. */
class ActivityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $properties = $this->properties?->toArray() ?? [];

        return [
            'id' => $this->id,
            'event' => $this->event,
            'description' => $this->description,
            'subject_type' => class_basename((string) $this->subject_type),
            'subject_id' => $this->subject_id,
            'causer' => $this->whenLoaded('causer', fn () => $this->causer?->name),
            'changes' => [
                'before' => $properties['old'] ?? [],
                'after' => $properties['attributes'] ?? [],
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
