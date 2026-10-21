<?php

namespace App\Modules\Staff\Http\Resources;

use App\Shared\Identity\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Role */
class RoleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'tenant_id' => $this->tenant_id, 'name' => $this->name, 'description' => $this->description,
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions'))];
    }
}
