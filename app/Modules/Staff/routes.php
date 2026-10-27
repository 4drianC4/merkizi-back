<?php

use App\Modules\Staff\Http\Controllers\EmployeeController;
use App\Modules\Staff\Http\Controllers\PermissionController;
use App\Modules\Staff\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::apiResource('employees', EmployeeController::class);

Route::put('employees/{employee}/permissions', [EmployeeController::class, 'permissions'])->name('employees.permissions.update');
Route::apiResource('roles', RoleController::class);
Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
