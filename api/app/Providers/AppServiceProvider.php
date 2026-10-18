<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Sponsorship;
use App\Observers\SponsorshipObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // البوابة من الإعداد؛ والتجريبية افتراضاً حتى تُربط بوابة حقيقية
        $this->app->bind(\App\Payments\PaymentGateway::class, fn () => match (config('kafala.gateway')) {
            default => new \App\Payments\DemoGateway(),
        });

        //
    }

    public function boot(): void
    {
        Sponsorship::observe(SponsorshipObserver::class);

        // هوية الموقع وتنقّله لقوالب الموقع العام: الترويسة والفوتر يُرسمان من الخادم.
        // والصفحات نفسها تحتاج الاسم (وصفها وJSON-LD ونصوص المشاركة)، لا القالب وحده
        View::composer(['site.layouts.*', 'site.pages.*', 'site.auth.*', 'site.portal.*', 'errors.*'], fn ($view) => $view->with([
            'site' => \App\Support\SiteProfile::forViews(),
            'nav' => \App\Support\SiteProfile::nav(request()->path()),
            'navFlat' => \App\Support\SiteProfile::flatNav(request()->path()),
        ]));

        /**
         * انضباط Eloquent — يحوّل ثلاثة أخطاء صامتة إلى استثناءات صريحة
         * في التطوير، فتُكتشف وقت كتابة الكود لا في الإنتاج:
         *
         *  · preventLazyLoading      → يمسك مشكلة N+1 وقت حدوثها
         *  · preventSilentlyDiscarding → يمنع ضياع حقل نُسي في $fillable
         *  · preventAccessingMissing → يمنع قراءة عمود لم يُحمَّل في select()
         */
        Model::shouldBeStrict(! app()->isProduction());

        // في الإنتاج نسجّل مشكلة N+1 بدل إسقاط الطلب على المستخدم
        if (app()->isProduction()) {
            Model::preventLazyLoading(false);
        }

        Model::unguard(false);

        Date::use(Carbon::class);

        /**
         * الحدّ العام لكل مسارات api — يستدعيه throttleApi() في bootstrap/app.php.
         *
         * المفتاح هو معرّف المستخدم إن كان داخلاً، وعنوان IP إن لم يكن. الفرق
         * مهم: موظفو مكتب واحد خلف NAT يتشاركون IP، فالحدّ بالـ IP وحده يجعل
         * أحدهم يعطّل البقية بمجرد استعماله الطبيعي للوحة.
         *
         * ورقم الزائر (‏٢٤٠) ليس سخاءً: **كل صفحة في الموقع العام تُصدر من
         * طلبين إلى أربعة** لترطيب بياناتها. فستون في الدقيقة تكفي خمس عشرة
         * صفحة فقط — وزائرٌ يتصفّح الحالات بسرعة يبلغها، وكذلك مدرسةٌ أو
         * مكتبٌ خلف عنوان واحد. وهي قراءات مُخزَّنة في الكاش وثمنها زهيد.
         *
         * أما الكتابة فمحدودة بستّ محاولات في الدقيقة على مساراتها في
         * `routes/api.php` — وهناك يقع الضغط الحقيقي، لا هنا.
         */
        RateLimiter::for('api', static fn (Request $request): Limit => $request->user()
            ? Limit::perMinute(240)->by('u:'.$request->user()->id)
            : Limit::perMinute(240)->by('ip:'.$request->ip()));
    }
}
