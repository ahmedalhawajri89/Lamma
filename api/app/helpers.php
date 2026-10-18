<?php

declare(strict_types=1);

use App\Support\Locale;

if (! function_exists('lurl')) {
    /**
     * رابطٌ داخلي بلغة الصفحة: lurl('/orphans') يعطي «/en/orphans» في الإنجليزية.
     * كل رابطٍ داخلي في قوالب الموقع يمرّ به، فلا يقفز الزائر إلى لغةٍ أخرى.
     */
    function lurl(string $path, ?string $locale = null): string
    {
        return Locale::url($path, $locale);
    }
}
