<?php

namespace App\Modules\Sales\Models;

use App\Shared\Tenancy\Concerns\HasTenantScope;
use App\Shared\Tenancy\Models\Branch;
use Database\Factories\Modules\Sales\Models\DiningAreaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiningArea extends Model
{
    /** @use HasFactory<DiningAreaFactory> */
    use HasFactory;

    use HasTenantScope;
    use HasUuids;

    protected $fillable = ['tenant_id', 'branch_id', 'name', 'sort_order', 'active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
