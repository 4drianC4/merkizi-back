<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Actions\RecordAccountPayments;
use App\Modules\Sales\Http\Requests\StoreAccountPaymentsRequest;
use App\Modules\Sales\Http\Requests\UpdateAccountPaymentRequest;
use App\Modules\Sales\Http\Resources\AccountPaymentResource;
use App\Modules\Sales\Models\AccountPayment;
use App\Modules\Sales\Models\TableAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountPaymentController extends Controller
{
    public function store(StoreAccountPaymentsRequest $request, TableAccount $account, RecordAccountPayments $record): JsonResponse
    {
        $payments = $record->handle($account, $request->user(), $request->validated());

        return response()->json(['data' => AccountPaymentResource::collection($payments)->resolve($request),
            'account' => ['id' => $account->id, 'status' => $account->fresh()->status, ...$account->fresh()->totals()]], 201);
    }

    public function show(Request $request, AccountPayment $accountPayment): AccountPaymentResource
    {
        abort_unless($request->user()->hasPermission('pos.venta.ver', $accountPayment->branch), 403);

        return new AccountPaymentResource($accountPayment);
    }

    public function update(UpdateAccountPaymentRequest $request, AccountPayment $accountPayment, RecordAccountPayments $record): AccountPaymentResource
    {
        return new AccountPaymentResource($record->resolve($accountPayment, $request->user(), $request->validated('status')));
    }
}
