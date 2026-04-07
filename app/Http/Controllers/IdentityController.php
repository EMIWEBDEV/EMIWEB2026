<?php

namespace App\Http\Controllers;

use App\Models\Identity;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cookie;
use Vinkla\Hashids\Facades\Hashids;

class IdentityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
         return inertia("vue/dashboard/identity-komputer/IdentityKomputer")->withViewData([
            'layout' => 'layouts.master2'
         ]);
    
    }

    public function getDataIdentity(Request $request)
    {
        try {
            $limit = (int) $request->query('limit', 10);
            $page = (int) $request->query('page', 1);
            $offset = ($page - 1) * $limit;

            Log::error("message");
    
            $total = DB::table('N_EMI_LAB_Identity')->count();

            // Ambil data dengan limit dan offset
            $getData = DB::table('N_EMI_LAB_Identity')
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
                $item->id = Hashids::connection('custom')->encode($item->id);
                
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
            Log::channel('IdentityController')->error('Error: ' . $e->getMessage());
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

            $getDataKategori = DB::table('N_EMI_LAB_Identity')
            ->whereRaw("LOWER(Keterangan) LIKE ?", ["%" . strtolower($keyword) . "%"])
            ->get();

            if ($getDataKategori->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' =>  "Data tidak ditemukan."
                ], 404);
            }

            $encodedData = $getDataKategori->map(function ($item) {
                $item->id = Hashids::connection('custom')->encode($item->id);
                
                return $item;
            });

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Data ditemukan.",
                'result' => $encodedData
            ], 200);

        } catch (\Exception $e) {
            Log::channel('IdentityController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'status' => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function generateKey()
    {
        $key = Str::random(30);

        return response()->json([
            'key' => $key
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'Computer_Keys' => 'required',
            'Keterangan' => 'required'
        ]);

        DB::beginTransaction();

        try {
            $data = [
                'Kode_Perusahaan' => '001',
                'Computer_Keys' => $request->Computer_Keys,
                'Keterangan' => $request->Keterangan,
            ];

            DB::table('N_EMI_LAB_Identity')->insert($data);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Data Berhasil Disimpan',
                'computer_key' => $data['Computer_Keys']
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('IdentityController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'status' => 500,
                'message' => "Terjadi Kesalahan",
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

        $request->validate([
            'Computer_Keys' => 'required',
            'Keterangan' => 'required'
        ]);

        DB::beginTransaction();

        try {
            $data = [
                'Kode_Perusahaan' => '001',
                'Computer_Keys' => $request->Computer_Keys,
                'Keterangan' => $request->Keterangan,
            ];

            DB::table('N_EMI_LAB_Identity')->where('id', $id)->update($data);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Data Berhasil Disimpan',
                'computer_key' => $data['Computer_Keys']
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('IdentityController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'status' => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }
}
