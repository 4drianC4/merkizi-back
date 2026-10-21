<?php

namespace App\Shared\Identity\Models;

use App\Shared\Tenancy\Models\Tenant;
use App\Shared\Tenancy\TenantContext;
use Database\Factories\Shared\Identity\Models\RoleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    use HasUuids;

    public const string SuperAdmin = 'SuperAdmin';

    public const string CompanyAdmin = 'Admin Empresa';

    public const string Employee = 'Empleado';

    public const UPDATED_AT = null;

    protected $fillable = ['tenant_id', 'name', 'description'];

    protected static function booted(): void
    {
        static::addGlobalScope('company', function (Builder $query): void {
            $tenantId = app(TenantContext::class)->id();
            if ($tenantId !== null) {
                $query->where(fn (Builder $query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId));
            }
        });
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->tenant_id === null && $this->name === self::SuperAdmin;
    }
}
