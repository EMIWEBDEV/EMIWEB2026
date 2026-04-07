<?php

namespace App\Http\Controllers\Master;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Vinkla\Hashids\Facades\Hashids;

class DashboardController extends Controller
{
    public function dashboard_page()
    {
        // dd(Auth::user(), Session::get("User_Roles"));
        $namaPengguna = Auth::user()->Nama;
        $spesialUsers = ['HENDRY', 'RATNA', 'FRANS']; 
        $currentUserIdUpper = strtoupper(Auth::user()->UserId); 
        $aksesSpesial = in_array($currentUserIdUpper, $spesialUsers);

        return inertia('vue/dashboard/Dashboard', [
            'namaPengguna' => $namaPengguna,
            'aksesSpesial' => $aksesSpesial, 
        ]);
    }

    public function getNotifikasiCurrent(Request $request)
    {
        $kode = $request->query('kode');
        $limit = 5;
        $targetPage = 1;

        if($kode){
            DB::table('N_EMI_LAB_PO_Sampel')
                ->where('No_Sampel', $kode)
                ->whereNull('Flag_Baca')
                ->update(['Flag_Baca' => 'Y']);

            $subQuery = DB::table("N_EMI_LAB_PO_Sampel as ps")
            ->join("EMI_Master_Mesin as mm", "ps.Id_Mesin", "=", "mm.Id_Master_Mesin")
            ->select(
                'ps.No_Sampel',
                DB::raw('ROW_NUMBER() OVER(ORDER BY (CASE WHEN ps.Flag_Baca IS NULL THEN 0 ELSE 1 END), ps.Tanggal DESC, ps.Jam DESC) as RowNum')
            );
            $positionData = DB::query()->fromSub($subQuery, 'ranked_table')
            ->where('No_Sampel', $kode)
            ->first();

            if ($positionData) {
                $targetPage = ceil($positionData->RowNum / $limit);
            }
        }

        return inertia('vue/dashboard/notifikasi-detail/DetailPesanNotifikasi', [
            'initialKode' => $kode,
            'initialPage' => $targetPage
        ]);
    }

    public function Tentang()
    {
        return inertia('vue/dashboard/tentang/HomeTentang');
    }

