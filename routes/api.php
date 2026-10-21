<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Shared\Identity\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->name('login');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->middleware('auth:sanctum')
        ->name('logout');

    Route::get('/user', function (Request $request) {
        return response()->json((new UserResource($request->user()))->resolve($request));
    })->middleware(['auth:sanctum', 'active']);
});

Route::prefix('v1')
    ->middleware(['auth:sanctum', 'active', 'tenant'])
    ->group(function () {
        foreach (glob(app_path('Modules/*/routes.php')) ?: [] as $file) {
            require $file;
        }
    });
