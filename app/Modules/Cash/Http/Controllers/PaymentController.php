<?php

namespace App\Modules\Cash\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cash\Actions\RecordPayment;
use App\Modules\Cash\Http\Requests\StorePaymentRequest;
use App\Modules\Cash\Http\Resources\PaymentResource;
use App\Modules\Sales\Models\Order;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function store(StorePaymentRequest $request, Order $order, RecordPayment $recordPayment): JsonResponse
    {
        $payment = $recordPayment->handle(
            $order,
            $request->user(),
            $request->validated('method'),
            (string) $request->validated('amount'),
            $request->validated('reference'),
        );

        return (new PaymentResource($payment))->response()->setStatusCode(201);
    }
}
