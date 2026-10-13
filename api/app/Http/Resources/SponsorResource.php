<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SponsorResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sponsor_code' => $this->sponsor_code,
            'city' => $this->city,
            'preferred_currency' => $this->preferred_currency,
            'prefers_zakat' => $this->prefers_zakat,
            'is_anonymous' => $this->is_anonymous,
            'communication_prefs' => $this->communication_prefs,
            'sponsoring_since' => $this->sponsoring_since?->toDateString(),

            'name' => $this->whenLoaded('user', fn () => $this->user->name),
            'email' => $this->whenLoaded('user', fn () => $this->user->email),
            'country' => new CountryResource($this->whenLoaded('country')),

            // كفالاته في صفحة تفاصيله (show)، لا في القوائم
            'sponsorships' => SponsorshipResource::collection($this->whenLoaded('sponsorships')),
            // الهاتف حين يُحمَّل عموده (صفحة التفاصيل)؛ القوائم تحمّل الاسم والبريد وحدهما
            'phone' => $this->whenLoaded('user', fn () => $this->user->getAttributes()['phone'] ?? null),

            'sponsorships_count' => $this->whenCounted('sponsorships'),
            'active_sponsorships_count' => $this->whenCounted('activeSponsorships'),
            'total_given' => $this->when(
                isset($this->payments_sum_amount_base),
                fn () => (float) $this->payments_sum_amount_base,
            ),

            // ملاحظات الإدارة لا تخرج إلا لمن يملك صلاحية إدارة الكفلاء
            'internal_notes' => $this->when(
                $request->user()?->can('sponsors.manage') ?? false,
                fn () => $this->getAttribute('internal_notes'),
            ),
        ];
    }
}
