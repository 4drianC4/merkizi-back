<?php

namespace App\Modules\Cash\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('open', $this->route('cashRegister'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'opening_balance' => ['nullable', 'decimal:0,2'],
        ];
    }
}
