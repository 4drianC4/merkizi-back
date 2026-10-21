<?php

namespace App\Modules\Cash\Policies;

use App\Modules\Cash\Models\CashSession;
use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\TenantContext;

class CashSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->belongsToCompany(app(TenantContext::class)->requireTenant()) && $user->hasPermission('caja.sesiones.ver');
    }

    public function view(User $user, CashSession $cashSession): bool
    {
        return $user->belongsToCompany($cashSession->tenant) && $user->hasPermission('caja.sesiones.ver', $cashSession->branch);
    }

    public function close(User $user, CashSession $cashSession): bool
    {
        return $user->belongsToCompany($cashSession->tenant) && $user->hasPermission('caja.sesiones.cerrar', $cashSession->branch);
    }
}
