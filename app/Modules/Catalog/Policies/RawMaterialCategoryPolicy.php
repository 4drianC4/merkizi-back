<?php

namespace App\Modules\Catalog\Policies;

use App\Modules\Catalog\Models\RawMaterialCategory;
use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\TenantContext;

class RawMaterialCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->belongsToCompany(app(TenantContext::class)->requireTenant()) && $user->hasPermission('catalogo.categorias.ver');
    }

    public function view(User $user, RawMaterialCategory $rawMaterialCategory): bool
    {
        return $user->belongsToCompany($rawMaterialCategory->tenant) && $user->hasPermission('catalogo.categorias.ver');
    }

    public function create(User $user): bool
    {
        return $user->belongsToCompany(app(TenantContext::class)->requireTenant()) && $user->hasPermission('catalogo.categorias.crear');
    }

    public function update(User $user, RawMaterialCategory $rawMaterialCategory): bool
    {
        return $user->belongsToCompany($rawMaterialCategory->tenant) && $user->hasPermission('catalogo.categorias.editar');
    }

    public function delete(User $user, RawMaterialCategory $rawMaterialCategory): bool
    {
        return $user->belongsToCompany($rawMaterialCategory->tenant) && $user->hasPermission('catalogo.categorias.eliminar');
    }
}
