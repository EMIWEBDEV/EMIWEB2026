<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tracking\TrackingApi;

Route::prefix('api')->group(function() {
    Route::apiResource('tracking', TrackingApi::class)->names('api.tracking');
});
