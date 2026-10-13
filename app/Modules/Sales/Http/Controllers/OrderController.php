<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Sales\Actions\CreateOrder;
use App\Modules\Sales\Http\Requests\StoreOrderRequest;
use App\Modules\Sales\Http\Resources\OrderResource;
use App\Modules\Sales\Models\Order;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Order::class);

        return OrderResource::collection(
            Order::query()->with(['items', 'payments'])->latest()->get(),
        );
    }

    public function store(StoreOrderRequest $request, CreateOrder $createOrder): JsonResponse
    {
        $session = CashSession::query()->find($request->validated('cash_session_id'));

        if ($session === null) {
            throw new DomainException('Cash session not found.', 'cash_session_not_found');
        }

        $order = $createOrder->handle($session->branch, $session, $request->validated());

        return (new OrderResource($order->load(['items', 'payments'])))->response()->setStatusCode(201);
    }

    public function show(Order $order): OrderResource
    {
        $this->authorize('view', $order);

        return new OrderResource($order->load(['items', 'payments']));
    }
}
