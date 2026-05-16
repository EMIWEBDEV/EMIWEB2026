<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FinalisasiLabProduksiTrial\FinalisasiLabProduksiTrialApi;

Route::prefix('api')->group(function() {
    Route::apiResource('finalisasilabproduksitrial', FinalisasiLabProduksiTrialApi::class)->names('api.finalisasilabproduksitrial');
});
