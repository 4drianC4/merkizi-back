<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Sales\Actions\CreateOrder;
use App\Modules\Sales\Actions\ManageAccountOrders;
use App\Modules\Sales\Http\Requests\StoreOrderRequest;
use App\Modules\Sales\Http\Requests\UpdateOrderItemsRequest;
use App\Modules\Sales\Http\Resources\OrderResource;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\TableAccount;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Order::class);

        return OrderResource::collection(
            $request->user()->restrictToBranches(Order::query())->with(['items', 'payments'])->latest()->get(),
        );
    }

    public function store(StoreOrderRequest $request, CreateOrder $createOrder, ManageAccountOrders $accountOrders): JsonResponse
    {
        if ($request->filled('account_id')) {
            $account = TableAccount::query()->findOrFail($request->validated('account_id'));
            abort_unless($request->user()->hasPermission('pos.venta.crear', $account->branch), 403);
            $order = $accountOrders->create($account, $request->user(), $request->validated());

            return (new OrderResource($order))->response()->setStatusCode(201);
        }
        $session = CashSession::query()->find($request->validated('cash_session_id'));

        if ($session === null) {
            throw new DomainException('Cash session not found.', 'cash_session_not_found');
        }

        abort_unless($request->user()->hasPermission('pos.venta.crear', $session->branch), 403);

        $order = $createOrder->handle($session->branch, $session, $request->validated());

        return (new OrderResource($order->load(['items', 'payments'])))->response()->setStatusCode(201);
    }

    public function show(Order $order): OrderResource
    {
        $this->authorize('view', $order);

        return new OrderResource($order->load(['items', 'payments']));
    }

    public function updateItems(UpdateOrderItemsRequest $request, Order $order, ManageAccountOrders $orders): OrderResource
    {
        if ($order->account_id === null) {
            throw new DomainException('Item editing requires an account-based order.', 'account_required', 409);
        }

        return new OrderResource($orders->update($order, $request->user(), $request->validated()));
    }
}
