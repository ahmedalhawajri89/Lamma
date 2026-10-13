<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Disbursement;
use App\Models\User;

class DisbursementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('disbursements.view');
    }

    public function create(User $user): bool
    {
        return $user->can('disbursements.record');
    }

    public function update(User $user, Disbursement $disbursement): bool
    {
        // المعتمَد لا يُعدَّل — يُعكَس بقيد مضاد، فيبقى الأثر المحاسبي كاملاً
        return $user->can('disbursements.record') && $disbursement->approved_at === null;
    }

    /**
     * ولا يعتمد المسجِّل صرفه بنفسه، ولا من عدّل مبلغه: كان من يملك الصلاحيتين
     * يعدّل مبلغ صرفٍ سجّله غيره ثم يعتمده، فتسقط العين الثانية.
     */
    public function approve(User $user, Disbursement $disbursement): bool
    {
        return $user->can('disbursements.approve')
            && $disbursement->approved_at === null
            && $disbursement->recorded_by !== $user->id
            && $disbursement->updated_by !== $user->id;
    }

    public function viewReceipt(User $user, Disbursement $disbursement): bool
    {
        return $user->can('disbursements.view');
    }
}
