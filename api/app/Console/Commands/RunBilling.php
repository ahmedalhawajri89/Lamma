<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Payments\RecordPayment;
use App\Enums\PaymentMethod;
use App\Enums\SponsorshipStatus;
use App\Models\Sponsorship;
use App\Services\MetricsService;
use Illuminate\Console\Command;
use Throwable;

/**
 * تحصيل الأقساط المستحقة اليوم.
 *
 * يقرأ عبر `idx_sponsorships_due` — الفهرس `(status, next_due_at)` الذي
 * وُضع في الهجرة لهذا الأمر بالذات.
 *
 * `idempotency_key` مبنيّ من «معرّف الكفالة + الشهر»، فتشغيل الأمر مرتين في
 * اليوم نفسه — أو إعادة تشغيله بعد انقطاع في منتصف الدفعة — لا يخصم مرتين.
 * هذا هو الفرق بين أمر مجدول آمن وآخر يحتاج من يراقبه.
 */
class RunBilling extends Command
{
    protected $signature = 'billing:run
                            {--dry : اعرض ما سيحدث بلا تنفيذ}
                            {--limit=500 : أقصى عدد كفالات في التشغيلة}';

    protected $description = 'تحصيل أقساط الكفالات المستحقة اليوم';

    public function handle(RecordPayment $recordPayment, MetricsService $metrics): int
    {
        $dry = (bool) $this->option('dry');
        $limit = (int) $this->option('limit');

        $this->expireAbandonedHolds();

        $due = Sponsorship::query()
            ->dueBy()
            ->with(['sponsor:id,preferred_currency', 'orphan:id,first_name'])
            ->limit($limit)
            ->get();

        if ($due->isEmpty()) {
            $this->info('لا أقساط مستحقة اليوم.');

            return self::SUCCESS;
        }

        $this->line(sprintf('%d قسطاً مستحقاً%s.', $due->count(), $dry ? ' (تجربة)' : ''));

        $done = 0;
        $failed = 0;

        foreach ($due as $sponsorship) {
            $key = hash('sha256', "{$sponsorship->id}-".now()->format('Y-m'));

            if ($dry) {
                $this->line("  · {$sponsorship->reference_code} — {$sponsorship->monthly_amount} {$sponsorship->currency}");
                $done++;

                continue;
            }

            try {
                $recordPayment->handle(
                    sponsorship: $sponsorship,
                    idempotencyKey: $key,
                    method: PaymentMethod::Card,
                );
                $done++;
            } catch (Throwable $e) {
                // كفالة واحدة فاشلة لا توقف التشغيلة كلها
                $failed++;
                $this->error("  × {$sponsorship->reference_code}: {$e->getMessage()}");
                report($e);
            }
        }

        if (! $dry) {
            $metrics->flushCache();
        }

        $this->newLine();
        $this->info("نجح: {$done}".($failed > 0 ? " · فشل: {$failed}" : ''));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * إلغاء الحجوزات المهجورة.
     *
     * الكفالة تُنشأ بحالة `pending` وتُحتسب فوراً في تمويل الطفل — وهذا
     * مقصود، فهو يمنع كافلين من حجز الطفل نفسه في اللحظة نفسها. لكنه يعني
     * أن من فتح صفحة الدفع ثم أغلقها يحجب الطفل إلى الأبد.
     *
     * فنلغي كل حجز تجاوز مهلة السماح ولم تصله دفعة واحدة. عندها يعيد
     * SponsorshipObserver حساب التمويل ويعود الطفل إلى الموقع.
     */
    private function expireAbandonedHolds(): void
    {
        $deadline = now()->subDays((int) config('kafala.billing.grace_days'));

        $abandoned = Sponsorship::query()
            ->where('status', SponsorshipStatus::Pending)
            ->where('created_at', '<', $deadline)
            ->whereDoesntHave('payments')
            ->get();

        foreach ($abandoned as $sponsorship) {
            $sponsorship->update([
                'status' => SponsorshipStatus::Cancelled,
                'cancellation_reason' => 'حجز لم يُستكمل دفعه',
                'ended_at' => now(),
                'next_due_at' => null,
            ]);
        }

        if ($abandoned->isNotEmpty()) {
            $this->line("أُلغي {$abandoned->count()} حجزاً مهجوراً.");
        }
    }
}
