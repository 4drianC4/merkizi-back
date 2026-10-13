<?php

namespace Database\Factories\Shared\Tenancy\Models;

use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Models\Tenant;
use App\Shared\Tenancy\Models\TenantMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantMembership>
 */
class TenantMembershipFactory extends Factory
{
    protected $model = TenantMembership::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'role' => 'admin',
            'status' => 'active',
        ];
    }
}
