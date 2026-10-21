<?php

namespace Tests\Feature\Tenancy;

use App\Modules\Staff\Models\Employee;
use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_403_without_company_before_listing_or_binding_records(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());
        foreach (['employees', 'branches', 'categories', 'products', 'cash-registers', 'orders', 'employees/1'] as $path) {
            $this->getJson('/api/v1/'.$path)->assertForbidden()->assertJsonPath('code', 'tenant_required');
            $this->assertNull(app(TenantContext::class)->id());
        }
    }

    public function test_returns_403_for_inactive_account(): void
    {
        Sanctum::actingAs(User::factory()->inactive()->create());
        $this->getJson('/api/v1/employees')->assertForbidden();
    }

    public function test_returns_401_for_guest(): void
    {
        $this->getJson('/api/v1/employees')->assertUnauthorized();
    }

    public function test_lists_only_own_company_users_and_returns_404_for_other_company_record(): void
    {
        $user = User::factory()->admin()->create();
        $employee = Employee::factory()->create(['tenant_id' => $user->tenant_id]);
        $other = Employee::factory()->create();
        Sanctum::actingAs($user);
        $response = $this->getJson('/api/v1/employees')->assertOk()->assertJsonCount(2, 'data');
        $this->assertContains($employee->id, array_column($response->json('data'), 'id'));
        $this->getJson('/api/v1/employees/'.$employee->id)->assertOk();
        $this->getJson('/api/v1/employees/'.$other->id)->assertNotFound();
        $this->assertNull(app(TenantContext::class)->id());
    }
}
