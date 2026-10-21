<?php

namespace App\Modules\Branches\Policies;

use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Models\Branch;
use App\Shared\Tenancy\TenantContext;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->belongsToCompany(app(TenantContext::class)->requireTenant()) && $user->hasPermission('sucursales.ver');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->belongsToCompany($branch->tenant) && $user->hasPermission('sucursales.ver');
    }

    public function create(User $user): bool
    {
        return $user->belongsToCompany(app(TenantContext::class)->requireTenant()) && $user->hasPermission('sucursales.crear');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->belongsToCompany($branch->tenant) && $user->hasPermission('sucursales.editar');
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->belongsToCompany($branch->tenant) && $user->hasPermission('sucursales.eliminar');
    }
}
