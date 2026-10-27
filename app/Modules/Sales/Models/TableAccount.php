<?php

namespace App\Modules\Sales\Models;

use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Support\Money;
use App\Shared\Tenancy\Concerns\HasTenantScope;
use App\Shared\Tenancy\Models\Branch;
use Database\Factories\Modules\Sales\Models\TableAccountFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TableAccount extends Model
{
    /** @use HasFactory<TableAccountFactory> */
    use HasFactory;

    use HasTenantScope;
    use HasUuids;

    protected $fillable = ['tenant_id', 'branch_id', 'cash_session_id', 'opened_by', 'service_type', 'customer_name', 'customer_tax_id', 'guests', 'notes', 'status', 'tip', 'version', 'closed_at', 'destination_account_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['tip' => 'decimal:2', 'closed_at' => 'datetime'];
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'account_id');
    }

    /** @return HasMany<AccountPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(AccountPayment::class, 'account_id');
    }

    /** @return HasOne<Sale, $this> */
    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class, 'account_id');
    }

    /** @return HasMany<SalesAudit, $this> */
    public function audits(): HasMany
    {
        return $this->hasMany(SalesAudit::class, 'account_id');
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsToMany<DiningTable, $this> */
    public function tables(): BelongsToMany
    {
        return $this->belongsToMany(DiningTable::class, 'account_tables', 'account_id', 'table_id')
            ->withPivot(['joined_at', 'separated_at'])->wherePivotNull('separated_at');
    }

    public function assertOpen(): void
    {
        if ($this->status !== 'open') {
            throw new InvalidStateTransitionException('The account is not open.');
        }
    }

    /** @return array{subtotal: string, discount: string, tax: string, tip: string, total: string, paid: string, pending: string, balance: string} */
    public function totals(): array
    {
        $this->loadMissing(['orders', 'payments']);
        $subtotal = $discount = $tax = $paid = $pending = '0.00';
        foreach ($this->orders as $order) {
            $subtotal = Money::add($subtotal, (string) $order->subtotal);
            $discount = Money::add($discount, (string) $order->discount);
            $tax = Money::add($tax, (string) $order->tax);
        }
        foreach ($this->payments as $payment) {
            if ($payment->status === 'confirmed') {
                $paid = Money::add($paid, (string) $payment->amount);
            } elseif ($payment->status === 'pending') {
                $pending = Money::add($pending, (string) $payment->amount);
            }
        }
        $tip = (string) $this->tip;
        $total = Money::sub(Money::add($subtotal, $tax, $tip), $discount);
        $balance = Money::sub($total, $paid);

        return compact('subtotal', 'discount', 'tax', 'tip', 'total', 'paid', 'pending', 'balance');
    }
}
