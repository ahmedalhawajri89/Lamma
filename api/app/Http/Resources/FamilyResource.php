<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FamilyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'family_code' => $this->family_code,
            'address' => $this->address,
            'members_count' => $this->members_count,
            'monthly_income' => $this->monthly_income !== null ? (float) $this->monthly_income : null,
            // دخل الفرد: مقياس الحاجة الذي يقارن أسرة من 7 بأسرة من 2
            'per_capita_income' => $this->monthly_income !== null && $this->members_count > 0
                ? round((float) $this->monthly_income / $this->members_count, 2)
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'housing_status' => $this->housing_status->value,
            'housing_status_label' => $this->housing_status->label(),

            'country' => new CountryResource($this->whenLoaded('country')),
            'city' => $this->whenLoaded('city', fn () => [
                'id' => $this->city?->id,
                'name' => $this->city?->name_ar,
            ]),
            'guardian' => $this->whenLoaded('guardian', fn () => [
                'id' => $this->guardian->id,
                'full_name' => $this->guardian->full_name,
                'relation' => $this->guardian->relation->value,
                'relation_label' => $this->guardian->relation->label(),
                // هاتف الوصيّ لمن يتعامل مع الأسر (المشرف والمدير)، لا للمحاسب
                'phone' => $this->when((bool) $request->user()?->can('families.manage'), $this->guardian->phone),
                'occupation' => $this->guardian->occupation,
                'monthly_income' => $this->guardian->monthly_income !== null
                    ? (float) $this->guardian->monthly_income
                    : null,
            ]),

            'orphans_count' => $this->whenCounted('orphans'),
            'orphans' => OrphanResource::collection($this->whenLoaded('orphans')),
        ];
    }
}
