<?php

namespace Tests\Feature\Modules;

use App\Shared\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UuidIdentifiersTest extends TestCase
{
    use RefreshDatabase;

    public function test_uuid_resources_can_be_created_related_and_resolved(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'test@example.com')->firstOrFail();
        $this->assertTrue(Str::isUuid($user->id));
        Sanctum::actingAs($user);

        $branchId = $this->postJson('/api/v1/branches', ['name' => 'Sucursal UUID'])
            ->assertCreated()->json('data.id');
        $this->assertTrue(Str::isUuid($branchId));
        $employeeId = $this->postJson('/api/v1/employees', ['name' => 'Empleado UUID', 'email' => 'uuid@example.com', 'password' => 'password', 'branch_ids' => [$branchId], 'role_id' => $user->role_id])
            ->assertCreated()->assertJsonPath('data.branches.0.id', $branchId)->json('data.id');
        $this->assertTrue(Str::isUuid($employeeId));
        $this->getJson('/api/v1/employees/'.$employeeId)->assertOk()->assertJsonPath('data.id', $employeeId);
        $this->getJson('/api/v1/employees/1')->assertNotFound();
        $this->getJson('/api/v1/employees/not-a-uuid')->assertNotFound();
        $this->postJson('/api/v1/employees', ['name' => 'Invalid', 'branch_id' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('branch_id');
        $this->assertDatabaseHas('users', ['id' => $employeeId, 'tenant_id' => $user->tenant_id]);
        $this->assertDatabaseHas('branch_user', ['user_id' => $employeeId, 'branch_id' => $branchId]);
    }

    public function test_bearer_token_resolves_uuid_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('uuid-test')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/user')->assertOk()->assertJsonPath('id', $user->id);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    public function test_database_session_stores_uuid_user(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $this->withHeader('Origin', 'http://localhost:4200')->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->assertJsonPath('id', $user->id);
        $this->assertDatabaseHas('sessions', ['user_id' => $user->id]);
    }
}
