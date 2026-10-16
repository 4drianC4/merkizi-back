<?php

use App\Modules\Cash\Http\Controllers\CashRegisterController;
use App\Modules\Cash\Http\Controllers\CashSessionController;
use App\Modules\Cash\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::apiResource('cash-registers', CashRegisterController::class)
    ->parameters(['cash-registers' => 'cashRegister']);

Route::apiResource('cash-registers.sessions', CashSessionController::class)
    ->parameters(['cash-registers' => 'cashRegister', 'sessions' => 'cashSession'])
    ->only(['index', 'store', 'show']);
Route::post('cash-sessions/{cashSession}/reconciliation', [CashSessionController::class, 'reconcile'])
    ->name('cash-sessions.reconciliation.store');
Route::post('cash-sessions/{cashSession}/close', [CashSessionController::class, 'close'])->name('cash-sessions.close');
Route::post('orders/{order}/payments', [PaymentController::class, 'store'])->name('orders.payments.store');
