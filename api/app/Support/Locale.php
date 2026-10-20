<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * لغة الموقع العام: أيّها الحالية، واتجاهها، وروابطها.
 *
 * العربية الافتراضية بلا بادئة (/orphans) فتبقى روابطها كما شاركها الناس
 * وفهرستها محركات البحث، وكل لغة أخرى تحت بادئتها (/en/orphans). لكل لغة
 * رابطٌ مستقل لأن محرك البحث يفهرس لغةً واحدة لكل رابط؛ والكوكي يحفظ اختيار
 * الزائر فقط ولا يحوّله تلقائياً.
 */
final class Locale
{
    /** @return array<string, array{name: string, dir: string, og: string, intl: string, css: string}> */
    public static function all(): array
    {
        return config('kafala.locales');
    }

    public static function default(): string
    {
        return array_key_first(self::all());
    }

    public static function current(): string
    {
        $locale = app()->getLocale();

        return array_key_exists($locale, self::all()) ? $locale : self::default();
    }

    public static function supports(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, self::all());
    }

    /** @return array{name: string, dir: string, og: string, intl: string, css: string} */
    public static function config(?string $locale = null): array
    {
        return self::all()[$locale ?? self::current()];
    }

    public static function dir(?string $locale = null): string
    {
        return self::config($locale)['dir'];
    }

    public static function isDefault(?string $locale = null): bool
    {
        return ($locale ?? self::current()) === self::default();
    }

    /**
     * رابطٌ داخلي بلغةٍ ما: «/orphans» يصير «/en/orphans» للإنجليزية.
     * المعاملات والمرساة تبقى كما هي.
     */
    public static function url(string $path, ?string $locale = null): string
    {
        $locale ??= self::current();
        $path = '/'.ltrim($path, '/');

        if (self::isDefault($locale)) {
            return $path;
        }

        return $path === '/' ? "/{$locale}" : "/{$locale}{$path}";
    }

    /**
     * رابطٌ مطلق بلغةٍ ما، لما يُقرأ خارج الموقع (البريد). الأساس من
     * app.url لا من الطلب: رأس Host مزوّر لا يضع في بريدٍ رابطاً لموقعٍ آخر.
     */
    public static function absolute(string $path, ?string $locale = null): string
    {
        return rtrim((string) config('app.url'), '/').self::url($path, $locale);
    }

    /**
     * من عمودين متقابلين (name_ar / name_en): ما يوافق لغة الصفحة، والعربي إن
     * لم يُكتب مقابله.
     */
    public static function pick(?string $ar, ?string $en): ?string
    {
        return ! self::isDefault() && filled($en) ? $en : $ar;
    }

    /** المسار بلا بادئة اللغة: «/en/orphans» ← «/orphans». */
    public static function strip(string $path): string
    {
        $path = '/'.ltrim($path, '/');

        foreach (array_keys(self::all()) as $code) {
            if ($path === "/{$code}") {
                return '/';
            }
            if (str_starts_with($path, "/{$code}/")) {
                return substr($path, strlen($code) + 1);
            }
        }

        return $path;
    }

    /** لغة الرابط من بادئته، والافتراضية إن لم تكن له بادئة. */
    public static function fromPath(string $path): string
    {
        $first = explode('/', trim($path, '/'))[0] ?? '';

        return self::supports($first) && ! self::isDefault($first) ? $first : self::default();
    }

    /**
     * الصفحة نفسها بكل لغة، لـ hreflang ومبدّل اللغة.
     *
     * @return array<string, string> code => absolute url
     */
    public static function alternates(Request $request): array
    {
        $path = self::strip($request->getPathInfo());
        $query = $request->getQueryString();

        $out = [];
        foreach (array_keys(self::all()) as $code) {
            $out[$code] = url(self::url($path, $code)).($query ? "?{$query}" : '');
        }

        return $out;
    }
}
