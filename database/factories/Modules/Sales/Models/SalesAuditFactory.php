<?php

namespace Database\Factories\Modules\Sales\Models;

use App\Modules\Sales\Models\SalesAudit;
use App\Modules\Sales\Models\TableAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SalesAudit> */
class SalesAuditFactory extends Factory
{
    protected $model = SalesAudit::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['account_id' => TableAccount::factory(), 'tenant_id' => fn (array $attributes) => TableAccount::withoutGlobalScopes()->findOrFail($attributes['account_id'])->tenant_id, 'branch_id' => fn (array $attributes) => TableAccount::withoutGlobalScopes()->findOrFail($attributes['account_id'])->branch_id, 'user_id' => fn (array $attributes) => TableAccount::withoutGlobalScopes()->findOrFail($attributes['account_id'])->opened_by, 'action' => 'account.opened', 'data' => []];
    }
}
