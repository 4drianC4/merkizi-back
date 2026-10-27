<?php

namespace App\Shared\Identity\Http\Resources;

use App\Modules\Staff\Http\Resources\EmployeeResource;
use App\Shared\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['tenant', 'role.permissions', 'branches', 'permissions']);

        return [
            ...(new EmployeeResource($this->resource))->resolve($request),
            'tenant_id' => $this->tenant_id,
            'is_superadmin' => $this->isSuperAdmin(),
            'permission_codes' => $this->effectivePermissionCodes(),
        ];
    }
}
