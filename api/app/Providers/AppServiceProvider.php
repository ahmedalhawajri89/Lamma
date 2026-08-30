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
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Sponsorship::observe(SponsorshipObserver::class);

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
         */
        RateLimiter::for('api', static fn (Request $request): Limit => $request->user()
            ? Limit::perMinute(120)->by('u:'.$request->user()->id)
            : Limit::perMinute(60)->by('ip:'.$request->ip()));
    }
}
