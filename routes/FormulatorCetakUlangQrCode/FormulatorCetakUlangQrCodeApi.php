<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FormulatorCetakUlangQrCode\FormulatorCetakUlangQrCodeApi;

Route::prefix('formulatorcetakulangqrcode')->group(function() {
    Route::apiResource('formulatorcetakulangqrcode', FormulatorCetakUlangQrCodeApi::class);
});