    public function getKpiTurnAroundTime()
    {
        $currentMonth = date('m');
        $currentYear = date('Y');  

        $monthName = date('F', mktime(0, 0, 0, $currentMonth, 10)); 

        $avgMinutes = DB::table('N_EMI_LAB_Uji_Sampel as uji')
            ->join('N_EMI_LAB_PO_Sampel as po', 'uji.No_Po_Sampel', '=', 'po.No_Sampel')
            ->where('uji.Flag_Selesai', 'Y')
            ->whereNull('uji.Status')
            ->whereMonth('uji.Tanggal', $currentMonth)
            ->whereYear('uji.Tanggal', $currentYear)
            ->select(DB::raw("AVG(CAST(DATEDIFF(minute, po.Tanggal, uji.Tanggal) AS BIGINT)) as avg_tat"))
            ->value('avg_tat');

        $hours = floor($avgMinutes / 60);
        $minutes = $avgMinutes % 60;

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Data Ditemukan',
            'result' => [
                'hours' => (int)$hours,
                'minutes' => (int)$minutes,
                'period' => "Periode $monthName $currentYear"
            ],
        ], 200);
    }
    
    // public function getKpiTurnAroundTime()
    // {
    //     // Query ini menggunakan DATEDIFF dalam menit
    //     $avgMinutes = DB::table('N_EMI_LAB_Uji_Sampel as uji')
    //         ->join('N_EMI_LAB_PO_Sampel as po', 'uji.No_Po_Sampel', '=', 'po.No_Sampel')
    //         ->where('uji.Flag_Selesai', 'Y')
    //         ->whereNull('uji.Status')
    //         ->select(DB::raw("AVG(CAST(DATEDIFF(minute, po.Tanggal, uji.Tanggal) AS BIGINT)) as avg_tat"))
    //         ->value('avg_tat');

    //     $hours = floor($avgMinutes / 60);
    //     $minutes = $avgMinutes % 60;

    //     return response()->json([
    //         'success' => true,
    //         'status' => 200,
    //         'message' => 'Data Ditemukan',
    //         'result' => [
    //             'hours' => (int)$hours,
    //             'minutes' => (int)$minutes
    //         ],
    //     ], 200);
    // }

    public function getAktivitasTerbaru()
    {
        $activities = DB::table('N_EMI_LAB_Uji_Sampel as uji')
            ->join('N_EMI_LAB_Jenis_Analisa as ja', 'uji.Id_Jenis_Analisa', '=', 'ja.id')
            ->select(
                'uji.No_Faktur as id',
                'uji.No_Po_Sampel as no_sampel',
                'ja.Jenis_Analisa as jenis_analisa',
                'uji.Id_User as user',
                DB::raw("CONVERT(varchar, uji.Tanggal, 23) + ' ' + uji.Jam as tanggal")
            )
            ->where('uji.Flag_Selesai', 'Y')
            ->whereNull('uji.Status')
            ->orderBy('uji.Tanggal', 'desc')
            ->orderBy('uji.Jam', 'desc')
            ->take(5)
            ->get();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Data Ditemukan',
            'result' => $activities,
        ], 200);
    }

    /**
     * Method baru untuk data kinerja per analis (jumlah uji selesai).
     */
    public function getKinerjaAnalis()
    {
        $kinerja = DB::table('N_EMI_LAB_Uji_Sampel')
            ->select('Id_User', DB::raw('COUNT(*) as jumlah_uji'))
            ->where('Flag_Selesai', 'Y')
            ->whereNull('Status')
            ->whereRaw('UPPER(Id_User) != ?', ['RUDI', 'RATNA', 'HENDRY'])
            ->groupBy('Id_User')
            ->orderBy('jumlah_uji', 'desc')
            ->get();

        $series = [
            [
                'name' => 'Uji Selesai',
                'data' => $kinerja->pluck('jumlah_uji')->toArray(),
            ]
        ];

        $options = [
            'xaxis' => [
                'categories' => $kinerja->pluck('Id_User')->toArray(),
            ],
            'title' => [
                'text' => 'Jumlah Pengujian Selesai Per Analyzer',
                'align' => 'left',
            ],
        ];

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Data Ditemukan',
            'result' => [
                'series' => $series,
                'options' => $options
            ],
        ], 200);
    }
    
    public function getDataHariIniWidget()
    {
        try {

            // ============================
            // Gunakan Range Date (Index Friendly)
            // ============================
            $start = Carbon::today()->startOfDay();
            $end   = Carbon::today()->endOfDay();

            // ============================
            // 1️⃣ Registrasi Sampel Hari Ini
            // ============================
            $registrasiSampelHariIni = DB::table('N_EMI_LAB_PO_Sampel')
                ->whereNull('Status')
                ->whereBetween('Tanggal', [$start, $end])
                ->count();

            // ============================
            // 2️⃣ Aggregate Uji Sampel (1x scan saja)
            // ============================
            $uji = DB::table('N_EMI_LAB_Uji_Sampel')
                ->whereBetween('Tanggal', [$start, $end])
                ->selectRaw("
                    COUNT(DISTINCT CASE 
                        WHEN Status IS NULL 
                        THEN Id_Jenis_Analisa 
                    END) as total_uji,

                    COUNT(DISTINCT CASE 
                        WHEN Status IS NULL 
                        AND Flag_Selesai = 'Y' 
                        THEN Id_Jenis_Analisa 
                    END) as total_selesai,

                    COUNT(DISTINCT CASE 
                        WHEN Status IS NULL 
                        AND Flag_Selesai IS NULL 
                        THEN Id_Jenis_Analisa 
                    END) as total_belum_selesai,

                    COUNT(DISTINCT CASE 
                        WHEN Status IS NULL 
                        THEN Id_Jenis_Analisa 
                    END) as total_jenis_analisa,

                    COUNT(DISTINCT CASE 
                        WHEN Status = 'Y' 
                        THEN Id_Jenis_Analisa 
                    END) as total_dibatalkan
                ")
                ->first();

            // ============================
            // 3️⃣ Format Widget
            // ============================
            $widgets = [
                [
                    "title" => "Registrasi Sampel Hari Ini",
                    "subtitle" => "Sampel baru hari ini",
                    "value" => (int) $registrasiSampelHariIni,
                    "icon" => "fas fa-vials",
                    "color" => "#6366F1",
                ],
                [
                    "title" => "Uji Sampel Hari Ini",
                    "subtitle" => "Total pengujian masuk",
                    "value" => (int) ($uji->total_uji ?? 0),
                    "icon" => "fas fa-microscope",
                    "color" => "#10B981",
                ],
                [
                    "title" => "Uji Sampel Selesai Hari Ini",
                    "subtitle" => "Pengujian selesai",
                    "value" => (int) ($uji->total_selesai ?? 0),
                    "icon" => "fas fa-check-circle",
                    "color" => "#22C55E",
                ],
                [
                    "title" => "Uji Sampel Belum Selesai Hari Ini",
                    "subtitle" => "Pengujian dalam proses",
                    "value" => (int) ($uji->total_belum_selesai ?? 0),
                    "icon" => "fas fa-hourglass-half",
                    "color" => "#F59E0B",
                ],
                [
                    "title" => "Jenis Analisa Hari Ini",
                    "subtitle" => "Macam pengujian hari ini",
                    "value" => (int) ($uji->total_jenis_analisa ?? 0),
                    "icon" => "fas fa-layer-group",
                    "color" => "#3B82F6",
                ],
                [
                    "title" => "Uji Sampel Dibatalkan Hari Ini",
                    "subtitle" => "Pengujian dibatalkan",
                    "value" => (int) ($uji->total_dibatalkan ?? 0),
                    "icon" => "fas fa-times-circle",
                    "color" => "#DC2626",
                ],
            ];

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Data Ditemukan',
                'result' => $widgets,
            ], 200);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Terjadi Kesalahan Server',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function getTotalWidget()
    {
        try {

            // ===============================
            // 1️⃣ Registrasi Sampel
            // ===============================
            $registrasiSampel = DB::table('N_EMI_LAB_PO_Sampel')
                ->whereNull('Status')
                ->count();

            // ===============================
            // 2️⃣ Aggregate Uji Sampel (1x scan)
            // ===============================
            $ujiSampel = DB::table('N_EMI_LAB_Uji_Sampel')
                ->whereNull('Status')
                ->selectRaw("
                    COUNT(DISTINCT Id_Jenis_Analisa) as total_uji,
                    COUNT(DISTINCT CASE 
                        WHEN Flag_Selesai = 'Y' 
                        THEN Id_Jenis_Analisa 
                    END) as total_selesai,
                    COUNT(DISTINCT CASE 
                        WHEN Flag_Selesai IS NULL 
                        THEN Id_Jenis_Analisa 
                    END) as total_belum_selesai
                ")
                ->first();

            // ===============================
            // 3️⃣ Format Widget
            // ===============================
            $widgets = [
                [
                    "title" => "Total Registrasi Sampel",
                    "subtitle" => "Dari semua waktu",
                    "value" => (int) $registrasiSampel,
                    "icon" => "fas fa-vials",
                    "color" => "#6366F1",
                ],
                [
                    "title" => "Total Uji Sampel",
                    "subtitle" => "Total semua pengujian",
                    "value" => (int) ($ujiSampel->total_uji ?? 0),
                    "icon" => "fas fa-microscope",
                    "color" => "#0EA5E9",
                ],
                [
                    "title" => "Total Uji Sampel Selesai",
                    "subtitle" => "Total pengujian selesai",
                    "value" => (int) ($ujiSampel->total_selesai ?? 0),
                    "icon" => "fas fa-check-circle",
                    "color" => "#22C55E",
                ],
                [
                    "title" => "Total Uji Sampel Belum Selesai",
                    "subtitle" => "Total pengujian tertunda",
                    "value" => (int) ($ujiSampel->total_belum_selesai ?? 0),
                    "icon" => "fas fa-pause-circle",
                    "color" => "#EF4444",
                ],
            ];

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Data Ditemukan',
                'result' => $widgets,
            ], 200);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Terjadi Kesalahan Server',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function grafikAnalisaData()
    {
        $endDate = Carbon::today();
        $startDate = $endDate->copy()->subDays(5);

        $rawData = DB::table('N_EMI_LAB_Uji_Sampel')
            ->select(
                DB::raw("FORMAT(Tanggal, 'yyyy-MM-dd') as date"),
                DB::raw("COUNT(DISTINCT CONCAT(No_Po_Sampel, '-', Id_Jenis_Analisa)) as jumlah_uji")
            )
            ->whereNull('Status')
            ->whereBetween('Tanggal', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->groupBy(DB::raw("FORMAT(Tanggal, 'yyyy-MM-dd')"))
            ->orderBy(DB::raw("FORMAT(Tanggal, 'yyyy-MM-dd')"))
            ->get()
            ->keyBy('date');

        $period = CarbonPeriod::create($startDate, $endDate);

        $resultData = [];

        foreach ($period as $date) {
            $key = $date->format('Y-m-d');
            $formattedDate = $date->format('d M Y'); 

            $jumlahUji = $rawData[$key]->jumlah_uji ?? 0;

            $resultData[] = [
                'date' => $formattedDate,
                'jumlah_uji' => (int) $jumlahUji,
            ];
        }

        $chartLineSeries = [
            [
                'name' => 'Jumlah Uji',
                'data' => collect($resultData)->pluck('jumlah_uji'),
            ]
        ];

        $chartLineOptions = [
            'chart' => [
                'type' => 'line',
                'zoom' => ['enabled' => false],
            ],
            'xaxis' => [
                'categories' => collect($resultData)->pluck('date'),
            ],
            'title' => [
                'text' => 'Jumlah Uji Sampel per Hari',
                'align' => 'left',
            ],
        ];

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Data ditemukan',
            'result' => [
                'chartLineSeries' => $chartLineSeries,
                'chartLineOptions' => $chartLineOptions,
            ],
        ], 200);
    }

    public function getFrekuensiUjiSampelBerdasarkanJenisAnalisa()
    {
        $uniqueUji = DB::table('N_EMI_LAB_Uji_Sampel as us')
            ->select('us.No_Po_Sampel', 'us.Id_Jenis_Analisa', 'ja.Jenis_Analisa')
            ->join('N_EMI_LAB_Jenis_Analisa as ja', 'us.Id_Jenis_Analisa', '=', 'ja.id')
            ->whereNull('us.Status')
            ->distinct()
            ->get();

        $grouped = $uniqueUji->groupBy('Jenis_Analisa')->map->count();

        $categories = $grouped->keys()->values();
        $dataValues = $grouped->values();

        $chartBarSeries = [
            [
                'name' => 'Jumlah Uji',
                'data' => $dataValues,
            ],
        ];

        $chartBarOptions = [
            'chart' => ['type' => 'radar'],
            'dataLabels' => ['enabled' => false],
            'title' => [
                'text' => 'Frekuensi Uji Sampel Berdasarkan Jenis Analisa',
                'align' => 'left',
            ],
            'labels' => $categories, 
        ];
        

        return response()->json([
            'status' => 200,
            'message' => 'Data Ditemukan',
            'result' => [
                'chartBarSeries' => $chartBarSeries,
                'chartBarOptions' => $chartBarOptions,
            ],
        ]);
    }

    public function getPieStatusPenyelesaianUji()
    {
        $ujiSampel = DB::table('N_EMI_LAB_Uji_Sampel')
            ->select('No_Po_Sampel', 'Id_Jenis_Analisa', 'Flag_Selesai')
            ->whereNull('Status')
            ->get();

        $uniqueData = $ujiSampel
            ->map(fn($item) => [
                'No_Po_Sampel' => $item->No_Po_Sampel,
                'Id_Jenis_Analisa' => $item->Id_Jenis_Analisa,
                'Flag_Selesai' => $item->Flag_Selesai,
            ])
            ->unique(fn($item) => $item['No_Po_Sampel'] . '|' . $item['Id_Jenis_Analisa'])
            ->values();

        $total = $uniqueData->count();
        $selesai = $uniqueData->where('Flag_Selesai', 'Y')->count();
        $belumSelesai = $total - $selesai;

        return response()->json([
            'status' => 200,
            'message' => 'Data Ditemukan',
            'result' => [
                'chartPieSeries' => [$selesai, $belumSelesai],
                'chartPieOptions' => [
                    'labels' => ['Selesai', 'Belum Selesai'],
                    'colors' => ['#22C55E', '#FACC15'], 
                    'title' => [
                        'text' => 'Status Penyelesaian Uji Sampel',
                        'align' => 'left',
                    ],
                    'legend' => [
                        'position' => 'bottom',
                    ],
                ],
            ],
        ]);
    }

    public function getScatterSebaranHasilAnalisa()
    {
        $ujiSampel = DB::table('N_EMI_LAB_Uji_Sampel as us')
            ->select(
                'us.No_Po_Sampel',
                'us.No_Fak_Sub_Po',
                'us.Id_Jenis_Analisa',
                'us.Hasil',
                'ja.Jenis_Analisa'
            )
            ->join('N_EMI_LAB_Jenis_Analisa as ja', 'us.Id_Jenis_Analisa', '=', 'ja.id')
            ->whereNull('us.Status')
            ->whereNotNull('us.Hasil')
            ->get();

        $uniqueData = $ujiSampel
            ->map(fn($item) => [
                'No_Po_Sampel'   => $item->No_Po_Sampel,
                'No_Fak_Sub_Po'  => $item->No_Fak_Sub_Po ?? '-', // default jika null
                'Id_Jenis_Analisa' => $item->Id_Jenis_Analisa,
                'Jenis_Analisa'  => $item->Jenis_Analisa,
                'Hasil'          => $item->Hasil,
            ])
            ->unique(fn($item) => $item['No_Po_Sampel'] . '|' . $item['Id_Jenis_Analisa'])
            ->values();

        $grouped = $uniqueData->groupBy('Id_Jenis_Analisa');

        $series = [];
        $index = 1;

        foreach ($grouped as $items) {
            if ($items->isEmpty()) continue;

            $first = $items->first();
            $jenisAnalisaName = $first['Jenis_Analisa'];

            $dataPoints = [];
            foreach ($items as $item) {
                $dataPoints[] = [
                    'x'              => $index++,
                    'y'              => (float) $item['Hasil'],
                    'no_po_sampel'   => $item['No_Po_Sampel'],
                    'no_fak_sub_po'  => $item['No_Fak_Sub_Po'] ?? '-', // default jika null
                ];
            }

            $series[] = [
                'name' => $jenisAnalisaName,
                'data' => $dataPoints,
            ];
        }

        return response()->json([
            'status'  => 200,
            'message' => 'Data Ditemukan',
            'result'  => [
                'chartScatterSeries' => $series,
            ],
        ]);
    }
    
    public function getListNotifikasi(Request $request)
    {
        // 1. Setup Parameter
        $limit = (int) $request->query('limit', 5);
        $page = (int) $request->query('page', 1);
        $filter = $request->query('filter');
        $offset = ($page - 1) * $limit;

        // 2. Base Query (Query Dasar)
        $query = DB::table("N_EMI_LAB_PO_Sampel as ps")
            ->join("EMI_Master_Mesin as mm", "ps.Id_Mesin", "=", "mm.Id_Master_Mesin")
            ->select(
                'ps.Kode_Barang', 'ps.No_Split_Po', 'ps.No_Sampel', 'ps.No_Batch',
                'ps.Id_Jenis_Analisa_Khusus', 'ps.Id_Mesin', 'ps.Tanggal', 'ps.Jam',
                'ps.Berat_Sampel', 'ps.Id_User', 'ps.Flag_Baca', 'ps.Keterangan', 'ps.Jumlah_Pcs',
                'mm.Nama_Mesin', 'mm.Flag_Multi_QrCode'
            );

        // 3. Terapkan Filter (Jika user minta 'unread', tambahkan where)
        if ($filter === 'unread') {
            $query->whereNull('ps.Flag_Baca');
            // Urutkan unread biasanya berdasarkan tanggal terbaru
            $query->orderByDesc('ps.Tanggal')->orderByDesc('ps.Jam');
        } else {
            // Default: Unread dulu, baru tanggal
            $query->orderByRaw("CASE WHEN ps.Flag_Baca IS NULL THEN 0 ELSE 1 END")
                ->orderByDesc('ps.Tanggal')
                ->orderByDesc('ps.Jam');
        }

        // 4. Hitung Total (Efisien: Count dulu sebelum ambil data detail)
        $totalData = $query->count();
        $totalPage = ceil($totalData / $limit);

        // 5. Ambil Data Halaman Ini (PAGINATION SELALU AKTIF)
        $sampels = $query->offset($offset)->limit($limit)->get();

        // Jika kosong, langsung return (Hemat resource)
        if ($sampels->isEmpty()) {
            return response()->json([
                'status' => 200, 
                'message' => 'Kosong', 
                'result' => [], 
                'page' => $page, 
                'total_page' => 0,
                'total_data' => 0
            ]);
        }

        // --- MULAI MAPPING DATA (Hanya untuk 5 data yang tampil, jadi RINGAN) ---
        
        // 6. Persiapan Peta Data (Lookup Maps)
        $kodeBarangs = $sampels->pluck('Kode_Barang')->unique()->toArray();
        $mesinIds = $sampels->pluck('Id_Mesin')->unique()->toArray();
        $noSampels = $sampels->pluck('No_Sampel')->unique()->toArray();
        $idJenisKhusus = $sampels->pluck('Id_Jenis_Analisa_Khusus')->filter()->unique()->toArray();

        // Peta Nama Barang
        $barangMap = DB::table('N_EMI_View_Barang')
            ->whereIn('Kode_Barang', $kodeBarangs)
            ->pluck('Nama', 'Kode_Barang');

        // Peta Analisa Barang
        $analisaBarang = DB::table('N_EMI_LAB_Barang_Analisa as ba')
            ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ba.Id_Jenis_Analisa', '=', 'ja.id')
            ->whereIn('ba.Kode_Barang', $kodeBarangs)
            ->whereIn('ba.Id_Master_Mesin', $mesinIds)
            ->select('ba.Kode_Barang', 'ba.Id_Master_Mesin', 'ja.Jenis_Analisa')
            ->get()
            ->groupBy(fn($row) => $row->Kode_Barang . '-' . $row->Id_Master_Mesin);

        // Peta Analisa Berkala
        $analisaBerkala = collect();
        if (!empty($idJenisKhusus)) {
            $analisaBerkala = DB::table('N_EMI_LAB_Jenis_Analisa_Berkala as jab')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'jab.Id_Sub_Jenis_Analisa', '=', 'ja.id')
                ->whereIn('jab.Id_Jenis_Analisa', $idJenisKhusus)
                ->select('jab.Id_Jenis_Analisa', 'ja.Jenis_Analisa')
                ->get()
                ->groupBy('Id_Jenis_Analisa');
        }

        // Peta Multi QR
        $qrCodes = DB::table('N_EMI_LAB_PO_Sampel_Multi_QrCode')
            ->whereIn('No_Po_Sampel', $noSampels)
            ->whereIn('Kode_Barang', $kodeBarangs)
            ->get()
            ->groupBy(fn($row) => $row->No_Po_Sampel . '-' . $row->Kode_Barang);

        // 7. Gabungkan Data
        $result = $sampels->map(function ($item) use ($barangMap, $analisaBarang, $analisaBerkala, $qrCodes) {
            
            // --- Logic Analisa ---
            $keyAnalisa = $item->Kode_Barang . '-' . $item->Id_Mesin;
            $analisa = $analisaBarang->get($keyAnalisa)?->pluck('Jenis_Analisa')->toArray() ?? [];

            if (!is_null($item->Id_Jenis_Analisa_Khusus)) {
                $berkalaItems = $analisaBerkala->get($item->Id_Jenis_Analisa_Khusus, collect());
                // Ambil nama analisa utama (sebaiknya di-cache/eager load jika banyak, tapi untuk 5 item query DB direct ok)
                $utama = DB::table('N_EMI_LAB_Jenis_Analisa')->where('id', $item->Id_Jenis_Analisa_Khusus)->value('Jenis_Analisa');
                
                foreach ($berkalaItems as $berkalaItem) {
                    $analisa[] = "{$utama} ~ {$berkalaItem->Jenis_Analisa}";
                }
            }

            // --- Logic Multi QR ---
            $qrKey = $item->No_Sampel . '-' . $item->Kode_Barang;
            $multiQr = $qrCodes->get($qrKey, collect())->map(function ($qr) use ($item, $barangMap, $analisa) {
                return [
                    'No_Sampel' => $qr->No_Po_Multi,
                    'No_Split_Po' => $item->No_Split_Po,
                    'No_Batch' => $item->No_Batch,
                    'Tanggal' => Carbon::parse($qr->Tanggal)->format('d M Y'),
                    'Nama_Barang' => $barangMap->get($item->Kode_Barang, 'Tidak Ditemukan'),
                    'Nama_Mesin' => $item->Nama_Mesin,
                    'Analisa' => $analisa,
                ];
            });

            return [
                'No_Sampel' => $item->No_Sampel,
                'Id_User' => $item->Id_User,
                'Kode_Barang' => $item->Kode_Barang,
                'No_Split_Po' => $item->No_Split_Po,
                'No_Batch' => $item->No_Batch,
                'Berat_Sampel' => (float) $item->Berat_Sampel,
                'Jumlah_Pcs' => $item->Jumlah_Pcs,
                'Tanggal' => Carbon::parse($item->Tanggal)->format('d M Y'),
                'Jam' => $item->Jam,
                'Nama_Barang' => $barangMap->get($item->Kode_Barang, 'Tidak Ditemukan'),
                'Nama_Mesin' => $item->Nama_Mesin,
                'Flag_Multi_QrCode' => $item->Flag_Multi_QrCode,
                'Keterangan' => $item->Keterangan,
                'Analisa' => array_values(array_unique($analisa)),
                'Flag_Baca' => $item->Flag_Baca,
                'multi_qrcode' => $multiQr,
            ];
        });

        return response()->json([
            'status' => 200,
            'message' => 'Data Ditemukan',
            'result' => $result,
            'page' => $page,
            'total_page' => $totalPage,
            'total_data' => $totalData
        ]);
    }

    public function getCountNoRead()
    {
        $countNotifikasi = DB::tabLe("N_EMI_LAB_PO_Sampel")->whereNull('Flag_Baca')->count() ?? 0;
        return response()->json([
            'status' => 200,
            'message' => 'Data Ditemukan',
            'result' => $countNotifikasi,
        ]);
    }

    public function updateFlagBacaRead()
    {
        try {
            // Cek apakah masih ada notifikasi yang belum dibaca
            $jumlahBelumDibaca = DB::table('N_EMI_LAB_PO_Sampel')
                ->whereNull('Flag_Baca')
                ->count();

            // Jika semua sudah dibaca
            if ($jumlahBelumDibaca === 0) {
                return response()->json([
                    'status' => 200,
                    'message' => 'Semua notifikasi sudah dibaca. Tidak ada yang diperbarui.',
                ]);
            }

            // Jika masih ada yang belum dibaca, lakukan update
            DB::beginTransaction();

            DB::table('N_EMI_LAB_PO_Sampel')
                ->whereNull('Flag_Baca')
                ->update(['Flag_Baca' => 'Y']);

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Status baca berhasil diperbarui.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 500,
                'message' => 'Terjadi kesalahan saat memperbarui status baca',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
