<?php

namespace App\Http\Controllers\MonitoringAnalisa;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonitoringAnalisaController extends Controller
{
    public function index()
    {
        return inertia('vue/dashboard/lab/MonitoringAnalisa');
    }

    // ── Listing: returns split list only (no sampel detail) ─────────────────
    // Fast: 3 targeted queries — no parameter/activity data loaded at all.
    public function getMonitoringData(Request $request)
    {
        $search       = $request->input('search', '');
        $tipe         = $request->input('tipe', '');
        $statusFilter = $request->input('status', '');
        $dari         = $request->input('tanggal_dari', '');
        $sampai       = $request->input('tanggal_sampai', '');
        $kodeBarang   = $request->input('kode_barang', '');
        $page         = max(1, (int) $request->input('page', 1));
        $limit        = max(5, min(50, (int) $request->input('limit', 15)));

        if (!$dari && !$sampai) {
            $dari = $sampai = now()->toDateString();
        } elseif ($dari && $sampai) {
            if ((int) round((strtotime($sampai) - strtotime($dari)) / 86400) > 90) {
                $sampai = date('Y-m-d', strtotime($dari . ' +90 days'));
            }
        }

        // Step 1: Get No_Sampel IDs (indexed query on Tanggal)
        $noSampelIds = $this->getFilteredSampelIds($search, $tipe, $dari, $sampai, $kodeBarang);

        if (empty($noSampelIds)) {
            return response()->json([
                'success' => true, 'status' => 200,
                'result'  => ['splits' => [], 'summary' => $this->emptySummary()],
                'pagination' => ['page' => 1, 'limit' => $limit, 'totalPage' => 0, 'total' => 0],
            ]);
        }

        // Step 2: Aggregate uji counts for status (whereIn = index scan on No_Po_Sampel)
        $ujiAggMap = $this->aggregateUji($noSampelIds);

        // Step 3: HVF flags
        $hvfSet = DB::table('N_EMI_LAB_Hasil_Uji_Validasi_Final')
            ->whereIn('No_Sampel', $noSampelIds)
            ->pluck('No_Sampel')->flip();

        // Step 4: PO metadata (no joins to uji tables)
        $sampelList = $this->getSampelMeta($noSampelIds);

        // Step 5: Compute statuses + group into splits
        $priority    = ['belum_input' => 5, 'resampling' => 4, 'menunggu_validasi' => 3, 'menunggu_finalisasi' => 2, 'selesai' => 1];
        $splitMap    = [];
        $allStatuses = [];

        foreach ($sampelList as $row) {
            $uji    = $ujiAggMap->get($row->No_Sampel);
            $hasFV  = isset($hvfSet[$row->No_Sampel]);
            $status = $this->computeStatus($row, $uji, $hasFV);

            $allStatuses[] = $status;
            $k = $row->No_Split_Po;
            if (!isset($splitMap[$k])) {
                $splitMap[$k] = [
                    'no_po' => $row->No_Po, 'no_split_po' => $row->No_Split_Po,
                    'kode_barang' => $row->Kode_Barang, 'flag_trial' => $row->Flag_Trial_Produksi === 'Y',
                    'statuses' => [],
                ];
            }
            $splitMap[$k]['statuses'][] = $status;
        }

        // Step 6: Summary
        $summary = [
            'total_splits'        => count($splitMap),
            'total_sampel'        => count($allStatuses),
            'belum_input'         => count(array_filter($allStatuses, fn($s) => $s === 'belum_input')),
            'menunggu_validasi'   => count(array_filter($allStatuses, fn($s) => $s === 'menunggu_validasi')),
            'resampling'          => count(array_filter($allStatuses, fn($s) => $s === 'resampling')),
            'menunggu_finalisasi' => count(array_filter($allStatuses, fn($s) => $s === 'menunggu_finalisasi')),
            'selesai'             => count(array_filter($allStatuses, fn($s) => $s === 'selesai')),
        ];

        // Step 7: Build splits, filter, paginate
        $splits = [];
        foreach ($splitMap as $sp) {
            $splitStatus = 'selesai';
            foreach ($sp['statuses'] as $st) {
                if (($priority[$st] ?? 0) > ($priority[$splitStatus] ?? 0)) $splitStatus = $st;
            }
            $splits[] = [
                'no_po'         => $sp['no_po'],
                'no_split_po'   => $sp['no_split_po'],
                'kode_barang'   => $sp['kode_barang'],
                'flag_trial'    => $sp['flag_trial'],
                'status_split'  => $splitStatus,
                'total_sampel'  => count($sp['statuses']),
                'status_counts' => array_count_values($sp['statuses']),
            ];
        }

        if ($statusFilter) {
            $splits = array_values(array_filter($splits, fn($sp) => $sp['status_split'] === $statusFilter));
        }

        $total     = count($splits);
        $totalPage = $total > 0 ? (int) ceil($total / $limit) : 1;
        $page      = min($page, max(1, $totalPage));

        return response()->json([
            'success' => true, 'status' => 200,
            'result'  => ['splits' => array_slice($splits, ($page - 1) * $limit, $limit), 'summary' => $summary],
            'pagination' => ['page' => $page, 'limit' => $limit, 'totalPage' => $totalPage, 'total' => $total],
        ]);
    }

