<?php

namespace App\Modules\Sales\Policies;

use App\Modules\Sales\Models\Order;
use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\TenantContext;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->belongsToCompany(app(TenantContext::class)->requireTenant()) && $user->hasPermission('pos.venta.ver');
    }

    public function view(User $user, Order $order): bool
    {
        return $user->belongsToCompany($order->tenant) && $user->hasPermission('pos.venta.ver', $order->branch);
    }

    public function create(User $user): bool
    {
        return $user->belongsToCompany(app(TenantContext::class)->requireTenant()) && $user->hasPermission('pos.venta.crear');
    }

    public function recordPayment(User $user, Order $order): bool
    {
        return $user->belongsToCompany($order->tenant) && $user->hasPermission('pos.venta.cobrar', $order->branch);
    }

    public function update(User $user, Order $order): bool
    {
        return $user->belongsToCompany($order->tenant) && $user->hasPermission('pos.venta.editar', $order->branch);
    }
}
