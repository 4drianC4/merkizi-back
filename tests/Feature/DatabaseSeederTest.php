<?php

namespace Tests\Feature;

use App\Shared\Identity\Models\Role;
use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_seeding_repairs_demo_access_without_resetting_password(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'test@example.com')->firstOrFail();
        $user->update(['password' => 'changed-password', 'active' => false]);
        $this->seed();
        $this->assertDatabaseCount('users', 4);
        $this->assertDatabaseCount('tenants', 1);
        $this->assertDatabaseCount('branches', 2);
        $this->assertDatabaseCount('roles', 4);
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertTrue(Hash::check('changed-password', $user->fresh()->password));
        $this->assertTrue($user->fresh()->active);
        $this->assertNull(app(TenantContext::class)->id());
        Sanctum::actingAs($user->fresh());
        $this->getJson('/api/v1/employees')->assertOk()->assertJsonCount(3, 'data');
        $this->postJson('/api/v1/branches', ['name' => 'Sucursal de prueba'])->assertCreated();
    }

    public function test_fresh_seed_creates_demo_credentials_and_permission_examples(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'test@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertSame(Role::CompanyAdmin, $user->role->name);
        $this->assertTrue($user->hasPermission('usuarios.crear'));
        $this->assertTrue(User::query()->where('email', 'superadmin@example.com')->firstOrFail()->isSuperAdmin());
        $cashier = User::query()->where('email', 'cashier@example.com')->firstOrFail();
        $this->assertTrue($cashier->hasPermission('pos.venta.cobrar'));
        $this->assertFalse($cashier->hasPermission('caja.sesiones.cerrar'));
    }

    public function test_production_seeding_does_not_create_demo_accounts(): void
    {
        $this->app->instance('env', 'production');
        $this->artisan('db:seed', ['--force' => true, '--no-interaction' => true])->assertSuccessful();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseHas('plans', ['slug' => 'pro']);
        $this->assertDatabaseHas('roles', ['name' => Role::SuperAdmin, 'tenant_id' => null]);
    }
}
