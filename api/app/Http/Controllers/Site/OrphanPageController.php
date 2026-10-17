<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicOrphanResource;
use App\Models\Orphan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * صفحة الطفل، مرسومةً من الخادم.
 *
 * كانت قالباً فارغاً يملؤه JS بعد طلبٍ ثانٍ، فيرى الزائر نقاطاً «…» لحظة،
 * ورابطها المُشارك على واتساب بلا اسم ولا وصف. الآن تصل بالاسم والحلم
 * والقصة وحالة الكفالة، وعنوانها ووسوم مشاركتها باسم الطفل.
 *
 * البيانات نفسها التي يراها الـ API العام (PublicOrphanResource): الاسم الأول
 * والرسم لا الصورة، ولا عائلة ولا عنوان.
 */
class OrphanPageController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $reference = $request->string('id')->trim()->toString();

        // بلا رمز: لا طفل يُعرض، فإلى الفهرس
        if ($reference === '') {
            return redirect('/orphans');
        }

        // public لا seekingSponsor: طفلٌ اكتملت كفالته تفتح صفحته وتقول ذلك
        $orphan = Orphan::query()
            ->public()
            ->withSponsorCount()
            ->with([
                'program:id,slug,name_ar,monthly_price,currency',
                'family:id,country_id,city_id',
                'family.country:id,name_ar',
                'family.city:id,name_ar',
            ])
            ->where('reference_code', $reference)
            ->first();

        if ($orphan === null) {
            return response()->view('site.pages.orphan', ['kid' => null], 404);
        }

        $remaining = $orphan->remainingNeed();

        $kid = [
            ...(new PublicOrphanResource($orphan))->resolve($request),
            'her' => $orphan->gender === \App\Enums\Gender::Female,
            'remaining' => $remaining,
            'accepting' => $orphan->status->acceptsSponsorship() && $remaining > 0,
            'program_name' => $orphan->program?->name_ar,
        ];

        return response()->view('site.pages.orphan', ['kid' => $kid]);
    }
}
