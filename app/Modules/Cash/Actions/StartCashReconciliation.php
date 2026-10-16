<?php

namespace App\Modules\Cash\Actions;

use App\Modules\Cash\Models\CashSession;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Support\Money;
use Illuminate\Support\Facades\DB;

class StartCashReconciliation
{
    public function __construct(
        private readonly CalculateExpectedCashBalance $calculateExpectedCashBalance,
    ) {}

    public function handle(CashSession $session, string $countedCashBalance): CashSession
    {
        return DB::transaction(function () use ($session, $countedCashBalance) {
            $session = CashSession::query()
                ->whereKey($session->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($session->status !== 'open') {
                throw new InvalidStateTransitionException('The cash session is not open.');
            }

            $expectedCashBalance = $this->calculateExpectedCashBalance->handle($session);

            $session->update([
                'status' => 'reconciliation_pending',
                'expected_cash_balance' => $expectedCashBalance,
                'counted_cash_balance' => $countedCashBalance,
                'cash_difference' => Money::sub($countedCashBalance, $expectedCashBalance),
            ]);

            return $session->refresh();
        });
    }
}
