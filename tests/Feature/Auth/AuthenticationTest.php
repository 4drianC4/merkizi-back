<?php

namespace Tests\Feature\Auth;

use App\Shared\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Origin', 'http://localhost:4200');
    }

    public function test_user_can_login_and_logout(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('id', $user->id);

        $this->assertAuthenticatedAs($user, 'web');

        $this->postJson('/api/v1/logout')->assertNoContent();

        $this->assertGuest('web');
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_inactive_user_cannot_login_or_use_an_existing_token(): void
    {
        $user = User::factory()->inactive()->create();
        $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertGuest('web');
        $token = $user->createToken('previous-token')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/user')->assertForbidden();
    }

    public function test_deleted_user_cannot_login(): void
    {
        $user = User::factory()->create();
        $user->delete();
        $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertGuest('web');
    }

    public function test_current_user_exposes_effective_permissions_and_branch_assignments(): void
    {
        $this->seed();
        $cashier = User::query()->where('email', 'cashier@example.com')->firstOrFail();
        $this->actingAs($cashier, 'web');
        $response = $this->getJson('/api/v1/user')->assertOk()
            ->assertJsonPath('id', $cashier->id)
            ->assertJsonPath('tenant_id', $cashier->tenant_id)
            ->assertJsonPath('is_superadmin', false)
            ->assertJsonPath('role.name', 'Cajero')
            ->assertJsonCount(1, 'branches')
            ->assertJsonMissingPath('password');
        $this->assertContains('pos.venta.cobrar', $response->json('permission_codes'));
        $this->assertNotContains('caja.sesiones.cerrar', $response->json('permission_codes'));
    }
}
