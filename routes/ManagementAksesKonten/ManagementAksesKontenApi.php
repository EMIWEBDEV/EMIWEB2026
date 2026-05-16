<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ManagementAksesKonten\ManagementAksesKontenApi;

Route::prefix('api')->group(function() {
    Route::apiResource('managementakseskonten', ManagementAksesKontenApi::class)->names('api.managementakseskonten');
});
