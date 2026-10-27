<?php

namespace Database\Factories\Modules\Catalog\Models;

use App\Modules\Catalog\Models\RawMaterialCategory;
use App\Shared\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RawMaterialCategory>
 */
class RawMaterialCategoryFactory extends Factory
{
    protected $model = RawMaterialCategory::class;

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