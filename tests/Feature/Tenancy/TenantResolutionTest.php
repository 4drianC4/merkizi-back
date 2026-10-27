<?php

namespace Tests\Feature\Tenancy;

use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Models\Tenant;
use App\Shared\Tenancy\Resolvers\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class TenantResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_company_from_authenticated_user(): void
    {
        $user = User::factory()->create();
        $this->assertSame($user->tenant_id, app(TenantResolver::class)->resolve($this->requestFor($user))?->id);
    }

    public function test_ignores_inactive_accounts(): void
    {
        $user = User::factory()->inactive()->create();
        $this->assertNull(app(TenantResolver::class)->resolve($this->requestFor($user)));
    }

    public function test_resolves_nothing_for_unauthenticated_request(): void
    {
        $this->assertNull(app(TenantResolver::class)->resolve(Request::create('/api/v1/anything', 'GET')));
    }

    public function test_session_selected_company_cannot_override_user_company(): void
    {
        $user = User::factory()->create();
        $other = Tenant::factory()->create();
        $request = $this->requestFor($user);
        $request->session()->put('tenant_id', $other->id);
        $request->headers->set('X-Tenant-ID', $other->id);
        $request->query->set('tenant_id', $other->id);
        $this->assertSame($user->tenant_id, app(TenantResolver::class)->resolve($request)?->id);
    }

    public function test_superadmin_has_no_implicit_company(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->assertNull(app(TenantResolver::class)->resolve($this->requestFor($user)));
    }

    public function test_inactive_company_cannot_be_resolved(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'inactive']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->assertNull(app(TenantResolver::class)->resolve($this->requestFor($user)));
    }

    private function requestFor(User $user): Request
    {
        $request = Request::create('/api/v1/anything', 'GET');
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession(app('session.store'));

        return $request;
    }
}
