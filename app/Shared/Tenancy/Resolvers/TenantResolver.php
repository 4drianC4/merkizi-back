<?php

namespace App\Shared\Tenancy\Resolvers;

use App\Shared\Tenancy\Models\Tenant;
use Illuminate\Http\Request;

interface TenantResolver
{
    /**
     * Resolve the tenant for the given request from an authorized membership.
     */
    public function resolve(Request $request): ?Tenant;
}
