<?php

namespace App\Modules\Staff\Policies;

use App\Modules\Staff\Models\Employee;
use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Concerns\AuthorizesTenantMembership;

class EmployeePolicy
{
    use AuthorizesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->isMember($user, $this->tenant());
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->isMember($user, $employee->tenant);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user, $this->tenant());
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->isAdmin($user, $employee->tenant);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $this->isAdmin($user, $employee->tenant);
    }
}
