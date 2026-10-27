<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Http\Resources\CategoryResource;
use App\Modules\Catalog\Http\Resources\RawMaterialCategoryResource;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\RawMaterialCategory;
use Illuminate\Http\JsonResponse;

class AllCategoriesController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Category::class);
        $this->authorize('viewAny', RawMaterialCategory::class);

        $categories = Category::query()
            ->whereNull('parent_id')
            ->with('children')
            ->withCount(['products', 'children'])
            ->get()
            ->map(function (Category $category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'type' => 'category',
                    'status' => $category->status,
                    'parent_id' => $category->parent_id,
                    'children' => CategoryResource::collection($category->children),
                    'products_count' => $category->products_count,
                    'children_count' => $category->children_count,
                    'created_at' => $category->created_at?->toISOString(),
                    'updated_at' => $category->updated_at?->toISOString(),
                ];
            });

        $rawMaterialCategories = RawMaterialCategory::query()
            ->whereNull('parent_id')
            ->with('children')
            ->withCount('children')
            ->get()
            ->map(function (RawMaterialCategory $category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'type' => 'raw_material',
                    'status' => $category->status,
                    'parent_id' => $category->parent_id,
                    'children' => RawMaterialCategoryResource::collection(
                        $category->children
                    ),
                    'products_count' => 0,
                    'children_count' => $category->children_count,
                    'created_at' => $category->created_at?->toISOString(),
                    'updated_at' => $category->updated_at?->toISOString(),
                ];
            });

        return response()->json([
            'data' => $categories
                ->concat($rawMaterialCategories)
                ->values(),
        ]);
    }
}
