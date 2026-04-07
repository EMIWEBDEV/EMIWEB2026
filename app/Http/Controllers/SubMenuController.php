<?php

namespace App\Http\Controllers;

use App\Models\SubMenu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubMenuController extends Controller
{

    public function getDataSubMenuJson()
    {
        try {
            $getData = DB::table('N_EMI_LAB_Sub_Menus')
                ->get();

            if ($getData->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Data Tidak Ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'status' => true,
                'result' => $getData,
            ]);
        } catch (\Exception $e) {
            Log::channel('SubMenuController')->error('Error: ' . $e->getMessage());
            return response()->json([
                    'success' => true,
                    'status' => 500,
                    'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }
}
