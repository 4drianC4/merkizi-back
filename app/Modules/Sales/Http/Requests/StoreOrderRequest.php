<?php

namespace App\Modules\Sales\Http\Requests;

use App\Modules\Sales\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Order::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cash_session_id' => ['required', 'integer'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'discount' => ['nullable', 'decimal:0,2'],
            'tax' => ['nullable', 'decimal:0,2'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'decimal:0,3', 'gt:0'],
            'items.*.extras' => ['nullable', 'array'],
        ];
    }
}
