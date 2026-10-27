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
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CashSessionController extends Controller
{
    public function index(Request $request, CashRegister $cashRegister): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CashSession::class);
        abort_unless($request->user()->hasPermission('caja.sesiones.ver', $cashRegister->branch), 403);

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

    public function show(CashRegister $cashRegister, CashSession $cashSession): CashSessionResource
    {
        abort_unless($cashSession->cash_register_id === $cashRegister->id, 404);
        $this->authorize('view', $cashSession);

        return new CashSessionResource($cashSession);
    }

    public function close(CloseCashSessionRequest $request, CashSession $cashSession, CloseCashSession $closeCashSession): CashSessionResource
    {
        $session = $closeCashSession->handle($cashSession);

        return new CashSessionResource($session);
    }
}
