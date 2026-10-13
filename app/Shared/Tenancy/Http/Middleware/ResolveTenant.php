<?php

namespace App\Shared\Tenancy\Http\Middleware;

use App\Shared\Tenancy\Resolvers\TenantResolver;
use App\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(
        private readonly TenantResolver $resolver,
        private readonly TenantContext $context,
    ) {}

    /**
     * Resolve the tenant and always clear the context after the request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->context->setTenant($this->resolver->resolve($request));

        try {
            return $next($request);
        } finally {
            $this->context->setTenant(null);
        }
    }
}
