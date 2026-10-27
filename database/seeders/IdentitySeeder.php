<?php

namespace Database\Seeders;

use App\Shared\Identity\Models\Permission;
use App\Shared\Identity\Models\Role;
use App\Shared\Identity\PermissionCatalog;
use Illuminate\Database\Seeder;

class IdentitySeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionCatalog::definitions() as $code => $definition) {
            Permission::query()->updateOrCreate(['code' => $code], $definition);
        }
        Role::query()->firstOrCreate(['tenant_id' => null, 'name' => Role::SuperAdmin]);
        $admin = Role::query()->firstOrCreate(['tenant_id' => null, 'name' => Role::CompanyAdmin]);
        $employee = Role::query()->firstOrCreate(['tenant_id' => null, 'name' => Role::Employee]);
        $admin->permissions()->sync(Permission::query()->pluck('id'));
        $employee->permissions()->sync(Permission::query()->whereIn('code', PermissionCatalog::employeePermissions())->pluck('id'));
    }
}
