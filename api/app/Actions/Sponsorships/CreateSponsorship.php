<?php

declare(strict_types=1);

namespace App\Actions\Sponsorships;

use App\Enums\SponsorshipStatus;
use App\Models\Orphan;
use App\Models\Program;
use App\Models\Sponsor;
use App\Models\Sponsorship;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * إنشاء كفالة.
 *
 * ثلاث قواعد تعيش هنا لا في المتحكّم، لأن الكفالة تُنشأ من ثلاثة مسارات
 * (الموقع العام، ولوحة الكافل، ولوحة الإدارة) ولا يجوز أن تختلف بينها:
 *
 *  1. المبلغ يُنسخ من البرنامج لحظة الإنشاء — snapshot pricing.
 *  2. يوم الفوترة مقيَّد بـ 1..28، فلا يختفي القسط في فبراير.
 *  3. لا كفالة على ملف لا يقبلها (مسوّدة، أو بانتظار تحقق، أو متخرّج).
 */
class CreateSponsorship
{
    public function handle(
        Sponsor $sponsor,
        Orphan $orphan,
        ?float $monthlyAmount = null,
        bool $isZakat = false,
        ?int $billingDay = null,
    ): Sponsorship {
        if (! $orphan->status->acceptsSponsorship()) {
            throw ValidationException::withMessages([
                'orphan_id' => 'هذا الملف لا يقبل كفالة جديدة حالياً.',
            ]);
        }

        return DB::transaction(function () use ($sponsor, $orphan, $monthlyAmount, $isZakat, $billingDay): Sponsorship {
            /**
             * قفل الصف يمنع سباقاً واقعياً: كافلان يفتحان صفحة الطفل نفسها
             * ويضغطان «اكفل» في اللحظة نفسها. بلا القفل يمرّ الاثنان من فحص
             * التمويل ويتجاوز المجموع حاجة الطفل.
             */
            $orphan = Orphan::query()->lockForUpdate()->findOrFail($orphan->id);

            $program = Program::findOrFail($orphan->program_id);
            $amount = $monthlyAmount ?? (float) $program->monthly_price;

            $remaining = (float) $orphan->monthly_need * (100 - $orphan->funded_percent) / 100;

            if ($remaining <= 0) {
                throw ValidationException::withMessages([
                    'orphan_id' => 'اكتمل تمويل هذا الطفل. اختر حالة أخرى تنتظر كافلاً.',
                ]);
            }

            $amount = min($amount, round($remaining, 2));

            if ($amount < 5) {
                throw ValidationException::withMessages([
                    'monthly_amount' => 'أقل مبلغ للكفالة ٥ دولارات شهرياً.',
                ]);
            }

            $day = max(1, min(config('kafala.billing.max_billing_day'), $billingDay ?? (int) now()->day));

            $sponsorship = new Sponsorship([
                'sponsor_id' => $sponsor->id,
                'orphan_id' => $orphan->id,
                'program_id' => $program->id,
                'monthly_amount' => $amount,
                'currency' => $program->currency,
                'share_percent' => (int) round($amount / (float) $orphan->monthly_need * 100),
                'is_zakat' => $isZakat,
                'status' => SponsorshipStatus::Pending,
                'billing_day' => $day,
                'started_at' => now()->toDateString(),
                'next_due_at' => Carbon::now()->setDay($day)->isPast()
                    ? Carbon::now()->addMonthNoOverflow()->setDay($day)->toDateString()
                    : Carbon::now()->setDay($day)->toDateString(),
            ]);

            $sponsorship->reference_code = $this->nextReference();
            $sponsorship->save();

            return $sponsorship;
        });
    }

    /**
     * الترقيم داخل المعاملة نفسها وبقفل — وإلا حصل طلبان متزامنان على
     * الرقم نفسه وفشل القيد الفريد على reference_code.
     */
    private function nextReference(): string
    {
        $year = now()->year;
        $last = Sponsorship::query()
            ->where('reference_code', 'like', "KFS-{$year}-%")
            ->lockForUpdate()
            ->max('reference_code');

        $next = $last ? ((int) substr($last, -5)) + 1 : 1;

        return sprintf('KFS-%d-%05d', $year, $next);
    }
}
