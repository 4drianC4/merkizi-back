<?php

namespace Tests\Feature;

use App\Modules\Cash\Models\CashSession;
use App\Shared\Identity\Models\Permission;
use App\Shared\Identity\Models\Role;
use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_an_employee_as_a_user_with_role_and_multiple_branches(): void
    {
        $admin = $this->demoAdmin();
        $branches = $admin->branches->pluck('id')->all();
        $role = Role::query()->where('name', Role::Employee)->firstOrFail();
        Sanctum::actingAs($admin);
        $response = $this->postJson('/api/v1/employees', [
            'name' => 'Nuevo empleado', 'email' => 'new@example.com', 'password' => 'strong-password',
            'role_id' => $role->id, 'branch_ids' => $branches, 'primary_branch_id' => $branches[1],
        ])->assertCreated()->assertJsonPath('data.role_id', $role->id)->assertJsonCount(2, 'data.branches')->assertJsonMissingPath('data.password');
        $user = User::query()->findOrFail($response->json('data.id'));
        $this->assertSame($admin->tenant_id, $user->tenant_id);
        $this->assertTrue(Hash::check('strong-password', $user->password));
        $this->assertDatabaseHas('branch_user', ['user_id' => $user->id, 'branch_id' => $branches[1], 'is_primary' => true]);
        $this->assertTrue($user->hasPermission('pos.venta.crear', $admin->branches[0]));
    }

    public function test_updates_role_branches_and_status_on_the_same_identity(): void
    {
        $admin = $this->demoAdmin();
        $employee = User::query()->where('email', 'employee@example.com')->firstOrFail();
        $north = Branch::query()->where('name', 'Sucursal Norte')->firstOrFail();
        $role = Role::query()->where('name', 'Cajero')->firstOrFail();
        Sanctum::actingAs($admin);
        $this->patchJson('/api/v1/employees/'.$employee->id, ['role_id' => $role->id, 'branch_ids' => [$north->id], 'active' => false])
            ->assertOk()->assertJsonPath('data.role_id', $role->id)->assertJsonPath('data.active', false)->assertJsonCount(1, 'data.branches');
        $this->assertDatabaseHas('users', ['id' => $employee->id, 'role_id' => $role->id, 'active' => false]);
        $this->assertDatabaseHas('branch_user', ['user_id' => $employee->id, 'branch_id' => $north->id, 'is_primary' => true]);
        $this->assertSame(1, $employee->branches()->count());
    }

    public function test_rejects_roles_branches_and_tenant_identity_from_another_company(): void
    {
        $admin = $this->demoAdmin();
        $foreignRole = Role::factory()->create();
        $foreignBranch = Branch::factory()->create();
        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/employees', [
            'name' => 'Forbidden', 'email' => 'foreign@example.com', 'password' => 'password',
            'role_id' => $foreignRole->id, 'branch_ids' => [$foreignBranch->id], 'tenant_id' => $foreignRole->tenant_id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['role_id', 'branch_ids.0', 'tenant_id']);
        $this->assertDatabaseMissing('users', ['email' => 'foreign@example.com']);
        $this->getJson('/api/v1/roles/'.$foreignRole->id)->assertNotFound();
    }

    public function test_rejects_superadmin_role_and_primary_branch_outside_the_assignments(): void
    {
        $admin = $this->demoAdmin();
        Sanctum::actingAs($admin);
        $superRole = Role::query()->where('name', Role::SuperAdmin)->firstOrFail();
        $this->postJson('/api/v1/employees', [
            'name' => 'Invalid', 'email' => 'invalid@example.com', 'password' => 'password', 'role_id' => $superRole->id, 'branch_ids' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('role_id');
        $this->postJson('/api/v1/employees', [
            'name' => 'Invalid', 'email' => 'invalid@example.com', 'password' => 'password',
            'role_id' => $admin->role_id, 'branch_ids' => [$admin->branches[0]->id], 'primary_branch_id' => $admin->branches[1]->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('primary_branch_id');
    }

    public function test_permission_overrides_record_the_grantor_and_can_restore_role_inheritance(): void
    {
        $admin = $this->demoAdmin();
        $employee = User::query()->where('email', 'employee@example.com')->firstOrFail();
        $permission = Permission::query()->where('code', 'pos.venta.cobrar')->firstOrFail();
        Sanctum::actingAs($admin);
        $this->putJson('/api/v1/employees/'.$employee->id.'/permissions', ['permissions' => [['permission_id' => $permission->id, 'granted' => false]]])
            ->assertOk()->assertJsonPath('data.permissions.0.granted', false);
        $this->assertDatabaseHas('permission_user', ['user_id' => $employee->id, 'permission_id' => $permission->id, 'granted' => false, 'granted_by_user_id' => $admin->id]);
        $this->assertFalse($employee->hasPermission('pos.venta.cobrar'));
        $this->putJson('/api/v1/employees/'.$employee->id.'/permissions', ['permissions' => []])->assertOk()->assertJsonCount(0, 'data.permissions');
        $this->assertTrue($employee->hasPermission('pos.venta.cobrar'));
        $this->assertDatabaseMissing('permission_user', ['user_id' => $employee->id, 'permission_id' => $permission->id]);
    }

    public function test_cannot_escalate_own_permissions_or_modify_own_access_assignments(): void
    {
        $admin = $this->demoAdmin();
        Sanctum::actingAs($admin);
        $this->putJson('/api/v1/employees/'.$admin->id.'/permissions', ['permissions' => []])->assertForbidden();
        $this->patchJson('/api/v1/employees/'.$admin->id, ['role_id' => $admin->role_id, 'branch_ids' => [], 'active' => false])
            ->assertUnprocessable()->assertJsonValidationErrors(['role_id', 'branch_ids', 'active']);
        $this->deleteJson('/api/v1/employees/'.$admin->id)->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'active' => true, 'deleted_at' => null]);
    }

    public function test_limited_manager_cannot_assign_or_acquire_permissions_they_do_not_have(): void
    {
        $admin = $this->demoAdmin();
        $managerRole = Role::factory()->create(['tenant_id' => $admin->tenant_id]);
        $managerRole->permissions()->sync(Permission::query()->whereIn('code', ['usuarios.crear', 'usuarios.editar', 'usuarios.permisos', 'roles.crear'])->pluck('id'));
        $manager = User::factory()->create(['tenant_id' => $admin->tenant_id, 'role_id' => $managerRole->id]);
        Sanctum::actingAs($manager);
        $this->postJson('/api/v1/employees', [
            'name' => 'Escalation', 'email' => 'escalation@example.com', 'password' => 'password', 'role_id' => $admin->role_id, 'branch_ids' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('role_id');
        $this->postJson('/api/v1/roles', ['name' => 'Escalated role', 'permission_ids' => [Permission::query()->where('code', 'usuarios.eliminar')->firstOrFail()->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('permission_ids');
        $this->patchJson('/api/v1/employees/'.$admin->id, ['password' => 'stolen-password'])->assertForbidden();
        $this->putJson('/api/v1/employees/'.$admin->id.'/permissions', ['permissions' => []])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'escalation@example.com']);
    }

    public function test_company_roles_can_be_managed_but_system_and_assigned_roles_are_protected(): void
    {
        $admin = $this->demoAdmin();
        $permission = Permission::query()->where('code', 'catalogo.productos.ver')->firstOrFail();
        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/permissions')->assertOk();
        $roleId = $this->postJson('/api/v1/roles', ['name' => 'Consulta', 'permission_ids' => [$permission->id]])
            ->assertCreated()->assertJsonPath('data.tenant_id', $admin->tenant_id)->json('data.id');
        $this->assertDatabaseHas('permission_role', ['role_id' => $roleId, 'permission_id' => $permission->id]);
        $this->getJson('/api/v1/roles/'.$roleId)->assertOk();
        $this->patchJson('/api/v1/roles/'.$roleId, ['name' => 'Consulta actualizada', 'permission_ids' => []])->assertOk()->assertJsonCount(0, 'data.permissions');
        $this->deleteJson('/api/v1/roles/'.$roleId)->assertNoContent();
        $this->assertDatabaseMissing('roles', ['id' => $roleId]);
        $this->patchJson('/api/v1/roles/'.$admin->role_id, ['name' => 'Override', 'permission_ids' => []])->assertForbidden();
        $this->deleteJson('/api/v1/roles/'.$admin->role_id)->assertForbidden();
        $cashierRole = Role::query()->where('name', 'Cajero')->firstOrFail();
        $this->deleteJson('/api/v1/roles/'.$cashierRole->id)->assertUnprocessable();
    }

    public function test_employee_deletion_preserves_historical_references_and_revokes_login(): void
    {
        $admin = $this->demoAdmin();
        $employee = User::query()->where('email', 'employee@example.com')->firstOrFail();
        $token = $employee->createToken('old-session');
        $session = CashSession::factory()->create(['tenant_id' => $employee->tenant_id, 'opened_by' => $employee->id]);
        Sanctum::actingAs($admin);
        $this->deleteJson('/api/v1/employees/'.$employee->id)->assertNoContent();
        $this->assertSoftDeleted('users', ['id' => $employee->id]);
        $this->assertDatabaseHas('cash_sessions', ['id' => $session->id, 'opened_by' => $employee->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->assertFalse(User::withTrashed()->findOrFail($employee->id)->active);
    }

    private function demoAdmin(): User
    {
        $this->seed();

        return User::query()->where('email', 'test@example.com')->firstOrFail();
    }
}
