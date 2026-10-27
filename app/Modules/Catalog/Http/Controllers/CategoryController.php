<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Http\Requests\StoreCategoryRequest;
use App\Modules\Catalog\Http\Requests\UpdateCategoryRequest;
use App\Modules\Catalog\Http\Requests\SearchCategoryRequest;
use App\Modules\Catalog\Http\Resources\CategoryResource;
use App\Modules\Catalog\Http\Resources\CategorySelectResource;
use App\Modules\Catalog\Models\Category;
use App\Shared\Exceptions\DomainException;
use App\Shared\Tenancy\Concerns\ResolvesTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use ResolvesTenant;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Category::class);

        $query = Category::query()
            ->withCount(['products', 'children']);

        if ($request->has('parent_id')) {
            if ($request->query('parent_id') === 'null') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $request->query('parent_id'));
            }
        }

        return CategoryResource::collection($query->latest()->get());
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $parentId = $request->validated('parent_id');

        if ($parentId !== null && Category::query()->whereKey($parentId)->doesntExist()) {
            throw new DomainException('Parent category not found.', 'category_not_found');
        }

        $category = Category::query()->create([
            'tenant_id' => $this->tenant()->id,
            'name' => $request->validated('name'),
            'parent_id' => $parentId,
            'status' => 'active',
        ]);

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function show(Category $category): CategoryResource
    {
        $this->authorize('view', $category);

        $category->loadCount(['products', 'children']);

        return new CategoryResource($category);
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $parentId = $request->validated('parent_id');

        if ($parentId !== null && Category::query()->whereKey($parentId)->doesntExist()) {
            throw new DomainException('Parent category not found.', 'category_not_found');
        }

        $category->update($request->validated());

        return new CategoryResource($category);
    }

    public function destroy(Category $category): Response
    {
        $this->authorize('delete', $category);

        $category->update(['status' => 'inactive']);

        return response()->noContent();
    }

    public function subcategories(Category $category): AnonymousResourceCollection
    {
        $this->authorize('view', $category);

        $subcategories = $category->children()
            ->withCount('products')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return CategoryResource::collection($subcategories);
    }

    public function search(
        SearchCategoryRequest $request
    ): AnonymousResourceCollection {
        $search = $request->validated('name');

        $categories = Category::query()
            ->where('name', 'ilike', "%{$search}%")
            ->orderBy('name')
            ->limit(20)
            ->get();

        return CategorySelectResource::collection($categories);
    }
}
