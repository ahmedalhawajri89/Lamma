<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payments.view') || $user->sponsor !== null;
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->can('payments.view')
            || ($user->sponsor !== null && $payment->sponsor_id === $user->sponsor->id);
    }

    public function record(User $user): bool
    {
        return $user->can('payments.record');
    }

    public function refund(User $user, Payment $payment): bool
    {
        return $user->can('payments.refund') && $payment->status->isSettled();
    }
}
