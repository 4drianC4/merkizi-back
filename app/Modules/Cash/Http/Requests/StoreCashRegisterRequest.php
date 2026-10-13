<?php

namespace App\Modules\Cash\Http\Requests;

use App\Modules\Cash\Models\CashRegister;
use Illuminate\Foundation\Http\FormRequest;

class StoreCashRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CashRegister::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => ['required', 'integer'],
        ];
    }
}
