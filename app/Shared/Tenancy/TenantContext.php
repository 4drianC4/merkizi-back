<?php

namespace App\Shared\Tenancy;

use App\Shared\Tenancy\Models\Tenant;
use RuntimeException;

/**
 * Holds the tenant active for the current unit of work (request, job, command).
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    private ?string $tenantId = null;

    /**
     * Set the tenant that subsequent queries should be scoped to.
     */
    public function setTenant(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->tenantId = $tenant?->getKey();
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?string
    {
        return $this->tenantId;
    }

    /**
     * Run a callback inside a tenant context, restoring the previous one after.
     */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->tenant;

        $this->setTenant($tenant);

        try {
            return $callback();
        } finally {
            $this->setTenant($previous);
        }
    }

    /**
     * Get the active tenant or fail when none is set.
     */
    public function requireTenant(): Tenant
    {
        return $this->tenant ?? throw new RuntimeException('No active tenant context.');
    }
}
