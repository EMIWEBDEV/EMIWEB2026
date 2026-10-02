<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VerifikasiHasilAnalisa\VerifikasiHasilAnalisaController;
use App\Http\Controllers\VerifikasiHasilAnalisa\TrialUiController;
use App\Http\Controllers\VerifikasiHasilAnalisa\FinalisasiSandboxController;
use App\Http\Controllers\VerifikasiHasilAnalisa\ValidasiSandboxController;
use App\Http\Controllers\VerifikasiHasilAnalisa\MockupSiklusController;

/*
|--------------------------------------------------------------------------
| VERIFIKASI HASIL ANALISA  —  step antara Validasi dan Finalisasi
|--------------------------------------------------------------------------
| Modul terisolasi: hanya membaca tabel lama, menulis hanya ke tabel
| N_EMI_LAB_Verifikasi_*. Tidak menyentuh satu pun fitur yang sudah ada.
*/

/*
|--------------------------------------------------------------------------
| TRIAL UI — sandbox alur penuh, TANPA login aplikasi
|--------------------------------------------------------------------------
| Halaman dan endpoint sesi sengaja berada DI LUAR middleware 'auth':
| pengguna memilih user sandbox dari halaman ini, lalu sesi login asli
| dibentuk oleh TrialUiController. Setelah itu modul-modul lama berjalan
| apa adanya karena Auth::user() dan user_permissions sudah terisi.
*/
Route::get('/trial-ui', [TrialUiController::class, 'index']);
Route::get('/api/v1/trial-ui/user',   [TrialUiController::class, 'daftarUser']);
Route::get('/api/v1/trial-ui/sesi',   [TrialUiController::class, 'sesi']);
Route::post('/api/v1/trial-ui/masuk', [TrialUiController::class, 'masuk']);
Route::post('/api/v1/trial-ui/keluar',[TrialUiController::class, 'keluar']);


/*
|--------------------------------------------------------------------------
| Modul verifikasi — memakai 'auth' seperti menu LIMS lainnya
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    // Halaman di dalam layout aplikasi
    Route::get('/verifikasi-hasil-analisa', [VerifikasiHasilAnalisaController::class, 'index']);

    // Profil verifikator = user yang sedang login (tanpa pemilihan terpisah)
    Route::get('/api/v1/verifikasi-hasil-analisa/profil',
        [VerifikasiHasilAnalisaController::class, 'profil']);

    // Master jenis keputusan (setuju penuh / bersyarat / tolak)
    Route::get('/api/v1/verifikasi-hasil-analisa/master-keputusan',
        [VerifikasiHasilAnalisaController::class, 'masterKeputusan']);

    // Daftar kerja & keputusan
    Route::get('/api/v1/verifikasi-hasil-analisa/daftar-kerja',
        [VerifikasiHasilAnalisaController::class, 'daftarKerja']);
    Route::post('/api/v1/verifikasi-hasil-analisa/keputusan',
        [VerifikasiHasilAnalisaController::class, 'simpanKeputusan']);

    // Jejak
    Route::get('/api/v1/verifikasi-hasil-analisa/riwayat/{no_sampel}/{kode_aktivitas}',
        [VerifikasiHasilAnalisaController::class, 'riwayat']);
    Route::get('/api/v1/verifikasi-hasil-analisa/lifecycle/{no_sampel}',
        [VerifikasiHasilAnalisaController::class, 'lifecycle']);
    Route::get('/api/v1/verifikasi-hasil-analisa/ringkasan-tahapan',
        [VerifikasiHasilAnalisaController::class, 'ringkasanTahapan']);
});

/*
|--------------------------------------------------------------------------
| SANDBOX — Finalisasi Trial Produksi (modul pembaharuan)
|--------------------------------------------------------------------------
| Rute terpisah dari /finalisai/trial-produksi milik modul lama. Sidebar
| mengarahkan ke sini hanya bila sesi bertanda 'sandbox_trial', sehingga
| pengguna yang login lewat jalur biasa tetap membuka modul lama.
|
| Middleware 'auth' saja, tanpa permission modul lama: kewenangan sandbox
| sudah dibentuk TrialUiController saat sesi dibuat.
*/
Route::middleware(['auth'])->group(function () {
    Route::get('/sandbox/finalisasi-trial-produksi',
        [FinalisasiSandboxController::class, 'index']);
    Route::get('/api/v1/sandbox/finalisasi-trial-produksi/daftar',
        [FinalisasiSandboxController::class, 'daftar']);
    Route::get('/api/v1/sandbox/finalisasi-trial-produksi/master-keputusan',
        [FinalisasiSandboxController::class, 'masterKeputusan']);
    Route::post('/api/v1/sandbox/finalisasi-trial-produksi/keputusan',
        [FinalisasiSandboxController::class, 'simpanKeputusan']);
});

