<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DisbursementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'amount_base' => (float) $this->amount_base,
            'spent_on' => $this->spent_on?->toDateString(),
            'description' => $this->description,
            'is_approved' => $this->approved_at !== null,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'slug' => $this->category->slug,
                'name' => $this->category->name_ar,
                'color' => $this->category->color,
                'is_overhead' => $this->category->is_overhead,
            ]),
            'orphan' => $this->whenLoaded('orphan', fn () => $this->orphan ? [
                'id' => $this->orphan->id,
                'reference_code' => $this->orphan->reference_code,
                'name' => $this->orphan->first_name,
            ] : null),
            /**
             * المعرّف إلى جانب الاسم.
             *
             * `DisbursementPolicy::approve` تمنع من سجّل الصرف أن يعتمده —
             * مبدأ العينين. وبلا المعرّف لا تعرف الواجهة أن الزرّ أمام صاحبه،
             * فتعرضه ليردّه الخادم بـ٤٠٣: زرٌّ يُعرض ليُرفض فخّ لا واجهة.
             */
            'recorded_by' => $this->recorded_by,
            'recorder' => $this->whenLoaded('recorder', fn () => $this->recorder?->name),
            'approver' => $this->whenLoaded('approver', fn () => $this->approver?->name),
        ];
    }
}
