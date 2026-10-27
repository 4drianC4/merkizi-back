<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\RawMaterialCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RawMaterialCategory
 */
class RawMaterialCategorySelectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}