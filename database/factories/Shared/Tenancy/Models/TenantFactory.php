<?php

namespace Database\Factories\Shared\Tenancy\Models;

use App\Shared\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fn () => Str::slug(fake()->unique()->company()).'-'.strtolower(Str::random(5)),
            'status' => 'active',
        ];
    }
}
