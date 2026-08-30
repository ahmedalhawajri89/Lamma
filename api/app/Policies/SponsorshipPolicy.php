<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Sponsorship;
use App\Models\User;

class SponsorshipPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sponsorships.view');
    }

    public function view(User $user, Sponsorship $sponsorship): bool
    {
        return $this->owns($user, $sponsorship) || $user->can('sponsorships.view');
    }

    public function manage(User $user): bool
    {
        return $user->can('sponsorships.manage');
    }

    /** الكافل يلغي كفالته بنفسه؛ لا يحتاج إذن موظف. */
    public function cancel(User $user, Sponsorship $sponsorship): bool
    {
        return $this->owns($user, $sponsorship) || $user->can('sponsorships.manage');
    }

    /** الرسائل تخصّ صاحب الكفالة وحده. */
    public function message(User $user, Sponsorship $sponsorship): bool
    {
        return $this->owns($user, $sponsorship);
    }

    private function owns(User $user, Sponsorship $sponsorship): bool
    {
        return $user->sponsor !== null && $sponsorship->sponsor_id === $user->sponsor->id;
    }
}
