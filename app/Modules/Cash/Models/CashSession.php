<?php

namespace App\Modules\Cash\Models;

use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Concerns\HasTenantScope;
use App\Shared\Tenancy\Models\Branch;
use Database\Factories\Modules\Cash\Models\CashSessionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    /** @use HasFactory<CashSessionFactory> */
    use HasFactory;

    use HasTenantScope;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'cash_register_id',
        'opened_by',
        'opened_at',
        'closed_at',
        'opening_balance',
        'expected_cash_balance',
        'counted_cash_balance',
        'cash_difference',
        'difference_note',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_balance' => 'decimal:2',
            'expected_cash_balance' => 'decimal:2',
            'counted_cash_balance' => 'decimal:2',
            'cash_difference' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<CashRegister, $this>
     */
    public function register(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'cash_register_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * @return HasMany<CashMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }
}
