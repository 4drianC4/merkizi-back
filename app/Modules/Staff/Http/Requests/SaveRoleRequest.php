<?php

namespace App\Modules\Staff\Http\Requests;

use App\Shared\Identity\Models\Permission;
use App\Shared\Identity\Models\Role;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');
        if ($role instanceof Role && $role->tenant_id !== app(TenantContext::class)->id()) {
            return false;
        }

        return $this->user()->hasPermission($role instanceof Role ? 'roles.editar' : 'roles.crear');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', Rule::notIn([Role::SuperAdmin, Role::CompanyAdmin, Role::Employee]), Rule::unique('roles', 'name')->where('tenant_id', app(TenantContext::class)->id())->ignore($this->route('role'))],
            'description' => ['nullable', 'string'],
            'permission_ids' => ['present', 'array'],
            'permission_ids.*' => ['required', 'uuid', 'distinct', Rule::exists('permissions', 'id')],
            'tenant_id' => ['prohibited'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $permissions = Permission::query()->whereIn('id', $this->input('permission_ids', []))->get();
            $role = $this->route('role');
            if ($role instanceof Role) {
                $permissions = $permissions->merge($role->permissions);
            }
            foreach ($permissions as $permission) {
                if (! $this->user()->hasPermission($permission->code)) {
                    $validator->errors()->add('permission_ids', 'You cannot change permissions you do not have.');
                    break;
                }
            }
        }];
    }
}
