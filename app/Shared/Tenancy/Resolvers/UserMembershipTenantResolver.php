<?php

namespace App\Shared\Tenancy\Resolvers;

use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Resolves the tenant from the authenticated user's active memberships.
 *
 * A tenant id stored in the session (from an explicit company switch) is
 * honored only when it maps to one of the user's active memberships.
 */
class UserMembershipTenantResolver implements TenantResolver
{
    public function resolve(Request $request): ?Tenant
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        $query = $user->memberships()->where('status', 'active');

        $selectedId = $request->hasSession() ? $request->session()->get('tenant_id') : null;

        if (is_string($selectedId) && Str::isUuid($selectedId)) {
            $membership = (clone $query)->where('tenant_id', $selectedId)->first();

            if ($membership !== null) {
                return $membership->tenant;
            }
        }

        return $query->with('tenant')->orderBy('id')->first()?->tenant;
    }
}
