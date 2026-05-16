<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KlasifikasiJenisAnalisa\KlasifikasiJenisAnalisaController;

Route::get('/api/v1/klasifikasi-analisa/option/current', [KlasifikasiJenisAnalisaController::class, 'getOptionKlasifikasiJenisAnalisa']);