/*
|--------------------------------------------------------------------------
| SANDBOX — Validasi Trial Produksi (modul pembaharuan)
|--------------------------------------------------------------------------
| Terpisah dari /validasi-trial/produksi milik modul lama. Hanya sesi
| /trial-ui dengan tahap VAL (ratna, jati, roby); vengine ditolak dan sesi
| login biasa dikembalikan ke modul lama ('sandbox.tahap:VAL').
|
| Penyimpanan validasi memakai fungsi modul lama apa adanya
| (UjiSampelController::storeConfirmedUjiSampelV2); uji ulang memakai
| endpoint resampling modul lama langsung dari layar.
*/
Route::middleware(['auth', 'sandbox.tahap:VAL'])->group(function () {
    Route::get('/sandbox/validasi-trial-produksi',
        [ValidasiSandboxController::class, 'index']);
    Route::get('/api/v1/sandbox/validasi-trial-produksi/daftar',
        [ValidasiSandboxController::class, 'daftar']);
    Route::get('/api/v1/sandbox/validasi-trial-produksi/rincian',
        [ValidasiSandboxController::class, 'rincian']);
    Route::post('/api/v1/sandbox/validasi-trial-produksi/validasi',
        [ValidasiSandboxController::class, 'validasi']);
    // Contoh data — database demo saja.
    Route::post('/api/v1/sandbox/validasi-trial-produksi/sampel-dummy',
        [ValidasiSandboxController::class, 'buatDummy']);
    Route::post('/api/v1/sandbox/validasi-trial-produksi/sampel-dummy/hapus',
        [ValidasiSandboxController::class, 'hapusDummy']);
});

/*
|--------------------------------------------------------------------------
| MOCKUP SIKLUS — Validasi -> Verifikasi -> Finalisasi -> Hasil Analisa
|--------------------------------------------------------------------------
| Datanya disimpan di SESSION STORAGE browser; server hanya menyajikan
| halaman, membuat contoh data dari master (baca saja), dan master keputusan.
| Tidak ada yang ditulis ke database. Hanya sesi /trial-ui.
*/
Route::middleware(['auth'])->group(function () {
    Route::get('/mockup/validasi', [MockupSiklusController::class, 'validasi'])
        ->middleware('sandbox.tahap:VAL');
    Route::get('/mockup/verifikasi', [MockupSiklusController::class, 'verifikasi'])
        ->middleware('sandbox.tahap:VER');
    Route::get('/mockup/finalisasi', [MockupSiklusController::class, 'finalisasi'])
        ->middleware('sandbox.tahap:FIN');
    Route::get('/mockup/hasil-analisa', [MockupSiklusController::class, 'hasil'])
        ->middleware('sandbox.tahap:SEMUA');

    Route::middleware('sandbox.tahap:SEMUA')->group(function () {
        Route::get('/api/v1/mockup/master', [MockupSiklusController::class, 'master']);
        Route::post('/api/v1/mockup/contoh', [MockupSiklusController::class, 'contoh']);
    });
});
