<?php

namespace App\Modules\Staff\Http\Requests;

use App\Modules\Staff\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Employee::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', 'uuid'],
            'user_id' => ['nullable', 'uuid'],
            'role' => ['sometimes', 'string', 'in:employee,admin'],
        ];
    }
}
