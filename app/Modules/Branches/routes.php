<?php

use App\Modules\Branches\Http\Controllers\BranchController;
use Illuminate\Support\Facades\Route;

Route::apiResource('branches', BranchController::class);
