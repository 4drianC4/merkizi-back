<?php

namespace App\Modules\Cash\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashSessionActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this['id'],
            'category' => $this['category'],
            'type' => $this['type'],
            'description' => $this['description'],
            'amount' => $this['amount'],
            'cash_effect' => $this['cash_effect'],
            'status' => $this['status'],
            'reference' => $this['reference'],
            'recorded_by' => $this['recorded_by'],
            'occurred_at' => $this['occurred_at']->toISOString(),
        ];
    }
}
