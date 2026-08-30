<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'period_year' => $this->period_year,
            'period_quarter' => $this->period_quarter,
            'period_label' => $this->periodLabel(),
            'title' => $this->title,
            'summary' => $this->summary,
            'academic_note' => $this->academic_note,
            'health_note' => $this->health_note,
            'psychosocial_note' => $this->psychosocial_note,
            'spending_breakdown' => $this->spending_breakdown,
            'visit_date' => $this->visit_date?->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'published_at' => $this->published_at?->toIso8601String(),

            // تُضاف بـ withCount في استعلام بوابة الكافل
            'is_new' => $this->when(
                isset($this->views_count),
                fn (): bool => $this->views_count === 0,
            ),

            'orphan' => $this->whenLoaded('orphan', fn () => [
                'id' => $this->orphan->id,
                'reference_code' => $this->orphan->reference_code,
                'name' => $this->orphan->first_name,
                'photo_url' => $this->orphan->photo_path
                    ? asset('storage/'.$this->orphan->photo_path)
                    : null,
            ]),
            'supervisor' => $this->whenLoaded('supervisor', fn () => $this->supervisor?->name),
            'media' => $this->whenLoaded('media', fn () => $this->media->map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->type,
                'url' => asset('storage/'.$m->path),
                'caption' => $m->caption,
            ])->all()),
        ];
    }
}
