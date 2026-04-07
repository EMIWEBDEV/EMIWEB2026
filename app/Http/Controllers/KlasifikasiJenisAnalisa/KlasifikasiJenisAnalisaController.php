<?php

namespace App\Http\Controllers\KlasifikasiJenisAnalisa;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

class KlasifikasiJenisAnalisaController extends Controller
{
    public function getOptionKlasifikasiJenisAnalisa()
    {
        try {
            $getData = DB::table('N_EMI_LIMS_Klasifikasi_Aktivitas_Lab')
                    ->get();

                if ($getData->isEmpty()) {
                    return response()->json([
                        'success' => false,
                        'status' => 404,
                        'message' => 'Data tidak ditemukan'
                    ], 404);
                }

            $encodedData = $getData->map(function ($item) {
                    $item->Id_Klasifikasi_Aktivitas_Lab = Hashids::connection('custom')->encode($item->Id_Klasifikasi_Aktivitas_Lab);
                    return $item;
            });
            return ResponseHelper::success($encodedData, "Data Ditemukan !", 200);
        }catch(\Exception $e){
            Log::channel("KlasifikasiJenisAnalisaController")->error($e->getMessage());
            return ResponseHelper::error("Terjadi Kesalahan", 500);
        }
    }
}
