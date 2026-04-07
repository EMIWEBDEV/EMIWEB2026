<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Vinkla\Hashids\Facades\Hashids;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class BindingJenisAnalisaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return inertia('vue/dashboard/binding-parameter-jenis-analisa/HomeBindingParameter');
    }

    public function getListDataJenisAnalisa()
    {
        $kodeRoles = collect(Session::get('User_Roles', []))->pluck('Kode_Role')->toArray();

        if (empty($kodeRoles)) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Data tidak ditemukan (Role kosong).'
            ], 404);
        }

        try {
            $getData = DB::table('N_EMI_LAB_Binding_Jenis_Analisa as B')
                ->join('N_EMI_LAB_Jenis_Analisa as J', 'B.Id_Jenis_Analisa', '=', 'J.id')
                ->select(
                    'B.Id_Jenis_Analisa',
                    DB::raw('MAX(J.Kode_Analisa) as kode_analisa'),
                    DB::raw('MAX(J.Jenis_Analisa) as jenis_analisa'),
                    DB::raw('COUNT(B.Id_Jenis_Analisa) as total_data')
                )
                ->whereIn('J.Kode_Role', $kodeRoles)
                ->groupBy('B.Id_Jenis_Analisa')
                ->get();

            if ($getData->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'status'  => 404,
                    'message' => 'Data Tidak Ditemukan !'
                ], 404);
            }

            foreach ($getData as $item) {
                $item->Id_Jenis_Analisa = Hashids::connection('custom')->encode($item->Id_Jenis_Analisa);
            }

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Data Ditemukan !',
                'result'  => $getData
            ], 200);

        } catch (\Exception $e) {
            Log::channel('BindingJenisAnalisaController')->error($e->getMessage());
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Terjadi kesalahan pada server'
            ], 500);
        }
    }

    public function getOptionJenisAnalisa()
    {
        try {
            $getDataJenisAnalisa = DB::table('N_EMI_LAB_Jenis_Analisa')
                ->leftJoin('N_EMI_LAB_Mesin_Analisa', 'N_EMI_LAB_Jenis_Analisa.Id_Mesin', '=', 'N_EMI_LAB_Mesin_Analisa.No_Urut')
                ->select(
                    'N_EMI_LAB_Jenis_Analisa.*',
                    'N_EMI_LAB_Mesin_Analisa.Nama_Mesin'
                )
                ->get();

            if(empty($getDataJenisAnalisa)){
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "Data Tidak Ditemukan !"
                ], 404);
            }
            return ResponseHelper::success($getDataJenisAnalisa, "Data Ditemukan !", 200);
        }catch(\Exception $e){
            Log::channel("BindingJenisAnalisaController")->error($e->getMessage());
            return ResponseHelper::error("Terjadi Kesalahan", 500);
        }
    }

    public function getOptionQualityControl()
    {
        try {
            $getDataQualityControl = DB::table('EMI_Quality_Control')->get();

            if(empty($getDataQualityControl)){
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "Data Tidak Ditemukan !"
                ], 404);
            }
            return ResponseHelper::success($getDataQualityControl, "Data Ditemukan !", 200);
        }catch(\Exception $e){
            Log::channel("BindingJenisAnalisaController")->error($e->getMessage());
            return ResponseHelper::error("Terjadi Kesalahan", 500);
        }
    }

    public function create()
    {
        $roles = Session::get("User_Roles") ?? [];
        return inertia("vue/dashboard/binding-parameter-jenis-analisa/FormBindingParameter", [
            'roles' => $roles
        ]);
    }

    public function store(Request $request)
    {
        $pengguna   = Auth::user();
        $roles      = Session::get("User_Roles") ?? [];
        $jumlahRole = count($roles);

        $request->validate([
            'Kode_Role'                  => $jumlahRole > 1 ? 'required' : 'nullable',
            'data'                       => 'required|array|min:1',
            'data.*.Id_Jenis_Analisa'    => 'required',
            'data.*.Id_Quality_Control'  => 'required|array|min:1', 
        ], [
            'Kode_Role.required'                 => 'Penempatan/Role Tidak Boleh Kosong !',
            'data.*.Id_Jenis_Analisa.required'   => 'Jenis Analisa Tidak Boleh Kosong !',
            'data.*.Id_Quality_Control.required' => 'Minimal 1 Quality Control harus dipilih !',
        ]);

        DB::beginTransaction();

        try {
            $kodeRoleValid = ($jumlahRole === 1) 
                ? (is_object($roles[0]) ? $roles[0]->Kode_Role : $roles[0]['Kode_Role']) 
                : $request->Kode_Role;

            $insertData = [];

            foreach ($request->data as $index => $item) {
                $idJenisAnalisa   = $item['Id_Jenis_Analisa'];
                $keterangansArray = $item['Keterangans'] ?? [];

                foreach ($item['Id_Quality_Control'] as $idQualityControl) {
                    $exists = DB::table('N_EMI_LAB_Binding_Jenis_Analisa')
                        ->where('Id_Jenis_Analisa', $idJenisAnalisa)
                        ->where('Id_Quality_Control', $idQualityControl)
                        ->exists();

                    if ($exists) {
                        DB::rollBack();
                        return ResponseHelper::error(
                            "Data pada Analisa #" . ($index + 1) . " dengan kombinasi tersebut sudah ada di database.", 
                            422
                        );
                    }

                    $spesifikKeterangan = $keterangansArray[$idQualityControl] ?? null;
                    
                    $insertData[] = [
                        'Id_Jenis_Analisa'   => $idJenisAnalisa,
                        'Id_Quality_Control' => $idQualityControl,
                        'Keterangan'         => $spesifikKeterangan,
                        'Kode_Role'          => $kodeRoleValid,
                        'Id_User'            => $pengguna->Id_User ?? $pengguna->id, 
                    ];
                }
            }

            if (!empty($insertData)) {
                DB::table('N_EMI_LAB_Binding_Jenis_Analisa')->insert($insertData);
            }

            DB::commit();

            return ResponseHelper::success(
                $insertData, 
                "Semua Data Berhasil Disimpan", 
                201
            );

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('BindingJenisAnalisaController')->error('Error Binding store: ' . $e->getMessage());
            return ResponseHelper::error(
                "Terjadi Kesalahan Sistem",
                500
            );
        }
    }

    public function getDataDetailById($id)
    {
        try {
            $decodedId = Hashids::connection('custom')->decode($id)[0];
        } catch (\Exception $e) {
            return ResponseHelper::error('Format ID tidak valid.', 400);
        }

        // Ambil semua data binding berdasarkan Id_Jenis_Analisa
        $data = DB::table('N_EMI_LAB_Binding_Jenis_Analisa')
            ->where('Id_Jenis_Analisa', $decodedId)
            ->get();

        if ($data->isEmpty()) {
            return ResponseHelper::error('Data tidak ditemukan.', 404);
        }

        $result = [
            'Kode_Role'          => $data->first()->Kode_Role,
            'Id_Jenis_Analisa'   => $data->first()->Id_Jenis_Analisa,
            'Id_Quality_Control' => $data->pluck('Id_Quality_Control')->toArray(),
            // Map keterangan berdasarkan Id_Quality_Control
            'Keterangans'        => $data->pluck('Keterangan', 'Id_Quality_Control')->toArray(), 
        ];

        return ResponseHelper::success($result);
    }

    public function show($id_jenis_analisa)
    {
        return inertia("vue/dashboard/binding-parameter-jenis-analisa/DetailBindingParameter", [
            'id' => $id_jenis_analisa
        ]);
    }

    public function getDetailParameterJenisAnalisa(Request $request, $id_jenis_analisa)
    {
        try {
            $id_jenis_analisa = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
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

            $total = DB::table('N_EMI_LAB_Binding_Jenis_Analisa')
                ->where('Id_Jenis_Analisa', $id_jenis_analisa)
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
                        B.*,
                        J.Kode_Analisa AS kode_analisa,
                        J.Jenis_Analisa AS jenis_analisa,
                        Q.Kode_Uji AS kode_uji,
                        Q.Keterangan AS nama_parameter,
                        Q.Satuan AS satuan,
                        ROW_NUMBER() OVER (ORDER BY Q.Kode_Uji ASC) AS RowNum
                    FROM
                        N_EMI_LAB_Binding_Jenis_Analisa B
                    JOIN N_EMI_LAB_Jenis_Analisa J
                        ON B.Id_Jenis_Analisa = J.id
                    JOIN EMI_Quality_Control Q
                        ON B.Id_Quality_Control = Q.Id_QC_Formula
                    WHERE
                        B.Id_Jenis_Analisa = ?
                ) AS TempTable
                WHERE RowNum BETWEEN ? AND ?
            ";

            $getData = DB::select($query, [
                $id_jenis_analisa,
                $offset + 1,
                $offset + $limit
            ]);

            $encodedData = collect($getData)->map(function ($item) {
                $item->id = $item->id ? Hashids::connection('custom')->encode($item->id) : null;
                $item->Id_Jenis_Analisa = $item->Id_Jenis_Analisa ? Hashids::connection('custom')->encode($item->Id_Jenis_Analisa) : null;
                $item->Id_Quality_Control = $item->Id_Quality_Control ? Hashids::connection('custom')->encode($item->Id_Quality_Control) : null;
                return $item;
            });

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Data Ditemukan",
                'result' => $encodedData,
                'page' => $page,
                'total_page' => ceil($total / $limit),
                'total_data' => $total
            ], 200);

        } catch (\Exception $e) {
            Log::channel('BindingJenisAnalisaController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'status' => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }

    public function searchDetailParameter(Request $request, $id_jenis_analisa)
    {
        try {
            $id_jenis_analisa = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
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

            $getData = DB::table('N_EMI_LAB_Binding_Jenis_Analisa as B')
                ->join('N_EMI_LAB_Jenis_Analisa as J', 'B.Id_Jenis_Analisa', '=', 'J.id')
                ->join('EMI_Quality_Control as Q', 'B.Id_Quality_Control', '=', 'Q.Id_QC_Formula')
                ->select(
                    'B.*',
                    'J.Kode_Analisa as kode_analisa',
                    'J.Jenis_Analisa as jenis_analisa',
                    'Q.Kode_Uji as kode_uji',
                    'Q.Keterangan as nama_parameter',
                    'Q.Satuan as satuan'
                )
                ->where('B.Id_Jenis_Analisa', $id_jenis_analisa)
                ->where(function ($query) use ($keyword) {
                    $query->whereRaw('LOWER(Q.Keterangan) LIKE ?', ['%' . strtolower($keyword) . '%'])
                        ->orWhereRaw('LOWER(Q.Kode_Uji) LIKE ?', ['%' . strtolower($keyword) . '%']);
                })
                ->get();

            if ($getData->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "Data tidak ditemukan."
                ], 404);
            }

            $encodedData = collect($getData)->map(function ($item) {
                /** @var \stdClass $item */
                $item->id = $item->id ? Hashids::connection('custom')->encode($item->id) : null;
                $item->Id_Jenis_Analisa = $item->Id_Jenis_Analisa ? Hashids::connection('custom')->encode($item->Id_Jenis_Analisa) : null;
                $item->Id_Quality_Control = $item->Id_Quality_Control ? Hashids::connection('custom')->encode($item->Id_Quality_Control) : null;
                return $item;
            });


            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Data ditemukan.",
                'result' => $encodedData
            ], 200);

        } catch (\Exception $e) {
            Log::channel('BindingJenisAnalisaController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'status' => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }

    public function edit($id)
    {
        $roles = Session::get("User_Roles") ?? [];
        return inertia("vue/dashboard/binding-parameter-jenis-analisa/FormEditBindingParameter", [
            'roles' => $roles,
            "id" => $id
        ]);
    }

    public function update(Request $request, $id)
    {
        try {
            $decodedId = Hashids::connection('custom')->decode($id)[0];
        } catch (\Exception $e) {
            return ResponseHelper::error('Format ID tidak valid.', 400);
        }

        $roles      = Session::get("User_Roles") ?? [];
        $jumlahRole = count($roles);

        $request->validate([
            'Kode_Role'          => $jumlahRole > 1 ? 'required' : 'nullable',
            'Id_Jenis_Analisa'   => 'required',
            'Id_Quality_Control' => 'required|array|min:1',
        ], [
            'Kode_Role.required'          => 'Penempatan/Role Tidak Boleh Kosong!',
            'Id_Jenis_Analisa.required'   => 'Jenis Analisa tidak boleh kosong!',
            'Id_Quality_Control.required' => 'Minimal 1 Parameter Quality harus dipilih!',
        ]);

        DB::beginTransaction();

        try {
            $kodeRoleValid = ($jumlahRole === 1) 
                ? (is_object($roles[0]) ? $roles[0]->Kode_Role : $roles[0]['Kode_Role']) 
                : $request->Kode_Role;
            
            $pengguna = Auth::user();
            $newIdJenisAnalisa = $request->Id_Jenis_Analisa;

            // Jika user iseng mengganti Jenis Analisa ke Analisa lain yang sudah ada datanya
            if ($newIdJenisAnalisa != $decodedId) {
                $existsOther = DB::table('N_EMI_LAB_Binding_Jenis_Analisa')
                    ->where('Id_Jenis_Analisa', $newIdJenisAnalisa)
                    ->exists();

                if ($existsOther) {
                    DB::rollBack();
                    return ResponseHelper::error("Kombinasi Jenis Analisa tersebut sudah memiliki data. Silakan edit langsung di data terkait.", 422);
                }

                // Perbarui kepemilikan Id_Jenis_Analisa ke ID yang baru
                DB::table('N_EMI_LAB_Binding_Jenis_Analisa')
                    ->where('Id_Jenis_Analisa', $decodedId)
                    ->update(['Id_Jenis_Analisa' => $newIdJenisAnalisa]);
            }

            // Dapatkan Data Eksisting
            $existingQcs = DB::table('N_EMI_LAB_Binding_Jenis_Analisa')
                ->where('Id_Jenis_Analisa', $newIdJenisAnalisa)
                ->pluck('Id_Quality_Control')
                ->toArray();

            $newQcs = $request->Id_Quality_Control;

            // Komparasi Diffing
            $toDelete = array_diff($existingQcs, $newQcs);
            $toInsert = array_diff($newQcs, $existingQcs);
            $toUpdate = array_intersect($newQcs, $existingQcs);

            // 1. Hapus QC yang di-uncheck
            if (!empty($toDelete)) {
                DB::table('N_EMI_LAB_Binding_Jenis_Analisa')
                    ->where('Id_Jenis_Analisa', $newIdJenisAnalisa)
                    ->whereIn('Id_Quality_Control', $toDelete)
                    ->delete();
            }

            // 2. Perbarui QC yang tetap ada (Keterangan/Role mungkin berubah)
            foreach ($toUpdate as $qcId) {
                DB::table('N_EMI_LAB_Binding_Jenis_Analisa')
                    ->where('Id_Jenis_Analisa', $newIdJenisAnalisa)
                    ->where('Id_Quality_Control', $qcId)
                    ->update([
                        'Keterangan' => $request->Keterangans[$qcId] ?? null,
                        'Kode_Role'  => $kodeRoleValid,
                    ]);
            }

            // 3. Tambahkan QC baru yang baru di-check
            $insertData = [];
            foreach ($toInsert as $qcId) {
                $insertData[] = [
                    'Id_Jenis_Analisa'   => $newIdJenisAnalisa,
                    'Id_Quality_Control' => $qcId,
                    'Keterangan'         => $request->Keterangans[$qcId] ?? null,
                    'Kode_Role'          => $kodeRoleValid,
                    'Id_User'            => $pengguna->Id_User ?? $pengguna->id,
                ];
            }

            if (!empty($insertData)) {
                DB::table('N_EMI_LAB_Binding_Jenis_Analisa')->insert($insertData);
            }

            DB::commit();

            return ResponseHelper::success(null, "Data Berhasil Diperbarui", 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('BindingJenisAnalisaController')->error('Error Binding update: ' . $e->getMessage());
            return ResponseHelper::error("Terjadi Kesalahan Sistem: " . $e->getMessage(), 500);
        }
    }
    
}
