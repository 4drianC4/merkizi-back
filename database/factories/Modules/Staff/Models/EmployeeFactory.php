<?php

namespace Database\Factories\Modules\Staff\Models;

use App\Modules\Staff\Models\Employee;
use App\Shared\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => null,
            'user_id' => null,
            'name' => fake()->name(),
            'role' => 'employee',
            'status' => 'active',
        ];
    }
}
