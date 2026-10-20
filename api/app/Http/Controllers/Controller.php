<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * القاعدة في Laravel 12 فارغة عمداً.
 * نضيف التفويض والتحقق هنا لأن كل متحكّم في هذا المشروع يستعمل `$this->authorize()`.
 */
abstract class Controller
{
    use AuthorizesRequests;
    use ValidatesRequests;

    /**
     * حجم الصفحة من الطلب، محصوراً بين 1 والحد الأعلى في الإعداد.
     *
     * كان يُقرأ كما هو: `per_page=100000` يحمّل الجدول كله بعلاقاته في الذاكرة،
     * و`per_page=-1` يصير `LIMIT -1` فيسقط الاستعلام بخطأ 500.
     */
    /**
     * بداية يوم محلي (بتوقيت المنظمة) أو نهايته، محوّلةً إلى UTC كما تُخزَّن
     * الأوقات. فلتر «من 13 أكتوبر» كان يقارن بتاريخ UTC، فدفعة الواحدة فجراً
     * بتوقيت غزة تُحسب على اليوم السابق.
     */
    protected function localDay(?string $date, bool $end = false): ?Carbon
    {
        if (! $date) {
            return null;
        }

        $day = Carbon::parse($date, config('kafala.display_timezone'));

        return ($end ? $day->endOfDay() : $day->startOfDay())->utc();
    }

    protected function perPage(Request $request, int $default = 20): int
    {
        return min(max($request->integer('per_page', $default), 1), (int) config('kafala.pagination.max', 100));
    }
}
