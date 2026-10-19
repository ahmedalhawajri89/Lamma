<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ContinuityFund;
use App\Services\MetricsService;
use Illuminate\Console\Command;

/**
 * التغطية اليومية من صندوق الاستمرارية، بعد billing:run: كل قسطٍ استنفد
 * محاولاته ومضى على استحقاقه مهلة الاستعادة يغطّيه الصندوق شهراً، أو تنتهي
 * كفالته إن نفدت أشهر التغطية أو الرصيد (ContinuityFund::coverDue).
 */
class CoverWithContinuityFund extends Command
{
    protected $signature = 'kafala:continuity-cover';

    protected $description = 'تغطية الأقساط المتعثّرة من صندوق الاستمرارية';

    public function handle(ContinuityFund $fund, MetricsService $metrics): int
    {
        $r = $fund->coverDue();

        if ($r['covered'] + $r['ended'] > 0) {
            $metrics->flushCache();
        }

        $this->info(sprintf('غُطّي %d قسطاً (%s)، وانتهت %d كفالة.', $r['covered'], number_format($r['total'], 2), $r['ended']));

        return self::SUCCESS;
    }
}
