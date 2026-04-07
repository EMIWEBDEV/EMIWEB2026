<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FormulatorRekapitulasiTrial\FormulatorRekapitulasiTrialApi;

Route::prefix('formulatorrekapitulasitrial')->group(function() {
    Route::apiResource('formulatorrekapitulasitrial', FormulatorRekapitulasiTrialApi::class);
});
