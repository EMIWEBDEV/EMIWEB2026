<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FormulatorTrialSampel\FormulatorTrialSampelApi;

Route::prefix('formulatortrialsampel')->group(function() {
    Route::apiResource('formulatortrialsampel', FormulatorTrialSampelApi::class);
});
