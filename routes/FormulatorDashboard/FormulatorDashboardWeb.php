<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Formulator\FormulatorDashboardController;

Route::middleware(['auth', 'web'])->group(function () {

    // ── Halaman (Web) ──────────────────────────────────────────────
    Route::get('/flm/activity-center', [FormulatorDashboardController::class, 'analyzerPage'])
        ->name('flm.activity-center')
        ->middleware('autotrack', 'permission:Pusat_Aktivitas_Formulator,VIEW');

    Route::get('/flm/ops-command', [FormulatorDashboardController::class, 'atasanPage'])
        ->name('flm.ops-command')
        ->middleware('autotrack', 'permission:Kinerja_Operasi_Formulator,VIEW');

    // ── API Analyzer ───────────────────────────────────────────────
    Route::get('/api/v1/flm/kpi-hari-ini',      [FormulatorDashboardController::class, 'getKpiHariIni']);
    Route::get('/api/v1/flm/tren-aktivitas',     [FormulatorDashboardController::class, 'getTrenAktivitas']);
    Route::get('/api/v1/flm/distribusi-jenis',   [FormulatorDashboardController::class, 'getDistribusiJenis']);
    Route::get('/api/v1/flm/aktivitas-terbaru',  [FormulatorDashboardController::class, 'getAktivitasTerbaru']);
    Route::get('/api/v1/flm/kpi-tat',            [FormulatorDashboardController::class, 'getKpiTat']);

    // ── API Atasan ─────────────────────────────────────────────────
    Route::get('/api/v1/flm/atasan/kpi-rekap',       [FormulatorDashboardController::class, 'getKpiRekap']);
    Route::get('/api/v1/flm/atasan/tren-bulanan',     [FormulatorDashboardController::class, 'getTrenBulanan']);
    Route::get('/api/v1/flm/atasan/per-aktivitas',    [FormulatorDashboardController::class, 'getPerAktivitas']);
    Route::get('/api/v1/flm/atasan/top-formulator',   [FormulatorDashboardController::class, 'getTopFormulator']);
    Route::get('/api/v1/flm/atasan/sampel-terbaru',   [FormulatorDashboardController::class, 'getSampelTerbaru']);
    Route::get('/api/v1/flm/atasan/foto-terbaru',     [FormulatorDashboardController::class, 'getFotoTerbaru']);
    Route::get('/api/v1/flm/atasan/status-validasi',  [FormulatorDashboardController::class, 'getStatusValidasi']);
});
