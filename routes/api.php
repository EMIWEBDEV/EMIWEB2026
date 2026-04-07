<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


require base_path('routes/FormulatorRegistrasi/FormulatorRegistrasiApi.php');

require base_path('routes/FormulatorTrialSampel/FormulatorTrialSampelApi.php');

require base_path('routes/KlasifikasiJenisAnalisa/KlasifikasiJenisAnalisaApi.php');

require base_path('routes/FormulatorFinalisasi/FormulatorFinalisasiApi.php');

require base_path('routes/FormulatorValidasiHirarki/FormulatorValidasiHirarkiApi.php');

require base_path('routes/FormulatorCetakUlangQrCode/FormulatorCetakUlangQrCodeApi.php');

require base_path('routes/FormulatorRekapitulasiTrial/FormulatorRekapitulasiTrialApi.php');

require base_path('routes/FormulatorStatusData/FormulatorStatusDataApi.php');
