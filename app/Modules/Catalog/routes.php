<?php

use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\ProductController;
use App\Modules\Catalog\Http\Controllers\RawMaterialCategoryController;
use App\Modules\Catalog\Http\Controllers\AllCategoriesController;
use Illuminate\Support\Facades\Route;

Route::get(
    'categories/all',
    [AllCategoriesController::class, 'index']
);

Route::get(
    'categories/search',
    [CategoryController::class, 'search']
);

Route::get(
    'categories/{category}/subcategories',
    [CategoryController::class, 'subcategories']
);
Route::apiResource('categories', CategoryController::class);
Route::apiResource('products', ProductController::class);

Route::get(
    'raw-material-categories/search',
    [RawMaterialCategoryController::class, 'search']
);
Route::get(
    'raw-material-categories/{raw_material_category}/subcategories',
    [RawMaterialCategoryController::class, 'subcategories']
);
Route::apiResource('raw-material-categories', RawMaterialCategoryController::class);