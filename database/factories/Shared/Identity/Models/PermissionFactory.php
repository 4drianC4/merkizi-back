<?php

namespace Database\Factories\Shared\Identity\Models;

use App\Shared\Identity\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Permission> */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['code' => fake()->unique()->slug(3), 'module' => 'test', 'action' => 'ver', 'scope' => 'EMPRESA'];
    }
}
