<?php

namespace App\Modules\Catalog\Policies;

use App\Modules\Catalog\Models\RawMaterialCategory;
use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Concerns\AuthorizesTenantMembership;

class RawMaterialCategoryPolicy
{
    use AuthorizesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->isMember($user, $this->tenant());
    }

    public function view(User $user, RawMaterialCategory $rawMaterialCategory): bool
    {
        return $this->isMember($user, $rawMaterialCategory->tenant);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user, $this->tenant());
    }

    public function update(User $user, RawMaterialCategory $rawMaterialCategory): bool
    {
        return $this->isAdmin($user, $rawMaterialCategory->tenant);
    }

    public function delete(User $user, RawMaterialCategory $rawMaterialCategory): bool
    {
        return $this->isAdmin($user, $rawMaterialCategory->tenant);
    }
}