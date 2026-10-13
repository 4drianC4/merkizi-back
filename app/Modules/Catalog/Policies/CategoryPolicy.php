<?php

namespace App\Modules\Catalog\Policies;

use App\Modules\Catalog\Models\Category;
use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Concerns\AuthorizesTenantMembership;

class CategoryPolicy
{
    use AuthorizesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->isMember($user, $this->tenant());
    }

    public function view(User $user, Category $category): bool
    {
        return $this->isMember($user, $category->tenant);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user, $this->tenant());
    }

    public function update(User $user, Category $category): bool
    {
        return $this->isAdmin($user, $category->tenant);
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->isAdmin($user, $category->tenant);
    }
}
