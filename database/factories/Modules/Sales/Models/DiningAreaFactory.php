<?php

namespace Database\Factories\Modules\Sales\Models;

use App\Modules\Sales\Models\DiningArea;
use App\Shared\Tenancy\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DiningArea> */
class DiningAreaFactory extends Factory
{
    protected $model = DiningArea::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['branch_id' => Branch::factory(), 'tenant_id' => fn (array $attributes) => Branch::withoutGlobalScopes()->findOrFail($attributes['branch_id'])->tenant_id, 'name' => fake()->unique()->word(), 'active' => true];
    }
}
