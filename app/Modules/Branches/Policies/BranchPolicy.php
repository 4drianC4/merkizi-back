<?php

namespace App\Modules\Branches\Policies;

use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Models\Branch;
use App\Shared\Tenancy\Models\Tenant;
use App\Shared\Tenancy\TenantContext;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isMember($user, app(TenantContext::class)->requireTenant());
    }

    public function view(User $user, Branch $branch): bool
    {
        return $this->isMember($user, $branch->tenant);
    }

    public function create(User $user): bool
    {
        return $user->isTenantAdmin(app(TenantContext::class)->requireTenant());
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->isTenantAdmin($branch->tenant);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->isTenantAdmin($branch->tenant);
    }

    private function isMember(User $user, Tenant $tenant): bool
    {
        return $user->activeMembershipFor($tenant) !== null;
    }
}
