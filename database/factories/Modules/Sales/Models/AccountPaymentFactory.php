<?php

namespace Database\Factories\Modules\Sales\Models;

use App\Modules\Cash\Models\CashSession;
use App\Modules\Sales\Models\AccountPayment;
use App\Modules\Sales\Models\TableAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AccountPayment> */
class AccountPaymentFactory extends Factory
{
    protected $model = AccountPayment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['account_id' => TableAccount::factory(), 'origin_account_id' => fn (array $attributes) => $attributes['account_id'], 'tenant_id' => fn (array $attributes) => TableAccount::withoutGlobalScopes()->findOrFail($attributes['account_id'])->tenant_id, 'branch_id' => fn (array $attributes) => TableAccount::withoutGlobalScopes()->findOrFail($attributes['account_id'])->branch_id, 'user_id' => fn (array $attributes) => TableAccount::withoutGlobalScopes()->findOrFail($attributes['account_id'])->opened_by, 'cash_session_id' => fn (array $attributes) => CashSession::factory()->create(['tenant_id' => $attributes['tenant_id'], 'branch_id' => $attributes['branch_id']])->id, 'method' => 'cash', 'amount' => '10.00', 'status' => 'confirmed', 'idempotency_key' => fake()->uuid(), 'batch_index' => 0, 'request_hash' => hash('sha256', 'factory')];
    }
}
