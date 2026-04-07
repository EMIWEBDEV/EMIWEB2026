<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FormulatorFinalisasi\FormulatorFinalisasiController;

Route::get('/finalisasi/formulator/hasil-uji-trial', [FormulatorFinalisasiController::class, 'index'])->middleware('autotrack');
Route::get('/finalisasi/formulator/hasil-uji-trial/by/{no_sample}/{no_split}', [FormulatorFinalisasiController::class, 'viewInformasiJenisAnalisaMultiQrS'])->middleware('autotrack');
Route::get('/finalisasi/formulator/hasil-uji-trial/by/detail/{no_sampel}/multi/{no_sub_sampel}/{id_jenis_analisa}', [FormulatorFinalisasiController::class, 'viewDataHasilAnalisaValidasiR'])->middleware('autotrack');
Route::post('/api/v1/finalisasi/formulator/hasil-uji-trial/{no_sampel}', [FormulatorFinalisasiController::class, 'store']);