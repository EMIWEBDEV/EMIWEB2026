<?php

namespace App\Http\Controllers\FormulatorValidasiHirarki;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Vinkla\Hashids\Facades\Hashids;

class FormulatorValidasiHirarkiController extends Controller
{
    public function index()
    {
        return inertia("vue/dashboard/lab/formulator/validasi-hirarki/ValidasiHirarkiHome");
    }
    public function getViewBatal()
    {
        return inertia("vue/dashboard/lab/formulator/hasil-analisis/HasilAnalisaDibatalkan");
    }

    public function getKlasifikasiAktivitasLab()
    {
        try {
            $data = DB::table('N_EMI_LIMS_Klasifikasi_Aktivitas_Lab')
                ->orderBy('Urutan', 'ASC')
                ->get();

            if ($data->isEmpty()) {
                return ResponseHelper::error('Data klasifikasi tidak ditemukan.', 404);
            }
            return ResponseHelper::success($data, "Data Klasifikasi Ditemukan !", 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error('Terjadi kesalahan pada server', 500);
        }
    }

    public function getDataValidasi(Request $request)
    {
        try {
            $search = $request->query('search', '');
            $page = (int) $request->query('page', 1);
            $limit = (int) $request->query('limit', 5);

            $query = DB::table('N_EMI_LIMS_Uji_Sampel as U')
                ->join('N_EMI_LAB_Jenis_Analisa as A', 'U.Id_Jenis_Analisa', '=', 'A.id')
                ->join('N_EMI_LIMS_Klasifikasi_Aktivitas_Lab as B', 'A.Kode_Aktivitas_Lab', '=', 'B.Kode_Aktivitas_Lab')
                ->leftJoin('N_EMI_LIMS_Uji_Pra_Final as UPF', 'U.No_Po_Sampel', '=', 'UPF.No_Sampel')
                ->select(
                    'U.No_Po_Sampel',
                    DB::raw("
                        CASE 
                            WHEN SUM(CASE WHEN U.Flag_Approval IS NOT NULL THEN 1 ELSE 0 END) > 0 THEN 1 
                            ELSE 0 
                        END as has_validasi
                    "),
                    DB::raw("
                        CASE 
                            WHEN SUM(CASE WHEN B.Kode_Aktivitas_Lab = 'LCKV' THEN 1 ELSE 0 END) = 0 THEN 'TIDAK ADA'
                            WHEN SUM(CASE WHEN B.Kode_Aktivitas_Lab = 'LCKV' AND U.Flag_Approval = 'T' THEN 1 ELSE 0 END) > 0 THEN 'DITOLAK'
                            WHEN SUM(CASE WHEN B.Kode_Aktivitas_Lab = 'LCKV' AND U.Flag_Approval = 'Y' THEN 1 ELSE 0 END) > 0 THEN 'DISETUJUI'
                            ELSE 'MENUNGGU VALIDASI'
                        END as status_lock_view
                    "),
                    DB::raw("
                        CASE 
                            WHEN SUM(CASE WHEN B.Kode_Aktivitas_Lab = 'ANL' THEN 1 ELSE 0 END) = 0 THEN 'TIDAK ADA'
                            WHEN SUM(CASE WHEN B.Kode_Aktivitas_Lab = 'LCKV' THEN 1 ELSE 0 END) > 0 AND SUM(CASE WHEN B.Kode_Aktivitas_Lab = 'LCKV' AND U.Flag_Approval IS NOT NULL THEN 1 ELSE 0 END) = 0 THEN 'MENUNGGU LOCK VIEW'
                            WHEN SUM(CASE WHEN B.Kode_Aktivitas_Lab = 'ANL' AND U.Flag_Approval = 'T' THEN 1 ELSE 0 END) > 0 THEN 'DITOLAK'
                            WHEN SUM(CASE WHEN B.Kode_Aktivitas_Lab = 'ANL' AND U.Flag_Approval = 'Y' THEN 1 ELSE 0 END) > 0 THEN 'DISETUJUI'
                            ELSE 'MENUNGGU VALIDASI'
                        END as status_analisa_lab
                    "),
                    DB::raw("
                        CASE 
                            WHEN SUM(CASE WHEN B.Kode_Aktivitas_Lab = 'PLT' THEN 1 ELSE 0 END) = 0 THEN 'TIDAK ADA'
                            WHEN SUM(CASE WHEN B.Kode_Aktivitas_Lab = 'ANL' AND U.Flag_Approval = 'T' THEN 1 ELSE 0 END) > 0 THEN 'TERKUNCI'
                            WHEN SUM(CASE WHEN B.Kode_Aktivitas_Lab = 'LCKV' THEN 1 ELSE 0 END) > 0 AND SUM(CASE WHEN B.Kode_Aktivitas_Lab = 'LCKV' AND U.Flag_Approval IS NOT NULL THEN 1 ELSE 0 END) = 0 THEN 'MENUNGGU LOCK VIEW'
                            WHEN SUM(CASE WHEN B.Kode_Aktivitas_Lab = 'ANL' THEN 1 ELSE 0 END) > 0 AND SUM(CASE WHEN B.Kode_Aktivitas_Lab = 'ANL' AND U.Flag_Approval IS NOT NULL THEN 1 ELSE 0 END) = 0 THEN 'MENUNGGU HASIL UJI LAB'
                            WHEN SUM(CASE WHEN B.Kode_Aktivitas_Lab = 'PLT' AND U.Flag_Approval = 'T' THEN 1 ELSE 0 END) > 0 THEN 'DITOLAK'
                            WHEN SUM(CASE WHEN B.Kode_Aktivitas_Lab = 'PLT' AND U.Flag_Approval = 'Y' THEN 1 ELSE 0 END) > 0 THEN 'DISETUJUI'
                            ELSE 'MENUNGGU VALIDASI'
                        END as status_palatabilitas
                    ")
                )
                ->where('U.Flag_Selesai', 'Y')
                ->whereNull('U.Flag_Resampling')
                ->whereNull('UPF.No_Sampel');

            if (!empty($search)) {
                $query->where('U.No_Po_Sampel', 'LIKE', '%' . $search . '%');
            }

            $query->groupBy('U.No_Po_Sampel');

            $totalQuery = DB::table(DB::raw("({$query->toSql()}) as sub"))->mergeBindings($query);
            $total = $totalQuery->count();

            if ($total == 0) {
                return ResponseHelper::error('Data tidak ditemukan.', 404);
            }

            $offset = ($page - 1) * $limit;
            
            $getData = $query->orderBy('U.No_Po_Sampel', 'DESC')
                ->offset($offset)
                ->limit($limit)
                ->get();

            return ResponseHelper::successWithPaginationV2(
                $getData,
                $page,
                $limit,
                $total,
                "Data Ditemukan",
                200
            );
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error('Terjadi kesalahan pada server', 500);
        }
    }

    public function getDetailValidasi($no_po_sampel)
    {
        try {
            $kodeRoles = collect(Session::get('User_Roles', []))->pluck('Kode_Role')->toArray();

            $sampel = DB::table('N_LIMS_PO_Sampel')
                ->where('No_Sampel', $no_po_sampel)
                ->select('Kode_Barang', 'Id_Mesin as Id_Master_Mesin')
                ->first();

            $kodeBarang = $sampel->Kode_Barang ?? null;
            $idMasterMesin = $sampel->Id_Master_Mesin ?? null;

            $masterAnalisa = collect();
            if ($kodeBarang && $idMasterMesin) {
                $masterAnalisa = DB::table('N_EMI_LAB_Barang_Analisa as BA')
                    ->join('N_EMI_LAB_Jenis_Analisa as JA', 'BA.Id_Jenis_Analisa', '=', 'JA.id')
                    ->where('BA.Kode_Barang', $kodeBarang)
                    ->where('BA.Id_Master_Mesin', $idMasterMesin)
                    ->where('BA.Kode_Role', 'FLM')
                    ->select('JA.Kode_Aktivitas_Lab', 'BA.Id_Jenis_Analisa', 'JA.Jenis_Analisa')
                    ->groupBy('JA.Kode_Aktivitas_Lab', 'BA.Id_Jenis_Analisa', 'JA.Jenis_Analisa')
                    ->get();
            }

            // 🔥 AMBIL FILE SEKALI (bukan join rusak)
            $fileGlobal = DB::table('N_EMI_LIMS_Berkas_Uji_Lab')
                ->where('No_Sampel', $no_po_sampel)
                ->first();

            $data = DB::table('N_EMI_LIMS_Uji_Sampel as U')
                ->join('N_EMI_LAB_Jenis_Analisa as A', 'U.Id_Jenis_Analisa', '=', 'A.id')
                ->join('N_EMI_LIMS_Klasifikasi_Aktivitas_Lab as B', 'A.Kode_Aktivitas_Lab', '=', 'B.Kode_Aktivitas_Lab')
                ->leftJoin('N_EMI_LAB_Standar_Rentang_Non_Perhitungan as SR', function($join) use ($kodeRoles) {
                    $join->on('U.Id_Jenis_Analisa', '=', 'SR.Id_Jenis_Analisa')
                        ->on(DB::raw('CAST(U.Hasil AS VARCHAR(255))'), '=', DB::raw('CAST(SR.Nilai_Kriteria AS VARCHAR(255))'))
                        ->where('SR.Flag_Aktif', '=', 'Y')
                        ->whereIn('SR.Kode_Role', $kodeRoles);
                })
                ->leftJoin('N_EMI_LAB_Perhitungan as P', 'U.Id_Jenis_Analisa', '=', 'P.Id_Jenis_Analisa')
                ->select(
                    'U.No_Fak_Sub_Po',
                    'U.No_Po_Sampel',
                    'U.Id_Jenis_Analisa',
                    'U.Hasil',
                    'U.Status_Keputusan_Sampel',
                    'U.Flag_Approval',
                    'U.Tahapan_Ke',
                    'U.Flag_Foto as Flag_Foto', // ✅ FIX
                    'A.Kode_Analisa',
                    'A.Jenis_Analisa',
                    'A.Flag_Perhitungan',
                    'B.Kode_Aktivitas_Lab',
                    'B.Nama_Aktivitas',
                    'B.Urutan',
                    'SR.Keterangan_Kriteria',
                    'P.Hasil_Perhitungan as Digit_Desimal'
                )
                ->where('U.No_Po_Sampel', $no_po_sampel)
                ->where('U.Flag_Selesai', 'Y')
                ->whereNull('U.Flag_Resampling')
                ->orderBy('B.Urutan', 'ASC')
                ->get();

            $allSteps = DB::table('N_EMI_LIMS_Klasifikasi_Aktivitas_Lab')
                ->orderBy('Urutan', 'ASC')
                ->get();

            $arrayRentang = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->where('Flag_Aktif', 'Y')
                ->whereIn('Kode_Role', $kodeRoles)
                ->select('Id_Jenis_Analisa', 'Nilai_Kriteria', 'Keterangan_Kriteria')
                ->get()
                ->groupBy('Id_Jenis_Analisa');

            $groupedData = [];
            $stepStatuses = [];

            foreach ($allSteps as $step) {
                $items = $data->where('Kode_Aktivitas_Lab', $step->Kode_Aktivitas_Lab)->values();
                $requiredForThisStep = $masterAnalisa->where('Kode_Aktivitas_Lab', $step->Kode_Aktivitas_Lab);
                $pendingAnalisaNames = [];

                foreach ($requiredForThisStep as $req) {
                    if (!$items->contains('Id_Jenis_Analisa', $req->Id_Jenis_Analisa)) {
                        $pendingAnalisaNames[] = $req->Jenis_Analisa;
                    }
                }

                $statusStep = 'TIDAK ADA';

                if ($items->isNotEmpty() || count($pendingAnalisaNames) > 0) {

                    if ($items->isNotEmpty()) {
                        $items->transform(function ($item) use ($arrayRentang, $fileGlobal) {

                            // 🔥 FIX GCS
                            if ($item->Flag_Foto === 'Y' && !empty($fileGlobal->File_Path)) {
                                try {
                                    $item->File_Url = Storage::disk('gcs')->temporaryUrl(
                                        $fileGlobal->File_Path,
                                        now()->addMinutes(120)
                                    );
                                } catch (\Exception $e) {
                                    Log::error('Gagal generate URL GCS: ' . $e->getMessage());
                                    $item->File_Url = null;
                                }
                            } else {
                                $item->File_Url = null; // ❌ jangan spam log
                            }

                            // ===== NORMALISASI HASIL =====
                            $hasilStr = (string)$item->Hasil;
                            $cleanHasil = $hasilStr;

                            if (preg_match('/^-?\d+\.0+$/', $cleanHasil)) {
                                $cleanHasil = explode('.', $cleanHasil)[0];
                            }

                            $isOpsiRentang = false;

                            if (isset($arrayRentang[$item->Id_Jenis_Analisa])) {
                                $matched = $arrayRentang[$item->Id_Jenis_Analisa]
                                    ->firstWhere('Nilai_Kriteria', $cleanHasil);

                                if ($matched) {
                                    $item->Hasil = $matched->Keterangan_Kriteria;
                                    $isOpsiRentang = true;
                                }
                            }

                            if (!$isOpsiRentang) {
                                if (!empty($item->Keterangan_Kriteria)) {
                                    $item->Hasil = $item->Keterangan_Kriteria;
                                } elseif (is_numeric($item->Hasil)) {
                                    if ($item->Flag_Perhitungan === 'Y' || $item->Digit_Desimal !== null) {
                                        $digit = is_numeric($item->Digit_Desimal) ? (int)$item->Digit_Desimal : 2;
                                        $item->Hasil = number_format((float)$item->Hasil, $digit, '.', '');
                                    } else {
                                        $item->Hasil = $hasilStr;
                                    }
                                }
                            }

                            return $item;
                        });
                    }

                    $approvedItems = $items->where('Flag_Approval', 'Y')->count();
                    $rejectedItems = $items->where('Flag_Approval', 'T')->count();
                    $validatedItems = $approvedItems + $rejectedItems;

                    $dependencyCode = $step->Butuh_Hasil_Dari;
                    $isDependencyMet = true;

                    if ($dependencyCode) {
                        $depStatus = $stepStatuses[$dependencyCode] ?? 'TIDAK ADA';

                        if ($step->Kode_Aktivitas_Lab === 'PLT' && $dependencyCode === 'ANL') {
                            if ($depStatus !== 'DISETUJUI') $isDependencyMet = false;
                        } else {
                            if (!in_array($depStatus, ['DISETUJUI', 'DITOLAK'])) {
                                $isDependencyMet = false;
                            }
                        }
                    }

                    if (!$isDependencyMet) {
                        $statusStep = 'TERKUNCI';
                    } else {
                        if ($validatedItems > 0) {
                            $statusStep = ($rejectedItems > 0) ? 'DITOLAK' : 'DISETUJUI';
                        } else {
                            $statusStep = 'MENUNGGU VALIDASI';
                        }
                    }
                }

                $stepStatuses[$step->Kode_Aktivitas_Lab] = $statusStep;

                if ($items->isNotEmpty() || count($pendingAnalisaNames) > 0) {
                    $groupedData[] = [
                        'Urutan' => $step->Urutan,
                        'Kode_Aktivitas_Lab' => $step->Kode_Aktivitas_Lab,
                        'Nama_Aktivitas' => $step->Nama_Aktivitas,
                        'status_step' => $statusStep,
                        'pending_analisa' => $pendingAnalisaNames,
                        'data_analisa' => $items
                    ];
                }
            }

            if (empty($groupedData)) {
                return ResponseHelper::error('Data tidak ditemukan.', 404);
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Detail Data Ditemukan',
                'result' => [
                    'No_Po_Sampel' => $no_po_sampel,
                    'steps' => $groupedData
                ]
            ]);

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error('Terjadi kesalahan pada server', 500);
        }
    }

    public function getInformasiDesktop(Request $request)
    {
        try {
            $search = $request->query('search', '');
            $page = (int) $request->query('page', 1);
            $limit = (int) $request->query('limit', 10);

            // 1. QUERY MASTER (GROUP BY SPLIT & MESIN) + JOIN MESIN & BARANG
            $query = DB::table('N_LIMS_PO_Sampel as PS')
                ->leftJoin('EMI_Master_Mesin as MM', 'PS.Id_Mesin', '=', 'MM.Id_Master_Mesin')
                ->leftJoin(DB::raw('(SELECT Kode_Barang, MAX(Nama) as Nama_Barang FROM N_EMI_View_Barang GROUP BY Kode_Barang) as VB'), 'PS.Kode_Barang', '=', 'VB.Kode_Barang')
                ->select(
                    'PS.No_Po',
                    'PS.No_Split_Po',
                    'PS.Kode_Barang',
                    'VB.Nama_Barang',
                    'MM.Nama_Mesin',
                    'PS.Id_Mesin', // Tambahkan Id_Mesin untuk mapping detail
                    DB::raw("MAX(PS.Tanggal_Validasi_Formulator_Desktop) as Last_Update_Tanggal"),
                    DB::raw("MAX(PS.Jam_Validasi_Formulator_Desktop) as Last_Update_Jam"),
                    DB::raw("MAX(PS.Id_User_Validasi_Formulator_Desktop) as Validasi_Oleh"),
                    DB::raw("SUM(CASE WHEN PS.Flag_Validasi_Formulator_Desktop = 'Y' THEN 1 ELSE 0 END) as count_y"),
                    DB::raw("COUNT(PS.No_Sampel) as total_sampel")
                );

            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('PS.No_Split_Po', 'LIKE', '%' . $search . '%')
                      ->orWhere('PS.No_Po', 'LIKE', '%' . $search . '%')
                      ->orWhere('VB.Nama_Barang', 'LIKE', '%' . $search . '%')
                      ->orWhere('MM.Nama_Mesin', 'LIKE', '%' . $search . '%');
                });
            }

            $query->groupBy('PS.No_Po', 'PS.No_Split_Po', 'PS.Kode_Barang', 'VB.Nama_Barang', 'MM.Nama_Mesin', 'PS.Id_Mesin');

            $totalQuery = DB::table(DB::raw("({$query->toSql()}) as sub"))->mergeBindings($query);
            $total = $totalQuery->count();

            if ($total == 0) {
                return ResponseHelper::error('Data tidak ditemukan.', 404);
            }

            $offset = ($page - 1) * $limit;
            
            $getData = $query->orderBy('PS.No_Split_Po', 'DESC')
                ->offset($offset)
                ->limit($limit)
                ->get();

            // 2. QUERY DETAIL (AMBIL ANAK-ANAK SAMPELNYA)
            // Kumpulkan ID unik berupa gabungan No_Split_Po dan Id_Mesin
            $splitMesinKeys = $getData->map(function ($item) {
                return $item->No_Split_Po . '_' . $item->Id_Mesin;
            })->toArray();
            
            // Ambil hanya No_Split_Po-nya saja untuk query In
            $splitPos = $getData->pluck('No_Split_Po')->unique()->toArray();

            // Ambil data dari database
            $detailSampelData = DB::table('N_LIMS_PO_Sampel')
                ->whereIn('No_Split_Po', $splitPos)
                ->select('No_Split_Po', 'Id_Mesin', 'No_Sampel', 'Flag_Validasi_Formulator_Desktop', 'Tanggal_Validasi_Formulator_Desktop', 'Jam_Validasi_Formulator_Desktop', 'Id_User_Validasi_Formulator_Desktop')
                ->orderBy('No_Sampel', 'ASC')
                ->get();
                
            // Grouping manual dengan key gabungan: No_Split_Po_Id_Mesin
            $detailSampel = collect();
            foreach ($detailSampelData as $detail) {
                $key = $detail->No_Split_Po . '_' . $detail->Id_Mesin;
                if (!isset($detailSampel[$key])) {
                    $detailSampel[$key] = collect();
                }
                $detailSampel[$key]->push($detail);
            }

            // 3. GABUNGKAN DATA MASTER DENGAN DETAIL
            $getData->transform(function ($item) use ($detailSampel) {
                if ($item->count_y == 0) {
                    $item->Status_Desktop = 'Belum Diterima';
                } elseif ($item->count_y < $item->total_sampel) {
                    $item->Status_Desktop = 'Diterima Sebagian';
                } else {
                    $item->Status_Desktop = 'Selesai Diterima';
                }
                
                // Gunakan key gabungan untuk mencari anak-anaknya
                $itemKey = $item->No_Split_Po . '_' . $item->Id_Mesin;
                $item->Detail_Sampel = $detailSampel->get($itemKey, collect())->toArray();
                return $item;
            });

            return ResponseHelper::successWithPaginationV2(
                $getData,
                $page,
                $limit,
                $total,
                "Data Desktop Ditemukan",
                200
            );
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ResponseHelper::error('Terjadi kesalahan pada server', 500);
        }
    }

    public function storeValidasiHirarki(Request $request)
    {
        $pengguna = Auth::user();

        $request->validate([
            'No_Po_Sampel' => 'required',
            'Kode_Aktivitas_Lab' => 'required',
            'Status_Action' => 'required|in:setuju,tolak',
            'Alasan' => 'required_if:Status_Action,tolak|nullable|min:8',
            'Items' => 'required|array',
            'Items.*.No_Fak_Sub_Po' => 'nullable'
        ]);

        DB::beginTransaction();

        try {
            $noPo = $request->No_Po_Sampel;
            $kodeAktivitas = $request->Kode_Aktivitas_Lab;
            $isTolak = $request->Status_Action === 'tolak';

            foreach ($request->Items as $item) {
                $noSub = $item['No_Fak_Sub_Po'] ?? null;

                $query = DB::table("N_EMI_LIMS_Uji_Sampel as U")
                    ->join('N_EMI_LAB_Jenis_Analisa as A', 'U.Id_Jenis_Analisa', '=', 'A.id')
                    ->where('U.No_Po_Sampel', $noPo)
                    ->where('A.Kode_Aktivitas_Lab', $kodeAktivitas);

                if (!empty($noSub)) {
                    $query->where('U.No_Fak_Sub_Po', $noSub);
                }

                $query->update([
                    'Status_Keputusan_Sampel' => $request->Status_Action,
                    'Flag_Approval' => $isTolak ? 'T' : 'Y',
                    'Id_User' => $pengguna->UserId
                ]);

                $statusKeterangan = $isTolak ? 'Penolakan Tahap ' . $kodeAktivitas : 'Persetujuan Tahap ' . $kodeAktivitas;
                $alasanKeterangan = $isTolak ? $request->Alasan : 'Disetujui otomatis oleh sistem';

                DB::table('N_EMI_LIMS_Uji_Sampel_Keterangan_Status')->insert([
                    'Kode_Perusahaan' => '001', 
                    'No_Sampel' => $noSub ?? $noPo,
                    'Status_Keterangan' => $statusKeterangan,
                    'Alasan' => $alasanKeterangan,
                    'Tanggal' => now()->format('Y-m-d'),
                    'Jam' => now()->format('H:i:s'),
                    'Id_User' => $pengguna->UserId
                ]);
            }

            DB::commit();
            return ResponseHelper::success(null, "Data Berhasil Diproses", 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return ResponseHelper::error("Terjadi Kesalahan Mohon segera hubungi developer", 500);
        }
    }

    public function cancelPraFinal(Request $request)
    {
        $pengguna = Auth::user();

        $request->validate([
            'No_Po_Sampel' => 'required',
            'Alasan' => 'required|min:8'
        ]);

        DB::beginTransaction();

        try {
            $noPo = $request->No_Po_Sampel;

            DB::table('N_EMI_LIMS_Uji_Sampel')
                ->where('No_Po_Sampel', $noPo)
                ->update([
                    'Status_Keputusan_Sampel' => 'tolak',
                    'Flag_Approval' => 'T',
                    'Status' => 'Y',
                    'Id_User' => $pengguna->UserId
                ]);

            
            DB::table('N_LIMS_PO_Sampel')
                ->where('No_Sampel', $noPo)
                ->update([
                    'Flag_Selesai' => 'Y'
                ]);

            DB::table('N_EMI_LIMS_Uji_Pra_Final')->insert([
                'No_Sampel' => $noPo,
                'Alasan' => $request->Alasan,
                'Flag_Setuju' => 'T',
                'Tanggal' => now()->format('Y-m-d'),
                'Jam' => now()->format('H:i:s'),
                'Id_User' => $pengguna->UserId
            ]);

            DB::commit();
            return ResponseHelper::success($noPo, "Sampel Berhasil Dibatalkan", 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return ResponseHelper::error("Terjadi Kesalahan Mohon segera hubungi developer", 500);
        }
    }

    public function finalizePraFinal(Request $request)
    {
        $pengguna = Auth::user();

        $request->validate([
            'No_Po_Sampel' => 'required'
        ]);

        $noPo = $request->No_Po_Sampel;

        $getInformasiPo = DB::table('N_LIMS_PO_Sampel')
            ->where('No_Sampel', $noPo)
            ->select('Kode_Barang', 'Id_Mesin as Id_Master_Mesin')
            ->first();

        if (!$getInformasiPo) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Informasi PO tidak ditemukan.'
            ], 404);
        }

        $kodeDikecualikan = ['HOMOGENITAS', 'MBLG-STR', 'PSZ'];

        // 1. Cari TAHAPAN/KLASIFIKASI apa saja yang diwajibkan untuk Barang & Mesin ini
        $requiredTahapan = DB::table('N_EMI_LAB_Barang_Analisa as ba')
            ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ba.Id_Jenis_Analisa', '=', 'ja.id')
            ->where('ba.Kode_Role', 'FLM')
            ->where('ba.Flag_Aktif', 'Y')
            ->where('ba.Kode_Barang', $getInformasiPo->Kode_Barang)
            ->where('ba.Id_Master_Mesin', $getInformasiPo->Id_Master_Mesin)
            ->whereNotIn('ja.Kode_Analisa', $kodeDikecualikan)
            ->pluck('ja.Kode_Aktivitas_Lab')
            ->unique()
            ->toArray();

        // 2. Cari TAHAPAN/KLASIFIKASI apa saja yang sudah dikerjakan (Minimal ada 1 data)
        $testedTahapan = DB::table('N_EMI_LIMS_Uji_Sampel as us')
            ->join('N_EMI_LAB_Jenis_Analisa as ja', 'us.Id_Jenis_Analisa', '=', 'ja.id')
            ->where('us.No_Po_Sampel', $noPo)
            ->whereNull('us.Flag_Resampling')
            ->whereNull('us.Status')
            ->whereNotIn('ja.Kode_Analisa', $kodeDikecualikan)
            ->pluck('ja.Kode_Aktivitas_Lab')
            ->unique()
            ->toArray();

        // 3. Bandingkan, apakah ada tahapan wajib yang KOSONG sama sekali
        $tahapanKurangCodes = array_diff($requiredTahapan, $testedTahapan);

        if (!empty($tahapanKurangCodes)) {
            // Ambil nama tahapannya untuk pesan error yang mudah dipahami user
            $namaTahapanKurang = DB::table('N_EMI_LIMS_Klasifikasi_Aktivitas_Lab')
                ->whereIn('Kode_Aktivitas_Lab', $tahapanKurangCodes)
                ->pluck('Nama_Aktivitas')
                ->toArray();

            return response()->json([
                'success' => false, 
                'status' => 422,
                'message' => 'Gagal Finalisasi! Minimal harus ada 1 (satu) data analisa yang diisi pada tahapan berikut.',
                'detail' => ['No_Sampel' => $noPo, 'Analisa_Bermasalah' => $namaTahapanKurang]
            ], 422);
        }

        // PENGHAPUSAN VALIDASI "BELUM APPROVAL":
        // Blok kode yang memunculkan error "Masih ada data yang belum melewati proses validasi..." 
        // sengaja dihapus di sini agar data yang masih berstatus 'Menunggu' diabaikan saja dan diteruskan ke finalisasi.

        DB::beginTransaction();

        try {
            DB::table('N_EMI_LIMS_Uji_Sampel')
                ->where('No_Po_Sampel', $noPo)
                ->update([
                    'Status_Keputusan_Sampel' => 'terima',
                    'Flag_Approval' => 'Y',
                    'Status' => null,
                    'Id_User' => $pengguna->UserId
                ]);

            DB::table('N_EMI_LIMS_Uji_Pra_Final')->insert([
                'No_Sampel' => $noPo,
                'Alasan' => 'Disetujui otomatis oleh sistem informasi',
                'Flag_Setuju' => 'Y',
                'Tanggal' => now()->format('Y-m-d'),
                'Jam' => now()->format('H:i:s'),
                'Id_User' => $pengguna->UserId
            ]);

            DB::commit();
            return ResponseHelper::success(null, "Sampel Berhasil Di-Finalisasi", 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return ResponseHelper::error("Terjadi Kesalahan Mohon segera hubungi developer", 500);
        }
    }

    public function getHasilTrialDibatalkan(Request $request)
    {
        try {
            $limit = $request->input('limit', 10);
            $search = $request->input('search', '');

            $baseQuery = DB::table('N_EMI_LIMS_Uji_Pra_Final as pra')
                ->join('N_LIMS_PO_Sampel as po', 'pra.No_Sampel', '=', 'po.No_Sampel')
                ->select(
                    'pra.Id_Uji_Pra_Final',
                    'pra.No_Sampel',
                    'po.No_Po',
                    'po.No_Split_Po',
                    'po.No_Batch',
                    'po.Kode_Barang',
                    'po.Id_Mesin',
                    'pra.Tanggal',
                    'pra.Jam',
                    'pra.Id_User',
                    'pra.Alasan'
                )
                ->where('pra.Flag_Setuju', 'T');

            if (!empty($search)) {
                $baseQuery->where(function($q) use ($search) {
                    $q->where('pra.No_Sampel', 'LIKE', "%{$search}%")
                    ->orWhere('po.No_Po', 'LIKE', "%{$search}%")
                    ->orWhere('po.Kode_Barang', 'LIKE', "%{$search}%");
                });
            }

            $paginated = $baseQuery->orderByDesc('pra.Tanggal')
                ->orderByDesc('pra.Jam')
                ->paginate($limit);

            $items = $paginated->items();

            if (empty($items)) {
                return ResponseHelper::successWithPaginationV2(
                    [],
                    $paginated->currentPage(),
                    $paginated->perPage(),
                    $paginated->total(),
                    'Data Tidak Ditemukan',
                    200,
                    'v1'
                );
            }

            $sampelIds = array_column($items, 'No_Sampel');
            $kodeBarangIds = array_unique(array_column($items, 'Kode_Barang'));

            $masterBarang = DB::table('N_EMI_View_Barang')
                ->whereIn('Kode_Barang', $kodeBarangIds)
                ->select('Kode_Barang', 'Nama')
                ->get()
                ->keyBy('Kode_Barang');

            $keteranganStatus = DB::table('N_EMI_LIMS_Uji_Sampel_Keterangan_Status')
                ->whereIn('No_Sampel', $sampelIds)
                ->orderByDesc('Tanggal')
                ->orderByDesc('Jam')
                ->get()
                ->groupBy('No_Sampel');

            $ujiSampelRaw = DB::table('N_EMI_LIMS_Uji_Sampel as uji')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'uji.Id_Jenis_Analisa', '=', 'ja.id')
                ->leftJoin('N_EMI_LAB_Perhitungan as p', 'uji.Id_Jenis_Analisa', '=', 'p.Id_Jenis_Analisa')
                ->whereIn('uji.No_Po_Sampel', $sampelIds)
                ->select(
                    'uji.No_Po_Sampel',
                    'uji.No_Faktur',
                    'uji.No_Fak_Sub_Po',
                    'uji.Id_Jenis_Analisa',
                    'uji.Hasil',
                    'uji.Nilai_Hasil_String',
                    'uji.Flag_Perhitungan',
                    'ja.Jenis_Analisa',
                    'p.Nama_Kolom as Nama_Kolom_Hasil',
                    DB::raw("ISNULL(p.Hasil_Perhitungan, 0) AS Pembulatan")
                )
                ->get();

            $fakturIds = $ujiSampelRaw->pluck('No_Faktur')->unique()->toArray();
            $jenisAnalisaIds = $ujiSampelRaw->pluck('Id_Jenis_Analisa')->unique()->toArray();

            $detailParams = DB::table('N_EMI_LIMS_Uji_Sampel_Detail')
                ->whereIn('No_Faktur_Uji_Sample', $fakturIds)
                ->get()
                ->groupBy('No_Faktur_Uji_Sample');

            $masterParams = DB::table('N_EMI_LAB_Binding_jenis_analisa as b')
                ->join('EMI_Quality_Control as q', 'q.Id_QC_Formula', '=', 'b.Id_Quality_Control')
                ->whereIn('b.Id_Jenis_Analisa', $jenisAnalisaIds)
                ->select('b.Id_Jenis_Analisa', 'q.Id_QC_Formula', 'q.Keterangan as nama_parameter')
                ->get()
                ->groupBy('Id_Jenis_Analisa');

            // --- AMBIL RULES NON-PERHITUNGAN UNTUK MAPPING WANGI/BUSUK DLL ---
            $rawRules = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->select('Id_Jenis_Analisa', 'Nilai_Kriteria', 'Keterangan_Kriteria')
                ->whereIn('Id_Jenis_Analisa', $jenisAnalisaIds)
                ->where('Flag_Aktif', 'Y')
                ->where('Kode_Role', 'FLM')
                ->get();

            $rulesMap = [];
            foreach ($rawRules as $rule) {
                // Di-cast ke (float) lalu ke (string) agar match persis meskipun ada beda desimal 0
                $key = is_numeric($rule->Nilai_Kriteria) ? (string)((float)$rule->Nilai_Kriteria) : (string)$rule->Nilai_Kriteria;
                $rulesMap[$rule->Id_Jenis_Analisa][$key] = $rule->Keterangan_Kriteria;
            }

            $resultData = [];

            foreach ($items as $item) {
                $noSampel = $item->No_Sampel;
                $histori = isset($keteranganStatus[$noSampel]) ? $keteranganStatus[$noSampel]->first() : null;

                $tahapBatal = $histori ? $histori->Status_Keterangan : 'Pra Final';
                $ketStatus = $histori ? $histori->Alasan : $item->Alasan;

                $tglDibatalkan = date('d M Y H:i', strtotime(substr($item->Tanggal, 0, 10) . ' ' . $item->Jam));
                $namaBarang = isset($masterBarang[$item->Kode_Barang]) ? $masterBarang[$item->Kode_Barang]->Nama : '-';

                $ujiSampelItem = $ujiSampelRaw->where('No_Po_Sampel', $noSampel)->groupBy('Id_Jenis_Analisa');
                $analisaArray = [];

                foreach ($ujiSampelItem as $idAnalisa => $ujiGroup) {
                    $firstUji = $ujiGroup->first();
                    $namaAnalisa = $firstUji->Jenis_Analisa;
                    $isPerhitungan = $firstUji->Flag_Perhitungan === 'Y';
                    $pembulatan = (int)$firstUji->Pembulatan;
                    $namaKolomHasil = $firstUji->Nama_Kolom_Hasil ?? $namaAnalisa;

                    $paramsForAnalisa = isset($masterParams[$idAnalisa]) ? $masterParams[$idAnalisa] : collect();

                    $kolom = ['No Transaksi', 'No Sub'];
                    foreach ($paramsForAnalisa as $mp) {
                        $kolom[] = $mp->nama_parameter;
                    }
                    $kolom[] = $namaKolomHasil;

                    $dataBaris = [];
                    $totalHasil = 0;
                    $countHasil = 0;

                    foreach ($ujiGroup as $uji) {
                        $row = [
                            'No Transaksi' => $uji->No_Faktur,
                            'No Sub' => $uji->No_Fak_Sub_Po ?? '-'
                        ];

                        $detForUji = isset($detailParams[$uji->No_Faktur]) ? $detailParams[$uji->No_Faktur]->keyBy('Id_Quality_Control') : collect();

                        foreach ($paramsForAnalisa as $mp) {
                            if (isset($detForUji[$mp->Id_QC_Formula])) {
                                $rawVal = $detForUji[$mp->Id_QC_Formula]->Value_Parameter;
                                $keyParam = is_numeric($rawVal) ? (string)((float)$rawVal) : (string)$rawVal;

                                // Pengecekan Standar Non Perhitungan untuk Kolom Parameter
                                if (!$isPerhitungan && isset($rulesMap[$idAnalisa][$keyParam])) {
                                    $row[$mp->nama_parameter] = $rulesMap[$idAnalisa][$keyParam];
                                } else {
                                    $row[$mp->nama_parameter] = is_numeric($rawVal) ? number_format((float)$rawVal, 2, '.', '') : $rawVal;
                                }
                            } else {
                                $row[$mp->nama_parameter] = '-';
                            }
                        }

                        // Pengecekan Standar Non Perhitungan untuk Hasil Akhir
                        if ($isPerhitungan) {
                            $hasilAkhirVal = number_format((float)$uji->Hasil, $pembulatan, '.', '');
                            if (is_numeric($uji->Hasil)) {
                                $totalHasil += (float)$uji->Hasil;
                                $countHasil++;
                            }
                        } else {
                            $keyHasil = is_numeric($uji->Hasil) ? (string)((float)$uji->Hasil) : (string)$uji->Hasil;
                            if (isset($rulesMap[$idAnalisa][$keyHasil])) {
                                $hasilAkhirVal = $rulesMap[$idAnalisa][$keyHasil];
                            } else {
                                $hasilAkhirVal = $uji->Nilai_Hasil_String ?? $uji->Hasil;
                            }
                        }

                        $row[$namaKolomHasil] = $hasilAkhirVal;
                        $dataBaris[] = $row;
                    }

                    $rataRata = null;
                    if ($isPerhitungan && $countHasil > 0) {
                        $rataRata = number_format($totalHasil / $countHasil, $pembulatan, '.', '');
                    }

                    $analisaArray[] = [
                        'Nama_Analisa' => $namaAnalisa,
                        'Kolom' => $kolom,
                        'Data' => $dataBaris,
                        'Has_RataRata' => $isPerhitungan,
                        'RataRata' => $rataRata
                    ];
                }

                $resultData[] = [
                    'id' => \Hashids::connection('custom')->encode($item->Id_Uji_Pra_Final),
                    'No_Sampel' => $noSampel,
                    'No_PO' => $item->No_Po,
                    'No_Split_PO' => $item->No_Split_Po,
                    'No_Batch' => $item->No_Batch,
                    'Kode_Barang' => $item->Kode_Barang,
                    'Nama_Barang' => $namaBarang,
                    'Status' => 'Batal', 
                    'Tgl_Dibatalkan' => $tglDibatalkan,
                    'User' => $item->Id_User,
                    'Id_Mesin' => $item->Id_Mesin ?? '-',
                    'Tahap_Batal' => $tahapBatal,
                    'Alasan' => $item->Alasan,
                    'Ket_Status' => $ketStatus,
                    'Analisa' => $analisaArray
                ];
            }

            return ResponseHelper::successWithPaginationV2(
                $resultData,
                $paginated->currentPage(),
                $paginated->perPage(),
                $paginated->total(),
                'Data Ditemukan',
                200,
                'v1'
            );
        } catch (\Exception $e) {
            \Log::channel("FormulatorValidasiHirarkiController")->error($e->getMessage());
            return ResponseHelper::error(
                'Terjadi kesalahan sistem',
                500,
                'v1'
            );
        }
    }
}
