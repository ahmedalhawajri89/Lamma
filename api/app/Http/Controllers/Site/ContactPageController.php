<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Resources\FaqResource;
use App\Models\Faq;
use App\Support\SiteProfile;
use Illuminate\Contracts\View\View;

/**
 * صفحة التواصل.
 *
 * كان الهاتف والبريد فيها مكتوبين باليد، والترويسة والفوتر من الإعدادات:
 * يغيّر الفريق رقمه من اللوحة فتبقى هذه الصفحة على القديم. الآن من المصدر
 * نفسه (SiteProfile). ومعها أكثر الأسئلة شيوعاً قبل النموذج: من يجد جوابه
 * لا يكتب رسالة ولا ينتظر 72 ساعة.
 */
class ContactPageController extends Controller
{
    private const QUICK_FAQS = 4;

    public function __invoke(): View
    {
        $faqs = Faq::query()->published()->limit(self::QUICK_FAQS)->get()
            ->map(fn (Faq $f) => ['q' => $f->translated('question'), 'a' => FaqResource::fill((string) $f->translated('answer'))]);

        return view('site.pages.contact', [
            'contact' => SiteProfile::forViews(),
            'faqs' => $faqs,
        ]);
    }
}
