<?php

namespace App\Shared\Tenancy\Concerns;

use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Models\Tenant;
use App\Shared\Tenancy\TenantContext;

trait AuthorizesTenantMembership
{
    protected function tenant(): Tenant
    {
        return app(TenantContext::class)->requireTenant();
    }

    protected function isMember(User $user, Tenant $tenant): bool
    {
        return $user->activeMembershipFor($tenant) !== null;
    }

    protected function isAdmin(User $user, Tenant $tenant): bool
    {
        return $user->isTenantAdmin($tenant);
    }
}
