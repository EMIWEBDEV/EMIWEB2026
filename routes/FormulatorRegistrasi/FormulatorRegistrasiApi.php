<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FormulatorRegistrasi\FormulatorRegistrasiApi;

Route::prefix('formulatorregistrasi')->group(function() {
    Route::apiResource('formulatorregistrasi', FormulatorRegistrasiApi::class);
});
