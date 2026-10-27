<?php

namespace Database\Factories\Shared\Identity\Models;

use App\Shared\Identity\Models\Permission;
use App\Shared\Identity\Models\Role;
use App\Shared\Identity\PermissionCatalog;
use App\Shared\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Role> */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['tenant_id' => Tenant::factory(), 'name' => fake()->unique()->word(), 'description' => null];
    }

    public function companyAdmin(): static
    {
        return $this->state(['tenant_id' => null, 'name' => Role::CompanyAdmin])->afterCreating(function (Role $role): void {
            foreach (PermissionCatalog::definitions() as $code => $definition) {
                $permission = Permission::query()->firstOrCreate(['code' => $code], $definition);
                $role->permissions()->syncWithoutDetaching([$permission->id]);
            }
        });
    }
}
