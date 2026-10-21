<?php

namespace App\Shared\Tenancy;

use App\Shared\Tenancy\Resolvers\TenantResolver;
use App\Shared\Tenancy\Resolvers\UserTenantResolver;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);

        $this->app->bind(TenantResolver::class, UserTenantResolver::class);
    }
}
