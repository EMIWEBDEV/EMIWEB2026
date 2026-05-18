<?php

namespace App\Http\Controllers\Formulator;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class FormulatorDashboardController extends Controller
{
    // ============================================================
    // PAGE METHODS
    // ============================================================

    public function analyzerPage()
    {
        return inertia('vue/dashboard/dashboard-formulator/DashboardFormulator', [
            'namaPengguna' => Auth::user()->Nama ?? Auth::user()->UserId,
        ]);
    }

    public function atasanPage()
    {
        return inertia('vue/dashboard/dashboard-formulator-atasan/DashboardFormulatorAtasan', [
            'namaPengguna' => Auth::user()->Nama ?? Auth::user()->UserId,
        ]);
    }

    // ============================================================
    // ANALYZER API
    // ============================================================

    public function getKpiHariIni()
    {
        $start = Carbon::today()->startOfDay();
        $end   = Carbon::today()->endOfDay();

        $data = DB::table('N_LIMS_PO_Sampel')
            ->whereBetween('Tanggal', [$start, $end])
            ->selectRaw("
                COUNT(*) as total_sampel,
                COUNT(CASE WHEN Flag_Selesai = 'Y' THEN 1 END) as total_selesai,
                COUNT(CASE WHEN Flag_Selesai IS NULL THEN 1 END) as total_pending,
                COUNT(CASE WHEN Flag_Close_Po = 'Y' THEN 1 END) as total_close
            ")
            ->first();

        $perAktivitas = DB::table('N_EMI_LIMS_Uji_Sampel as us')
            ->join('N_EMI_LAB_Jenis_Analisa as ja', 'us.Id_Jenis_Analisa', '=', 'ja.id')
            ->whereBetween('us.Tanggal', [$start, $end])
            ->whereNull('us.Status')
            ->select('ja.Kode_Aktivitas_Lab', DB::raw('COUNT(*) as total'))
            ->groupBy('ja.Kode_Aktivitas_Lab')
            ->get()
            ->keyBy('Kode_Aktivitas_Lab');

        return response()->json([
            'status' => 200,
            'result' => [
                ['title' => 'Total Sampel Hari Ini',   'value' => (int)($data->total_sampel  ?? 0), 'icon' => 'ri-flask-line',           'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.12)',  'sub' => 'Terdaftar hari ini'],
                ['title' => 'Sampel Selesai',           'value' => (int)($data->total_selesai ?? 0), 'icon' => 'ri-check-double-line',    'color' => '#059669', 'bg' => 'rgba(5,150,105,0.12)',   'sub' => 'Proses selesai'],
                ['title' => 'Sampel Pending',           'value' => (int)($data->total_pending ?? 0), 'icon' => 'ri-time-line',            'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.12)',   'sub' => 'Menunggu proses'],
                ['title' => 'Look View (LCKV)',          'value' => (int)($perAktivitas->get('LCKV')->total ?? 0), 'icon' => 'ri-eye-line',            'color' => '#0284c7', 'bg' => 'rgba(2,132,199,0.12)',   'sub' => 'Aktivitas hari ini'],
                ['title' => 'Analisa Lab (ANL)',         'value' => (int)($perAktivitas->get('ANL')->total  ?? 0), 'icon' => 'ri-microscope-line',     'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.12)',  'sub' => 'Aktivitas hari ini'],
                ['title' => 'Uji Palatabilitas (PLT)',   'value' => (int)($perAktivitas->get('PLT')->total  ?? 0), 'icon' => 'ri-heart-pulse-line',    'color' => '#dc2626', 'bg' => 'rgba(220,38,38,0.12)',   'sub' => 'Aktivitas hari ini'],
            ],
        ]);
    }

    public function getTrenAktivitas(Request $request)
    {
        $days      = max(7, min(30, (int) $request->query('days', 7)));
        $endDate   = Carbon::today();
        $startDate = $endDate->copy()->subDays($days - 1);

        $rawData = DB::table('N_LIMS_PO_Sampel')
            ->whereBetween('Tanggal', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->select(
                DB::raw("FORMAT(Tanggal, 'yyyy-MM-dd') as date"),
                DB::raw('COUNT(*) as total'),
                DB::raw("COUNT(CASE WHEN Flag_Selesai = 'Y' THEN 1 END) as selesai")
            )
            ->groupBy(DB::raw("FORMAT(Tanggal, 'yyyy-MM-dd')"))
            ->get()
            ->keyBy('date');

        $period = CarbonPeriod::create($startDate, $endDate);
        $categories = $totalArr = $selesaiArr = [];

        foreach ($period as $date) {
            $key          = $date->format('Y-m-d');
            $categories[] = $date->format('d M');
            $row          = $rawData->get($key);
            $totalArr[]   = (int)($row->total   ?? 0);
            $selesaiArr[] = (int)($row->selesai ?? 0);
        }

        return response()->json([
            'status' => 200,
            'result' => [
                'categories' => $categories,
                'series' => [
                    ['name' => 'Total Sampel', 'data' => $totalArr],
                    ['name' => 'Selesai',       'data' => $selesaiArr],
                ],
            ],
        ]);
    }

    public function getDistribusiJenis()
    {
        $data = DB::table('N_EMI_LIMS_Uji_Sampel as us')
            ->join('N_EMI_LAB_Jenis_Analisa as ja', 'us.Id_Jenis_Analisa', '=', 'ja.id')
            ->whereNull('us.Status')
            ->where('ja.Kode_Role', 'FLM')
            ->select('ja.Jenis_Analisa', DB::raw('COUNT(*) as total'))
            ->groupBy('ja.Jenis_Analisa', 'ja.id')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->get();

        return response()->json([
            'status' => 200,
            'result' => [
                'labels' => $data->pluck('Jenis_Analisa')->toArray(),
                'data'   => $data->pluck('total')->map(fn($v) => (int)$v)->values()->toArray(),
            ],
        ]);
    }

    public function getAktivitasTerbaru()
    {
        $data = DB::table('N_EMI_LIMS_Uji_Sampel as us')
            ->join('N_EMI_LAB_Jenis_Analisa as ja', 'us.Id_Jenis_Analisa', '=', 'ja.id')
            ->whereNull('us.Status')
            ->select(
                'us.No_Faktur',
                'us.No_Po_Sampel',
                'ja.Jenis_Analisa',
                'ja.Kode_Aktivitas_Lab',
                'us.Id_User',
                'us.Tanggal',
                'us.Jam',
                'us.Flag_Selesai',
                'us.Flag_Foto',
                'us.Status_Keputusan_Sampel'
            )
            ->orderByDesc('us.Tanggal')
            ->orderByDesc('us.Jam')
            ->take(10)
            ->get()
            ->map(fn($item) => array_merge((array)$item, [
                'Tanggal' => Carbon::parse($item->Tanggal)->format('d M Y'),
            ]));

        return response()->json(['status' => 200, 'result' => $data]);
    }

    public function getKpiTat()
    {
        $bulanIni = Carbon::now()->month;
        $tahunIni = Carbon::now()->year;
        $monthNames = ['','Januari','Februari','Maret','April','Mei','Juni',
            'Juli','Agustus','September','Oktober','November','Desember'];

        $avgMinutes = DB::table('N_EMI_LIMS_Uji_Sampel as uji')
            ->join('N_LIMS_PO_Sampel as po', 'uji.No_Po_Sampel', '=', 'po.No_Sampel')
            ->where('uji.Flag_Selesai', 'Y')
            ->whereNull('uji.Status')
            ->whereMonth('uji.Tanggal', $bulanIni)
            ->whereYear('uji.Tanggal', $tahunIni)
            ->select(DB::raw("AVG(CAST(DATEDIFF(minute, po.Tanggal, uji.Tanggal) AS BIGINT)) as avg_tat"))
            ->value('avg_tat');

        return response()->json([
            'status' => 200,
            'result' => [
                'hours'   => (int)floor(($avgMinutes ?? 0) / 60),
                'minutes' => (int)(($avgMinutes ?? 0) % 60),
                'period'  => 'Periode ' . $monthNames[$bulanIni] . ' ' . $tahunIni,
            ],
        ]);
    }

    // ============================================================
    // ATASAN API
    // ============================================================

    public function getKpiRekap()
    {
        $bulanIni = Carbon::now()->month;
        $tahunIni = Carbon::now()->year;
        $monthNames = ['','Januari','Februari','Maret','April','Mei','Juni',
            'Juli','Agustus','September','Oktober','November','Desember'];

        $sampel = DB::table('N_LIMS_PO_Sampel')
            ->selectRaw("
                COUNT(*) as total_all,
                COUNT(CASE WHEN MONTH(Tanggal) = ? AND YEAR(Tanggal) = ? THEN 1 END) as total_bulan,
                COUNT(CASE WHEN Flag_Selesai = 'Y' AND MONTH(Tanggal) = ? AND YEAR(Tanggal) = ? THEN 1 END) as selesai_bulan,
                COUNT(CASE WHEN Flag_Selesai = 'Y' THEN 1 END) as selesai_all,
                COUNT(CASE WHEN Flag_Close_Po = 'Y' THEN 1 END) as close_po_all,
                COUNT(CASE WHEN Flag_Validasi_Formulator_Desktop = 'Y' AND MONTH(Tanggal) = ? AND YEAR(Tanggal) = ? THEN 1 END) as validasi_bulan,
                COUNT(CASE WHEN Flag_Validasi_Formulator_Desktop = 'Y' THEN 1 END) as validasi_all
            ", [$bulanIni, $tahunIni, $bulanIni, $tahunIni, $bulanIni, $tahunIni])
            ->first();

        $uji = DB::table('N_EMI_LIMS_Uji_Sampel')
            ->whereNull('Status')
            ->where('Flag_Selesai', 'Y')
            ->whereMonth('Tanggal', $bulanIni)
            ->whereYear('Tanggal', $tahunIni)
            ->selectRaw("COUNT(*) as total, COUNT(CASE WHEN Flag_Layak = 'Y' THEN 1 END) as layak")
            ->first();

        $fotoBulan = DB::table('N_EMI_LIMS_Uji_Sampel')
            ->where('Flag_Foto', 'Y')
            ->whereMonth('Tanggal', $bulanIni)
            ->whereYear('Tanggal', $tahunIni)
            ->count();

        $fotoAll = DB::table('N_EMI_LIMS_Berkas_Uji_Lab')->count();

        $praFinalBulan = DB::table('N_EMI_LIMS_Uji_Pra_Final')
            ->whereMonth('Tanggal', $bulanIni)
            ->whereYear('Tanggal', $tahunIni)
            ->count();

        $validasiFinalBulan = DB::table('N_EMI_LIMS_Hasil_Uji_Validasi_Final')
            ->whereMonth('Tanggal', $bulanIni)
            ->whereYear('Tanggal', $tahunIni)
            ->count();

        $passRate = ($uji->total ?? 0) > 0
            ? round(($uji->layak / $uji->total) * 100, 1) : 0;

        return response()->json([
            'status' => 200,
            'result' => [
                'periode'            => $monthNames[$bulanIni] . ' ' . $tahunIni,
                'sampel_bulan_ini'   => (int)($sampel->total_bulan    ?? 0),
                'sampel_selesai'     => (int)($sampel->selesai_bulan  ?? 0),
                'sampel_all'         => (int)($sampel->total_all      ?? 0),
                'selesai_all'        => (int)($sampel->selesai_all    ?? 0),
                'close_po_all'       => (int)($sampel->close_po_all   ?? 0),
                'validasi_bulan'     => (int)($sampel->validasi_bulan ?? 0),
                'validasi_all'       => (int)($sampel->validasi_all   ?? 0),
                'pass_rate_pct'      => $passRate,
                'foto_bulan'         => $fotoBulan,
                'foto_all'           => $fotoAll,
                'pra_final_bulan'    => $praFinalBulan,
                'final_bulan'        => $validasiFinalBulan,
                'uji_selesai_bulan'  => (int)($uji->total ?? 0),
            ],
        ]);
    }

    public function getTrenBulanan()
    {
        $monthNames = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        $tahun      = Carbon::now()->year;
        $bulanNow   = Carbon::now()->month;

        $sampelRaw = DB::table('N_LIMS_PO_Sampel')
            ->whereYear('Tanggal', $tahun)
            ->selectRaw("MONTH(Tanggal) as m, COUNT(*) as total, COUNT(CASE WHEN Flag_Selesai = 'Y' THEN 1 END) as selesai")
            ->groupBy(DB::raw('MONTH(Tanggal)'))
            ->get()->keyBy('m');

        $ujiRaw = DB::table('N_EMI_LIMS_Uji_Sampel')
            ->whereNull('Status')->where('Flag_Selesai', 'Y')
            ->whereYear('Tanggal', $tahun)
            ->selectRaw("MONTH(Tanggal) as m, COUNT(*) as total")
            ->groupBy(DB::raw('MONTH(Tanggal)'))
            ->get()->keyBy('m');

        $finalRaw = DB::table('N_EMI_LIMS_Hasil_Uji_Validasi_Final')
            ->whereYear('Tanggal', $tahun)
            ->selectRaw("MONTH(Tanggal) as m, COUNT(*) as total")
            ->groupBy(DB::raw('MONTH(Tanggal)'))
            ->get()->keyBy('m');

        $categories = $sampelArr = $selesaiArr = $ujiArr = $finalArr = [];

        for ($m = 1; $m <= $bulanNow; $m++) {
            $categories[] = $monthNames[$m];
            $sampelArr[]  = (int)($sampelRaw->get($m)->total   ?? 0);
            $selesaiArr[] = (int)($sampelRaw->get($m)->selesai ?? 0);
            $ujiArr[]     = (int)($ujiRaw->get($m)->total     ?? 0);
            $finalArr[]   = (int)($finalRaw->get($m)->total   ?? 0);
        }

        return response()->json([
            'status' => 200,
            'result' => [
                'categories' => $categories,
                'series' => [
                    ['name' => 'Registrasi Sampel', 'data' => $sampelArr],
                    ['name' => 'Sampel Selesai',    'data' => $selesaiArr],
                    ['name' => 'Uji Selesai',        'data' => $ujiArr],
                    ['name' => 'Validasi Final',     'data' => $finalArr],
                ],
            ],
        ]);
    }

    public function getPerAktivitas()
    {
        $bulanIni = Carbon::now()->month;
        $tahunIni = Carbon::now()->year;

        $data = DB::table('N_EMI_LIMS_Uji_Sampel as us')
            ->join('N_EMI_LAB_Jenis_Analisa as ja', 'us.Id_Jenis_Analisa', '=', 'ja.id')
            ->join('N_EMI_LIMS_Klasifikasi_Aktivitas_Lab as ka', 'ja.Kode_Aktivitas_Lab', '=', 'ka.Kode_Aktivitas_Lab')
            ->whereNull('us.Status')
            ->whereMonth('us.Tanggal', $bulanIni)
            ->whereYear('us.Tanggal', $tahunIni)
            ->select(
                'ka.Nama_Aktivitas',
                'ka.Kode_Aktivitas_Lab',
                DB::raw('COUNT(*) as total'),
                DB::raw("COUNT(CASE WHEN us.Flag_Selesai = 'Y' THEN 1 END) as selesai"),
                DB::raw("COUNT(CASE WHEN us.Flag_Foto = 'Y' THEN 1 END) as dengan_foto")
            )
            ->groupBy('ka.Nama_Aktivitas', 'ka.Kode_Aktivitas_Lab')
            ->get();

        return response()->json(['status' => 200, 'result' => $data]);
    }

    public function getTopFormulator()
    {
        $bulanIni = Carbon::now()->month;
        $tahunIni = Carbon::now()->year;

        $data = DB::table('N_EMI_LIMS_Uji_Sampel')
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

    public function getSampelTerbaru()
    {
        $data = DB::table('N_LIMS_PO_Sampel as po')
            ->select(
                'po.No_Sampel', 'po.No_Po', 'po.No_Split_Po', 'po.No_Batch',
                'po.Kode_Barang', 'po.Id_User', 'po.Tanggal', 'po.Jam',
                'po.Flag_Selesai', 'po.Flag_Close_Po',
                'po.Flag_Validasi_Formulator_Desktop'
            )
            ->orderByDesc('po.Tanggal')
            ->orderByDesc('po.Jam')
            ->take(15)
            ->get();

        $noSampels = $data->pluck('No_Sampel')->toArray();

        $fotoCounts = DB::table('N_EMI_LIMS_Berkas_Uji_Lab')
            ->whereIn('No_Sampel', $noSampels)
            ->select('No_Sampel', DB::raw('COUNT(*) as jumlah_foto'))
            ->groupBy('No_Sampel')
            ->get()->keyBy('No_Sampel');

        $ujiCounts = DB::table('N_EMI_LIMS_Uji_Sampel')
            ->whereIn('No_Po_Sampel', $noSampels)
            ->whereNull('Status')
            ->select(
                'No_Po_Sampel',
                DB::raw('COUNT(*) as total_uji'),
                DB::raw("COUNT(CASE WHEN Flag_Selesai = 'Y' THEN 1 END) as selesai_uji")
            )
            ->groupBy('No_Po_Sampel')
            ->get()->keyBy('No_Po_Sampel');

        $praFinalMap = DB::table('N_EMI_LIMS_Uji_Pra_Final')
            ->whereIn('No_Sampel', $noSampels)
            ->get()->keyBy('No_Sampel');

        $validasiMap = DB::table('N_EMI_LIMS_Hasil_Uji_Validasi_Final')
            ->whereIn('No_Sampel', $noSampels)
            ->get()->keyBy('No_Sampel');

        $result = $data->map(function ($item) use ($fotoCounts, $ujiCounts, $praFinalMap, $validasiMap) {
            $uji = $ujiCounts->get($item->No_Sampel);
            $praFinal = $praFinalMap->get($item->No_Sampel);
            $validasiFinal = $validasiMap->get($item->No_Sampel);

            $status = 'Menunggu Analisa';
            if ($validasiFinal) {
                $status = 'Selesai';
            } elseif ($praFinal && $praFinal->Flag_Ok === 'Y') {
                $status = 'Menunggu Finalisasi';
            } elseif ($uji && $uji->total_uji > 0) {
                $status = 'Sedang Analisa';
            }

            return [
                'No_Sampel'    => $item->No_Sampel,
                'No_Po'        => $item->No_Po,
                'No_Split_Po'  => $item->No_Split_Po,
                'No_Batch'     => $item->No_Batch,
                'Kode_Barang'  => $item->Kode_Barang,
                'Id_User'      => $item->Id_User,
                'Tanggal'      => Carbon::parse($item->Tanggal)->format('d M Y'),
                'Jam'          => $item->Jam,
                'Flag_Selesai' => $item->Flag_Selesai,
                'Flag_Close'   => $item->Flag_Close_Po,
                'Flag_Validasi'=> $item->Flag_Validasi_Formulator_Desktop,
                'Jumlah_Foto'  => (int)($fotoCounts->get($item->No_Sampel)->jumlah_foto ?? 0),
                'Total_Uji'    => (int)($uji->total_uji   ?? 0),
                'Selesai_Uji'  => (int)($uji->selesai_uji ?? 0),
                'Status'       => $status,
            ];
        });

        return response()->json(['status' => 200, 'result' => $result]);
    }

    public function getFotoTerbaru()
    {
        $data = DB::table('N_EMI_LIMS_Berkas_Uji_Lab as b')
            ->leftJoin('N_EMI_LIMS_Uji_Sampel as us', 'b.No_Faktur', '=', 'us.No_Faktur')
            ->leftJoin('N_EMI_LAB_Jenis_Analisa as ja', 'us.Id_Jenis_Analisa', '=', 'ja.id')
            ->select(
                'b.Id_Berkas_Uji_Lab',
                'b.No_Faktur',
                'b.No_Sampel',
                'b.Berkas_Key',
                'b.File_Path',
                'b.Keterangan',
                'ja.Jenis_Analisa',
                'us.Id_User',
                'us.Tanggal',
                'us.Jam'
            )
            ->orderByDesc('b.Id_Berkas_Uji_Lab')
            ->take(12)
            ->get()
            ->map(fn($item) => array_merge((array)$item, [
                'Tanggal' => $item->Tanggal
                    ? Carbon::parse($item->Tanggal)->format('d M Y')
                    : '-',
            ]));

        return response()->json(['status' => 200, 'result' => $data]);
    }

    public function getStatusValidasi()
    {
        $bulanIni = Carbon::now()->month;
        $tahunIni = Carbon::now()->year;

        $praFinal = DB::table('N_EMI_LIMS_Uji_Pra_Final')
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN Flag_Ok = 'Y' THEN 1 END) as ok,
                COUNT(CASE WHEN Flag_FG = 'Y' THEN 1 END) as fg
            ")->first();

        $validasiFinal = DB::table('N_EMI_LIMS_Hasil_Uji_Validasi_Final')
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN Flag_Ok = 'Y' THEN 1 END) as ok,
                COUNT(CASE WHEN Flag_FG = 'Y' THEN 1 END) as fg
            ")->first();

        $praFinalBulan = DB::table('N_EMI_LIMS_Uji_Pra_Final')
            ->whereMonth('Tanggal', $bulanIni)->whereYear('Tanggal', $tahunIni)->count();

        $validasiFinalBulan = DB::table('N_EMI_LIMS_Hasil_Uji_Validasi_Final')
            ->whereMonth('Tanggal', $bulanIni)->whereYear('Tanggal', $tahunIni)->count();

        return response()->json([
            'status' => 200,
            'result' => [
                'pra_final' => [
                    'total'     => (int)($praFinal->total ?? 0),
                    'ok'        => (int)($praFinal->ok   ?? 0),
                    'fg'        => (int)($praFinal->fg   ?? 0),
                    'bulan_ini' => $praFinalBulan,
                ],
                'validasi_final' => [
                    'total'     => (int)($validasiFinal->total ?? 0),
                    'ok'        => (int)($validasiFinal->ok   ?? 0),
                    'fg'        => (int)($validasiFinal->fg   ?? 0),
                    'bulan_ini' => $validasiFinalBulan,
                ],
            ],
        ]);
    }
}
