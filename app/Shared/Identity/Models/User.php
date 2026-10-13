<?php

namespace App\Shared\Identity\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Shared\Tenancy\Models\Tenant;
use App\Shared\Tenancy\Models\TenantMembership;
use Database\Factories\Shared\Identity\Models\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The memberships linking this user to companies.
     *
     * @return HasMany<TenantMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    /**
     * The companies this user belongs to through memberships.
     *
     * @return BelongsToMany<Tenant, $this>
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_memberships')
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    /**
     * The active membership linking this user to the given tenant, if any.
     */
    public function activeMembershipFor(Tenant $tenant): ?TenantMembership
    {
        return $this->memberships()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Whether the user has an administrative role in the given tenant.
     */
    public function isTenantAdmin(Tenant $tenant): bool
    {
        return in_array($this->activeMembershipFor($tenant)?->role, ['admin', 'owner'], true);
    }
}
