<?php

namespace App\Modules\Cash\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cash\Actions\CreateCashRegister;
use App\Modules\Cash\Http\Requests\StoreCashRegisterRequest;
use App\Modules\Cash\Http\Requests\UpdateCashRegisterRequest;
use App\Modules\Cash\Http\Resources\CashRegisterResource;
use App\Modules\Cash\Models\CashRegister;
use App\Shared\Exceptions\DomainException;
use App\Shared\Tenancy\Concerns\ResolvesTenant;
use App\Shared\Tenancy\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CashRegisterController extends Controller
{
    use ResolvesTenant;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CashRegister::class);

        return CashRegisterResource::collection($request->user()->restrictToBranches(CashRegister::query())->latest()->get());
    }

    public function store(StoreCashRegisterRequest $request, CreateCashRegister $createCashRegister): JsonResponse
    {
        $branch = Branch::query()->find($request->validated('branch_id'));

        if ($branch === null) {
            throw new DomainException('Branch not found.', 'branch_not_found');
        }

        abort_unless($request->user()->hasPermission('caja.cajas.crear', $branch), 403);

        $cashRegister = $createCashRegister->handle($this->tenant(), $branch, $request->validated('name'));

        return (new CashRegisterResource($cashRegister))->response()->setStatusCode(201);
    }

    public function show(CashRegister $cashRegister): CashRegisterResource
    {
        $this->authorize('view', $cashRegister);

        return new CashRegisterResource($cashRegister);
    }

    public function update(UpdateCashRegisterRequest $request, CashRegister $cashRegister): CashRegisterResource
    {
        $cashRegister->update($request->validated());

        return new CashRegisterResource($cashRegister);
    }

    public function destroy(CashRegister $cashRegister): Response
    {
        $this->authorize('delete', $cashRegister);

        $cashRegister->update(['status' => 'inactive']);

        return response()->noContent();
    }
}
