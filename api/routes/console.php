<?php

declare(strict_types=1);

use App\Console\Commands\CheckDataIntegrity;
use App\Console\Commands\RollupMetrics;
use App\Console\Commands\RunBilling;
use Illuminate\Support\Facades\Schedule;

/**
 * الجدولة.
 *
 * `withoutOverlapping` على الفوترة ليس احتياطاً نظرياً: تشغيلة تأخذ دقائق
 * على آلاف الكفالات، وبلا القفل يبدأ تشغيل الغد قبل أن ينتهي اليوم على خادم
 * بطيء. و`onOneServer` لأن النشر خلف أكثر من خادم يشغّل الأمر على كلٍّ منها.
 */
Schedule::command(RunBilling::class)
    ->dailyAt('02:00')
    ->timezone(config('kafala.display_timezone'))
    ->withoutOverlapping(30)
    ->onOneServer()
    ->runInBackground();

Schedule::command(RollupMetrics::class)
    ->dailyAt('03:00')
    ->timezone(config('kafala.display_timezone'))
    ->onOneServer();

Schedule::command(CheckDataIntegrity::class)
    ->weeklyOn(1, '04:00')
    ->timezone(config('kafala.display_timezone'))
    ->onOneServer();
