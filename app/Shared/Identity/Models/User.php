<?php

namespace App\Shared\Identity\Models;

use App\Shared\Tenancy\Models\Branch;
use App\Shared\Tenancy\Models\Tenant;
use Database\Factories\Shared\Identity\Models\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasUuids;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = ['tenant_id', 'role_id', 'name', 'email', 'password', 'phone', 'photo_url', 'active'];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = ['active' => true];

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            $role = Role::query()->find($user->role_id);
            if ($role === null || ($role->tenant_id !== null && $role->tenant_id !== $user->tenant_id)
                || ($role->isSuperAdmin() !== ($user->tenant_id === null))) {
                throw ValidationException::withMessages(['role_id' => 'The role must belong to the user company. Only SuperAdmin may have no company.']);
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'active' => 'boolean', 'last_access_at' => 'datetime'];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return BelongsToMany<Branch, $this> */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_user', 'user_id', 'branch_id')->withPivot(['tenant_id', 'is_primary']);
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_user', 'user_id', 'permission_id')->withPivot(['granted', 'granted_by_user_id', 'created_at']);
    }

    public function belongsToCompany(Tenant $tenant): bool
    {
        return $this->active && ! $this->trashed() && $this->tenant_id === $tenant->id && $tenant->status === 'active';
    }

    public function isSuperAdmin(): bool
    {
        return $this->active && ! $this->trashed() && $this->tenant_id === null && $this->role?->isSuperAdmin() === true;
    }

    public function hasPermission(string $code, ?Branch $branch = null): bool
    {
        if (! $this->active || $this->trashed() || $this->tenant_id === null || $this->tenant?->status !== 'active') {
            return false;
        }
        $role = $this->role;
        if ($role === null || ($role->tenant_id !== null && $role->tenant_id !== $this->tenant_id) || $role->isSuperAdmin()) {
            return false;
        }
        $permission = Permission::query()->where('code', $code)->first();
        if ($permission === null) {
            return false;
        }
        $override = $this->permissions()->whereKey($permission->id)->first();
        $granted = $override !== null
            ? (bool) $override->getRelation('pivot')->getAttribute('granted')
            : $role->permissions()->whereKey($permission->id)->exists();
        if (! $granted) {
            return false;
        }
        if ($branch !== null && ($branch->tenant_id !== $this->tenant_id || $branch->status !== 'active')) {
            return false;
        }
        if ($permission->scope === 'EMPRESA') {
            return true;
        }
        $branches = $this->branches()->where('status', 'active');

        return $branch === null ? $branches->exists() : $branches->whereKey($branch->id)->exists();
    }

    /** @return list<string> */
    public function effectivePermissionCodes(): array
    {
        if (! $this->active || $this->trashed() || $this->tenant_id === null || $this->tenant?->status !== 'active') {
            return [];
        }
        $this->loadMissing(['role.permissions', 'permissions', 'branches']);
        $permissions = $this->role->permissions->keyBy('id');
        foreach ($this->permissions as $override) {
            if ((bool) $override->getRelation('pivot')->getAttribute('granted')) {
                $permissions->put($override->id, $override);
            } else {
                $permissions->forget($override->id);
            }
        }
        $hasBranch = $this->branches->contains(fn (Branch $branch): bool => $branch->tenant_id === $this->tenant_id && $branch->status === 'active');

        return $permissions->filter(fn (Permission $permission): bool => $permission->scope === 'EMPRESA' || $hasBranch)->pluck('code')->sort()->values()->all();
    }

    public function canManageUser(User $target): bool
    {
        if ($target->tenant_id !== $this->tenant_id || $target->isSuperAdmin()) {
            return false;
        }
        $overrides = $target->permissions()->get()->keyBy('id');
        $permissions = $target->role->permissions()->get()->keyBy('id')->merge($overrides);
        foreach ($permissions as $permission) {
            if (isset($overrides[$permission->id]) && ! (bool) $overrides[$permission->id]->getRelation('pivot')->getAttribute('granted')) {
                continue;
            }
            if (! $this->hasPermission($permission->code)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Restrict branch-scoped collections using the same assignment rule as individual actions.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function restrictToBranches(Builder $query): Builder
    {
        return $query->whereIn($query->getModel()->qualifyColumn('branch_id'), $this->branches()->where('status', 'active')->select('branches.id'));
    }
}
