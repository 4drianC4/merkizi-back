<?php

namespace Database\Factories\Modules\Cash\Models;

use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashSession;
use App\Shared\Tenancy\Models\Branch;
use App\Shared\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashMovement>
 */
class CashMovementFactory extends Factory
{
    protected $model = CashMovement::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => Branch::factory(),
            'cash_register_id' => 1,
            'cash_session_id' => CashSession::factory(),
            'type' => 'sale',
            'amount' => number_format(fake()->randomFloat(2, -100, 100), 2, '.', ''),
            'reason' => null,
            'reference' => null,
        ];
    }
}
