<?php

namespace App\Modules\Cash\Actions;

use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashSession;
use App\Shared\Exceptions\DomainException;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Identity\Models\User;
use App\Shared\Support\Money;
use Illuminate\Support\Facades\DB;

class RecordManualCashMovement
{
    public function handle(
        CashSession $session,
        User $user,
        string $type,
        string $amount,
        string $reason,
        ?string $reference = null,
    ): CashMovement {
        if (Money::cmp($amount, '0.00') <= 0) {
            throw new DomainException(
                'The cash movement amount must be greater than zero.',
                'invalid_cash_movement_amount',
            );
        }

        return DB::transaction(function () use ($session, $user, $type, $amount, $reason, $reference) {
            $session = CashSession::query()
                ->whereKey($session->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($session->status !== 'open') {
                throw new InvalidStateTransitionException('The cash session is not open.');
            }

            $signedAmount = match ($type) {
                'cash_in' => $amount,
                'cash_out', 'expense' => Money::sub('0.00', $amount),
                default => throw new DomainException(
                    'The cash movement type is invalid.',
                    'invalid_cash_movement_type',
                ),
            };

            return CashMovement::query()->create([
                'tenant_id' => $session->tenant_id,
                'branch_id' => $session->branch_id,
                'cash_register_id' => $session->cash_register_id,
                'cash_session_id' => $session->id,
                'recorded_by' => $user->id,
                'type' => $type,
                'amount' => $signedAmount,
                'reason' => $reason,
                'reference' => $reference,
            ]);
        });
    }
}
