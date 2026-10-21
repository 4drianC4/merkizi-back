<?php

namespace App\Modules\Staff\Http\Resources;

use App\Modules\Staff\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Employee */
class EmployeeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'active' => $this->active,
            'role_id' => $this->role_id,
            'role' => new RoleResource($this->whenLoaded('role')),
            'branches' => $this->whenLoaded('branches', fn () => $this->branches->map(fn ($branch) => ['id' => $branch->id, 'name' => $branch->name, 'is_primary' => (bool) $branch->getRelation('pivot')->getAttribute('is_primary')])),
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->map(fn ($permission) => ['permission_id' => $permission->id, 'code' => $permission->code, 'granted' => (bool) $permission->getRelation('pivot')->getAttribute('granted'), 'granted_by_user_id' => $permission->getRelation('pivot')->getAttribute('granted_by_user_id')])),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
