<?php

namespace App\Modules\Sales\Models;

use App\Modules\Cash\Models\CashSession;
use App\Modules\Cash\Models\Payment;
use App\Shared\Support\Money;
use App\Shared\Tenancy\Concerns\HasTenantScope;
use App\Shared\Tenancy\Models\Branch;
use Database\Factories\Modules\Sales\Models\OrderFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    use HasTenantScope;
    use HasUuids;

    protected $fillable = [
        'account_id', 'origin_table_id', 'responsible_user_id', 'number', 'version', 'service_type', 'notes',
        'tenant_id',
        'branch_id',
        'cash_session_id',
        'customer_name',
        'status',
        'subtotal',
        'discount',
        'tax',
        'total',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<CashSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Recompute subtotal/total from the loaded items (discount and tax are preserved).
     */
    public function recalculateTotals(): void
    {
        $subtotal = '0.00';

        foreach ($this->items as $item) {
            $subtotal = Money::add($subtotal, (string) $item->subtotal);
        }

        $this->subtotal = $subtotal;
        $this->total = Money::sub(Money::add($subtotal, (string) $this->tax), (string) $this->discount);
    }

    /**
     * Sum of confirmed payments only. Pending payments (e.g. QR) do not settle an order.
     */
    public function confirmedPaymentsTotal(): string
    {
        $total = '0.00';

        foreach ($this->payments()->where('status', 'confirmed')->get() as $payment) {
            $total = Money::add($total, (string) $payment->amount);
        }

        return $total;
    }

    /**
     * Mark the order as paid when confirmed payments cover the total.
     */
    public function markPaidIfCovered(): bool
    {
        if ($this->status === 'paid') {
            return false;
        }

        if (Money::cmp($this->confirmedPaymentsTotal(), (string) $this->total) >= 0) {
            $this->status = 'paid';
            $this->paid_at = now();

            return true;
        }

        return false;
    }

    /** @return BelongsTo<TableAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(TableAccount::class, 'account_id');
    }
}
