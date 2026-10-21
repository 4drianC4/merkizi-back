<?php

namespace Tests\Feature;

use App\Modules\Cash\Models\CashSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\InteractsWithSales;
use Tests\TestCase;

class SalesPaymentsTest extends TestCase
{
    use InteractsWithSales;
    use RefreshDatabase;

    public function test_mixed_payments_wait_for_external_confirmation_and_count_only_cash_in_drawer(): void
    {
        $fixture = $this->salesFixture();
        $account = $this->openSalesAccount($fixture);
        $this->addSalesOrder($account, $fixture, '2.000');
        $payment = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/accounts/'.$account['id'].'/payments', ['tip' => '2.00', 'payments' => [
            ['method' => 'cash', 'amount' => '10.00', 'received_amount' => '20.00'], ['method' => 'qr', 'amount' => '12.00', 'reference' => 'QR-123'],
        ]])->assertCreated()->assertJsonPath('account.total', '22.00')->assertJsonPath('account.paid', '10.00')->assertJsonPath('account.pending', '12.00')->assertJsonPath('data.0.change', '10.00')->json('data.1.id');
        $this->assertDatabaseCount('cash_movements', 1);
        $this->assertDatabaseHas('cash_movements', ['cash_session_id' => $fixture['session']->id, 'amount' => '10.00']);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/accounts/'.$account['id'].'/close')->assertConflict()->assertJsonPath('code', 'account_unsettled');
        $this->postJson('/api/v1/cash-sessions/'.$fixture['session']->id.'/close')->assertConflict();
        $this->patchJson('/api/v1/account-payments/'.$payment, ['status' => 'confirmed'])->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->patchJson('/api/v1/account-payments/'.$payment, ['status' => 'confirmed'])->assertOk();
        $this->assertDatabaseCount('cash_movements', 1);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/accounts/'.$account['id'].'/close')->assertCreated()->assertJsonPath('data.total', '22.00');
        $this->postJson('/api/v1/cash-sessions/'.$fixture['session']->id.'/close')->assertOk()->assertJsonPath('data.closing_balance', '10.00');
    }

    public function test_idempotency_prevents_duplicate_payments_tips_and_closures(): void
    {
        $fixture = $this->salesFixture();
        $account = $this->openSalesAccount($fixture);
        $this->addSalesOrder($account, $fixture);
        $key = (string) Str::uuid();
        $data = ['tip' => '1.00', 'payments' => [['method' => 'cash', 'amount' => '11.00']]];
        $paymentId = $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/accounts/'.$account['id'].'/payments', $data)->assertCreated()->json('data.0.id');
        $this->postJson('/api/v1/accounts/'.$account['id'].'/payments', ['payments' => [['amount' => '11.00', 'method' => 'cash']], 'tip' => '1.00'])->assertCreated()->assertJsonPath('data.0.id', $paymentId)->assertJsonPath('account.tip', '1.00');
        $this->postJson('/api/v1/accounts/'.$account['id'].'/payments', ['payments' => [['method' => 'cash', 'amount' => '10.00']]])->assertConflict()->assertJsonPath('code', 'idempotency_conflict');
        $closeKey = (string) Str::uuid();
        $saleId = $this->withHeader('Idempotency-Key', $closeKey)->postJson('/api/v1/accounts/'.$account['id'].'/close')->assertCreated()->json('data.id');
        $this->postJson('/api/v1/accounts/'.$account['id'].'/close')->assertOk()->assertJsonPath('data.id', $saleId);
        $this->assertDatabaseCount('account_payments', 1);
        $this->assertDatabaseCount('cash_movements', 1);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_items', 1);
    }

    public function test_excess_payments_insufficient_cash_and_missing_idempotency_key_are_rejected_atomically(): void
    {
        $fixture = $this->salesFixture();
        $account = $this->openSalesAccount($fixture);
        $this->addSalesOrder($account, $fixture);
        $this->postJson('/api/v1/accounts/'.$account['id'].'/payments', ['payments' => [['method' => 'cash', 'amount' => '10.00']]])->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/accounts/'.$account['id'].'/payments', ['payments' => [['method' => 'cash', 'amount' => '11.00']]])->assertConflict()->assertJsonPath('code', 'payment_exceeds_balance');
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/accounts/'.$account['id'].'/payments', ['payments' => [['method' => 'cash', 'amount' => '10.00', 'received_amount' => '5.00']]])->assertUnprocessable()->assertJsonPath('code', 'insufficient_cash');
        $this->assertDatabaseCount('account_payments', 0);
        $this->assertDatabaseCount('cash_movements', 0);
    }

    public function test_rejected_and_expired_payments_release_reservations_without_creating_cash_movements(): void
    {
        $fixture = $this->salesFixture();
        $account = $this->openSalesAccount($fixture);
        $this->addSalesOrder($account, $fixture);
        $paymentId = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/accounts/'.$account['id'].'/payments', ['payments' => [['method' => 'card', 'amount' => '10.00', 'reference' => 'CARD-1']]])->assertCreated()->json('data.0.id');
        $this->patchJson('/api/v1/account-payments/'.$paymentId, ['status' => 'expired'])->assertOk();
        $this->patchJson('/api/v1/account-payments/'.$paymentId, ['status' => 'confirmed'])->assertConflict();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/accounts/'.$account['id'].'/payments', ['payments' => [['method' => 'cash', 'amount' => '10.00']]])->assertCreated()->assertJsonPath('account.balance', '0.00');
        $this->assertDatabaseCount('cash_movements', 1);
    }

    public function test_closed_or_foreign_cash_sessions_cannot_be_used_for_payments(): void
    {
        $fixture = $this->salesFixture();
        $account = $this->openSalesAccount($fixture);
        $this->addSalesOrder($account, $fixture);
        $foreign = CashSession::factory()->create();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/accounts/'.$account['id'].'/payments', ['cash_session_id' => $foreign->id, 'payments' => [['method' => 'cash', 'amount' => '10.00']]])->assertConflict();
        $fixture['session']->update(['status' => 'closed']);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/accounts/'.$account['id'].'/payments', ['payments' => [['method' => 'cash', 'amount' => '10.00']]])->assertConflict();
        $this->assertDatabaseCount('account_payments', 0);
    }

    public function test_legacy_order_payment_endpoint_cannot_bypass_account_rules(): void
    {
        $fixture = $this->salesFixture();
        $account = $this->openSalesAccount($fixture);
        $order = $this->addSalesOrder($account, $fixture);
        $this->postJson('/api/v1/orders/'.$order['id'].'/payments', ['method' => 'cash', 'amount' => '10.00'])->assertConflict();
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('account_payments', 0);
    }

    public function test_refunds_preserve_original_sale_and_are_bounded_audited_and_idempotent(): void
    {
        $fixture = $this->salesFixture();
        $account = $this->openSalesAccount($fixture, takeaway: true);
        $this->addSalesOrder($account, $fixture);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/accounts/'.$account['id'].'/payments', ['payments' => [['method' => 'cash', 'amount' => '10.00']]])->assertCreated();
        $sale = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/accounts/'.$account['id'].'/close')->assertCreated()->json('data');
        $key = (string) Str::uuid();
        $data = ['cash_session_id' => $fixture['session']->id, 'amount' => '4.00', 'reason' => 'Producto devuelto'];
        $refundId = $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/sales/'.$sale['id'].'/refunds', $data)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/sales/'.$sale['id'].'/refunds', $data)->assertCreated()->assertJsonPath('data.id', $refundId);
        $this->getJson('/api/v1/sales/'.$sale['id'])->assertOk()->assertJsonPath('data.total', '10.00')->assertJsonPath('data.net_total', '6.00')->assertJsonPath('data.status', 'partially_refunded');
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/sales/'.$sale['id'].'/refunds', [...$data, 'amount' => '7.00'])->assertConflict();
        $this->assertDatabaseCount('sale_refunds', 1);
        $this->assertDatabaseHas('cash_movements', ['type' => 'refund', 'amount' => '-4.00', 'reference' => $refundId]);
        $this->assertDatabaseHas('sales_audits', ['account_id' => $account['id'], 'action' => 'sale.refunded', 'user_id' => $fixture['user']->id]);
    }

    public function test_payment_validation_rejects_missing_external_reference_and_client_controlled_status(): void
    {
        $fixture = $this->salesFixture();
        $account = $this->openSalesAccount($fixture);
        $this->addSalesOrder($account, $fixture);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/accounts/'.$account['id'].'/payments', ['payments' => [['method' => 'qr', 'amount' => '10.00']]])
            ->assertUnprocessable()->assertJsonValidationErrors('payments.0.reference');
        $this->postJson('/api/v1/accounts/'.$account['id'].'/payments', ['payments' => [['method' => 'card', 'amount' => '10.00', 'reference' => 'CARD-X', 'status' => 'confirmed']]])
            ->assertUnprocessable()->assertJsonValidationErrors('payments.0');
        $this->postJson('/api/v1/accounts/'.$account['id'].'/payments', ['payments' => [['method' => 'card', 'amount' => '10.00', 'reference' => 'CARD-X', 'received_amount' => '10.00']]])
            ->assertUnprocessable()->assertJsonValidationErrors('payments.0.received_amount');
        $this->assertDatabaseCount('account_payments', 0);
    }

    public function test_pending_amounts_reserve_balance_and_rejection_releases_it(): void
    {
        $fixture = $this->salesFixture();
        $account = $this->openSalesAccount($fixture);
        $this->addSalesOrder($account, $fixture);
        $paymentId = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/accounts/'.$account['id'].'/payments', ['payments' => [['method' => 'qr', 'amount' => '10.00', 'reference' => 'QR-RESERVED']]])->assertCreated()->json('data.0.id');
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/accounts/'.$account['id'].'/payments', ['payments' => [['method' => 'cash', 'amount' => '10.00']]])->assertConflict();
        $this->patchJson('/api/v1/account-payments/'.$paymentId, ['status' => 'rejected'])->assertOk();
        $this->postJson('/api/v1/accounts/'.$account['id'].'/payments', ['payments' => [['method' => 'cash', 'amount' => '10.00']]])->assertCreated()->assertJsonPath('account.balance', '0.00');
        $this->assertDatabaseCount('cash_movements', 1);
    }

    public function test_account_cannot_close_without_items_or_before_being_paid(): void
    {
        $fixture = $this->salesFixture();
        $account = $this->openSalesAccount($fixture);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/accounts/'.$account['id'].'/close')->assertConflict();
        $order = $this->addSalesOrder($account, $fixture);
        $this->postJson('/api/v1/accounts/'.$account['id'].'/close')->assertConflict();
        $this->patchJson('/api/v1/orders/'.$order['id'].'/items', ['version' => 1, 'items' => []])->assertOk();
        $this->postJson('/api/v1/accounts/'.$account['id'].'/close')->assertConflict();
        $this->assertDatabaseCount('sales', 0);
    }
}
