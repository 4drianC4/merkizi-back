<?php

namespace App\Modules\Cash\Actions;

use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\Payment;
use App\Modules\Sales\Actions\ApplyPaymentToOrder;
use App\Modules\Sales\Models\Order;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Support\Facades\DB;

class RecordPayment
{
    public function handle(Order $order, string $method, string $amount, ?string $reference = null): Payment
    {
        return DB::transaction(function () use ($order, $method, $amount, $reference) {
            $session = $order->session;

            if ($session === null || $session->status !== 'open') {
                throw new InvalidStateTransitionException('The order has no open cash session.');
            }

            $isPending = $method === 'qr';

            $payment = Payment::query()->create([
                'tenant_id' => $order->tenant_id,
                'branch_id' => $order->branch_id,
                'order_id' => $order->id,
                'method' => $method,
                'amount' => $amount,
                'status' => $isPending ? 'pending' : 'confirmed',
                'reference' => $reference,
                'confirmed_at' => $isPending ? null : now(),
            ]);

            if ($method === 'cash') {
                CashMovement::query()->create([
                    'tenant_id' => $order->tenant_id,
                    'branch_id' => $order->branch_id,
                    'cash_register_id' => $session->cash_register_id,
                    'cash_session_id' => $session->id,
                    'type' => 'sale',
                    'amount' => $amount,
                    'reference' => $reference,
                ]);
            }

            app(ApplyPaymentToOrder::class)->handle($order);

            return $payment;
        });
    }
}
