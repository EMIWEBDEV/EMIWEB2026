<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FormulatorRegistrasi\FormulatorRegistrasiController;

Route::middleware(['auth', 'role:FLM'])->group(function () {
    Route::get("/registrasi-material", [FormulatorRegistrasiController::class, 'index']);
    Route::get('/api/v1/formulator/registrasi-material/po/{computer_keys}', [FormulatorRegistrasiController::class, 'getPoListWithCompletionStatusV2']);
    Route::get('/api/v1/formulator/registrasi-material/split-po/{id}/{computer_keys}', [FormulatorRegistrasiController::class, 'getSplitPo']);
    Route::get('/api/v1/formulator/registrasi-material/batch/{no_transaksi}/{computer_keys}', [FormulatorRegistrasiController::class, 'getBatchPo']);
    Route::get('/api/v1/formulator/registrasi-material/machine/{computerKey}/{noSplitPo}/{noBatch}', [FormulatorRegistrasiController::class, 'getMachinesByComputerKeys']);
    Route::post('/api/v1/formulator/registrasi-material/store', [FormulatorRegistrasiController::class, 'store']);
});
