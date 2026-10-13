<?php

namespace Database\Factories\Modules\Catalog\Models;

use App\Modules\Catalog\Models\Product;
use App\Shared\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'category_id' => null,
            'name' => fake()->words(2, true),
            'sku' => strtoupper(fake()->unique()->lexify('???-####')),
            'price' => number_format(fake()->randomFloat(2, 1, 500), 2, '.', ''),
            'status' => 'active',
        ];
    }
}
