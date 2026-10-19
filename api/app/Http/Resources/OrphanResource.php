<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** اليتيم في لوحة الإدارة — الملف الكامل. */
class OrphanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_code' => $this->reference_code,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'gender' => $this->gender->value,
            'gender_label' => $this->gender->label(),
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'age' => $this->age,

            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_urgent' => $this->is_urgent,
            'is_public' => $this->status->isPublic() && $this->published_at !== null,

            'photo_url' => $this->photo_path ? asset('storage/'.$this->photo_path) : null,
            'grade_level' => $this->grade_level,
            'health_status' => $this->health_status,
            'dream' => $this->dream,
            'story' => $this->story,

            'monthly_need' => (float) $this->monthly_need,
            'funded_percent' => $this->funded_percent,
            // المغطّى فعلاً بالدولار (حين يُحمَّل): «ينقصه $13.30» أوضح من «62٪»
            'funded_amount' => $this->when(isset($this->funded_amount), fn () => round((float) $this->funded_amount, 2)),

            'verified_at' => $this->verified_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            // لماذا لا يُنشر بعد، بلغة من سيصلحه (صفحة الطفل في اللوحة وحدها)
            'publish_blockers' => $this->when(
                $request->routeIs('api.v1.admin.orphans.show') && $this->published_at === null,
                fn () => $this->resource->publishBlockers(),
            ),
            'created_at' => $this->created_at?->toIso8601String(),

            'program' => new ProgramResource($this->whenLoaded('program')),
            'family' => new FamilyResource($this->whenLoaded('family')),

            'documents' => OrphanDocumentResource::collection($this->whenLoaded('documents')),
            'sponsorships' => SponsorshipResource::collection($this->whenLoaded('sponsorships')),
            'reports' => ReportResource::collection($this->whenLoaded('reports')),
            'field_visits' => FieldVisitResource::collection($this->whenLoaded('fieldVisits')),
            'siblings' => $this->whenLoaded('familyMembers', fn () => self::collection($this->siblings())),

            'sponsorships_count' => $this->whenCounted('sponsorships'),
            'reports_count' => $this->whenCounted('reports'),
            'documents_count' => $this->whenCounted('documents'),
        ];
    }
}
