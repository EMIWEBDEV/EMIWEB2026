<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

class BindingIdentityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return inertia("vue/dashboard/binding-identity/HomeBindingIdentity");
    }

    public function getDataBinding()
    {
        $query = "
                SELECT
                    binding.Id_Identity,
                    MAX(master.Computer_Keys) AS Computer_Keys,
                    MAX(master.Keterangan) AS Keterangan,
                    COUNT(binding.Id_Identity) AS total_data
                FROM
                    N_EMI_LAB_Binding_Identity binding
                JOIN N_EMI_LAB_Identity master 
                    ON binding.Id_Identity = master.id
                GROUP BY
                    binding.Id_Identity;
        ";
        $groupedData = DB::select($query);

        foreach ($groupedData as &$item) {
            $item->Id_Identity = Hashids::connection('custom')->encode($item->Id_Identity);
        }

        if(empty($groupedData)){
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => "Data Tidak Ditemukan"
            ], 404);
        }


        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => "Data Ditemukan !",
            'result' => $groupedData
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return inertia("vue/dashboard/binding-identity/FormBindingIdentity");
    }
    public function edit($Id)
    {
        try {
            $Id = Hashids::connection('custom')->decode($Id)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Format Kunci Menu tidak valid.'
            ], 400);
        }

        $getData = DB::table('N_EMI_LAB_Binding_Identity')->where('id', $Id)->first();

        return inertia("vue/dashboard/binding-identity/FormEditBindingIdentity", [
            'getData' => $getData,
        ]);
    }
    public function show($id)
    {
        return inertia("vue/dashboard/binding-identity/DetailBindingIdentity", [
            'id' => $id
        ]);
    }

    public function getMesinList()
    {
        $query = 'SELECT Id_Master_Mesin, Seri_Mesin, Nama_Mesin FROM EMI_Master_Mesin';
        $mesin = DB::select($query);

        if(empty($mesin)){
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => "Data Tidak Ditemukan !"
            ], 404);
        }

        foreach ($mesin as &$item) {
            $item->Id_Master_Mesin = Hashids::connection('custom')->encode($item->Id_Master_Mesin);
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => "Data Ditemukan",
            'result' => $mesin
        ], 200);
        
    }
    public function getIdentityComputerList()
    {
        $query = 'SELECT id, Computer_Keys, Keterangan FROM N_EMI_LAB_Identity';
        $Identity = DB::select($query);

        if(empty($Identity)){
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => "Data Tidak Ditemukan !"
            ], 404);
        }

        foreach ($Identity as &$item) {
            $item->id = Hashids::connection('custom')->encode($item->id);
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => "Data Ditemukan",
            'result' => $Identity
        ], 200);

    }
    public function store(Request $request)
    {
        $request->validate([
            'Id_Identity' => 'required',
            'Id_Mesin' => 'required'
        ]);

        try {
            $Id_Identity = Hashids::connection('custom')->decode($request->Id_Identity)[0];
            $Id_Mesin = Hashids::connection('custom')->decode($request->Id_Mesin)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Format Kunci Menu tidak valid.'
            ], 400);
        }

        DB::beginTransaction();
        try {
             $exists = DB::table('N_EMI_LAB_Binding_Identity')
                ->where('Id_Identity', $Id_Identity)
                ->where('Id_Mesin', $Id_Mesin)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'status' => 409,
                    'message' => "Kombinasi Identity dan Mesin sudah terdaftar"
                ], 409);
            }

            $payload = [
                'Kode_Perusahaan' => '001',
                'Id_Identity' => $Id_Identity,
                'Id_Mesin' => $Id_Mesin
            ];
            DB::table('N_EMI_LAB_Binding_Identity')->insert($payload);
            DB::commit();
            return response()->json([
                'success' => false,
                'status' => 201,
                'message' => "Data Berhasil Disimpan",
                'result' => [
                    $payload
                ]
            ], 201);
            return redirect()->route('bidingidentity.index')->with('success', "Data Berhasil Disimpan");
        }catch(\Exception $e) {
            DB::rollBack();
            Log::channel('BindingIdentityController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'status' => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }
    public function update(Request $request, $id)
    {

        $getData = DB::table('N_EMI_LAB_Binding_Identity')->where('id', $id)->first();

        if(!$getData){
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => "Data Tidak Ditemukan !"
            ], 404);
        }

        $request->validate([
            'Id_Identity' => 'required',
            'Id_Mesin' => 'required'
        ]);

        try {
            $Id_Identity = Hashids::connection('custom')->decode($request->Id_Identity)[0];
            $Id_Mesin = Hashids::connection('custom')->decode($request->Id_Mesin)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Format Kunci Menu tidak valid.'
            ], 400);
        }

        DB::beginTransaction();
        try {
             $exists = DB::table('N_EMI_LAB_Binding_Identity')
                ->where('Id_Identity', $Id_Identity)
                ->where('Id_Mesin', $Id_Mesin)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'status' => 409,
                    'message' => "Kombinasi Identity dan Mesin sudah terdaftar"
                ], 409);
            }

            $payload = [
                'Id_Identity' => $Id_Identity,
                'Id_Mesin' => $Id_Mesin
            ];
            DB::table('N_EMI_LAB_Binding_Identity')->where('id', $id)->update($payload);
            DB::commit();
            return response()->json([
                'success' => false,
                'status' => 200,
                'message' => "Data Berhasil Diupdate",
                'result' => [
                    $payload
                ]
            ], 200);
            return redirect()->route('bidingidentity.index')->with('success', "Data Berhasil Disimpan");
        }catch(\Exception $e) {
            DB::rollBack();
            Log::channel('BindingIdentityController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'status' => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }

    public function getDataDetailIdentity(Request $request, $id)
    {
        try {
            $id = Hashids::connection('custom')->decode($id)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Format Kunci Menu tidak valid.'
            ], 400);
        }

        try {
            $limit = (int) $request->query('limit', 10);
            $page = (int) $request->query('page', 1);
            $offset = ($page - 1) * $limit;

            $total = DB::table('N_EMI_LAB_Binding_Identity')
                ->where('Id_Identity', $id)
                ->count();

            if ($total === 0) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "Data Tidak Ditemukan"
                ], 404);
            }

            $query = "
                SELECT *
                FROM (
                    SELECT
                        binding.id,
                        binding.Id_Identity,
                        binding.Id_Mesin,
                        mesin.Nama_Mesin,
                        mesin.Seri_Mesin,
                        i.Keterangan,
                        ROW_NUMBER() OVER (ORDER BY mesin.Nama_Mesin) AS RowNum
                    FROM 
                        N_EMI_LAB_Binding_Identity AS binding
                    JOIN 
                        EMI_Master_Mesin AS mesin ON binding.Id_Mesin = mesin.Id_Master_Mesin
                    JOIN 
                        N_EMI_LAB_Identity AS i ON binding.Id_Identity = i.id
                    WHERE 
                        binding.Id_Identity = ?
                ) AS TempTable
                WHERE RowNum BETWEEN ? AND ?
            ";

            $getData = DB::select($query, [
                $id,
                $offset + 1,
                $offset + $limit
            ]);

            if (empty($getData)) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "Data Tidak Ditemukan"
                ], 404);
            }

            foreach ($getData as &$item) {
                $item->id = Hashids::connection('custom')->encode($item->id);
                $item->Id_Identity = Hashids::connection('custom')->encode($item->Id_Identity);
                $item->Id_Mesin = Hashids::connection('custom')->encode($item->Id_Mesin);
            }
    

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Data Ditemukan",
                'result' => $getData,
                'page' => $page,
                'total_page' => ceil($total / $limit),
                'total_data' => $total
            ], 200);
        } catch (\Exception $e) {
            Log::channel('BindingIdentityController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'status' => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }

    public function searchMesinByIdentity(Request $request, $id)
    {
        try {
            $id = Hashids::connection('custom')->decode($id)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Format Kunci Menu tidak valid.'
            ], 400);
        }

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

            $getData = DB::table('N_EMI_LAB_Binding_Identity as binding')
                ->join('EMI_Master_Mesin as mesin', 'binding.Id_Mesin', '=', 'mesin.Id_Master_Mesin')
                ->join('N_EMI_LAB_Identity as i', 'binding.Id_Identity', '=', 'i.id')
                ->select(
                    'mesin.Nama_Mesin',
                    'mesin.Seri_Mesin',
                    'i.Keterangan'
                )
                ->where('binding.Id_Identity', $id)
                ->whereRaw('LOWER(mesin.Nama_Mesin) LIKE ?', ['%' . strtolower($keyword) . '%'])
                ->get();

            if ($getData->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "Data tidak ditemukan."
                ], 404);
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Data ditemukan.",
                'result' => $getData
            ], 200);

        } catch (\Exception $e) {
            Log::channel('BindingIdentityController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'status' => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }
}
