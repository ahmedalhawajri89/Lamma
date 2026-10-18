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
            'active_sponsorships_count' => $this->when(isset($this->active_sponsorships_count), fn () => (int) $this->active_sponsorships_count),
            'overdue_sponsorships_count' => $this->when(isset($this->overdue_sponsorships_count), fn () => (int) $this->overdue_sponsorships_count),
            'monthly_commitment' => $this->when(array_key_exists('monthly_commitment', $this->getAttributes()), fn () => round((float) $this->getAttributes()['monthly_commitment'], 2)),
            'last_payment_at' => $this->when(array_key_exists('last_payment_at', $this->getAttributes()), fn () => $this->getAttributes()['last_payment_at']
                ? \Illuminate\Support\Carbon::parse($this->getAttributes()['last_payment_at'])->toIso8601String() : null),
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
