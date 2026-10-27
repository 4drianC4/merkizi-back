<?php

namespace Database\Factories\Modules\Sales\Models;

use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleRefund;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SaleRefund> */
class SaleRefundFactory extends Factory
{
    protected $model = SaleRefund::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['sale_id' => Sale::factory(), 'tenant_id' => fn (array $attributes) => Sale::withoutGlobalScopes()->findOrFail($attributes['sale_id'])->tenant_id, 'branch_id' => fn (array $attributes) => Sale::withoutGlobalScopes()->findOrFail($attributes['sale_id'])->branch_id, 'cash_session_id' => fn (array $attributes) => Sale::withoutGlobalScopes()->findOrFail($attributes['sale_id'])->cash_session_id, 'user_id' => fn (array $attributes) => Sale::withoutGlobalScopes()->findOrFail($attributes['sale_id'])->cashier_id, 'reason' => fake()->sentence(), 'amount' => '1.00', 'method' => 'cash', 'idempotency_key' => fake()->uuid(), 'request_hash' => hash('sha256', 'factory')];
    }
}
