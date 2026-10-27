<?php

namespace Tests\Feature;

use App\Modules\Sales\Models\DiningTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\InteractsWithSales;
use Tests\TestCase;

class SalesTransfersTest extends TestCase
{
    use InteractsWithSales;
    use RefreshDatabase;

    public function test_merge_preserves_order_and_payment_ids_and_keeps_both_tables_in_destination_account(): void
    {
        $fixture = $this->salesFixture();
        $source = $this->openSalesAccount($fixture);
        $otherTable = DiningTable::factory()->create(['area_id' => $fixture['table']->area_id]);
        $target = $this->openSalesAccount($fixture, $otherTable);
        $sourceOrder = $this->addSalesOrder($source, $fixture);
        $targetOrder = $this->addSalesOrder($target, $fixture, '2.000');
        $paymentId = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/accounts/'.$source['id'].'/payments', ['payments' => [['method' => 'cash', 'amount' => '5.00']]])->assertCreated()->json('data.0.id');
        $source = $this->getJson('/api/v1/accounts/'.$source['id'])->assertOk()->json('data');
        $target = $this->getJson('/api/v1/accounts/'.$target['id'])->assertOk()->json('data');
        $this->postJson('/api/v1/tables/merge', ['source_account_id' => $source['id'], 'destination_account_id' => $target['id'], 'source_version' => $source['version'], 'destination_version' => $target['version']])
            ->assertOk()->assertJsonPath('data.total', '30.00')->assertJsonPath('data.paid', '5.00')->assertJsonPath('data.balance', '25.00')->assertJsonCount(2, 'data.tables')->assertJsonCount(2, 'data.orders');
        $this->assertDatabaseCount('orders', 2);
        $this->assertDatabaseCount('account_tables', 3);
        $this->assertDatabaseHas('account_tables', ['account_id' => $source['id'], 'table_id' => $fixture['table']->id]);
        $this->assertDatabaseCount('account_payments', 1);
        $this->assertDatabaseCount('cash_movements', 1);
        $this->assertDatabaseHas('account_payments', ['id' => $paymentId, 'account_id' => $target['id'], 'origin_account_id' => $source['id']]);
        $this->assertDatabaseHas('orders', ['id' => $sourceOrder['id'], 'account_id' => $target['id']]);
        $this->assertDatabaseHas('table_accounts', ['id' => $source['id'], 'status' => 'merged', 'destination_account_id' => $target['id']]);
        $this->assertDatabaseHas('sales_audits', ['account_id' => $source['id'], 'action' => 'tables.merged']);
    }

    public function test_partial_transfer_prorates_adjustments_and_preserves_total_consumption(): void
    {
        $fixture = $this->salesFixture();
        $source = $this->openSalesAccount($fixture);
        $otherTable = DiningTable::factory()->create(['area_id' => $fixture['table']->area_id]);
        $target = $this->openSalesAccount($fixture, $otherTable);
        $order = $this->postJson('/api/v1/orders', ['account_id' => $source['id'], 'discount' => '2.00', 'tax' => '1.00', 'items' => [['product_id' => $fixture['product']->id, 'quantity' => '2.000']]])->assertCreated()->json('data');
        $source = $this->getJson('/api/v1/accounts/'.$source['id'])->assertOk()->json('data');
        $this->postJson('/api/v1/tables/transfer-items', ['source_account_id' => $source['id'], 'destination_account_id' => $target['id'], 'source_version' => $source['version'], 'destination_version' => 1, 'items' => [['id' => $order['items'][0]['id'], 'quantity' => '1.000']]])
            ->assertOk()->assertJsonPath('data.total', '9.50')->assertJsonPath('data.discount', '1.00')->assertJsonPath('data.tax', '0.50');
        $this->getJson('/api/v1/accounts/'.$source['id'])->assertOk()->assertJsonPath('data.total', '9.50')->assertJsonPath('data.orders.0.items.0.quantity', '1.000');
        $this->assertDatabaseHas('sales_audits', ['account_id' => $target['id'], 'action' => 'items.transferred']);
    }

    public function test_full_transfer_releases_empty_source_table_and_rejects_stale_retry(): void
    {
        $fixture = $this->salesFixture();
        $source = $this->openSalesAccount($fixture);
        $target = $this->openSalesAccount($fixture, DiningTable::factory()->create(['area_id' => $fixture['table']->area_id]));
        $this->addSalesOrder($source, $fixture);
        $source = $this->getJson('/api/v1/accounts/'.$source['id'])->assertOk()->json('data');
        $data = ['source_account_id' => $source['id'], 'destination_account_id' => $target['id'], 'source_version' => $source['version'], 'destination_version' => 1, 'transfer_all' => true];
        $this->postJson('/api/v1/tables/transfer-items', $data)->assertOk()->assertJsonPath('data.total', '10.00');
        $this->getJson('/api/v1/tables?status=free')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $fixture['table']->id);
        $this->postJson('/api/v1/tables/transfer-items', $data)->assertConflict();
        $this->assertDatabaseHas('table_accounts', ['id' => $source['id'], 'status' => 'cancelled']);
    }

    public function test_transfer_cannot_leave_prior_payments_without_sufficient_consumption(): void
    {
        $fixture = $this->salesFixture();
        $source = $this->openSalesAccount($fixture);
        $target = $this->openSalesAccount($fixture, DiningTable::factory()->create(['area_id' => $fixture['table']->area_id]));
        $this->addSalesOrder($source, $fixture);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/accounts/'.$source['id'].'/payments', ['payments' => [['method' => 'cash', 'amount' => '5.00']]])->assertCreated();
        $source = $this->getJson('/api/v1/accounts/'.$source['id'])->assertOk()->json('data');
        $this->postJson('/api/v1/tables/transfer-items', ['source_account_id' => $source['id'], 'destination_account_id' => $target['id'], 'source_version' => $source['version'], 'destination_version' => 1, 'transfer_all' => true])
            ->assertConflict()->assertJsonPath('code', 'payments_exceed_total');
        $this->assertDatabaseCount('orders', 1);
        $this->getJson('/api/v1/accounts/'.$source['id'])->assertOk()->assertJsonPath('data.total', '10.00')->assertJsonPath('data.paid', '5.00');
    }

    public function test_transfer_rejects_invalid_quantities_and_duplicate_item_ids_without_mutation(): void
    {
        $fixture = $this->salesFixture();
        $source = $this->openSalesAccount($fixture);
        $target = $this->openSalesAccount($fixture, DiningTable::factory()->create(['area_id' => $fixture['table']->area_id]));
        $order = $this->addSalesOrder($source, $fixture);
        $data = ['source_account_id' => $source['id'], 'destination_account_id' => $target['id'], 'source_version' => 2, 'destination_version' => 1,
            'items' => [['id' => $order['items'][0]['id'], 'quantity' => '2.000']]];
        $this->postJson('/api/v1/tables/transfer-items', $data)->assertUnprocessable()->assertJsonPath('code', 'invalid_transfer_item');
        $data['items'] = [['id' => $order['items'][0]['id'], 'quantity' => '1.000'], ['id' => $order['items'][0]['id'], 'quantity' => '1.000']];
        $this->postJson('/api/v1/tables/transfer-items', $data)->assertUnprocessable()->assertJsonValidationErrors(['items.0.id', 'items.1.id']);
        $this->assertDatabaseCount('orders', 1);
    }
}
