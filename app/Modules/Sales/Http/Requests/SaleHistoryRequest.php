<?php

namespace App\Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission($this->routeIs('sales.export') ? 'pos.venta.exportar' : 'pos.venta.historial');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['branch_id' => ['sometimes', 'uuid'], 'q' => ['sometimes', 'string', 'max:150'],
            'from' => ['sometimes', 'date_format:Y-m-d'], 'to' => ['sometimes', 'date_format:Y-m-d', Rule::when($this->filled('from'), 'after_or_equal:from')],
            'status' => ['sometimes', 'in:completed,partially_refunded,refunded'], 'service_type' => ['sometimes', 'in:table,takeaway'],
            'cashier_id' => ['sometimes', 'uuid'], 'method' => ['sometimes', 'in:cash,qr,card,transfer'],
            'sort' => ['sometimes', 'in:created_at,number,total'], 'direction' => ['sometimes', 'in:asc,desc'],
            'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']];
    }
}
