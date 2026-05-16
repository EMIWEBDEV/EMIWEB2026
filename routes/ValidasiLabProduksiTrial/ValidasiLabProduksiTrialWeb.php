<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ValidasiLabProduksiTrial\ValidasiLabProduksiTrialController;

Route::get('/validasi-trial/produksi', [ValidasiLabProduksiTrialController::class, 'index'])->middleware('autotrack', 'permission:Validasi_Trial_Produksi,VIEW');
Route::get('/api/v1/validasi-trial/produksi/current', [ValidasiLabProduksiTrialController::class, 'getDataConfirmedSelesaiV2'])->middleware('permission:Validasi_Trial_Produksi,VIEW');
