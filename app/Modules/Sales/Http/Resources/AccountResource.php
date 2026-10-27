<?php

namespace App\Modules\Sales\Http\Resources;

use App\Modules\Sales\Models\DiningTable;
use App\Modules\Sales\Models\TableAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TableAccount */
class AccountResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'branch_id' => $this->branch_id, 'cash_session_id' => $this->cash_session_id,
            'status' => $this->status, 'version' => $this->version, 'service_type' => $this->service_type,
            'customer_name' => $this->customer_name, 'customer_tax_id' => $this->customer_tax_id, 'guests' => $this->guests,
            'opened_by' => $this->opened_by, 'notes' => $this->notes, 'destination_account_id' => $this->destination_account_id,
            'sale_id' => $this->whenLoaded('sale', fn () => $this->sale?->id), ...$this->totals(),
            'orders' => OrderResource::collection($this->whenLoaded('orders')),
            'payments' => AccountPaymentResource::collection($this->whenLoaded('payments')),
            'tables' => $this->whenLoaded('tables', fn () => $this->tables->map(fn (DiningTable $table): array => ['id' => $table->id, 'number' => $table->number, 'area_id' => $table->area_id, 'capacity' => $table->capacity])),
            'opened_at' => $this->created_at?->toISOString(), 'closed_at' => $this->closed_at?->toISOString()];
    }
}
