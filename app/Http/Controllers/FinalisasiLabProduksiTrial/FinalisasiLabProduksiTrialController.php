<?php

namespace App\Http\Controllers\FinalisasiLabProduksiTrial;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Vinkla\Hashids\Facades\Hashids;

class FinalisasiLabProduksiTrialController extends Controller
{
    public function index()
    {
        return inertia("vue/dashboard/hasil-akhir-validasi/trial-produksi/HasilAkhirValidasi");
    }

    public function viewInformasiJenisAnalisaMultiQrS($no_sampel, $no_fak_sub_sampel)
    {
        if($no_fak_sub_sampel === null || $no_fak_sub_sampel === "null"){
            return inertia('vue/dashboard/hasil-akhir-validasi/trial-produksi/HasilAkhirValidasiNoPcsJenisAnalisa', [
                'No_Sampel' => $no_sampel,
            ]);
        }else {
            return inertia('vue/dashboard/hasil-akhir-validasi/trial-produksi/HasilAkhirValidasiPcsJenisAnalisa', [
                'No_Sampel' => $no_sampel,
                'No_Fak_Sub_Sampel' => $no_fak_sub_sampel,
            ]);
        }
    }
    
    public function getDataValidasiHasilAkhirDanCloseSampel(Request $request)
    {
        $checkedAkses = Session::get("user_permissions");
        $permissionKonten = $checkedAkses['permission_konten'] ?? [];

        $allowedAnalisaIds = [];
        if (isset($permissionKonten['Finalisasi Trial Produksi']) && is_array($permissionKonten['Finalisasi Trial Produksi'])) {
                foreach ($permissionKonten['Finalisasi Trial Produksi'] as $akses) {
                    if (isset($akses['flag']) && $akses['flag'] === 'Y' && isset($akses['id_jenis_analisa'])) {
                        $allowedAnalisaIds[] = $akses['id_jenis_analisa'];
                    }
                }
        }


        $perPage = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $qrType = $request->input('qr_type');
        $totalAnalisa = $request->input('total_analisa');

        if (empty($allowedAnalisaIds)) {
                return response()->json([
                    'success' => true,
                    'status'  => 200,
                    'message' => "Data tidak ditemukan (Tidak ada akses jenis analisa yang valid).",
                    'result'  => [
                        'data' => [],
                        'pagination' => [
                            'page'      => 1,
                            'limit'     => (int)$perPage,
                            'totalPage' => 0,
                            'totalData' => 0,
                        ]
                    ]
                ], 200);
        }

        $query = DB::table('N_EMI_LAB_Uji_Sampel as uji')
            ->join('N_EMI_LAB_PO_Sampel as po', 'uji.No_Po_Sampel', '=', 'po.No_Sampel')
            ->join('N_EMI_View_Barang as brg', 'po.Kode_Barang', '=', 'brg.Kode_Barang')
            ->select(
                'uji.No_Po_Sampel',
                DB::raw('MAX(uji.Tanggal) as Tanggal'),
                DB::raw('MAX(uji.Jam) as Jam'),
                'uji.Flag_Multi_QrCode',
                'po.No_Po',
                'po.No_Split_Po',
                'po.Kode_Barang',
                'brg.Nama as Nama_Barang',
                'po.Flag_Trial_Produksi'
            )
            ->whereIn('uji.Id_Jenis_Analisa', $allowedAnalisaIds)
            ->where('po.Flag_Trial_Produksi', 'Y')
            ->whereNull('po.Flag_Selesai')
            ->whereNull('po.Status')
            ->whereNull('uji.Status')
            ->where('uji.Flag_Selesai', 'Y')
            ->whereNull('uji.Flag_Final')
            ->where('uji.Status_Keputusan_Sampel', 'terima')
            ->where(function($q) {
                $q->where('uji.Flag_Resampling', '!=', 'Y')
                ->orWhereNull('uji.Flag_Resampling');
            });

        if (!empty($startDate) && !empty($endDate)) {
            $query->whereBetween('uji.Tanggal', [$startDate, $endDate]);
        }

        // Filter QR Code Type
        if (!empty($qrType)) {
            if ($qrType === 'Y') {
                $query->where('uji.Flag_Multi_QrCode', 'Y');
            } else {
                $query->where(function($q) {
                    $q->where('uji.Flag_Multi_QrCode', '!=', 'Y')
                    ->orWhereNull('uji.Flag_Multi_QrCode');
                });
            }
        }

        // Searching
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('uji.No_Po_Sampel', 'LIKE', "%{$search}%")
                ->orWhere('po.No_Po', 'LIKE', "%{$search}%")
                ->orWhere('po.No_Split_Po', 'LIKE', "%{$search}%")
                ->orWhere('po.Kode_Barang', 'LIKE', "%{$search}%")
                ->orWhere('brg.Nama', 'LIKE', "%{$search}%");
            });
        }

        $query->groupBy(
            'uji.No_Po_Sampel',
            'uji.Flag_Multi_QrCode',
            'po.No_Po',
            'po.No_Split_Po',
            'po.Kode_Barang',
            'brg.Nama',
            'po.Flag_Trial_Produksi'
        );

        // Filter total analisa (exact count)
        if (!empty($totalAnalisa)) {
            $query->having(DB::raw('COUNT(DISTINCT uji.Id_Jenis_Analisa)'), '=', (int)$totalAnalisa);
        }

        $query->orderByDesc(DB::raw('MAX(uji.Tanggal)'))
            ->orderByDesc(DB::raw('MAX(uji.Jam)'));

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);
        $items = $paginated->items();

        // Enrichment: sisipkan Detail_Jenis_Analisa per sampel
        $poSampelIds = collect($items)->pluck('No_Po_Sampel')->toArray();

        if (!empty($poSampelIds)) {
            $analisaDetails = DB::table('N_EMI_LAB_Uji_Sampel as uji')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'uji.Id_Jenis_Analisa', '=', 'ja.id')
                ->whereIn('uji.No_Po_Sampel', $poSampelIds)
                ->whereNull('uji.Status')
                ->where('uji.Flag_Selesai', 'Y')
                ->where('uji.Status_Keputusan_Sampel', 'terima')
                ->where(function($q) {
                    $q->where('uji.Flag_Resampling', '!=', 'Y')
                    ->orWhereNull('uji.Flag_Resampling');
                })
                ->select('uji.No_Po_Sampel', 'ja.id', 'ja.Kode_Analisa', 'ja.Jenis_Analisa', 'ja.Kode_Aktivitas_Lab')
                ->distinct()
                ->get()
                ->groupBy('No_Po_Sampel');

            $pltSessions = DB::table('N_EMI_LAB_Palatabilitas_Session as ps')
                ->leftJoin('N_EMI_LAB_Palatabilitas_Pembanding as pp', function ($j) {
                    $j->on('pp.Id_Session', '=', 'ps.Id_Session')->where('pp.Flag_Aktif', '=', 'Y');
                })
                ->whereIn('ps.No_Po_Sampel', $poSampelIds)
                ->where('ps.Kode_Aktivitas_Lab', 'PLT')
                ->select(
                    'ps.No_Po_Sampel',
                    'ps.Id_Session',
                    'ps.Status_Session',
                    DB::raw('COUNT(pp.Id_Pembanding) as jumlah_pembanding'),
                    DB::raw("STRING_AGG(pp.Nama_Pembanding, ', ') as nama_pembanding_list")
                )
                ->groupBy('ps.No_Po_Sampel', 'ps.Id_Session', 'ps.Status_Session')
                ->get()
                ->keyBy('No_Po_Sampel');

            $items = collect($items)->map(function ($item) use ($analisaDetails, $pltSessions) {
                $analisa = $analisaDetails->get($item->No_Po_Sampel, collect());
                $item->Total_Jenis_Analisa = $analisa->count();
                $item->Detail_Jenis_Analisa = $analisa->values()->toArray();

                $pltSession = $pltSessions->get($item->No_Po_Sampel);
                if ($pltSession && (int)$pltSession->jumlah_pembanding > 0) {
                    $item->plt_context = [
                        'ada_plt'           => true,
                        'jumlah_pembanding' => (int)$pltSession->jumlah_pembanding,
                        'nama_pembanding'   => $pltSession->nama_pembanding_list,
                        'status_session'    => $pltSession->Status_Session,
                        'session_final'     => $pltSession->Status_Session === 'F',
                    ];
                } else {
                    $adaPlt = $analisa->contains('Kode_Aktivitas_Lab', 'PLT');
                    $item->plt_context = $adaPlt ? ['ada_plt' => true, 'jumlah_pembanding' => 0] : null;
                }

                return $item;
            })->toArray();
        }

        return ResponseHelper::successWithPaginationV2(
            $items,
            $paginated->currentPage(),
            $paginated->perPage(),
            $paginated->total(),
            "Data Ditemukan",
            200,
            'v1'
        );
    }

    public function validasiHasilAkhirDariValidasiAwalJenisAnalisaV1($No_Po_Sampel)
    {
        $result = collect(
            DB::table('N_EMI_LAB_Uji_Sampel as us')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'us.Id_Jenis_Analisa', '=', 'ja.id')
                ->join('N_EMI_LAB_PO_Sampel as po', 'us.No_Po_Sampel', '=', 'po.No_Sampel')
                ->leftJoin('N_EMI_LAB_Palatabilitas_Pembanding as pb', 'us.Id_Pembanding', '=', 'pb.Id_Pembanding')
                ->select(
                    'us.*',
                    'ja.Jenis_Analisa',
                    'ja.Kode_Analisa',
                    'ja.Kode_Aktivitas_Lab',
                    'ja.Flag_Perhitungan',
                    'pb.Nama_Pembanding',
                    'pb.Kode_Barang_Pembanding'
                )
                ->where('us.No_Po_Sampel', $No_Po_Sampel)
                ->whereNull('us.Status')
                ->where('po.Flag_Trial_Produksi', 'Y')
                ->where(function ($q) {
                    $q->where(function ($inner) {
                        $inner->where('us.Flag_Selesai', 'Y')
                              ->where('us.Status_Keputusan_Sampel', 'terima');
                    })->orWhere('us.Flag_Final', 'Y');
                })
                ->where(function ($q) {
                    $q->where('us.Flag_Resampling', '!=', 'Y')
                      ->orWhereNull('us.Flag_Resampling');
                })
                ->orderByDesc('us.Tanggal')
                ->get()
        )->map(function ($item) {
            /** @var object $item */
            $item->Id_Jenis_Analisa = Hashids::connection('custom')->encode($item->Id_Jenis_Analisa);
            $item->is_plt = ($item->Kode_Aktivitas_Lab ?? null) === 'PLT';
            return $item;
        })->unique(function ($item) {
            return $item->No_Po_Sampel . '-' . $item->Id_Jenis_Analisa . '-' . ($item->Nama_Pembanding ?? '');
        })->values();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => "Data Ditemukan",
            'result' => $result
        ], 200);
    }

    public function store($no_sampel)
    {
        DB::beginTransaction();

        try {
            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow;
            $tanggalSqlServer = date('Y-m-d', strtotime($dt));
            $jamSqlServer = date('H:i:s', strtotime($dt));

            // Pastikan ini mengambil yang Flag_Trial_Produksi = 'Y'
            $getInformasiPo = DB::table('N_EMI_LAB_PO_Sampel')
                ->where('No_Sampel', $no_sampel)
                ->where('Flag_Trial_Produksi', 'Y')
                ->first();

            if (!$getInformasiPo) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Informasi PO tidak ditemukan.'
                ], 404);
            }

            $getData = DB::table('N_EMI_LAB_Uji_Sampel as us')
                ->join('N_EMI_LAB_PO_Sampel as ps', 'us.No_Po_Sampel', '=', 'ps.No_Sampel')
                ->select('us.*')
                ->where('us.No_Po_Sampel', $no_sampel)
                ->where('ps.Flag_Trial_Produksi', 'Y') 
                ->whereNull('us.Flag_Resampling')
                ->whereNull('us.Status')
                ->get();

            // ── Cek kelengkapan PLT (palatabilitas) ──────────────────────────────
            $pltSession = DB::table('N_EMI_LAB_Palatabilitas_Session')
                ->where('No_Po_Sampel', $no_sampel)
                ->where('Kode_Aktivitas_Lab', 'PLT')
                ->first();

            if ($pltSession) {
                // Ada PLT session → pastikan semua draft sudah difinalisasi ke Uji_Sampel
                $sisaDraftPlt = DB::table('N_EMI_LAB_Palatabilitas_Sementara')
                    ->where('Id_Session', $pltSession->Id_Session)
                    ->whereNull('Status')
                    ->count();

                if ($sisaDraftPlt > 0) {
                    return response()->json([
                        'success' => false,
                        'status'  => 422,
                        'message' => 'Masih ada ' . $sisaDraftPlt . ' data uji palatabilitas yang belum dikirim ke Uji Sampel. Selesaikan terlebih dahulu.',
                    ], 422);
                }

                // Pastikan semua slot (pembanding × analisa PLT) sudah terisi
                $pembandingList = DB::table('N_EMI_LAB_Palatabilitas_Pembanding')
                    ->where('Id_Session', $pltSession->Id_Session)
                    ->where('Flag_Aktif', 'Y')
                    ->get();

                $pltAnalisaIds = DB::table('N_EMI_LAB_Barang_Analisa as ba')
                    ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ba.Id_Jenis_Analisa', '=', 'ja.id')
                    ->where('ba.Kode_Barang', $getInformasiPo->Kode_Barang)
                    ->where('ba.Id_Master_Mesin', $getInformasiPo->Id_Mesin)
                    ->where('ba.Flag_Aktif', 'Y')
                    ->where('ba.Kode_Role', 'LAB')
                    ->where('ja.Kode_Aktivitas_Lab', 'PLT')
                    ->pluck('ja.id')
                    ->unique()
                    ->values()
                    ->all();

                if (!empty($pltAnalisaIds) && $pembandingList->isNotEmpty()) {
                    $totalSlot = count($pltAnalisaIds) * $pembandingList->count();
                    $sudahFinal = DB::table('N_EMI_LAB_Uji_Sampel')
                        ->where('No_Po_Sampel', $no_sampel)
                        ->where('Id_Session', $pltSession->Id_Session)
                        ->whereIn('Id_Jenis_Analisa', $pltAnalisaIds)
                        ->whereNull('Flag_Resampling')
                        ->count();

                    if ($sudahFinal < $totalSlot) {
                        return response()->json([
                            'success' => false,
                            'status'  => 422,
                            'message' => 'Data uji palatabilitas belum lengkap. Sudah ' . $sudahFinal . ' dari ' . $totalSlot . ' slot yang terisi.',
                        ], 422);
                    }
                }
            }
            // ── Akhir cek PLT ─────────────────────────────────────────────────────

            $kodeDikecualikan = DB::table('N_EMI_LAB_Jenis_Analisa_Opsional')
                ->pluck('Kode_Analisa')
                ->toArray();

            $checkedJumlahStandarMutu = DB::table('N_EMI_LAB_Barang_Analisa as ba')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ba.Id_Jenis_Analisa', '=', 'ja.id')
                ->select('ba.Id_Jenis_Analisa')
                ->where('ja.Kode_Role', 'LAB')
                ->where('ba.Kode_Role', 'LAB')
                ->where('ba.Flag_Aktif', 'Y')
                ->where('ja.Kode_Aktivitas_Lab', 'ANL')
                ->where('ba.Kode_Barang', $getInformasiPo->Kode_Barang)
                ->where('ba.Id_Master_Mesin', $getInformasiPo->Id_Mesin)
                ->whereNotIn('ja.Kode_Analisa', $kodeDikecualikan)
                ->groupBy('ba.Kode_Barang', 'ba.Id_Master_Mesin', 'ba.Id_Jenis_Analisa')
                ->pluck('ba.Id_Jenis_Analisa')
                ->toArray();

            $checkedJumlahAnalisa = DB::table('N_EMI_LAB_Uji_Sampel as us')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'us.Id_Jenis_Analisa', '=', 'ja.id')
                ->join('N_EMI_LAB_PO_Sampel as ps', 'us.No_Po_Sampel', '=', 'ps.No_Sampel')
                ->where('us.No_Po_Sampel', $no_sampel)
                ->where('ps.Flag_Trial_Produksi', 'Y')
                ->where('ja.Kode_Role', 'LAB')
                ->where('ja.Kode_Aktivitas_Lab', 'ANL')
                ->whereNull('us.Flag_Resampling')
                ->whereNull('us.Status')
                ->where('us.Status_Keputusan_Sampel', 'terima')
                ->whereNotIn('ja.Kode_Analisa', $kodeDikecualikan)
                ->pluck('us.Id_Jenis_Analisa')
                ->toArray();

            $analisaKurang = array_diff($checkedJumlahStandarMutu, $checkedJumlahAnalisa);
            if (!empty($analisaKurang)) {
                $detailKurang = DB::table('N_EMI_LAB_Jenis_Analisa')
                    ->whereIn('id', $analisaKurang)
                    ->pluck('Jenis_Analisa');

                return response()->json([
                    'success' => false, 'status' => 422,
                    'message' => 'Data analisa belum lengkap. Mohon lengkapi analisa berikut sebelum difinalisasi',
                    'detail' => ['No_Sampel' => $no_sampel, 'Analisa_Kurang' => $detailKurang]
                ], 422);
            }

            $belumSelesai = DB::table('N_EMI_LAB_Uji_Sampel as us')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'us.Id_Jenis_Analisa', '=', 'ja.id')
                ->join('N_EMI_LAB_PO_Sampel as ps', 'us.No_Po_Sampel', '=', 'ps.No_Sampel')
                ->where('us.No_Po_Sampel', $no_sampel)
                ->where('ps.Flag_Trial_Produksi', 'Y')
                ->where('ja.Kode_Role', 'LAB')
                ->where('ja.Kode_Aktivitas_Lab', 'ANL')
                ->where('us.Status_Keputusan_Sampel', 'terima')
                ->whereNull('us.Flag_Selesai')
                ->whereNull('us.Status')
                ->whereNull('us.Flag_Resampling')
                ->whereNotIn('ja.Kode_Analisa', $kodeDikecualikan)
                ->pluck('ja.Jenis_Analisa');

            if ($belumSelesai->count() > 0) {
                return response()->json([
                    'success' => false, 'status' => 422,
                    'message' => 'Masih ada analisa yang belum divalidasi.',
                    'detail' => ['No_Sampel' => $no_sampel, 'Analisa_Belum_Validasi' => $belumSelesai]
                ], 422);
            }

            // =========================================================
            // PERBAIKAN DIMULAI DARI SINI
            // =========================================================

            $allMesinIds = $getData->pluck('Id_Mesin')->unique()->values()->all();

            $validFgMesinIds = DB::table('EMI_Master_Mesin')
                ->whereIn('Id_Master_Mesin', $allMesinIds)
                ->where('Flag_FG', 'Y')
                ->pluck('Id_Master_Mesin')
                ->flip();

            $idJenisAnalisaList = [];
            $subPoToUpdate = [];

            foreach ($getData as $item) {
                if (isset($validFgMesinIds[$item->Id_Mesin])) {
                    $idJenisAnalisaList[] = $item->Id_Jenis_Analisa;

                    if (is_null($item->Status)) {
                        // FIX: Gunakan strtoupper agar array_unique tidak salah mendeteksi
                        $subPoToUpdate[] = strtoupper($item->No_Fak_Sub_Po);
                    }
                }
            }

            if (!empty($subPoToUpdate)) {
                DB::table('N_EMI_LAB_Uji_Sampel')
                    ->where('No_Po_Sampel', $no_sampel)
                    ->whereNull('Status')
                    ->whereNull('Flag_Resampling')
                    ->whereIn('No_Fak_Sub_Po', array_unique($subPoToUpdate))
                    ->update([
                        'Flag_Final' => 'Y'
                    ]);
            }

            $idJenisAnalisaList = array_unique($idJenisAnalisaList);

            if (!empty($idJenisAnalisaList)) {

                $adaYangTidakLayak = DB::table('N_EMI_LAB_Uji_Sampel as us')
                    ->join('N_EMI_LAB_PO_Sampel as ps', 'us.No_Po_Sampel', '=', 'ps.No_Sampel')
                    ->where('us.No_Po_Sampel', $no_sampel)
                    ->where('ps.Flag_Trial_Produksi', 'Y') 
                    ->whereIn('us.Id_Jenis_Analisa', $idJenisAnalisaList) 
                    ->where('us.Flag_Layak', 'T')   
                    ->whereNull('us.Status')          
                    ->whereNull('us.Flag_Resampling') 
                    ->exists();

                $flagOkValue = $adaYangTidakLayak ? 'T' : 'Y';

                // FIX: Gunakan updateOrInsert (Jurus Paksa) agar data PASTI MASUK, 
                // jika belum ada akan di-insert, jika sudah ada tapi nyangkut akan ditimpa (update)
                DB::table('N_EMI_LAB_Hasil_Uji_Validasi_Final')->updateOrInsert(
                    [
                        // Pengecekan unik (Harus menyertakan No_Sampel)
                        'No_Split_Po' => $getInformasiPo->No_Split_Po,
                        'No_Batch'    => $getInformasiPo->No_Batch,
                        'No_Sampel'   => $no_sampel 
                    ],
                    [
                        // Data Payload
                        'No_Po'       => $getInformasiPo->No_Po,
                        'Tanggal'     => $tanggalSqlServer,
                        'Jam'         => $jamSqlServer,
                        'Flag_FG'     => 'Y', // Hardcoded seperti pada kode awal Anda
                        'Flag_Ok'     => $flagOkValue,
                        'Id_User'     => Auth::user()->UserId
                    ]
                );

                DB::table('N_EMI_LAB_PO_Sampel')
                    ->where('No_Sampel', $no_sampel)
                    ->where('Flag_Trial_Produksi', 'Y')
                    ->update([
                        'Flag_Selesai' => 'Y'
                    ]);

                $logId = DB::table('N_EMI_LAB_Log_Aksi')->insertGetId([
                    'No_Sampel'  => $no_sampel,
                    'No_Po'      => $getInformasiPo->No_Po       ?? '-',
                    'No_Split_Po'=> $getInformasiPo->No_Split_Po ?? '-',
                    'Kode_Barang'=> $getInformasiPo->Kode_Barang ?? null,
                    'Flag_Trial' => 'Y',
                    'Jenis_Aksi' => 'FINALISASI_TRIAL_PRODUKSI',
                    'Sub_Aksi'   => 'CLOSE',
                    'Id_User'    => Auth::user()->UserId,
                    'Tanggal'    => $tanggalSqlServer,
                    'Jam'        => $jamSqlServer,
                ]);

                $jaNameMap = DB::table('N_EMI_LAB_Jenis_Analisa')
                    ->whereIn('id', $idJenisAnalisaList)
                    ->pluck('Jenis_Analisa', 'id');
                $detailRows = array_map(fn($jaId) => [
                    'Id_Log_Aksi'        => $logId,
                    'Id_Jenis_Analisa'   => $jaId,
                    'Nama_Jenis_Analisa' => $jaNameMap[$jaId] ?? null,
                    'Flag_Layak'         => null,
                    'Tanggal'            => $tanggalSqlServer,
                    'Jam'                => $jamSqlServer,
                    'Id_User'            => Auth::user()->UserId,
                ], $idJenisAnalisaList);
                if (!empty($detailRows)) {
                    DB::table('N_EMI_LAB_Log_Aksi_Detail')->insert($detailRows);
                }
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Data Berhasil Disimpan"
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('FinalisasiLabProduksiTrialController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan di Baris " . $e->getLine() . " - " . $e->getMessage()
            ], 500);
        }
    }

    public function storeBulk(Request $request)
    {
        $no_sampel_list = $request->input('no_sampel_list', []);

        if (empty($no_sampel_list)) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Tidak ada data sampel yang dipilih.'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $waktuServer      = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt               = $waktuServer[0]->DateTimeNow;
            $tanggalSqlServer = date('Y-m-d', strtotime($dt));
            $jamSqlServer     = date('H:i:s', strtotime($dt));

            $kodeDikecualikan = DB::table('N_EMI_LAB_Jenis_Analisa_Opsional')
                ->pluck('Kode_Analisa')
                ->toArray();

            $masterAnalisa = DB::table('N_EMI_LAB_Jenis_Analisa')
                ->pluck('Jenis_Analisa', 'id')
                ->toArray();

            $poSampels = DB::table('N_EMI_LAB_PO_Sampel')
                ->whereIn('No_Sampel', $no_sampel_list)
                ->where('Flag_Trial_Produksi', 'Y')
                ->get()
                ->keyBy('No_Sampel');

            $ujiSampels = DB::table('N_EMI_LAB_Uji_Sampel as us')
                ->join('N_EMI_LAB_PO_Sampel as ps', 'us.No_Po_Sampel', '=', 'ps.No_Sampel')
                ->select('us.*', 'ps.Kode_Barang', 'ps.Id_Mesin')
                ->whereIn('us.No_Po_Sampel', $no_sampel_list)
                ->where('ps.Flag_Trial_Produksi', 'Y')
                ->whereNull('us.Flag_Resampling')
                ->whereNull('us.Status')
                ->get()
                ->groupBy('No_Po_Sampel');

            $kodes  = $poSampels->pluck('Kode_Barang')->unique()->toArray();
            $mesins = $poSampels->pluck('Id_Mesin')->unique()->toArray();

            $stdMutuGrouped = DB::table('N_EMI_LAB_Barang_Analisa as ba')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ba.Id_Jenis_Analisa', '=', 'ja.id')
                ->where('ja.Kode_Role', 'LAB')
                ->where('ba.Kode_Role', 'LAB')
                ->where('ba.Flag_Aktif', 'Y')
                ->where('ja.Kode_Aktivitas_Lab', 'ANL')
                ->whereIn('ba.Kode_Barang', $kodes)
                ->whereIn('ba.Id_Master_Mesin', $mesins)
                ->whereNotIn('ja.Kode_Analisa', $kodeDikecualikan)
                ->select('ba.Kode_Barang', 'ba.Id_Master_Mesin', 'ba.Id_Jenis_Analisa')
                ->get()
                ->groupBy(fn($i) => $i->Kode_Barang . '_' . $i->Id_Master_Mesin);

            $ujiAnalisaGrouped = DB::table('N_EMI_LAB_Uji_Sampel as us')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'us.Id_Jenis_Analisa', '=', 'ja.id')
                ->join('N_EMI_LAB_PO_Sampel as ps', 'us.No_Po_Sampel', '=', 'ps.No_Sampel')
                ->whereIn('us.No_Po_Sampel', $no_sampel_list)
                ->where('ps.Flag_Trial_Produksi', 'Y')
                ->where('ja.Kode_Role', 'LAB')
                ->where('ja.Kode_Aktivitas_Lab', 'ANL')
                ->whereNull('us.Flag_Resampling')
                ->whereNull('us.Status')
                ->where('us.Status_Keputusan_Sampel', 'terima')
                ->whereNotIn('ja.Kode_Analisa', $kodeDikecualikan)
                ->select('us.No_Po_Sampel', 'us.Id_Jenis_Analisa')
                ->get()
                ->groupBy('No_Po_Sampel');

            $belumSelesaiGrouped = DB::table('N_EMI_LAB_Uji_Sampel as us')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'us.Id_Jenis_Analisa', '=', 'ja.id')
                ->join('N_EMI_LAB_PO_Sampel as ps', 'us.No_Po_Sampel', '=', 'ps.No_Sampel')
                ->whereIn('us.No_Po_Sampel', $no_sampel_list)
                ->where('ps.Flag_Trial_Produksi', 'Y')
                ->where('ja.Kode_Role', 'LAB')
                ->where('ja.Kode_Aktivitas_Lab', 'ANL')
                ->where(fn($q) => $q->where('us.Status_Keputusan_Sampel', 'terima')
                    ->orWhereNull('us.Status_Keputusan_Sampel'))
                ->whereNull('us.Flag_Selesai')
                ->whereNull('us.Status')
                ->whereNull('us.Flag_Resampling')
                ->select('us.No_Po_Sampel', 'ja.Jenis_Analisa')
                ->get()
                ->groupBy('No_Po_Sampel');

            $mesinFg = DB::table('EMI_Master_Mesin')
                ->whereIn('Id_Master_Mesin', $mesins)
                ->pluck('Flag_FG', 'Id_Master_Mesin')
                ->toArray();

            $tidakLayakData = DB::table('N_EMI_LAB_Uji_Sampel as us')
                ->join('N_EMI_LAB_PO_Sampel as ps', 'us.No_Po_Sampel', '=', 'ps.No_Sampel')
                ->whereIn('us.No_Po_Sampel', $no_sampel_list)
                ->where('ps.Flag_Trial_Produksi', 'Y')
                ->where('us.Flag_Layak', 'T')
                ->whereNull('us.Status')
                ->whereNull('us.Flag_Resampling')
                ->select('us.No_Po_Sampel', 'us.Id_Jenis_Analisa')
                ->get()
                ->groupBy('No_Po_Sampel');

            $poSampelUpdateCases = [];
            $berhasil            = [];
            $gagal               = [];
            $userId              = Auth::user()->UserId;

            foreach ($no_sampel_list as $no_sampel) {
                $infoPo = $poSampels->get($no_sampel);

                if (!$infoPo) {
                    $gagal[] = [
                        'sampel' => $no_sampel,
                        'reason' => 'PO tidak ditemukan atau bukan Trial Produksi.'
                    ];
                    continue;
                }

                $keyStd = $infoPo->Kode_Barang . '_' . $infoPo->Id_Mesin;

                $stdMutu   = $stdMutuGrouped->has($keyStd)
                    ? $stdMutuGrouped->get($keyStd)->pluck('Id_Jenis_Analisa')->toArray()
                    : [];

                $analisaUji = $ujiAnalisaGrouped->has($no_sampel)
                    ? $ujiAnalisaGrouped->get($no_sampel)->pluck('Id_Jenis_Analisa')->toArray()
                    : [];

                $analisaKurang = array_diff($stdMutu, $analisaUji);
                if (!empty($analisaKurang)) {
                    $namaKurang = array_map(fn($id) => $masterAnalisa[$id] ?? 'Unknown', $analisaKurang);
                    $gagal[] = [
                        'sampel' => $no_sampel,
                        'reason' => 'Analisa standar mutu belum terpenuhi: ' . implode(', ', $namaKurang)
                    ];
                    continue;
                }

                if ($belumSelesaiGrouped->has($no_sampel)) {
                    $namaBelumValid = $belumSelesaiGrouped->get($no_sampel)->pluck('Jenis_Analisa')->toArray();
                    $gagal[] = [
                        'sampel' => $no_sampel,
                        'reason' => 'Masih ada analisa yang belum divalidasi: ' . implode(', ', $namaBelumValid)
                    ];
                    continue;
                }

                $listUji            = $ujiSampels->get($no_sampel) ?? collect();
                $flagFgValid        = false;
                $idJenisAnalisaList = [];
                $subPoToUpdate      = [];

                foreach ($listUji as $item) {
                    if (($mesinFg[$item->Id_Mesin] ?? null) === 'Y') {
                        $flagFgValid          = true;
                        $idJenisAnalisaList[] = $item->Id_Jenis_Analisa;
                        if (is_null($item->Status)) {
                            $subPoToUpdate[] = strtoupper($item->No_Fak_Sub_Po);
                        }
                    }
                }

                if (!$flagFgValid) {
                    $gagal[] = [
                        'sampel' => $no_sampel,
                        'reason' => 'Mesin tidak memiliki Flag_FG valid.'
                    ];
                    continue;
                }

                $idJenisAnalisaList = array_unique($idJenisAnalisaList);
                $subPoToUpdate      = array_unique($subPoToUpdate);

                if (!empty($subPoToUpdate)) {
                    DB::table('N_EMI_LAB_Uji_Sampel')
                        ->where('No_Po_Sampel', $no_sampel)
                        ->whereNull('Status')
                        ->whereNull('Flag_Resampling')
                        ->whereIn('No_Fak_Sub_Po', $subPoToUpdate)
                        ->update(['Flag_Final' => 'Y']);
                }

                $tidakLayakDataSampel = $tidakLayakData->get($no_sampel) ?? collect();
                $adaYangTidakLayak    = collect($tidakLayakDataSampel)
                    ->contains(fn($tl) => in_array($tl->Id_Jenis_Analisa, $idJenisAnalisaList));

                $flagOkValue = $adaYangTidakLayak ? 'T' : 'Y';

                DB::table('N_EMI_LAB_Hasil_Uji_Validasi_Final')
                    ->updateOrInsert(
                        [
                            'No_Split_Po' => $infoPo->No_Split_Po,
                            'No_Batch'    => $infoPo->No_Batch,
                            'No_Sampel'   => $no_sampel,
                        ],
                        [
                            'No_Po'   => $infoPo->No_Po,
                            'Tanggal' => $tanggalSqlServer,
                            'Jam'     => $jamSqlServer,
                            'Flag_FG' => 'Y',
                            'Flag_Ok' => $flagOkValue,
                            'Id_User' => $userId,
                        ]
                    );

                $logId = DB::table('N_EMI_LAB_Log_Aksi')->insertGetId([
                    'No_Sampel'  => $no_sampel,
                    'No_Po'      => $infoPo->No_Po       ?? '-',
                    'No_Split_Po'=> $infoPo->No_Split_Po ?? '-',
                    'Kode_Barang'=> $infoPo->Kode_Barang ?? null,
                    'Flag_Trial' => 'Y',
                    'Jenis_Aksi' => 'FINALISASI_TRIAL_PRODUKSI',
                    'Sub_Aksi'   => 'CLOSE',
                    'Id_User'    => $userId,
                    'Tanggal'    => $tanggalSqlServer,
                    'Jam'        => $jamSqlServer,
                ]);

                $detailRows = array_map(fn($jaId) => [
                    'Id_Log_Aksi'        => $logId,
                    'Id_Jenis_Analisa'   => $jaId,
                    'Nama_Jenis_Analisa' => $masterAnalisa[$jaId] ?? null,
                    'Flag_Layak'         => null,
                    'Tanggal'            => $tanggalSqlServer,
                    'Jam'                => $jamSqlServer,
                    'Id_User'            => $userId,
                ], $idJenisAnalisaList);
                if (!empty($detailRows)) {
                    DB::table('N_EMI_LAB_Log_Aksi_Detail')->insert($detailRows);
                }

                $poSampelUpdateCases[] = $no_sampel;
                $berhasil[]            = $no_sampel;
            }

            if (!empty($poSampelUpdateCases)) {
                DB::table('N_EMI_LAB_PO_Sampel')
                    ->whereIn('No_Sampel', $poSampelUpdateCases)
                    ->where('Flag_Trial_Produksi', 'Y')
                    ->update(['Flag_Selesai' => 'Y']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Proses Finalisasi Bulk Trial Produksi Selesai.',
                'result'  => ['berhasil' => $berhasil, 'gagal' => $gagal]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('FinalisasiLabProduksiTrialController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Terjadi kesalahan sistem di Baris ' . $e->getLine() . ': ' . $e->getMessage()
            ], 500);
        }
    }
}
