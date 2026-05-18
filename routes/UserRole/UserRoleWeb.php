<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserRole\UserRoleController;

Route::middleware(['auth'])->group(function () {
    Route::get('/management-user-role', [UserRoleController::class, 'index'])->middleware('autotrack');
});
