<?php

namespace App\Shared\Tenancy\Resolvers;

use App\Shared\Tenancy\Models\Tenant;
use Illuminate\Http\Request;

interface TenantResolver
{
    /**
     * Resolve the tenant for the given request from the authenticated company account.
     */
    public function resolve(Request $request): ?Tenant;
}
