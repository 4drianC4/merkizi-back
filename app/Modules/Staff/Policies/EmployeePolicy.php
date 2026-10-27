<?php

namespace App\Modules\Staff\Policies;

use App\Modules\Staff\Models\Employee;
use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\TenantContext;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->belongsToCompany(app(TenantContext::class)->requireTenant()) && $user->hasPermission('usuarios.ver');
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->belongsToCompany($employee->tenant) && ! $employee->isSuperAdmin() && $user->hasPermission('usuarios.ver');
    }

    public function create(User $user): bool
    {
        return $user->belongsToCompany(app(TenantContext::class)->requireTenant()) && $user->hasPermission('usuarios.crear');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->belongsToCompany($employee->tenant) && ! $employee->isSuperAdmin() && $user->hasPermission('usuarios.editar') && $user->canManageUser($employee);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->belongsToCompany($employee->tenant) && ! $employee->isSuperAdmin() && $user->hasPermission('usuarios.eliminar') && $user->canManageUser($employee);
    }

    public function managePermissions(User $user, Employee $employee): bool
    {
        return $user->belongsToCompany($employee->tenant) && ! $employee->isSuperAdmin() && $user->hasPermission('usuarios.permisos') && $user->canManageUser($employee);
    }
}
