<?php

namespace App\Modules\Catalog\Policies;

use App\Modules\Catalog\Models\Product;
use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\TenantContext;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->belongsToCompany(app(TenantContext::class)->requireTenant()) && $user->hasPermission('catalogo.productos.ver');
    }

    public function view(User $user, Product $product): bool
    {
        return $user->belongsToCompany($product->tenant) && $user->hasPermission('catalogo.productos.ver');
    }

    public function create(User $user): bool
    {
        return $user->belongsToCompany(app(TenantContext::class)->requireTenant()) && $user->hasPermission('catalogo.productos.crear');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->belongsToCompany($product->tenant) && $user->hasPermission('catalogo.productos.editar');
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->belongsToCompany($product->tenant) && $user->hasPermission('catalogo.productos.eliminar');
    }
}
