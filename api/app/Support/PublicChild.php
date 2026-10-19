<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Gender;
use App\Http\Resources\PublicOrphanResource;
use App\Models\Orphan;
use Illuminate\Http\Request;

/**
 * الطفل كما تعرضه صفحاتُ Blade (ملفه وصفحة كفالته): شكل الـ API العام نفسه
 * (PublicOrphanResource: الاسم الأول والرسم، بلا عائلة ولا عنوان)، ومعه ما
 * تحتاجه لوحة الكفالة.
 *
 * `public` لا `seekingSponsor`: من اكتملت كفالته تُفتح صفحته وتقول ذلك.
 */
final class PublicChild
{
    /** @return array<string, mixed>|null */
    public static function find(string $reference, Request $request): ?array
    {
        if ($reference === '') {
            return null;
        }

        $orphan = Orphan::query()
            ->public()
            ->withSponsorCount()
            ->with([
                'program:id,slug,name_ar,name_en,monthly_price,currency',
                'family:id,country_id,city_id,association_id,care_arrangement', 'family.association:id,slug,name_ar,name_en,status,verified_at',
                'family.country:id,name_ar,name_en',
                'family.city:id,name_ar,name_en',
            ])
            ->where('reference_code', $reference)
            ->first();

        if ($orphan === null) {
            return null;
        }

        $remaining = $orphan->remainingNeed();

        return [
            ...(new PublicOrphanResource($orphan))->resolve($request),
            'her' => $orphan->gender === Gender::Female,
            'remaining' => $remaining,
            'accepting' => $orphan->status->acceptsSponsorship() && $remaining > 0,
            'program_name' => $orphan->program ? Locale::pick($orphan->program->name_ar, $orphan->program->name_en) : null,
            'program_price' => $orphan->program ? (float) $orphan->program->monthly_price : null,
            'currency' => $orphan->program?->currency ?? config('kafala.base_currency'),
        ];
    }

    /** «$90» و«$4.50»: كما يكتبها formatMoney في المتصفح، فلا يتبدّل الرقم حين يُحدَّث. */
    public static function money(float|int|string $value): string
    {
        $v = (float) $value;

        return '$'.number_format($v, fmod($v, 1.0) === 0.0 ? 0 : 2, '.', ',');
    }
}
