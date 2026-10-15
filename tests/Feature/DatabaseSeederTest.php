<?php

namespace Tests\Feature;

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
        $user = User::factory()->create(['email' => 'test@example.com', 'password' => Hash::make('changed-password')]);
        $this->seed();
        $this->seed();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('tenants', 1);
        $this->assertDatabaseCount('tenant_memberships', 1);
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertTrue(Hash::check('changed-password', $user->fresh()->password));
        $this->assertNull(app(TenantContext::class)->id());
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/employees')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/branches', ['name' => 'Sucursal de prueba'])->assertCreated();
    }

    public function test_fresh_seed_creates_demo_credentials(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'test@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertDatabaseHas('tenant_memberships', ['user_id' => $user->id, 'role' => 'admin', 'status' => 'active']);
    }

    public function test_production_seeding_does_not_create_demo_account(): void
    {
        $this->app->instance('env', 'production');
        $this->artisan('db:seed', ['--force' => true, '--no-interaction' => true])->assertSuccessful();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseHas('plans', ['slug' => 'pro']);
    }
}
