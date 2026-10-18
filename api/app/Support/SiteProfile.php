<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;

/**
 * هوية الموقع العام وتنقّله: مصدر واحد لقوالب Blade ولنقطة `bootstrap`.
 *
 * كانت الترويسة والفوتر يُبنيان في المتصفح من بذرة في data.js ثم تُكتب
 * الإعدادات فوقها حين يعود الطلب. صارا يُرسمان من الخادم بالقيم نفسها.
 */
final class SiteProfile
{
    /**
     * التنقل الرئيسي بترتيب نية الزائر: الأطفال أولاً. ثلاثة روابط ظاهرة،
     * والرابع مجموعة «عن لمّة» تُفتح قائمةً على الشاشة العريضة وتُسرد تحت
     * عنوانها في درج الجوال. الأيقونة والوصف للقائمة والدرج.
     */
    private const NAV = [
        ['label' => 'الأطفال', 'page' => 'orphans', 'icon' => 'fa-children'],
        ['label' => 'البرامج', 'page' => 'programs', 'icon' => 'fa-layer-group'],
        ['label' => 'الشفافية', 'page' => 'transparency', 'icon' => 'fa-scale-balanced'],
        ['label' => 'عن لمّة', 'icon' => 'fa-circle-info', 'children' => [
            ['label' => 'من نحن', 'page' => 'about', 'icon' => 'fa-people-roof', 'hint' => 'قصتنا، ولماذا «لمّة»'],
            ['label' => 'المدونة', 'page' => 'blog', 'icon' => 'fa-pen-nib', 'hint' => 'من الزيارات الميدانية، بالأرقام'],
            ['label' => 'تواصل معنا', 'page' => 'contact', 'icon' => 'fa-envelope', 'hint' => 'نردّ خلال يوم عمل'],
        ]],
    ];

    /** الصفحات العميقة تُبرز قسمها: ملف الطفل والكفالة تحت «الأيتام»، والمقال تحت «المدونة». */
    private const SECTION_OF = ['orphan' => 'orphans', 'checkout' => 'orphans', 'post' => 'blog'];

    /** @return array{name: string, tagline: string, email: string, phone: string, address: string} */
    public static function site(): array
    {
        $settings = Setting::cached();

        /*
         * النصوص بلغة الصفحة: site.name_en في الإنجليزية، والعربية إن لم يُكتب
         * لها مقابل. البريد والهاتف لا لغة لهما.
         */
        $suffix = Locale::isDefault() ? '' : '_'.Locale::current();
        $text = fn (string $key, string $fallback = '') => ($suffix !== '' ? ($settings["site.{$key}{$suffix}"] ?? null) : null)
            ?: ($settings["site.{$key}"] ?? $fallback);

        return [
            'name' => $text('name', 'لمّة'),
            'tagline' => $text('tagline'),
            'email' => $settings['site.email'] ?? '',
            'phone' => $settings['site.phone'] ?? '',
            'address' => $text('address'),
        ];
    }

    /** ما تحتاجه القوالب فوق ما يُرسل للمتصفح: رابط الهاتف وحسابات التواصل. */
    public static function forViews(): array
    {
        $site = self::site();

        return $site + [
            'phoneHref' => preg_replace('/[^\d+]/', '', $site['phone']),
            'social' => [
                ['label' => 'إكس', 'icon' => 'fa-x-twitter', 'url' => '#'],
                ['label' => 'إنستغرام', 'icon' => 'fa-instagram', 'url' => '#'],
                ['label' => 'فيسبوك', 'icon' => 'fa-facebook-f', 'url' => '#'],
                ['label' => 'يوتيوب', 'icon' => 'fa-youtube', 'url' => '#'],
            ],
        ];
    }

    /**
     * روابط التنقل مع حالة كل منها: «page» للصفحة نفسها و«true» لقسمٍ أنت
     * داخله. المجموعة بلا رابط، ولها children، وحالتها «true» إن كنت في أحد فروعها.
     *
     * @return list<array{label: string, href: ?string, icon: string, hint: ?string, current: ?string, children?: list<array>}>
     */
    public static function nav(string $path): array
    {
        // «en/orphans» و«orphans» الصفحة نفسها: البادئة لغةٌ لا قسم
        $page = trim(Locale::strip($path), '/');

        $item = function (array $link) use ($page, &$item): array {
            if (isset($link['children'])) {
                $children = array_map($item, $link['children']);

                return [
                    'label' => __($link['label']),
                    'href' => null,
                    'icon' => $link['icon'],
                    'hint' => null,
                    'current' => collect($children)->contains(fn ($c) => $c['current'] !== null) ? 'true' : null,
                    'children' => $children,
                ];
            }

            return [
                'label' => __($link['label']),
                'href' => Locale::url('/'.$link['page']),
                'icon' => $link['icon'],
                'hint' => isset($link['hint']) ? __($link['hint']) : null,
                'current' => match ($link['page']) {
                    $page => 'page',
                    self::SECTION_OF[$page] ?? null => 'true',
                    default => null,
                },
            ];
        };

        return array_map($item, self::NAV);
    }

    /**
     * التنقل مسطّحاً، كل صفحة رابطٌ واحد: للفوتر ولكل ما لا يعرض مجموعات.
     *
     * @return list<array{label: string, href: string, icon: string, hint: ?string, current: ?string}>
     */
    public static function flatNav(string $path): array
    {
        return collect(self::nav($path))
            ->flatMap(fn (array $link) => $link['children'] ?? [$link])
            ->values()
            ->all();
    }
}
