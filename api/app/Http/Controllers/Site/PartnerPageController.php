<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Enums\SponsorshipStatus;
use App\Http\Controllers\Controller;
use App\Models\Association;
use App\Models\FieldVisit;
use App\Models\Orphan;
use App\Support\Locale;
use Illuminate\Http\Response;

/**
 * صفحة الجمعية الشريكة (/partners/{slug}): من هي، ومتى وُثّقت وبأي تسجيل،
 * وأرقامٌ من قاعدة البيانات عمّا تفعله، وأطفالها الذين ينتظرون من يقف معهم
 * (يملؤهم partner.js من /public/orphans?association=).
 *
 * الموثّقة وحدها لها صفحة: المتقدّمة والموقوفة لا تُعرض على الموقع.
 */
class PartnerPageController extends Controller
{
    public function __invoke(string $slug): Response
    {
        $association = Association::verified()->where('slug', $slug)->with(['country', 'city'])->first();
        abort_if($association === null, 404);

        $children = fn () => Orphan::query()->public()->whereHas('family', fn ($f) => $f->where('association_id', $association->id));

        return response()->view('site.pages.partner', [
            'partner' => [
                'slug' => $association->slug,
                'name' => $association->displayName(),
                'country' => Locale::pick($association->country?->name_ar, $association->country?->name_en),
                'city' => Locale::pick($association->city?->name_ar, $association->city?->name_en),
                'about' => Locale::pick($association->about_ar, $association->about_en),
                'registration' => $association->registration_number,
                'authority' => $association->registration_authority,
                'verified_at' => $association->verified_at,
                'safeguarding_at' => $association->safeguarding_signed_at,
                'website' => $association->website,
            ],
            'stats' => [
                'children' => $children()->count(),
                'sponsored' => $children()->whereHas('sponsorships', fn ($s) => $s->where('status', SponsorshipStatus::Active))->count(),
                'families' => $association->families()->count(),
                'visits' => FieldVisit::query()
                    ->where('visited_at', '>=', now()->subYear())
                    ->whereHas('orphan.family', fn ($f) => $f->where('association_id', $association->id))
                    ->count(),
            ],
        ]);
    }
}
