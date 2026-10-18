<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مسارات الويب: الموقع العام ولوحة الإدارة
|--------------------------------------------------------------------------
|
| الموقع العام قوالب Blade في `resources/views`:
|
|   site/layouts/   app (ترويسة وفوتر من الخادم)، وauth (صفحات الحساب)، وportal (بوابة الكافل)
|   site/partials/  الرأس المشترك والترويسة والفوتر
|   site/pages/     صفحات الموقع
|   site/auth/      الدخول والتسجيل واستعادة كلمة المرور
|   site/portal/    لوحة الكافل
|   errors/404      صفحة ٤٠٤
|
| والمحتوى الحيّ (الأطفال، المقالات، الأرقام) يملؤه JS من الـ API كما كان.
*/

/**
 * صفحات الموقع العام، مرتين: العربية بلا بادئة كما كانت، والإنجليزية تحت /en.
 * أسماء مسارات الإنجليزية تبدأ بـ «en.» (en.site.orphans).
 */
$site = function (): void {
    Route::view('/', 'site.pages.home')->name('home');

    /** صفحات الموقع: العنوان هو اسم القالب في site/pages. */
    foreach ([
        'orphans', 'thank-you', 'transparency',
        'blog', 'about',
    ] as $page) {
        Route::view("/{$page}", "site.pages.{$page}")->name("site.{$page}");
    }

    /** صفحة الطفل وصفحة كفالته تُرسمان من الخادم: الاسم وحالة الكفالة ووسوم المشاركة */
    Route::get('/orphan', [App\Http\Controllers\Site\OrphanPageController::class, 'show'])->name('site.orphan');
    Route::get('/checkout', [App\Http\Controllers\Site\OrphanPageController::class, 'checkout'])->name('site.checkout');

    /** المقال من الخادم: عنوانه وصورته ونصّه ووسوم مشاركته */
    Route::get('/post', App\Http\Controllers\Site\PostPageController::class)->name('site.post');

    /** التواصل: بياناته من الإعدادات، وأكثر الأسئلة قبل النموذج */
    Route::get('/contact', App\Http\Controllers\Site\ContactPageController::class)->name('site.contact');

    /** الأسئلة الشائعة من الخادم: نصّها ووصفها المنظَّم لمحركات البحث */
    Route::get('/faq', App\Http\Controllers\Site\FaqPageController::class)->name('site.faq');

    /** البرامج من جدولها: السعر والبنود من مصدرٍ واحد، ومع كلٍّ من ينتظر فيه */
    Route::get('/programs', App\Http\Controllers\Site\ProgramsPageController::class)->name('site.programs');

    Route::view('/login', 'site.auth.login')->name('site.login');
    Route::view('/register', 'site.auth.register')->name('site.register');
    Route::get('/reset-password', App\Http\Controllers\Site\ResetPasswordPageController::class)->name('site.reset-password');
    Route::view('/dashboard', 'site.portal.dashboard')->name('site.dashboard');

    /** دليل الواجهة: مرجع للمطوّر لا صفحة للزائر، فلا يُسجَّل خارج التطوير. */
    if (app()->isLocal()) {
        Route::view('/styleguide', 'site.pages.styleguide')->name('site.styleguide');
    }
};

Route::middleware('locale:ar')->group($site);
Route::prefix('en')->name('en.')->middleware('locale:en')->group($site);

/**
 * الروابط القديمة بامتداد `.html` (المحفوظة والمُشاركة قبل الانتقال إلى
 * Blade) تُحوَّل دائماً إلى العنوان النظيف مع معاملاتها، فلا ينكسر منها شيء.
 */
Route::get('/{page}.html', function (string $page) {
    $query = request()->getQueryString();
    $target = $page === 'index' ? '/' : "/{$page}";

    return redirect($target.($query ? "?{$query}" : ''), 301);
})->where('page', '[a-z0-9\-]+')->name('page.legacy');

/*
|--------------------------------------------------------------------------
| لوحة الإدارة — تطبيق صفحة واحدة
|--------------------------------------------------------------------------
|
| كل ما تحت `/admin` يعيد `index.html` نفسه، ثم يتولّى موجّه Vue الباقي.
| هذا شرط أي SPA بتاريخ متصفح حقيقي: تحديث الصفحة على `/admin/orphans/12`
| يجب أن يصل التطبيق لا أن يردّ الخادم ٤٠٤.
|
| ورسالة الخطأ صريحة حين لا يكون مبنياً، لأن «٤٠٤» هنا تُربك: المسار صحيح
| والملف غير موجود لأن أحداً لم يشغّل البناء بعد.
*/
$adminSpa = function () {
    $file = public_path('admin-app/index.html');

    abort_unless(
        is_file($file),
        404,
        'لوحة الإدارة غير مبنية. شغّل: cd admin && npm run build',
    );

    return response(file_get_contents($file), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
};

/**
 * مساران لا واحد بمعامل اختياري: `{path?}` مع نمط `.*` يقبل الفراغ، وفصلهما
 * أوضح للقارئ ولمُجمِّع المسارات معاً.
 *
 * ولاحظ أن الملفات في `admin-app/` لا `admin/` — الاختلاف مقصود وسببه
 * موثّق في `admin/vite.config.js`، وإهماله يكسر اللوحة بصمت.
 */
Route::get('/admin', $adminSpa)->name('admin');
Route::get('/admin/{path}', $adminSpa)->where('path', '.*')->name('admin.spa');

/** ما لم يطابق شيئاً: صفحة ٤٠٤ الخاصة بالمشروع (errors/404) لا صفحة لارافل. */
Route::fallback(fn () => abort(404));
