<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Sponsorship;

/**
 * يبقي `orphans.funded_percent` صادقاً.
 *
 * لماذا Observer لا استدعاء في المتحكّم؟
 * لأن الكفالة تتغيّر من ستة مسارات مختلفة على الأقل: إنشاء من الموقع،
 * إلغاء من لوحة الكافل، إيقاف من الإدارة، اكتمال بأمر مجدول، وتعديل مبلغ،
 * واستعادة سجل محذوف. الاستدعاء اليدوي يعني نسيانه في أحدها يوماً ما،
 * فتظهر نسبة تمويل خاطئة على صفحة عامة.
 *
 * هنا يستحيل نسيانه.
 */
class SponsorshipObserver
{
    public function saved(Sponsorship $sponsorship): void
    {
        // لا داعي لإعادة الحساب إن لم يتغيّر ما يؤثّر فيه
        if ($sponsorship->wasChanged(['status', 'monthly_amount']) || $sponsorship->wasRecentlyCreated) {
            $sponsorship->orphan?->recalculateFunding();
        }
    }

    public function deleted(Sponsorship $sponsorship): void
    {
        $sponsorship->orphan?->recalculateFunding();
    }

    public function restored(Sponsorship $sponsorship): void
    {
        $sponsorship->orphan?->recalculateFunding();
    }
}
