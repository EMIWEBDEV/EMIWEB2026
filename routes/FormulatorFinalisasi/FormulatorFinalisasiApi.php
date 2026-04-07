<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FormulatorFinalisasi\FormulatorFinalisasiApi;

Route::prefix('formulatorfinalisasi')->group(function() {
    Route::apiResource('formulatorfinalisasi', FormulatorFinalisasiApi::class);
});
