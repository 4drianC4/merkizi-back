<?php

namespace App\Modules\Sales\Actions;

use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Sales\Models\AccountPayment;
use App\Modules\Sales\Models\SalesAudit;
use App\Modules\Sales\Models\TableAccount;
use App\Shared\Exceptions\DomainException;
use App\Shared\Identity\Models\User;
use App\Shared\Support\IdempotencyFingerprint;
use App\Shared\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecordAccountPayments
{
    /** @param array<string, mixed> $data
     * @return Collection<int, AccountPayment>
     */
    public function handle(TableAccount $account, User $user, array $data): Collection
    {
        return DB::transaction(function () use ($account, $user, $data): Collection {
            $account = TableAccount::query()->lockForUpdate()->findOrFail($account->id);
            $hash = IdempotencyFingerprint::make($data);
            $existing = AccountPayment::query()->where('origin_account_id', $account->id)->where('idempotency_key', $data['idempotency_key'])->orderBy('batch_index')->get();
            if ($existing->isNotEmpty()) {
                if ($existing->first()->request_hash !== $hash) {
                    throw new DomainException('This idempotency key belongs to a different request.', 'idempotency_conflict', 409);
                }

                return $existing;
            }
            $account->assertOpen();
            $session = $this->session($account, $data['cash_session_id'] ?? $account->cash_session_id, $user);
            $tip = (string) ($data['tip'] ?? '0.00');
            $account->tip = Money::add((string) $account->tip, $tip);
            $totals = $account->totals();
            $available = Money::sub($totals['balance'], $totals['pending']);
            $sum = '0.00';
            foreach ($data['payments'] as $line) {
                $sum = Money::add($sum, (string) $line['amount']);
            }
            if (Money::cmp($sum, $available) > 0) {
                throw new DomainException('Payments exceed the unreserved balance. Use received_amount for cash tendered.', 'payment_exceeds_balance', 409);
            }
            $payments = collect();
            foreach ($data['payments'] as $index => $line) {
                $amount = (string) $line['amount'];
                $received = $line['method'] === 'cash' ? (string) ($line['received_amount'] ?? $amount) : null;
                if ($received !== null && Money::cmp($received, $amount) < 0) {
                    throw new DomainException('Cash received cannot be less than the applied amount.', 'insufficient_cash');
                }
                $payment = AccountPayment::query()->create([
                    'tenant_id' => $account->tenant_id, 'branch_id' => $account->branch_id, 'account_id' => $account->id, 'origin_account_id' => $account->id,
                    'cash_session_id' => $session->id, 'user_id' => $user->id, 'method' => $line['method'], 'amount' => $amount,
                    'received_amount' => $received, 'change' => $received === null ? '0.00' : Money::sub($received, $amount),
                    'status' => $line['method'] === 'cash' ? 'confirmed' : 'pending', 'reference' => $line['reference'] ?? null,
                    'confirmed_at' => $line['method'] === 'cash' ? now() : null, 'idempotency_key' => $data['idempotency_key'],
                    'batch_index' => $index, 'request_hash' => $hash,
                ]);
                if ($payment->method === 'cash') {
                    $this->cashMovement($payment, $session);
                }
                $payments->push($payment);
            }
            $account->cash_session_id = $session->id;
            $account->version++;
            $account->save();
            SalesAudit::record($account, $user, 'payments.recorded', ['payment_ids' => $payments->pluck('id')->all(), 'tip_added' => $tip]);

            return $payments;
        }, 3);
    }

    public function resolve(AccountPayment $payment, User $user, string $status): AccountPayment
    {
        return DB::transaction(function () use ($payment, $user, $status): AccountPayment {
            $account = TableAccount::query()->lockForUpdate()->findOrFail($payment->account_id);
            $payment = AccountPayment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status === $status) {
                return $payment;
            }
            $account->assertOpen();
            if ($payment->status !== 'pending') {
                throw new DomainException('Only pending payments may be resolved.', 'payment_not_pending', 409);
            }
            $this->session($account, $payment->cash_session_id, $user);
            $payment->update(['status' => $status, 'resolved_by' => $user->id, 'confirmed_at' => $status === 'confirmed' ? now() : null]);
            $account->increment('version');
            SalesAudit::record($account, $user, 'payment.'.$status, ['payment_id' => $payment->id, 'amount' => $payment->amount]);

            return $payment;
        }, 3);
    }

    public function session(TableAccount $account, ?string $sessionId, User $user): CashSession
    {
        $session = CashSession::query()->lockForUpdate()->find($sessionId);
        if ($session === null || $session->status !== 'open' || $session->tenant_id !== $account->tenant_id || $session->branch_id !== $account->branch_id) {
            throw new DomainException('An open cash session in the account branch is required.', 'cash_session_required', 409);
        }

        abort_unless($session->opened_by === $user->id || $user->hasPermission('caja.cajas.editar', $account->branch), 403);

        return $session;
    }

    private function cashMovement(AccountPayment $payment, CashSession $session): void
    {
        CashMovement::query()->create(['tenant_id' => $payment->tenant_id, 'branch_id' => $payment->branch_id,
            'cash_register_id' => $session->cash_register_id, 'cash_session_id' => $session->id,
            'type' => 'sale', 'amount' => $payment->amount, 'reference' => $payment->id]);
    }
}
