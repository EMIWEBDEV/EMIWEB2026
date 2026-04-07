<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FormulatorTrialSampel\FormulatorTrialSampelController;

Route::get('/trial-sampel', [FormulatorTrialSampelController::class, "index"]);
Route::get('/trial-sampel-testing', [FormulatorTrialSampelController::class, "indexTesting"]);
Route::get('/api/v1/formulator/{no_PO_Multiqr}/multi-print/{id_jenis_analisa}/resampling', [FormulatorTrialSampelController::class, 'getPoSampelMultiQrDetail']);
Route::get('/api/v1/formulator/detail-data-sampel/{no_sampel}', [FormulatorTrialSampelController::class, 'getDetailSampelUjiV2']);
Route::get('/api/v1/formulator/detail-data-sampel/testing/{no_sampel}', [FormulatorTrialSampelController::class, 'getDetailSampelUjiV2Testing']);
Route::get('/api/v1/formulator/{no_sampel}/{no_PO_Multiqr}/multi-print/{id_jenis_analisa}', [FormulatorTrialSampelController::class, 'getPoSampelMultiQrDetailV3']);
Route::get('/api/v1/formulator/{no_sampel}/{no_PO_Multiqr}/multi-print/{id_jenis_analisa}/testing', [FormulatorTrialSampelController::class, 'getPoSampelMultiQrDetailV3Testing']);
Route::get('/api/v1/formulator/detail/{no_sampel_multiqr}/multi-print/{id_jenis_analisa}/detail', [FormulatorTrialSampelController::class, 'getPoSampelMultiQrDetailForRumus']);
Route::get('/api/v1/formulator/detail-split/{no_sampel_multiqr}/multi-print/{id_jenis_analisa}/hasil', [FormulatorTrialSampelController::class, 'getDetailHasilSubmit']);
Route::get('/api/v1/formulator/detail-split/{no_PO_Multiqr}/not-print/{id_jenis_analisa}', [FormulatorTrialSampelController::class, 'getDetailHasilSubmitNotMultiQrCode']);
Route::get('/api/v1/formulator/detail-split/{no_PO_Multiqr}/not-rumus-noqr/{id_jenis_analisa}', [FormulatorTrialSampelController::class, 'getDetailHasilSubmitNotMultigQrCodeNorumus']);
Route::get('/api/v1/formulator/tracking-detail/{no_po_sampel}/{no_PO_Multiqr}/multi-print/{id_jenis_analisa}/formulator-history', [FormulatorTrialSampelController::class, 'getDataTrackingInformasi']);
Route::post('/api/v1/formulator/temp/uji-sampel/store-multi-rumus/sementara', [FormulatorTrialSampelController::class, 'storeMultiRumusSementara']);
Route::post('/api/v1/formulator/update-trial/multi-qrcode/{id_ujisample}/sementara', [FormulatorTrialSampelController::class, 'updateDataForDraft']);
Route::post('/api/v1/formulator/multi-qrcode/{no_sementara}/delete', [FormulatorTrialSampelController::class, 'deleteDataForDraft']);
Route::post('/api/v1/formulator/non-multi-qrcode/{no_sementara}/delete', [FormulatorTrialSampelController::class, 'deleteDataForDraftNotMultiQrCode']);
Route::post('/api/v1/formulator/hasil-trial/multi-qrcode', [FormulatorTrialSampelController::class, 'storeMultiRumusV2']);
Route::post('/api/v1/formulator/hasil-trial/multi-qrcode/testing', [FormulatorTrialSampelController::class, 'storeMultiRumusV2Testing']);

