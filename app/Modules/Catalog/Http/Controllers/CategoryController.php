<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Http\Requests\StoreCategoryRequest;
use App\Modules\Catalog\Http\Requests\UpdateCategoryRequest;
use App\Modules\Catalog\Http\Resources\CategoryResource;
use App\Modules\Catalog\Models\Category;
use App\Shared\Exceptions\DomainException;
use App\Shared\Tenancy\Concerns\ResolvesTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    use ResolvesTenant;

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Category::class);

        return CategoryResource::collection(Category::query()->with('children')->orderBy('name')->orderBy('id')->get());
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
}
