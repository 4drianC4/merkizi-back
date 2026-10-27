<?php

use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\ProductController;
use App\Modules\Catalog\Http\Controllers\RawMaterialCategoryController;
use Illuminate\Support\Facades\Route;


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
