<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** بمفاتيح STORIES في data.js: quote و name و role. */
class TestimonialResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quote' => $this->quote,
            'name' => $this->author_name,
            'role' => $this->role,
            'sort_order' => $this->sort_order,
            'is_published' => $this->is_published,
        ];
    }
}
