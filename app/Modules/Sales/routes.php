<?php

use App\Modules\Sales\Http\Controllers\AccountController;
use App\Modules\Sales\Http\Controllers\AccountPaymentController;
use App\Modules\Sales\Http\Controllers\DiningTableController;
use App\Modules\Sales\Http\Controllers\OrderController;
use App\Modules\Sales\Http\Controllers\SaleController;
use Illuminate\Support\Facades\Route;

Route::apiResource('orders', OrderController::class)->only(['index', 'store', 'show']);

Route::patch('orders/{order}/items', [OrderController::class, 'updateItems'])->name('orders.items.update');
Route::get('areas', [DiningTableController::class, 'areas'])->name('areas.index');
Route::post('areas', [DiningTableController::class, 'storeArea'])->name('areas.store');
Route::get('tables', [DiningTableController::class, 'index'])->name('tables.index');
Route::post('tables', [DiningTableController::class, 'store'])->name('tables.store');
Route::patch('tables/{table}', [DiningTableController::class, 'update'])->name('tables.update');
Route::post('tables/merge', [AccountController::class, 'merge'])->name('tables.merge');
Route::post('tables/transfer-items', [AccountController::class, 'transfer'])->name('tables.transfer');
Route::get('accounts/open-tables', [AccountController::class, 'index'])->name('accounts.open-tables');
Route::post('accounts', [AccountController::class, 'store'])->name('accounts.store');
Route::get('accounts/{account}', [AccountController::class, 'show'])->name('accounts.show');
Route::post('accounts/{account}/payments', [AccountPaymentController::class, 'store'])->name('accounts.payments.store');
Route::post('accounts/{account}/close', [AccountController::class, 'close'])->name('accounts.close');
Route::get('accounts/{account}/audit', [AccountController::class, 'audit'])->name('accounts.audit');
Route::get('account-payments/{accountPayment}', [AccountPaymentController::class, 'show'])->name('account-payments.show');
Route::patch('account-payments/{accountPayment}', [AccountPaymentController::class, 'update'])->name('account-payments.update');
Route::get('sales/history', [SaleController::class, 'index'])->name('sales.history');
Route::get('sales/export', [SaleController::class, 'export'])->name('sales.export');
Route::get('sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
Route::get('sales/{sale}/audit', [SaleController::class, 'audit'])->name('sales.audit');
Route::post('sales/{sale}/refunds', [SaleController::class, 'refund'])->name('sales.refunds.store');
