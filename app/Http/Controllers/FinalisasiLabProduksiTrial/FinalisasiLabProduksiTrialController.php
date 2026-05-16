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
        if (isset($permissionKonten['Finalisasi Trial Produksi']) && is_array($permissionKonten['Validasi Hasil Analisa'])) {
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
                'uji.Tanggal',
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
            'uji.Tanggal',
            'uji.Flag_Multi_QrCode',
            'po.No_Po',
            'po.No_Split_Po',
            'po.Kode_Barang',
            'brg.Nama',
            'po.Flag_Trial_Produksi' // <-- Tambahkan baris ini
        )
        ->orderByDesc('uji.Tanggal')
        ->orderByDesc(DB::raw('MAX(uji.Jam)'));

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        return ResponseHelper::successWithPaginationV2(
            $paginated->items(),
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
            DB::table('N_EMI_LAB_Uji_Sampel')
                ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
                ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel') 
                ->select(
                    'N_EMI_LAB_Uji_Sampel.*',
                    'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa',
                    'N_EMI_LAB_Jenis_Analisa.Kode_Analisa'
                )
                ->where('N_EMI_LAB_Uji_Sampel.No_Po_Sampel', $No_Po_Sampel)
                ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
                ->where('N_EMI_LAB_Uji_Sampel.Flag_Selesai', 'Y')
                ->where('N_EMI_LAB_Uji_Sampel.Status_Keputusan_Sampel', 'terima')
                ->where('N_EMI_LAB_PO_Sampel.Flag_Trial_Produksi', 'Y') 
                ->orderByDesc('N_EMI_LAB_Uji_Sampel.Tanggal')
                ->get()
        )->map(function ($item) {
            /** @var object $item */
            $item->Id_Jenis_Analisa = Hashids::connection('custom')->encode($item->Id_Jenis_Analisa);
            return $item;
        })->unique(function ($item) {
            return $item->No_Po_Sampel . '-' . $item->Id_Jenis_Analisa;
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

            $kodeDikecualikan = ['HOMOGENITAS', 'MBLG-STR', 'PSZ'];

            $checkedJumlahStandarMutu = DB::table('N_EMI_LAB_Barang_Analisa as ba')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ba.Id_Jenis_Analisa', '=', 'ja.id')
                ->select('ba.Id_Jenis_Analisa')
                ->where('ba.Flag_Aktif', 'Y')
                ->where('ba.Kode_Role', 'LAB')
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
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Data Berhasil Disimpan"
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('FinalisasiLabProduksiTrialController')->error('Error: ' . $e->getMessage());
            
            // FIX: Keluarkan pesan error aslinya agar gampang melacak jika terjadi masalah
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan di Baris " . $e->getLine() . " - " . $e->getMessage()
            ], 500);
        }
    }
}
