<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FormulatorValidasiHirarki\FormulatorValidasiHirarkiController;

Route::get("/validasi/pra-finalisasi", [FormulatorValidasiHirarkiController::class, "index"])->middleware('autotrack');
Route::get("/api/v1/validasi/pra-finalisasi/current-home", [FormulatorValidasiHirarkiController::class, "getDataValidasi"]);
Route::get("/api/v1/validasi/pra-finalisasi/informasi/validasi-desktop", [FormulatorValidasiHirarkiController::class, "getInformasiDesktop"]);
Route::get("/api/v1/validasi/pra-finalisasi/options/klasifikasi-lab", [FormulatorValidasiHirarkiController::class, "getKlasifikasiAktivitasLab"]);
Route::get("/api/v1/validasi/pra-finalisasi/detail/by/{no_sampel}", [FormulatorValidasiHirarkiController::class, "getDetailValidasi"]);
Route::post("/api/v1/validasi/pra-finalisasi/store-hirarki", [FormulatorValidasiHirarkiController::class, "storeValidasiHirarki"]);
Route::post("/api/v1/formulator/validasi/pra-finalisasi/cancel", [FormulatorValidasiHirarkiController::class, "cancelPraFinal"]);
Route::post("/api/v1/formulator/validasi/pra-finalisasi/approve", [FormulatorValidasiHirarkiController::class, "finalizePraFinal"]);

Route::get("/hasil-trial/dibatalkan", [FormulatorValidasiHirarkiController::class, "getViewBatal"])->middleware('autotrack');
Route::get("/api/v1/hasil-trial/dibatalkan/current", [FormulatorValidasiHirarkiController::class, "getHasilTrialDibatalkan"]);