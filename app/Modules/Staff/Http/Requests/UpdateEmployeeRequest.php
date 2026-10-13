<?php

namespace App\Modules\Staff\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('employee'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'branch_id' => ['sometimes', 'nullable', 'integer'],
            'user_id' => ['sometimes', 'nullable', 'integer'],
            'role' => ['sometimes', 'string', 'in:employee,admin'],
            'status' => ['sometimes', 'required', 'string', 'in:active,inactive'],
        ];
    }
}
