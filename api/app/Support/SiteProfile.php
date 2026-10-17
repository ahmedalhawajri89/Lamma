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
    /** التنقل الرئيسي بترتيب نية الزائر: الأيتام أولاً. */
    private const NAV = [
        ['label' => 'الأيتام', 'page' => 'orphans'],
        ['label' => 'برامج الكفالة', 'page' => 'programs'],
        ['label' => 'الشفافية', 'page' => 'transparency'],
        ['label' => 'المدونة', 'page' => 'blog'],
        ['label' => 'من نحن', 'page' => 'about'],
        ['label' => 'تواصل معنا', 'page' => 'contact'],
    ];

    /** الصفحات العميقة تُبرز قسمها: ملف الطفل والكفالة تحت «الأيتام»، والمقال تحت «المدونة». */
    private const SECTION_OF = ['orphan' => 'orphans', 'checkout' => 'orphans', 'post' => 'blog'];

    /** @return array{name: string, tagline: string, email: string, phone: string, address: string} */
    public static function site(): array
    {
        $settings = Setting::cached();

        return [
            'name' => $settings['site.name'] ?? 'كفالة',
            'tagline' => $settings['site.tagline'] ?? '',
            'email' => $settings['site.email'] ?? '',
            'phone' => $settings['site.phone'] ?? '',
            'address' => $settings['site.address'] ?? '',
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
     * روابط التنقل مع حالة كل منها: «page» للصفحة نفسها و«true» لقسمٍ أنت داخله.
     *
     * @return list<array{label: string, href: string, current: string|null}>
     */
    public static function nav(string $path): array
    {
        $page = trim($path, '/');

        return array_map(fn (array $link): array => [
            'label' => $link['label'],
            'href' => '/'.$link['page'],
            'current' => match ($link['page']) {
                $page => 'page',
                self::SECTION_OF[$page] ?? null => 'true',
                default => null,
            },
        ], self::NAV);
    }
}
