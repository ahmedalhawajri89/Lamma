<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\PublicChild;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * صفحة الطفل وصفحة كفالته، مرسومتين من الخادم.
 *
 * كانتا قالبين فارغين يملؤهما JS بعد طلبٍ ثانٍ، فيرى الزائر نقاطاً «…» لحظة،
 * ورابط الطفل المُشارك على واتساب بلا اسم ولا وصف. الآن تصلان بالاسم وحالة
 * الكفالة، وعنوانهما ووسوم مشاركتهما باسم الطفل.
 */
class OrphanPageController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $reference = $request->string('id')->trim()->toString();

        // بلا رمز: لا طفل يُعرض، فإلى الفهرس
        if ($reference === '') {
            return redirect('/orphans');
        }

        $kid = PublicChild::find($reference, $request);

        return response()->view('site.pages.orphan', ['kid' => $kid], $kid ? 200 : 404);
    }

    /**
     * صفحة الكفالة: الملخص من الخادم، والنموذج يكمله checkout.js (المبلغ
     * والدورية والدفع). طفلٌ لا يقبل كفالة يصل وصفحته تقول ذلك بلا نموذج.
     */
    public function checkout(Request $request): Response|RedirectResponse
    {
        $reference = $request->string('id')->trim()->toString();

        if ($reference === '') {
            return redirect('/orphans');
        }

        $kid = PublicChild::find($reference, $request);

        return response()->view('site.pages.checkout', ['kid' => $kid], $kid ? 200 : 404);
    }
}
