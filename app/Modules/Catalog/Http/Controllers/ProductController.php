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
use App\Shared\Tenancy\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    use ResolvesTenant;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Product::class);

        $data = $request->validate(['branch_id' => ['sometimes', 'uuid'], 'category_id' => ['sometimes', 'uuid'],
            'q' => ['sometimes', 'string', 'max:150'], 'available' => ['sometimes', 'boolean']]);
        $this->catalogBranch($request);
        $query = Product::query()->with(['variants', 'extras.prices', 'prices']);
        if (isset($data['category_id'])) {
            $query->where('category_id', $data['category_id']);
        }
        if (isset($data['q'])) {
            $query->where('name', 'like', '%'.$data['q'].'%');
        }
        if ($request->boolean('available')) {
            $query->where('status', 'active');
        }

        return ProductResource::collection($query->orderBy('name')->orderBy('id')->get());
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

    public function show(Request $request, Product $product): ProductResource
    {
        $this->authorize('view', $product);

        $request->validate(['branch_id' => ['sometimes', 'uuid']]);
        $this->catalogBranch($request);

        return new ProductResource($product->load(['variants', 'extras.prices', 'prices']));
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

    private function catalogBranch(Request $request): void
    {
        if ($request->filled('branch_id')) {
            $branch = Branch::query()->findOrFail($request->input('branch_id'));
            abort_unless($request->user()->branches()->whereKey($branch->id)->where('status', 'active')->exists(), 403);
            $request->attributes->set('catalog_branch', $branch);
        }
    }
}
