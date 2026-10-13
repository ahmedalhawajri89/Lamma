<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrphanDocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'original_name' => $this->original_name,
            'mime' => $this->mime,
            'size_bytes' => $this->size_bytes,
            'is_verified' => $this->verified_at !== null,
            'uploaded_by' => $this->uploaded_by,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'is_rejected' => $this->rejected_at !== null,
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->toIso8601String(),

            // لا رابط مباشر: التنزيل يمرّ بمسار محمي بسياسة، والملف خارج public/
            'download_url' => route('api.v1.admin.documents.download', $this->id),
            // للعرض داخل اللوحة: الملف نفسه بلا «تنزيل»
            'preview_url' => route('api.v1.admin.documents.download', ['document' => $this->id, 'inline' => 1]),
        ];
    }
}
