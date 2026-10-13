<?php

namespace Database\Seeders;

use App\Shared\Tenancy\Models\Plan;
use App\Shared\Tenancy\Models\PlanLimit;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plan = Plan::query()->firstOrCreate(
            ['slug' => 'pro'],
            ['name' => 'Pro', 'status' => 'active'],
        );

        $limits = [
            'max_branches' => 10,
            'max_cash_registers_per_branch' => 5,
        ];

        foreach ($limits as $key => $value) {
            PlanLimit::query()->updateOrCreate(
                ['plan_id' => $plan->id, 'key' => $key],
                ['value' => $value],
            );
        }
    }
}
