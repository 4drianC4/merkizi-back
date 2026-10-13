<?php

namespace App\Modules\Branches\Actions;

use App\Shared\Exceptions\LimitExceededException;
use App\Shared\Tenancy\Models\Branch;
use App\Shared\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;

class CreateBranch
{
    public function handle(Tenant $tenant, string $name): Branch
    {
        return DB::transaction(function () use ($tenant, $name) {
            $subscription = $tenant->subscriptions()
                ->where('status', 'active')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $limit = $subscription?->plan?->limit('max_branches');

            if ($limit !== null) {
                $active = $tenant->branches()->where('status', 'active')->count();

                if ($active >= $limit) {
                    throw new LimitExceededException('max_branches', $limit);
                }
            }

            return $tenant->branches()->create([
                'name' => $name,
                'status' => 'active',
            ]);
        });
    }
}
