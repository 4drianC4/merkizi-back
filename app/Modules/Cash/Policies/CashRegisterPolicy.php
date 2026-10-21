<?php

namespace App\Modules\Cash\Policies;

use App\Modules\Cash\Models\CashRegister;
use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\TenantContext;

class CashRegisterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->belongsToCompany(app(TenantContext::class)->requireTenant()) && $user->hasPermission('caja.cajas.ver');
    }

    public function view(User $user, CashRegister $cashRegister): bool
    {
        return $user->belongsToCompany($cashRegister->tenant) && $user->hasPermission('caja.cajas.ver', $cashRegister->branch);
    }

    public function create(User $user): bool
    {
        return $user->belongsToCompany(app(TenantContext::class)->requireTenant()) && $user->hasPermission('caja.cajas.crear');
    }

    public function open(User $user, CashRegister $cashRegister): bool
    {
        return $user->belongsToCompany($cashRegister->tenant) && $user->hasPermission('caja.sesiones.abrir', $cashRegister->branch);
    }

    public function update(User $user, CashRegister $cashRegister): bool
    {
        return $user->belongsToCompany($cashRegister->tenant) && $user->hasPermission('caja.cajas.editar', $cashRegister->branch);
    }

    public function delete(User $user, CashRegister $cashRegister): bool
    {
        return $user->belongsToCompany($cashRegister->tenant) && $user->hasPermission('caja.cajas.eliminar', $cashRegister->branch);
    }
}
