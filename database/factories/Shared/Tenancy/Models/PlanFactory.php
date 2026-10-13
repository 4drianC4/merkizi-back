<?php

namespace Database\Factories\Shared\Tenancy\Models;

use App\Shared\Tenancy\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'slug' => fn () => Str::slug(fake()->unique()->words(2, true)),
            'status' => 'active',
        ];
    }
}
