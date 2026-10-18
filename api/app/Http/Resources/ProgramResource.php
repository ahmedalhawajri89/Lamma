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
            'name' => \App\Support\Locale::pick($this->name_ar, $this->name_en),
            'name_en' => $this->name_en,
            'icon' => $this->icon,
            'accent' => $this->accent,
            'price' => (float) $this->monthly_price,
            'currency' => $this->currency,
            'summary' => \App\Support\Locale::pick($this->summary_ar, $this->summary_en),
            'description' => \App\Support\Locale::pick($this->description_ar, $this->description_en),
            'featured' => $this->is_featured,
            'features' => $this->whenLoaded(
                'features',
                fn () => $this->features->map(fn ($f) => \App\Support\Locale::pick($f->label_ar, $f->label_en))->all(),
            ),
            'orphans_count' => $this->whenCounted('orphans'),
        ];
    }
}
