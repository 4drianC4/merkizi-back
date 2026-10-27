<?php

namespace App\Modules\Staff\Http\Resources;

use App\Shared\Identity\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Permission */
class PermissionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'code' => $this->code, 'module' => $this->module, 'action' => $this->action, 'scope' => $this->scope, 'description' => $this->description];
    }
}
