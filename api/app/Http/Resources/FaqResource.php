<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** المفاتيح `q` و `a` تطابق ما يتوقعه FAQS في data.js اليوم. */
class FaqResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'q' => $this->question,
            'a' => $this->answer,
            'category' => $this->category,
            'sort_order' => $this->sort_order,
            'is_published' => $this->is_published,
        ];
    }
}
