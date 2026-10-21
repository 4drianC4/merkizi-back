<?php

namespace Database\Factories\Modules\Sales\Models;

use App\Modules\Cash\Models\CashSession;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\TableAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Sale> */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['account_id' => TableAccount::factory(), 'tenant_id' => fn (array $attributes) => TableAccount::withoutGlobalScopes()->findOrFail($attributes['account_id'])->tenant_id, 'branch_id' => fn (array $attributes) => TableAccount::withoutGlobalScopes()->findOrFail($attributes['account_id'])->branch_id, 'cashier_id' => fn (array $attributes) => TableAccount::withoutGlobalScopes()->findOrFail($attributes['account_id'])->opened_by, 'cash_session_id' => fn (array $attributes) => CashSession::factory()->create(['tenant_id' => $attributes['tenant_id'], 'branch_id' => $attributes['branch_id']])->id, 'number' => fake()->unique()->numberBetween(1, 1000000), 'service_type' => 'takeaway', 'status' => 'completed', 'subtotal' => '10.00', 'discount' => '0.00', 'tax' => '0.00', 'tip' => '0.00', 'total' => '10.00', 'close_key' => fake()->uuid()];
    }
}
