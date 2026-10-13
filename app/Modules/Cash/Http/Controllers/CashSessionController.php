<?php

namespace App\Modules\Cash\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cash\Actions\CloseCashSession;
use App\Modules\Cash\Actions\OpenCashSession;
use App\Modules\Cash\Http\Requests\CloseCashSessionRequest;
use App\Modules\Cash\Http\Requests\StoreCashSessionRequest;
use App\Modules\Cash\Http\Resources\CashSessionResource;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CashSessionController extends Controller
{
    public function index(CashRegister $cashRegister): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CashSession::class);

        return CashSessionResource::collection($cashRegister->sessions()->latest()->get());
    }

    public function store(StoreCashSessionRequest $request, CashRegister $cashRegister, OpenCashSession $openCashSession): JsonResponse
    {
        $session = $openCashSession->handle(
            $cashRegister,
            $request->user(),
            (string) ($request->validated('opening_balance') ?? '0.00'),
        );

        return (new CashSessionResource($session))->response()->setStatusCode(201);
    }

    public function show(CashSession $cashSession): CashSessionResource
    {
        $this->authorize('view', $cashSession);

        return new CashSessionResource($cashSession);
    }

    public function close(CloseCashSessionRequest $request, CashSession $cashSession, CloseCashSession $closeCashSession): CashSessionResource
    {
        $session = $closeCashSession->handle($cashSession);

        return new CashSessionResource($session);
    }
}
