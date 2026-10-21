<?php

namespace Database\Factories\Shared\Identity\Models;

use App\Shared\Identity\Models\Role;
use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'role_id' => fn () => Role::query()->firstOrCreate(['tenant_id' => null, 'name' => Role::Employee])->id,
            'active' => true,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role_id' => Role::query()->whereNull('tenant_id')->where('name', Role::CompanyAdmin)->first()?->id
                ?? Role::factory()->companyAdmin()->create()->id,
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => [
            'tenant_id' => null,
            'role_id' => Role::query()->firstOrCreate(['tenant_id' => null, 'name' => Role::SuperAdmin])->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
