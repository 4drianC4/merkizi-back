<?php

namespace App\Modules\Cash\Policies;

use App\Modules\Cash\Models\CashSession;
use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Concerns\AuthorizesTenantMembership;

class CashSessionPolicy
{
    use AuthorizesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->isMember($user, $this->tenant());
    }

    public function view(User $user, CashSession $cashSession): bool
    {
        return $this->isMember($user, $cashSession->tenant);
    }

    public function close(User $user, CashSession $cashSession): bool
    {
        return $this->isMember($user, $cashSession->tenant);
    }
}
