<?php

namespace App\Shared\Tenancy\Concerns;

use App\Shared\Tenancy\Models\Tenant;
use App\Shared\Tenancy\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasTenantScope
{
    /**
     * Boot the trait: constrain every query to the active tenant.
     */
    public static function bootHasTenantScope(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
