<?php

namespace App\Http\Controllers\Master;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class DashboardController extends Controller
{
    public function dashboard_page()
    {

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
            $jumlahBelumDibaca = DB::table('N_EMI_LAB_PO_Sampel')
                ->whereNull('Flag_Baca')
                ->count();

            if ($jumlahBelumDibaca === 0) {
                return response()->json([
                    'status' => 200,
                    'message' => 'Semua notifikasi sudah dibaca. Tidak ada yang diperbarui.',
                ]);
            }

            DB::beginTransaction();
            DB::table('N_EMI_LAB_PO_Sampel')->whereNull('Flag_Baca')->update(['Flag_Baca' => 'Y']);
            DB::commit();

            return response()->json(['status' => 200, 'message' => 'Status baca berhasil diperbarui.']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 500,
                'message' => 'Terjadi kesalahan saat memperbarui status baca',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ============================================================
    // QA DASHBOARD
    // ============================================================

    public function getDashboardQaPage()
    {
        return inertia('vue/dashboard/dashboard-qa/DashboardQA');
    }

    public function getKpiQaHariIni()
    {
        $start = Carbon::today()->startOfDay();
        $end   = Carbon::today()->endOfDay();

        $data = DB::table('N_EMI_LAB_PO_Sampel')
            ->whereNull('Status')
            ->whereBetween('Tanggal', [$start, $end])
            ->selectRaw("
                COUNT(*) as total_sampel,
                COUNT(CASE WHEN Flag_Selesai = 'Y' THEN 1 END) as total_selesai,
                COUNT(CASE WHEN Flag_Selesai IS NULL THEN 1 END) as total_pending,
                COUNT(CASE WHEN Flag_Trial_Produksi = 'Y' THEN 1 END) as total_trial,
                COUNT(CASE WHEN Flag_Khusus = 'Y' THEN 1 END) as total_khusus,
                COUNT(CASE WHEN Flag_Close_Po = 'Y' THEN 1 END) as total_close_po
            ")
            ->first();

        return response()->json([
            'status' => 200,
            'result' => [
                ['title' => 'Total Sampel Hari Ini', 'value' => (int)($data->total_sampel  ?? 0), 'icon' => 'ri-test-tube-line',       'color' => '#405189', 'bg' => 'rgba(64,81,137,0.12)',   'sub' => 'Terdaftar hari ini'],
                ['title' => 'Sampel Selesai',        'value' => (int)($data->total_selesai  ?? 0), 'icon' => 'ri-checkbox-circle-line', 'color' => '#0ab39c', 'bg' => 'rgba(10,179,156,0.12)',  'sub' => 'Pengujian selesai'],
                ['title' => 'Sampel Pending',        'value' => (int)($data->total_pending  ?? 0), 'icon' => 'ri-time-line',            'color' => '#f7b84b', 'bg' => 'rgba(247,184,75,0.12)',  'sub' => 'Menunggu pengujian'],
                ['title' => 'Trial Produksi',        'value' => (int)($data->total_trial    ?? 0), 'icon' => 'ri-flask-line',           'color' => '#4b93f7', 'bg' => 'rgba(75,147,247,0.12)',  'sub' => 'Sampel trial produksi'],
                ['title' => 'Sampel Khusus',         'value' => (int)($data->total_khusus   ?? 0), 'icon' => 'ri-star-line',            'color' => '#6f42c1', 'bg' => 'rgba(111,66,193,0.12)',  'sub' => 'Analisa khusus/berkala'],
                ['title' => 'PO Closed',             'value' => (int)($data->total_close_po ?? 0), 'icon' => 'ri-lock-line',            'color' => '#f06548', 'bg' => 'rgba(240,101,72,0.12)',  'sub' => 'PO telah ditutup'],
            ],
        ]);
    }

    public function getTrenSampelQa(Request $request)
    {
        $days      = max(7, min(30, (int) $request->query('days', 7)));
        $endDate   = Carbon::today();
        $startDate = $endDate->copy()->subDays($days - 1);

        $rawData = DB::table('N_EMI_LAB_PO_Sampel')
            ->whereNull('Status')
            ->whereBetween('Tanggal', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->select(
                DB::raw("FORMAT(Tanggal, 'yyyy-MM-dd') as date"),
                DB::raw('COUNT(*) as total'),
                DB::raw("COUNT(CASE WHEN Flag_Selesai = 'Y' THEN 1 END) as selesai"),
                DB::raw("COUNT(CASE WHEN Flag_Trial_Produksi = 'Y' THEN 1 END) as trial")
            )
            ->groupBy(DB::raw("FORMAT(Tanggal, 'yyyy-MM-dd')"))
            ->get()
            ->keyBy('date');

        $period = CarbonPeriod::create($startDate, $endDate);
        $categories = $totalArr = $selesaiArr = $trialArr = [];

        foreach ($period as $date) {
            $key          = $date->format('Y-m-d');
            $categories[] = $date->format('d M');
            $row          = $rawData->get($key);
            $totalArr[]   = (int)($row->total   ?? 0);
            $selesaiArr[] = (int)($row->selesai ?? 0);
            $trialArr[]   = (int)($row->trial   ?? 0);
        }

        return response()->json([
            'status' => 200,
            'result' => [
                'categories' => $categories,
                'series' => [
                    ['name' => 'Total Sampel',  'data' => $totalArr],
                    ['name' => 'Selesai',        'data' => $selesaiArr],
                    ['name' => 'Trial Produksi', 'data' => $trialArr],
                ],
            ],
        ]);
    }

    public function getStatusRingkasanQa()
    {
        $data = DB::table('N_EMI_LAB_PO_Sampel')
            ->whereNull('Status')
            ->selectRaw("
                COUNT(CASE WHEN Flag_Selesai = 'Y' THEN 1 END) as selesai,
                COUNT(CASE WHEN Flag_Selesai IS NULL AND (Flag_Trial_Produksi IS NULL OR Flag_Trial_Produksi != 'Y') THEN 1 END) as pending,
                COUNT(CASE WHEN Flag_Trial_Produksi = 'Y' THEN 1 END) as trial
            ")
            ->first();

        return response()->json([
            'status' => 200,
            'result' => [
                'selesai' => (int)($data->selesai ?? 0),
                'pending' => (int)($data->pending ?? 0),
                'trial'   => (int)($data->trial   ?? 0),
            ],
        ]);
    }

    public function getDistribusiSampelPerMesinQa()
    {
        $data = DB::table('N_EMI_LAB_PO_Sampel as ps')
            ->join('EMI_Master_Mesin as mm', 'ps.Id_Mesin', '=', 'mm.Id_Master_Mesin')
            ->whereNull('ps.Status')
            ->select('mm.Nama_Mesin', DB::raw('COUNT(*) as total'))
            ->groupBy('mm.Nama_Mesin')
            ->orderByDesc('total')
            ->get();

        return response()->json([
            'status' => 200,
            'result' => [
                'categories' => $data->pluck('Nama_Mesin')->toArray(),
                'data'       => $data->pluck('total')->map(fn($v) => (int)$v)->values()->toArray(),
            ],
        ]);
    }

    public function getDistribusiSampelPerUserQa()
    {
        $data = DB::table('N_EMI_LAB_PO_Sampel')
            ->whereNull('Status')
            ->select('Id_User', DB::raw('COUNT(*) as total'))
            ->groupBy('Id_User')
            ->orderByDesc('total')
            ->take(10)
            ->get();

        return response()->json([
            'status' => 200,
            'result' => [
                'categories' => $data->pluck('Id_User')->toArray(),
                'data'       => $data->pluck('total')->map(fn($v) => (int)$v)->values()->toArray(),
            ],
        ]);
    }

    public function getAktivitasTerbaruQa()
    {
        $data = DB::table('N_EMI_LAB_PO_Sampel as ps')
            ->join('EMI_Master_Mesin as mm', 'ps.Id_Mesin', '=', 'mm.Id_Master_Mesin')
            ->whereNull('ps.Status')
            ->select(
                'ps.No_Sampel', 'ps.Kode_Barang', 'ps.No_Po', 'ps.No_Split_Po', 'ps.No_Batch',
                'ps.Id_User', 'ps.Tanggal', 'ps.Jam', 'ps.Flag_Selesai',
                'ps.Flag_Trial_Produksi', 'ps.Flag_Khusus', 'ps.Berat_Sampel',
                'mm.Nama_Mesin'
            )
            ->orderByDesc('ps.Tanggal')
            ->orderByDesc('ps.Jam')
            ->take(10)
            ->get()
            ->map(fn($item) => array_merge((array)$item, [
                'Tanggal' => Carbon::parse($item->Tanggal)->format('d M Y'),
            ]));

        return response()->json(['status' => 200, 'result' => $data]);
    }

    // ============================================================
    // PRODUKSI TAB (main dashboard)
    // ============================================================

    public function getKpiProduksi()
    {
        $po = DB::table('N_EMI_LAB_PO_Sampel')
            ->whereNull('Status')
            ->selectRaw("
                COUNT(DISTINCT No_Po) as total_po,
                COUNT(DISTINCT No_Split_Po) as total_split_po,
                COUNT(*) as total_sampel,
                COUNT(CASE WHEN Flag_Selesai = 'Y' THEN 1 END) as total_selesai,
                COUNT(CASE WHEN Flag_Trial_Produksi = 'Y' THEN 1 END) as total_trial,
                COUNT(CASE WHEN Flag_Close_Po = 'Y' THEN 1 END) as total_close_po
            ")
            ->first();

        $validasi = DB::table('N_EMI_LAB_Hasil_Uji_Validasi_Final')
            ->selectRaw("
                COUNT(*) as total_validasi,
                COUNT(CASE WHEN Flag_Ok = 'Y' THEN 1 END) as total_ok,
                COUNT(CASE WHEN Flag_FG = 'Y' THEN 1 END) as total_fg
            ")
            ->first();

        return response()->json([
            'status' => 200,
            'result' => [
                'total_po'       => (int)($po->total_po       ?? 0),
                'total_split_po' => (int)($po->total_split_po ?? 0),
                'total_sampel'   => (int)($po->total_sampel   ?? 0),
                'total_selesai'  => (int)($po->total_selesai  ?? 0),
                'total_trial'    => (int)($po->total_trial    ?? 0),
                'total_close_po' => (int)($po->total_close_po ?? 0),
                'total_validasi' => (int)($validasi->total_validasi ?? 0),
                'total_ok'       => (int)($validasi->total_ok       ?? 0),
                'total_fg'       => (int)($validasi->total_fg       ?? 0),
            ],
        ]);
    }

    public function getTrenProduksi()
    {
        $endDate   = Carbon::today();
        $startDate = $endDate->copy()->subDays(13);

        $rawData = DB::table('N_EMI_LAB_PO_Sampel')
            ->whereBetween('Tanggal', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->select(
                DB::raw("FORMAT(Tanggal, 'yyyy-MM-dd') as date"),
                DB::raw('COUNT(*) as total_sampel'),
                DB::raw("COUNT(CASE WHEN Flag_Trial_Produksi = 'Y' THEN 1 END) as trial_sampel")
            )
            ->groupBy(DB::raw("FORMAT(Tanggal, 'yyyy-MM-dd')"))
            ->get()
            ->keyBy('date');

        $period = CarbonPeriod::create($startDate, $endDate);
        $categories = $totalArr = $trialArr = [];

        foreach ($period as $date) {
            $key          = $date->format('Y-m-d');
            $categories[] = $date->format('d M');
            $row          = $rawData->get($key);
            $totalArr[]   = (int)($row->total_sampel ?? 0);
            $trialArr[]   = (int)($row->trial_sampel ?? 0);
        }

        return response()->json([
            'status' => 200,
            'result' => [
                'categories' => $categories,
                'series' => [
                    ['name' => 'Total Sampel',  'data' => $totalArr],
                    ['name' => 'Trial Produksi', 'data' => $trialArr],
                ],
            ],
        ]);
    }

    public function getValidasiFinalTerbaru()
    {
        $data = DB::table('N_EMI_LAB_Hasil_Uji_Validasi_Final')
            ->orderByDesc('Tanggal')
            ->orderByDesc('Jam')
            ->take(10)
            ->get()
            ->map(fn($item) => array_merge((array)$item, [
                'Tanggal' => Carbon::parse($item->Tanggal)->format('d M Y'),
            ]));

        return response()->json(['status' => 200, 'result' => $data]);
    }

    // ============================================================
    // ATASAN TAB (main dashboard)
    // ============================================================

    public function getSummaryAtasan()
    {
        $bulanIni = Carbon::now()->month;
        $tahunIni = Carbon::now()->year;
        $monthNames = ['','Januari','Februari','Maret','April','Mei','Juni',
            'Juli','Agustus','September','Oktober','November','Desember'];
        $namaBulan = $monthNames[$bulanIni] . ' ' . $tahunIni;

        $sampelBulanIni = DB::table('N_EMI_LAB_PO_Sampel')
            ->whereNull('Status')
            ->whereMonth('Tanggal', $bulanIni)
            ->whereYear('Tanggal', $tahunIni)
            ->count();

        $ujiSelesaiBulanIni = DB::table('N_EMI_LAB_Uji_Sampel')
            ->whereNull('Status')
            ->where('Flag_Selesai', 'Y')
            ->whereMonth('Tanggal', $bulanIni)
            ->whereYear('Tanggal', $tahunIni)
            ->count();

        $passRateData = DB::table('N_EMI_LAB_Uji_Sampel')
            ->whereNull('Status')
            ->where('Flag_Selesai', 'Y')
            ->selectRaw("COUNT(*) as total, COUNT(CASE WHEN Flag_Layak = 'Y' THEN 1 END) as layak")
            ->first();

        $passRatePct = ($passRateData->total ?? 0) > 0
            ? round(($passRateData->layak / $passRateData->total) * 100, 1)
            : 0;

        $tatMinutes = DB::table('N_EMI_LAB_Uji_Sampel as uji')
            ->join('N_EMI_LAB_PO_Sampel as po', 'uji.No_Po_Sampel', '=', 'po.No_Sampel')
            ->where('uji.Flag_Selesai', 'Y')
            ->whereNull('uji.Status')
            ->whereMonth('uji.Tanggal', $bulanIni)
            ->whereYear('uji.Tanggal', $tahunIni)
            ->select(DB::raw("AVG(CAST(DATEDIFF(minute, po.Tanggal, uji.Tanggal) AS BIGINT)) as avg_tat"))
            ->value('avg_tat');

        $validasiBulanIni = DB::table('N_EMI_LAB_Hasil_Uji_Validasi_Final')
            ->whereMonth('Tanggal', $bulanIni)
            ->whereYear('Tanggal', $tahunIni)
            ->count();

        return response()->json([
            'status' => 200,
            'result' => [
                'periode'               => $namaBulan,
                'sampel_bulan_ini'      => $sampelBulanIni,
                'uji_selesai_bulan_ini' => $ujiSelesaiBulanIni,
                'pass_rate_pct'         => $passRatePct,
                'tat_hours'             => (int)floor(($tatMinutes ?? 0) / 60),
                'tat_minutes'           => (int)(($tatMinutes ?? 0) % 60),
                'validasi_bulan_ini'    => $validasiBulanIni,
            ],
        ]);
    }

    public function getPassRatePerJenisAnalisa()
    {
        $data = DB::table('N_EMI_LAB_Uji_Sampel as us')
            ->join('N_EMI_LAB_Jenis_Analisa as ja', 'us.Id_Jenis_Analisa', '=', 'ja.id')
            ->whereNull('us.Status')
            ->where('us.Flag_Selesai', 'Y')
            ->select(
                'ja.Jenis_Analisa',
                DB::raw('COUNT(*) as total'),
                DB::raw("COUNT(CASE WHEN us.Flag_Layak = 'Y' THEN 1 END) as layak")
            )
            ->groupBy('ja.Jenis_Analisa', 'ja.id')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->get();

        return response()->json([
            'status' => 200,
            'result' => [
                'categories' => $data->pluck('Jenis_Analisa')->toArray(),
                'data'       => $data->map(fn($d) => $d->total > 0
                    ? round(($d->layak / $d->total) * 100, 1) : 0)->values()->toArray(),
            ],
        ]);
    }

    // ============================================================
    // DASHBOARD ATASAN (dedicated page)
    // ============================================================

    public function getDashboardAtasanPage()
    {
        $namaPengguna = Auth::user()->Nama ?? Auth::user()->UserId;
        return inertia('vue/dashboard/dashboard-atasan/DashboardAtasan', [
            'namaPengguna' => $namaPengguna,
        ]);
    }

    public function getKpiAtasanBulanan()
    {
        $bulanIni = Carbon::now()->month;
        $tahunIni = Carbon::now()->year;
        $monthNames = ['','Januari','Februari','Maret','April','Mei','Juni',
            'Juli','Agustus','September','Oktober','November','Desember'];
        $namaBulan = $monthNames[$bulanIni] . ' ' . $tahunIni;

        [$sampel, $uji, $tatRow, $validasi] = [
            DB::table('N_EMI_LAB_PO_Sampel')
                ->whereNull('Status')
                ->selectRaw("
                    COUNT(*) as total,
                    COUNT(CASE WHEN Flag_Selesai = 'Y' THEN 1 END) as selesai,
                    COUNT(CASE WHEN Flag_Trial_Produksi = 'Y' THEN 1 END) as trial
                ")
                ->whereMonth('Tanggal', $bulanIni)->whereYear('Tanggal', $tahunIni)
                ->first(),

            DB::table('N_EMI_LAB_Uji_Sampel')
                ->whereNull('Status')->where('Flag_Selesai', 'Y')
                ->selectRaw("COUNT(*) as total, COUNT(CASE WHEN Flag_Layak = 'Y' THEN 1 END) as layak")
                ->whereMonth('Tanggal', $bulanIni)->whereYear('Tanggal', $tahunIni)
                ->first(),

            DB::table('N_EMI_LAB_Uji_Sampel as uji')
                ->join('N_EMI_LAB_PO_Sampel as po', 'uji.No_Po_Sampel', '=', 'po.No_Sampel')
                ->where('uji.Flag_Selesai', 'Y')->whereNull('uji.Status')
                ->whereMonth('uji.Tanggal', $bulanIni)->whereYear('uji.Tanggal', $tahunIni)
                ->selectRaw("AVG(CAST(DATEDIFF(minute, po.Tanggal, uji.Tanggal) AS BIGINT)) as avg_tat")
                ->first(),

            DB::table('N_EMI_LAB_Hasil_Uji_Validasi_Final')
                ->selectRaw("COUNT(*) as total, COUNT(CASE WHEN Flag_Ok='Y' THEN 1 END) as ok, COUNT(CASE WHEN Flag_FG='Y' THEN 1 END) as fg")
                ->whereMonth('Tanggal', $bulanIni)->whereYear('Tanggal', $tahunIni)
                ->first(),
        ];

        $passRate = ($uji->total ?? 0) > 0 ? round(($uji->layak / $uji->total) * 100, 1) : 0;
        $tatMin   = (int)($tatRow->avg_tat ?? 0);

        return response()->json([
            'status' => 200,
            'result' => [
                'periode'          => $namaBulan,
                'sampel_bulan_ini' => (int)($sampel->total   ?? 0),
                'sampel_selesai'   => (int)($sampel->selesai ?? 0),
                'sampel_trial'     => (int)($sampel->trial   ?? 0),
                'uji_selesai'      => (int)($uji->total      ?? 0),
                'pass_rate_pct'    => $passRate,
                'tat_hours'        => (int)floor($tatMin / 60),
                'tat_minutes'      => (int)($tatMin % 60),
                'validasi_bulan'   => (int)($validasi->total ?? 0),
                'validasi_ok'      => (int)($validasi->ok    ?? 0),
                'validasi_fg'      => (int)($validasi->fg    ?? 0),
            ],
        ]);
    }

    public function getTrenBulananAtasan()
    {
        $monthNames = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        $tahun = Carbon::now()->year;
        $categories = $sampelArr = $ujiArr = $validasiArr = [];

        $sampelRaw = DB::table('N_EMI_LAB_PO_Sampel')
            ->whereNull('Status')->whereYear('Tanggal', $tahun)
            ->selectRaw("MONTH(Tanggal) as m, COUNT(*) as total")
            ->groupBy(DB::raw('MONTH(Tanggal)'))->get()->keyBy('m');

        $ujiRaw = DB::table('N_EMI_LAB_Uji_Sampel')
            ->whereNull('Status')->where('Flag_Selesai', 'Y')->whereYear('Tanggal', $tahun)
            ->selectRaw("MONTH(Tanggal) as m, COUNT(*) as total")
            ->groupBy(DB::raw('MONTH(Tanggal)'))->get()->keyBy('m');

        $validasiRaw = DB::table('N_EMI_LAB_Hasil_Uji_Validasi_Final')
            ->whereYear('Tanggal', $tahun)
            ->selectRaw("MONTH(Tanggal) as m, COUNT(*) as total")
            ->groupBy(DB::raw('MONTH(Tanggal)'))->get()->keyBy('m');

        $bulanSekarang = Carbon::now()->month;
        for ($m = 1; $m <= $bulanSekarang; $m++) {
            $categories[]  = $monthNames[$m];
            $sampelArr[]   = (int)($sampelRaw->get($m)->total   ?? 0);
            $ujiArr[]      = (int)($ujiRaw->get($m)->total      ?? 0);
            $validasiArr[] = (int)($validasiRaw->get($m)->total ?? 0);
        }

        return response()->json([
            'status' => 200,
            'result' => [
                'categories' => $categories,
                'series' => [
                    ['name' => 'Registrasi Sampel', 'data' => $sampelArr],
                    ['name' => 'Uji Selesai',        'data' => $ujiArr],
                    ['name' => 'Validasi Final',      'data' => $validasiArr],
                ],
            ],
        ]);
    }

    public function getBebanMesinAtasan()
    {
        $data = DB::table('N_EMI_LAB_PO_Sampel as ps')
            ->join('EMI_Master_Mesin as mm', 'ps.Id_Mesin', '=', 'mm.Id_Master_Mesin')
            ->whereNull('ps.Status')
            ->select('mm.Nama_Mesin',
                DB::raw('COUNT(*) as total'),
                DB::raw("COUNT(CASE WHEN ps.Flag_Selesai = 'Y' THEN 1 END) as selesai")
            )
            ->groupBy('mm.Nama_Mesin')
            ->orderByDesc('total')
            ->get();

        return response()->json([
            'status' => 200,
            'result' => [
                'categories' => $data->pluck('Nama_Mesin')->toArray(),
                'total'      => $data->pluck('total')->map(fn($v) => (int)$v)->values()->toArray(),
                'selesai'    => $data->pluck('selesai')->map(fn($v) => (int)$v)->values()->toArray(),
            ],
        ]);
    }

    public function getStatusOverallAtasan()
    {
        $po = DB::table('N_EMI_LAB_PO_Sampel')
            ->whereNull('Status')
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN Flag_Selesai = 'Y' THEN 1 END) as selesai,
                COUNT(CASE WHEN Flag_Trial_Produksi = 'Y' THEN 1 END) as trial,
                COUNT(CASE WHEN Flag_Close_Po = 'Y' THEN 1 END) as close_po
            ")
            ->first();

        $pending = max(0, (int)($po->total ?? 0) - (int)($po->selesai ?? 0));

        return response()->json([
            'status' => 200,
            'result' => [
                'selesai'  => (int)($po->selesai   ?? 0),
                'pending'  => $pending,
                'trial'    => (int)($po->trial     ?? 0),
                'close_po' => (int)($po->close_po  ?? 0),
            ],
        ]);
    }

    public function getTopAnalisAtasan()
    {
        $bulanIni = Carbon::now()->month;
        $tahunIni = Carbon::now()->year;

        $data = DB::table('N_EMI_LAB_Uji_Sampel')
            ->whereNull('Status')
            ->where('Flag_Selesai', 'Y')
            ->whereMonth('Tanggal', $bulanIni)
            ->whereYear('Tanggal', $tahunIni)
            ->select('Id_User', DB::raw('COUNT(*) as total'))
            ->groupBy('Id_User')
            ->orderByDesc('total')
            ->take(8)
            ->get();

        return response()->json([
            'status' => 200,
            'result' => [
                'categories' => $data->pluck('Id_User')->toArray(),
                'data'       => $data->pluck('total')->map(fn($v) => (int)$v)->values()->toArray(),
            ],
        ]);
    }

}
