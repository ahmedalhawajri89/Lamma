<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Portal;
use App\Http\Controllers\Api\V1\Public as Pub;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مسارات الـ API — الإصدار الأول
|--------------------------------------------------------------------------
|
| أربع مجموعات، وحدود كل منها صريحة:
|
|   public   بلا مصادقة — ما يقرأه الموقع العام في web/
|   auth     الدخول والتسجيل، بتحديد معدّل مشدَّد
|   portal   الكافل — كل مسار محكوم بسياسة تتحقق أن السجل يخصّه هو
|   admin    الموظفون — role: admin|accountant|field_supervisor
|
| التحديد المشدَّد على مسارات المصادقة والنماذج العامة ليس تجميلاً: هذه
| المسارات مفتوحة للعالم، وهي أول ما يُستهدف بالتخمين والسبام.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {

    /* ==================================================================
       ١. العام — بلا مصادقة
       ================================================================== */
    Route::prefix('public')->name('public.')->group(function (): void {

        Route::get('bootstrap', [Pub\CatalogController::class, 'bootstrap'])->name('bootstrap');
        Route::get('programs', [Pub\CatalogController::class, 'programs'])->name('programs');
        Route::get('countries', [Pub\CatalogController::class, 'countries'])->name('countries');
        Route::get('faqs', [Pub\CatalogController::class, 'faqs'])->name('faqs');

        Route::get('orphans', [Pub\OrphanController::class, 'index'])->name('orphans.index');
        Route::get('orphans/{reference}', [Pub\OrphanController::class, 'show'])->name('orphans.show');

        Route::get('transparency', [Pub\TransparencyController::class, 'index'])->name('transparency');
        Route::get('proof', [Pub\TransparencyController::class, 'proof'])->name('proof');

        Route::get('posts', [Pub\PostController::class, 'index'])->name('posts.index');
        Route::get('posts/{slug}', [Pub\PostController::class, 'show'])->name('posts.show');

        /**
         * نموذجان مفتوحان للعالم: ستّ محاولات في الدقيقة تكفي أي إنسان.
         *
         * ولكلٍّ دلوه (‏المعامل الثالث بادئة المفتاح). فمُحدِّد لارافل يبني
         * مفتاحه من النطاق والـIP لا من المسار، وجمعهما في مجموعة واحدة
         * يجعلهما يتقاسمان الرصيد: من اشترك في النشرة ثم أراد أن يكتب لنا
         * يجد الباب مغلقاً — وهما فعلان مختلفان بإساءتين مختلفتين.
         */
        Route::post('contact', [Pub\ContactController::class, 'store'])
            ->middleware('throttle:6,1,contact')->name('contact');

        Route::post('newsletter', [Pub\ContactController::class, 'subscribe'])
            ->middleware('throttle:6,1,newsletter')->name('newsletter');

        /**
         * الكفالة من صفحة الموقع: للزائر (يُنشأ حسابه) وللكافل الداخل.
         * ستّ محاولات في الدقيقة، ولكلٍّ دلوه، فلا يُكرَّر الحجز ولا الخصم بلا حدّ.
         */
        Route::get('checkout/config', [Pub\CheckoutController::class, 'config'])->name('checkout.config');
        Route::post('checkout', [Pub\CheckoutController::class, 'store'])
            ->middleware('throttle:6,1,checkout')->name('checkout');
    });

    /* ==================================================================
       ٢. المصادقة
       ================================================================== */
    Route::prefix('auth')->name('auth.')->group(function (): void {

        /**
         * الدخول: حدّ أوسع، لأن الحدّ الدقيق في مكانه الصحيح.
         *
         * `LoginRequest` يحدّ بمفتاح «البريد + IP» ويعدّ المحاولات الفاشلة
         * وحدها — وهو الحارس الفعلي ضد التخمين. أما هذا فبالـ IP وحده،
         * ويعدّ **كل** طلب بما فيه الناجح، فعشرة في الدقيقة تقفل مكتباً خلف
         * NAT عند الحضور صباحاً: خمسة عشر موظفاً يدخلون فيُردّ آخرهم.
         *
         * وهو ما يحذّر منه تعليق `LoginRequest` حرفياً. فيبقى هنا بوصفه
         * سدّاً أمام مسحٍ واسع بمئات البُرد — والمسح يحتاج آلاف الطلبات،
         * فثلاثون في الدقيقة تكفي لإيقافه ولا تؤذي أحداً.
         */
        Route::middleware('throttle:30,1')->group(function (): void {
            Route::post('login', [AuthController::class, 'login'])->name('login');
        });

        /**
         * التسجيل والاستعادة: الحدّ الضيّق يبقى.
         *
         * لهذين أثرٌ خارج الخادم — أحدهما يُنشئ حساباً، والآخر يُرسل بريداً
         * باسمنا إلى عنوانٍ يختاره الطالب. وليس لهما نظير `LoginRequest`
         * يحدّ بمفتاح دقيق، فهذا هو حارسهما الوحيد.
         */
        Route::middleware('throttle:10,1')->group(function (): void {
            Route::post('register', [AuthController::class, 'register'])->name('register');
            Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->name('password.email');
            Route::post('reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
        });

        Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        });
    });

    /* ==================================================================
       ٣. بوابة الكافل
       ================================================================== */
    // لوحة الكافل للكفلاء وحدهم: حساب فريق العمل يُرفض هنا برد 403 واحد،
    // لا بتحقق كل متحكم على حدة
    Route::prefix('portal')->name('portal.')
        ->middleware(['auth:sanctum', 'active', 'role:sponsor'])
        ->group(function (): void {

            Route::get('overview', Portal\OverviewController::class)->name('overview');

            Route::get('sponsorships', [Portal\SponsorshipController::class, 'index'])->name('sponsorships.index');
            // الحجز يُمسك حصة الطفل، فلا يُكرَّر بلا حدّ
            Route::post('sponsorships', [Portal\SponsorshipController::class, 'store'])
                ->middleware('throttle:5,1')
                ->name('sponsorships.store');
            Route::delete('sponsorships/{sponsorship}', [Portal\SponsorshipController::class, 'destroy'])->name('sponsorships.destroy');
            Route::patch('sponsorships/{sponsorship}', [Portal\SponsorshipController::class, 'update'])
                ->middleware('throttle:10,1')
                ->name('sponsorships.update');

            Route::get('payments', [Portal\PaymentController::class, 'index'])->name('payments.index');

            Route::get('reports', [Portal\ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/{report}', [Portal\ReportController::class, 'show'])->name('reports.show');

            Route::get('sponsorships/{sponsorship}/messages', [Portal\MessageController::class, 'index'])->name('messages.index');
            Route::post('sponsorships/{sponsorship}/messages', [Portal\MessageController::class, 'store'])
                ->middleware('throttle:20,1')
                ->name('messages.store');

            Route::patch('profile', [Portal\ProfileController::class, 'update'])
                ->middleware('throttle:10,1')
                ->name('profile.update');
            Route::put('profile/password', [Portal\ProfileController::class, 'updatePassword'])
                ->middleware('throttle:6,1')
                ->name('profile.password');
        });

    /* ==================================================================
       ٤. لوحة الإدارة
       ================================================================== */
    Route::prefix('admin')->name('admin.')
        ->middleware(['auth:sanctum', 'active', 'role:admin|accountant|field_supervisor'])
        ->group(function (): void {

            Route::get('dashboard', Admin\DashboardController::class)->name('dashboard');
            Route::get('queue', [Admin\DashboardController::class, 'queue'])->name('queue');

            /* --- الأيتام --- */
            Route::get('orphans', [Admin\OrphanController::class, 'index'])->name('orphans.index');
            Route::get('orphans/export', [Admin\OrphanController::class, 'export'])
                ->middleware('throttle:10,1')
                ->name('orphans.export');
            Route::post('orphans', [Admin\OrphanController::class, 'store'])->name('orphans.store');
            Route::get('orphans/{orphan}', [Admin\OrphanController::class, 'show'])->name('orphans.show');
            Route::match(['put', 'patch'], 'orphans/{orphan}', [Admin\OrphanController::class, 'update'])->name('orphans.update');
            Route::delete('orphans/{orphan}', [Admin\OrphanController::class, 'destroy'])->name('orphans.destroy');
            Route::post('orphans/{orphan}/verify', [Admin\OrphanController::class, 'verify'])->name('orphans.verify');
            Route::post('orphans/{orphan}/status', [Admin\OrphanController::class, 'changeStatus'])->name('orphans.status');
            Route::post('orphans/{orphan}/publish', [Admin\OrphanController::class, 'publish'])->name('orphans.publish');
            Route::post('orphans/{orphan}/unpublish', [Admin\OrphanController::class, 'unpublish'])->name('orphans.unpublish');

            /* --- المستندات --- */
            Route::get('orphans/{orphan}/documents', [Admin\DocumentController::class, 'index'])->name('documents.index');
            Route::post('orphans/{orphan}/documents', [Admin\DocumentController::class, 'store'])->name('documents.store');
            Route::get('documents', [Admin\DocumentController::class, 'queue'])->name('documents.queue');
            Route::post('documents/{document}/verify', [Admin\DocumentController::class, 'verify'])->name('documents.verify');
            Route::post('documents/{document}/reject', [Admin\DocumentController::class, 'reject'])->name('documents.reject');
            Route::get('documents/{document}/download', [Admin\DocumentController::class, 'download'])->name('documents.download');
            Route::delete('documents/{document}', [Admin\DocumentController::class, 'destroy'])->name('documents.destroy');

            /* --- بيانات مرجعية للنماذج --- */
            Route::get('references', [Admin\FamilyController::class, 'references'])->name('references');

            /* --- الأسر --- */
            Route::get('families', [Admin\FamilyController::class, 'index'])->name('families.index');
            Route::post('families', [Admin\FamilyController::class, 'store'])->name('families.store');
            Route::get('families/{family}', [Admin\FamilyController::class, 'show'])->name('families.show');
            Route::match(['put', 'patch'], 'families/{family}', [Admin\FamilyController::class, 'update'])->name('families.update');

            /* --- الكفلاء والكفالات --- */
            Route::get('sponsors', [Admin\SponsorController::class, 'index'])->name('sponsors.index');
            Route::get('sponsors/{sponsor}', [Admin\SponsorController::class, 'show'])->name('sponsors.show');
            Route::match(['put', 'patch'], 'sponsors/{sponsor}', [Admin\SponsorController::class, 'update'])->name('sponsors.update');

            Route::get('sponsorships', [Admin\SponsorshipController::class, 'index'])->name('sponsorships.index');
            Route::post('sponsorships', [Admin\SponsorshipController::class, 'store'])->name('sponsorships.store');
            Route::get('sponsorships/{sponsorship}', [Admin\SponsorshipController::class, 'show'])->name('sponsorships.show');
            Route::match(['put', 'patch'], 'sponsorships/{sponsorship}', [Admin\SponsorshipController::class, 'update'])->name('sponsorships.update');
            Route::delete('sponsorships/{sponsorship}', [Admin\SponsorshipController::class, 'destroy'])->name('sponsorships.destroy');

            /* --- المال --- */
            Route::get('payments', [Admin\PaymentController::class, 'index'])->name('payments.index');
            Route::get('payments/export', [Admin\PaymentController::class, 'export'])
                ->middleware('throttle:10,1')
                ->name('payments.export');
            Route::post('payments', [Admin\PaymentController::class, 'store'])->name('payments.store');
            Route::post('payments/{payment}/refund', [Admin\PaymentController::class, 'refund'])->name('payments.refund');

            Route::get('disbursements', [Admin\DisbursementController::class, 'index'])->name('disbursements.index');
            Route::get('disbursements/export', [Admin\DisbursementController::class, 'export'])
                ->middleware('throttle:10,1')
                ->name('disbursements.export');
            Route::get('disbursement-categories', [Admin\DisbursementController::class, 'categories'])->name('disbursements.categories');
            Route::post('disbursements', [Admin\DisbursementController::class, 'store'])->name('disbursements.store');
            Route::match(['put', 'patch'], 'disbursements/{disbursement}', [Admin\DisbursementController::class, 'update'])->name('disbursements.update');
            Route::post('disbursements/{disbursement}/approve', [Admin\DisbursementController::class, 'approve'])->name('disbursements.approve');
            Route::get('disbursements/{disbursement}/receipt', [Admin\DisbursementController::class, 'receipt'])->name('disbursements.receipt');

            /* --- الميدان --- */
            Route::get('reports', [Admin\ReportController::class, 'index'])->name('reports.index');
            Route::post('reports', [Admin\ReportController::class, 'store'])->name('reports.store');
            Route::get('reports/{report}', [Admin\ReportController::class, 'show'])->name('reports.show');
            Route::match(['put', 'patch'], 'reports/{report}', [Admin\ReportController::class, 'update'])->name('reports.update');
            Route::post('reports/{report}/submit', [Admin\ReportController::class, 'submit'])->name('reports.submit');
            Route::post('reports/{report}/approve', [Admin\ReportController::class, 'approve'])->name('reports.approve');
            Route::post('reports/{report}/return', [Admin\ReportController::class, 'return'])->name('reports.return');
            Route::post('reports/{report}/publish', [Admin\ReportController::class, 'publish'])->name('reports.publish');

            Route::get('visits', [Admin\FieldVisitController::class, 'index'])->name('visits.index');
            Route::get('visits/schedule', [Admin\FieldVisitController::class, 'schedule'])->name('visits.schedule');
            Route::post('visits', [Admin\FieldVisitController::class, 'store'])->name('visits.store');

            Route::get('messages', [Admin\MessageController::class, 'index'])->name('messages.index');
            Route::post('messages/{message}/review', [Admin\MessageController::class, 'review'])->name('messages.review');
            Route::get('messages/{message}/thread', [Admin\MessageController::class, 'thread'])->name('messages.thread');
            Route::post('messages/{message}/deliver', [Admin\MessageController::class, 'deliver'])->name('messages.deliver');

            /* --- المحتوى --- */
            Route::get('posts', [Admin\ContentController::class, 'posts'])->name('posts.index');
            Route::post('posts', [Admin\ContentController::class, 'storePost'])->name('posts.store');
            Route::post('posts/images', [Admin\ContentController::class, 'postImage'])
                ->middleware('throttle:30,1')
                ->name('posts.images');
            /**
             * الربط بالمعرّف صراحةً.
             *
             * `Post::getRouteKeyName()` تُرجع `slug` من أجل روابط الموقع
             * العام، فيرث هذا المسار الإداري ذلك بلا قصد: تُرسل اللوحة
             * معرّفاً رقمياً فيردّ ٤٠٤ — ولا شيء في المسار يشي بالسبب.
             * والمعرّف أصحّ هنا على أي حال: عنوان اللوحة ليس رابطاً يُشارَك.
             */
            Route::get('posts/{post:id}', [Admin\ContentController::class, 'showPost'])->name('posts.show');
            Route::match(['put', 'patch'], 'posts/{post:id}', [Admin\ContentController::class, 'updatePost'])->name('posts.update');

            // نوع واحد من ثلاثة: faqs أو testimonials أو partners
            Route::whereIn('type', ['faqs', 'testimonials', 'partners'])->group(function (): void {
                Route::get('content/{type}', [Admin\ContentController::class, 'simple'])->name('content.index');
                Route::post('content/{type}', [Admin\ContentController::class, 'storeSimple'])->name('content.store');
                Route::post('content/{type}/reorder', [Admin\ContentController::class, 'reorderSimple'])->name('content.reorder');
                Route::match(['put', 'patch'], 'content/{type}/{id}', [Admin\ContentController::class, 'updateSimple'])->name('content.update');
                Route::delete('content/{type}/{id}', [Admin\ContentController::class, 'destroySimple'])->name('content.destroy');
            });

            Route::get('contacts', [Admin\ContactInboxController::class, 'index'])->name('contacts.index');
            Route::get('contacts/{contact}', [Admin\ContactInboxController::class, 'show'])->name('contacts.show');
            Route::post('contacts/{contact}/reply', [Admin\ContactInboxController::class, 'reply'])
                ->middleware('throttle:20,1')
                ->name('contacts.reply');
            Route::match(['put', 'patch'], 'contacts/{contact}', [Admin\ContentController::class, 'updateContact'])->name('contacts.update');
            Route::get('settings', [Admin\ContentController::class, 'settings'])->name('settings.index');
            Route::put('settings', [Admin\ContentController::class, 'updateSettings'])->name('settings.update');

            /* --- المستخدمون والسجل --- */
            Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
            Route::post('users', [Admin\UserController::class, 'store'])->name('users.store');
            Route::get('users/{user}', [Admin\UserController::class, 'show'])->name('users.show');
            Route::match(['put', 'patch'], 'users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
            Route::post('users/{user}/invite', [Admin\UserController::class, 'invite'])
                ->middleware('throttle:6,1')
                ->name('users.invite');
            Route::get('roles', [Admin\UserController::class, 'roles'])->name('roles');

            Route::get('activity/export', [Admin\ActivityController::class, 'export'])
                ->middleware('throttle:10,1')
                ->name('activity.export');
            Route::get('activity', [Admin\ActivityController::class, 'index'])->name('activity.index');
        });
});
