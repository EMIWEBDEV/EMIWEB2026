<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FormulatorValidasiHirarki\FormulatorValidasiHirarkiApi;

Route::prefix('formulatorvalidasihirarki')->group(function() {
    Route::apiResource('formulatorvalidasihirarki', FormulatorValidasiHirarkiApi::class);
});
