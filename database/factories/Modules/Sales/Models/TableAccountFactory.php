<?php

namespace Database\Factories\Modules\Sales\Models;

use App\Modules\Sales\Models\TableAccount;
use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TableAccount> */
class TableAccountFactory extends Factory
{
    protected $model = TableAccount::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['branch_id' => Branch::factory(), 'tenant_id' => fn (array $attributes) => Branch::withoutGlobalScopes()->findOrFail($attributes['branch_id'])->tenant_id, 'opened_by' => fn (array $attributes) => User::factory()->create(['tenant_id' => $attributes['tenant_id']])->id, 'status' => 'open', 'service_type' => 'takeaway', 'tip' => '0.00', 'version' => 1];
    }
}
