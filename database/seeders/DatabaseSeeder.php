<?php

namespace Database\Seeders;

use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Models\Plan;
use App\Shared\Tenancy\Models\Subscription;
use App\Shared\Tenancy\Models\Tenant;
use App\Shared\Tenancy\Models\TenantMembership;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PlanSeeder::class);

        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        DB::transaction(function (): void {
            $user = User::query()->firstOrCreate(
                ['email' => 'test@example.com'],
                ['name' => 'Test User', 'password' => Hash::make('password')],
            );
            $tenant = Tenant::query()->firstOrCreate(
                ['slug' => 'merkizi-demo'],
                ['name' => 'Merkizi Demo', 'status' => 'active'],
            );
            TenantMembership::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                ['role' => 'admin', 'status' => 'active'],
            );

            app(TenantContext::class)->run($tenant, function () use ($tenant): void {
                Subscription::query()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'status' => 'active'],
                    ['plan_id' => Plan::query()->where('slug', 'pro')->firstOrFail()->id, 'starts_at' => now()],
                );
            });
        });

        $this->call(CashDemoSeeder::class);
    }
}
