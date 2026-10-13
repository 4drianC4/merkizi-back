<?php

use App\Modules\Sales\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::apiResource('orders', OrderController::class)->only(['index', 'store', 'show']);
