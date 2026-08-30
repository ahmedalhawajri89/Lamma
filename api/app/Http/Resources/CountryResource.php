<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CountryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'iso2' => $this->iso2,
            'name' => $this->name_ar,
            'name_en' => $this->name_en,
            'flag' => $this->flag_emoji,
            'is_operational' => $this->is_operational,
            'orphans_count' => $this->whenCounted('orphans'),
        ];
    }
}
