<?php

namespace App\Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountPaymentsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    public function authorize(): bool
    {
        return $this->user()->hasPermission('pos.venta.cobrar', $this->route('account')->branch);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['idempotency_key' => ['required', 'uuid'], 'cash_session_id' => ['nullable', 'uuid'],
            'tip' => ['sometimes', 'decimal:0,2', 'min:0', 'max:999999999'],
            'payments' => ['required', 'array', 'min:1', 'max:10'], 'payments.*' => ['array:method,amount,received_amount,reference'],
            'payments.*.method' => ['required', 'in:cash,qr,card,transfer'],
            'payments.*.amount' => ['required', 'decimal:0,2', 'gt:0', 'max:999999999'],
            'payments.*.received_amount' => ['prohibited_unless:payments.*.method,cash', 'nullable', 'decimal:0,2', 'min:0', 'max:999999999'],
            'payments.*.reference' => ['required_unless:payments.*.method,cash', 'nullable', 'string', 'max:100']];
    }
}
