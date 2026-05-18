<?php

namespace App\Http\Controllers\ValidasiLabProduksiTrial;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Vinkla\Hashids\Facades\Hashids;

class ValidasiLabProduksiTrialController extends Controller
{
    public function index()
    {
        return inertia('vue/dashboard/lab/trial-produksi/ValidasiTrialProduksi');
    }
    public function getDataConfirmedSelesaiV2(Request $request)
    {
        $checkedAkses = Session::get("user_permissions");
        $permissionKonten = $checkedAkses['permission_konten'] ?? [];

        $allowedAnalisaIds = [];
        if (isset($permissionKonten['Validasi Trial Produksi']) && is_array($permissionKonten['Validasi Hasil Analisa'])) {
                foreach ($permissionKonten['Validasi Trial Produksi'] as $akses) {
                    if (isset($akses['flag']) && $akses['flag'] === 'Y' && isset($akses['id_jenis_analisa'])) {
                        $allowedAnalisaIds[] = $akses['id_jenis_analisa'];
                    }
                }
        }
        
        $searchQuery = $request->input('q', '');
        $limit = $request->input('limit', 10);
        $filterTanggalMulai = $request->input('tanggal_mulai');
        $filterTanggalSelesai = $request->input('tanggal_selesai');
        $filterQrCode = $request->input('qrcode');
        $filterStatus = $request->input('status');

        if (empty($allowedAnalisaIds)) {
                return response()->json([
                    'success' => true,
                    'status'  => 200,
                    'message' => "Data tidak ditemukan (Tidak ada akses jenis analisa yang valid).",
                    'result'  => [
                        'data' => [],
                        'pagination' => [
                            'page'      => 1,
                            'limit'     => (int)$limit,
                            'totalPage' => 0,
                            'totalData' => 0,
                        ]
                    ]
                ], 200);
        }

        // 1. Base Query: Grouping by Sample & Analysis Type (Tanpa Tanggal di GroupBy)
        $baseQuery = DB::table('N_EMI_LAB_Uji_Sampel')
            ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
            ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
            ->select(
                'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa',
                'N_EMI_LAB_Uji_Sampel.No_Po_Sampel',
                'N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode',
                'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa',
                'N_EMI_LAB_Jenis_Analisa.Kode_Analisa',
                DB::raw('MAX(N_EMI_LAB_Uji_Sampel.Tanggal) as Tanggal') // Gunakan MAX untuk mengambil tanggal terakhir
            )
            ->whereIn('N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', $allowedAnalisaIds)
            ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
            ->whereNull('N_EMI_LAB_Uji_Sampel.Flag_Selesai')
            ->where('N_EMI_LAB_Uji_Sampel.Status_Keputusan_Sampel', 'menunggu')
            ->where('N_EMI_LAB_PO_Sampel.Flag_Trial_Produksi', 'Y') 
            ->groupBy(
                'N_EMI_LAB_Uji_Sampel.No_Po_Sampel',
                'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa',
                'N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode',
                'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa',
                'N_EMI_LAB_Jenis_Analisa.Kode_Analisa'
            );

        if (!empty($searchQuery)) {
            $baseQuery->where(function ($query) use ($searchQuery) {
                $query->where('N_EMI_LAB_Uji_Sampel.No_Po_Sampel', 'like', "%$searchQuery%")
                    ->orWhere('N_EMI_LAB_PO_Sampel.No_Po', 'like', "%$searchQuery%")
                    ->orWhere('N_EMI_LAB_PO_Sampel.No_Split_Po', 'like', "%$searchQuery%")
                    ->orWhere('N_EMI_LAB_PO_Sampel.No_Batch', 'like', "%$searchQuery%");
            });
        }

        if ($filterTanggalMulai && $filterTanggalSelesai) {
            $baseQuery->whereBetween('N_EMI_LAB_Uji_Sampel.Tanggal', [$filterTanggalMulai, $filterTanggalSelesai]);
        }

        if ($filterQrCode) {
            if ($filterQrCode === 'multi') {
                $baseQuery->where('N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode', 'Y');
            } elseif ($filterQrCode === 'single') {
                $baseQuery->where(function ($query) {
                    $query->where('N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode', '!=', 'Y')
                        ->orWhereNull('N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode');
                });
            }
        }

        if ($filterStatus === 'lolos') {
            $baseQuery->whereNotExists(function ($subQ) {
                $subQ->select(DB::raw(1))
                     ->from('N_EMI_LAB_Uji_Sampel as chk')
                     ->whereColumn('chk.No_Po_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel')
                     ->whereColumn('chk.Id_Jenis_Analisa', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa')
                     ->whereNull('chk.Status')
                     ->whereNull('chk.Flag_Selesai')
                     ->where(function ($q2) {
                         $q2->whereNull('chk.Flag_Layak')->orWhere('chk.Flag_Layak', '!=', 'Y');
                     })
                     ->whereRaw('chk.Tahapan_Ke = (SELECT MAX(mx.Tahapan_Ke) FROM N_EMI_LAB_Uji_Sampel mx WHERE mx.No_Po_Sampel = chk.No_Po_Sampel AND mx.Id_Jenis_Analisa = chk.Id_Jenis_Analisa AND mx.Status IS NULL AND mx.Flag_Selesai IS NULL)');
            });
        } elseif ($filterStatus === 'tidak_lolos') {
            $baseQuery->whereExists(function ($subQ) {
                $subQ->select(DB::raw(1))
                     ->from('N_EMI_LAB_Uji_Sampel as chk')
                     ->whereColumn('chk.No_Po_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel')
                     ->whereColumn('chk.Id_Jenis_Analisa', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa')
                     ->whereNull('chk.Status')
                     ->whereNull('chk.Flag_Selesai')
                     ->where(function ($q2) {
                         $q2->whereNull('chk.Flag_Layak')->orWhere('chk.Flag_Layak', '!=', 'Y');
                     })
                     ->whereRaw('chk.Tahapan_Ke = (SELECT MAX(mx.Tahapan_Ke) FROM N_EMI_LAB_Uji_Sampel mx WHERE mx.No_Po_Sampel = chk.No_Po_Sampel AND mx.Id_Jenis_Analisa = chk.Id_Jenis_Analisa AND mx.Status IS NULL AND mx.Flag_Selesai IS NULL)');
            });
        }

        // Urutkan menggunakan alias `Tanggal` yang didapat dari agregasi MAX()
        $paginatedData = $baseQuery->orderByDesc('Tanggal')->paginate($limit);
        $noPoSampelList = $paginatedData->pluck('No_Po_Sampel')->toArray();

        // 2. Fetch ALL history details for these samples
        $allInfoRows = DB::table('N_EMI_LAB_Uji_Sampel')
            ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
            ->join('EMI_Master_Mesin', 'N_EMI_LAB_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
            ->select(
                'N_EMI_LAB_Uji_Sampel.No_Po_Sampel',
                'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa',
                'N_EMI_LAB_PO_Sampel.No_Po',
                'N_EMI_LAB_PO_Sampel.Tanggal as Tanggal_Registrasi',
                'N_EMI_LAB_PO_Sampel.Jam as Jam_Registrasi',
                'N_EMI_LAB_PO_Sampel.Kode_Barang',
                'N_EMI_LAB_PO_Sampel.No_Split_Po',
                'N_EMI_LAB_PO_Sampel.No_Batch',
                'N_EMI_LAB_PO_Sampel.Flag_Trial_Produksi', // Tambahkan field ini
                'N_EMI_LAB_Uji_Sampel.Jam',
                'N_EMI_LAB_Uji_Sampel.Tanggal',
                'EMI_Master_Mesin.Nama_Mesin',
                'N_EMI_LAB_Uji_Sampel.Flag_Layak',
                'N_EMI_LAB_Uji_Sampel.Id_User',
                'N_EMI_LAB_Uji_Sampel.Tahapan_Ke'
            )
            ->whereIn('N_EMI_LAB_Uji_Sampel.No_Po_Sampel', $noPoSampelList)
            ->orderByDesc('N_EMI_LAB_Uji_Sampel.Tahapan_Ke')
            ->orderByDesc('N_EMI_LAB_Uji_Sampel.Tanggal')
            ->orderByDesc('N_EMI_LAB_Uji_Sampel.Jam')
            ->get();

        $groupedInfos = $allInfoRows->groupBy('No_Po_Sampel');

        $kodeBarangList = $allInfoRows->pluck('Kode_Barang')->unique()->filter()->toArray();
        $barangList = DB::table('N_EMI_View_Barang')
            ->whereIn('Kode_Barang', $kodeBarangList)
            ->pluck('Nama', 'Kode_Barang');

        $paginatedData->getCollection()->transform(function ($item) use ($groupedInfos, $barangList) {
            $rawIdJenisAnalisa = $item->Id_Jenis_Analisa;
            
            $item->Id_Jenis_Analisa = Hashids::connection('custom')->encode($item->Id_Jenis_Analisa);

            $sampleHistory = $groupedInfos->get($item->No_Po_Sampel);
            
            $specificInfo = null;
            if ($sampleHistory) {
                $specificInfo = $sampleHistory->where('Id_Jenis_Analisa', $rawIdJenisAnalisa)->first();
            }

            if ($specificInfo) {
                $item->Id_User            = $specificInfo->Id_User;
                $item->Jam                = $specificInfo->Jam;
                $item->Flag_Layak         = $specificInfo->Flag_Layak;
                
                $item->Tanggal_Registrasi = $specificInfo->Tanggal_Registrasi;
                $item->Jam_Registrasi     = $specificInfo->Jam_Registrasi;
                $item->Nama_Barang        = $barangList[$specificInfo->Kode_Barang] ?? null;
                $item->po_info = [
                    'No_Po'               => $specificInfo->No_Po,
                    'No_Split_Po'         => $specificInfo->No_Split_Po,
                    'No_Batch'            => $specificInfo->No_Batch,
                    'Kode_Barang'         => $specificInfo->Kode_Barang,
                    'Nama_Mesin'          => $specificInfo->Nama_Mesin,
                    'Flag_Trial_Produksi' => $specificInfo->Flag_Trial_Produksi, // Map ke po_info
                ];
            } else {
                $item->Id_User = null;
                $item->Jam = null;
                $item->Flag_Layak = null;
                $item->Tanggal_Registrasi = null;
                $item->Jam_Registrasi = null;
                $item->Nama_Barang = null;
                $item->po_info = null;
            }

            $autoLolosKodes = ['PSZ'];
            $kodeAnalisa = trim($item->Kode_Analisa);

            if (in_array($kodeAnalisa, $autoLolosKodes)) {
                $item->Status_Sampel = "Lolos Uji";
            } else {
                if ($sampleHistory && $sampleHistory->isNotEmpty()) {
                    $filteredRows = $sampleHistory->where('Id_Jenis_Analisa', $rawIdJenisAnalisa);

                    if ($filteredRows->isNotEmpty()) {
                        $maxTahapan = $filteredRows->max('Tahapan_Ke');
                        $specificFlags = $filteredRows->where('Tahapan_Ke', $maxTahapan)
                            ->pluck('Flag_Layak')
                            ->toArray();

                        if (empty($specificFlags)) {
                            $item->Status_Sampel = "Tidak Lolos Uji";
                        } else {
                            $allLolos = true;
                            foreach ($specificFlags as $flag) {
                                if ($flag !== 'Y') {
                                    $allLolos = false;
                                    break;
                                }
                            }
                            $item->Status_Sampel = $allLolos ? "Lolos Uji" : "Tidak Lolos Uji";
                        }
                    } else {
                        $item->Status_Sampel = "Tidak Lolos Uji";
                    }
                } else {
                    $item->Status_Sampel = "Tidak Lolos Uji";
                }
            }

            unset($item->Flag_Layak);
            return $item;
        });

        if ($paginatedData->total() === 0) {
            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => "Data tidak ditemukan sesuai kriteria pencarian Anda.",
                'result'  => [
                    'data' => [],
                    'pagination' => [
                        'page'      => 1,
                        'limit'     => (int)$limit,
                        'totalPage' => 0,
                        'totalData' => 0,
                    ]
                ]
            ], 200);
        }

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => "Data Ditemukan",
            'result'  => [
                'data' => $paginatedData->getCollection(),
                'pagination' => [
                    'page'      => $paginatedData->currentPage(),
                    'limit'     => $paginatedData->perPage(),
                    'totalPage' => $paginatedData->lastPage(),
                    'totalData' => $paginatedData->total(),
                ]
            ]
        ], 200);
    }
}
