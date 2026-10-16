<?php

namespace App\Modules\Cash\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartCashReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('close', $this->route('cashSession'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'counted_cash_balance' => ['required', 'decimal:0,2', 'gte:0'],
        ];
    }
}
