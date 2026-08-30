<?php

declare(strict_types=1);

namespace App\Actions\Sponsorships;

use App\Enums\SponsorshipStatus;
use App\Models\Sponsorship;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * إلغاء كفالة.
 *
 * الإلغاء لا يحذف شيئاً: يغيّر الحالة فقط. المدفوعات السابقة تبقى كما هي،
 * والسجل التاريخي يبقى قابلاً للتدقيق. وتحوّل الحالة إلى cancelled يُفرِغ
 * العمود المُولَّد active_key، فيُحرَّر القيد الفريد ويستطيع الكافل نفسه
 * أن يعود لكفالة الطفل نفسه لاحقاً.
 */
class CancelSponsorship
{
    public function handle(Sponsorship $sponsorship, ?string $reason = null): Sponsorship
    {
        if (! $sponsorship->status->isLive()) {
            throw ValidationException::withMessages([
                'status' => 'هذه الكفالة منتهية أو ملغاة أصلاً.',
            ]);
        }

        return DB::transaction(function () use ($sponsorship, $reason): Sponsorship {
            $sponsorship->update([
                'status' => SponsorshipStatus::Cancelled,
                'cancellation_reason' => $reason,
                'ended_at' => now(),
                'next_due_at' => null,
            ]);

            // SponsorshipObserver يعيد حساب funded_percent، فتعود الحالة
            // من fully_funded إلى active وتظهر على الموقع تنتظر كافلاً
            return $sponsorship->refresh();
        });
    }
}
