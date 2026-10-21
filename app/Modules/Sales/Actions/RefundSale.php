<?php

namespace App\Modules\Sales\Actions;

use App\Modules\Cash\Models\CashMovement;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleRefund;
use App\Modules\Sales\Models\SalesAudit;
use App\Shared\Exceptions\DomainException;
use App\Shared\Identity\Models\User;
use App\Shared\Support\IdempotencyFingerprint;
use App\Shared\Support\Money;
use Illuminate\Support\Facades\DB;

class RefundSale
{
    public function __construct(private readonly RecordAccountPayments $payments) {}

    /** @param array<string, mixed> $data */
    public function handle(Sale $sale, User $user, array $data): SaleRefund
    {
        return DB::transaction(function () use ($sale, $user, $data): SaleRefund {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            $hash = IdempotencyFingerprint::make($data);
            $existing = $sale->refunds()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing !== null) {
                if ($existing->request_hash !== $hash) {
                    throw new DomainException('This key belongs to another refund.', 'idempotency_conflict', 409);
                }

                return $existing;
            }
            $session = $this->payments->session($sale->account, $data['cash_session_id'], $user);
            $refunded = '0.00';
            foreach ($sale->refunds()->get() as $refund) {
                $refunded = Money::add($refunded, (string) $refund->amount);
            }
            $amount = (string) $data['amount'];
            if (Money::cmp(Money::add($refunded, $amount), (string) $sale->total) > 0) {
                throw new DomainException('The refund exceeds the remaining refundable amount.', 'refund_exceeds_total', 409);
            }
            $refund = $sale->refunds()->create(['tenant_id' => $sale->tenant_id, 'branch_id' => $sale->branch_id,
                'cash_session_id' => $session->id, 'user_id' => $user->id, 'amount' => $amount, 'method' => 'cash',
                'reason' => $data['reason'], 'idempotency_key' => $data['idempotency_key'], 'request_hash' => $hash]);
            CashMovement::query()->create(['tenant_id' => $sale->tenant_id, 'branch_id' => $sale->branch_id,
                'cash_register_id' => $session->cash_register_id, 'cash_session_id' => $session->id,
                'type' => 'refund', 'amount' => Money::sub('0.00', $amount), 'reason' => $data['reason'], 'reference' => $refund->id]);
            $sale->update(['status' => Money::cmp(Money::add($refunded, $amount), (string) $sale->total) === 0 ? 'refunded' : 'partially_refunded']);
            SalesAudit::record($sale->account, $user, 'sale.refunded', ['sale_id' => $sale->id, 'refund_id' => $refund->id, 'amount' => $amount, 'reason' => $data['reason']]);

            return $refund;
        }, 3);
    }
}
