<?php

namespace App\Modules\Sales\Models;

use App\Shared\Tenancy\Concerns\HasTenantScope;
use App\Shared\Tenancy\Models\Branch;
use Database\Factories\Modules\Sales\Models\SaleRefundFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleRefund extends Model
{
    /** @use HasFactory<SaleRefundFactory> */
    use HasFactory;

    use HasTenantScope;
    use HasUuids;

    protected $fillable = ['tenant_id', 'branch_id', 'sale_id', 'cash_session_id', 'user_id', 'reason', 'amount', 'method', 'idempotency_key', 'request_hash'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
