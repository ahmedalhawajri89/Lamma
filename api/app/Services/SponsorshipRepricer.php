<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Sponsorship;
use App\Support\Fx;

/**
 * ما يعادل كل كفالةٍ بعملةٍ أخرى بعملة الأساس، بأسعار اليوم.
 *
 * الكافل بالجنيه يدفع £40 كل شهر لا يتغيّر، وما يصل الطفل منها بالدولار
 * يتغيّر مع السعر. monthly_amount هو ما تُحسب به نسبة تمويل الطفل
 * وتقارير الالتزام الشهري، فيُعاد حسابه بعد كل تحديثٍ للأسعار؛ وحفظه
 * يُطلق SponsorshipObserver فيعيد حساب تمويل الطفل.
 */
final class SponsorshipRepricer
{
    /** @return int عدد الكفالات التي تغيّر ما يعادلها */
    public function reprice(): int
    {
        $changed = 0;

        Sponsorship::query()
            ->live()
            ->whereNotNull('charge_currency')
            ->whereColumn('charge_currency', '!=', 'currency')
            // الطفل كاملاً: المراقب يعيد حساب تمويله بعد الحفظ
            ->with('orphan')
            ->chunkById(200, function ($chunk) use (&$changed): void {
                foreach ($chunk as $s) {
                    $base = Fx::toBase((float) $s->charge_amount, $s->charge_currency);
                    if (abs($base - (float) $s->monthly_amount) < 0.01) {
                        continue;
                    }

                    $need = (float) ($s->orphan?->monthly_need ?? 0);
                    $s->monthly_amount = $base;
                    $s->share_percent = $need > 0 ? (int) min(100, round($base / $need * 100)) : $s->share_percent;
                    // يومياً مع الأسعار: لا يُسجَّل في سجلّ النشاط، فلا يغرقه
                    activity()->withoutLogs(fn () => $s->save());
                    $changed++;
                }
            });

        return $changed;
    }
}
