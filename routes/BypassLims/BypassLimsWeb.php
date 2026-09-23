<?php

use App\Http\Controllers\BypassLims\BypassLimsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Panel Bypass LIMS - LIMS Toolkit
|--------------------------------------------------------------------------
|
| Akses: /bypass-lims?gembok-secret=<BYPASS_LIMS_SECRET dari .env>
|
| Tidak memakai middleware `auth`; gerbangnya murni token 64 karakter di env.
| Bila BYPASS_LIMS_SECRET kosong atau token salah, seluruh rute di bawah ini
| membalas 404 supaya keberadaannya tidak terekspos.
|
*/

Route::middleware(['web', 'bypass-lims'])->group(function () {

    Route::get('/bypass-lims', [BypassLimsController::class, 'index'])
        ->name('bypass-lims.index');

    Route::prefix('api/v1/bypass-lims')->group(function () {

        // Fitur 1 - Tambah sub sampel
        Route::post('/sub-sampel/cari', [BypassLimsController::class, 'cariSampelSubSampel']);
        Route::post('/sub-sampel/preview', [BypassLimsController::class, 'previewSubSampel']);
        Route::post('/sub-sampel/tambah', [BypassLimsController::class, 'tambahSubSampel']);

        // Fitur 2 - Batalkan registrasi sampel
        Route::post('/registrasi/cari', [BypassLimsController::class, 'cariSampelRegistrasi']);
        Route::post('/registrasi/batalkan', [BypassLimsController::class, 'batalkanRegistrasi']);

        // Fitur 3 - Batalkan uji sampel
        Route::post('/uji-sampel/cari', [BypassLimsController::class, 'cariUjiSampel']);
        Route::post('/uji-sampel/batalkan', [BypassLimsController::class, 'batalkanUjiSampel']);

        // Fitur 4 - Atur template printer
        Route::get('/printer/templates', [BypassLimsController::class, 'daftarTemplatePrinter']);
        Route::post('/printer/detail', [BypassLimsController::class, 'detailTemplatePrinter']);
        Route::post('/printer/simpan', [BypassLimsController::class, 'simpanTemplatePrinter']);
        Route::post('/printer/test-print', [BypassLimsController::class, 'testPrintTemplate']);
    });
});
