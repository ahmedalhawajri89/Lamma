<?php

declare(strict_types=1);

/**
 * CORS.
 *
 * **الموقع العام لم يعد يحتاجه**: صار يُقدَّم من خادم لارافل نفسه، فطلباته
 * على الأصل ذاته. ما بقي هنا هو لوحة Vue في التطوير وحدها، إذ تعمل على
 * منفذ Vite (‏5173). وفي الإنتاج تُبنى إلى `public/admin` فيسقط الحاجة
 * إليه تماماً.
 *
 * و`supports_credentials` لا بد منه ما دامت اللوحة تنادي من أصل آخر:
 * مصادقتنا بكوكي جلسة HttpOnly لا بتوكن، والمتصفح لا يرسله عبر الأصول ما
 * لم تُصرَّح هذه القيمة. ولأن الكوكيز مسموحة فلا يجوز `['*']` — المتصفح
 * يرفضه مع الاعتمادات، والقائمة الصريحة هي الصواب أمنياً على أي حال.
 */
return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(explode(',', (string) env(
        'CORS_ALLOWED_ORIGINS',
        'http://localhost:5173,http://127.0.0.1:5173',
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
