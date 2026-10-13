<?php

namespace App\Shared\Tenancy\Models;

use App\Shared\Identity\Models\User;
use Database\Factories\Shared\Tenancy\Models\TenantMembershipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Membership is the link between a user and a company and is queried globally
 * to resolve the tenant context, so it is intentionally not tenant-scoped.
 */
class TenantMembership extends Model
{
    /** @use HasFactory<TenantMembershipFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'role',
        'status',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
