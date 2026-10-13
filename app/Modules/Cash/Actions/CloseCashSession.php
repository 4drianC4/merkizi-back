<?php

namespace App\Modules\Cash\Actions;

use App\Modules\Cash\Models\CashSession;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Support\Money;
use Illuminate\Support\Facades\DB;

class CloseCashSession
{
    public function handle(CashSession $session): CashSession
    {
        return DB::transaction(function () use ($session) {
            $session->lockForUpdate();

            if ($session->status !== 'open') {
                throw new InvalidStateTransitionException('The cash session is already closed.');
            }

            $movementsTotal = '0.00';

            foreach ($session->movements as $movement) {
                $movementsTotal = Money::add($movementsTotal, (string) $movement->amount);
            }

            $session->update([
                'status' => 'closed',
                'closed_at' => now(),
                'closing_balance' => Money::add((string) $session->opening_balance, $movementsTotal),
            ]);

            return $session;
        });
    }
}
