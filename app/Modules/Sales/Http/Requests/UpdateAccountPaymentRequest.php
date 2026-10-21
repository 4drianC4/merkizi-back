<?php

namespace App\Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('pos.venta.confirmar_pago', $this->route('accountPayment')->branch);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['status' => ['required', 'in:confirmed,rejected,expired']];
    }
}
