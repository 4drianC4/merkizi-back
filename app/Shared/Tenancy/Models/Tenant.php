<?php

namespace App\Shared\Tenancy\Models;

use App\Shared\Identity\Models\User;
use Database\Factories\Shared\Tenancy\Models\TenantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'status',
    ];

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return HasOne<Subscription, $this>
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->latest('created_at')->orderByDesc('id');
    }

    /**
     * The plan governing the active subscription, if any.
     */
    public function plan(): ?Plan
    {
        return $this->activeSubscription->plan ?? null;
    }

    /**
     * Read a single limit value from the tenant's active plan, if any.
     */
    public function limit(string $key): ?int
    {
        return $this->plan()?->limit($key);
    }
}
