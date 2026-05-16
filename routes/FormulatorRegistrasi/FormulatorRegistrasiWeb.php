<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FormulatorRegistrasi\FormulatorRegistrasiController;

Route::get("/registrasi-material", [FormulatorRegistrasiController::class, 'index'])->middleware('autotrack', 'permission:Registrasi_Material,VIEW');
Route::get('/api/v1/formulator/registrasi-material/po/{computer_keys}', [FormulatorRegistrasiController::class, 'getPoListWithCompletionStatusV2'])->middleware('permission:Registrasi_Material,VIEW');
Route::get('/api/v1/formulator/registrasi-material/split-po/{id}/{computer_keys}', [FormulatorRegistrasiController::class, 'getSplitPo'])->middleware('permission:Registrasi_Material,VIEW');
Route::get('/api/v1/formulator/registrasi-material/batch/{no_transaksi}/{computer_keys}', [FormulatorRegistrasiController::class, 'getBatchPo'])->middleware('permission:Registrasi_Material,VIEW');
Route::get('/api/v1/formulator/registrasi-material/machine/{computerKey}/{noSplitPo}/{noBatch}', [FormulatorRegistrasiController::class, 'getMachinesByComputerKeys'])->middleware('permission:Registrasi_Material,VIEW');
Route::post('/api/v1/formulator/registrasi-material/store', [FormulatorRegistrasiController::class, 'store'])->middleware('permission:Registrasi_Material,CREATE');
