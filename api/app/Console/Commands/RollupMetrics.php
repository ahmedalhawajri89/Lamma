<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DailyMetric;
use App\Models\Sponsorship;
use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * يملأ `daily_metrics` — الجدول الذي تقرأ منه مخططات اللوحة.
 *
 * يعمل كل ليلة على يوم أمس. و`--backfill` لإعادة بناء التاريخ كاملاً بعد
 * استيراد بيانات أو تغيير في طريقة الحساب.
 */
class RollupMetrics extends Command
{
    protected $signature = 'metrics:rollup
                            {--date= : يوم بعينه (Y-m-d)، الافتراضي أمس}
                            {--backfill= : عدد الأيام الماضية لإعادة بنائها}
                            {--all : إعادة بناء كل التاريخ من أول كفالة}';

    protected $description = 'بناء صفوف daily_metrics التي تغذّي تحليلات اللوحة';

    public function handle(MetricsService $metrics): int
    {
        $days = $this->resolveDays();

        if ($days === []) {
            $this->warn('لا توجد أيام لمعالجتها.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar(count($days));
        $bar->start();

        foreach ($days as $day) {
            $metrics->rollupDay($day);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $metrics->flushCache();

        $this->info(sprintf(
            'تم بناء %d صفاً. الجدول يحوي الآن %d يوماً.',
            count($days),
            DailyMetric::count(),
        ));

        return self::SUCCESS;
    }

    /** @return array<int, Carbon> */
    private function resolveDays(): array
    {
        if ($this->option('date')) {
            return [Carbon::parse((string) $this->option('date'))];
        }

        if ($this->option('all')) {
            $first = Sponsorship::withTrashed()->min('started_at');

            if ($first === null) {
                return [];
            }

            $start = Carbon::parse($first)->startOfDay();
            $this->line("إعادة بناء من {$start->toDateString()} حتى اليوم…");

            return $this->range($start, Carbon::today());
        }

        if ($this->option('backfill')) {
            $count = max(1, (int) $this->option('backfill'));

            return $this->range(Carbon::today()->subDays($count - 1), Carbon::today());
        }

        return [Carbon::yesterday()];
    }

    /** @return array<int, Carbon> */
    private function range(Carbon $from, Carbon $to): array
    {
        $days = [];

        for ($cursor = $from->copy(); $cursor->lte($to); $cursor->addDay()) {
            $days[] = $cursor->copy();
        }

        return $days;
    }
}
