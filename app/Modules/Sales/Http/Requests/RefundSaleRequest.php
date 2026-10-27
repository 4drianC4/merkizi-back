<?php

namespace App\Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefundSaleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    public function authorize(): bool
    {
        return $this->user()->hasPermission('pos.venta.devolver', $this->route('sale')->branch);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['idempotency_key' => ['required', 'uuid'], 'cash_session_id' => ['required', 'uuid'],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:999999999'], 'reason' => ['required', 'string', 'max:2000']];
    }
}
