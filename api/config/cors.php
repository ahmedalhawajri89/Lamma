<?php

declare(strict_types=1);

/**
 * CORS.
 *
 * `supports_credentials` لا بد منه: مصادقتنا بكوكي جلسة HttpOnly لا بتوكن،
 * والمتصفح لا يرسل الكوكي عبر الأصول ما لم تُصرَّح هذه القيمة.
 *
 * ولأن الكوكيز مسموحة، فلا يجوز `allowed_origins => ['*']` — المتصفح يرفضه
 * أصلاً مع الاعتمادات، والقائمة الصريحة هي الصواب أمنياً على أي حال.
 */
return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(explode(',', (string) env(
        'CORS_ALLOWED_ORIGINS',
        'http://localhost:5173,http://127.0.0.1:5173,http://localhost:5501,http://127.0.0.1:5501',
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
