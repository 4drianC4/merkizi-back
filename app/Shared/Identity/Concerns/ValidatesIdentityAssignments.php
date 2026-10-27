<?php

namespace App\Shared\Identity\Concerns;

use App\Shared\Identity\Models\Role;
use Illuminate\Validation\Validator;

trait ValidatesIdentityAssignments
{
    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if ($this->filled('role_id')) {
                $role = Role::query()->with('permissions')->findOrFail($this->input('role_id'));
                foreach ($role->permissions as $permission) {
                    if (! $this->user()->hasPermission($permission->code)) {
                        $validator->errors()->add('role_id', 'You cannot assign permissions you do not have.');
                        break;
                    }
                }
            }
            if ($this->filled('primary_branch_id') && ! in_array($this->input('primary_branch_id'), $this->input('branch_ids', []), true)) {
                $validator->errors()->add('primary_branch_id', 'The primary branch must be included in branch_ids.');
            }
        }];
    }
}
