<?php

namespace App\Modules\Staff\Http\Requests;

use App\Shared\Identity\Models\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEmployeePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employee = $this->route('employee');

        return $employee->id !== $this->user()->id && $this->user()->can('managePermissions', $employee);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['array:permission_id,granted'],
            'permissions.*.permission_id' => ['required', 'uuid', 'distinct', Rule::exists('permissions', 'id')],
            'permissions.*.granted' => ['required', 'boolean'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $codes = Permission::query()->pluck('code', 'id');
            foreach ($this->input('permissions', []) as $index => $permission) {
                if (! $this->user()->hasPermission($codes[$permission['permission_id']])) {
                    $validator->errors()->add('permissions.'.$index.'.permission_id', 'You cannot change permissions you do not have.');
                }
            }
            $requestedIds = array_column($this->input('permissions', []), 'permission_id');
            foreach ($this->route('employee')->permissions as $permission) {
                if (! in_array($permission->id, $requestedIds, true) && ! $this->user()->hasPermission($permission->code)) {
                    $validator->errors()->add('permissions', 'You cannot remove overrides for permissions you do not have.');
                }
            }
        }];
    }
}
