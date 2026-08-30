<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgramResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->slug,                 // الواجهة تعرف البرنامج بالـ slug كما في data.js
            'db_id' => $this->id,
            'name' => $this->name_ar,
            'name_en' => $this->name_en,
            'icon' => $this->icon,
            'accent' => $this->accent,
            'price' => (float) $this->monthly_price,
            'currency' => $this->currency,
            'summary' => $this->summary_ar,
            'description' => $this->description_ar,
            'featured' => $this->is_featured,
            'features' => $this->whenLoaded(
                'features',
                fn () => $this->features->pluck('label_ar')->all(),
            ),
            'orphans_count' => $this->whenCounted('orphans'),
        ];
    }
}
