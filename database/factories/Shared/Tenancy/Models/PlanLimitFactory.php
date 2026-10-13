<?php

namespace Database\Factories\Shared\Tenancy\Models;

use App\Shared\Tenancy\Models\Plan;
use App\Shared\Tenancy\Models\PlanLimit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanLimit>
 */
class PlanLimitFactory extends Factory
{
    protected $model = PlanLimit::class;

    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'key' => fake()->word(),
            'value' => fake()->numberBetween(1, 100),
        ];
    }
}
