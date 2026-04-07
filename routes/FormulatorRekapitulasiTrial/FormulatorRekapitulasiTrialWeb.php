<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FormulatorRekapitulasiTrial\FormulatorRekapitulasiTrialController;

Route::get("/formulator/rekapitulasi", [FormulatorRekapitulasiTrialController::class, 'index']);
