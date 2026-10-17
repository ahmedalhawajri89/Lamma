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
            // كم كافلاً يتشاركون كفالته: «يكفله شخصان، ويتبقى 10$»
            'sponsors' => $this->when(
                array_key_exists('sponsors_count', $this->resource->getAttributes()),
                fn () => (int) $this->sponsors_count,
            ),
            'urgent' => $this->is_urgent,
            'photo' => $this->resource->illustrationUrl(),
            'grade' => $this->grade_level,
            'health' => $this->health_status,
            'dream' => $this->dream,
            'story' => $this->story,

            /*
             * لصفحة الكفالة (صفحة الطفل الواحد وحدها، فلا يثقل الفهرس): المتبقي
             * من حاجته بالمبالغ الفعلية لا بالنسبة المقرّبة، وعملته، وهل يقبل
             * كفالة الآن. كانت الصفحة تعرض الحاجة كاملة ثم يُخصم غيرها.
             */
            $this->mergeWhen($request->routeIs('api.v1.public.orphans.show'), fn () => $this->checkoutFacts()),
        ];
    }


    /** @return array<string, mixed> */
    private function checkoutFacts(): array
    {
        $remaining = $this->resource->remainingNeed();

        return [
            'remaining' => $remaining,
            'currency' => $this->program?->currency ?? config('kafala.base_currency'),
            'program_price' => $this->program ? (float) $this->program->monthly_price : null,
            'program_name' => $this->program?->name_ar,
            'accepting' => $this->status->acceptsSponsorship() && $remaining > 0,
        ];
    }
}
