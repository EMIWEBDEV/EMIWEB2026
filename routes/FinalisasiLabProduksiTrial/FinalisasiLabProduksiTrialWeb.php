<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FinalisasiLabProduksiTrial\FinalisasiLabProduksiTrialController;

Route::get("/finalisai/trial-produksi", [FinalisasiLabProduksiTrialController::class, 'index'])->middleware('autotrack', 'permission:Finalisasi_Trial_Produksi,VIEW');
Route::get('/finalisai/trial-produksi/validasi-close-sampel/{No_Sub_Sampel}', [FinalisasiLabProduksiTrialController::class, 'viewInformasiMultiQrs'])->middleware('autotrack', 'permission:Finalisasi_Trial_Produksi,DETAIL');
Route::get('/finalisai/trial-produksi/validasi-close-sampel/{No_Sampel}/{No_Sub_Sampel}', [FinalisasiLabProduksiTrialController::class, 'viewInformasiJenisAnalisaMultiQrS'])->middleware('autotrack', 'permission:Finalisasi_Trial_Produksi,DETAIL');
Route::get("/api/v1/finalisai/trial-produksi/current", [FinalisasiLabProduksiTrialController::class, 'getDataValidasiHasilAkhirDanCloseSampel']);
Route::get("/api/v1/finalisai/trial-produksi/hasil-validasi/{no_sampel}", [FinalisasiLabProduksiTrialController::class, 'validasiHasilAkhirDariValidasiAwalJenisAnalisaV1']);
// Sample Lifecycle — dibaca dari Hasil_Uji_Validasi_Final + _Detail_Final,
// bukan dari Log_Aksi yang menghasilkan entri berulang.
Route::get("/api/v1/finalisai/trial-produksi/lifecycle/{no_sampel}", [FinalisasiLabProduksiTrialController::class, 'getSampleLifecycle']);
Route::post('/api/v1/finalisai/trial-produksi/hasil-analisa-close/finalisasi/bulk', [FinalisasiLabProduksiTrialController::class, 'storeBulk'])->middleware('permission:Finalisasi_Trial_Produksi,FINALISASI');
Route::post('/api/v1/finalisai/trial-produksi/hasil-analisa-close/finalisasi/{no_sampel}', [FinalisasiLabProduksiTrialController::class, 'store'])->middleware('permission:Finalisasi_Trial_Produksi,FINALISASI');