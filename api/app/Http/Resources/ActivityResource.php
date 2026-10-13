<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\ActivityFormatter;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** سجل النشاط — من غيّر ماذا ومتى، وقيمة الحقل قبل التغيير وبعده. */
class ActivityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return app(ActivityFormatter::class)->format($this->resource);
    }
}
