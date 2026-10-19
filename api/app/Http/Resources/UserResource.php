<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * المستخدم كما تراه الواجهة.
 *
 * الأدوار والصلاحيات تُرسَل صراحةً لأن موجّه Vue يبني القائمة الجانبية منها،
 * ولا يجوز أن يخمّنها من عناوين المسارات.
 *
 * إخفاء الحقول الحساسة هنا طبقةٌ ثانية فوق $hidden في الموديل، لا بديلٌ عنها.
 */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_path ? asset('storage/'.$this->avatar_path) : null,
            'locale' => $this->locale,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'email_verified' => $this->email_verified_at !== null,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'two_factor_enabled' => $this->two_factor_confirmed_at !== null,

            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->all(), []),
            'permissions' => $this->when(
                $this->relationLoaded('roles') || $this->relationLoaded('permissions'),
                fn () => $this->getAllPermissions()->pluck('name')->all(),
            ),

            'sponsor' => new SponsorResource($this->whenLoaded('sponsor')),
            'staff_profile' => $this->whenLoaded('staffProfile', fn () => [
                'employee_code' => $this->staffProfile->employee_code,
                'title' => $this->staffProfile->title,
                'assigned_country_id' => $this->staffProfile->assigned_country_id,
                'assigned_country_name' => $this->staffProfile->relationLoaded('assignedCountry')
                    ? $this->staffProfile->assignedCountry?->name_ar
                    : null,
                // موظف الجمعية الشريكة (فارغ لموظف المنصة)
                'association_id' => $this->staffProfile->association_id,
                'association_name' => $this->staffProfile->relationLoaded('association')
                    ? $this->staffProfile->association?->name_ar
                    : null,
            ]),
        ];
    }
}
