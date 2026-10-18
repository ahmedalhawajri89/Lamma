<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\TransparencyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * المفاتيح `q` و `a` تطابق ما يتوقعه FAQS في data.js اليوم.
 *
 * والجواب قد يحمل رموزاً تُملأ من أرقام الشفافية لحظة الطلب: `{overhead}`
 * نسبة التشغيل هذه السنة، و`{toChild}` ما يصل للطفل. كان جواب «ما نسبة
 * المصاريف الإدارية؟» يكتب «٧٪» بيده والفعلي 6.9: المحرّر يكتب الرمز في
 * اللوحة كما هو، والموقع العام يعرض الرقم.
 */
class FaqResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'q' => $this->resource->translated('question'),
            'a' => self::fill((string) $this->resource->translated('answer')),
            'category' => $this->resource->translated('category'),
            'sort_order' => $this->sort_order,
            'is_published' => $this->is_published,
        ];
    }

    /** يملأ رموز الأرقام الحيّة، ويُحسب مرة واحدة لكل طلب. */
    public static function fill(string $answer): string
    {
        if (! str_contains($answer, '{')) {
            return $answer;
        }

        $tokens = once(function (): array {
            $service = app(TransparencyService::class);
            $toChild = (int) $service->proof()['toChild'];
            // بلا صرفٍ معتمد هذه السنة بعد: المكمّل لما يصل للطفل، لا خانة فارغة
            $overhead = $service->overhead((int) now()->year)['actual'] ?? (100 - $toChild);

            return [
                '{overhead}' => rtrim(rtrim(number_format((float) $overhead, 1, '.', ''), '0'), '.'),
                '{toChild}' => (string) $toChild,
            ];
        });

        return strtr($answer, $tokens);
    }
}
