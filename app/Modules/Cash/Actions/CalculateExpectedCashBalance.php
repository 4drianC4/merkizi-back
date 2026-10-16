<?php

namespace App\Modules\Cash\Actions;

use App\Modules\Cash\Models\CashSession;
use App\Shared\Support\Money;

class CalculateExpectedCashBalance
{
    public function handle(CashSession $session): string
    {
        $movementsTotal = '0.00';

        foreach ($session->movements()->get(['amount']) as $movement) {
            $movementsTotal = Money::add($movementsTotal, (string) $movement->amount);
        }

        return Money::add((string) $session->opening_balance, $movementsTotal);
    }
}
