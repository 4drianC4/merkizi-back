<?php

namespace App\Modules\Cash\Actions;

use App\Modules\Cash\Models\CashRegister;
use App\Shared\Exceptions\LimitExceededException;
use App\Shared\Tenancy\Models\Branch;
use App\Shared\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;

class CreateCashRegister
{
    public function handle(Tenant $tenant, Branch $branch, string $name): CashRegister
    {
        return DB::transaction(function () use ($tenant, $branch, $name) {
            $subscription = $tenant->subscriptions()
                ->where('status', 'active')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $limit = $subscription?->plan?->limit('max_cash_registers_per_branch');

            if ($limit !== null) {
                $count = CashRegister::query()
                    ->where('branch_id', $branch->id)
                    ->where('status', 'active')
                    ->count();

                if ($count >= $limit) {
                    throw new LimitExceededException('max_cash_registers_per_branch', $limit);
                }
            }

            return CashRegister::query()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'name' => $name,
                'status' => 'active',
            ]);
        });
    }
}
