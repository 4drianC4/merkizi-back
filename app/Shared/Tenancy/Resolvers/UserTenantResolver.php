<?php

namespace App\Shared\Tenancy\Resolvers;

use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Models\Tenant;
use Illuminate\Http\Request;

/** Resolve company identity from the authenticated user, never from request input. */
class UserTenantResolver implements TenantResolver
{
    public function resolve(Request $request): ?Tenant
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->active || $user->trashed()) {
            return null;
        }
        $tenant = $user->tenant;

        return $tenant !== null && $user->belongsToCompany($tenant) ? $tenant : null;
    }
}
