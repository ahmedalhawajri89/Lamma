<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Payments\RecordPayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SponsorshipStatus;
use App\Mail\PaymentIssueMail;
use App\Models\Payment;
use App\Models\Sponsorship;
use App\Payments\ChargeDeclined;
use App\Payments\PaymentGateway;
use App\Services\MetricsService;
use App\Support\Fx;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
 *
 * والخصم يمرّ بالبوابة فعلاً، بالبطاقة التي حفظتها صفحة الكفالة عند أول خصم.
 * كان الأمر يسجّل دفعة «بطاقة» ناجحة بإيصالها لكل كفالة مستحقة بلا أن يخصم
 * شيئاً: الكافل يرى إيصالاً لمالٍ لم يُدفع، والتقارير تحسب دخلاً غير موجود.
 * كفالةٌ بلا بطاقة محفوظة تُسجَّل محاولتها فاشلةً بسببها، فيتابعها المحاسب.
 */
class RunBilling extends Command
{
    protected $signature = 'billing:run
                            {--dry : اعرض ما سيحدث بلا تنفيذ}
                            {--limit=500 : أقصى عدد كفالات في التشغيلة}';

    protected $description = 'تحصيل أقساط الكفالات المستحقة اليوم';

    public function handle(RecordPayment $recordPayment, PaymentGateway $gateway, MetricsService $metrics): int
    {
        $dry = (bool) $this->option('dry');
        $limit = (int) $this->option('limit');

        // التجربة لا تلغي شيئاً: كان «--dry» يلغي حجوزاً حقيقية قبل أن يعرض ما سيحدث
        if (! $dry) {
            $this->expireAbandonedHolds();
        }

        $due = Sponsorship::query()
            ->dueBy()
            ->with(['sponsor:id,user_id,preferred_currency', 'sponsor.user:id,name,email,locale', 'orphan:id,first_name,translations,reference_code'])
            ->limit($limit)
            ->get();

        if ($due->isEmpty()) {
            $this->info('لا أقساط مستحقة اليوم.');

            return self::SUCCESS;
        }

        $this->line(sprintf('%d قسطاً مستحقاً%s.', $due->count(), $dry ? ' (تجربة)' : ''));

        $done = 0;
        $failed = 0;
        $manual = 0;

        foreach ($due as $sponsorship) {
            $key = $sponsorship->dueKey();

            /*
             * من يدفع بتحويل أو نقداً لا يُخصم منه آلياً. كان الأمر ينشئ دفعة
             * «بطاقة» ناجحة لكل كفالة مستحقة، فإذا سجّل المحاسب التحويل الحقيقي
             * حُسب الشهر مرتين. يبقى قسطه مستحقاً يتابعه المحاسب يدوياً.
             */
            $lastMethod = $sponsorship->payment_method
                ?? $sponsorship->payments()->where('status', PaymentStatus::Succeeded)->latest('paid_at')->value('method')?->value;
            if (in_array($lastMethod, [PaymentMethod::BankTransfer->value, PaymentMethod::Cash->value], true)) {
                $manual++;

                continue;
            }

            // قسط هذا الشهر سُجّل من قبل (تشغيلٌ ثانٍ اليوم): لا نطلب من البوابة خصماً آخر
            if (Payment::where('idempotency_key', $key)->exists()) {
                continue;
            }

            $card = $sponsorship->gateway_payment_method;

            if ($dry) {
                $this->line("  · {$sponsorship->reference_code} — {$sponsorship->chargeCycleAmount()} {$sponsorship->chargeCurrency()}".($card ? '' : ' (بلا بطاقة محفوظة)'));
                $done++;

                continue;
            }

            try {
                if ($card === null) {
                    throw new ChargeDeclined('لا بطاقة محفوظة لهذه الكفالة. تواصل مع الكافل ليحدّث وسيلة الدفع.');
                }

                $result = $gateway->chargeSaved($card, $sponsorship->chargeCycleAmount(), $sponsorship->chargeCurrency(), $key);

                if (! $result->succeeded) {
                    throw new ChargeDeclined($result->failureReason ?? 'رفضت البوابة الخصم.');
                }

                $recordPayment->handle(
                    sponsorship: $sponsorship,
                    idempotencyKey: $key,
                    method: PaymentMethod::Card,
                    cardBrand: $sponsorship->card_brand,
                    cardLast4: $sponsorship->card_last4,
                    gatewayReference: $result->reference,
                );
                $done++;
            } catch (Throwable $e) {
                // كفالة واحدة فاشلة لا توقف التشغيلة كلها
                $failed++;

                // المحاولة الفاشلة تُحفظ كي يتابعها المحاسب («دفعات فشلت هذا الشهر»)؛
                // كانت تُرمى وحدها فلا أثر لها في أي شاشة. مفتاحها غير مفتاح النجاح،
                // فإعادة المحاولة لاحقاً تبقى ممكنة.
                $this->recordFailure($sponsorship, $key, $e->getMessage());
                $this->scheduleRetry($sponsorship, $e->getMessage());
                $this->error("  × {$sponsorship->reference_code}: {$e->getMessage()}");

                // رفض البنك أو غياب البطاقة نتيجةٌ متوقعة تُتابع من شاشة الدفعات،
                // لا عطلٌ في البرنامج يُبلَّغ عنه
                if (! $e instanceof ChargeDeclined) {
                    report($e);
                }
            }
        }

        if (! $dry) {
            $metrics->flushCache();
        }

        $this->newLine();
        $this->info("نجح: {$done}".($failed > 0 ? " · فشل: {$failed}" : '').($manual > 0 ? " · يدوي يتابعه المحاسب: {$manual}" : ''));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * التعثّر: المحاولة التالية في موعدها (retry_after_days) لا غداً، وأول
     * رفضٍ فيه يُبلَّغ به الكافل بلغته مع رابط تحديث البطاقة. البريد لا
     * يُسقط التشغيلة إن فشل.
     */
    private function scheduleRetry(Sponsorship $sponsorship, string $reason): void
    {
        try {
            $first = $sponsorship->recordPaymentFailure();
            $user = $sponsorship->sponsor?->user;

            if ($first && $user?->email) {
                // بلغة الكافل: User::preferredLocale
                Mail::to($user)->send(new PaymentIssueMail($sponsorship, $reason));
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function recordFailure(Sponsorship $sponsorship, string $key, string $reason): void
    {
        try {
            DB::transaction(fn () => Payment::firstOrCreate(
                // مُجزّأ لا مُلصق: المفتاح 64 حرفاً هو العمود كله، و«-failed-…» بعده
                // كان يتجاوزه فيسقط الإدراج بصمت، فلا تُحفظ أي محاولة فاشلة أبداً
                ['idempotency_key' => hash('sha256', $key.'|failed|'.now()->format('Ymd'))],
                [
                    // من مولّد المراجع نفسه: مرجع بصيغة أخرى كان يُفسد حساب «الأكبر»
                    'reference_code' => app(RecordPayment::class)->nextReference(),
                    'sponsorship_id' => $sponsorship->id,
                    'sponsor_id' => $sponsorship->sponsor_id,
                    'amount' => $sponsorship->chargeCycleAmount(),
                    'currency' => $sponsorship->chargeCurrency(),
                    'fx_rate' => Fx::rate($sponsorship->chargeCurrency()),
                    'amount_base' => Fx::toBase($sponsorship->chargeCycleAmount(), $sponsorship->chargeCurrency()),
                    'method' => PaymentMethod::Card,
                    'gateway' => config('kafala.gateway', 'manual'),
                    'status' => PaymentStatus::Failed,
                    'failure_reason' => mb_substr($reason, 0, 255),
                ],
            ));
        } catch (Throwable $e) {
            report($e);
        }
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
            // محاولة دفع فاشلة ليست دفعاً: كانت تُبقي الحجز إلى الأبد
            ->whereDoesntHave('payments', fn ($q) => $q->where('status', PaymentStatus::Succeeded))
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
