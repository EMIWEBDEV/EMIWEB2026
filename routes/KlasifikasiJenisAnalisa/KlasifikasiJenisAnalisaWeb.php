<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KlasifikasiJenisAnalisa\KlasifikasiJenisAnalisaController;

Route::middleware(['auth', 'web','role:FLM'])->group(function () {
    Route::get('/api/v1/klasifikasi-analisa/option/current', [KlasifikasiJenisAnalisaController::class, 'getOptionKlasifikasiJenisAnalisa']);
});
