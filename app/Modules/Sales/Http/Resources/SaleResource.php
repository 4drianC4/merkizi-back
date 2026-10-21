<?php

namespace App\Modules\Sales\Http\Resources;

use App\Modules\Sales\Models\Sale;
use App\Shared\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Sale */
class SaleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $refunded = '0.00';
        $this->resource->loadMissing('refunds');
        foreach ($this->refunds as $refund) {
            $refunded = Money::add($refunded, (string) $refund->amount);
        }

        return ['id' => $this->id, 'number' => $this->number, 'account_id' => $this->account_id, 'branch_id' => $this->branch_id,
            'cash_session_id' => $this->cash_session_id, 'cashier_id' => $this->cashier_id, 'service_type' => $this->service_type,
            'customer_name' => $this->customer_name, 'customer_tax_id' => $this->customer_tax_id, 'status' => $this->status,
            'subtotal' => $this->subtotal, 'discount' => $this->discount, 'tax' => $this->tax, 'tip' => $this->tip, 'total' => $this->total,
            'refunded_total' => $refunded, 'net_total' => Money::sub((string) $this->total, $refunded),
            'items' => JsonResource::collection($this->whenLoaded('items')),
            'payments' => AccountPaymentResource::collection($this->whenLoaded('payments')),
            'refunds' => JsonResource::collection($this->whenLoaded('refunds')),
            'receipt' => ['number' => $this->number, 'fiscal' => false, 'invoice' => null],
            'created_at' => $this->created_at?->toISOString()];
    }
}
