<?php

namespace App\Http\Controllers\FormulatorStatusData;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

class FormulatorStatusDataController extends Controller
{
    public function index()
    {
       return inertia("vue/dashboard/lab/formulator/status-data/HomeStatusDataSampel");
    }

    public function statusDataSampel(Request $request)
    {
        try {
            $limit = $request->input('limit', 10);
            $search = $request->input('search', '');

            $baseQuery = DB::table('N_LIMS_PO_Sampel as po')
                ->select(
                    'po.id',
                    'po.No_Sampel',
                    'po.No_Po',
                    'po.No_Split_Po',
                    'po.No_Batch',
                    'po.Kode_Barang',
                    'po.Id_Mesin',
                    'po.Tanggal',
                    'po.Jam',
                    'po.Id_User',
                    'po.Flag_Selesai as PO_Flag_Selesai'
                );

            if (!empty($search)) {
                $baseQuery->where(function($q) use ($search) {
                    $q->where('po.No_Sampel', 'LIKE', "%{$search}%")
                    ->orWhere('po.No_Po', 'LIKE', "%{$search}%")
                    ->orWhere('po.Kode_Barang', 'LIKE', "%{$search}%");
                });
            }

            $paginated = $baseQuery->orderByDesc('po.Tanggal')
                ->orderByDesc('po.Jam')
                ->paginate($limit);

            $items = $paginated->items();

            if (empty($items)) {
                return ResponseHelper::successWithPaginationV2(
                    [], $paginated->currentPage(), $paginated->perPage(), $paginated->total(),
                    'Data Tidak Ditemukan', 200, 'v1'
                );
            }

            $sampelIds = array_column($items, 'No_Sampel');
            $kodeBarangIds = array_unique(array_column($items, 'Kode_Barang'));
            $mesinIds = array_unique(array_filter(array_column($items, 'Id_Mesin')));

            $masterBarang = DB::table('N_EMI_View_Barang')
                ->whereIn('Kode_Barang', $kodeBarangIds)
                ->select('Kode_Barang', 'Nama')
                ->get()
                ->keyBy('Kode_Barang');

            $masterMesin = DB::table('EMI_Master_Mesin')
                ->whereIn('Id_Master_Mesin', $mesinIds)
                ->select('Id_Master_Mesin', 'Nama_Mesin')
                ->get()
                ->keyBy('Id_Master_Mesin');

            $wajibAnalisa = DB::table('N_EMI_LAB_Barang_Analisa as ba')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ba.Id_Jenis_Analisa', '=', 'ja.id')
                ->whereIn('ba.Kode_Barang', $kodeBarangIds)
                ->whereIn('ba.Id_Master_Mesin', $mesinIds)
                ->where('ba.Kode_Role', 'FLM')
                ->where('ba.Flag_Aktif', 'Y')
                ->select('ba.Kode_Barang', 'ba.Id_Master_Mesin', 'ba.Id_Jenis_Analisa', 'ja.Jenis_Analisa')
                ->groupBy('ba.Kode_Barang', 'ba.Id_Master_Mesin', 'ba.Id_Jenis_Analisa', 'ja.Jenis_Analisa')
                ->get()
                ->groupBy(function($item) {
                    return $item->Kode_Barang . '_' . $item->Id_Master_Mesin;
                });

            $ujiSampelRaw = DB::table('N_EMI_LIMS_Uji_Sampel as uji')
                ->whereIn('uji.No_Po_Sampel', $sampelIds)
                ->get();
            $ujiGrouped = $ujiSampelRaw->groupBy('No_Po_Sampel');

            $praFinalMap = DB::table('N_EMI_LIMS_Uji_Pra_Final')
                ->whereIn('No_Sampel', $sampelIds)
                ->get()
                ->keyBy('No_Sampel');

            $validasiFinalMap = DB::table('N_EMI_LIMS_Hasil_Uji_Validasi_Final')
                ->whereIn('No_Sampel', $sampelIds)
                ->get()
                ->keyBy('No_Sampel');

            $resultData = [];

            foreach ($items as $item) {
                $noSampel = $item->No_Sampel;
                $namaBarang = isset($masterBarang[$item->Kode_Barang]) ? $masterBarang[$item->Kode_Barang]->Nama : '-';
                $namaMesin = isset($masterMesin[$item->Id_Mesin]) ? $masterMesin[$item->Id_Mesin]->Nama_Mesin : '-';
                
                $ujiList = isset($ujiGrouped[$noSampel]) ? $ujiGrouped[$noSampel] : collect();
                $praFinal = isset($praFinalMap[$noSampel]) ? $praFinalMap[$noSampel] : null;
                $validasiFinal = isset($validasiFinalMap[$noSampel]) ? $validasiFinalMap[$noSampel] : null;

                $statusUtama = '';
                
                if ($ujiList->isEmpty()) {
                    $statusUtama = 'Belum Mulai Trial';
                } else {
                    $hasResampling = $ujiList->contains('Flag_Resampling', 'Y');
                    $hasApprovalNull = $ujiList->containsStrict('Flag_Approval', null);
                    
                    $isDibatalkan = $item->PO_Flag_Selesai === 'Y' && 
                                    $ujiList->contains(fn($u) => $u->Status === 'Y' && strtolower($u->Status_Keputusan_Sampel) === 'ditolak') && 
                                    !$hasApprovalNull && 
                                    !$hasResampling;

                    if ($validasiFinal) {
                        $statusUtama = 'Selesai';
                    } elseif ($isDibatalkan) {
                        $statusUtama = 'Dibatalkan';
                    } elseif ($praFinal && !$hasApprovalNull && !$hasResampling && $item->PO_Flag_Selesai === 'Y') {
                        $statusUtama = 'Menunggu Finalisasi';
                    } else {
                        $statusUtama = 'Sedang Analisa';
                    }
                }

                $analisaArray = [];
                $keyMasterAnalisa = $item->Kode_Barang . '_' . $item->Id_Mesin;
                $requiredAnalisa = isset($wajibAnalisa[$keyMasterAnalisa]) ? $wajibAnalisa[$keyMasterAnalisa]->unique('Id_Jenis_Analisa')->values() : collect();

                foreach ($requiredAnalisa as $req) {
                    $idAnalisa = $req->Id_Jenis_Analisa;
                    $namaAnalisa = $req->Jenis_Analisa;
                    
                    $ujiAnalisa = $ujiList->where('Id_Jenis_Analisa', $idAnalisa)->sortByDesc(function($u) {
                        return $u->Tanggal . ' ' . $u->Jam;
                    });
                    
                    $statusAnalisa = '';

                    if ($ujiAnalisa->isEmpty()) {
                        $statusAnalisa = 'Belum Mulai Analisa';
                    } else {
                        $latestUji = $ujiAnalisa->first();
                        
                        if ($validasiFinal) {
                            $statusAnalisa = 'Selesai';
                        } elseif ($latestUji->Flag_Resampling === 'Y') {
                            $statusAnalisa = 'Menunggu Dilakukan Resampling';
                        } elseif ($praFinal && $praFinal->Flag_Setuju === 'T' && !$hasApprovalNull) {
                            $statusAnalisa = 'Sampel Dibatalkan';
                        } elseif ($praFinal && $praFinal->Flag_Setuju === 'Y' && !$hasApprovalNull) {
                            $statusAnalisa = 'Menunggu Finalisasi';
                        } elseif ($latestUji->Flag_Selesai === null && $latestUji->Status === null && strtolower($latestUji->Status_Keputusan_Sampel) === 'menunggu') {
                            $statusAnalisa = 'Menunggu Validasi';
                        } else {
                            $statusAnalisa = 'Selesai Di Validasi'; 
                        }
                    }

                    $analisaArray[] = [
                        'Id_Analisa'   => $idAnalisa,
                        'Nama_Analisa' => $namaAnalisa,
                        'Ket_Status'   => $statusAnalisa,
                    ];
                }

                $resultData[] = [
                    'id'             => Hashids::connection('custom')->encode($item->id),
                    'No_Sampel'      => $noSampel,
                    'No_PO'          => $item->No_Po,
                    'No_Split_PO'    => $item->No_Split_Po,
                    'No_Batch'       => $item->No_Batch,
                    'Kode_Barang'    => $item->Kode_Barang,
                    'Nama_Barang'    => $namaBarang,
                    'Status_Sampel'  => $statusUtama, 
                    'User'           => $item->Id_User,
                    'Nama_Mesin'     => $namaMesin,
                    'Analisa'        => $analisaArray
                ];
            }

            return ResponseHelper::successWithPaginationV2(
                $resultData, $paginated->currentPage(), $paginated->perPage(), $paginated->total(),
                'Data Ditemukan', 200, 'v1'
            );

        } catch (\Exception $e) {
            \Log::channel("FormulatorValidasiHirarkiController")->error($e->getMessage());
            return ResponseHelper::error('Terjadi kesalahan sistem', 500, 'v1');
        }
    }
}
