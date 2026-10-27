<?php

namespace Database\Factories\Modules\Sales\Models;

use App\Modules\Sales\Models\DiningArea;
use App\Modules\Sales\Models\DiningTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DiningTable> */
class DiningTableFactory extends Factory
{
    protected $model = DiningTable::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['area_id' => DiningArea::factory(), 'tenant_id' => fn (array $attributes) => DiningArea::withoutGlobalScopes()->findOrFail($attributes['area_id'])->tenant_id, 'branch_id' => fn (array $attributes) => DiningArea::withoutGlobalScopes()->findOrFail($attributes['area_id'])->branch_id, 'number' => (string) fake()->unique()->numberBetween(1, 10000), 'capacity' => 4, 'active' => true, 'blocked' => false];
    }
}
