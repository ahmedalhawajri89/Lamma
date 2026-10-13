<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FieldVisitResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'visited_at' => $this->visited_at?->toIso8601String(),
            'findings' => $this->findings,
            'needs_flagged' => $this->needs_flagged ?? [],
            'next_visit_due' => $this->next_visit_due?->toDateString(),
            // تعريف «متأخرة» نفسه في العدّاد (FieldVisit::scopeOverdue): موعد مضى
            // قبل اليوم، ولم تتلُها زيارة أحدث للطفل نفسه
            'is_overdue' => $this->next_visit_due !== null
                && $this->next_visit_due->lt(now(config('kafala.display_timezone'))->startOfDay())
                && ! $this->has_later,
            'coordinates' => $this->latitude !== null
                ? ['lat' => (float) $this->latitude, 'lng' => (float) $this->longitude]
                : null,

            'orphan' => $this->whenLoaded('orphan', fn () => [
                'id' => $this->orphan->id,
                'reference_code' => $this->orphan->reference_code,
                'name' => $this->orphan->first_name,
            ]),
            'supervisor' => $this->whenLoaded('supervisor', fn () => $this->supervisor?->name),
        ];
    }
}
