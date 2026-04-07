<?php

namespace App\Http\Controllers\FormulatorRekapitulasiTrial;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FormulatorRekapitulasiTrialController extends Controller
{
    public function index()
    {
        return inertia("vue/dashboard/lab/formulator/hasil-analisis/HasilAnalisaDibatalkan");
    }
}
