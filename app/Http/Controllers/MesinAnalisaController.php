<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class MesinAnalisaController extends Controller
{
    public function index()
    {
        $roles = Session::get("User_Roles") ?? [];
        return inertia("vue/dashboard/mesin-analisa/HomeMesinAnalisa", [
            'roles' => $roles
        ]);
    }

    public function getListMesinAnalisa()
    {
        $kodeRoles = collect(Session::get('User_Roles', []))->pluck('Kode_Role')->toArray();

        if (empty($kodeRoles)) {
            return ResponseHelper::error('Data Tidak Ditemukan', 404);
        }

        try {
           
            $getData = DB::table('N_EMI_LAB_Mesin_Analisa')
                    ->whereIn('Kode_Role', $kodeRoles)      
                    ->get();

            if ($getData->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "Data Tidak Ditemukan"
                ], 404);
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Data Ditemukan",
                'result' => $getData,
            ], 200);
        }catch(\Exception $e){
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => [
                    'error' => $e->getMessage()
                ]
            ], 500);
        }
    }

    public function getDataMesinAnalisa(Request $request)
    {
        try {
            $limit = (int) $request->query('limit', 10);
            $page = (int) $request->query('page', 1);
            $offset = ($page - 1) * $limit;

            $kodeRoles = collect(Session::get('User_Roles', []))->pluck('Kode_Role')->toArray();

            if (empty($kodeRoles)) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            $total = DB::table('N_EMI_LAB_Mesin_Analisa')
                    ->whereIn('Kode_Role', $kodeRoles)
                    ->count();

            $getData = DB::table('N_EMI_LAB_Mesin_Analisa')
                    ->select('*')
                    ->whereIn('Kode_Role', $kodeRoles)
                    ->offset($offset)
                    ->limit($limit)
                    ->get();

            if ($getData->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            return ResponseHelper::successWithPaginationV2(
                $getData, 
                $page, 
                $limit, 
                $total, 
                'Data Ditemukan', 
                200
            );
        } catch (\Exception $e) {
            Log::channel('MesinAnalisaController')->error('Error: ' . $e->getMessage());
            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function searchMesinAnalisa(Request $request)
    {
        try {
            $keyword = $request->input('q');

            if (empty($keyword)) {
                return ResponseHelper::error('Kata kunci pencarian tidak boleh kosong.', 400);
            }

            $kodeRoles = collect(Session::get('User_Roles', []))->pluck('Kode_Role')->toArray();

            if (empty($kodeRoles)) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            $getData = DB::table('N_EMI_LAB_Mesin_Analisa')
                    ->select('*')
                    ->whereIn('Kode_Role', $kodeRoles)
                    ->whereRaw('LOWER(Nama_Mesin) LIKE ?', ['%'. strtolower($keyword) . '%'])       
                    ->get();

            if ($getData->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            return ResponseHelper::success($getData, 'Data Ditemukan', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function store(Request $request)
    {
        $pengguna = Auth::user();
        $roles = Session::get("User_Roles") ?? [];
        $jumlahRole = count($roles);

        $rules = [
            'Nama_Mesin' => "required",
            'Keterangan' => 'required'
        ];

        $messages = [
            'Nama_Mesin.required' => "Nama Mesin Analisa Tidak Boleh Kosong",
            'Keterangan.required' => "Keterangan Nama Mesin Analisa Tidak Boleh Kosong",
            'Kode_Role.required'  => "Role / Divisi Mesin Wajib Dipilih",
        ];

        if ($jumlahRole > 1) {
            $rules['Kode_Role'] = 'required';
        }

        $request->validate($rules, $messages);

        DB::beginTransaction();

        try {
            $kodeRole = ($jumlahRole === 1) ? $roles[0]->Kode_Role : $request->Kode_Role;
            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow; 

            $tanggalSqlServer = date('Y-m-d', strtotime($dt)); 
            $jamSqlServer = date('H:i:s', strtotime($dt));  

            $payload = [
                'Kode_Perusahaan' => "001",
                "Divisi_Mesin"  => "LAB",
                'Kode_Role' => $kodeRole,
                'Nama_Mesin' => $request->Nama_Mesin,
                'Keterangan' => $request->Keterangan,
                'Tanggal'   => $tanggalSqlServer,
                'Jam'   => $jamSqlServer,
                'Id_User' => $pengguna->UserId
            ];

            DB::table('N_EMI_LAB_Mesin_Analisa')->insert($payload);
            DB::commit();
            
            return ResponseHelper::success([$payload], "Data Berhasil Disimpan", 201);
        } catch(\Exception $e){
            DB::rollBack();
            Log::channel('MesinAnalisaController')->error('Error: ' . $e->getMessage());
            return ResponseHelper::error("Terjadi Kesalahan", 500); 
        }
    }

    public function update(Request $request, $id)
    {
        $pengguna = Auth::user();

        $checkedData = DB::table('N_EMI_LAB_Mesin_Analisa')
                        ->where('No_Urut', $id)
                        ->first();

        if(empty($checkedData)){
            return ResponseHelper::error('Data Tidak Ditemukan', 404);
        }

        $request->validate([
            'Kode_Role' => 'required',
            'Nama_Mesin' => 'required',
            'Keterangan' => 'required'
        ], [
            'Kode_Role.required' => "Role Tidak Boleh Kosong",
            'Nama_Mesin.required' => "Nama Mesin Analisa Tidak Boleh Kosong",
            'Keterangan.required' => "Keterangan Nama Mesin Analisa Tidak Boleh Kosong",
        ]);

        DB::beginTransaction();

        try {
            $payload = [
                'Kode_Role' => $request->Kode_Role,
                'Nama_Mesin' => $request->Nama_Mesin,
                'Keterangan' => $request->Keterangan,
                'Id_User' => $pengguna->UserId,
            ];

            DB::table('N_EMI_LAB_Mesin_Analisa')
                ->where('No_Urut', $checkedData->No_Urut)
                ->update($payload);
                
            DB::commit();
            
            return ResponseHelper::success(null, 'Data Berhasil Diperbaharui', 200);
        } catch(\Exception $e){
            DB::rollBack();
            Log::channel('MesinAnalisaController')->error('Error: ' . $e->getMessage());
            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }
}