    // ── Detail: called when user clicks a split ───────────────────────────────
    // Loads full sampel + activities for one specific split only.
    public function getMonitoringDetail(Request $request)
    {
        $noSplitPo = $request->input('no_split_po', '');
        if (!$noSplitPo) {
            return response()->json(['success' => false, 'message' => 'no_split_po diperlukan'], 400);
        }

        // Get No_Sampel for this split
        $noSampelList = DB::table('N_EMI_LAB_PO_Sampel')
            ->where('No_Split_Po', $noSplitPo)
            ->whereNull('Status')
            ->orderBy('No_Sampel')
            ->pluck('No_Sampel')
            ->toArray();

        if (empty($noSampelList)) {
            return response()->json(['success' => true, 'sampel' => []]);
        }

        // Uji aggregation for status computation
        $ujiAggMap = $this->aggregateUji($noSampelList);

        // HVF flags
        $hvfSet = DB::table('N_EMI_LAB_Hasil_Uji_Validasi_Final')
            ->whereIn('No_Sampel', $noSampelList)
            ->pluck('No_Sampel')->flip();

        // PO metadata
        $sampelMeta = $this->getSampelMeta($noSampelList);

        // Full uji detail
        $ujiItems = $this->loadUjiForPage($noSampelList);
        $fakturs  = $ujiItems->pluck('No_Faktur')->filter()->unique()->values()->toArray();
        $jaIds    = $ujiItems->pluck('Id_Jenis_Analisa')->unique()->filter()->values()->toArray();

        $templateMap   = $this->loadTemplateMap($jaIds);
        $strRules      = $this->loadStrRules($jaIds);
        $parameterRaw  = $this->loadParameterValues($fakturs);
        $berkasAll     = $this->loadBerkas(
            $ujiItems->filter(fn($u) => $u->Kode_Aktivitas_Lab === 'LCKV' && $u->Flag_Foto === 'Y')
                     ->pluck('No_Faktur')->filter()->unique()->values()->toArray()
        );
        $pembandingMap = $this->loadPembanding(
            $ujiItems->filter(fn($u) => $u->Kode_Aktivitas_Lab === 'PLT' && $u->Id_Pembanding)
                     ->pluck('Id_Pembanding')->filter()->unique()->values()->toArray()
        );

        $ujiByNs   = $ujiItems->groupBy('No_Po_Sampel');
        $sampelArr = [];

        foreach ($sampelMeta as $row) {
            $uji     = $ujiAggMap->get($row->No_Sampel);
            $hasFV   = isset($hvfSet[$row->No_Sampel]);
            $status  = $this->computeStatus($row, $uji, $hasFV);
            $ujiNs   = $ujiByNs[$row->No_Sampel] ?? collect();

            $sampelArr[] = [
                'no_sampel'          => $row->No_Sampel,
                'no_po'              => $row->No_Po,
                'no_split_po'        => $row->No_Split_Po,
                'no_batch'           => $row->No_Batch,
                'tanggal'            => $row->Tanggal,
                'kode_barang'        => $row->Kode_Barang,
                'nama_mesin'         => $row->Nama_Mesin,
                'flag_fg'            => $row->Flag_FG === 'Y',
                'flag_trial'         => $row->Flag_Trial_Produksi === 'Y',
                'has_validasi_final' => $hasFV,
                'status'             => $status,
                'no_fak_sub_po_list' => $ujiNs->pluck('No_Fak_Sub_Po')->filter()->unique()->sort()->values()->toArray(),
                'activities'         => $this->buildActivities($ujiNs, $templateMap, $strRules, $berkasAll, $pembandingMap, $parameterRaw),
            ];
        }

        return response()->json(['success' => true, 'sampel' => $sampelArr]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function getFilteredSampelIds(?string $search, ?string $tipe, ?string $dari, ?string $sampai, ?string $kodeBarang): array
    {
        $q = DB::table('N_EMI_LAB_PO_Sampel as pos')
            ->whereNull('pos.Status');

        $search     = $search     ?? '';
        $tipe       = $tipe       ?? '';
        $dari       = $dari       ?? '';
        $sampai     = $sampai     ?? '';
        $kodeBarang = $kodeBarang ?? '';

        if ($tipe === 'trial')        $q->where('pos.Flag_Trial_Produksi', 'Y');
        elseif ($tipe === 'produksi') $q->whereNull('pos.Flag_Trial_Produksi');
        if ($dari)       $q->where('pos.Tanggal', '>=', $dari);
        if ($sampai)     $q->where('pos.Tanggal', '<=', $sampai);
        if ($kodeBarang) $q->where('pos.Kode_Barang', 'like', '%' . $kodeBarang . '%');
        if ($search) {
            $q->where(function ($sq) use ($search) {
                $sq->where('pos.No_Po',        'like', '%' . $search . '%')
                   ->orWhere('pos.No_Split_Po', 'like', '%' . $search . '%')
                   ->orWhere('pos.No_Sampel',   'like', '%' . $search . '%')
                   ->orWhere('pos.Kode_Barang', 'like', '%' . $search . '%');
            });
        }

        return $q->pluck('pos.No_Sampel')->unique()->values()->toArray();
    }

    private function aggregateUji(array $noSampelList): \Illuminate\Support\Collection
    {
        if (empty($noSampelList)) return collect();
        $result = collect();
        foreach (array_chunk($noSampelList, 500) as $chunk) {
            $result = $result->merge(
                DB::table('N_EMI_LAB_Uji_Sampel as us')
                    ->join('N_EMI_LAB_Jenis_Analisa as ja', 'us.Id_Jenis_Analisa', '=', 'ja.id')
                    ->whereIn('us.No_Po_Sampel', $chunk)
                    ->whereNull('us.Status')
                    ->where('ja.Kode_Role', 'LAB')
                    ->groupBy('us.No_Po_Sampel')
                    ->select(
                        'us.No_Po_Sampel',
                        DB::raw('COUNT(us.No_Faktur) as cnt_uji'),
                        DB::raw("SUM(CASE WHEN us.Flag_Final = 'Y' THEN 1 ELSE 0 END) as cnt_final"),
                        DB::raw("SUM(CASE WHEN us.Flag_Resampling = 'Y' AND us.Status_Keputusan_Sampel IS NULL THEN 1 ELSE 0 END) as cnt_resamp_open"),
                        DB::raw("SUM(CASE WHEN (us.Flag_Resampling IS NULL OR us.Flag_Resampling <> 'Y') AND us.Status_Keputusan_Sampel IS NULL THEN 1 ELSE 0 END) as cnt_pending_val"),
                        DB::raw("SUM(CASE WHEN us.Status_Keputusan_Sampel = 'terima' AND (us.Flag_Resampling IS NULL OR us.Flag_Resampling <> 'Y') THEN 1 ELSE 0 END) as cnt_terima_non"),
                        DB::raw("SUM(CASE WHEN us.Flag_Resampling IS NULL OR us.Flag_Resampling <> 'Y' THEN 1 ELSE 0 END) as cnt_non_resamp")
                    )
                    ->get()
            );
        }
        return $result->keyBy('No_Po_Sampel');
    }

    private function getSampelMeta(array $noSampelList): \Illuminate\Support\Collection
    {
        if (empty($noSampelList)) return collect();
        $result = collect();
        foreach (array_chunk($noSampelList, 500) as $chunk) {
            $result = $result->merge(
                DB::table('N_EMI_LAB_PO_Sampel as pos')
                    ->leftJoin('EMI_Master_Mesin as mm', 'pos.Id_Mesin', '=', 'mm.Id_Master_Mesin')
                    ->whereIn('pos.No_Sampel', $chunk)
                    ->whereNull('pos.Status')
                    ->select(
                        'pos.No_Split_Po', 'pos.No_Po', 'pos.No_Sampel',
                        'pos.No_Batch', 'pos.Kode_Barang', 'pos.Tanggal', 'pos.Flag_Trial_Produksi',
                        'mm.Flag_FG', 'mm.Nama_Mesin'
                    )
                    ->orderByDesc('pos.No_Split_Po')
                    ->orderBy('pos.No_Sampel')
                    ->get()
            );
        }
        return $result;
    }

    private function computeStatus($row, $uji, bool $hasFV): string
    {
        $isFG = $row->Flag_FG === 'Y';
        if (!$uji || (int) $uji->cnt_uji === 0) return 'belum_input';
        if ($isFG ? $hasFV : ((int) $uji->cnt_final > 0)) return 'selesai';
        if ((int) $uji->cnt_resamp_open > 0) return 'resampling';
        if ($isFG && (int) $uji->cnt_non_resamp > 0 && (int) $uji->cnt_terima_non === (int) $uji->cnt_non_resamp) return 'menunggu_finalisasi';
        if ((int) $uji->cnt_pending_val > 0) return 'menunggu_validasi';
        return 'menunggu_validasi';
    }

    private function loadUjiForPage(array $noSampelList): \Illuminate\Support\Collection
    {
        if (empty($noSampelList)) return collect();
        $result = collect();
        foreach (array_chunk($noSampelList, 500) as $chunk) {
            $result = $result->merge(
                DB::table('N_EMI_LAB_Uji_Sampel as us')
                    ->join('N_EMI_LAB_Jenis_Analisa as ja', 'us.Id_Jenis_Analisa', '=', 'ja.id')
                    ->leftJoin('N_EMI_LAB_Perhitungan as ph', function ($j) {
                        $j->on('ph.id', '=', 'us.Id_Perhitungan')
                          ->on('ph.Kode_Perusahaan', '=', 'us.Kode_Perusahaan');
                    })
                    ->whereIn('us.No_Po_Sampel', $chunk)
                    ->whereNull('us.Status')
                    ->where('ja.Kode_Role', 'LAB')
                    ->select(
                        'us.No_Po_Sampel', 'us.No_Faktur', 'us.No_Fak_Sub_Po',
                        'us.Id_Jenis_Analisa', 'us.Tahapan_Ke', 'us.Id_Pembanding',
                        'us.Flag_Resampling', 'us.Status_Keputusan_Sampel',
                        'us.Flag_Final', 'us.Flag_Layak', 'us.Hasil',
                        'us.Flag_String', 'us.Nilai_Hasil_String', 'us.Flag_Foto',
                        'us.Flag_Perhitungan', 'us.Tanggal as Tanggal_Uji',
                        'ja.Kode_Aktivitas_Lab', 'ja.Jenis_Analisa', 'ja.Kode_Analisa',
                        DB::raw('ISNULL(ph.Hasil_Perhitungan, 0) AS Pembulatan')
                    )->get()
            );
        }
        return $result;
    }

    private function loadTemplateMap(array $jaIdList): array
    {
        if (empty($jaIdList)) return [];
        $map = [];
        foreach (
            DB::table('N_EMI_LAB_Binding_jenis_analisa as b')
                ->join('EMI_Quality_Control as q', 'q.Id_QC_Formula', '=', 'b.Id_Quality_Control')
                ->whereIn('b.Id_Jenis_Analisa', $jaIdList)
                ->select('b.Id_Jenis_Analisa', 'q.Id_QC_Formula as id_qc', 'q.Keterangan as nama_parameter', 'q.Satuan as satuan')
                ->orderBy('b.Id_Jenis_Analisa')->orderBy('b.id')->get()
            as $row
        ) {
            $map[$row->Id_Jenis_Analisa][] = ['id_qc' => $row->id_qc, 'nama_parameter' => $row->nama_parameter, 'satuan' => $row->satuan ?? null];
        }
        return $map;
    }

    private function loadStrRules(array $jaIdList): array
    {
        if (empty($jaIdList)) return [];
        $map = [];
        foreach (
            DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->whereIn('Id_Jenis_Analisa', $jaIdList)
                ->where('Flag_Aktif', 'Y')
                ->select('Id_Jenis_Analisa', 'Nilai_Kriteria', 'Keterangan_Kriteria')
                ->get()
            as $row
        ) {
            $map[$row->Id_Jenis_Analisa][(string)((float)$row->Nilai_Kriteria)] = $row->Keterangan_Kriteria;
        }
        return $map;
    }

    private function loadParameterValues(array $fakturs): \Illuminate\Support\Collection
    {
        if (empty($fakturs)) return collect();
        $result = collect();
        foreach (array_chunk($fakturs, 500) as $chunk) {
            $result = $result->merge(
                DB::table('N_EMI_LAB_Uji_Sampel_Detail')
                    ->select('No_Faktur_Uji_Sample', 'Id_Quality_Control', 'Value_Parameter as Hasil_Analisa')
                    ->whereIn('No_Faktur_Uji_Sample', $chunk)
                    ->orderBy('Id_Quality_Control')->get()
            );
        }
        return $result->groupBy('No_Faktur_Uji_Sample');
    }

    private function loadBerkas(array $fakturs): \Illuminate\Support\Collection
    {
        if (empty($fakturs)) return collect();
        $result = collect();
        foreach (array_chunk($fakturs, 500) as $chunk) {
            $result = $result->merge(
                DB::table('N_EMI_LAB_Berkas_Uji_Lab')
                    ->select('No_Faktur', 'Berkas_Key')
                    ->whereIn('No_Faktur', $chunk)->whereNotNull('Berkas_Key')->get()
            );
        }
        return $result->groupBy('No_Faktur');
    }

    private function loadPembanding(array $ids): \Illuminate\Support\Collection
    {
        if (empty($ids)) return collect();
        return DB::table('N_EMI_LAB_Palatabilitas_Pembanding')
            ->whereIn('Id_Pembanding', $ids)
            ->select('Id_Pembanding', 'Nama_Pembanding')->get()->keyBy('Id_Pembanding');
    }

    private function buildActivities(
        \Illuminate\Support\Collection $ujiItems,
        array $templateMap, array $strRules,
        \Illuminate\Support\Collection $berkasAll,
        \Illuminate\Support\Collection $pembandingMap,
        \Illuminate\Support\Collection $parameterRaw
    ): array {
        $activities = [];
        foreach (['LCKV', 'ANL', 'PLT'] as $act) {
            $actItems = $ujiItems->filter(fn($u) => $u->Kode_Aktivitas_Lab === $act);
            if ($actItems->isEmpty()) continue;

            $groups = [];
            foreach ($actItems->groupBy('Id_Jenis_Analisa') as $jaId => $jaItems) {
                $first   = $jaItems->first();
                $tmpl    = $templateMap[$jaId] ?? [];
                $jaRules = $strRules[$jaId] ?? [];

                $rows = $jaItems
                    // For PLT, each unique pembanding must produce its own row (different Id_Pembanding).
                    // Grouping only by No_Fak_Sub_Po collapses all pembanding into one group and
                    // only the first one (sortByDesc->first) would be shown — hence only 1 pembanding
                    // appeared in monitoring. Including Id_Pembanding in the key for PLT fixes this.
                    ->groupBy(fn($u) => ($u->No_Fak_Sub_Po ?? '__') . ($act === 'PLT' ? '-pb-' . ($u->Id_Pembanding ?? '0') : ''))
                    ->map(function ($grp) use ($jaRules, $berkasAll, $act, $pembandingMap, $parameterRaw) {
                        $top        = $grp->sortByDesc('Tahapan_Ke')->first();
                        $pembulatan = (int)($top->Pembulatan ?? 0);

                        $displayResult = null;
                        if ($top->Hasil !== null && $top->Hasil !== '') {
                            if ($top->Flag_String === 'Y' && $top->Nilai_Hasil_String !== null) {
                                $key           = (string)((float)$top->Nilai_Hasil_String);
                                $displayResult = $jaRules[$key] ?? $top->Nilai_Hasil_String;
                            } else {
                                $val = (float)$top->Hasil;
                                $key = (string)$val;
                                if (is_null($top->Flag_Perhitungan) && isset($jaRules[$key])) {
                                    $displayResult = $jaRules[$key];
                                } else {
                                    $displayResult = number_format($val, $pembulatan, '.', '');
                                }
                            }
                        }

                        $paramValues = ($parameterRaw->get($top->No_Faktur) ?? collect())
                            ->map(function ($p) use ($top, $jaRules) {
                                if ($p->Hasil_Analisa === null || $p->Hasil_Analisa === '') return null;
                                $val = (float)$p->Hasil_Analisa;
                                $key = (string)$val;
                                if (is_null($top->Flag_Perhitungan) && isset($jaRules[$key])) return $jaRules[$key];
                                return round($val, 4);
                            })->values()->toArray();

                        $berkasKeys = [];
                        $hasFoto    = false;
                        if ($act === 'LCKV' && $top->Flag_Foto === 'Y') {
                            $berkasKeys = ($berkasAll[$top->No_Faktur] ?? collect())->pluck('Berkas_Key')->filter()->values()->toArray();
                            $hasFoto    = count($berkasKeys) > 0;
                        }

                        $namaPembanding = null;
                        if ($act === 'PLT' && $top->Id_Pembanding) {
                            $pb             = $pembandingMap->get($top->Id_Pembanding);
                            $namaPembanding = $pb ? $pb->Nama_Pembanding : null;
                        }

                        return [
                            'no_faktur'        => $top->No_Faktur,
                            'no_fak_sub_po'    => $top->No_Fak_Sub_Po,
                            'tanggal_uji'      => $top->Tanggal_Uji,
                            'tahapan_ke'       => $top->Tahapan_Ke,
                            'flag_resampling'  => $top->Flag_Resampling,
                            'flag_layak'       => $top->Flag_Layak,
                            'status_keputusan' => $top->Status_Keputusan_Sampel,
                            'hasil'            => $displayResult,
                            'flag_perhitungan' => $top->Flag_Perhitungan,
                            'nama_pembanding'  => $namaPembanding,
                            'parameters'       => $paramValues,
                            'has_foto'         => $hasFoto,
                            'berkas_keys'      => $berkasKeys,
                            'foto_count'       => count($berkasKeys),
                        ];
                    })->values()->toArray();

                $groups[] = [
                    'kode_analisa'     => $first->Kode_Analisa,
                    'jenis_analisa'    => $first->Jenis_Analisa,
                    'flag_perhitungan' => $first->Flag_Perhitungan,
                    'template'         => $tmpl,
                    'rows'             => $rows,
                ];
            }
            $activities[$act] = $groups;
        }
        return $activities;
    }

    private function emptySummary(): array
    {
        return ['total_splits' => 0, 'total_sampel' => 0, 'belum_input' => 0, 'menunggu_validasi' => 0, 'resampling' => 0, 'menunggu_finalisasi' => 0, 'selesai' => 0];
    }
}
