<?php

namespace Database\Factories\Modules\Cash\Models;

use App\Modules\Cash\Models\CashRegister;
use App\Shared\Tenancy\Models\Branch;
use App\Shared\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashRegister>
 */
class CashRegisterFactory extends Factory
{
    protected $model = CashRegister::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => Branch::factory(),
            'name' => 'Caja '.fake()->randomNumber(2),
            'status' => 'active',
        ];
    }
}
