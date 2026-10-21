<?php

namespace App\Modules\Staff\Http\Requests;

use App\Modules\Staff\Models\Employee;
use App\Shared\Identity\Concerns\ValidatesIdentityAssignments;
use App\Shared\Identity\Models\Role;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    use ValidatesIdentityAssignments;

    public function authorize(): bool
    {
        return $this->user()->can('create', Employee::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role_id' => ['required', 'uuid', Rule::exists('roles', 'id')->where(fn (Builder $query) => $query
                ->where('name', '!=', Role::SuperAdmin)
                ->where(fn (Builder $query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId)))],
            'branch_ids' => ['present', 'array'],
            'branch_ids.*' => ['required', 'uuid', 'distinct', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)->where('status', 'active')],
            'primary_branch_id' => ['nullable', 'uuid'],
            'role' => ['prohibited'],
            'user_id' => ['prohibited'],
            'branch_id' => ['prohibited'],
            'tenant_id' => ['prohibited'],
        ];
    }
}
