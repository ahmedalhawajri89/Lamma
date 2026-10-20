<?php

/*
| مدخل Vercel (vercel-php): كل طلب لا يطابق ملفاً ثابتاً في vercel.json يصل هنا.
|
| القرص للقراءة فقط عدا ‎/tmp‎، فالتخزين والكاش المترجم يُنقلان إليه. ما يرفعه
| الزوار يبقى ما دامت النسخة دافئة ثم يزول، وهذا مقبول في نسخة العرض. الصور
| المنشورة مع العرض تُخدَم ساكنةً من ‎public/storage‎.
*/

$storage = '/tmp/storage';

foreach (['app/public', 'app/private', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $dir) {
    if (! is_dir("{$storage}/{$dir}")) {
        mkdir("{$storage}/{$dir}", 0777, true);
    }
}

$paths = [
    'LARAVEL_STORAGE_PATH' => $storage,
    'VIEW_COMPILED_PATH' => "{$storage}/framework/views",
    'APP_CONFIG_CACHE' => '/tmp/config.php',
    'APP_EVENTS_CACHE' => '/tmp/events.php',
    'APP_PACKAGES_CACHE' => '/tmp/packages.php',
    'APP_ROUTES_CACHE' => '/tmp/routes.php',
    'APP_SERVICES_CACHE' => '/tmp/services.php',
];
foreach ($paths as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $_SERVER[$key] = $value;
}

// Vercel يخدم HTTPS دائماً ويصل الطلب إلى PHP عبر وكيله: بدون هذا تُبنى
// الروابط والأصول بـ http فيحجبها المتصفح.
$_SERVER['HTTPS'] = 'on';

// المدخل في مجلد ‎api/‎، فيستنتج Symfony من ‎SCRIPT_NAME‎ أن أصل التطبيق ‎/api‎
// ويقصّه من كل عنوان يبدأ به، فيصل ‎/api/v1/…‎ إلى لارافل ‎/v1/…‎ ويرجع 404.
// التطبيق في الحقيقة على جذر الموقع، كما لو خدمه ‎public/index.php‎ مباشرة.
$_SERVER['SCRIPT_FILENAME'] = realpath(__DIR__.'/../public/index.php');
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/index.php';

require __DIR__.'/../public/index.php';
