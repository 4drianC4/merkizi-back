<?php

namespace App\Modules\Sales\Http\Resources;

use App\Modules\Cash\Http\Resources\PaymentResource;
use App\Modules\Sales\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'number' => $this->number,
            'version' => $this->version,
            'branch_id' => $this->branch_id,
            'cash_session_id' => $this->cash_session_id,
            'responsible_user_id' => $this->responsible_user_id,
            'service_type' => $this->service_type,
            'notes' => $this->notes,
            'status' => $this->status,
            'customer_name' => $this->customer_name,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'tax' => $this->tax,
            'total' => $this->total,
            'paid_at' => $this->paid_at?->toISOString(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
