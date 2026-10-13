<?php

namespace App\Modules\Sales\Actions;

use App\Modules\Sales\Models\Order;

class ApplyPaymentToOrder
{
    /**
     * Reconcile the order status after a payment has been recorded.
     */
    public function handle(Order $order): void
    {
        if ($order->markPaidIfCovered()) {
            $order->save();
        }
    }
}
