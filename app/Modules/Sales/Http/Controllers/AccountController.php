<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Actions\CloseAccount;
use App\Modules\Sales\Actions\MoveAccountItems;
use App\Modules\Sales\Actions\RecordAccountPayments;
use App\Modules\Sales\Http\Requests\CloseAccountRequest;
use App\Modules\Sales\Http\Requests\MoveAccountRequest;
use App\Modules\Sales\Http\Requests\StoreAccountRequest;
use App\Modules\Sales\Http\Resources\AccountResource;
use App\Modules\Sales\Http\Resources\SaleResource;
use App\Modules\Sales\Models\DiningTable;
use App\Modules\Sales\Models\SalesAudit;
use App\Modules\Sales\Models\TableAccount;
use App\Shared\Exceptions\DomainException;
use App\Shared\Tenancy\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->hasPermission('pos.venta.ver'), 403);
        $filters = $request->validate(['branch_id' => ['sometimes', 'uuid']]);
        $query = $request->user()->restrictToBranches(TableAccount::query())->where('status', 'open')->where('service_type', 'table');
        if (isset($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        return AccountResource::collection($query->with(['orders.items', 'orders.payments', 'payments', 'tables'])->orderBy('created_at')->orderBy('id')->get());
    }

    public function store(StoreAccountRequest $request, RecordAccountPayments $payments): JsonResponse
    {
        $branch = Branch::query()->findOrFail($request->validated('branch_id'));
        abort_unless($request->user()->hasPermission('pos.venta.crear', $branch), 403);
        $account = DB::transaction(function () use ($request, $branch, $payments): TableAccount {
            $table = null;
            if ($request->validated('service_type') === 'table') {
                $table = DiningTable::query()->where('branch_id', $branch->id)->lockForUpdate()->findOrFail($request->validated('table_id'));
                if (! $table->active || $table->blocked || ! $table->area->active) {
                    throw new DomainException('The table is blocked or inactive.', 'table_unavailable', 409);
                }
                if (DB::table('account_tables')->where('table_id', $table->id)->whereNull('separated_at')->exists()) {
                    throw new DomainException('The table already has an open account.', 'table_occupied', 409);
                }
            }
            $account = TableAccount::query()->create([...$request->safe()->except(['table_id']),
                'tenant_id' => $branch->tenant_id, 'opened_by' => $request->user()->id, 'status' => 'open', 'tip' => '0.00', 'version' => 1]);
            if ($request->filled('cash_session_id')) {
                $payments->session($account, $request->validated('cash_session_id'), $request->user());
            }
            if ($table !== null) {
                DB::table('account_tables')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $branch->tenant_id, 'branch_id' => $branch->id, 'account_id' => $account->id, 'table_id' => $table->id, 'joined_at' => now()]);
            }
            SalesAudit::record($account, $request->user(), 'account.opened', ['table_id' => $table?->id, 'service_type' => $account->service_type]);

            return $account;
        }, 3);

        return (new AccountResource($account->load(['orders.items', 'payments', 'tables'])))->response()->setStatusCode(201);
    }

    public function show(Request $request, TableAccount $account): AccountResource
    {
        abort_unless($request->user()->hasPermission('pos.venta.ver', $account->branch), 403);

        return new AccountResource($account->load(['orders.items', 'payments', 'tables', 'sale']));
    }

    public function close(CloseAccountRequest $request, TableAccount $account, CloseAccount $close): SaleResource
    {
        return new SaleResource($close->handle($account, $request->user(), $request->validated('idempotency_key'), $request->validated('cash_session_id'))
            ->load(['items', 'payments', 'refunds']));
    }

    public function merge(MoveAccountRequest $request, MoveAccountItems $move): AccountResource
    {
        return new AccountResource($move->handle($request->user(), $request->validated(), true)->load(['orders.items', 'payments', 'tables']));
    }

    public function transfer(MoveAccountRequest $request, MoveAccountItems $move): AccountResource
    {
        return new AccountResource($move->handle($request->user(), $request->validated(), false)->load(['orders.items', 'payments', 'tables']));
    }

    public function audit(Request $request, TableAccount $account): AnonymousResourceCollection
    {
        abort_unless($request->user()->hasPermission('pos.venta.auditoria', $account->branch), 403);

        return JsonResource::collection($account->audits()->orderBy('created_at')->orderBy('id')->paginate(50));
    }
}
