<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Http\Requests\StoreProductRequest;
use App\Modules\Catalog\Http\Requests\UpdateProductRequest;
use App\Modules\Catalog\Http\Resources\ProductResource;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Shared\Exceptions\DomainException;
use App\Shared\Tenancy\Concerns\ResolvesTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    use ResolvesTenant;

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Product::class);

        return ProductResource::collection(Product::query()->latest()->get());
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $categoryId = $request->validated('category_id');

        if ($categoryId !== null && Category::query()->whereKey($categoryId)->doesntExist()) {
            throw new DomainException('Category not found.', 'category_not_found');
        }

        $product = Product::query()->create([
            'tenant_id' => $this->tenant()->id,
            'category_id' => $categoryId,
            'name' => $request->validated('name'),
            'sku' => $request->validated('sku'),
            'price' => (string) ($request->validated('price') ?? '0.00'),
            'status' => 'active',
        ]);

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        $this->authorize('view', $product);

        return new ProductResource($product);
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        if ($request->has('category_id')) {
            $categoryId = $request->validated('category_id');

            if ($categoryId !== null && Category::query()->whereKey($categoryId)->doesntExist()) {
                throw new DomainException('Category not found.', 'category_not_found');
            }
        }

        $product->update($request->validated());

        return new ProductResource($product);
    }

    public function destroy(Product $product): Response
    {
        $this->authorize('delete', $product);

        $product->update(['status' => 'inactive']);

        return response()->noContent();
    }
}