// route not multi qrcode 
Route::get('/api/v1/formulator/{no_po_sampel}/no-multi/{id_jenis_analisa}/uji-trial', [FormulatorTrialSampelController::class, 'getPoSampelNotMultiQrDetailForRumus']);
Route::get('/api/v1/formulator/detail-split/{no_PO_Multiqr}/not-print/{id_jenis_analisa}/uji-trial', [FormulatorTrialSampelController::class, 'getDetailHasilSubmitNotMultiQrCode']);
Route::get('/api/v1/formulator/tracking-detail/not-print/{no_po_sampel}/{id_jenis_analisa}/uji-trial', [FormulatorTrialSampelController::class, 'getDataTrackingInformasiNotMultiQrCode']);
Route::post('/api/v1/formulator/temp/uji-sampel/store-not-rumus/sementara', [FormulatorTrialSampelController::class, 'storeNotMultiRumusSementara']);
Route::post('/api/v1/formulator/update-trial/non-multi-qrcode/{id_ujisample}/sementara', [FormulatorTrialSampelController::class, 'updateDataForDraftNotMultiQr']);
Route::post('/api/v1/formulator/hasil-trial/non-multi-qrcode', [FormulatorTrialSampelController::class, 'storeMultiRumusNotMultiQrCode']);


// route tidak makai perhitungan uji trial
Route::post('/api/v1/formulator/hasil-trial/multi-qr-code/not-perhitungan/temp', [FormulatorTrialSampelController::class, 'storeMultiQrCodeNotPerhitunganSementara']);
Route::post('/api/v1/formulator/hasil-trial/single-qr-code/not-perhitungan/temp', [FormulatorTrialSampelController::class, 'storeNotMultiRumusSementaraNoPerhitunganJe']);
Route::post('/api/v1/formulator/hasil-trial/single-qr-code/not-perhitungan/{id_ujisample}/sementara', [FormulatorTrialSampelController::class, 'updateDataForDraftNoRumusNotMultiQr']);
Route::post('/api/v1/formulator/hasil-trial/single-qr-code/not-perhitungan/{no_sementara}/sementara', [FormulatorTrialSampelController::class, 'deleteDataForDraftNoRumusNotMultiQrCode']);
Route::post('/api/v1/formulator/hasil-trial/multi-qr-code/not-perhitungan', [FormulatorTrialSampelController::class, 'storeMultiQrCodeNotRumus']);
Route::post('/api/v1/formulator/hasil-trial/single-qr-code/not-perhitungan', [FormulatorTrialSampelController::class, 'storeNoRumusNotMultiQrCode']);

// validasi 
Route::get('/validasi-hasil/uji-trial', [FormulatorTrialSampelController::class, 'viewConfirmedAnalisis'])->middleware('autotrack');
Route::get('/validasi/hasil/uji-trial/confirmed-analisis/{no_sub_sampel}/multi/{id_jenis_analisa}', [FormulatorTrialSampelController::class, 'viewInformasiMultiQr'])->middleware('autotrack');
Route::get('/validasi/hasil/uji-trial/confirmed-analisis/{no_sampel}/single-qrCode', [FormulatorTrialSampelController::class, 'viewInformasiJenisAnalisaSingleQr'])->middleware('autotrack');
Route::get('/validasi/hasil/uji-trial/{no_sampel}/multi/{no_sub_sampel}/{id_jenis_analisa}/confirmed', [FormulatorTrialSampelController::class, 'viewInformasiJenisAnalisaMultiQr'])->middleware('autotrack');
Route::get('/validasi/hasil/uji-trial/confirmed-analisis/{no_sampel}/multi/{no_sub_sampel}/{id_jenis_analisa}', [FormulatorTrialSampelController::class, 'viewDataHasilAnalisaValidasi'])->middleware('autotrack');
Route::get('/validasi/hasil/uji-trial/confirmed-analisis/{no_sampel}/single-qr/{id_jenis_analisa}', [FormulatorTrialSampelController::class, 'viewDataHasilAnalisaValidasiSingleQrCode'])->middleware('autotrack');
Route::get('/api/v1/formulator/validasi-hasil/uji-trial/current', [FormulatorTrialSampelController::class, 'getDataConfirmedSelesaiV2']);
Route::get('/api/v1/formulator/validasi-hasil/uji-trial/{no_sub_sampel}/{id_jenis_analisa}', [FormulatorTrialSampelController::class, 'validasiDataMultiQrCodeV2']);
Route::get('/api/v1/formulator/validasi-hasil/uji-trial/no-pcs/{no_sampel}/single-qrcode', [FormulatorTrialSampelController::class, 'validasiDataJenisAnalisaSingleQrCodeV2']);
Route::get('/api/v1/formulator/validasi-hasil/uji-tial/multi-qrcode/{no_sampel}/{no_fak_sub_sampel}/{id_jenis_analisa}', [FormulatorTrialSampelController::class, 'validasiDataJenisAnalisaMultiQrCodeV2']);
Route::get('/api/v1/formulator/validasi-hasil/uji-trial/verifikasi-analisa/multi/{id_jenis_analisa}/{no_po_sampel}/{no_sub}', [FormulatorTrialSampelController::class, 'getVerifikasiHasilAnalisaPerhitunganByMultiV2']);
Route::get('/api/v1/formulator/validasi-hasil/uji-trial/verifikasi-analisa/single-qrcode/{id_jenis_analisa}/{no_po_sampel}', [FormulatorTrialSampelController::class, 'getVerifikasiHasilAnalisaPerhitunganBySingleQrV2']);
Route::get('/api/v1/formulator/validasi-hasil/uji-trial/sub/all/{no_sampel}/{id_jenis_analisa}', [FormulatorTrialSampelController::class, 'getDataSubSampelCurrentV1']);
Route::post('/api/v1/formulator/validasi-hasil/uji-trial/resampeling/reanalisis', [FormulatorTrialSampelController::class, 'resampelingAnalisa']);
Route::post('/api/v1/formulator/validasi-hasil/uji-trial/resampling-single/reanalisis', [FormulatorTrialSampelController::class, 'resampelingAnalisaSingle']);
Route::post('/api/v1/formulator/validasi-hasil/uji-trial/approve', [FormulatorTrialSampelController::class, 'storeConfirmedUjiSampelV2']);
Route::post('/api/v1/formulator/hasil-uji/berkas/foto/token/bulk', [FormulatorTrialSampelController::class, 'generateFotoToken']);
Route::get('/api/v1/formulator/berkas/stream/foto-uji/{key}', [FormulatorTrialSampelController::class, 'streamFoto']);

