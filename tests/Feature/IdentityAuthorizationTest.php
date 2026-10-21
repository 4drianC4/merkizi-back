<?php

namespace Tests\Feature;

use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Sales\Models\Order;
use App\Modules\Staff\Models\Employee;
use App\Shared\Identity\Models\Permission;
use App\Shared\Identity\Models\Role;
use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Models\Branch;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IdentityAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_permissions_inherit_from_role_and_explicit_overrides_take_precedence(): void
    {
        $user = User::factory()->admin()->create();
        $permission = Permission::query()->where('code', 'catalogo.productos.crear')->firstOrFail();
        $this->assertTrue($user->hasPermission($permission->code));
        $user->permissions()->attach($permission->id, ['granted' => false]);
        $this->assertFalse($user->hasPermission($permission->code));
        $user->permissions()->detach($permission->id);
        $this->assertTrue($user->hasPermission($permission->code));
        $user->role->permissions()->detach($permission->id);
        $this->assertFalse($user->hasPermission($permission->code));
        $user->permissions()->attach($permission->id, ['granted' => true]);
        $this->assertTrue($user->hasPermission($permission->code));
        $this->assertFalse($user->hasPermission('unknown.permission'));
        $this->assertTrue(Str::isUuid($user->role_id));
        $this->assertTrue(Str::isUuid($permission->id));
    }

    public function test_branch_permissions_require_an_active_assignment_in_the_same_company(): void
    {
        $user = User::factory()->admin()->create();
        $assigned = Branch::factory()->create(['tenant_id' => $user->tenant_id]);
        $unassigned = Branch::factory()->create(['tenant_id' => $user->tenant_id]);
        $foreign = Branch::factory()->create();
        $this->assertFalse($user->hasPermission('pos.venta.crear'));
        $user->branches()->attach($assigned->id, ['tenant_id' => $user->tenant_id, 'is_primary' => true]);
        $this->assertTrue($user->hasPermission('pos.venta.crear', $assigned));
        $this->assertFalse($user->hasPermission('pos.venta.crear', $unassigned));
        $this->assertFalse($user->hasPermission('pos.venta.crear', $foreign));
        $assigned->update(['status' => 'inactive']);
        $this->assertFalse($user->hasPermission('pos.venta.crear', $assigned));
        $this->assertTrue($user->hasPermission('catalogo.productos.crear'));
    }

    public function test_policies_require_their_specific_permission_for_collection_access(): void
    {
        $user = User::factory()->admin()->create();
        $branch = Branch::factory()->create(['tenant_id' => $user->tenant_id]);
        $user->branches()->attach($branch->id, ['tenant_id' => $user->tenant_id]);
        app(TenantContext::class)->run($user->tenant, function () use ($user): void {
            foreach ([Branch::class => 'sucursales.ver', Employee::class => 'usuarios.ver', Category::class => 'catalogo.categorias.ver', Product::class => 'catalogo.productos.ver', CashRegister::class => 'caja.cajas.ver', CashSession::class => 'caja.sesiones.ver', Order::class => 'pos.venta.ver'] as $model => $code) {
                $this->assertTrue(Gate::forUser($user)->allows('viewAny', $model), $code);
                $permission = Permission::query()->where('code', $code)->firstOrFail();
                $user->permissions()->attach($permission->id, ['granted' => false]);
                $this->assertFalse(Gate::forUser($user)->allows('viewAny', $model), $code);
            }
        });
    }

    public function test_branch_collections_and_actions_exclude_unassigned_branches(): void
    {
        $user = User::factory()->admin()->create();
        $assigned = Branch::factory()->create(['tenant_id' => $user->tenant_id]);
        $other = Branch::factory()->create(['tenant_id' => $user->tenant_id]);
        $user->branches()->attach($assigned->id, ['tenant_id' => $user->tenant_id]);
        $ownRegister = CashRegister::factory()->create(['tenant_id' => $user->tenant_id, 'branch_id' => $assigned->id]);
        $otherRegister = CashRegister::factory()->create(['tenant_id' => $user->tenant_id, 'branch_id' => $other->id]);
        $ownOrder = Order::factory()->create(['tenant_id' => $user->tenant_id, 'branch_id' => $assigned->id]);
        $otherOrder = Order::factory()->create(['tenant_id' => $user->tenant_id, 'branch_id' => $other->id]);
        $ownSession = CashSession::factory()->create(['tenant_id' => $user->tenant_id, 'branch_id' => $assigned->id, 'cash_register_id' => $ownRegister->id]);
        $otherSession = CashSession::factory()->create(['tenant_id' => $user->tenant_id, 'branch_id' => $other->id, 'cash_register_id' => $otherRegister->id]);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/cash-registers')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ownRegister->id);
        $this->getJson('/api/v1/cash-registers/'.$ownRegister->id.'/sessions/'.$ownSession->id)->assertOk();
        $this->getJson('/api/v1/cash-registers/'.$ownRegister->id.'/sessions/'.$otherSession->id)->assertNotFound();
        $this->getJson('/api/v1/orders')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ownOrder->id);
        $this->getJson('/api/v1/cash-registers/'.$otherRegister->id)->assertForbidden();
        $this->getJson('/api/v1/cash-registers/'.$otherRegister->id.'/sessions')->assertForbidden();
        $this->postJson('/api/v1/cash-registers/'.$otherRegister->id.'/sessions')->assertForbidden();
        $this->getJson('/api/v1/orders/'.$otherOrder->id)->assertForbidden();
        $this->postJson('/api/v1/orders/'.$otherOrder->id.'/payments', ['method' => 'cash', 'amount' => '10.00'])->assertForbidden();
        $this->postJson('/api/v1/cash-sessions/'.$otherSession->id.'/close')->assertForbidden();
        $this->postJson('/api/v1/cash-registers', ['name' => 'Forbidden', 'branch_id' => $other->id])->assertForbidden();
        $product = Product::factory()->create(['tenant_id' => $user->tenant_id]);
        $this->postJson('/api/v1/orders', ['cash_session_id' => $otherSession->id, 'items' => [['product_id' => $product->id, 'quantity' => 1]]])->assertForbidden();
        $this->assertDatabaseMissing('cash_registers', ['name' => 'Forbidden']);
        $this->assertDatabaseCount('orders', 2);
    }

    public function test_individual_revocation_is_enforced_by_http_endpoint(): void
    {
        $user = User::factory()->admin()->create();
        $user->permissions()->attach(Permission::query()->where('code', 'catalogo.productos.crear')->firstOrFail()->id, ['granted' => false]);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/products', ['name' => 'Denied', 'price' => '10.00'])->assertForbidden();
        $this->assertDatabaseMissing('products', ['name' => 'Denied']);
    }

    public function test_company_specific_roles_cannot_be_assigned_to_another_company(): void
    {
        $role = Role::factory()->create();
        $this->expectException(ValidationException::class);
        User::factory()->create(['role_id' => $role->id]);
    }

    public function test_only_superadmin_may_have_no_company(): void
    {
        $this->expectException(ValidationException::class);
        User::factory()->create(['tenant_id' => null]);
    }

    public function test_database_rejects_cross_company_branch_assignments(): void
    {
        $user = User::factory()->create();
        $branch = Branch::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('branch_user')->insert(['user_id' => $user->id, 'branch_id' => $branch->id, 'tenant_id' => $user->tenant_id]);
    }

    public function test_database_rejects_two_primary_branches_for_the_same_user(): void
    {
        $user = User::factory()->create();
        $branches = Branch::factory()->count(2)->create(['tenant_id' => $user->tenant_id]);
        $user->branches()->attach($branches[0]->id, ['tenant_id' => $user->tenant_id, 'is_primary' => true]);
        $this->expectException(QueryException::class);
        $user->branches()->attach($branches[1]->id, ['tenant_id' => $user->tenant_id, 'is_primary' => true]);
    }

    public function test_company_role_named_superadmin_has_no_platform_privileges(): void
    {
        $role = Role::factory()->create(['name' => Role::SuperAdmin]);
        $user = User::factory()->create(['tenant_id' => $role->tenant_id, 'role_id' => $role->id]);
        $this->assertFalse($user->isSuperAdmin());
        $this->assertFalse($user->hasPermission('usuarios.permisos'));
    }
}
