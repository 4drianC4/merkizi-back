<?php

namespace Database\Factories\Shared\Tenancy\Models;

use App\Shared\Tenancy\Models\Plan;
use App\Shared\Tenancy\Models\Subscription;
use App\Shared\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'plan_id' => Plan::factory(),
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => null,
        ];
    }
}
