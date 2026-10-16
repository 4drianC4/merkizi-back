<?php

namespace App\Modules\Cash\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cash\Actions\RecordManualCashMovement;
use App\Modules\Cash\Http\Requests\StoreCashMovementRequest;
use App\Modules\Cash\Http\Resources\CashMovementResource;
use App\Modules\Cash\Models\CashSession;
use Illuminate\Http\JsonResponse;

class CashMovementController extends Controller
{
    public function store(
        StoreCashMovementRequest $request,
        CashSession $cashSession,
        RecordManualCashMovement $recordManualCashMovement,
    ): JsonResponse {
        $movement = $recordManualCashMovement->handle(
            $cashSession,
            $request->user(),
            $request->validated('type'),
            (string) $request->validated('amount'),
            $request->validated('reason'),
            $request->validated('reference'),
        );

        return (new CashMovementResource($movement))->response()->setStatusCode(201);
    }
}
