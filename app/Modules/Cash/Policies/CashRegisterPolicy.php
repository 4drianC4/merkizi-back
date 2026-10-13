<?php

namespace App\Modules\Cash\Policies;

use App\Modules\Cash\Models\CashRegister;
use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Concerns\AuthorizesTenantMembership;

class CashRegisterPolicy
{
    use AuthorizesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->isMember($user, $this->tenant());
    }

    public function view(User $user, CashRegister $cashRegister): bool
    {
        return $this->isMember($user, $cashRegister->tenant);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user, $this->tenant());
    }

    public function open(User $user, CashRegister $cashRegister): bool
    {
        return $this->isMember($user, $cashRegister->tenant);
    }

    public function update(User $user, CashRegister $cashRegister): bool
    {
        return $this->isAdmin($user, $cashRegister->tenant);
    }

    public function delete(User $user, CashRegister $cashRegister): bool
    {
        return $this->isAdmin($user, $cashRegister->tenant);
    }
}
