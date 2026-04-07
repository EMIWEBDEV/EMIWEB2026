<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\JenisAnalisa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Vinkla\Hashids\Facades\Hashids;

class JenisAnalisaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
       $roles = Session::get("User_Roles") ?? []; 
       return inertia("vue/dashboard/jenis-analisa/HomeJenisAnalisa", [
            'roles' => $roles
        ]);
    }

    public function getDataAllGropedBy(Request $request)
    {
        $kodeRoles = collect(Session::get('User_Roles', []))->pluck('Kode_Role')->toArray();
        
        if (empty($kodeRoles)) {
            return ResponseHelper::error('Data tidak ditemukan (Role kosong).', 404);
        }

        try {
            // Ambil parameter dari request (default: page 1, limit 5)
            $search = $request->query('search', '');
            $page = (int) $request->query('page', 1);
            $limit = (int) $request->query('limit', 5);

            $query = DB::table('N_EMI_LAB_Jenis_Analisa as A')
                ->leftJoin('N_EMI_LIMS_Klasifikasi_Aktivitas_Lab as B', 'A.Kode_Aktivitas_Lab', '=', 'B.Kode_Aktivitas_Lab')
                ->select(
                    'A.Kode_Analisa',
                    'A.Jenis_Analisa',
                    DB::raw('MAX(A.Flag_Perhitungan) AS Flag_Perhitungan'),
                    DB::raw('MAX(A.Flag_Foto) AS Flag_Foto'),
                    DB::raw('MAX(B.Nama_Aktivitas) AS Nama_Aktivitas'),
                    DB::raw('COUNT(A.Kode_Analisa) as total_data')
                )
                ->whereIn('A.Kode_Role', $kodeRoles);

            // Filter Pencarian (Search)
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('A.Jenis_Analisa', 'LIKE', '%' . $search . '%')
                    ->orWhere('A.Kode_Analisa', 'LIKE', '%' . $search . '%');
                });
            }

            $query->groupBy('A.Kode_Analisa', 'A.Jenis_Analisa');

            // Menghitung total data untuk pagination (karena query menggunakan groupBy)
            $totalQuery = DB::table(DB::raw("({$query->toSql()}) as sub"))->mergeBindings($query);
            $total = $totalQuery->count();

            if ($total == 0) {
                return ResponseHelper::error('Data tidak ditemukan.', 404);
            }

            // Terapkan Limit dan Offset untuk Pagination
            $offset = ($page - 1) * $limit;
            $getData = $query->orderByRaw('MAX(A.Created_At) DESC')
                ->offset($offset)
                ->limit($limit)
                ->get();

            // Gunakan ResponseHelper untuk output
            return ResponseHelper::successWithPaginationV2(
                $getData,
                $page,
                $limit,
                $total,
                "Data Ditemukan",
                200
            );

        } catch (\Exception $e) {
            Log::channel("JenisAnalisaController")->error($e->getMessage());
            return ResponseHelper::error('Terjadi kesalahan pada server', 500);
        }
    }

    public function getDataJenisAnalisa()
    {
        $getData = DB::table('N_EMI_LAB_Jenis_Analisa')
              ->leftJoin('N_EMI_LAB_Mesin_Analisa', 'N_EMI_LAB_Jenis_Analisa.Id_Mesin', '=', 'N_EMI_LAB_Mesin_Analisa.No_Urut')
            ->select(
                'N_EMI_LAB_Jenis_Analisa.id', 
                'N_EMI_LAB_Jenis_Analisa.Kode_Analisa', 
                'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa', 
                'N_EMI_LAB_Jenis_Analisa.Flag_Perhitungan',
                'N_EMI_LAB_Mesin_Analisa.Nama_Mesin',
                'N_EMI_LAB_Jenis_Analisa.Sifat_Kegiatan'
            )
            ->where('Flag_Perhitungan', 'Y')
            ->get();

            if ($getData->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Data tidak ditemukan'
                ], 404);
            }
            
        return response()->json([
            'success' => true,
            'status' => 200,
            'result' => $getData
        ], 200);
    }

    public function getJenisAnalisaCurrentAlls()
    {
        $kodeRoles = collect(Session::get('User_Roles', []))
                        ->pluck('Kode_Role')
                        ->toArray();

        if (empty($kodeRoles)) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Data tidak ditemukan (Role kosong).'
            ], 404);
        }

        $getData = DB::table('N_EMI_LAB_Jenis_Analisa')
            ->leftJoin(
                'N_EMI_LAB_Mesin_Analisa',
                'N_EMI_LAB_Jenis_Analisa.Id_Mesin',
                '=',
                'N_EMI_LAB_Mesin_Analisa.No_Urut'
            )
            ->select(
                'N_EMI_LAB_Jenis_Analisa.id',
                'N_EMI_LAB_Jenis_Analisa.Kode_Analisa',
                'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa',
                'N_EMI_LAB_Jenis_Analisa.Flag_Perhitungan',
                'N_EMI_LAB_Mesin_Analisa.Nama_Mesin',
                'N_EMI_LAB_Jenis_Analisa.Sifat_Kegiatan'
            )
            ->whereIn('N_EMI_LAB_Jenis_Analisa.Kode_Role', $kodeRoles) 
            ->get();

        if ($getData->isEmpty()) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        $encodedData = $getData->map(function ($item) {
            $item->id = Hashids::connection('custom')->encode($item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'status'  => 200,
            'result'  => $encodedData
        ], 200);
    }

    public function getDataJenisAnalisaByBerkala()
    {
        $getData = DB::table('N_EMI_LAB_Jenis_Analisa')
              ->leftJoin('N_EMI_LAB_Mesin_Analisa', 'N_EMI_LAB_Jenis_Analisa.Id_Mesin', '=', 'N_EMI_LAB_Mesin_Analisa.No_Urut')
            ->select(
                'N_EMI_LAB_Jenis_Analisa.id', 
                'N_EMI_LAB_Jenis_Analisa.Kode_Analisa', 
                'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa', 
                'N_EMI_LAB_Jenis_Analisa.Flag_Perhitungan',
                'N_EMI_LAB_Mesin_Analisa.Nama_Mesin',
                'N_EMI_LAB_Jenis_Analisa.Sifat_Kegiatan'
            )
            ->where('Sifat_Kegiatan', 'Berkala')
            ->get();

        if ($getData->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Data tidak ditemukan'
                ], 404);
        }

        $encodedData = $getData->map(function ($item) {
            $item->id = Hashids::connection('custom')->encode($item->id);
            
            return $item;
        });
            
        return response()->json([
            'success' => true,
            'status' => 200,
            'result' => $encodedData
        ], 200);
    }

    public function getDataQualityControl($id)
    {
        $query = "
            SELECT
                B.*,
                J.Kode_Analisa as kode_analisa,
                J.Jenis_Analisa as jenis_analisa,
                Q.Kode_Uji as kode_uji,
                Q.Keterangan as keterangan,
                Q.Satuan as satuan
            FROM
                N_EMI_LAB_Binding_Jenis_Analisa B
            JOIN N_EMI_LAB_Jenis_Analisa J
                ON B.Id_Jenis_Analisa = J.id
            JOIN EMI_Quality_Control Q
                ON B.Id_Quality_Control = Q.Id_QC_Formula
            WHERE
                B.Id_Jenis_Analisa = ?
        ";
        $getData = DB::select($query, [$id]);

        if(empty($getData)){
            return response()->json([
                'success' => false,
                'statut' => 404,
                'message' => 'Data Tidak Ditemukan'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'status' => 200,
            'result' => $getData
        ], 200);
    }

    public function create()
    {
        $roles = Session::get("User_Roles") ?? [];
        return inertia("vue/dashboard/jenis-analisa/FormJenisAnalisa", [
            'roles' => $roles
        ]);
    }

    public function store(Request $request)
    {
        // dd($request->all());
        $pengguna = Auth::user();
        $roles = Session::get("User_Roles") ?? [];
        $jumlahRole = count($roles);

        $rules = [
            'data_analisa' => 'required|array|min:1',
            'data_analisa.*.Kode_Analisa' => 'required',
            'data_analisa.*.Jenis_Analisa' => 'required'
        ];

        $messages = [
            'data_analisa.required' => 'Data Analisa Tidak Boleh Kosong !',
            'data_analisa.*.Kode_Analisa.required' => 'Kode Analisa Tidak Boleh Kosong !',
            'data_analisa.*.Jenis_Analisa.required' => 'Jenis Analisa Tidak Boleh Kosong !',
            'Kode_Role.required'  => 'Role / Divisi Mesin Wajib Dipilih',
        ];

        if ($jumlahRole > 1) {
            $rules['Kode_Role'] = 'required';
        }

        $kodeRole = ($jumlahRole === 1) ? $roles[0]->Kode_Role : $request->Kode_Role;

        if ($kodeRole === 'FLM') {
            $rules['data_analisa.*.Kode_Aktivitas_Lab'] = 'required';
            $rules['data_analisa.*.Flag_Foto'] = 'required|in:Y,T';
            $messages['data_analisa.*.Kode_Aktivitas_Lab.required'] = 'Kategori Analisa Wajib Dipilih !';
            $messages['data_analisa.*.Flag_Foto.required'] = 'Flag Foto Wajib Dipilih !';
            $messages['data_analisa.*.Flag_Foto.in'] = 'Flag Foto Tidak Valid !';
        }

        $request->validate($rules, $messages);

        DB::beginTransaction();

        try {
            $insertData = [];

            foreach ($request->data_analisa as $item) {
                $existing = DB::table('N_EMI_LAB_Jenis_Analisa')
                    ->where('Kode_Analisa', $item['Kode_Analisa'])
                    ->where('Jenis_Analisa', $item['Jenis_Analisa'])
                    ->first();

                if ($existing) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'status' => 409,
                        'message' => 'Data dengan kombinasi Kode Analisa ' . $item['Kode_Analisa'] . ' dan Jenis Analisa ' . $item['Jenis_Analisa'] . ' sudah ada.',
                    ], 409);
                }

                $dataRow = [
                    'Kode_Analisa' => $item['Kode_Analisa'],
                    'Jenis_Analisa' => $item['Jenis_Analisa'],
                    'Id_Mesin' => $item['Id_Mesin'] ?? null,
                    'Flag_Perhitungan' => $item['Flag_Perhitungan'] ?? null,
                    'Sifat_Kegiatan' => $item['Sifat_Kegiatan'] ?? 'Rutin',
                    'Flag_Foto' => ($kodeRole === 'FLM') ? ($item['Flag_Foto'] ?? 'T') : 'T',
                    'Kode_Role' => $kodeRole,
                    'Id_User' => $pengguna->UserId,
                    'Created_At' => now(),
                    'Updated_At' => now()
                ];

                if ($kodeRole === 'FLM') {
                    $dataRow['Kode_Aktivitas_Lab'] = $item['Kode_Aktivitas_Lab'];
                }

                $insertData[] = $dataRow;
            }

            DB::table('N_EMI_LAB_Jenis_Analisa')->insert($insertData);
            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => "Data Berhasil Disimpan",
                'data' => $insertData
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('JenisAnalisaController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }

    public function show($jenis_analisa)
    {
        $roles = Session::get("User_Roles") ?? [];
        return inertia("vue/dashboard/jenis-analisa/DetailJenisAnalisa", [
            'id' => $jenis_analisa,
            'roles' => $roles
        ]);
    }

    public function update(Request $request, $id)
    {
        $pengguna = Auth::user();

        try {
            $id = Hashids::connection('custom')->decode($id)[0];
            $Id_Mesin = null;
            if ($request->filled('Id_Mesin')) {
                $decodedMesin = Hashids::connection('custom')->decode($request->Id_Mesin);
                $Id_Mesin = $decodedMesin[0] ?? $request->Id_Mesin;
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Format Kunci Menu tidak valid.'
            ], 400);
        }

        $getData = DB::table('N_EMI_LAB_Jenis_Analisa')->where('id', $id)->first();

        if(!$getData){
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => "Data Tidak Ditemukan !"
            ], 404);
        }

        $rules = [
            'Kode_Role' => 'required',
            'Kode_Analisa' => 'required',
            'Jenis_Analisa' => 'required',
        ];

        $messages = [
            'Kode_Role.required' => 'Role / Divisi Mesin Wajib Dipilih',
            'Kode_Analisa.required' => 'Kode Analisa Tidak Boleh Kosong !',
            'Jenis_Analisa.required' => 'Jenis Analisa Tidak Boleh Kosong !',
        ];

        if ($request->Kode_Role === 'FLM') {
            $rules['Kode_Aktivitas_Lab'] = 'required';
            $rules['Flag_Foto'] = 'required|in:Y,T';
            $messages['Kode_Aktivitas_Lab.required'] = 'Kategori Analisa Wajib Dipilih !';
            $messages['Flag_Foto.required'] = 'Flag Foto Wajib Dipilih !';
            $messages['Flag_Foto.in'] = 'Flag Foto Tidak Valid !';
        }

        $request->validate($rules, $messages);

        DB::beginTransaction();

        try {
            $existing = DB::table('N_EMI_LAB_Jenis_Analisa')
                ->where('Kode_Analisa', $request->Kode_Analisa)
                ->where('Jenis_Analisa', $request->Jenis_Analisa)
                ->where('id', '!=', $id)
                ->first();

            if ($existing) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'status' => 409,
                    'message' => 'Data dengan kombinasi Kode Analisa ' . $request->Kode_Analisa . ' dan Jenis Analisa ' . $request->Jenis_Analisa . ' sudah ada.',
                ], 409);
            }

            $data = [
                'Kode_Role' => $request->Kode_Role,
                'Kode_Analisa' => $request->Kode_Analisa,
                'Jenis_Analisa' => $request->Jenis_Analisa,
                'Id_Mesin' => $Id_Mesin,
                'Flag_Perhitungan' => $request->Flag_Perhitungan,
                'Sifat_Kegiatan' => $request->Sifat_Kegiatan ?? 'Rutin',
                'Flag_Foto' => ($request->Kode_Role === 'FLM') ? ($request->Flag_Foto ?? 'T') : 'T',
                'Kode_Aktivitas_Lab' => ($request->Kode_Role === 'FLM') ? $request->Kode_Aktivitas_Lab : null,
                'Id_User' => $pengguna->UserId,
                'Updated_At' => now()
            ];
            
            DB::table('N_EMI_LAB_Jenis_Analisa')->where('id', $id)->update($data);
            DB::commit();
            
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Data Berhasil Diupdate",
                'data' => $data
            ], 200);
            
        } catch(\Exception $e){
            DB::rollBack();
            Log::channel('JenisAnalisaController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }

    public function getDetailJenisAnalisa(Request $request, $jenis_analisa)
    {
       try {
            $limit = (int) $request->query('limit', 10);
            $page = (int) $request->query('page', 1);
            $offset = ($page - 1) * $limit;

            $total = DB::table('N_EMI_LAB_Jenis_Analisa')
            ->where('Jenis_Analisa', $jenis_analisa)
            ->count();

            $getData = DB::table('N_EMI_LAB_Jenis_Analisa')
                ->leftJoin('N_EMI_LAB_Mesin_Analisa', 'N_EMI_LAB_Jenis_Analisa.Id_Mesin', '=', 'N_EMI_LAB_Mesin_Analisa.No_Urut')
                ->select(
                    'N_EMI_LAB_Jenis_Analisa.*',
                    'N_EMI_LAB_Mesin_Analisa.Nama_Mesin'
                )
                ->where('Jenis_Analisa', $jenis_analisa)
                ->offset($offset)
                ->limit($limit)
                ->get();
            
            foreach ($getData as &$item) {
                $getDataSubJenis = DB::table('N_EMI_LAB_Jenis_Analisa_Berkala as berkala')
                    ->join('N_EMI_LAB_Jenis_Analisa as ja', 'berkala.Id_Jenis_Analisa', '=', 'ja.id')
                    ->join('N_EMI_LAB_Jenis_Analisa as sub_ja', 'berkala.Id_Sub_Jenis_Analisa', '=', 'sub_ja.id')
                    ->select(
                        'berkala.Id_Jenis_Analisa_Berkala',
                        'berkala.Id_Jenis_Analisa',
                        'berkala.Id_Sub_Jenis_Analisa',
                        'ja.Jenis_Analisa as Jenis_Analisa',
                        'sub_ja.Jenis_Analisa as Sub_Jenis_Analisa'
                    )
                    ->where('berkala.Id_Jenis_Analisa', $item->id)
                    ->get();

                // Tambahkan Sub_Analisa sebagai array string dari sub_ja
                $item->Sub_Analisa = $getDataSubJenis->pluck('Sub_Jenis_Analisa');

                // Encode ID
                $item->id = Hashids::connection('custom')->encode($item->id);
                $item->Id_Mesin = Hashids::connection('custom')->encode($item->Id_Mesin);
            }

            
            if($getData->isEmpty()){
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "Data Tidak Ditemukan !",
                ], 404);
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
       }catch(\Exception $e){
            Log::channel('JenisAnalisaController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'status' => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
       }
    }
    
    public function searchDetailJenisAnalisa(Request $request, $jenis_analisa)
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

            $getData = DB::table('N_EMI_LAB_Jenis_Analisa')
                ->join('EMI_Master_Mesin', 'N_EMI_LAB_Jenis_Analisa.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
                ->select(
                    'N_EMI_LAB_Jenis_Analisa.*',
                    'EMI_Master_Mesin.Nama_Mesin'
                )
                ->where('Jenis_Analisa', $jenis_analisa)
                ->whereRaw('LOWER(N_EMI_LAB_Jenis_Analisa.Jenis_Analisa) LIKE ?', ['%' . strtolower($keyword) . '%'])
                ->get();
            
            if(empty($getData)){
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "Data Tidak Ditemukan !",
                ], 404);
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Data Ditemukan",
                'result' => $getData,
            ], 200);
       }catch(\Exception $e){
            Log::channel('JenisAnalisaController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'status' => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
       }
    }
}