// uji ulang
Route::get("/uji-ulang/hasil-trial", [FormulatorTrialSampelController::class, "viewResamplingHomes"])->middleware('autotrack');
Route::get('/api/v1/formulator/resampling/current', [FormulatorTrialSampelController::class, 'getDataResamplingCurrent']);
Route::get('/uji-ulang/hasil-trial/resampling/reanalisa/{no_sampel}/{no_sub_sampel}/{id_jenis_analisa}/{no_resampling}/form', [FormulatorTrialSampelController::class, 'ViewUjiResampling'])->middleware('autotrack');
Route::get('/api/v1/lab/resampling/detail-data-sampel/{no_sampel}/{no_sub_sampel}/{no_resampling}/{id_jenis_analisa}/formulator', [FormulatorTrialSampelController::class, 'getDetailResamplingV1']);
Route::post('/api/v1/formulator/resampling/hasil-trial/multi-qr-code/store', [FormulatorTrialSampelController::class, 'storeMultiRumusResamplingV2']);
Route::post('/api/v1/formulator/resampling/hasil-trial/single-qr-code/store', [FormulatorTrialSampelController::class, 'storeMultiRumusNotMultiQrCodeResampling']);
Route::post('/api/v1/formulator/resampling/hasil-trial/multi-qr-code/not-rumus/store', [FormulatorTrialSampelController::class, 'storeMultiQrCodeNotRumusResampling']);
Route::post('/api/v1/formulator/resampling/hasil-trial/single-qr-code/not-rumus/store', [FormulatorTrialSampelController::class, 'storeNoRumusNotMultiQrCodeResampling']);

Route::get('/api/v1/formulator/uji-trial/{id_analisa}/parameter-perhitungan-old', [FormulatorTrialSampelController::class, 'getParameterAndPerhitunganOld']);
Route::get('/api/v1/formulator/uji-trial/{id_analisa}/parameter-perhitungan-old/testing', [FormulatorTrialSampelController::class, 'getParameterAndPerhitunganOldTesting']);

