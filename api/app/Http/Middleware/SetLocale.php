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
 * والـ API: من ترويسة Accept-Language التي يرسلها api.js بلغة الصفحة، فتصل
 * رسائل التحقق والتسميات باللغة نفسها. ما ليس في القائمة يعود للافتراضية.
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
        $header = (string) $request->header('Accept-Language', '');
        $first = strtolower(substr(trim(explode(',', $header)[0]), 0, 2));

        return $first !== '' ? $first : null;
    }
}
