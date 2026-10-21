<?php

namespace App\Modules\Sales\Models;

use App\Shared\Tenancy\Concerns\HasTenantScope;
use App\Shared\Tenancy\Models\Branch;
use Database\Factories\Modules\Sales\Models\DiningTableFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DiningTable extends Model
{
    /** @use HasFactory<DiningTableFactory> */
    use HasFactory;

    use HasTenantScope;
    use HasUuids;

    protected $fillable = ['tenant_id', 'branch_id', 'area_id', 'number', 'capacity', 'active', 'blocked'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['active' => 'boolean', 'blocked' => 'boolean'];
    }

    /** @return BelongsTo<DiningArea, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(DiningArea::class, 'area_id');
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsToMany<TableAccount, $this> */
    public function activeAccounts(): BelongsToMany
    {
        return $this->belongsToMany(TableAccount::class, 'account_tables', 'table_id', 'account_id')->wherePivotNull('separated_at');
    }
}
