<?php

namespace Database\Factories\Modules\Cash\Models;

use App\Modules\Cash\Models\Payment;
use App\Modules\Sales\Models\Order;
use App\Shared\Tenancy\Models\Branch;
use App\Shared\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => Branch::factory(),
            'order_id' => Order::factory(),
            'method' => 'cash',
            'amount' => number_format(fake()->randomFloat(2, 1, 500), 2, '.', ''),
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ];
    }
}
