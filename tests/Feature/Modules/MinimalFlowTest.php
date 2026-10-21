<?php

namespace Tests\Feature\Modules;

use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Models\Branch;
use App\Shared\Tenancy\Models\Plan;
use App\Shared\Tenancy\Models\PlanLimit;
use App\Shared\Tenancy\Models\Subscription;
use App\Shared\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MinimalFlowTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();

        $plan = Plan::factory()->create(['slug' => 'pro', 'status' => 'active']);
        PlanLimit::factory()->create(['plan_id' => $plan->id, 'key' => 'max_branches', 'value' => 10]);
        PlanLimit::factory()->create(['plan_id' => $plan->id, 'key' => 'max_cash_registers_per_branch', 'value' => 5]);
        Subscription::factory()->create(['tenant_id' => $this->tenant->id, 'plan_id' => $plan->id, 'status' => 'active']);

        $this->admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);

        $this->branch = Branch::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);

        $this->admin->branches()->attach($this->branch->id, ['tenant_id' => $this->tenant->id, 'is_primary' => true]);

        Sanctum::actingAs($this->admin);
    }

    public function test_minimal_operation_flow_runs_end_to_end(): void
    {
        $this->postJson('/api/v1/categories', ['name' => 'Bebidas'])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Bebidas');

        $productId = $this->postJson('/api/v1/products', ['name' => 'Café', 'price' => '10.50'])
            ->assertStatus(201)
            ->json('data.id');

        $registerId = $this->postJson('/api/v1/cash-registers', ['name' => 'Caja 1', 'branch_id' => $this->branch->id])
            ->assertStatus(201)
            ->json('data.id');

        $sessionId = $this->postJson("/api/v1/cash-registers/{$registerId}/sessions", ['opening_balance' => '100.00'])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'open')
            ->json('data.id');

        $orderId = $this->postJson('/api/v1/orders', [
            'cash_session_id' => $sessionId,
            'items' => [
                ['product_id' => $productId, 'quantity' => 2],
            ],
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.total', '21.00')
            ->json('data.id');

        $this->postJson("/api/v1/orders/{$orderId}/payments", ['method' => 'cash', 'amount' => '21.00'])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'confirmed');

        $this->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.paid_at', fn ($value) => $value !== null);

        $this->postJson("/api/v1/cash-sessions/{$sessionId}/close")
            ->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.closing_balance', '121.00');
    }

    public function test_cash_register_limit_is_enforced(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/v1/cash-registers', ['name' => "Caja {$i}", 'branch_id' => $this->branch->id])
                ->assertStatus(201);
        }

        $this->postJson('/api/v1/cash-registers', ['name' => 'Caja 6', 'branch_id' => $this->branch->id])
            ->assertStatus(422)
            ->assertJsonPath('code', 'limit_exceeded');
    }

    public function test_pending_payment_does_not_settle_order(): void
    {
        $productId = $this->postJson('/api/v1/products', ['name' => 'Café', 'price' => '10.00'])
            ->json('data.id');

        $registerId = $this->postJson('/api/v1/cash-registers', ['name' => 'Caja 1', 'branch_id' => $this->branch->id])
            ->json('data.id');

        $sessionId = $this->postJson("/api/v1/cash-registers/{$registerId}/sessions")
            ->json('data.id');

        $orderId = $this->postJson('/api/v1/orders', [
            'cash_session_id' => $sessionId,
            'items' => [
                ['product_id' => $productId, 'quantity' => 1],
            ],
        ])
            ->json('data.id');

        $this->postJson("/api/v1/orders/{$orderId}/payments", ['method' => 'qr', 'amount' => '10.00'])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $this->getJson("/api/v1/orders/{$orderId}")
            ->assertJsonPath('data.status', 'draft');

        $this->postJson("/api/v1/orders/{$orderId}/payments", ['method' => 'cash', 'amount' => '10.00'])
            ->assertStatus(201);

        $this->getJson("/api/v1/orders/{$orderId}")
            ->assertJsonPath('data.status', 'paid');
    }
}
