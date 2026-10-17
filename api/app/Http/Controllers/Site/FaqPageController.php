<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Resources\FaqResource;
use App\Models\Faq;
use App\Support\SiteProfile;
use Illuminate\Contracts\View\View;

/**
 * الأسئلة الشائعة، مرسومةً من الخادم.
 *
 * كانت قائمةً فارغة يملؤها JS، فلا يقرأ محركُ البحث جواباً واحداً منها،
 * والأسئلة الشائعة أكثر ما يُبحث عنه قبل التبرع. الآن تصل بنصّها ومعها وصفٌ
 * منظَّم (FAQPage)، والبحث والتصنيف فوقها تحسينٌ في المتصفح لا شرطٌ لقراءتها.
 */
class FaqPageController extends Controller
{
    /**
     * مراسي الأسئلة كما في الرئيسية (FAQ_TOPICS في render.js): صفحة الكفالة
     * تربط «/faq#zakat»، والرئيسية تربط بقية المواضيع. كلمةٌ في السؤال تحدد
     * مرساته وأيقونته، وما لا يطابق شيئاً يأخذ مرساةً برقمه.
     */
    private const TOPICS = [
        ['وصل', 'delivery', 'fa-circle-check'],
        ['الزكاة', 'zakat', 'fa-scale-balanced'],
        ['المصاريف', 'overhead', 'fa-chart-pie'],
        ['إيقاف', 'pause', 'fa-circle-pause'],
        ['الدفع', 'payment', 'fa-credit-card'],
        ['التواصل', 'contact', 'fa-comments'],
    ];

    public function __invoke(): View
    {
        $used = [];
        $faqs = Faq::query()->published()->get()->map(function (Faq $f) use (&$used): array {
            $topic = collect(self::TOPICS)->first(fn (array $t) => str_contains($f->question, $t[0]) && ! isset($used[$t[1]]));
            $anchor = $topic[1] ?? 'q-'.$f->id;
            $used[$anchor] = true;

            return [
                'id' => $anchor,
                'icon' => $topic[2] ?? 'fa-circle-question',
                'q' => $f->question,
                'a' => FaqResource::fill((string) $f->answer),
                'category' => $f->category ?: 'عام',
            ];
        });

        return view('site.pages.faq', [
            'faqs' => $faqs,
            'categories' => $faqs->countBy('category'),
            'contact' => SiteProfile::forViews(),
        ]);
    }
}
