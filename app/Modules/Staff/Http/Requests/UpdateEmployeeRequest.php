<?php

namespace App\Modules\Staff\Http\Requests;

use App\Shared\Identity\Concerns\ValidatesIdentityAssignments;
use App\Shared\Identity\Models\Role;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    use ValidatesIdentityAssignments;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('employee'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $employee = $this->route('employee');
        $tenantId = app(TenantContext::class)->id();
        $isSelf = $employee->id === $this->user()->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'email' => ['sometimes', 'required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($employee)],
            'password' => ['sometimes', 'required', 'string', 'min:8'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'role_id' => [Rule::when($isSelf, 'missing'), 'sometimes', 'required', 'uuid', Rule::exists('roles', 'id')->where(fn (Builder $query) => $query
                ->where('name', '!=', Role::SuperAdmin)
                ->where(fn (Builder $query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId)))],
            'branch_ids' => [Rule::when($isSelf, 'missing'), 'sometimes', 'array'],
            'branch_ids.*' => ['required', 'uuid', 'distinct', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)->where('status', 'active')],
            'primary_branch_id' => [Rule::when($isSelf, 'missing'), 'nullable', 'uuid'],
            'active' => [Rule::when($isSelf, 'missing'), 'sometimes', 'required', 'boolean'],
            'role' => ['prohibited'],
            'user_id' => ['prohibited'],
            'branch_id' => ['prohibited'],
            'tenant_id' => ['prohibited'],
        ];
    }
}
