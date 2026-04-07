<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\BarangAnalisa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Vinkla\Hashids\Facades\Hashids;

class BarangAnalisaController extends Controller
{
    public function index()
    {
        return inertia('vue/dashboard/barang-analisa/HomeBarangAnalisa');
    }

    public function getDataBarangAnalisa()
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
            $getData = DB::table('N_EMI_LAB_Barang_Analisa as BA')
                ->join('N_EMI_LAB_Jenis_Analisa as J', 'BA.Id_Jenis_Analisa', '=', 'J.id')
                ->select(
                    'BA.Id_Jenis_Analisa',
                    DB::raw('MAX(J.Kode_Analisa) as kode_analisa'),
                    DB::raw('MAX(J.Jenis_Analisa) as jenis_analisa'),
                    DB::raw('COUNT(BA.Id_Jenis_Analisa) as total_data')
                )
                ->whereIn('BA.Kode_Role', $kodeRoles)
                ->groupBy('BA.Id_Jenis_Analisa')
                ->get();

            if ($getData->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'status'  => 404,
                    'message' => "Data Tidak Ditemukan"
                ], 404);
            }

            $encodedData = $getData->map(function ($item) {
                $item->Id_Jenis_Analisa = Hashids::connection('custom')->encode($item->Id_Jenis_Analisa);
                return $item;
            });

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => "Data Ditemukan",
                'result'  => $encodedData
            ], 200);

        } catch(\Exception $e) {
            Log::channel('BarangAnalisaController')->error('Login Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }

    public function getDetailBarangAnalisa(Request $request, $id_jenis_analisa)
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
            $decoded = Hashids::connection('custom')->decode($id_jenis_analisa);
            
            if (empty($decoded)) {
                throw new \Exception('Format ID Jenis Analisa tidak valid.');
            }
            
            $id_jenis_analisa = $decoded[0];

        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'status'  => 400, 
                'message' => $e->getMessage()
            ], 400);
        }

        try {
            $limit = (int) $request->input('limit', 10);

            $subQueryBarang = DB::table('N_EMI_View_Barang')
                ->select([
                    'Kode_Barang', 
                    'Nama', 
                    'Kode_Stock_Owner', 
                    DB::raw('ROW_NUMBER() OVER(PARTITION BY Kode_Barang ORDER BY Kode_Barang) as r_view')
                ]);

            $query = DB::table(function ($sub) use ($id_jenis_analisa, $subQueryBarang, $kodeRoles) {
                $sub->from('N_EMI_LAB_Barang_Analisa as BA')
                    ->select([
                        'BA.id',
                        'BA.Id_User',
                        'J.Kode_Analisa as kode_analisa',
                        'J.Jenis_Analisa as jenis_analisa',
                        'B.Nama as nama_barang',
                        'BA.Kode_Barang as kode_barang',
                        'B.Kode_Stock_Owner as kode_stock_owner',
                        'mm.Nama_Mesin as nama_mesin',
                        'BA.Flag_Aktif',
                        DB::raw('ROW_NUMBER() OVER(PARTITION BY BA.Kode_Barang, BA.Id_Master_Mesin, BA.Id_User ORDER BY BA.id) as rn')
                    ])
                    ->leftJoin('N_EMI_LAB_Jenis_Analisa as J', 'BA.Id_Jenis_Analisa', '=', 'J.id')
                    ->leftJoinSub($subQueryBarang, 'B', function ($join) {
                        $join->on('BA.Kode_Barang', '=', 'B.Kode_Barang')
                             ->where('B.r_view', '=', 1);
                    })
                    ->leftJoin('EMI_Master_Mesin as mm', 'BA.Id_Master_Mesin', '=', 'mm.Id_Master_Mesin')
                    ->where('BA.Id_Jenis_Analisa', $id_jenis_analisa)
                    ->whereIn('BA.Kode_Role', $kodeRoles);
            }, 'RankedData')
            ->where('rn', 1)
            ->orderBy('id');

            $paginator = $query->paginate($limit);

            if ($paginator->isEmpty()) {
                return response()->json([
                    'success' => false, 
                    'status'  => 404, 
                    'message' => "Data Tidak Ditemukan"
                ], 404);
            }

            return response()->json([
                'success'    => true,
                'status'     => 200,
                'message'    => "Data Ditemukan",
                'result'     => $paginator->items(),
                'page'       => $paginator->currentPage(),
                'total_page' => $paginator->lastPage(),
                'total_data' => $paginator->total()
            ], 200);

        } catch (\Exception $e) {
            Log::channel('BarangAnalisaController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }

    public function searchDetailBarangAnalisa(Request $request, $id_jenis_analisa)
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
            $decoded = Hashids::connection('custom')->decode($id_jenis_analisa);
            
            if (empty($decoded)) {
                throw new \Exception('Format ID Jenis Analisa tidak valid.');
            }
            
            $id_jenis_analisa = $decoded[0];

        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'status'  => 400, 
                'message' => $e->getMessage()
            ], 400);
        }

        try {
            $keyword = $request->input('q');

            if (empty($keyword)) {
                return response()->json([
                    'success' => false,
                    'status'  => 400,
                    'message' => "Kata kunci pencarian tidak boleh kosong."
                ], 400);
            }

            $searchKeyword = '%' . strtolower($keyword) . '%';
            $limit = (int) $request->input('limit', 10);

            $subQueryBarang = DB::table('N_EMI_View_Barang')
                ->select([
                    'Kode_Barang', 
                    'Nama', 
                    'Kode_Stock_Owner', 
                    DB::raw('ROW_NUMBER() OVER(PARTITION BY Kode_Barang ORDER BY Kode_Barang) as r_view')
                ]);

            $query = DB::table(function ($sub) use ($id_jenis_analisa, $subQueryBarang, $searchKeyword, $kodeRoles) {
                $sub->from('N_EMI_LAB_Barang_Analisa as BA')
                    ->select([
                        'BA.id',
                        'BA.Id_User',
                        'J.Kode_Analisa as kode_analisa',
                        'J.Jenis_Analisa as jenis_analisa',
                        'B.Nama as nama_barang',
                        'BA.Kode_Barang as kode_barang',
                        'B.Kode_Stock_Owner as kode_stock_owner',
                        'mm.Nama_Mesin as nama_mesin',
                        'BA.Flag_Aktif',
                        DB::raw('ROW_NUMBER() OVER(PARTITION BY BA.Kode_Barang, BA.Id_Master_Mesin, BA.Id_User ORDER BY BA.id) as rn')
                    ])
                    ->leftJoin('N_EMI_LAB_Jenis_Analisa as J', 'BA.Id_Jenis_Analisa', '=', 'J.id')
                    ->leftJoinSub($subQueryBarang, 'B', function ($join) {
                        $join->on('BA.Kode_Barang', '=', 'B.Kode_Barang')
                             ->where('B.r_view', '=', 1);
                    })
                    ->leftJoin('EMI_Master_Mesin as mm', 'BA.Id_Master_Mesin', '=', 'mm.Id_Master_Mesin')
                    ->where('BA.Id_Jenis_Analisa', $id_jenis_analisa)
                    ->whereIn('BA.Kode_Role', $kodeRoles)
                    ->where(function ($q) use ($searchKeyword) {
                        $q->whereRaw('LOWER(J.Kode_Analisa) LIKE ?', [$searchKeyword])
                        ->orWhereRaw('LOWER(J.Jenis_Analisa) LIKE ?', [$searchKeyword])
                        ->orWhereRaw('LOWER(BA.Kode_Barang) LIKE ?', [$searchKeyword])
                        ->orWhereRaw('LOWER(B.Nama) LIKE ?', [$searchKeyword])
                        ->orWhereRaw('LOWER(mm.Nama_Mesin) LIKE ?', [$searchKeyword])
                        ->orWhereRaw('LOWER(BA.Id_User) LIKE ?', [$searchKeyword]);
                    });
            }, 'RankedData')
            ->where('rn', 1)
            ->orderBy('id');

            $paginator = $query->paginate($limit);

            if ($paginator->isEmpty()) {
                return response()->json([
                    'success' => false, 
                    'status'  => 404, 
                    'message' => "Data tidak ditemukan."
                ], 404);
            }

            return response()->json([
                'success'    => true,
                'status'     => 200,
                'message'    => "Data ditemukan.",
                'result'     => $paginator->items(),
                'page'       => $paginator->currentPage(),
                'total_page' => $paginator->lastPage(),
                'total_data' => $paginator->total()
            ], 200);

        } catch (\Exception $e) {
            Log::channel('BarangAnalisaController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }

    public function getDataJenisAnalisa()
    {
        try {
            $jenisAnalisa = DB::table('N_EMI_LAB_Jenis_Analisa')
                ->select('id', 'Jenis_Analisa', 'Kode_Analisa')
                ->get();

            foreach ($jenisAnalisa as $item) {
                $item->id = Hashids::connection('custom')->encode($item->id);
            }

            return ResponseHelper::success(
                $jenisAnalisa,
                'Data Jenis Analisa berhasil diambil'
            );
        } catch (\Exception $e) {
            Log::channel('BarangAnalisaController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'status' => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }

    public function getDataVarianBarang()
    {
        try {
            $barang = DB::table('N_EMI_View_Barang')
                ->select("Kode_Barang", "Nama")
                ->get()
                ->unique('Kode_Barang')
                ->values();

            return ResponseHelper::success(
                $barang,
                'Data Varian Barang berhasil diambil'
            );
        } catch (\Exception $e) {
           Log::channel('BarangAnalisaController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'status' => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }

    public function getDataMesin()
    {
        try {
            $mesin = DB::table('EMI_Master_Mesin')
            ->select('Id_Master_Mesin', 'Nama_Mesin', 'Seri_Mesin')
            ->get();

            foreach ($mesin as $item) {
                if (isset($item->Id_Master_Mesin)) {
                    $item->Id_Master_Mesin = Hashids::connection('custom')->encode($item->Id_Master_Mesin);
                }
            }

            return ResponseHelper::success(
                $mesin,
                'Data Mesin berhasil diambil'
            );
        } catch (\Exception $e) {
            Log::channel('BarangAnalisaController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'status' => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }

    public function getDataUser()
    {
        try {
            $user = DB::table('N_EMI_LAB_Users')
                ->select('UserId', 'Nama')
                ->get();

            return ResponseHelper::success(
                $user,
                'Data User berhasil diambil'
            );
        } catch (\Exception $e) {
            Log::channel('BarangAnalisaController')->error('Error: ' . $e->getMessage());
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
        return inertia("vue/dashboard/barang-analisa/FormBarangAnalisa", [
            'roles' => $roles
        ]);
    }

    public function store(Request $request)
    {
        $roles      = Session::get("User_Roles") ?? [];
        $jumlahRole = count($roles);

        $request->validate([
            'Kode_Role'                 => $jumlahRole > 1 ? 'required' : 'nullable',
            'group'                     => 'required|array',
            'group.*.Id_Jenis_Analisa'  => 'required',
            'group.*.Kode_Barang'       => 'required|array|min:1',
            'group.*.Id_Master_Mesin'   => 'required|array|min:1',
            'group.*.Id_User'           => 'required|array|min:1',
        ], [
            'Kode_Role.required'                  => 'Penempatan/Role Tidak Boleh Kosong!',
            'group.*.Id_Jenis_Analisa.required'   => 'Jenis Analisa wajib diisi!',
            'group.*.Kode_Barang.required'        => 'Barang wajib dipilih!',
            'group.*.Id_Master_Mesin.required'    => 'Mesin wajib dipilih!',
            'group.*.Id_User.required'            => 'User wajib dipilih!',
        ]);

        $pengguna = Auth::user()->UserId;
        $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
        $dt = $waktuServer[0]->DateTimeNow;
        $tanggalSqlServer = date('Y-m-d', strtotime($dt));
        $jamSqlServer = date('H:i:s', strtotime($dt));

        DB::beginTransaction();
        try {
            $dataToInsert = [];
            $duplicateLog = [];

            $cacheAllMesin = null;
            $cacheAllUser = null;

            $kodeRoleValid = ($jumlahRole === 1) 
                ? (is_object($roles[0]) ? $roles[0]->Kode_Role : $roles[0]['Kode_Role']) 
                : $request->Kode_Role;

            foreach ($request->group as $row) {
                $jenisAnalisa = Hashids::connection('custom')->decode($row['Id_Jenis_Analisa'])[0] ?? $row['Id_Jenis_Analisa'];
                
                $isAllMesin = in_array('ALL', $row['Id_Master_Mesin']);
                if ($isAllMesin) {
                    if ($cacheAllMesin === null) {
                        $cacheAllMesin = DB::table('EMI_Master_Mesin')->pluck('Id_Master_Mesin')->toArray();
                    }
                    $mesinList = $cacheAllMesin;
                } else {
                    $mesinList = array_map(function($m) {
                        return Hashids::connection('custom')->decode($m)[0] ?? $m;
                    }, $row['Id_Master_Mesin']);
                }

                $barangList = $row['Kode_Barang'];

                $isAllUser = in_array('ALL', $row['Id_User']);
                if ($isAllUser) {
                    if ($cacheAllUser === null) {
                        $cacheAllUser = DB::table('N_EMI_LAB_Users')->pluck('UserId')->toArray();
                    }
                    $userList = $cacheAllUser;
                } else {
                    $userList = $row['Id_User'];
                }

                $queryExisting = DB::table('N_EMI_LAB_Barang_Analisa')
                    ->select('Id_User', 'Kode_Barang', 'Id_Master_Mesin')
                    ->where('Id_Jenis_Analisa', $jenisAnalisa);

                if (!$isAllMesin) {
                    $queryExisting->whereIn('Id_Master_Mesin', $mesinList);
                }
                
                $queryExisting->whereIn('Kode_Barang', $barangList);

                if (!$isAllUser) {
                    $queryExisting->whereIn('Id_User', $userList);
                }

                $existingRecords = $queryExisting->get();
                
                $existingMap = [];
                foreach ($existingRecords as $rec) {
                    $key = $rec->Id_User . '|' . $rec->Kode_Barang . '|' . $rec->Id_Master_Mesin;
                    $existingMap[$key] = true;
                }

                foreach ($userList as $userId) {
                    foreach ($barangList as $kodeBarang) {
                        foreach ($mesinList as $mesinId) {
                            $checkKey = $userId . '|' . $kodeBarang . '|' . $mesinId;

                            if (isset($existingMap[$checkKey])) {
                                if (count($duplicateLog) < 10) { 
                                    $duplicateLog[] = "$userId - $kodeBarang"; 
                                }
                            } else {
                                $dataToInsert[] = [
                                    'Id_Jenis_Analisa' => $jenisAnalisa,
                                    'Id_Master_Mesin'  => $mesinId,
                                    'Id_User'          => $userId,
                                    'Kode_Barang'      => $kodeBarang,
                                    'Kode_Role'        => $kodeRoleValid,
                                    'Kode_Perusahaan'  => '001',
                                    'Tanggal'          => $tanggalSqlServer,
                                    'Jam'              => $jamSqlServer,
                                    'Id_User_Menginput'=> $pengguna
                                ];
                            }
                        }
                    }
                }
            }

            if (!empty($dataToInsert)) {
                foreach (array_chunk($dataToInsert, 200) as $chunk) {
                    DB::table('N_EMI_LAB_Barang_Analisa')->insert($chunk);
                }
            }

            DB::commit();
            
            $totalInserted = count($dataToInsert);
            $message = 'Semua data berhasil disimpan.';

            if (count($duplicateLog) > 0) {
                $message = "Data disimpan. Terdapat duplikat yang diabaikan (Total Insert: $totalInserted).";
            }

            return ResponseHelper::success(
                [
                    'inserted_count' => $totalInserted,
                    'duplicate_sample' => $duplicateLog
                ],
                $message,
                200
            );

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('BarangAnalisaController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => "Terjadi Kesalahan: " . $e->getMessage(),
            ], 500); 
        }
    }

    public function show($id)
    {
        return inertia("vue/dashboard/barang-analisa/DetailBarangAnalisa", [
            'id' => $id
        ]);
    }
}
