<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TransferPreparer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * يجهّز مسوّدات تحويلات الشهر للجمعيات الشريكة (TransferPreparer).
 * بلا شهر: الشهر الماضي، فيُشغَّل أول كل شهر لما اكتمل قبله.
 */
class PrepareTransfers extends Command
{
    protected $signature = 'kafala:transfers-prepare {month? : الشهر بصيغة YYYY-MM، والماضي إن غاب}';

    protected $description = 'يجهّز مسوّدات تحويلات الشهر للجمعيات الشريكة من دفعات كفلاء أطفالها';

    public function handle(TransferPreparer $preparer): int
    {
        $arg = $this->argument('month');

        if ($arg !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $arg) !== 1) {
            $this->error('الشهر بصيغة YYYY-MM، مثل 2026-09.');

            return self::INVALID;
        }

        $month = $arg !== null
            ? Carbon::createFromFormat('Y-m-d', "{$arg}-01")
            : now(config('kafala.display_timezone'))->subMonthNoOverflow();

        $r = $preparer->prepare($month);

        $this->info(sprintf(
            '%s: %d تحويلاً جديداً، %d مسوّدة أُعيد بناؤها، %d أُرسل ولم يُمسّ. المجموع %s.',
            $month->format('Y-m'), $r['created'], $r['rebuilt'], $r['locked'], number_format($r['total'], 2),
        ));

        return self::SUCCESS;
    }
}
