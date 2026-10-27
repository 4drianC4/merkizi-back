<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\RawMaterialCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RawMaterialCategory
 */
class RawMaterialCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'parent_id' => $this->parent_id,
            'children' => RawMaterialCategoryResource::collection($this->whenLoaded('children')),
            'children_count' => $this->whenCounted('children'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
