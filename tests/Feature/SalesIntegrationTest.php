<?php

namespace Tests\Feature;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductPrice;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\RawMaterialCategory;
use App\Modules\Sales\Models\DiningTable;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\TableAccount;
use App\Shared\Identity\Models\Permission;
use App\Shared\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\InteractsWithSales;
use Tests\TestCase;

class SalesIntegrationTest extends TestCase
{
    use InteractsWithSales;
    use RefreshDatabase;

    public function test_table_account_accumulates_orders_and_remains_open_until_explicit_close(): void
    {
        $fixture = $this->salesFixture();
        $account = $this->openSalesAccount($fixture);
        $this->addSalesOrder($account, $fixture, '2.000');
        $this->addSalesOrder($account, $fixture);
        $this->getJson('/api/v1/accounts/'.$account['id'])->assertOk()->assertJsonPath('data.total', '30.00')->assertJsonPath('data.balance', '30.00')->assertJsonCount(2, 'data.orders');
        $this->getJson('/api/v1/accounts/open-tables')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/tables?status=occupied')->assertOk()->assertJsonPath('data.0.active_account.id', $account['id']);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/accounts/'.$account['id'].'/payments', ['payments' => [['method' => 'cash', 'amount' => '30.00', 'received_amount' => '50.00']]])
            ->assertCreated()->assertJsonPath('account.status', 'open')->assertJsonPath('account.balance', '0.00')->assertJsonPath('data.0.change', '20.00');
        $this->getJson('/api/v1/tables?status=occupied')->assertOk()->assertJsonCount(1, 'data');
        $saleId = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/accounts/'.$account['id'].'/close')
            ->assertCreated()->assertJsonPath('data.total', '30.00')->assertJsonCount(2, 'data.items')->json('data.id');
        $this->getJson('/api/v1/tables?status=free')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/sales/'.$saleId.'/audit')->assertOk()->assertJsonCount(5, 'data');
        $this->assertDatabaseHas('table_accounts', ['id' => $account['id'], 'status' => 'paid']);
    }

    public function test_raw_material_and_combined_categories_use_current_catalog_permissions(): void
    {
        $fixture = $this->salesFixture();
        $category = RawMaterialCategory::factory()->create(['tenant_id' => $fixture['user']->tenant_id]);

        $this->getJson('/api/v1/raw-material-categories')->assertOk()->assertJsonFragment(['id' => $category->id]);
        $this->getJson('/api/v1/categories/all')->assertOk()->assertJsonFragment(['id' => $category->id]);
        $this->postJson('/api/v1/raw-material-categories', ['name' => 'Insumos'])
            ->assertCreated()->assertJsonPath('data.name', 'Insumos');
        $this->assertDatabaseHas('raw_material_categories', ['tenant_id' => $fixture['user']->tenant_id, 'name' => 'Insumos']);

        $permission = Permission::query()->where('code', 'catalogo.categorias.ver')->firstOrFail();
        $fixture['user']->permissions()->attach($permission->id, ['granted' => false]);
        $this->getJson('/api/v1/raw-material-categories')->assertForbidden();
        $this->getJson('/api/v1/categories/all')->assertForbidden();
    }

    public function test_category_filters_preserve_children_counts_and_name_order(): void
    {
        $fixture = $this->salesFixture();
        $tenantId = $fixture['user']->tenant_id;
        $root = Category::factory()->create(['tenant_id' => $tenantId, 'name' => 'Zeta']);
        $first = Category::factory()->create(['tenant_id' => $tenantId, 'parent_id' => $root->id, 'name' => 'Alfa']);
        $second = Category::factory()->create(['tenant_id' => $tenantId, 'parent_id' => $root->id, 'name' => 'Beta']);
        Category::factory()->create(['name' => 'Foreign']);
        Product::factory()->create(['tenant_id' => $tenantId, 'category_id' => $root->id]);

        $roots = $this->getJson('/api/v1/categories?parent_id=null')->assertOk();
        $roots->assertJsonFragment(['id' => $root->id, 'products_count' => 1, 'children_count' => 2]);
        $roots->assertJsonFragment(['id' => $first->id, 'parent_id' => $root->id]);
        foreach ($roots->json('data') as $category) {
            $this->assertNull($category['parent_id']);
            $this->assertNotSame('Foreign', $category['name']);
        }

        $this->getJson('/api/v1/categories?parent_id='.$root->id)->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.id', $second->id)->assertJsonPath('data.0.children', [])
            ->assertJsonPath('data.0.products_count', 0)->assertJsonPath('data.0.children_count', 0);
    }

    public function test_catalog_prices_variants_and_extras_are_priced_on_the_server(): void
    {
        $fixture = $this->salesFixture();
        ProductPrice::factory()->create(['tenant_id' => $fixture['user']->tenant_id, 'branch_id' => $fixture['branch']->id, 'product_id' => $fixture['product']->id, 'price' => '12.00']);
        $variant = ProductVariant::factory()->create(['product_id' => $fixture['product']->id, 'price' => '15.00']);
        $extra = Product::factory()->create(['tenant_id' => $fixture['user']->tenant_id, 'price' => '3.00', 'status' => 'active']);
        $fixture['product']->extras()->attach($extra->id, ['price' => '2.00', 'active' => true]);
        $this->getJson('/api/v1/products/'.$fixture['product']->id.'?branch_id='.$fixture['branch']->id)->assertOk()
            ->assertJsonPath('data.effective_price', '12.00')->assertJsonPath('data.variants.0.price', '15.00');
        $account = $this->openSalesAccount($fixture, takeaway: true);
        $order = $this->postJson('/api/v1/orders', ['account_id' => $account['id'], 'items' => [[
            'product_id' => $fixture['product']->id, 'quantity' => '2.000', 'variant_id' => $variant->id, 'extras' => [['product_id' => $extra->id]], 'notes' => 'Sin cebolla',
        ]]])->assertCreated()->assertJsonPath('data.total', '34.00')->assertJsonPath('data.items.0.price', '17.00')->json('data');
        $fixture['product']->update(['price' => '100.00']);
        $this->patchJson('/api/v1/orders/'.$order['id'].'/items', ['version' => 1, 'items' => [['id' => $order['items'][0]['id'], 'quantity' => '3.000']]])
            ->assertOk()->assertJsonPath('data.total', '51.00')->assertJsonPath('data.items.0.price', '17.00');
        $this->assertDatabaseHas('orders', ['id' => $order['id'], 'version' => 2, 'total' => '51.00']);
    }

    public function test_item_editing_rejects_stale_versions_and_payments_greater_than_new_total(): void
    {
        $fixture = $this->salesFixture();
        $account = $this->openSalesAccount($fixture);
        $order = $this->addSalesOrder($account, $fixture, '2.000');
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/accounts/'.$account['id'].'/payments', ['payments' => [['method' => 'cash', 'amount' => '15.00']]])->assertCreated();
        $this->patchJson('/api/v1/orders/'.$order['id'].'/items', ['version' => 2, 'items' => []])->assertConflict()->assertJsonPath('code', 'version_conflict');
        $this->patchJson('/api/v1/orders/'.$order['id'].'/items', ['version' => 1, 'items' => []])->assertConflict()->assertJsonPath('code', 'payments_exceed_total');
        $this->assertDatabaseHas('orders', ['id' => $order['id'], 'version' => 1, 'total' => '20.00']);
        $this->addSalesOrder($account, $fixture);
        $this->getJson('/api/v1/accounts/'.$account['id'])->assertOk()->assertJsonPath('data.balance', '15.00');
    }

    public function test_table_occupancy_blocking_and_same_branch_cash_session_are_enforced(): void
    {
        $fixture = $this->salesFixture();
        $this->openSalesAccount($fixture);
        $this->postJson('/api/v1/accounts', ['branch_id' => $fixture['branch']->id, 'service_type' => 'table', 'table_id' => $fixture['table']->id])
            ->assertConflict()->assertJsonPath('code', 'table_occupied');
        $this->patchJson('/api/v1/tables/'.$fixture['table']->id, ['blocked' => true])->assertConflict();
        $blocked = DiningTable::factory()->create(['area_id' => $fixture['table']->area_id, 'blocked' => true]);
        $this->postJson('/api/v1/accounts', ['branch_id' => $fixture['branch']->id, 'service_type' => 'table', 'table_id' => $blocked->id])->assertConflict()->assertJsonPath('code', 'table_unavailable');
        $this->assertDatabaseCount('table_accounts', 1);
    }

    public function test_sales_resources_are_protected_by_company_branch_and_permissions(): void
    {
        $fixture = $this->salesFixture();
        $account = $this->openSalesAccount($fixture);
        $foreign = TableAccount::factory()->create();
        $this->getJson('/api/v1/accounts/'.$foreign->id)->assertNotFound();
        $unassigned = User::factory()->admin()->create(['tenant_id' => $fixture['user']->tenant_id]);
        Sanctum::actingAs($unassigned);
        $this->getJson('/api/v1/accounts/'.$account['id'])->assertForbidden();
        Sanctum::actingAs($fixture['user']);
        $permission = Permission::query()->where('code', 'pos.venta.crear')->firstOrFail();
        $fixture['user']->permissions()->attach($permission->id, ['granted' => false]);
        $this->postJson('/api/v1/accounts', ['branch_id' => $fixture['branch']->id, 'service_type' => 'takeaway'])->assertForbidden();
    }

    public function test_history_filters_pagination_export_and_historical_snapshots(): void
    {
        $fixture = $this->salesFixture();
        $account = $this->openSalesAccount($fixture, takeaway: true);
        $this->addSalesOrder($account, $fixture);
        $originalName = $fixture['product']->name;
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/accounts/'.$account['id'].'/payments', ['payments' => [['method' => 'cash', 'amount' => '10.00']]])->assertCreated();
        $sale = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/accounts/'.$account['id'].'/close')->assertCreated()->json('data');
        $fixture['product']->update(['name' => 'New name', 'price' => '99.00']);
        $this->getJson('/api/v1/sales/'.$sale['id'])->assertOk()->assertJsonPath('data.items.0.name', $originalName)->assertJsonPath('data.total', '10.00');
        $this->getJson('/api/v1/sales/history?q=123456&method=cash&service_type=takeaway&per_page=1')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $sale['id']);
        $this->getJson('/api/v1/sales/history?method=qr')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/sales/history?sort=password')->assertUnprocessable()->assertJsonValidationErrors('sort');
        $this->get('/api/v1/sales/export?method=cash')->assertOk()->assertDownload('sales.csv')->assertStreamedContent("id,number,customer,tax_id,status,subtotal,discount,tax,tip,total,created_at\n".$sale['id'].',1,Cliente,123456,completed,10.00,0.00,0.00,0.00,10.00,'.Sale::query()->findOrFail($sale['id'])->created_at->toIso8601String()."\n");
    }

    public function test_sales_endpoints_reject_an_assigned_user_without_the_required_permissions(): void
    {
        $fixture = $this->salesFixture();
        $account = $this->openSalesAccount($fixture);
        $this->addSalesOrder($account, $fixture);
        $paymentId = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/accounts/'.$account['id'].'/payments', ['payments' => [['method' => 'qr', 'amount' => '10.00', 'reference' => 'QR-AUTH']]])->assertCreated()->json('data.0.id');
        $sale = Sale::factory()->create(['account_id' => $account['id'], 'cash_session_id' => $fixture['session']->id]);
        $user = User::factory()->create(['tenant_id' => $fixture['user']->tenant_id]);
        $user->branches()->attach($fixture['branch']->id, ['tenant_id' => $user->tenant_id]);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/tables')->assertForbidden();
        $this->getJson('/api/v1/sales/history')->assertForbidden();
        $this->getJson('/api/v1/sales/export')->assertForbidden();
        $this->getJson('/api/v1/accounts/'.$account['id'].'/audit')->assertForbidden();
        $this->postJson('/api/v1/accounts/'.$account['id'].'/payments', ['payments' => [['method' => 'cash', 'amount' => '10.00']]])->assertForbidden();
        $this->postJson('/api/v1/accounts/'.$account['id'].'/close')->assertForbidden();
        $this->patchJson('/api/v1/account-payments/'.$paymentId, ['status' => 'confirmed'])->assertForbidden();
        $this->postJson('/api/v1/sales/'.$sale->id.'/refunds', ['amount' => '5.00', 'reason' => 'Unauthorized', 'cash_session_id' => $fixture['session']->id])->assertForbidden();
    }

    public function test_history_date_filters_use_bolivia_day_boundaries_and_support_end_date_only(): void
    {
        $fixture = $this->salesFixture();
        $localPreviousDay = Sale::factory()->create(['tenant_id' => $fixture['user']->tenant_id,
            'branch_id' => $fixture['branch']->id, 'created_at' => '2026-10-21 02:00:00']);
        Sale::factory()->create(['tenant_id' => $fixture['user']->tenant_id,
            'branch_id' => $fixture['branch']->id, 'created_at' => '2026-10-21 04:00:00']);
        $this->getJson('/api/v1/sales/history?from=2026-10-20&to=2026-10-20')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $localPreviousDay->id);
        $this->getJson('/api/v1/sales/history?to=2026-10-20')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/sales/history?from=2026-10-21&to=2026-10-20')->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    public function test_area_and_table_management_validate_branch_access_and_uniqueness(): void
    {
        $fixture = $this->salesFixture();
        $areaId = $this->postJson('/api/v1/areas', ['branch_id' => $fixture['branch']->id, 'name' => 'Terraza'])
            ->assertCreated()->json('data.id');
        $tableId = $this->postJson('/api/v1/tables', ['area_id' => $areaId, 'number' => 'M1', 'capacity' => 6])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/tables', ['area_id' => $areaId, 'number' => 'M1'])->assertUnprocessable()->assertJsonValidationErrors('number');
        $this->patchJson('/api/v1/tables/'.$tableId, ['blocked' => true])->assertOk()->assertJsonPath('data.status', 'blocked');
        $this->assertDatabaseHas('dining_tables', ['id' => $tableId, 'blocked' => true, 'branch_id' => $fixture['branch']->id]);
        $this->getJson('/api/v1/areas?branch_id='.$fixture['branch']->id)->assertOk()->assertJsonCount(2, 'data');
    }
}
