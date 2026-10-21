<?php

namespace App\Modules\Sales\Actions;

use App\Modules\Catalog\Models\Product;
use App\Shared\Exceptions\DomainException;
use App\Shared\Support\Money;
use App\Shared\Tenancy\Models\Branch;

class BuildOrderItems
{
    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    public function handle(Branch $branch, array $lines): array
    {
        $products = Product::query()->where('tenant_id', $branch->tenant_id)->with(['variants', 'extras.prices', 'prices'])->whereIn('id', array_column($lines, 'product_id'))->get()->keyBy('id');
        $items = [];
        foreach ($lines as $line) {
            $product = $products->get($line['product_id']);
            if ($product === null || $product->status !== 'active') {
                throw new DomainException('The product is not available.', 'product_unavailable');
            }
            $price = $product->priceForBranch($branch);
            $name = $product->name;
            if (! empty($line['variant_id'])) {
                $variant = $product->variants->firstWhere('id', $line['variant_id']);
                if ($variant === null || ! $variant->active) {
                    throw new DomainException('The variant is not available for this product.', 'variant_unavailable');
                }
                $price = (string) $variant->price;
                $name .= ' - '.$variant->name;
            }
            $extras = [];
            foreach ($line['extras'] ?? [] as $extraLine) {
                $extra = $product->extras->firstWhere('id', $extraLine['product_id']);
                if ($extra === null || $extra->status !== 'active' || ! $extra->getRelation('pivot')->getAttribute('active')) {
                    throw new DomainException('The extra is not available for this product.', 'extra_unavailable');
                }
                $extraPrice = (string) ($extra->getRelation('pivot')->getAttribute('price') ?? $extra->priceForBranch($branch));
                $price = Money::add($price, $extraPrice);
                $extras[] = ['product_id' => $extra->id, 'name' => $extra->name, 'price' => $extraPrice];
            }
            $rawSubtotal = bcmul($price, (string) $line['quantity'], 4);
            $subtotal = bcadd($rawSubtotal, '0.005', 2);
            $items[] = ['product_id' => $product->id, 'variant_id' => $line['variant_id'] ?? null, 'name' => $name,
                'price' => $price, 'quantity' => (string) $line['quantity'], 'extras' => $extras,
                'notes' => $line['notes'] ?? null, 'subtotal' => $subtotal];
        }

        return $items;
    }
}
