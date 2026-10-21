<?php

namespace Database\Factories\Modules\Sales\Models;

use App\Modules\Sales\Models\OrderItem;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SaleItem> */
class SaleItemFactory extends Factory
{
    protected $model = SaleItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['sale_id' => Sale::factory(), 'order_item_id' => OrderItem::factory(), 'name' => fake()->word(), 'quantity' => '1.000', 'price' => '10.00', 'subtotal' => '10.00'];
    }
}
