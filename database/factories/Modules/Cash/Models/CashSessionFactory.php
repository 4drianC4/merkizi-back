<?php

namespace Database\Factories\Modules\Cash\Models;

use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Models\Branch;
use App\Shared\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashSession>
 */
class CashSessionFactory extends Factory
{
    protected $model = CashSession::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => Branch::factory(),
            'cash_register_id' => CashRegister::factory(),
            'opened_by' => User::factory(),
            'opened_at' => now(),
            'opening_balance' => '0.00',
            'status' => 'open',
        ];
    }
}
