<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KlasifikasiJenisAnalisa\KlasifikasiJenisAnalisaApi;

Route::prefix('klasifikasijenisanalisa')->group(function() {
    Route::apiResource('klasifikasijenisanalisa', KlasifikasiJenisAnalisaApi::class);
});
