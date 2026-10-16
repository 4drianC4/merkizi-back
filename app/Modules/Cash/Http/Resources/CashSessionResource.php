<?php

namespace App\Modules\Cash\Http\Resources;

use App\Modules\Cash\Models\CashSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CashSession
 */
class CashSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cash_register_id' => $this->cash_register_id,
            'branch_id' => $this->branch_id,
            'status' => $this->status,
            'opening_balance' => $this->opening_balance,
            'expected_cash_balance' => $this->expected_cash_balance,
            'counted_cash_balance' => $this->counted_cash_balance,
            'cash_difference' => $this->cash_difference,
            'difference_note' => $this->difference_note,
            'opened_at' => $this->opened_at->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
