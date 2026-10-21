<?php

namespace App\Modules\Sales\Models;

use Database\Factories\Modules\Sales\Models\SaleItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    /** @use HasFactory<SaleItemFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = ['sale_id', 'order_item_id', 'name', 'quantity', 'price', 'subtotal', 'extras', 'notes'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'price' => 'decimal:2', 'subtotal' => 'decimal:2', 'extras' => 'array'];
    }
}
