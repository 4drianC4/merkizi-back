<?php

namespace Database\Factories\Modules\Catalog\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductVariant> */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['product_id' => Product::factory(), 'tenant_id' => fn (array $attributes) => Product::withoutGlobalScopes()->findOrFail($attributes['product_id'])->tenant_id,
            'name' => fake()->word(), 'price' => '15.00', 'active' => true];
    }
}
