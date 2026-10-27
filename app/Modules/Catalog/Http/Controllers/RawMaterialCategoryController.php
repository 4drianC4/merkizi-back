<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Http\Requests\SearchRawMaterialCategoryRequest;
use App\Modules\Catalog\Http\Requests\StoreRawMaterialCategoryRequest;
use App\Modules\Catalog\Http\Requests\UpdateRawMaterialCategoryRequest;
use App\Modules\Catalog\Http\Resources\RawMaterialCategoryResource;
use App\Modules\Catalog\Http\Resources\RawMaterialCategorySelectResource;
use App\Modules\Catalog\Models\RawMaterialCategory;
use App\Shared\Exceptions\DomainException;
use App\Shared\Tenancy\Concerns\ResolvesTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class RawMaterialCategoryController extends Controller
{
    use ResolvesTenant;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', RawMaterialCategory::class);

        $query = RawMaterialCategory::query()
            ->withCount('children');

        if ($request->has('parent_id')) {
            if ($request->query('parent_id') === 'null') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $request->query('parent_id'));
            }
        }

        return RawMaterialCategoryResource::collection($query->with('children')->latest()->get());
    }

    public function store(StoreRawMaterialCategoryRequest $request): JsonResponse
    {
        $parentId = $request->validated('parent_id');

        if (
            $parentId !== null
            && RawMaterialCategory::query()->whereKey($parentId)->doesntExist()
        ) {
            throw new DomainException(
                'Parent raw material category not found.',
                'raw_material_category_not_found'
            );
        }

        $rawMaterialCategory = RawMaterialCategory::query()->create([
            'tenant_id' => $this->tenant()->id,
            'name' => $request->validated('name'),
            'parent_id' => $parentId,
            'status' => 'active',
        ]);

        return (new RawMaterialCategoryResource($rawMaterialCategory))
            ->response()
            ->setStatusCode(201);
    }

    public function show(
        RawMaterialCategory $raw_material_category
    ): RawMaterialCategoryResource {
        $this->authorize('view', $raw_material_category);

        $raw_material_category->loadCount('children');

        return new RawMaterialCategoryResource($raw_material_category);
    }

    public function update(
        UpdateRawMaterialCategoryRequest $request,
        RawMaterialCategory $raw_material_category
    ): RawMaterialCategoryResource {
        $parentId = $request->validated('parent_id');

        if (
            $parentId !== null
            && RawMaterialCategory::query()->whereKey($parentId)->doesntExist()
        ) {
            throw new DomainException(
                'Parent raw material category not found.',
                'raw_material_category_not_found'
            );
        }

        $raw_material_category->update($request->validated());

        return new RawMaterialCategoryResource($raw_material_category);
    }

    public function destroy(
        RawMaterialCategory $raw_material_category
    ): Response {
        $this->authorize('delete', $raw_material_category);

        $raw_material_category->update(['status' => 'inactive']);

        return response()->noContent();
    }

    public function subcategories(RawMaterialCategory $raw_material_category): AnonymousResourceCollection
    {
        $this->authorize('view', $raw_material_category);

        $raw_material_subcategories = $raw_material_category->children()
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return RawMaterialCategoryResource::collection($raw_material_subcategories);
    }

    public function search(
        SearchRawMaterialCategoryRequest $request
    ): AnonymousResourceCollection {
        $search = $request->validated('name');

        $raw_material_categories = RawMaterialCategory::query()
            ->where('name', 'ilike', "%{$search}%")
            ->orderBy('name')
            ->limit(20)
            ->get();

        return RawMaterialCategorySelectResource::collection($raw_material_categories);
    }
}
