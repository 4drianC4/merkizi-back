<?php

namespace App\Modules\Catalog\Policies;

use App\Modules\Catalog\Models\Product;
use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Concerns\AuthorizesTenantMembership;

class ProductPolicy
{
    use AuthorizesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->isMember($user, $this->tenant());
    }

    public function view(User $user, Product $product): bool
    {
        return $this->isMember($user, $product->tenant);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user, $this->tenant());
    }

    public function update(User $user, Product $product): bool
    {
        return $this->isAdmin($user, $product->tenant);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->isAdmin($user, $product->tenant);
    }
}
