<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * الجمعية الشريكة في اللوحة. بيانات التحويل البنكي لا تُرسل نصّاً: يُقال
 * إنها مسجّلة أم لا فقط، وتُكتب من جديد لا تُقرأ.
 *
 * @mixin \App\Models\Association
 */
class AssociationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'country_id' => $this->country_id,
            'country' => $this->whenLoaded('country', fn () => $this->country?->name_ar),
            'city_id' => $this->city_id,
            'city' => $this->whenLoaded('city', fn () => $this->city?->name_ar),
            'registration_number' => $this->registration_number,
            'registration_authority' => $this->registration_authority,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'verified_by' => $this->whenLoaded('verifier', fn () => $this->verifier?->name),
            'suspension_reason' => $this->suspension_reason,
            'safeguarding_signed_at' => $this->safeguarding_signed_at?->toIso8601String(),
            'family_care_attested_at' => $this->family_care_attested_at?->toIso8601String(),
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'about_ar' => $this->about_ar,
            'about_en' => $this->about_en,
            'has_transfer_details' => filled($this->resource->getRawOriginal('transfer_details')),
            'transfer_currency' => $this->transfer_currency,
            'families_count' => $this->whenCounted('families'),
            'staff_count' => $this->whenCounted('staff'),
            'children_count' => $this->when(isset($this->resource->children_count), fn () => (int) $this->resource->children_count),
            'public_url' => $this->status->value === 'verified' ? url('/partners/'.$this->slug) : null,
        ];
    }
}
