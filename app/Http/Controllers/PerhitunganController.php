<?php

namespace App\Http\Controllers;

use App\Models\Perhitungan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Vinkla\Hashids\Facades\Hashids;

class PerhitunganController extends Controller
{
    
    public function index()
    {
        return inertia("vue/dashboard/rumus-perhitungan/HomeRumusPerhitungan")->withViewData([
            "layout" => "layouts.master2"
        ]);
    }

    public function getDataRumusPerhitungan()
    {
        $kodeRoles = collect(Session::get('User_Roles', []))->pluck('Kode_Role')->toArray();
        
        if (empty($kodeRoles)) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Data tidak ditemukan (Role kosong).'
            ], 404);
        }

        // Eksekusi menggunakan Query Builder
        $results = DB::table('N_EMI_LAB_Perhitungan as p')
            ->select(
                'p.Id_Jenis_Analisa',
                DB::raw('MAX(j.Kode_Analisa) as kode_analisa'),
                DB::raw('MAX(j.Jenis_Analisa) as jenis_analisa'),
                DB::raw('COUNT(p.Id_Jenis_Analisa) as total_data'),
                DB::raw('MAX(m.Nama_Mesin) as nama_mesin')
            )
            ->join('N_EMI_LAB_Jenis_Analisa as j', 'p.Id_Jenis_Analisa', '=', 'j.id')
            ->leftJoin('N_EMI_LAB_Mesin_Analisa as m', 'j.Id_Mesin', '=', 'm.No_Urut')
            ->whereIn('p.Kode_Role', $kodeRoles) 
            ->groupBy('p.Id_Jenis_Analisa')
            ->get();

        // Karena hasil dari ->get() sudah berupa Collection, kita bisa langsung map()
        $getData = $results->map(function ($item) {
            $item->Id_Jenis_Analisa = Hashids::connection('custom')->encode($item->Id_Jenis_Analisa);
            return $item;
        });

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
            'message' => "Data Ditemukan",
            'result' => $getData
        ], 200);
    }

    public function create()
    {
        $roles = Session::get("User_Roles") ?? [];
        return inertia("vue/dashboard/rumus-perhitungan/FormRumusPerhitungan", [
            'roles' => $roles
        ])->withViewData([
            'layout' => 'layouts.master2'
        ]);
    }

    public function store(Request $request)
    {
        $userId = Auth::user()->UserId ?? 'SYSTEM';
        $roles      = Session::get("User_Roles") ?? [];
        $jumlahRole = count($roles);

        $request->validate([
            'Kode_Role'         => $jumlahRole > 1 ? 'required' : 'nullable',
            'Id_Jenis_Analisa'  => 'required',
            'Rumus'             => 'required',
            'Nama_Kolom'        => 'required',
            'Hasil_Perhitungan' => 'required'
        ], [
            'Kode_Role.required'         => 'Penempatan/Role Tidak Boleh Kosong',
            'Id_Jenis_Analisa.required'  => 'Jenis Analisa Tidak Boleh Kosong',
            'Rumus.required'             => 'Rumus Tidak Boleh Kosong',
            'Nama_Kolom.required'        => 'Nama Kolom Tidak Boleh Kosong',
            'Hasil_Perhitungan.required' => 'Hasil Perhitungan (Digit Belakang Koma) Tidak Boleh Kosong'
        ]);

        $kodeRoleValid = ($jumlahRole === 1) 
                ? (is_object($roles[0]) ? $roles[0]->Kode_Role : $roles[0]['Kode_Role']) 
                : $request->Kode_Role;

        $exists = DB::table('N_EMI_LAB_Perhitungan')->where([
                ['Id_Jenis_Analisa', '=', $request->Id_Jenis_Analisa],
                ['Nama_Kolom', '=', $request->Nama_Kolom],
                ['Kode_Role', '=', $kodeRoleValid] 
        ])->exists();

        if ($exists) {
            return response()->json([
                    'success' => false,
                    'status'  => 409,
                    'message' => 'Data dengan kombinasi (Jenis Analisa, Nama Kolom & Role) yang sama sudah ada.',
            ], 409);
        }

        DB::beginTransaction();
        try {
            
            $data = [
                'Kode_Perusahaan'   => '001',
                'Id_Jenis_Analisa'  => $request->Id_Jenis_Analisa,
                'Rumus'             => $request->Rumus,
                'Nama_Kolom'        => $request->Nama_Kolom,
                'Hasil_Perhitungan' => $request->Hasil_Perhitungan,
                'Kode_Role'         => $kodeRoleValid,
                'Id_User'           => $userId
            ];

            DB::table('N_EMI_LAB_Perhitungan')->insert($data);
            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => "Data Berhasil Disimpan",
                'data' => $data
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('PerhitunganController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'status' => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }

    private function extractIdsFromFormula($formula)
    {
        preg_match_all('/\[(\d+)\]/', $formula, $matches);
        return $matches[1]; 
    }

    private function convertFormulaToLabel($formula)
    {
        // dd($formula);
        $ids = $this->extractIdsFromFormula($formula);

        if (empty($ids)) {
            return $formula;
        }

        $qcParams = DB::table('EMI_Quality_Control')
            ->whereIn('Id_QC_Formula', $ids)
            ->pluck('Keterangan', 'Id_QC_Formula')
            ->toArray();

        foreach ($qcParams as $id => $keterangan) {
            $formula = str_replace("[$id]", "[$keterangan]", $formula);
        }

        return $formula;
    }
    
    private function convertFormulaToHashed($formula)
    {
        preg_match_all('/\[(\d+)\]/', $formula, $matches);
        $ids = $matches[1];

        foreach ($ids as $id) {
            $hash = Hashids::connection('custom')->encode($id);
            $formula = str_replace("[$id]", "[$hash]", $formula);
        }

        return $formula;
    }

    public function getDetailData(Request $request, $id_jenis_analisa)
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

            // Total Count
            $total = DB::table('N_EMI_LAB_Perhitungan')
                ->where('Id_Jenis_Analisa', $id_jenis_analisa)
                ->count();

            // Main Query with OFFSET-FETCH
            $getData = DB::select("
                SELECT 
                    j.Kode_Analisa AS kode_analisa,
                    j.Jenis_Analisa AS jenis_analisa,
                    p.*
                FROM N_EMI_LAB_Perhitungan p
                JOIN N_EMI_LAB_Jenis_Analisa j ON p.Id_Jenis_Analisa = j.id
                WHERE p.Id_Jenis_Analisa = ?
                ORDER BY p.Id_Jenis_Analisa
                OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
            ", [$id_jenis_analisa, $offset, $limit]);

            if (empty($getData)) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "Data Tidak Ditemukan"
                ], 404);
            }

            foreach ($getData as &$item) {
                $item->id = Hashids::connection('custom')->encode($item->id);
                $item->Id_Jenis_Analisa = Hashids::connection('custom')->encode($item->Id_Jenis_Analisa);
                $item->Formula_Label = $this->convertFormulaToLabel($item->Rumus ?? '');
                $item->Rumus = $this->convertFormulaToHashed($item->Rumus ?? '');
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
            Log::channel('PerhitunganController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'status' => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }

    public function searchDetailData(Request $request, $id_jenis_analisa)
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

            $getData = DB::table('N_EMI_LAB_Perhitungan as p')
                ->join('N_EMI_LAB_Jenis_Analisa as j', 'p.Id_Jenis_Analisa', '=', 'j.id')
                ->select(
                    'j.Kode_Analisa as kode_analisa',
                    'j.Jenis_Analisa as jenis_analisa',
                    'p.*'
                )
                ->where('p.Id_Jenis_Analisa', $id_jenis_analisa)
                ->whereRaw('LOWER(p.Nama_Kolom) LIKE ?', ['%' . strtolower($keyword) . '%'])
                ->get();

            if ($getData->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "Data tidak ditemukan."
                ], 404);
            }

            foreach ($getData as &$item) {
                $item->Formula_Label = $this->convertFormulaToLabel($item->Rumus ?? '');
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Data ditemukan.",
                'result' => $getData
            ], 200);

        } catch (\Exception $e) {
            Log::channel('PerhitunganController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'status' => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }

    public function show($id_jenis_analisa)
    {

        return inertia('vue/dashboard/rumus-perhitungan/DetailRumusPerhitungan', [
            'id' => $id_jenis_analisa
        ])->withViewData([
            'layout' => 'layouts.master2'
        ]);
    }

    public function edit($id)
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

        $roles = Session::get("User_Roles") ?? [];
        $getData = DB::table('N_EMI_LAB_Perhitungan')->where('id', $id)->first();
        return inertia("vue/dashboard/rumus-perhitungan/FormEditRumusPerhitungan", [
            'getData' => $getData,
            'roles' => $roles
        ]);
    }

    public function update(Request $request, $id)
    {
        $userId = Auth::user()->UserId ?? 'SYSTEM';
        $getData = DB::table('N_EMI_LAB_Perhitungan')->where('id', $id)->first();

        if(!$getData){
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Data Tidak Ditemukan !'
            ], 404);
        }

        $roles      = Session::get("User_Roles") ?? [];
        $jumlahRole = count($roles);

        $request->validate([
            'Kode_Role'         => $jumlahRole > 1 ? 'required' : 'nullable',
            'Id_Jenis_Analisa'  => 'required',
            'Rumus'             => 'required',
            'Nama_Kolom'        => 'required',
            'Hasil_Perhitungan' => 'required'
        ], [
            'Kode_Role.required'         => 'Penempatan/Role Tidak Boleh Kosong',
            'Id_Jenis_Analisa.required'  => 'Jenis Analisa Tidak Boleh Kosong',
            'Rumus.required'             => 'Rumus Tidak Boleh Kosong',
            'Nama_Kolom.required'        => 'Nama Kolom Tidak Boleh Kosong',
            'Hasil_Perhitungan.required' => 'Hasil Perhitungan (Digit Belakang Koma) Tidak Boleh Kosong'
        ]);

        $kodeRoleValid = ($jumlahRole === 1) 
                ? (is_object($roles[0]) ? $roles[0]->Kode_Role : $roles[0]['Kode_Role']) 
                : $request->Kode_Role;

        $exists = DB::table('N_EMI_LAB_Perhitungan')->where([
                ['Id_Jenis_Analisa', '=', $request->Id_Jenis_Analisa],
                ['Nama_Kolom', '=', $request->Nama_Kolom],
                ['Kode_Role', '=', $kodeRoleValid],
                ['id', '!=', $id] 
        ])->exists();

        if ($exists) {
            return response()->json([
                    'success' => false,
                    'status'  => 409,
                    'message' => 'Data dengan kombinasi (Jenis Analisa, Nama Kolom & Role) yang sama sudah ada.',
            ], 409);
        }

        DB::beginTransaction();

        try {
            $data = [
                'Kode_Role'         => $kodeRoleValid,
                'Id_Jenis_Analisa' => $request->Id_Jenis_Analisa,
                'Rumus' => $request->Rumus,
                'Nama_Kolom' => $request->Nama_Kolom,
                'Hasil_Perhitungan' => $request->Hasil_Perhitungan,
                'Id_user' => $userId
            ];

            DB::table('N_EMI_LAB_Perhitungan')->where('id', $id)->update($data);
            DB::commit();
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Data Berhasil Diupdate",
                $data
            ], 200);
        }catch(\Exception $e){
            DB::rollBack();
            Log::channel('PerhitunganController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'status' => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }
}
