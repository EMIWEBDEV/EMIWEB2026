<?php

use App\Http\Controllers\AbsensiController;
use App\Http\Controllers\BarangAnalisaController;
use App\Http\Controllers\BindingIdentityController;
use App\Http\Controllers\BindingJenisAnalisaController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\IdentityController;
use App\Http\Controllers\JenisAnalisaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\JenisAnalisaBerkalaController;
use App\Http\Controllers\Master\DashboardController;
use App\Http\Controllers\MasterMesinController;
use App\Http\Controllers\MasterSerialController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\MesinAnalisaController;
use App\Http\Controllers\PengajuanBukaUlangUjiSampelController;
use App\Http\Controllers\PerhitunganController;
use App\Http\Controllers\POSampleController;
use App\Http\Controllers\PrinterTemplatesControllerController;
use App\Http\Controllers\ProgressAnalisaSampelController;
use App\Http\Controllers\QuisyController;
use App\Http\Controllers\ResamplingController;
use App\Http\Controllers\RoleMenuController;
use App\Http\Controllers\StandarRentangController;
use App\Http\Controllers\SubMenuController;
use App\Http\Controllers\UjiSampelController;
use App\Http\Controllers\UjiValidasiFinalController;

Route::get('/', [AuthController::class, 'form_login'])->name('login.form')->middleware('guest', 'autotrack');
Route::post('/proses_login', [AuthController::class, 'proses_login'])->name('proses_login')->middleware('guest');
Route::get('/logout-clear', [AuthController::class, 'logoutclear']);


Route::middleware(['auth'])->group(function () {
    Route::get('/master-template-printer', [PrinterTemplatesControllerController::class, 'masterViewPrinter'])->middleware('menu_access', 'autotrack');
    Route::get('/master-template-printer-items', [PrinterTemplatesControllerController::class, 'masterViewPrinterItems'])->middleware('menu_access', 'autotrack');
    Route::get('/master-template-printer-transaksi', [PrinterTemplatesControllerController::class, 'masterViewPrinterTransaksi'])->middleware('menu_access', 'autotrack');
    Route::get('/api/v1/master-template-printer/option/master-template', [PrinterTemplatesControllerController::class, 'getDataMasterPrintOptions']);
    Route::post('/api/v1/master-template-printer/current', [PrinterTemplatesControllerController::class, 'getDataCurrent']);
    Route::post('/api/v1/master-template-printer/store', [PrinterTemplatesControllerController::class, 'storeMasterTemplatePrinter']);
    Route::post('/api/v1/master-template-printer/items/store', [PrinterTemplatesControllerController::class, 'storeMultipleItems']);
    Route::post('/api/v1/master-template-printer/set-first', [PrinterTemplatesControllerController::class, 'setFirst']);
    Route::post('/api/v1/master-template-printer/toggle', [PrinterTemplatesControllerController::class, 'toggleTemplate']);
    Route::get('/api/v1/master-template-printer/current-template', [PrinterTemplatesControllerController::class, 'getCurrentTemplates']);
    Route::get("/tentang", [DashboardController::class, 'Tentang'])->name('tentang.index')->middleware('autotrack');
    Route::get('/api/v1/jenis-analisa/by-berkala', [JenisAnalisaController::class, 'getDataJenisAnalisaByBerkala']);
    Route::post('/proses_change/user', [AuthController::class, 'changePasswordSubmitRegisterSampel']);
    Route::post('/logout', [AuthController::class, 'logout']);
});


