<?php

namespace App\Http\Controllers;

use App\Models\StandarRentang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Vinkla\Hashids\Facades\Hashids;

class StandarRentangController extends Controller
{

    public function index()
    {
        return inertia("vue/dashboard/standar-hasil-analisa/HomeStandarHasilAnalisa");
    }

    public function getAllCurrent(Request $request)
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
            $limit = max((int) $request->query('limit', 10), 1);
            $page = max((int) $request->query('page', 1), 1);
            $offset = ($page - 1) * $limit;
            $search = $request->query('search');

            $query1 = DB::table('N_EMI_LAB_Standar_Rentang as sr')
                ->leftJoin('N_EMI_LAB_Jenis_Analisa as ja', 'sr.Id_Jenis_Analisa', '=', 'ja.id')
                ->leftJoin('EMI_Master_Mesin as m', 'sr.Id_Master_Mesin', '=', 'm.Id_Master_Mesin')
                ->leftJoin('N_EMI_LAB_Perhitungan as p', 'sr.Id_Perhitungan', '=', 'p.id')
                ->leftJoin('N_EMI_View_Barang as vb', 'sr.Kode_Barang', '=', 'vb.Kode_Barang')
                ->select(
                    'sr.Id_Standar_Rentang as Id',
                    'sr.Kode_Perusahaan',
                    'sr.Id_Jenis_Analisa',
                    'sr.Kode_Barang',
                    'sr.Id_Master_Mesin',
                    'sr.Id_Perhitungan'
                )
                ->selectRaw("CAST(sr.Range_Awal AS VARCHAR) as Range_Awal")
                ->selectRaw("CAST(sr.Range_Akhir AS VARCHAR) as Range_Akhir")
                ->selectRaw("'Perhitungan' as Status_Analisa")
                ->selectRaw("'Y' as Flag_Layak")
                ->selectRaw('MAX(ja.Jenis_Analisa) as Jenis_Analisa')
                ->selectRaw('MAX(m.Nama_Mesin) as Nama_Mesin')
                ->selectRaw('MAX(p.Nama_Kolom) as Nama_Kolom')
                ->selectRaw('MAX(vb.Nama) as Nama_Barang')
                ->selectRaw('MAX(vb.Kode_Stock_Owner) as Kode_Stock_Owner')
                ->whereIn('sr.Kode_Role', $kodeRoles)
                ->groupBy(
                    'sr.Id_Standar_Rentang',
                    'sr.Kode_Perusahaan',
                    'sr.Id_Jenis_Analisa',
                    'sr.Kode_Barang',
                    'sr.Id_Master_Mesin',
                    'sr.Id_Perhitungan',
                    'sr.Range_Awal',
                    'sr.Range_Akhir'
                );

            $query2 = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan as non')
                ->leftJoin('N_EMI_LAB_Jenis_Analisa as ja', 'non.Id_Jenis_Analisa', '=', 'ja.id')
                ->select(
                    'non.Id_Standar_Rentang_Non_Perhitungan as Id',
                    'non.Kode_Perusahaan',
                    'non.Id_Jenis_Analisa'
                )
                ->selectRaw("NULL as Kode_Barang")
                ->selectRaw("NULL as Id_Master_Mesin")
                ->selectRaw("NULL as Id_Perhitungan")
                ->addSelect('non.Keterangan_Kriteria as Range_Awal')
                ->addSelect('non.Keterangan_Kriteria as Range_Akhir')
                ->selectRaw("'Non Perhitungan' as Status_Analisa")
                ->addSelect('non.Flag_Layak')
                ->addSelect('ja.Jenis_Analisa')
                ->selectRaw("'-' as Nama_Mesin")
                ->selectRaw("'-' as Nama_Kolom")
                ->selectRaw("'-' as Nama_Barang")
                ->selectRaw("NULL as Kode_Stock_Owner")
                ->whereIn('non.Kode_Role', $kodeRoles);

