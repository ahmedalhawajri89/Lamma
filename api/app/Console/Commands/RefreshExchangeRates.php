<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ExchangeRate;
use App\Services\SponsorshipRepricer;
use App\Support\Fx;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * تحديث أسعار الصرف يومياً، لعملات الكفلاء وعملات الصرف معاً.
 *
 * فشل المصدر لا يمسّ الأسعار الحالية: تبقى كما هي حتى ينجح تحديثٌ لاحق،
 * وتقول شاشة الإعدادات منذ متى لم تُحدَّث. وسعرٌ يقفز أكثر من الربع في يوم
 * يُرفض: خطأ مصدرٍ أرجح من انهيار عملة، والخطأ هنا يغيّر ما يُخصم من الكفلاء.
 *
 * بعد التحديث يُعاد حساب ما يعادل كل كفالةٍ بعملةٍ أخرى بالأساس، فتبقى
 * نسب تمويل الأطفال صادقة (SponsorshipRepricer).
 */
class RefreshExchangeRates extends Command
{
    protected $signature = 'kafala:rates-refresh';

    protected $description = 'تحديث أسعار الصرف إلى عملة الأساس';

    private const MAX_DAILY_JUMP = 0.25;

    public function handle(SponsorshipRepricer $repricer): int
    {
        $base = Fx::base();
        $wanted = array_values(array_unique([...Fx::sponsorCurrencies(), ...(array) config('kafala.currencies', [])]));

        try {
            $response = Http::timeout(15)->retry(2, 500)->acceptJson()->get(rtrim((string) config('kafala.fx.url'), '/').'/'.$base);
            $rates = $response->json('rates');

            if (! $response->successful() || ! is_array($rates) || $response->json('result', 'success') !== 'success') {
                throw new \RuntimeException('استجابة غير متوقعة من مصدر الأسعار.');
            }
        } catch (Throwable $e) {
            $this->error('تعذّر جلب الأسعار، وبقيت الحالية كما هي: '.$e->getMessage());
            report($e);

            return self::FAILURE;
        }

        $updated = [];
        $rejected = [];
        $current = ExchangeRate::query()->pluck('rate_to_base', 'currency')->map(fn ($r) => (float) $r);

        foreach ($wanted as $code) {
            if ($code === $base) {
                continue;
            }
            $perBase = (float) ($rates[$code] ?? 0);
            if ($perBase <= 0) {
                $rejected[] = "{$code} (غائب)";

                continue;
            }

            $rate = round(1 / $perBase, 8);
            $old = $current[$code] ?? null;
            // القفزة تُقاس على سعرٍ جاء من المصدر، لا على الابتدائي التقريبي
            if ($old !== null && ExchangeRate::where('currency', $code)->value('source') !== 'seed' && abs($rate - $old) / $old > self::MAX_DAILY_JUMP) {
                $rejected[] = "{$code} (قفز من {$old} إلى {$rate})";

                continue;
            }

            ExchangeRate::updateOrCreate(['currency' => $code], [
                'rate_to_base' => $rate,
                'source' => parse_url((string) config('kafala.fx.url'), PHP_URL_HOST) ?: 'api',
                'fetched_at' => now(),
            ]);
            $updated[] = $code;
        }

        Fx::flush();
        $repriced = $repricer->reprice();

        $this->info(sprintf('حُدّث %d سعراً، وأُعيد حساب %d كفالة بعملةٍ أخرى.', count($updated), $repriced));
        if ($rejected) {
            $this->warn('لم يُحدَّث: '.implode('، ', $rejected));
        }

        return self::SUCCESS;
    }
}
