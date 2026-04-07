<?php

namespace App\Http\Controllers;

use App\Models\MasterMesin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

class MasterMesinController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return inertia("vue/dashboard/master-mesin/HomeMasterMesin");
    }

    public function getDataMasterMesin(Request $request)
    {
        try {
            $limit = (int) $request->query('limit', 10);
            $page = (int) $request->query('page', 1);
            $offset = ($page - 1) * $limit;

            $total = DB::table('EMI_Master_Mesin')
                    ->count();

            $getData = DB::table('EMI_Master_Mesin')
                    ->select('*')
                    ->offset($offset)
                    ->limit($limit)        
                    ->get()
                    ->map(function ($item) {
                        $item->Id_Master_Mesin = Hashids::connection('custom')->encode($item->Id_Master_Mesin);
                        $item->Id_Divisi_Mesin = Hashids::connection('custom')->encode($item->Id_Divisi_Mesin);
                        return $item;
                    });

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
                'page' => $page,
                'total_page' => ceil($total/$limit),
                'total_data' => $total
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
    public function searchMasterMesin(Request $request)
    {
        try {
            $keyword = trim($request->input('q'));

            if (empty($keyword)) {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => [
                        'error' => "Kata kunci pencarian tidak boleh kosong."
                    ]
                ], 400);
            }

            $getData = DB::table('EMI_Master_Mesin')
                ->select('*')
                ->whereRaw('LOWER(Nama_Mesin) LIKE ?', ['%' . strtolower($keyword) . '%'])
                ->get()
                ->map(function ($item) {
                    $item->Id_Master_Mesin = Hashids::connection('custom')->encode($item->Id_Master_Mesin);
                    $item->Id_Divisi_Mesin = Hashids::connection('custom')->encode($item->Id_Divisi_Mesin);
                    return $item;
                });

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

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => [
                    'error' => $e->getMessage()
                ]
            ], 500);
        }
    }

    public function getDataMasterMesinById($id)
    {
        try {

            $getData = DB::table('EMI_Master_Mesin')
                    ->select('*')
                    ->where('Id_Master_Mesin', $id)        
                    ->first();

            if (empty($getData)) {
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

    public function getDataDivisiMesin()
    {
        try {
            $getData = DB::table('N_EMI_Divisi_Mesin')
                    ->select('*') 
                    ->get()
                    ->map(function ($item) {
                        $item->Id_Divisi = Hashids::connection('custom')->encode($item->Id_Divisi);
                        return $item;
                    });

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

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'Nama_Mesin' => "required",
            'Seri_Mesin' => 'required',
            'Keterangan' => 'required',
            'Id_Divisi_Mesin' => 'required',
            'Flag_Multi_Qrcode' => 'nullable|in:Y',
            'Jumlah_Print_QRCode' => 'required|numeric|min:1'
        ], [
            'Nama_Mesin.required' => "Nama Mesin Analisa Tidak Boleh Kosong",
            'Seri_Mesin.required' => "Seri Mesin Tidak Boleh Kosong",
            'Keterangan.required' => "Keterangan Nama Mesin Analisa Tidak Boleh Kosong",
            'Id_Divisi_Mesin.required' => "Divisi Mesin Tidak Boleh Kosong",
            'Flag_Multi_Qrcode.in' => "Flag Multi Qrcode hanya boleh bernilai 'Y' atau kosong",
            'Jumlah_Print_QRCode.required' => "Jumlah Print QRCode wajib diisi",
            'Jumlah_Print_QRCode.min' => "Minimal Jumlah Print QRCode adalah 1",
        ]);

        // Validasi tambahan untuk Flag Y harus > 1
        if ($request->Flag_Multi_Qrcode === 'Y' && $request->Jumlah_Print_QRCode <= 1) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => "Jika menggunakan Multi QRCode, jumlah print harus lebih dari 1."
            ], 422);
        }

        try {
            $Id_Divisi = Hashids::connection('custom')->decode($request->Id_Divisi_Mesin)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Divisi tidak valid.'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $getDivisiMesin = DB::table('N_EMI_Divisi_Mesin')
                ->where('Id_Divisi', $Id_Divisi)
                ->first();

            if (!$getDivisiMesin) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "Divisi Mesin tidak ditemukan"
                ], 404);
            }

            $payload = [
                'Kode_Perusahaan' => "001",
                'Divisi_Mesin' => $getDivisiMesin->Keterangan,
                'Nama_Mesin' => $request->Nama_Mesin,
                'Seri_Mesin' => $request->Seri_Mesin,
                'Keterangan' => $request->Keterangan,
                'Id_Divisi_Mesin' => $Id_Divisi,
                'Flag_Multi_Qrcode' => $request->Flag_Multi_Qrcode ?? null,
                'Jumlah_Print_QRCode' => $request->Jumlah_Print_QRCode,
            ];

            DB::table('EMI_Master_Mesin')->insert($payload);
            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => "Data Berhasil Disimpan",
                'result' => $payload
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan: " . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $id = Hashids::connection('custom')->decode($id)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Jenis Analisa tidak valid.'
            ], 400);
        }

        $getData = DB::table('EMI_Master_Mesin')->where('Id_Master_Mesin', $id)->first();

        if(empty($getData)){
            return response()->json([
                'status' => false,
                'status' => 404,
                'message' => 'Data Tidak Ditemukan !'
            ], 404);
        }

        $request->validate([
            'Nama_Mesin' => "required",
            'Seri_Mesin' => 'required',
            'Keterangan' => 'required',
            'Id_Divisi_Mesin' => 'required',
            'Flag_Multi_Qrcode' => 'nullable|in:Y',
            'Jumlah_Print_QRCode' => 'required|numeric|min:1'
        ], [
            'Nama_Mesin.required' => "Nama Mesin Analisa Tidak Boleh Kosong",
            'Seri_Mesin.required' => "Seri Mesin Tidak Boleh Kosong",
            'Keterangan.required' => "Keterangan Nama Mesin Analisa Tidak Boleh Kosong",
            'Id_Divisi_Mesin.required' => "Divisi Mesin Tidak Boleh Kosong",
            'Flag_Multi_Qrcode.in' => "Flag Multi Qrcode hanya boleh bernilai 'Y' atau kosong",
            'Jumlah_Print_QRCode.required' => "Jumlah Print QRCode wajib diisi",
            'Jumlah_Print_QRCode.min' => "Minimal Jumlah Print QRCode adalah 1",
        ]);

        // Validasi tambahan untuk Flag Y harus > 1
        if ($request->Flag_Multi_Qrcode === 'Y' && $request->Jumlah_Print_QRCode <= 1) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => "Jika menggunakan Multi QRCode, jumlah print harus lebih dari 1."
            ], 422);
        }

        try {
            $Id_Divisi = Hashids::connection('custom')->decode($request->Id_Divisi_Mesin)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Divisi tidak valid.'
            ], 400);
        }

        DB::beginTransaction();

        try {
            // Cek apakah data mesin ada
            $existingMesin = DB::table('EMI_Master_Mesin')->where('Id_Master_Mesin', $id)->first();

            if (!$existingMesin) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "Data mesin dengan ID tersebut tidak ditemukan"
                ], 404);
            }

            // Ambil data divisi mesin
            $getDivisiMesin = DB::table('N_EMI_Divisi_Mesin')
                ->where('Id_Divisi', $Id_Divisi)
                ->first();

            if (!$getDivisiMesin) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "Divisi Mesin tidak ditemukan"
                ], 404);
            }

            $payload = [
                'Kode_Perusahaan' => "001",
                'Divisi_Mesin' => $getDivisiMesin->Keterangan,
                'Nama_Mesin' => $request->Nama_Mesin,
                'Seri_Mesin' => $request->Seri_Mesin,
                'Keterangan' => $request->Keterangan,
                'Id_Divisi_Mesin' => $Id_Divisi,
                'Flag_Multi_Qrcode' => $request->Flag_Multi_Qrcode ?? null,
                'Jumlah_Print_QRCode' => $request->Jumlah_Print_QRCode,
            ];

            DB::table('EMI_Master_Mesin')
                ->where('Id_Master_Mesin', $id)
                ->update($payload);

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Data Berhasil Diperbarui",
                'result' => $payload
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan: " . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(MasterMesin $masterMesin)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MasterMesin $masterMesin)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
  
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MasterMesin $masterMesin)
    {
        //
    }
}
