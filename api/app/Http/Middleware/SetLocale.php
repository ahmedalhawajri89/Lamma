<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * لغة الطلب.
 *
 * صفحات الموقع: من مجموعة المسار (locale:en)، فالرابط وحده يحدّد اللغة.
 * والـ API: من ترويسة X-Locale التي يرسلها api.js بلغة الصفحة، فتصل رسائل
 * التحقق والتسميات باللغة نفسها.
 *
 * لا من Accept-Language: كل متصفح يرسلها بلغة جهازه، فكانت لوحة الإدارة
 * العربية تتلقى رسائلها بالإنجليزية لموظفٍ متصفحه إنجليزي. ما لم يُطلب
 * صراحةً، أو ليس في القائمة، يعود للافتراضية.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next, ?string $locale = null): Response
    {
        $locale ??= $this->fromHeader($request);

        app()->setLocale(Locale::supports($locale) ? $locale : Locale::default());

        return $next($request);
    }

    private function fromHeader(Request $request): ?string
    {
        $locale = strtolower(trim((string) $request->header('X-Locale', '')));

        return $locale !== '' ? $locale : null;
    }
}
