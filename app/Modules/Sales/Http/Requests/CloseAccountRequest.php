<?php

namespace App\Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseAccountRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    public function authorize(): bool
    {
        return $this->user()->hasPermission('pos.venta.cerrar', $this->route('account')->branch);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['idempotency_key' => ['required', 'uuid'], 'cash_session_id' => ['nullable', 'uuid']];
    }
}
