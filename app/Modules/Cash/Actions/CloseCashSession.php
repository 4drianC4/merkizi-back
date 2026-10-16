<?php

namespace App\Modules\Cash\Actions;

use App\Modules\Cash\Models\CashSession;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloseCashSession
{
    public function handle(CashSession $session, ?string $differenceNote = null): CashSession
    {
        return DB::transaction(function () use ($session, $differenceNote) {
            $session = CashSession::query()
                ->whereKey($session->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($session->status !== 'reconciliation_pending') {
                throw new InvalidStateTransitionException('The cash session is not pending reconciliation.');
            }

            if (Money::cmp((string) $session->cash_difference, '0.00') !== 0 && blank($differenceNote)) {
                throw ValidationException::withMessages([
                    'difference_note' => 'A note is required when the counted cash differs from the expected cash.',
                ]);
            }

            $session->update([
                'status' => 'closed',
                'closed_at' => now(),
                'difference_note' => $differenceNote,
            ]);

            return $session->refresh();
        });
    }
}
