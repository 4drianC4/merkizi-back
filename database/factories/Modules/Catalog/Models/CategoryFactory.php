<?php

namespace Database\Factories\Modules\Catalog\Models;

use App\Modules\Catalog\Models\Category;
use App\Shared\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'parent_id' => null,
            'name' => fake()->word(),
            'status' => 'active',
        ];
    }
}
