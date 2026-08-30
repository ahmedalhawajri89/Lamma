<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** بمفاتيح PARTNERS في data.js: name و icon. */
class PartnerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon' => $this->icon,
            'logo' => $this->logo_path ? asset('storage/'.$this->logo_path) : null,
            'url' => $this->url,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
