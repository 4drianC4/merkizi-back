<?php

namespace Database\Seeders;

use App\Shared\Identity\Models\Permission;
use App\Shared\Identity\Models\Role;
use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Models\Branch;
use App\Shared\Tenancy\Models\Plan;
use App\Shared\Tenancy\Models\Subscription;
use App\Shared\Tenancy\Models\Tenant;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([PlanSeeder::class, IdentitySeeder::class]);
        if (! app()->environment(['local', 'testing'])) {
            return;
        }
        DB::transaction(function (): void {
            $tenant = Tenant::query()->firstOrCreate(['slug' => 'merkizi-demo'], ['name' => 'Merkizi Demo', 'status' => 'active']);
            $adminRole = Role::query()->whereNull('tenant_id')->where('name', Role::CompanyAdmin)->firstOrFail();
            $employeeRole = Role::query()->whereNull('tenant_id')->where('name', Role::Employee)->firstOrFail();
            $admin = User::query()->firstOrCreate(['email' => 'test@example.com'], [
                'name' => 'Administrador Demo', 'password' => 'password', 'tenant_id' => $tenant->id, 'role_id' => $adminRole->id,
            ]);
            $admin->update(['tenant_id' => $tenant->id, 'role_id' => $adminRole->id, 'active' => true]);
            User::query()->firstOrCreate(['email' => 'superadmin@example.com'], [
                'name' => 'SuperAdmin Demo', 'password' => 'password', 'tenant_id' => null,
                'role_id' => Role::query()->whereNull('tenant_id')->where('name', Role::SuperAdmin)->firstOrFail()->id,
            ]);

            app(TenantContext::class)->run($tenant, function () use ($tenant, $admin, $employeeRole): void {
                Subscription::query()->firstOrCreate(['tenant_id' => $tenant->id, 'status' => 'active'], [
                    'plan_id' => Plan::query()->where('slug', 'pro')->firstOrFail()->id, 'starts_at' => now(),
                ]);
                $central = Branch::query()->firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Central'], ['status' => 'active']);
                $north = Branch::query()->firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Sucursal Norte'], ['status' => 'active']);
                $admin->branches()->syncWithoutDetaching([
                    $central->id => ['tenant_id' => $tenant->id, 'is_primary' => true],
                    $north->id => ['tenant_id' => $tenant->id, 'is_primary' => false],
                ]);
                $employee = User::query()->firstOrCreate(['email' => 'employee@example.com'], [
                    'name' => 'Empleado Demo', 'password' => 'password', 'tenant_id' => $tenant->id, 'role_id' => $employeeRole->id,
                ]);
                $employee->branches()->syncWithoutDetaching([$central->id => ['tenant_id' => $tenant->id, 'is_primary' => true]]);
                $cashierRole = Role::query()->firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Cajero']);
                $cashierRole->permissions()->sync($employeeRole->permissions()->pluck('permissions.id'));
                $cashier = User::query()->firstOrCreate(['email' => 'cashier@example.com'], [
                    'name' => 'Cajero Demo', 'password' => 'password', 'tenant_id' => $tenant->id, 'role_id' => $cashierRole->id,
                ]);
                $cashier->branches()->syncWithoutDetaching([$north->id => ['tenant_id' => $tenant->id, 'is_primary' => true]]);
                $cashier->permissions()->syncWithoutDetaching([
                    Permission::query()->where('code', 'caja.sesiones.cerrar')->firstOrFail()->id => ['granted' => false, 'granted_by_user_id' => $admin->id],
                ]);
            });
        });
        $this->call(SalesDemoSeeder::class);
    }
}
