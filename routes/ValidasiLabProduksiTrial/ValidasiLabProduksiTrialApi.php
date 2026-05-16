<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ValidasiLabProduksiTrial\ValidasiLabProduksiTrialApi;

Route::prefix('api')->group(function() {
    Route::apiResource('validasilabproduksitrial', ValidasiLabProduksiTrialApi::class)->names('api.validasilabproduksitrial');
});
