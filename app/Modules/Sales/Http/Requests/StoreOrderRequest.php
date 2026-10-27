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
            'account_id' => ['required_without:cash_session_id', 'uuid'],
            'cash_session_id' => ['required_without:account_id', 'uuid'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'discount' => ['nullable', 'decimal:0,2', 'min:0', 'max:999999999'],
            'tax' => ['nullable', 'decimal:0,2', 'min:0', 'max:999999999'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['array:product_id,quantity,variant_id,extras,notes'],
            'items.*.product_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'decimal:0,3', 'gt:0', 'max:99999'],
            'items.*.variant_id' => ['nullable', 'uuid'],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
            'items.*.extras' => ['nullable', 'array', 'max:20'],
            'items.*.extras.*' => ['array:product_id'],
            'items.*.extras.*.product_id' => ['required', 'uuid'],
        ];
    }
}
