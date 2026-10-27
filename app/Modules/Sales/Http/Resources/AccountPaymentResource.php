<?php

namespace App\Modules\Sales\Http\Resources;

use App\Modules\Sales\Models\AccountPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AccountPayment */
class AccountPaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'account_id' => $this->account_id, 'origin_account_id' => $this->origin_account_id,
            'sale_id' => $this->sale_id, 'cash_session_id' => $this->cash_session_id, 'user_id' => $this->user_id,
            'method' => $this->method, 'amount' => $this->amount, 'received_amount' => $this->received_amount,
            'change' => $this->change, 'status' => $this->status, 'reference' => $this->reference,
            'confirmed_at' => $this->confirmed_at?->toISOString(), 'created_at' => $this->created_at?->toISOString()];
    }
}
