<?php

namespace App\Http\Controllers;

use App\Models\JenisAnalisaBerkala;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Vinkla\Hashids\Facades\Hashids;

class JenisAnalisaBerkalaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return inertia('vue/dashboard/sub-jenis-analisa/HomeSubJenisAnalisa');
    }

    public function getData(Request $request)
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
            $limit = (int) $request->query('limit', 10);
            $page = (int) $request->query('page', 1);
            $offset = ($page - 1) * $limit;

            $total = DB::table('N_EMI_LAB_Jenis_Analisa_Berkala')
                ->whereIn('Kode_Role', $kodeRoles)
                ->count();

            $getData = DB::table('N_EMI_LAB_Jenis_Analisa_Berkala as berkala')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'berkala.Id_Jenis_Analisa', '=', 'ja.id')
                ->join('N_EMI_LAB_Jenis_Analisa as sub_ja', 'berkala.Id_Sub_Jenis_Analisa', '=', 'sub_ja.id')
                ->select(
                    'berkala.Id_Jenis_Analisa_Berkala',
                    'berkala.Id_Jenis_Analisa',
                    'berkala.Id_Sub_Jenis_Analisa',
                    'ja.Jenis_Analisa as Jenis_Analisa',
                    'sub_ja.Jenis_Analisa as Sub_Jenis_Analisa'
                )
                ->whereIn('berkala.Kode_Role', $kodeRoles)
                ->offset($offset)
                ->limit($limit)
                ->get();

            if ($getData->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'status'  => 404,
                    'message' => 'Data Tidak Ditemukan'
                ], 404);
            }

            $encodedData = $getData->map(function ($item) {
                $item->Id_Jenis_Analisa_Berkala = Hashids::connection('custom')->encode($item->Id_Jenis_Analisa_Berkala);
                $item->Id_Jenis_Analisa         = Hashids::connection('custom')->encode($item->Id_Jenis_Analisa);
                $item->Id_Sub_Jenis_Analisa     = Hashids::connection('custom')->encode($item->Id_Sub_Jenis_Analisa);
                return $item;
            });

            return response()->json([
                'success'    => true,
                'status'     => true,
                'data'       => $encodedData,
                'page'       => $page,
                'total_page' => ceil($total / $limit),
                'total_data' => $total
            ]);
        } catch (\Exception $e) {
            Log::channel('JenisAnalisaBerkalaController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }

    public function search(Request $request)
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
            $keyword = $request->input('q');

            if (empty($keyword)) {
                return response()->json([
                    'success' => false,
                    'status'  => 400,
                    'message' => [
                        'error' => "Kata kunci pencarian tidak boleh kosong."
                    ]
                ], 400);
            }

            $getData = DB::table('N_EMI_LAB_Jenis_Analisa_Berkala as berkala')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'berkala.Id_Jenis_Analisa', '=', 'ja.id')
                ->join('N_EMI_LAB_Jenis_Analisa as sub_ja', 'berkala.Id_Sub_Jenis_Analisa', '=', 'sub_ja.id')
                ->select(
                    'berkala.Id_Jenis_Analisa_Berkala',
                    'berkala.Id_Jenis_Analisa',
                    'berkala.Id_Sub_Jenis_Analisa',
                    'ja.Jenis_Analisa as Jenis_Analisa',
                    'sub_ja.Jenis_Analisa as Sub_Jenis_Analisa'
                )
                ->whereIn('berkala.Kode_Role', $kodeRoles)
                ->where(function ($query) use ($keyword) {
                    $query->whereRaw('LOWER(ja.Jenis_Analisa) LIKE ?', ['%' . strtolower($keyword) . '%'])
                          ->orWhereRaw('LOWER(sub_ja.Jenis_Analisa) LIKE ?', ['%' . strtolower($keyword) . '%']);
                })
                ->get();

            if ($getData->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'status'  => 404,
                    'message' => "Data tidak ditemukan."
                ], 404);
            }

            $encodedData = $getData->map(function ($item) {
                $item->Id_Jenis_Analisa_Berkala = Hashids::connection('custom')->encode($item->Id_Jenis_Analisa_Berkala);
                $item->Id_Jenis_Analisa         = Hashids::connection('custom')->encode($item->Id_Jenis_Analisa);
                $item->Id_Sub_Jenis_Analisa     = Hashids::connection('custom')->encode($item->Id_Sub_Jenis_Analisa);
                return $item;
            });

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => "Data ditemukan.",
                'result'  => $encodedData
            ], 200);

        } catch (\Exception $e) {
            Log::channel('JenisAnalisaBerkalaController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }


    public function getJenisAnalisaBerkala()
    {
        try {
            $getData = DB::table('N_EMI_LAB_Jenis_Analisa')
                    ->select('*')  
                    ->where('Sifat_Kegiatan', 'Berkala')
                    ->get()
                    ->map(function ($item) {
                        $item->id = Hashids::connection('custom')->encode($item->id);
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
            Log::channel('JenisAnalisaBerkalaController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'status' => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }
    public function getJenisAnalisaRutin()
    {
        try {
            $getData = DB::table('N_EMI_LAB_Jenis_Analisa')
                    ->select('*')  
                    ->where('Sifat_Kegiatan', 'Rutin')
                    ->get()
                    ->map(function ($item) {
                        $item->id = Hashids::connection('custom')->encode($item->id);
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
            Log::channel('JenisAnalisaBerkalaController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'status' => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }

  
   public function create()
    {
        $roles = Session::get("User_Roles") ?? [];
        return inertia('vue/dashboard/sub-jenis-analisa/FormSubJenisAnalisa', [
            'roles' => $roles
        ]);
    }

    public function store(Request $request)
    {
        try {
            $pengguna = Auth::user();
            $roles = Session::get("User_Roles") ?? [];
            $jumlahRole = count($roles);

            $request->validate([
                'Kode_Role'                 => $jumlahRole > 1 ? 'required' : 'nullable',
                'Id_Jenis_Analisa'          => 'required',
                'Id_Sub_Jenis_Analisa_List' => 'required|array|min:1',
            ], [
                'Kode_Role.required'                 => 'Penempatan/Role Tidak Boleh Kosong!',
                'Id_Jenis_Analisa.required'          => 'Jenis Analisa Tidak Boleh Kosong!',
                'Id_Sub_Jenis_Analisa_List.required' => 'Minimal 1 Sub Jenis Analisa harus dipilih!',
            ]);

            $decodedJenis = Hashids::connection('custom')->decode($request->Id_Jenis_Analisa);
            if (empty($decodedJenis)) {
                throw new \Exception("Format ID Jenis Analisa tidak valid.");
            }
            $decodedIdJenis = $decodedJenis[0];

            $decodedSubList = [];
            foreach ($request->Id_Sub_Jenis_Analisa_List as $encodedId) {
                $decoded = Hashids::connection('custom')->decode($encodedId);
                if (empty($decoded)) {
                    throw new \Exception("Format salah pada salah satu ID Sub Jenis Analisa.");
                }
                $decodedSubList[] = $decoded[0];
            }

            DB::beginTransaction();

            $kodePerusahaan = '001';
            $inserted = [];
            
            $kodeRoleValid = ($jumlahRole === 1) 
                ? (is_object($roles[0]) ? $roles[0]->Kode_Role : $roles[0]['Kode_Role']) 
                : $request->Kode_Role;

            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow; 

            $tanggalSqlServer = date('Y-m-d', strtotime($dt)); 
            $jamSqlServer = date('H:i:s', strtotime($dt));  

            foreach ($decodedSubList as $subId) {
                $existing = DB::table('N_EMI_LAB_Jenis_Analisa_Berkala')
                    ->where('Kode_Perusahaan', $kodePerusahaan)
                    ->where('Id_Jenis_Analisa', $decodedIdJenis)
                    ->where('Id_Sub_Jenis_Analisa', $subId)
                    ->first();

                if (!$existing) {
                    $payload = [
                        'Kode_Perusahaan'      => $kodePerusahaan,
                        'Id_Jenis_Analisa'     => $decodedIdJenis,
                        'Id_Sub_Jenis_Analisa' => $subId,
                        'Kode_Role'            => $kodeRoleValid,
                        'Tanggal'              => $tanggalSqlServer,
                        'Jam'                  => $jamSqlServer,
                        'Id_User'              => $pengguna->UserId ?? $pengguna->id,
                    ];

                    DB::table('N_EMI_LAB_Jenis_Analisa_Berkala')->insert($payload);
                    $inserted[] = $payload;
                }
            }

            DB::commit();

            if (count($inserted)) {
                return response()->json([
                    'success' => true,
                    'status'  => 201,
                    'message' => 'Data berhasil disimpan.',
                    'data'    => $inserted
                ], 201);
            } else {
                return response()->json([
                    'success' => false,
                    'status'  => 409,
                    'message' => 'Semua data sudah ada, tidak ada yang disimpan.'
                ], 409);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('JenisAnalisaBerkalaController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'status'  => 500,
                'message' => "Terjadi Kesalahan: " . $e->getMessage(),
            ], 500); 
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $id = Hashids::connection('custom')->decode($id)[0];
            $Id_Jenis_Analisa = Hashids::connection('custom')->decode($request->Id_Jenis_Analisa)[0];
            $Id_Sub_Jenis_Analisa = Hashids::connection('custom')->decode($request->Id_Sub_Jenis_Analisa)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Jenis Analisa tidak valid.'
            ], 400);
        }

        $request->validate([
            'Id_Jenis_Analisa' => 'required',
            'Id_Sub_Jenis_Analisa' => 'required',
        ]);

        DB::beginTransaction();
        try {
            $kodePerusahaan = '001';

       
            $data = DB::table('N_EMI_LAB_Jenis_Analisa_Berkala')->where('Id_Jenis_Analisa_Berkala', $id)->first();
            if (!$data) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Data tidak ditemukan.'
                ], 404);
            }

            $duplicate = DB::table('N_EMI_LAB_Jenis_Analisa_Berkala')
                ->where('Kode_Perusahaan', $kodePerusahaan)
                ->where('Id_Jenis_Analisa', $Id_Jenis_Analisa)
                ->where('Id_Sub_Jenis_Analisa', $Id_Sub_Jenis_Analisa)
                ->where('Id_Jenis_Analisa_Berkala', '!=', $id)
                ->first();

            if ($duplicate) {
                return response()->json([
                    'success' => false,
                    'status' => 409,
                    'message' => 'Perubahan ini menyebabkan duplikasi data.'
                ], 409);
            }

            DB::table('N_EMI_LAB_Jenis_Analisa_Berkala')
                ->where('Id_Jenis_Analisa_Berkala', $id)
                ->update([
                    'Kode_Perusahaan' => $kodePerusahaan,
                    'Id_Jenis_Analisa' => $Id_Jenis_Analisa,
                    'Id_Sub_Jenis_Analisa' => $Id_Sub_Jenis_Analisa,
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Data berhasil diperbarui.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('JenisAnalisaBerkalaController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'status' => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }
}
