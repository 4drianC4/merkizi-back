<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Http\Requests\StoreDiningTableRequest;
use App\Modules\Sales\Http\Resources\DiningTableResource;
use App\Modules\Sales\Models\DiningArea;
use App\Modules\Sales\Models\DiningTable;
use App\Shared\Exceptions\DomainException;
use App\Shared\Tenancy\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DiningTableController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->hasPermission('salon.mesas.ver'), 403);
        $filters = $request->validate(['branch_id' => ['sometimes', 'uuid'], 'area_id' => ['sometimes', 'uuid'], 'status' => ['sometimes', 'in:free,occupied,blocked']]);
        $query = $request->user()->restrictToBranches(DiningTable::query())->with(['area', 'activeAccounts.orders', 'activeAccounts.payments']);
        foreach (['branch_id', 'area_id'] as $filter) {
            if (isset($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
            }
        }
        if (isset($filters['status'])) {
            match ($filters['status']) {
                'blocked' => $query->where(fn ($query) => $query->where('blocked', true)->orWhere('active', false)),
                'occupied' => $query->whereHas('activeAccounts'),
                default => $query->where('blocked', false)->where('active', true)->whereDoesntHave('activeAccounts'),
            };
        }

        return DiningTableResource::collection($query->orderBy('area_id')->orderBy('number')->get());
    }

    public function store(StoreDiningTableRequest $request): JsonResponse
    {
        $area = DiningArea::query()->whereKey($request->validated('area_id'))->firstOrFail();
        abort_unless($request->user()->hasPermission('salon.mesas.crear', $area->branch), 403);
        $request->validate(['number' => [Rule::unique('dining_tables')->where('area_id', $area->id)]]);
        $table = DiningTable::query()->create([...$request->validated(), 'tenant_id' => $area->tenant_id, 'branch_id' => $area->branch_id]);

        return (new DiningTableResource($table->fresh()->load(['area', 'activeAccounts'])))->response()->setStatusCode(201);
    }

    public function update(Request $request, DiningTable $table): DiningTableResource
    {
        abort_unless($request->user()->hasPermission('salon.mesas.editar', $table->branch), 403);
        $data = $request->validate(['blocked' => ['sometimes', 'boolean'], 'active' => ['sometimes', 'boolean'], 'capacity' => ['sometimes', 'integer', 'min:1', 'max:999']]);
        DB::transaction(function () use ($table, $data): void {
            $locked = DiningTable::query()->lockForUpdate()->findOrFail($table->id);
            if (($data['blocked'] ?? false) || (isset($data['active']) && ! $data['active'])) {
                if ($locked->activeAccounts()->exists()) {
                    throw new DomainException('An occupied table cannot be blocked or deactivated.', 'table_occupied', 409);
                }
            }
            $locked->update($data);
        });

        return new DiningTableResource($table->fresh()->load(['area', 'activeAccounts']));
    }

    public function areas(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->hasPermission('salon.mesas.ver'), 403);
        $data = $request->validate(['branch_id' => ['sometimes', 'uuid']]);
        $query = $request->user()->restrictToBranches(DiningArea::query());
        if (isset($data['branch_id'])) {
            $query->where('branch_id', $data['branch_id']);
        }

        return JsonResource::collection($query->orderBy('sort_order')->orderBy('id')->get());
    }

    public function storeArea(Request $request): JsonResponse
    {
        $data = $request->validate(['branch_id' => ['required', 'uuid'], 'name' => ['required', 'string', 'max:80'], 'sort_order' => ['sometimes', 'integer', 'min:0']]);
        $branch = Branch::query()->findOrFail($data['branch_id']);
        abort_unless($request->user()->hasPermission('salon.mesas.crear', $branch), 403);
        $request->validate(['name' => [Rule::unique('dining_areas')->where('branch_id', $branch->id)]]);

        return (new JsonResource(DiningArea::query()->create([...$data, 'tenant_id' => $branch->tenant_id])))->response()->setStatusCode(201);
    }
}
