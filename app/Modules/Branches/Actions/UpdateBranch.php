<?php

namespace App\Modules\Branches\Actions;

use App\Shared\Tenancy\Models\Branch;

class UpdateBranch
{
    /**
     * @param  array{name?: string, status?: string}  $data
     */
    public function handle(Branch $branch, array $data): Branch
    {
        $branch->update($data);

        return $branch;
    }
}
