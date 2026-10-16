<?php

namespace App\Modules\Cash\Actions;

use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Cash\Models\Payment;
use Illuminate\Support\Collection;

class GetCashSessionActivity
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function handle(CashSession $session): Collection
    {
        $payments = Payment::query()
            ->whereHas('order', fn ($query) => $query->where('cash_session_id', $session->id))
            ->get(['id', 'order_id', 'method', 'amount', 'status', 'reference', 'created_at'])
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'category' => 'payment',
                'type' => $payment->method,
                'description' => 'Order payment',
                'amount' => $payment->amount,
                'cash_effect' => $payment->method === 'cash' ? $payment->amount : '0.00',
                'status' => $payment->status,
                'reference' => $payment->reference,
                'recorded_by' => null,
                'occurred_at' => $payment->created_at,
            ]);

        $manualMovements = CashMovement::query()
            ->where('cash_session_id', $session->id)
            ->whereIn('type', ['cash_in', 'cash_out', 'expense'])
            ->get(['id', 'recorded_by', 'type', 'amount', 'reason', 'reference', 'created_at'])
            ->map(fn (CashMovement $movement) => [
                'id' => $movement->id,
                'category' => 'cash_movement',
                'type' => $movement->type,
                'description' => $movement->reason,
                'amount' => $movement->amount,
                'cash_effect' => $movement->amount,
                'status' => null,
                'reference' => $movement->reference,
                'recorded_by' => $movement->recorded_by,
                'occurred_at' => $movement->created_at,
            ]);

        return $payments
            ->merge($manualMovements)
            ->sortByDesc(fn (array $activity) => $activity['occurred_at']->getTimestamp())
            ->values();
    }
}