Route::get("/api/v1/formualtor/finalisasi/hasil-uji-trial/current", [FormulatorTrialSampelController::class, "getDataValidasiHasilAkhirDanCloseSampel"]);
Route::get('/api/v1/formualtor/finalisasi/hasil-uji-trial/{no_sampel}', [FormulatorTrialSampelController::class, 'validasiHasilAkhirDariValidasiAwalJenisAnalisaV1']);
Route::get('/api/v1/formulator-final-keputusan/detail/{id_jenis_analisa}/{no_po_sampel}/{no_sub}', [FormulatorTrialSampelController::class, 'getVerifikasiHasilAnalisaFinalKeputusanV1']);
Route::get('/api/v1/formulator-final-keputusan/detail/{id_jenis_analisa}/single-qrcode/{no_sampel}/final', [FormulatorTrialSampelController::class, 'getVerifikasiHasilAnalisaFinalKeputusanV1NoPcs']);

Route::get('/formulator/hasil-trial', [FormulatorTrialSampelController::class, 'viewHasilAnalisa'])->middleware('autotrack', 'menu_access');
Route::get('/formulator/hasil-trial/multi/{id_jenis_analisa}/{no_po_sampel}/{flag_multi}/{no_sub}', [FormulatorTrialSampelController::class, 'viewDetaiHasilMulti'])->middleware('autotrack');
Route::get('/formulator/hasil-trial/{id_jenis_analisa}/{no_po_sampel}/{flag_multi}', [FormulatorTrialSampelController::class, 'viewNestedSubHasilAnalisa']);
Route::get('/formulator/hasil-analisa/{id_jenis_analisa}', [FormulatorTrialSampelController::class, 'viewSubHasilAnalisa']);
Route::get('/api/v1/formulator/hasil-trial/uji-trial', [FormulatorTrialSampelController::class, 'getDataHasilAnalisaSelesai']);
Route::get('/api/v1/formulator/hasil-trial/{id_jenis_analisa}', [FormulatorTrialSampelController::class, 'getDataHasilAnalisaSelesaiByJenisAnalisa']);
Route::get('/api/v1/formulator/hasil-trial/sub/{id_jenis_analisa}/{no_po_sampel}', [FormulatorTrialSampelController::class, 'getDataHasilAnalisaSubPoByJenisAnalisa']);
Route::get('/api/v1/lab/confirmed-selesai/uji-sampel/by/{id_analisa}', [FormulatorTrialSampelController::class, 'getDataConfirmedSelesaiByJenisAnalisa']);
Route::get('/api/v2/lab/confirmed-selesai/uji-sampel/by/{id_analisa}', [FormulatorTrialSampelController::class, 'getDataConfirmedSelesaiByJenisAnalisaV2']);
Route::get('/api/v1/lab/no-uji/sampel/sub/all/{no_sampel}/{id_jenis_analisa}', [FormulatorTrialSampelController::class, 'getDataSubSampelCurrentV1']);
Route::get('/api/v1/formulator/hasil-trial/uji-trial/multi/{id_jenis_analisa}/{no_po_sampel}/{flag_multi}/{no_sub}', [FormulatorTrialSampelController::class, 'getDataHasilAnalisaPerhitunganByMultiV2']);
Route::get('/api/v1/formulator/hasil-trial/uji-trial/no-multi/{id_jenis_analisa}/{no_po_sampel}', [FormulatorTrialSampelController::class, 'getDataHasilAnalisaPerhitunganByNoMultiV2']);

Route::post('/api/v2/formulator/rekap-sampel/pdf', [FormulatorTrialSampelController::class, 'downloadRekapSampelByPdfV2']);
Route::post('/api/v2/formulator/rekap-sampel/excell', [FormulatorTrialSampelController::class, 'downloadRekapSampelByExcellV2']);
Route::post('/api/v2/formulator/rekap-sampel/excell/pra-finalisasi', [FormulatorTrialSampelController::class, 'downloadRekapSampelByExcellV2Prafinal']);
Route::post('/api/v1/formulator/rekap-sampel/pdf', [FormulatorTrialSampelController::class, 'downloadRekapSampelByPdf']);
Route::post('/api/v1/formulator/download-rekap/analisa', [FormulatorTrialSampelController::class, 'downloadRekapSampel']);
Route::post('/api/v1/formulator/download-rekap/analisa/pra-finalisasi', [FormulatorTrialSampelController::class, 'downloadRekapSampelPrafinalisasi']);
