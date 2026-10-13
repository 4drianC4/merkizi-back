<?php

namespace Tests\Feature\Tenancy;

use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Models\Tenant;
use App\Shared\Tenancy\Models\TenantMembership;
use App\Shared\Tenancy\Resolvers\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class TenantResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_tenant_from_single_active_membership(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        TenantMembership::factory()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'status' => 'active',
        ]);

        $request = $this->requestFor($user);

        $resolved = app(TenantResolver::class)->resolve($request);

        $this->assertNotNull($resolved);
        $this->assertSame($tenant->id, $resolved->id);
    }

    public function test_ignores_inactive_memberships(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        TenantMembership::factory()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'status' => 'suspended',
        ]);

        $request = $this->requestFor($user);

        $this->assertNull(app(TenantResolver::class)->resolve($request));
    }

    public function test_resolves_nothing_for_unauthenticated_request(): void
    {
        $request = Request::create('/api/v1/anything', 'GET');

        $this->assertNull(app(TenantResolver::class)->resolve($request));
    }

    public function test_session_selected_tenant_is_honored_when_authorized(): void
    {
        $user = User::factory()->create();
        $first = Tenant::factory()->create();
        $second = Tenant::factory()->create();
        TenantMembership::factory()->create(['user_id' => $user->id, 'tenant_id' => $first->id]);
        TenantMembership::factory()->create(['user_id' => $user->id, 'tenant_id' => $second->id]);

        $request = $this->requestFor($user);
        $request->session()->put('tenant_id', $second->id);

        $resolved = app(TenantResolver::class)->resolve($request);

        $this->assertSame($second->id, $resolved->id);
    }

    public function test_session_selected_tenant_is_ignored_when_not_authorized(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        TenantMembership::factory()->create(['user_id' => $user->id, 'tenant_id' => $tenant->id]);

        $request = $this->requestFor($user);
        $request->session()->put('tenant_id', 9999);

        $resolved = app(TenantResolver::class)->resolve($request);

        $this->assertSame($tenant->id, $resolved->id);
    }

    private function requestFor(User $user): Request
    {
        $request = Request::create('/api/v1/anything', 'GET');
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession(app('session.store'));

        return $request;
    }
}
