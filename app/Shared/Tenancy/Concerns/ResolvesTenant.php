<?php

namespace App\Shared\Tenancy\Concerns;

use App\Shared\Tenancy\Models\Tenant;
use App\Shared\Tenancy\TenantContext;

trait ResolvesTenant
{
    /**
     * The tenant active for the current request.
     */
    protected function tenant(): Tenant
    {
        return app(TenantContext::class)->requireTenant();
    }
}
