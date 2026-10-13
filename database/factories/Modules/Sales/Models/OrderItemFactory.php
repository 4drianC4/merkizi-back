<?php

namespace Database\Factories\Modules\Sales\Models;

use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => null,
            'name' => fake()->words(2, true),
            'price' => number_format(fake()->randomFloat(2, 1, 500), 2, '.', ''),
            'quantity' => number_format(fake()->randomFloat(3, 0.1, 10), 3, '.', ''),
            'subtotal' => '0.00',
        ];
    }
}
