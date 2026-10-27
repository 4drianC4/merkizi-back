<?php

namespace Tests\Feature\Concerns;

use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Catalog\Models\Product;
use App\Modules\Sales\Models\DiningArea;
use App\Modules\Sales\Models\DiningTable;
use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Models\Branch;
use Laravel\Sanctum\Sanctum;

trait InteractsWithSales
{
    /** @return array{user: User, branch: Branch, table: DiningTable, product: Product, session: CashSession} */
    private function salesFixture(): array
    {
        $user = User::factory()->admin()->create();
        $branch = Branch::factory()->create(['tenant_id' => $user->tenant_id]);
        $user->branches()->attach($branch->id, ['tenant_id' => $user->tenant_id]);
        $area = DiningArea::factory()->create(['branch_id' => $branch->id]);
        $table = DiningTable::factory()->create(['area_id' => $area->id]);
        $product = Product::factory()->create(['tenant_id' => $user->tenant_id, 'price' => '10.00', 'status' => 'active']);
        $register = CashRegister::factory()->create(['tenant_id' => $user->tenant_id, 'branch_id' => $branch->id]);
        $session = CashSession::factory()->create(['tenant_id' => $user->tenant_id, 'branch_id' => $branch->id, 'cash_register_id' => $register->id, 'opened_by' => $user->id]);
        Sanctum::actingAs($user);

        return compact('user', 'branch', 'table', 'product', 'session');
    }

    /** @param array{branch: Branch, table: DiningTable, session: CashSession, user: User, product: Product} $fixture
     * @return array<string, mixed>
     */
    private function openSalesAccount(array $fixture, ?DiningTable $table = null, bool $takeaway = false): array
    {
        $data = ['branch_id' => $fixture['branch']->id, 'service_type' => $takeaway ? 'takeaway' : 'table', 'cash_session_id' => $fixture['session']->id, 'customer_name' => 'Cliente', 'customer_tax_id' => '123456'];
        if (! $takeaway) {
            $data['table_id'] = ($table ?? $fixture['table'])->id;
        }

        return $this->postJson('/api/v1/accounts', $data)->assertCreated()->json('data');
    }

    /** @param array<string, mixed> $account
     * @param  array{product: Product}  $fixture
     * @return array<string, mixed>
     */
    private function addSalesOrder(array $account, array $fixture, string $quantity = '1.000'): array
    {
        return $this->postJson('/api/v1/orders', ['account_id' => $account['id'], 'items' => [['product_id' => $fixture['product']->id, 'quantity' => $quantity]]])->assertCreated()->json('data');
    }
}
