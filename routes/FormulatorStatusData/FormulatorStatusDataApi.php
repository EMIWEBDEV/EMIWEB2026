<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FormulatorStatusData\FormulatorStatusDataApi;

Route::prefix('formulatorstatusdata')->group(function() {
    Route::apiResource('formulatorstatusdata', FormulatorStatusDataApi::class);
});
