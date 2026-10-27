<?php

namespace App\Modules\Catalog\Policies;

use App\Modules\Catalog\Models\Category;
use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\TenantContext;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->belongsToCompany(app(TenantContext::class)->requireTenant()) && $user->hasPermission('catalogo.categorias.ver');
    }

    public function view(User $user, Category $category): bool
    {
        return $user->belongsToCompany($category->tenant) && $user->hasPermission('catalogo.categorias.ver');
    }

    public function create(User $user): bool
    {
        return $user->belongsToCompany(app(TenantContext::class)->requireTenant()) && $user->hasPermission('catalogo.categorias.crear');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->belongsToCompany($category->tenant) && $user->hasPermission('catalogo.categorias.editar');
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->belongsToCompany($category->tenant) && $user->hasPermission('catalogo.categorias.eliminar');
    }
}
