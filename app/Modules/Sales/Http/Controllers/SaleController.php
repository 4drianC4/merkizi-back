<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Actions\RefundSale;
use App\Modules\Sales\Http\Requests\RefundSaleRequest;
use App\Modules\Sales\Http\Requests\SaleHistoryRequest;
use App\Modules\Sales\Http\Resources\SaleResource;
use App\Modules\Sales\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SaleController extends Controller
{
    public function index(SaleHistoryRequest $request): AnonymousResourceCollection
    {
        return SaleResource::collection($this->query($request)->with(['items', 'payments', 'refunds'])->paginate($request->integer('per_page', 20))->withQueryString())
            ->additional(['filters' => $request->validated()]);
    }

    public function show(Request $request, Sale $sale): SaleResource
    {
        abort_unless($request->user()->hasPermission('pos.venta.ver', $sale->branch), 403);

        return new SaleResource($sale->load(['items', 'payments', 'refunds']));
    }

    public function audit(Request $request, Sale $sale): AnonymousResourceCollection
    {
        abort_unless($request->user()->hasPermission('pos.venta.auditoria', $sale->branch), 403);

        return JsonResource::collection($sale->account->audits()->orderBy('created_at')->orderBy('id')->paginate(50));
    }

    public function refund(RefundSaleRequest $request, Sale $sale, RefundSale $refund): JsonResponse
    {
        return (new JsonResource($refund->handle($sale, $request->user(), $request->validated())))->response()->setStatusCode(201);
    }

    public function export(SaleHistoryRequest $request): StreamedResponse
    {
        $query = $this->query($request);

        return response()->streamDownload(function () use ($query): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['id', 'number', 'customer', 'tax_id', 'status', 'subtotal', 'discount', 'tax', 'tip', 'total', 'created_at'], ',', '"', '');
            foreach ($query->cursor() as $sale) {
                $customer = preg_match('/^[=+\-@\t\r\n]/', $sale->customer_name ?? '') ? "'".$sale->customer_name : $sale->customer_name;
                $taxId = preg_match('/^[=+\-@\t\r\n]/', $sale->customer_tax_id ?? '') ? "'".$sale->customer_tax_id : $sale->customer_tax_id;
                fputcsv($stream, [$sale->id, $sale->number, $customer, $taxId, $sale->status, $sale->subtotal, $sale->discount, $sale->tax, $sale->tip, $sale->total, $sale->created_at->toIso8601String()], ',', '"', '');
            }
            fclose($stream);
        }, 'sales.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return Builder<Sale> */
    private function query(SaleHistoryRequest $request): Builder
    {
        $data = $request->validated();
        $query = $request->user()->restrictToBranches(Sale::query())->where('tenant_id', $request->user()->tenant_id);
        foreach (['branch_id', 'status', 'service_type', 'cashier_id'] as $field) {
            if (isset($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }
        if (isset($data['q'])) {
            $q = $data['q'];
            $query->where(function (Builder $query) use ($q): void {
                $query->where('customer_name', 'like', '%'.$q.'%')->orWhere('customer_tax_id', 'like', '%'.$q.'%');
                if (ctype_digit($q) && bccomp($q, '2147483647', 0) <= 0) {
                    $query->orWhere('number', $q);
                }
            });
        }
        foreach (['from', 'to'] as $field) {
            if (isset($data[$field])) {
                $date = CarbonImmutable::createFromFormat('!Y-m-d', $data[$field], 'America/La_Paz');
                $query->where('created_at', $field === 'from' ? '>=' : '<=', ($field === 'from' ? $date->startOfDay() : $date->endOfDay())->utc());
            }
        }
        if (isset($data['method'])) {
            $query->whereHas('payments', fn (Builder $query) => $query->where('method', $data['method'])->where('status', 'confirmed'));
        }

        return $query->orderBy($data['sort'] ?? 'created_at', $data['direction'] ?? 'desc')->orderBy('id');
    }
}
