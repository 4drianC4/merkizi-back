<?php

namespace Tests\Feature\Tenancy;

use App\Shared\Tenancy\Models\Branch;
use App\Shared\Tenancy\Models\Subscription;
use App\Shared\Tenancy\Models\Tenant;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(TenantContext::class)->setTenant(null);

        parent::tearDown();
    }

    public function test_queries_are_scoped_to_the_active_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $branchA = Branch::factory()->create(['tenant_id' => $tenantA->id]);
        Branch::factory()->create(['tenant_id' => $tenantB->id]);

        app(TenantContext::class)->setTenant($tenantA);

        $this->assertEquals([$branchA->id], Branch::query()->pluck('id')->all());
    }

    public function test_subscriptions_are_scoped_to_the_active_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $subscriptionA = Subscription::factory()->create(['tenant_id' => $tenantA->id]);
        Subscription::factory()->create(['tenant_id' => $tenantB->id]);

        app(TenantContext::class)->setTenant($tenantA);

        $this->assertEquals([$subscriptionA->id], Subscription::query()->pluck('id')->all());
    }

    public function test_without_tenant_context_all_records_are_visible(): void
    {
        Branch::factory()->create();
        Branch::factory()->create();

        $this->assertCount(2, Branch::query()->get());
    }
}
