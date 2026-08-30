<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * اليتيم كما يراه الزائر — بالشكل نفسه الذي يتوقعه render.js في الموقع الحالي.
 *
 * الاسم الأول فقط، ولا اسم عائلة ولا وصيّ ولا عنوان ولا مستندات.
 * هذا ليس اختياراً تجميلياً: نشر الاسم الكامل مع الصورة والعنوان لطفل يتيم
 * يخلق خطراً حقيقياً عليه.
 */
class PublicOrphanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->reference_code,
            'name' => $this->first_name,
            'age' => $this->age,
            'gender' => $this->gender->label(),
            'country' => $this->whenLoaded('family', fn () => $this->family->country?->name_ar),
            'city' => $this->whenLoaded('family', fn () => $this->family->city?->name_ar),
            'program' => $this->whenLoaded('program', fn () => $this->program->slug),
            'monthly' => (float) $this->monthly_need,
            'funded' => $this->funded_percent,
            'urgent' => $this->is_urgent,
            'photo' => $this->photo_path ? asset('storage/'.$this->photo_path) : null,
            'grade' => $this->grade_level,
            'health' => $this->health_status,
            'dream' => $this->dream,
            'story' => $this->story,
        ];
    }
}
