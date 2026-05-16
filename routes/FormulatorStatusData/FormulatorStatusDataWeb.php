<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FormulatorStatusData\FormulatorStatusDataController;

Route::get('/formulator/status-data/sampel', [FormulatorStatusDataController::class, "index"])->middleware('autotrack', 'permission:Status_Trial,VIEW');
Route::get('/api/v1/formulator/status-data/sampel/current', [FormulatorStatusDataController::class, "statusDataSampel"])->middleware('permission:Status_Trial,VIEW');
