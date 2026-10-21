<?php

namespace App\Modules\Sales\Actions;

use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SalesAudit;
use App\Modules\Sales\Models\TableAccount;
use App\Shared\Exceptions\DomainException;
use App\Shared\Identity\Models\User;
use App\Shared\Support\Money;
use App\Shared\Tenancy\Models\Branch;
use Illuminate\Support\Facades\DB;

class CloseAccount
{
    public function __construct(private readonly RecordAccountPayments $payments) {}

    public function handle(TableAccount $account, User $user, string $key, ?string $cashSessionId): Sale
    {
        return DB::transaction(function () use ($account, $user, $key, $cashSessionId): Sale {
            $account = TableAccount::query()->lockForUpdate()->findOrFail($account->id);
            $sale = $account->sale()->first();
            if ($sale !== null) {
                if ($sale->close_key !== $key || ($cashSessionId !== null && $sale->cash_session_id !== $cashSessionId)) {
                    throw new DomainException('The account was closed by another request.', 'idempotency_conflict', 409);
                }

                return $sale;
            }
            $account->assertOpen();
            $session = $this->payments->session($account, $cashSessionId ?? $account->cash_session_id, $user);
            $totals = $account->totals();
            if (Money::cmp($totals['balance'], '0') !== 0 || Money::cmp($totals['pending'], '0') !== 0 || $account->orders()->whereHas('items')->doesntExist()) {
                throw new DomainException('The account must contain orders and be fully paid with no pending payments.', 'account_unsettled', 409);
            }
            Branch::query()->lockForUpdate()->findOrFail($account->branch_id);
            $sale = Sale::query()->create([
                'tenant_id' => $account->tenant_id, 'branch_id' => $account->branch_id, 'account_id' => $account->id,
                'cash_session_id' => $session->id, 'cashier_id' => $user->id, 'number' => ((int) Sale::query()->where('branch_id', $account->branch_id)->max('number')) + 1,
                'service_type' => $account->service_type, 'customer_name' => $account->customer_name, 'customer_tax_id' => $account->customer_tax_id,
                'status' => 'completed', 'close_key' => $key, ...array_intersect_key($totals, array_flip(['subtotal', 'discount', 'tax', 'tip', 'total'])),
            ]);
            foreach ($account->orders()->with('items')->get() as $order) {
                foreach ($order->items as $item) {
                    $sale->items()->create(['order_item_id' => $item->id, ...$item->only(['name', 'quantity', 'price', 'subtotal', 'extras', 'notes'])]);
                }
                $order->update(['status' => 'paid', 'paid_at' => now()]);
            }
            $account->payments()->where('status', 'confirmed')->update(['sale_id' => $sale->id]);
            DB::table('account_tables')->where('account_id', $account->id)->whereNull('separated_at')->update(['separated_at' => now()]);
            $account->update(['status' => 'paid', 'closed_at' => now(), 'version' => $account->version + 1, 'cash_session_id' => $session->id]);
            SalesAudit::record($account, $user, 'account.closed', ['sale_id' => $sale->id, 'total' => $sale->total, 'cash_session_id' => $session->id]);

            return $sale;
        }, 3);
    }
}
