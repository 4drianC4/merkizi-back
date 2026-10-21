<?php

namespace App\Modules\Sales\Http\Resources;

use App\Modules\Sales\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'notes' => $this->notes,
            'variant_id' => $this->variant_id,
            'product_id' => $this->product_id,
            'name' => $this->name,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'extras' => $this->extras,
            'subtotal' => $this->subtotal,
        ];
    }
}
