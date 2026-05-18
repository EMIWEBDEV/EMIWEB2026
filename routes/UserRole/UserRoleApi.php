<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserRole\UserRoleApi;

Route::middleware(['auth'])->group(function () {
    Route::get('/api/v1/user-role/list',           [UserRoleApi::class, 'getList']);
    Route::get('/api/v1/user-role/grouped',        [UserRoleApi::class, 'getGroupedList']);
    Route::get('/api/v1/user-role/stats',          [UserRoleApi::class, 'getStats']);
    Route::get('/api/v1/user-role/options/users',  [UserRoleApi::class, 'getOptionUsers']);
    Route::get('/api/v1/user-role/options/roles',  [UserRoleApi::class, 'getOptionRoles']);
    Route::post('/api/v1/user-role/store',         [UserRoleApi::class, 'store']);
    Route::delete('/api/v1/user-role/delete',      [UserRoleApi::class, 'destroy']);
});
