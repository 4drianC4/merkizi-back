<?php

namespace App\Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('order'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['version' => ['required', 'integer', 'min:1'], 'items' => ['present', 'array', 'max:100'],
            'items.*' => ['array:id,product_id,quantity,variant_id,extras,notes'],
            'items.*.id' => ['nullable', 'uuid', 'distinct'], 'items.*.product_id' => ['required_without:items.*.id', 'uuid'],
            'items.*.quantity' => ['required', 'decimal:0,3', 'gt:0', 'max:99999'],
            'items.*.variant_id' => ['nullable', 'uuid'], 'items.*.notes' => ['nullable', 'string', 'max:2000'],
            'items.*.extras' => ['nullable', 'array', 'max:20'], 'items.*.extras.*' => ['array:product_id'],
            'items.*.extras.*.product_id' => ['required', 'uuid'],
            'discount' => ['sometimes', 'decimal:0,2', 'min:0', 'max:999999999'],
            'tax' => ['sometimes', 'decimal:0,2', 'min:0', 'max:999999999'], 'notes' => ['nullable', 'string', 'max:2000']];
    }
}
