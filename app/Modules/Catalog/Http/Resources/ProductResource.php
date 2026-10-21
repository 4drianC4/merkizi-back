<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'price' => $this->price,
            'effective_price' => $this->priceForBranch($request->attributes->get('catalog_branch')),
            'available' => $this->status === 'active',
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->where('active', true)->map(fn ($variant): array => ['id' => $variant->id, 'name' => $variant->name, 'price' => $variant->price])->values()),
            'extras' => $this->whenLoaded('extras', fn () => $this->extras->filter(fn ($extra): bool => $extra->status === 'active' && (bool) $extra->getRelation('pivot')->getAttribute('active'))
                ->map(fn ($extra): array => ['product_id' => $extra->id, 'name' => $extra->name, 'price' => (string) ($extra->getRelation('pivot')->getAttribute('price') ?? $extra->priceForBranch($request->attributes->get('catalog_branch')))])->values()),
            'category_id' => $this->category_id,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
