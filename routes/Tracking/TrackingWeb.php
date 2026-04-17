<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tracking\TrackingController;

Route::resource('tracking', TrackingController::class);
