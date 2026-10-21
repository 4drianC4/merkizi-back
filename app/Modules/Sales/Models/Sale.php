<?php

namespace App\Modules\Sales\Models;

use App\Shared\Tenancy\Concerns\HasTenantScope;
use App\Shared\Tenancy\Models\Branch;
use Database\Factories\Modules\Sales\Models\SaleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    use HasTenantScope;
    use HasUuids;

    protected $fillable = ['tenant_id', 'branch_id', 'account_id', 'cash_session_id', 'cashier_id', 'number', 'service_type', 'customer_name', 'customer_tax_id', 'status', 'subtotal', 'discount', 'tax', 'tip', 'total', 'close_key'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'tax' => 'decimal:2', 'tip' => 'decimal:2', 'total' => 'decimal:2'];
    }

    /** @return HasMany<SaleItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'sale_id');
    }

    /** @return HasMany<AccountPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(AccountPayment::class, 'sale_id');
    }

    /** @return HasMany<SaleRefund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(SaleRefund::class, 'sale_id');
    }

    /** @return BelongsTo<TableAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(TableAccount::class, 'account_id');
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
