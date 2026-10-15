<?php

namespace App\Shared\Tenancy\Models;

use App\Shared\Tenancy\Concerns\HasTenantScope;
use Database\Factories\Shared\Tenancy\Models\BranchFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    use HasTenantScope;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'name',
        'status',
    ];
}
