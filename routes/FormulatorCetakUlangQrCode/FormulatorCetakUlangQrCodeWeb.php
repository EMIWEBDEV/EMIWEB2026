<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FormulatorCetakUlangQrCode\FormulatorCetakUlangQrCodeController;

Route::get('/formulator/cetak-ulang', [FormulatorCetakUlangQrCodeController::class, "HalamanCtkUlangQrcode"]);
Route::get('/api/v1/formulator/history/registrasi-sampel', [FormulatorCetakUlangQrCodeController::class, 'getDataHistoriRegistrasiSampel']);
Route::get('/api/v1/formulator/grafik/registrasi-sampel/tren-uji-sampel', [FormulatorCetakUlangQrCodeController::class, 'LineChartTrenBeratSampel']);
Route::get('/api/v1/formulator/grafik/registrasi-sampel/jumlah-sampel-permesin', [FormulatorCetakUlangQrCodeController::class, 'JumlahSampelPerMesin']);
Route::get('/api/v1/formulator/grafik/registrasi-sampel/distribusi-tujuan-pengujian', [FormulatorCetakUlangQrCodeController::class, 'DistribusiSampelTujuanPengujian']);
Route::post('/api/v1/formulator/cetak-ulang/qrcode/{no_sampel}/{id_mesin}', [FormulatorCetakUlangQrCodeController::class, 'cetakUlangQrCode']);