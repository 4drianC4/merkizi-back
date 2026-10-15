<?php

namespace Tests\Feature\Tenancy;

use App\Modules\Staff\Models\Employee;
use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Models\Tenant;
use App\Shared\Tenancy\Models\TenantMembership;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_403_without_membership_before_listing_or_binding_records(): void
    {
        Sanctum::actingAs(User::factory()->create());

        foreach (['employees', 'branches', 'categories', 'products', 'cash-registers', 'orders', 'employees/1'] as $path) {
            $this->getJson('/api/v1/'.$path)
                ->assertForbidden()
                ->assertJsonPath('code', 'tenant_required');
            $this->assertNull(app(TenantContext::class)->id());
        }
    }

    public function test_returns_403_for_inactive_membership(): void
    {
        $user = User::factory()->create();
        TenantMembership::factory()->create(['user_id' => $user->id, 'status' => 'suspended']);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/employees')->assertForbidden()->assertJsonPath('code', 'tenant_required');
    }

    public function test_returns_401_for_guest(): void
    {
        $this->getJson('/api/v1/employees')->assertUnauthorized();
    }

    public function test_lists_only_own_employees_and_returns_404_for_other_tenant_record(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        TenantMembership::factory()->create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'role' => 'admin']);
        $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
        $other = Employee::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/employees')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $employee->id);
        $this->getJson('/api/v1/employees/'.$employee->id)->assertOk();
        $this->getJson('/api/v1/employees/'.$other->id)->assertNotFound();
        $this->assertNull(app(TenantContext::class)->id());
    }
}