Route::middleware(['auth', 'web'])->group(function () {
    Route::get('/home', [QuisyController::class, 'index'])->name('quisy.index')->middleware('menu_access', 'autotrack');
    Route::post('/ganti-password', [AuthController::class, 'gantiPassword'])->name('gantipassword.store');
    Route::post('/ganti-pin', [AuthController::class, 'gantiPin'])->name('gantiPin.store');
    Route::get('/master-akun/tambah-akun', [AuthController::class, 'form_register'])->name('register.form')->middleware('menu_access', 'autotrack');
    Route::get('/api/v1/master-akun/current', [AuthController::class, 'getDataAkunPengguna'])->middleware('menu_access');
    Route::get('/api/v1/master-akun/search', [AuthController::class, 'searchDataPengguna'])->middleware('menu_access');
    Route::get('/master-akun', [AuthController::class, 'ViewMasterAkun'])->name('masterakun.index')->middleware('menu_access', 'autotrack');
    Route::post('/proses_register', [AuthController::class, 'proses_register']);
    Route::put('/proses-update/akun', [AuthController::class, 'proses_update_akun']);
    Route::post('/api/v1/master-akun/status-akun/{UserId}', [AuthController::class, 'updateStatusAkun']);
    Route::get('/dashboard', [DashboardController::class, 'dashboard_page'])->name('dashboard')->middleware('menu_access', 'autotrack');
    Route::get('/api/v1/dashboard/current-hari-ini', [DashboardController::class, 'getDataHariIniWidget'])->middleware('menu_access');
    Route::get('/api/v1/dashboard/analyzer/kpi-tat', [DashboardController::class, 'getKpiTurnAroundTime']);
    Route::get('/api/v1/dashboard/analyzer/aktivitas-terbaru', [DashboardController::class, 'getAktivitasTerbaru']);
    Route::get('/api/v1/dashboard/analyzer/kinerja-analis', [DashboardController::class, 'getKinerjaAnalis']);
    Route::get('/api/v1/dashboard/current-all-time', [DashboardController::class, 'getTotalWidget'])->middleware('menu_access');
    Route::get('/api/v1/dashboard/grafik/jumlah-uji-perhari', [DashboardController::class, 'grafikAnalisaData'])->middleware('menu_access');
    Route::get('/api/v1/dashboard/grafik/frekuensi-uji-sampel-berdasarkan-jenis-analisa', [DashboardController::class, 'getFrekuensiUjiSampelBerdasarkanJenisAnalisa'])->middleware('menu_access');
    Route::get('/api/v1/dashboard/grafik/pie-status-uji-sampel', [DashboardController::class, 'getPieStatusPenyelesaianUji'])->middleware('menu_access');
    Route::get('/api/v1/dashboard/grafik/scatter-sebaran-hasil', [DashboardController::class, 'getScatterSebaranHasilAnalisa'])->middleware('menu_access');

    Route::get('/po/{computer_keys}', [QuisyController::class, 'getPoListWithCompletionStatus'])->name('api.podata');
    Route::get('/api/v2/po/{computer_keys}', [QuisyController::class, 'getPoListWithCompletionStatusV2']);
    Route::post('/api/v1/close-po/by-qa', [QuisyController::class, 'closePoByProduksi']);
    Route::get('/total-po-belum-selesai/{computer_keys}', [QuisyController::class, 'getTotalPOBelumSelesai'])->name('api.getTotalPOBelumSelesai');
    Route::get('/total-sp-belum-selesai/{computer_keys}', [QuisyController::class, 'getTotalSPPOBelumSelesai'])->name('api.getTotalSPPOBelumSelesai');
    Route::get('/split-po/{id}/{computer_keys}', [QuisyController::class, 'getSplitPo']);
    Route::get('/batch-po/{no_transaksi}/{computer_keys}', [QuisyController::class, 'getBatchPo']);
    Route::get('/search-nama-mesin', [QuisyController::class, 'getNamaMesin']);
    Route::get('/api/v1/history/registrasi-sampel', [QuisyController::class, 'getDataHistoriRegistrasiSampel']);
    Route::get('/cetak-ulang-qrcode', [QuisyController::class, 'HalamanCtkUlangQrcode'])->name('quisy.cetakulangqrcode')->middleware('menu_access');
    Route::post('/api/v1/cetak-ulang/qrcode/{no_sampel}/{id_mesin}', [QuisyController::class, 'cetakUlangQrCode'])->name('quisy.cetakUlangQrCodestore');
    Route::get('/api/v1/grafik/registrasi-sampel/tren-uji-sampel', [QuisyController::class, 'LineChartTrenBeratSampel']);
    Route::get('/api/v1/grafik/registrasi-sampel/jumlah-sampel-permesin', [QuisyController::class, 'JumlahSampelPerMesin']);
    Route::get('/api/v1/grafik/registrasi-sampel/distribusi-tujuan-pengujian', [QuisyController::class, 'DistribusiSampelTujuanPengujian']);

    // MASTER IDENTITY CONTROLLER 
    Route::get("/data/identity-komputer", [IdentityController::class, "getDataIdentity"])->middleware('menu_access');
    Route::get("/data-search/identity-komputer", [IdentityController::class, "search"])->middleware('menu_access');
    Route::get("/identity-ssidevo", [IdentityController::class, "index"])->name('identity.index')->middleware('menu_access');
    Route::post("/identity-ssidevo/gaskeun", [IdentityController::class, "store"])->name('identity.store');
    Route::put("/api/v1/identity-ssidevo/{id}", [IdentityController::class, "update"])->name('identity.update')->middleware('menu_access');
    Route::post('/generate-key', [IdentityController::class, 'generateKey'])->name('identity.generate');

    Route::get("/biding-identity", [BindingIdentityController::class, "index"])->name('bidingidentity.index')->middleware('menu_access');
    Route::get("/fetch/biding-identity", [BindingIdentityController::class, "getDataBinding"]);
    Route::get("/fetch/biding-identity/mesin-list", [BindingIdentityController::class, "getMesinList"]);
    Route::get("/fetch/biding-identity/identity-computer", [BindingIdentityController::class, "getIdentityComputerList"]);
    Route::get("/fetch/biding-identity/search/{id}/data", [BindingIdentityController::class, "searchMesinByIdentity"]);
    Route::get("/biding-identity/page/{id}", [BindingIdentityController::class, "show"])->middleware('menu_access');
    Route::get("/fetch/biding-identity/{id}", [BindingIdentityController::class, "getDataDetailIdentity"]);
    Route::get("/biding-identity/create/mesin-identity", [BindingIdentityController::class, "create"])->name('bidingidentity.create')->middleware('menu_access');
    Route::post("/biding-identity/store", [BindingIdentityController::class, "store"])->name('bidingidentity.store');
    Route::get("/biding-identity/edit-form/{id}", [BindingIdentityController::class, "edit"])->name('bidingidentity.edit')->middleware('menu_access');
    Route::put("/biding-identity/update/{id}", [BindingIdentityController::class, "update"])->name('bidingidentity.update');

    Route::post('/po-sample/create', [POSampleController::class, 'store'])->name('POsample.store');
    Route::post('/qr-print/send', [POSampleController::class, 'sendToPrinter']);

    Route::get('/machines/{computerKey}/{noSplitPo}/{noBatch}', [QuisyController::class, 'getMachinesByComputerKeys']);

    // lab controller 
    Route::get('/lab/home', [UjiSampelController::class, 'index'])->middleware('autotrack', 'menu_access');
    Route::get('/lab/resampling/current', [ResamplingController::class, 'index'])->middleware('autotrack');
    Route::get('/lab/resampling/reanalisa/{no_sampel}/{no_sub_sampel}/{id_jenis_analisa}/{no_resampling}/form', [ResamplingController::class, 'ViewUjiResampling'])->middleware('autotrack');
    Route::get('/lab/confirmed-analisis', [UjiSampelController::class, 'viewConfirmedAnalisis'])->middleware('autotrack', 'menu_access');
    Route::get('/lab/confirmed-analisis/v2/{no_sub_sampel}/multi/{id_jenis_analisa}', [UjiSampelController::class, 'viewInformasiMultiQr'])->middleware('autotrack');
    Route::get('/lab/confirmed-analisis/v2/{no_sampel}/multi/{no_sub_sampel}/{id_jenis_analisa}/confirmed', [UjiSampelController::class, 'viewInformasiJenisAnalisaMultiQr'])->middleware('autotrack');
    Route::get('/lab/confirmed-analisis/v2/{no_sampel}/single-qrCode', [UjiSampelController::class, 'viewInformasiJenisAnalisaSingleQr'])->middleware('autotrack');
    Route::get('/lab/confirmed-analisis/v2/{no_sampel}/multi/{no_sub_sampel}/{id_jenis_analisa}', [UjiSampelController::class, 'viewDataHasilAnalisaValidasi'])->middleware('autotrack');
    Route::get('/lab/confirmed-analisis/v2/{no_sampel}/single-qr/{id_jenis_analisa}', [UjiSampelController::class, 'viewDataHasilAnalisaValidasiSingleQrCode'])->middleware('autotrack');
    Route::get('/lab/hasil-analisa', [UjiSampelController::class, 'viewHasilAnalisa'])->middleware('autotrack', 'menu_access');
    Route::get('/lab/hasil-analisa/{id_jenis_analisa}', [UjiSampelController::class, 'viewSubHasilAnalisa'])->middleware('autotrack', 'menu_access');
    Route::get('/lab/hasil-analisa/{id_jenis_analisa}/{no_po_sampel}/{flag_multi}', [UjiSampelController::class, 'viewNestedSubHasilAnalisa'])->middleware('autotrack', 'menu_access');
    Route::get('/lab/hasil-analisa/multi/{id_jenis_analisa}/{no_po_sampel}/{flag_multi}/{no_sub}', [UjiSampelController::class, 'viewDetaiHasilMulti'])->middleware('autotrack','menu_access');
    Route::get('/lab/data-sampel/{no_sampel}', [UjiSampelController::class, 'getDataParameterUjiSampelByNoSampel'])->name('labujisampel.apidataujisampel');
    Route::get('/lab/detail-data-sampel/{no_sampel}', [UjiSampelController::class, 'getDetailSampelUji'])->middleware('menu_access');
    Route::get('/api/v2/lab/detail-data-sampel/{no_sampel}', [UjiSampelController::class, 'getDetailSampelUjiV2'])->middleware('menu_access');
    Route::get('/api/v1/lab/resampling/detail-data-sampel/{no_sampel}/{no_sub_sampel}/{no_resampling}/{id_jenis_analisa}', [UjiSampelController::class, 'getDetailResamplingV1']);
    Route::get('/api/v1/lab/resampling-detail-data-sampel/{No_Sampel_Resampling_Origin}/{No_Sampel_Resampling}/{Id_Jenis_Analisa}', [ResamplingController::class, 'getDetailSampelResampling']);
    Route::get('/api/v1/lab/hasil-analisa/multi/{id_jenis_analisa}/{no_po_sampel}/{flag_multi}/{no_sub}', [UjiSampelController::class, 'getDataHasilAnalisaPerhitunganByMulti']);
    Route::get('/api/v2/lab/hasil-analisa/multi/{id_jenis_analisa}/{no_po_sampel}/{flag_multi}/{no_sub}', [UjiSampelController::class, 'getDataHasilAnalisaPerhitunganByMultiV2']);
    Route::get('/api/v2/lab/verifikasi-analisa/multi/{id_jenis_analisa}/{no_po_sampel}/{no_sub}', [UjiSampelController::class, 'getVerifikasiHasilAnalisaPerhitunganByMultiV2']);
    Route::get('/api/v2/lab/verifikasi-analisa/single-qrcode/{id_jenis_analisa}/{no_po_sampel}', [UjiSampelController::class, 'getVerifikasiHasilAnalisaPerhitunganBySingleQrV2']);
    Route::get('/api/v1/verifikasi-final-keputusan/{id_jenis_analisa}/{no_po_sampel}/{no_sub}', [UjiSampelController::class, 'getVerifikasiHasilAnalisaFinalKeputusanV1']);
    Route::get('/api/v1/hasil-final-keputusan/{id_jenis_analisa}/single-qrcode/{no_sampel}', [UjiSampelController::class, 'getVerifikasiHasilAnalisaFinalKeputusanV1NoPcs']);
    Route::get('/api/v1/lab/hasil-analisa/no-multi/{id_jenis_analisa}/{no_po_sampel}', [UjiSampelController::class, 'getDataHasilAnalisaPerhitunganByNoMulti']);
    Route::get('/api/v2/lab/hasil-analisa/no-multi/{id_jenis_analisa}/{no_po_sampel}', [UjiSampelController::class, 'getDataHasilAnalisaPerhitunganByNoMultiV2']);
    Route::get('/api/v1/lab/confirmed-selesai/uji-sampel', [UjiSampelController::class, 'getDataConfirmedSelesai']);
    Route::get('/api/v1/resampling/current', [ResamplingController::class, 'getDataResamplingCurrent']);
    Route::get('/api/v2/lab/confirmed-selesai/uji-sampel', [UjiSampelController::class, 'getDataConfirmedSelesaiV2']);
    Route::get('/api/v1/lab/validasi-hasil/akhir', [UjiSampelController::class, 'getDataValidasiHasilAkhirDanCloseSampel']);
    Route::get('/api/v1/lab/hasil-akhir/close/current-final', [UjiSampelController::class, 'getDataValidasiAkhirCloseKeputusan']);
    Route::get('/api/v2/lab/validasi-selesai/uji-sampel/{no_sub_sampel}/{id_jenis_analisa}', [UjiSampelController::class, 'validasiDataMultiQrCodeV2']);
    // Route::get('/api/v1/lab/validasi-akhir-close/uji-sampel/{no_sub_sampel}', [UjiSampelController::class, 'validasiHasilAkhirDariValidasiAwal']);
    
    Route::get('/api/v2/lab/validasi-selesai/uji-sampel/{no_sampel}/{no_fak_sub_sampel}/{id_jenis_analisa}/confirmed-pcs', [UjiSampelController::class, 'validasiDataJenisAnalisaMultiQrCodeV2']);
    Route::get("/api/v1/lab/mesin/export-hasil-analisa", [UjiSampelController::class, "getMesinForCetakLaporan"]);
    Route::get('/api/v2/lab/validasi-selesai/uji-sampel/no-pcs/{no_sampel}/single-qrcode-s', [UjiSampelController::class, 'validasiDataJenisAnalisaSingleQrCodeV2']);

    Route::get('/api/v1/lab/validasi-akhir-close/uji-sampel/{no_sampel}', [UjiSampelController::class, 'validasiHasilAkhirDariValidasiAwalJenisAnalisaV1']);
    Route::get('/api/v1/lab/hasil-analisa/uji-sampel', [UjiSampelController::class, 'getDataHasilAnalisaSelesai'])->middleware('menu_access');
    Route::get('/api/v1/lab/hasil-analisa/{id_jenis_analisa}', [UjiSampelController::class, 'getDataHasilAnalisaSelesaiByJenisAnalisa']);
    Route::get('/api/v1/lab/hasil-analisa/sub/{id_jenis_analisa}/{no_po_sampel}', [UjiSampelController::class, 'getDataHasilAnalisaSubPoByJenisAnalisa']);
    Route::get('/api/v1/lab/confirmed-selesai/uji-sampel/by/{id_analisa}', [UjiSampelController::class, 'getDataConfirmedSelesaiByJenisAnalisa']);
    Route::get('/api/v2/lab/confirmed-selesai/uji-sampel/by/{id_analisa}', [UjiSampelController::class, 'getDataConfirmedSelesaiByJenisAnalisaV2']);
    Route::get('/api/v1/lab/no-uji/sampel/sub/all/{no_sampel}/{id_jenis_analisa}', [UjiSampelController::class, 'getDataSubSampelCurrentV1']);
    Route::post('/api/v1/lab/resampeling/reanalisis', [UjiSampelController::class, 'resampelingAnalisa']);
    Route::post('/api/v1/lab/resampling-single/reanalisis', [UjiSampelController::class, 'resampelingAnalisaSingle']);
    Route::get('/fetch/lab/{id_mesin}/{id_analisa}/parameter-perhitungan', [UjiSampelController::class, 'getParameterAndPerhitungan']);
    Route::get('/fetch/lab/lama/{id_analisa}/parameter-perhitungan-old', [UjiSampelController::class, 'getParameterAndPerhitunganOld']);
    Route::get('/api/v1/{no_PO_Multiqr}/multi-print/{id_jenis_analisa}', [UjiSampelController::class, 'getPoSampelMultiQrDetail']);
    Route::get('/api/v2/{no_PO_Multiqr}/multi-print/{id_jenis_analisa}', [UjiSampelController::class, 'getPoSampelMultiQrDetailV2']);
    Route::get('/api/v3/{no_sampel}/{no_PO_Multiqr}/multi-print/{id_jenis_analisa}', [UjiSampelController::class, 'getPoSampelMultiQrDetailV3']);
    Route::get('/api/v1/{no_po_sampel}/no-multi/{id_jenis_analisa}', [UjiSampelController::class, 'getPoSampelNotMultiQrDetailForRumus']);
    Route::get('/api/v1/detail/{no_PO_Multiqr}/multi-print/{id_jenis_analisa}', [UjiSampelController::class, 'getPoSampelMultiQrDetailForRumus']);
    Route::get('/api/v1/detail-split/{no_PO_Multiqr}/multi-print/{id_jenis_analisa}', [UjiSampelController::class, 'getDetailHasilSubmit']);
    Route::get('/api/v1/detail-split/{no_PO_Multiqr}/not-print/{id_jenis_analisa}', [UjiSampelController::class, 'getDetailHasilSubmitNotMultiQrCode']);
    Route::get('/api/v1/detail-split/{no_PO_Multiqr}/not-rumus-noqr/{id_jenis_analisa}', [UjiSampelController::class, 'getDetailHasilSubmitNotMultiQrCodeNorumus']);
    Route::get('/api/v1/tracking-detail/{no_po_sampel}/{no_PO_Multiqr}/multi-print/{id_jenis_analisa}/analisa', [UjiSampelController::class, 'getDataTrackingInformasi']);
    Route::get('/api/v1/tracking-detail/not-print/{no_po_sampel}/{id_jenis_analisa}/analisa', [UjiSampelController::class, 'getDataTrackingInformasiNotMultiQrCode']);

    Route::get('/jenis-analisa', [JenisAnalisaController::class, 'index'])->name('jenisanalisa.index')->middleware('menu_access', 'autotrack');
    Route::get('/fetch/jenis-analisa-current', [JenisAnalisaController::class, 'getDataAllGropedBy'])->middleware('menu_access');
    Route::get('/fetch/detail/jenis-analisa/{jenis_analisa}', [JenisAnalisaController::class, 'getDetailJenisAnalisa']);
    Route::get('/search/detail/jenis-analisa/{jenis_analisa}', [JenisAnalisaController::class, 'searchDetailJenisAnalisa']);
    Route::get('/jenis-analisa/for-select', [JenisAnalisaController::class, 'getDataJenisAnalisa']);
    Route::get('/jenis-analisa-current/for-select', [JenisAnalisaController::class, 'getJenisAnalisaCurrentAlls']);
    Route::get('/jenis-analisa/qc-for-select/{id}', [JenisAnalisaController::class, 'getDataQualityControl']);
    Route::get('/jenis-analisa/detail/page-current/{jenis_analisa}', [JenisAnalisaController::class, 'show'])->name('jenisanalisa.show')->middleware('menu_access', 'autotrack');
    Route::get('/jenis-analisa/create', [JenisAnalisaController::class, 'create'])->name('jenisanalisa.create')->middleware('menu_access', 'autotrack');
    Route::post('/jenis-analisa/store', [JenisAnalisaController::class, 'store'])->name('jenisanalisa.store');
    Route::put('/jenis-analisa/update/{id}', [JenisAnalisaController::class, 'update'])->name('jenisanalisa.update')->middleware('menu_access');

    Route::get('/binding-jenis-analisa', [BindingJenisAnalisaController::class, 'index'])->name('bindingjenisanalisa.index')->middleware('menu_access', 'autotrack');
    Route::get('/fetch/binding-jenis-analisa', [BindingJenisAnalisaController::class, 'getListDataJenisAnalisa'])->middleware('menu_access');
    Route::get('/api/v1/binding-jenis-analisa/option/jenis-analisa', [BindingJenisAnalisaController::class, 'getOptionJenisAnalisa']);
    Route::get('/api/v1/binding-jenis-analisa/option/quality-control', [BindingJenisAnalisaController::class, 'getOptionQualityControl']);
    Route::get('/api/v1/binding-jenis-analisa/by/{Id}', [BindingJenisAnalisaController::class, 'getDataDetailById']);
    Route::get('/fetch/binding-jenis-analisa/detail/{id_jenis_analisa}', [BindingJenisAnalisaController::class, 'getDetailParameterJenisAnalisa'])->middleware('menu_access');
    Route::get('/fetch/search/binding-jenis-analisa/{id_jenis_analisa}', [BindingJenisAnalisaController::class, 'searchDetailParameter'])->middleware('menu_access');
    Route::get('/binding-jenis-analisa/{id_jenis_analisa}', [BindingJenisAnalisaController::class, 'show'])->name('bindingjenisanalisa.show')->middleware('menu_access', 'autotrack');
    Route::get('/binding-jenis-analisa/create/form', [BindingJenisAnalisaController::class, 'create'])->name('bindingjenisanalisa.create')->middleware('menu_access', 'autotrack');
    Route::get('/binding-jenis-analisa/edit/form/{id}', [BindingJenisAnalisaController::class, 'edit'])->name('bindingjenisanalisa.edit')->middleware('menu_access');
    Route::post('/api/v1/binding-jenis-analisa/store', [BindingJenisAnalisaController::class, 'store'])->name('bindingjenisanalisa.store')->middleware('menu_access');
    Route::put('/api/v1/binding-jenis-analisa/update/{id}', [BindingJenisAnalisaController::class, 'update'])->name('bindingjenisanalisa.update')->middleware('menu_access');


    Route::get('/perhitungan-rumus', [PerhitunganController::class, 'index'])->name('perhitungan.index')->middleware('menu_access', 'autotrack');
    Route::get('/fetch/perhitungan-rumus', [PerhitunganController::class, 'getDataRumusPerhitungan'])->middleware('menu_access');
    Route::get('/perhitungan-rumus/show/{id_jenis_analisa}', [PerhitunganController::class, 'show'])->name('perhitungan.show')->middleware('autotrack', 'menu_access');
    Route::get('/fetch/perhitungan-rumus/show/{id_jenis_analisa}', [PerhitunganController::class, 'getDetailData'])->middleware('menu_access');
    Route::get('/search/perhitungan-rumus/show/{id_jenis_analisa}', [PerhitunganController::class, 'searchDetailData'])->middleware('menu_access');
    Route::get('/perhitungan/rumus/create', [PerhitunganController::class, 'create'])->name('perhitungan.create')->middleware('menu_access', 'autotrack');
    Route::get('/perhitungan/rumus/edit/{id}', [PerhitunganController::class, 'edit'])->name('perhitungan.edit')->middleware('menu_access', 'autotrack');
    Route::post('/perhitungan/rumus/store', [PerhitunganController::class, 'store'])->name('perhitungan.store')->middleware('menu_access');
    Route::put('/perhitungan/rumus/update/{id}', [PerhitunganController::class, 'update'])->name('perhitungan.update')->middleware('menu_access');

    Route::get('/barang-jenis-analisa', [BarangAnalisaController::class, 'index'])->name('barangjenisanalis.index')->middleware('menu_access', 'autotrack');
    Route::get('/barang-jenis-analisa/show/{id}', [BarangAnalisaController::class, 'show'])->name('barangjenisanalis.show')->middleware('menu_access', 'autotrack');
    Route::get('/api/v1/barang-jenis-analisa/current', [BarangAnalisaController::class, 'getDataBarangAnalisa'])->middleware('menu_access');
    Route::get('/api/v1/barang-jenis-analisa/{id_jenis_analisa}/search', [BarangAnalisaController::class, 'searchDetailBarangAnalisa']);
    Route::get('/api/v1/detail/barang-jenis-analisa/{id_jenis_analisa}', [BarangAnalisaController::class, 'getDetailBarangAnalisa']);
    Route::get('/api/v1/barang-analisa/option/jenis-analisa', [BarangAnalisaController::class, 'getDataJenisAnalisa']);
    Route::get('/api/v1/barang-analisa/option/varian-barang', [BarangAnalisaController::class, 'getDataVarianBarang']);
    Route::get('/api/v1/barang-analisa/option/mesin', [BarangAnalisaController::class, 'getDataMesin']);
    Route::get('/api/v1/barang-analisa/option/user', [BarangAnalisaController::class, 'getDataUser']);
    Route::get('/barang-jenis/analisa/create', [BarangAnalisaController::class, 'create'])->name('barangjenisanalis.create')->middleware('menu_access', 'autotrack');
    Route::post('/api/v1/barang-jenis/analisa/store', [BarangAnalisaController::class, 'store'])->name('barangjenisanalis.store')->middleware('menu_access');

    Route::post('/uji-sampel/store', [UjiSampelController::class, 'storeNotMultiRumus']);
    Route::post('/uji-sampel/store/sementara-draft-notrumus', [UjiSampelController::class, 'storeNotMultiAndNoQrSementara']);
    Route::post('/uji-sampel/store-multi-rumus', [UjiSampelController::class, 'storeMultiRumus']);
    Route::post('/api/v2/uji-sampel/store-multi-rumus', [UjiSampelController::class, 'storeMultiRumusV2']);
    // Route::post('/api/v2/uji-sampel/store-multi-rumus', [UjiSampelController::class, 'storeMultiRumusV2']);
    Route::post('/api/v1/uji-sampel/store-multi-rumus/resampling', [UjiSampelController::class, 'storeMultiRumusResamplingV2']);
    Route::post('/uji-sampel/store-multi-qrcode-not-rumus-perhitungan', [UjiSampelController::class, 'storeMultiQrCodeNotRumus']);
    Route::post('/api/v1/uji-sampel/store-multi-qrcode-not-rumus-perhitungan/resampling', [UjiSampelController::class, 'storeMultiQrCodeNotRumusResampling']);
    Route::post('/uji-sampel/store-multi-rumus-not-multipleqr', [UjiSampelController::class, 'storeMultiRumusNotMultiQrCode']);
    Route::post('/uji-sampel/store-multi-rumus-not-multipleqr/resampling', [UjiSampelController::class, 'storeMultiRumusNotMultiQrCodeResampling']);
    Route::post('/uji-sampel/store-not-rumus-not-multipleqr', [UjiSampelController::class, 'storeNoRumusNotMultiQrCode']);
    Route::post('/uji-sampel/store-not-rumus-not-multipleqr/resampling', [UjiSampelController::class, 'storeNoRumusNotMultiQrCodeResampling']);
    Route::post('/uji-sampel/store-multi-rumus/sementara', [UjiSampelController::class, 'storeMultiRumusSementara']);
    Route::post('/uji-sampel/store-multi-qr-code-no-perhitungan/sementara', [UjiSampelController::class, 'storeMultiQrCodeNotPerhitunganSementara']);
    Route::post('/uji-sampel/store-multi-qr-code-no-perhitungan/sementara/resampling', [UjiSampelController::class, 'storeMultiQrCodeNotPerhitunganSementaraResampling']);
    Route::post('/uji-sampel/store-not-rumus/sementara', [UjiSampelController::class, 'storeNotMultiRumusSementara']);
    Route::post('/uji-sampel/store-not-rumus/sementara/no-perhitungan/no-qr', [UjiSampelController::class, 'storeNotMultiRumusSementaraNoPerhitunganJe']);
    Route::post('/uji-sampel/store-multi-rumus/sementara/change-update/{id_ujisample}', [UjiSampelController::class, 'updateDataForDraft']);
    Route::post('/uji-sampel/store-not-rumus/sementara/change-update/{id_ujisample}', [UjiSampelController::class, 'updateDataForDraftNotMultiQr']);
    Route::post('/uji-sampel/store-not-rumus-not-mutipleqr/sementara/change-update/{id_ujisample}', [UjiSampelController::class, 'updateDataForDraftNoRumusNotMultiQr']);
    Route::post('/uji-sampel/store-multi-rumus/sementara/hapus-data/{no_sementara}', [UjiSampelController::class, 'deleteDataForDraft']);
    Route::post('/uji-sampel/store-not-rumus/sementara/hapus-data/{no_sementara}', [UjiSampelController::class, 'deleteDataForDraftNotMultiQrCode']);
    Route::post('/uji-sampel/store-not-rumus-nomultiple-qr/sementara/hapus-data/{no_sementara}', [UjiSampelController::class, 'deleteDataForDraftNoRumusNotMultiQrCode']);
    Route::post('/uji-sampel/confirmed', [UjiSampelController::class, 'storeConfirmedUjiSampel']);
    Route::post('/api/v2/uji-sampel/confirmed', [UjiSampelController::class, 'storeConfirmedUjiSampelV2']);
    Route::put('/uji-sampel/finalisasi-hasil/analisa/{no_sampel}', [UjiSampelController::class, 'finalisasiNoPoSampel']);
    Route::post('/api/v1/download-rekap/analisa', [UjiSampelController::class, 'downloadRekapSampel']);

    Route::get('/hasil-analisa/validasi-close-sampel', [UjiValidasiFinalController::class, 'index'])->middleware('autotrack');
    Route::get('/hasil-analisa/produk-rilis-all', [UjiValidasiFinalController::class, 'viewProdukSiapRilis'])->middleware('autotrack');
    Route::get('/pembatalan-po/selesai-diclose', [QuisyController::class, 'viewPelepasanPoClose'])->middleware('autotrack');
    Route::get('/hasil-analisa/validasi-close-sampel/{No_Sub_Sampel}', [UjiValidasiFinalController::class, 'viewInformasiMultiQrs'])->middleware('autotrack');
    Route::get('/hasil-analisa/validasi-close-sampel/{No_Sampel}/{No_Sub_Sampel}', [UjiValidasiFinalController::class, 'viewInformasiJenisAnalisaMultiQrS'])->middleware('autotrack');
    Route::get('/hasil-analisa/validasi-close-sampel/moment-final/{no_sampel}/multi/{no_sub_sampel}/{id_jenis_analisa}', [UjiValidasiFinalController::class, 'viewDataHasilAnalisaValidasiR'])->middleware('autotrack');
    Route::post('/api/v1/hasil-analisa-close/finalisasi/{no_sampel}', [UjiValidasiFinalController::class, 'store']);
    Route::get('/api/v1/hasil-analisa/produk-rilis-current', [UjiValidasiFinalController::class, 'getDataCurrentHasilFinalValidasi']);
    Route::get('/api/v1/po-done/close-by-produksi', [QuisyController::class, 'getDataCurrentPoYangDiClose']);
    Route::post('/api/v1/quisy-pembatalan/pelepasan-po-close/{no_po}', [QuisyController::class, 'BukaKembaliPoYangSudahDiClose']);

    Route::get('/lembur',[AbsensiController::class, 'indexLembur'])->name('absensi.lembur');
    Route::get('/lembur/create-form',[AbsensiController::class, 'createLembur'])->name('absensi.lemburcreate');
    Route::get('/lembur-sesudah',[AbsensiController::class, 'indexLemburSesudah'])->name('absensi.lemburSesudahForm');
    Route::post('/lembur/add-submitssion',[AbsensiController::class, 'lemburSubmissionStore'])->name('absensi.lembur.addsubmision');
    Route::post('/lembur/confirm-done/submit/{id}', [AbsensiController::class, 'lemburDoneConfirmedStore'])->name('absensi.lembur.confirmdonesubmision');

    Route::get('/image/bukti-dukung/{filename}', [AbsensiController::class, 'show'])->name("image.showbuktidukung");
    Route::get('/image/bukti-selesai/{filename}', [AbsensiController::class, 'showBuktiSelesaiConfirmed'])->name("image.showconfirmeddone");

    Route::get("/mesin-analisa", [MesinAnalisaController::class, 'index'])->name("mesinanalisa.index")->middleware('menu_access', 'autotrack');
    Route::get("/api/v1/mesin-analisa/current", [MesinAnalisaController::class, 'getDataMesinAnalisa']);
    Route::get("/api/v1/mesin-analisa/list", [MesinAnalisaController::class, 'getListMesinAnalisa']);
    Route::get("/api/v1/mesin-analisa/search", [MesinAnalisaController::class, 'searchMesinAnalisa']);
    Route::post("/api/v1/mesin-analisa/store", [MesinAnalisaController::class, 'store']);
    Route::put("/api/v1/mesin-analisa/update/{id}", [MesinAnalisaController::class, 'update']);


    Route::get("/master-mesin", [MasterMesinController::class, 'index'])->name("master-mesin.index")->middleware('menu_access', 'autotrack');
    Route::get("/api/v1/master-mesin/current", [MasterMesinController::class, 'getDataMasterMesin'])->middleware('menu_access');
    Route::get("/api/v1/divisi-mesin/current", [MasterMesinController::class, 'getDataDivisiMesin']);
    Route::get("/api/v1/divisi-mesin/by/{id}", [MasterMesinController::class, 'getDataMasterMesinById']);
    Route::get("/api/v1/master-mesin/search", [MasterMesinController::class, 'searchMasterMesin'])->middleware('menu_access');
    Route::post("/api/v1/master-mesin/store", [MasterMesinController::class, 'store'])->middleware('menu_access');
    Route::put("/api/v1/divisi-mesin/by-update/{id}", [MasterMesinController::class, 'update'])->middleware('menu_access');
    
    Route::get("/master/menu", [MenuController::class, 'index'])->middleware('menu_access', 'autotrack');
    Route::get("/api/v1/master-menu/current", [MenuController::class, 'getDataMenu'])->middleware('menu_access');
    Route::get("/api/v1/master-menu", [MenuController::class, 'getDataMenuJson'])->middleware('menu_access');
    Route::get("/api/v1/master-menu/search", [MenuController::class, 'searchDataMenu'])->middleware('menu_access');
    Route::post("/api/v1/master-menu/store", [MenuController::class, 'store'])->middleware('menu_access');
    Route::put("/api/v1/master-menu/update/{Id_Menu}", [MenuController::class, 'update'])->middleware('menu_access');

    Route::get("/api/v1/sub-menu/current", [SubMenuController::class, 'getDataSubMenuJson']);
    
    Route::get("/api/v1/pengguna/current", [AuthController::class, 'getDataAkunPenggunaJson'])->middleware('menu_access');
    Route::get("/role/menu/{UserId}", [RoleMenuController::class, 'index'])->middleware('menu_access');
    Route::get("/role/home-menu", [RoleMenuController::class, 'viewAksesPage'])->middleware('menu_access');
    Route::get("/api/v1/role-menu/current/{UserId}", [RoleMenuController::class, 'getDataMenu'])->middleware('menu_access');
    Route::get("/api/v1/role-menu/search/{UserId}", [RoleMenuController::class, 'searchDataMenu'])->middleware('menu_access');
    Route::get("/api/v1/role-menu/home-current", [RoleMenuController::class, 'getDataRoleMenu'])->middleware('menu_access');
    Route::post("/api/v1/role-menu/store", [RoleMenuController::class, 'store'])->middleware('menu_access');
    Route::put("/api/v1/role-menu/update/{id_role_menu}", [RoleMenuController::class, 'update'])->middleware('menu_access');

    Route::get("/serial-kabel", [MasterSerialController::class, 'index']);

    Route::get("/api/v1/sub-jenis-analisa/current", [JenisAnalisaBerkalaController::class, 'getData']);
    Route::get("/api/v1/sub-jenis-analisa/search", [JenisAnalisaBerkalaController::class, 'search']);
    Route::get("/api/v1/jenis-analisa-berkala/current", [JenisAnalisaBerkalaController::class, 'getJenisAnalisaBerkala']);
    Route::get("/api/v1/jenis-analisa-rutin/current", [JenisAnalisaBerkalaController::class, 'getJenisAnalisaRutin']);
    Route::post("/api/v1/jenis-analisa-rutin/store", [JenisAnalisaBerkalaController::class, 'store']);
    Route::put("/api/v1/jenis-analisa-rutin/put/{id}", [JenisAnalisaBerkalaController::class, 'update']);
    Route::get("/sub-jenis-analisa/all", [JenisAnalisaBerkalaController::class, 'index']);
    Route::get("/sub-jenis-analisa/create", [JenisAnalisaBerkalaController::class, 'create']);
    
    Route::get("/notifikasi/current", [DashboardController::class, 'getNotifikasiCurrent']);
    Route::get("/api/v1/notifikasi/current", [DashboardController::class, 'getListNotifikasi']);
    Route::get("/api/v1/notifikasi-count/no-read", [DashboardController::class, 'getCountNoRead']);
    Route::put("/api/v1/notifikasi/update/all-read", [DashboardController::class, 'updateFlagBacaRead']);

    Route::post('/rekap-sampel/pdf', [UjiSampelController::class, 'downloadRekapSampelByPdf'])->name('rekap-sampel.pdf');
    Route::post('/rekap-sampel/pdf/particle-size', [UjiSampelController::class, 'downloadRekapSampelByPdfParticleSize']);
    Route::post('/rekap-sampel/excell/particle-size', [UjiSampelController::class, 'downloadRekapSampelByExcelParticleSize']);
    Route::post('/api/v2/rekap-sampel/pdf', [UjiSampelController::class, 'downloadRekapSampelByPdfV2']);
    Route::post('/api/v2/rekap-sampel/excell', [UjiSampelController::class, 'downloadRekapSampelByExcellV2']);

    Route::get("/standar-hasil-analisa/current", [StandarRentangController::class, 'index'])->middleware('autotrack');
    Route::get("/standar-hasil-analisa/tambah", [StandarRentangController::class, 'create'])->middleware('autotrack');
    Route::post("/api/v1/standar-hasil-analisa/store", [StandarRentangController::class, 'store']);
    Route::get("/api/v1/standar-rentang-analisa/current", [StandarRentangController::class, 'getAllCurrent']);
    Route::get("/api/v1/jenis-analisa/standar", [StandarRentangController::class, 'getJenisAnalisa']);
    Route::get("/api/v1/daftar-barang/standar/{id_jenis_analisa}", [StandarRentangController::class, 'getBarangStandarRentang']);
    Route::get("/api/v1/list-mesin/standar/{id_jenis_analisa}", [StandarRentangController::class, 'getDaftarMesinStandar']);
    Route::get("/api/v1/list-kolom-perhitungan/standar/{id_jenis_analisa}", [StandarRentangController::class, 'getPerhitunganListStandar']);

    Route::get("/pengajuan-uji-buka-sampel/current", [PengajuanBukaUlangUjiSampelController::class, 'index'])->middleware('autotrack');
    Route::get("/api/v1/pengajuan-uji-buka-sampel/current", [PengajuanBukaUlangUjiSampelController::class, 'getDataBukUlangUjiSampel']);
    Route::get("/api/v1/list-pengajuan-uji-buka-sampel/no-sampel/current", [PengajuanBukaUlangUjiSampelController::class, 'getDataPoSampel']);
    Route::get("/api/v1/pengajuan-uji-buka-sampel/search", [PengajuanBukaUlangUjiSampelController::class, 'search']);
    Route::post("/pengajuan-uji-buka-sampel/store", [PengajuanBukaUlangUjiSampelController::class, 'store']);
    Route::put("/pengajuan-uji-buka-sampel/update/{Id_Pengajuan_Buka_Ulang}", [PengajuanBukaUlangUjiSampelController::class, 'update']);
    Route::get("/progress-sistem/uji-analisa", [ProgressAnalisaSampelController::class, 'index'])->middleware('autotrack');
    Route::get("/api/v1/progress-sistem/uji-analisa/current", [ProgressAnalisaSampelController::class, 'getDataCurrent']);
});

require base_path('routes/FormulatorRegistrasi/FormulatorRegistrasiWeb.php');
require base_path('routes/FormulatorTrialSampel/FormulatorTrialSampelWeb.php');
require base_path('routes/FormulatorTrialSampel/FormulatorTrialSampelWeb.php');
require base_path('routes/KlasifikasiJenisAnalisa/KlasifikasiJenisAnalisaWeb.php');

                    
require base_path('routes/FormulatorFinalisasi/FormulatorFinalisasiWeb.php');

require base_path('routes/FormulatorValidasiHirarki/FormulatorValidasiHirarkiWeb.php');

require base_path('routes/FormulatorCetakUlangQrCode/FormulatorCetakUlangQrCodeWeb.php');

require base_path('routes/FormulatorRekapitulasiTrial/FormulatorRekapitulasiTrialWeb.php');

require base_path('routes/FormulatorStatusData/FormulatorStatusDataWeb.php');
require base_path('routes/developer/fransDevEvo.php');
require base_path('routes/developer/ridhoDevEvo.php');