<?php

namespace App\Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('pos.venta.crear');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['branch_id' => ['required', 'uuid'], 'service_type' => ['required', 'in:table,takeaway'],
            'table_id' => ['required_if:service_type,table', 'prohibited_if:service_type,takeaway', 'nullable', 'uuid'],
            'cash_session_id' => ['nullable', 'uuid'], 'customer_name' => ['nullable', 'string', 'max:150'],
            'customer_tax_id' => ['nullable', 'string', 'max:30'], 'guests' => ['nullable', 'integer', 'min:1', 'max:999'],
            'notes' => ['nullable', 'string', 'max:2000']];
    }
}
