<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SponsorshipStatus;
use App\Models\Orphan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * فحص اتساق البيانات.
 *
 * المخطّط يمنع أخطاء كثيرة بالقيود، لكن ثلاثة أشياء لا يستطيع المحرّك حراستها
 * وحده — وهذا الأمر يكشفها. يُشغَّل أسبوعياً وقبل كل نشر.
 */
class CheckDataIntegrity extends Command
{
    protected $signature = 'kafala:check {--fix : أصلح ما يمكن إصلاحه تلقائياً}';

    protected $description = 'فحص اتساق البيانات بين الجداول';

    public function handle(): int
    {
        $issues = 0;

        // ١) نسبة تمويل لا تطابق مجموع الكفالات الحيّة —
        //    يقع لو أُدرجت كفالات بالجملة متجاوزةً الـ Observer (كما في البذور)
        $drifted = Orphan::query()
            ->select('orphans.id', 'orphans.reference_code', 'orphans.funded_percent', 'orphans.monthly_need')
            ->selectRaw('COALESCE((
                SELECT SUM(s.monthly_amount) FROM sponsorships s
                WHERE s.orphan_id = orphans.id AND s.deleted_at IS NULL AND s.status IN (?, ?)
            ), 0) as covered', [SponsorshipStatus::Active->value, SponsorshipStatus::Paused->value])
            ->get()
            ->filter(function ($o): bool {
                $expected = (float) $o->monthly_need > 0
                    ? (int) min(100, round((float) $o->covered / (float) $o->monthly_need * 100))
                    : 0;

                return $expected !== (int) $o->funded_percent;
            });

        if ($drifted->isNotEmpty()) {
            $issues += $drifted->count();
            $this->warn("نسب تمويل غير متطابقة: {$drifted->count()}");

            if ($this->option('fix')) {
                foreach ($drifted as $row) {
                    Orphan::find($row->id)?->recalculateFunding();
                }
                $this->info('  ← أُعيد حسابها.');
            } else {
                $this->line('  '.$drifted->take(5)->pluck('reference_code')->implode('، ').'…');
            }
        }

        // ٢) دفعات ناجحة بلا تاريخ دفع — تكسر كل تقرير مالي بصمت
        $noPaidAt = DB::table('payments')->where('status', 'succeeded')->whereNull('paid_at')->count();
        if ($noPaidAt > 0) {
            $issues += $noPaidAt;
            $this->warn("دفعات ناجحة بلا paid_at: {$noPaidAt}");
        }

        // ٣) أيتام منشورون بلا توثيق — خرق مباشر لسياسة النشر
        $unverified = Orphan::whereNotNull('published_at')->whereNull('verified_at')->count();
        if ($unverified > 0) {
            $issues += $unverified;
            $this->error("أيتام منشورون بلا توثيق: {$unverified}");
        }

        if ($issues === 0) {
            $this->info('البيانات متسقة.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn("الإجمالي: {$issues} ملاحظة.");

        return self::FAILURE;
    }
}
