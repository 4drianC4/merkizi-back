<?php

namespace App\Modules\Cash\Http\Resources;

use App\Modules\Cash\Models\CashMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CashMovement
 */
class CashMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cash_session_id' => $this->cash_session_id,
            'recorded_by' => $this->recorded_by,
            'type' => $this->type,
            'amount' => $this->amount,
            'reason' => $this->reason,
            'reference' => $this->reference,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
