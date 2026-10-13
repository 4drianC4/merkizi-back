<?php

namespace Database\Factories\Modules\Sales\Models;

use App\Modules\Sales\Models\Order;
use App\Shared\Tenancy\Models\Branch;
use App\Shared\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => Branch::factory(),
            'cash_session_id' => null,
            'status' => 'draft',
            'subtotal' => '0.00',
            'discount' => '0.00',
            'tax' => '0.00',
            'total' => '0.00',
        ];
    }
}
