<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

class PengajuanBukaUlangUjiSampelController extends Controller
{
    public function index()
    {
        return inertia("vue/dashboard/pengajuan-buka-ulang-ujisampel/HomePengajuanBukaUlang");
    }
    public function getDataBukUlangUjiSampel(Request $request)
    {
        try {
            $limit = (int) $request->query('limit', 10);
            $page = (int) $request->query('page', 1);
            $offset = ($page - 1) * $limit;            

            $total = DB::table('N_EMI_LAB_Pengajuan_Buka_Ulang_Uji_Sampel')->count();

            $getData = DB::table('N_EMI_LAB_Pengajuan_Buka_Ulang_Uji_Sampel')
                ->orderBy('Id_Pengajuan_Buka_Ulang', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get();
                
            if ($getData->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Data Tidak Ditemukan'
                ], 404);
            }

            $encodedData = $getData->map(function ($item) {
                $item->Id_Pengajuan_Buka_Ulang = Hashids::connection('custom')->encode($item->Id_Pengajuan_Buka_Ulang);
                
                return $item;
            });

            return response()->json([
                'success' => true,
                'status' => true,
                'data' => $encodedData,
                'page' => $page,
                'total_page' => ceil($total / $limit),
                'total_data' => $total
            ]);
        } catch (\Exception $e) {
            Log::channel('PengajuanBukaUlangUjiSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'status' => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }
    public function getDataPoSampel() 
    {
        try {
            $allData = DB::table('N_EMI_LAB_PO_Sampel')
                ->whereNull('Flag_Selesai')
                ->get();

            if ($allData->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'status' => 'no-data',
                    'message' => 'Tidak ada data PO sampel di database.',
                    'result' => [],
                ]);
            }
     
            $dueDataRaw = DB::table('N_EMI_LAB_PO_Sampel')
                ->whereNull('Flag_Selesai')
                ->whereRaw("DATEADD(DAY, 2, Tanggal) <= CAST(GETDATE() AS DATE)") 
                ->orderBy('No_Split_Po')
                ->orderBy('No_Sampel')
                ->get();


            if ($dueDataRaw->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'status' => 'not-yet-due',
                    'message' => 'Belum waktunya, belum H-1 dari deadline.',
                    'result' => [],
                ]);
            }
            
            return response()->json([
                'success' => true,
                'status' => 'due-today',
                'message' => 'Data yang sudah H-1 dari deadline atau lebih.',
                'result' => $dueDataRaw->values(),
            ]);

        } catch (\Exception $e) {
            Log::channel('PengajuanBukaUlangUjiSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'status' => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }
    public function search(Request $request)
    {
        try {
            $keyword = $request->input('q');

            if (empty($keyword)) {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => [
                        'error' => "Kata kunci pencarian tidak boleh kosong."
                    ]
                ], 400);
            }

            $getDataKategori = DB::table('N_EMI_LAB_Pengajuan_Buka_Ulang_Uji_Sampel')
            ->whereRaw("LOWER(No_Sampel) LIKE ?", ["%" . strtolower($keyword) . "%"])
            ->get();

            if ($getDataKategori->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' =>  "Data tidak ditemukan."
                ], 404);
            }

            $encodedData = $getDataKategori->map(function ($item) {
                $item->Id_Pengajuan_Buka_Ulang = Hashids::connection('custom')->encode($item->Id_Pengajuan_Buka_Ulang);
                
                return $item;
            });

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Data ditemukan.",
                'result' => $encodedData
            ], 200);

        } catch (\Exception $e) {
            Log::channel('PengajuanBukaUlangUjiSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'status' => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }
    public function store(Request $request)
    {
        $pengguna = Auth::user()->UserId;
        
        $request->validate([
            'No_Sampel' => 'required',
            'Waktu_Mulai' => 'required',
            'Waktu_Akhir' => 'required|',
            'Keterangan' => 'required'
        ]);
        

        DB::beginTransaction();

        try {

            $existing = DB::table('N_EMI_LAB_Pengajuan_Buka_Ulang_Uji_Sampel')
                ->where('No_Sampel', $request->No_Sampel)
                ->whereDate('Tanggal', date('Y-m-d'))
                ->exists();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'status' => 409,
                    'message' => "Pengajuan untuk sampel ini sudah ada pada hari yang sama."
                ], 409);
            }

            $payload = [
                'No_Sampel' => $request->No_Sampel,
                'Waktu_Mulai' => date('Y-m-d H:i:s', strtotime($request->Waktu_Mulai)),
                'Waktu_Akhir' => date('Y-m-d H:i:s', strtotime($request->Waktu_Akhir)),
                'Keterangan' => $request->Keterangan,
                'Jam' => date('H:i:s'),
                'Tanggal' => date("Y-m-d"),
                'Id_User' => $pengguna
            ];

            DB::table('N_EMI_LAB_Pengajuan_Buka_Ulang_Uji_Sampel')->insert($payload);
            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => "Data Berhasil Disimpan"
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('PengajuanBukaUlangUjiSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'status' => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }
    public function update(Request $request, $Id_Pengajuan_Buka_Ulang)
    {
        try {
            $Id_Pengajuan_Buka_Ulang = Hashids::connection('custom')->decode($Id_Pengajuan_Buka_Ulang)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Jenis Analisa tidak valid.'
            ], 400);
        }

        $request->validate([
            'Waktu_Mulai' => 'required|date_format:Y-m-d H:i:s',
            'Waktu_Akhir' => 'required|date_format:Y-m-d H:i:s|after:Waktu_Mulai',
            'Keterangan' => 'required|string|max:255'
        ]);

        DB::beginTransaction();

        try {
            $record = DB::table('N_EMI_LAB_Pengajuan_Buka_Ulang_Uji_Sampel')->where('Id_Pengajuan_Buka_Ulang', $Id_Pengajuan_Buka_Ulang)->first();

            if (!$record) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "Data tidak ditemukan"
                ], 404);
            }

            DB::table('N_EMI_LAB_Pengajuan_Buka_Ulang_Uji_Sampel')
                ->where('Id_Pengajuan_Buka_Ulang', $Id_Pengajuan_Buka_Ulang)
                ->update([
                    'Waktu_Mulai' => date('Y-m-d H:i:s', strtotime($request->Waktu_Mulai)),
                    'Waktu_Akhir' => date('Y-m-d H:i:s', strtotime($request->Waktu_Akhir)),
                    'Keterangan' => $request->Keterangan,
                    'Jam' => date('H:i:s'),
                    'Tanggal' => date("Y-m-d"),
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Data Berhasil Diupdate"
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('PengajuanBukaUlangUjiSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'status' => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }
}
