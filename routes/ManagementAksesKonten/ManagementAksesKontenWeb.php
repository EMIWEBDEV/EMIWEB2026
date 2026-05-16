<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ManagementAksesKonten\ManagementAksesKontenController;

Route::get("/management-role-akses", [ManagementAksesKontenController::class, "index"]);
Route::get("/api/v1/management-role-akses/current", [ManagementAksesKontenController::class, "getDataGroupBy"]);
Route::get("/api/v1/management-role-akses/options/klasifikasi", [ManagementAksesKontenController::class, "getDataKlasifikasiAksiJson"]);
Route::get("/api/v1/management-role-akses/options/pageaccess", [ManagementAksesKontenController::class, "getDataPageAccessJson"]);
Route::get("/api/v1/management-role-akses/options/jenis-analisa", [ManagementAksesKontenController::class, "getDataJenisAnalisa"]);
Route::post("/api/v1/management-role-akses/store", [ManagementAksesKontenController::class, "store"]);
Route::post('/api/v1/management-role-akses/toggle', [ManagementAksesKontenController::class, 'toggleAccess']);
Route::post('/api/v1/management-role-akses/toggle-content', [ManagementAksesKontenController::class, 'toggleContentAccess']);