            $finalQuery = DB::query()->fromSub($query1->unionAll($query2), 'combined_table');

            $finalQuery->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('Kode_Barang', 'like', "%{$search}%")
                        ->orWhere('Nama_Barang', 'like', "%{$search}%")
                        ->orWhere('Jenis_Analisa', 'like', "%{$search}%");
                });
            });

            $finalQuery->when($request->query('kode_barang'), function ($q, $kode) {
                $q->where('Kode_Barang', 'like', "%{$kode}%");
            });

            $finalQuery->when($request->query('id_jenis_analisa'), function ($q, $id) {
                $q->where('Id_Jenis_Analisa', $id);
            });

            $total = $finalQuery->count();

            $data = $finalQuery
                ->orderBy('Id', 'asc')
                ->skip($offset)
                ->take($limit)
                ->get();

            if ($page > 1 && $data->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Data tidak ditemukan di halaman ini.'
                ], 404);
            }

            $data = $data->map(function ($item) {
                $item->Range_Awal = is_numeric($item->Range_Awal) ? (float)$item->Range_Awal : $item->Range_Awal;
                $item->Range_Akhir = is_numeric($item->Range_Akhir) ? (float)$item->Range_Akhir : $item->Range_Akhir;
                return $item;
            });

            return response()->json([
                'success'    => true,
                'status'     => 200,
                'message'    => 'Data Ditemukan',
                'result'     => $data,
                'page'       => $page,
                'total_page' => (int) ceil($total / $limit),
                'total_data' => $total
            ]);

        } catch (\Exception $e) {
            Log::channel('StandarRentangController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }

    public function getJenisAnalisa(Request $request)
    {
        $type = $request->query('type');

        $jenisAnalisa = DB::table('N_EMI_LAB_Jenis_Analisa')
            ->when($type === 'non-perhitungan', function ($query) {
                return $query->whereNull('Flag_Perhitungan');
            }, function ($query) {
                return $query->where('Flag_Perhitungan', 'Y');
            })
            ->get()
            ->map(function ($item) {
                $item->id = Hashids::connection('custom')->encode($item->id);
                $item->Id_Mesin = Hashids::connection('custom')->encode($item->Id_Mesin);
                return $item;
            });

        return response()->json([
            'success' => true,
            'status' => 200,
            'result' => $jenisAnalisa
        ]);
    }

    public function getBarangStandarRentang($id_jenis_analisa)
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

       $queryBarang = "
            select  b.Kode_Perusahaan, 
            b.Kode_Barang, 
            MIN(b.Kode_Stock_Owner) AS Kode_Stock_Owner,
            MIN(b.Nama) AS Nama
            from N_EMI_LAB_Barang_Analisa ba  
                    JOIN N_EMI_View_Barang b ON ba.Kode_Barang = b.Kode_Barang
                    where ba.Id_Jenis_Analisa = ?
                    GROUP BY 
            b.Kode_Perusahaan, 
            b.Kode_Barang;
        ";
        $barang = collect(DB::select($queryBarang, [$id_jenis_analisa]));

        return response()->json([
            'success' => true,
            'status' => 200,
            'result' => $barang
        ]);
    }
    public function getDaftarMesinStandar($id_jenis_analisa)
    {
        try {
            $decoded = Hashids::connection('custom')->decode($id_jenis_analisa);
            if (empty($decoded)) {
                throw new \Exception('Invalid ID');
            }
            $id_jenis_analisa = $decoded[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Format Kunci Jenis Analisa tidak valid.'
            ], 400);
        }
        $mesin = DB::table('N_EMI_LAB_Barang_Analisa')
        ->join('EMI_Master_Mesin', 'N_EMI_LAB_Barang_Analisa.Id_Master_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
        ->where('N_EMI_LAB_Barang_Analisa.Id_Jenis_Analisa', $id_jenis_analisa)
        ->select(
            'EMI_Master_Mesin.Id_Master_Mesin',
            'EMI_Master_Mesin.Kode_Perusahaan',
            DB::raw('MIN(EMI_Master_Mesin.Divisi_Mesin) AS Divisi_Mesin'),
            DB::raw('MIN(EMI_Master_Mesin.Seri_Mesin) AS Seri_Mesin'),
            DB::raw('MIN(EMI_Master_Mesin.Nama_Mesin) AS Nama_Mesin'),
            DB::raw('MIN(EMI_Master_Mesin.Keterangan) AS Keterangan'),
            DB::raw('MIN(EMI_Master_Mesin.NoUrut) AS NoUrut'),
            DB::raw('MIN(EMI_Master_Mesin.id_divisi_mesin) AS id_divisi_mesin'),
            DB::raw('MIN(EMI_Master_Mesin.Flag_Multi_Qrcode) AS Flag_Multi_Qrcode'),
            DB::raw('MIN(EMI_Master_Mesin.Jumlah_Print_QRCode) AS Jumlah_Print_QRCode'),
            DB::raw('MIN(EMI_Master_Mesin.Flag_Kg) AS Flag_Kg')
        )
        ->groupBy(
            'EMI_Master_Mesin.Id_Master_Mesin',
            'EMI_Master_Mesin.Kode_Perusahaan'
        )
        ->get()
        ->map(function ($item) {
            $item->Id_Master_Mesin = Hashids::connection('custom')->encode($item->Id_Master_Mesin);
            return $item;
        });


            return response()->json([
                'success' => true,
                'status' => 200,
                'result' => $mesin
            ]);
    }
    public function getPerhitunganListStandar($id_jenis_analisa)
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

        $perhitungan = DB::table('N_EMI_LAB_Perhitungan')
            ->select('id', 'Nama_Kolom')
            ->where('Id_Jenis_Analisa', $id_jenis_analisa)
            ->get()
            ->map(function ($item) {
                $item->id = Hashids::connection('custom')->encode($item->id);
                return $item;
            });

        return response()->json([
            'success' => true,
            'status' => 200,
            'result' => $perhitungan
        ]);
    }

    public function create()
    {
        $roles = Session::get("User_Roles") ?? [];
        return inertia("vue/dashboard/standar-hasil-analisa/FormStandarHasilAnalisa", [
            'roles' => $roles
        ]);
    }

    public function store(Request $request)
    {
        $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
        $dt = $waktuServer[0]->DateTimeNow;
        $tanggalSqlServer = date('Y-m-d', strtotime($dt));
        $jamSqlServer     = date('H:i:s', strtotime($dt));

        $userId = Auth::user()->UserId ?? 'SYSTEM';
        $roles = Session::get("User_Roles") ?? [];
        $jumlahRole = count($roles);

        $request->validate([
            'Kode_Role' => $jumlahRole > 1 ? 'required' : 'nullable',
        ], [
            'Kode_Role.required' => 'Penempatan/Role Tidak Boleh Kosong!',
        ]);

        try {
            $decoded = Hashids::connection('custom')->decode($request->Id_Jenis_Analisa);
            if (empty($decoded)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jenis Analisa tidak valid.'
                ], 400);
            }
            $Id_Jenis_Analisa = $decoded[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Jenis Analisa tidak valid.'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $kodeRoleValid = ($jumlahRole === 1) 
                ? (is_object($roles[0]) ? $roles[0]->Kode_Role : $roles[0]['Kode_Role']) 
                : $request->Kode_Role;

            if ($request->Mode === 'perhitungan') {
                $request->validate([
                    'Items_Perhitungan'                   => 'required|array',
                    'Items_Perhitungan.*.Id_Master_Mesin' => 'required',
                    'Items_Perhitungan.*.Id_Perhitungan'  => 'required',
                    'Items_Perhitungan.*.Range_Awal'      => 'required|numeric',
                    'Items_Perhitungan.*.Range_Akhir'     => 'required|numeric',
                    'Items_Perhitungan.*.Kode_Barang'     => 'required|array|min:1',
                ]);

                $rows = [];

                foreach ($request->Items_Perhitungan as $item) {
                    $decodeMesin = Hashids::connection('custom')->decode($item['Id_Master_Mesin']);
                    $decodeHitung = Hashids::connection('custom')->decode($item['Id_Perhitungan']);

                    if (empty($decodeMesin) || empty($decodeHitung)) {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'ID Mesin / Perhitungan tidak valid.'
                        ], 400);
                    }

                    $Id_Master_Mesin = $decodeMesin[0];
                    $Id_Perhitungan  = $decodeHitung[0];

                    foreach ($item['Kode_Barang'] as $kodeBarang) {
                        $rows[] = [
                            'Kode_Perusahaan'  => '001',
                            'Id_Jenis_Analisa' => $Id_Jenis_Analisa,
                            'Id_Master_Mesin'  => $Id_Master_Mesin,
                            'Id_Perhitungan'   => $Id_Perhitungan,
                            'Range_Awal'       => $item['Range_Awal'],
                            'Range_Akhir'      => $item['Range_Akhir'],
                            'Kode_Barang'      => $kodeBarang,
                            'Kode_Role'        => $kodeRoleValid,
                            'Id_User'          => $userId,
                            'Tanggal'          => $tanggalSqlServer,
                            'Jam'              => $jamSqlServer,
                        ];
                    }
                }

                $rows = collect($rows)
                    ->unique(function ($item) {
                        return implode('|', [
                            $item['Id_Jenis_Analisa'],
                            $item['Id_Master_Mesin'],
                            $item['Id_Perhitungan'],
                            $item['Range_Awal'],
                            $item['Range_Akhir'],
                            $item['Kode_Barang'],
                            $item['Kode_Role']
                        ]);
                    })
                    ->values()
                    ->toArray();

                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table('N_EMI_LAB_Standar_Rentang')
                        ->upsert(
                            $chunk,
                            [
                                'Id_Jenis_Analisa',
                                'Id_Master_Mesin',
                                'Id_Perhitungan',
                                'Range_Awal',
                                'Range_Akhir',
                                'Kode_Barang',
                                'Kode_Role'
                            ],
                            [] 
                        );
                }
            }
            elseif ($request->Mode === 'non') {
                $request->validate([
                    'Items_Non'                       => 'required|array',
                    'Items_Non.*.Nilai_Kriteria'      => 'required',
                    'Items_Non.*.Keterangan_Kriteria' => 'required',
                    'Items_Non.*.Flag_Layak'          => 'required',
                ]);

                $rows = [];

                foreach ($request->Items_Non as $item) {
                    $rows[] = [
                        'Kode_Perusahaan'     => '001',
                        'Id_Jenis_Analisa'    => $Id_Jenis_Analisa,
                        'Nilai_Kriteria'      => $item['Nilai_Kriteria'],
                        'Keterangan_Kriteria' => $item['Keterangan_Kriteria'],
                        'Flag_Layak'          => $item['Flag_Layak'],
                        'Flag_Aktif'          => 'Y',
                        'Kode_Role'           => $kodeRoleValid,
                        'Id_User'             => $userId,
                        'Tanggal'             => $tanggalSqlServer,
                        'Jam'                 => $jamSqlServer,
                    ];
                }

                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                        ->upsert(
                            $chunk,
                            [
                                'Id_Jenis_Analisa',
                                'Nilai_Kriteria',
                                'Keterangan_Kriteria',
                                'Kode_Role'
                            ],
                            [] 
                        );
                }
            }
            else {
                return response()->json([
                    'message' => 'Mode tidak dikenali'
                ], 400);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'status'  => 201,
                'message' => 'Data Berhasil Disimpan!'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('StandarRentangController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }
}
