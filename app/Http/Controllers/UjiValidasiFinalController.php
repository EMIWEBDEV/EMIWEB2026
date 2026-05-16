<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UjiValidasiFinalController extends Controller
{

    public function index()
    {
        return inertia("vue/dashboard/hasil-akhir-validasi/HasilAkhirValidasi");
    }
    
    public function viewProdukSiapRilis()
    {
        return inertia("vue/dashboard/hasil-akhir-validasi/ProdukSiapRilis");
    }

    public function getDataCurrentHasilFinalValidasi(Request $request)
    {
        try {
            $limit = (int) $request->query('limit', 10);
            $page = (int) $request->query('page', 1);
            $offset = ($page - 1) * $limit;

            $query = DB::table('N_EMI_LAB_Hasil_Uji_Validasi_Final as hv')
                ->where('hv.Flag_FG', 'Y');

            // 🔎 Filter tanggal
            if ($request->filled('date')) {
                $query->whereDate('hv.Tanggal', $request->date);
            }

            // 🔎 Filter no sampel
            if ($request->filled('no_sampel')) {
                $query->where('hv.No_Sampel', 'like', "%{$request->no_sampel}%");
            }

            // 🔎 Filter batch
            if ($request->filled('batch')) {
                $query->where('hv.No_Batch', 'like', "%{$request->batch}%");
            }

            // 🔎 Filter status (pakai Flag_Ok Y/T)
            if ($request->filled('status')) {
                if ($request->status === 'Lolos Uji') {
                    $query->where('hv.Flag_Ok', 'Y');
                } elseif ($request->status === 'Tidak Lolos Uji') {
                    $query->where('hv.Flag_Ok', 'T');
                }
            }

            // 🔎 Pencarian umum
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('hv.No_Po', 'like', "%{$search}%")
                        ->orWhere('hv.No_Sampel', 'like', "%{$search}%")
                        ->orWhere('hv.No_Split_Po', 'like', "%{$search}%")
                        ->orWhere('hv.No_Batch', 'like', "%{$search}%");
                });
            }

            $total = $query->count();

            $getData = $query->orderBy('hv.Tanggal', 'desc')
                ->orderBy('hv.Jam', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get();

            if ($getData->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'status' => 200,
                    'message' => 'Tidak ada data hasil final validasi yang ditemukan.',
                    'result' => [],
                    'page' => $page,
                    'total_page' => 0,
                    'total_data' => 0,
                ], 200);
            }

            $listData = [];

            foreach ($getData as $item) {
                $getDataUjiSampel = DB::table('N_EMI_LAB_PO_Sampel')
                    ->where('No_Po', $item->No_Po)
                    ->where('No_Split_Po', $item->No_Split_Po)
                    ->first();

                $checkedNamaBarang = null;
                if ($getDataUjiSampel) {
                    $checkedNamaBarang = DB::table('N_EMI_View_Barang')
                        ->where('Kode_Barang', $getDataUjiSampel->Kode_Barang)
                        ->first();
                }

                // 🟢 Mapping status berdasarkan Flag_Ok
                $status = 'Status Konflik/Tidak Dikenali';
                if ($item->Flag_Ok === 'Y') {
                    $status = 'Lolos Uji';
                } elseif ($item->Flag_Ok === 'T') {
                    $status = 'Tidak Lolos Uji';
                }

                $listData[] = [
                    'No_Sampel'   => $item->No_Sampel,
                    'No_Po'       => $item->No_Po,
                    'No_Split_Po' => $item->No_Split_Po,
                    'No_Batch'    => (float) $item->No_Batch,
                    'Tanggal'     => $item->Tanggal,
                    'Jam'         => $item->Jam,
                    'Flag_FG'     => $item->Flag_FG,
                    'Flag_Ok'     => $item->Flag_Ok,
                    'Nama_Barang' => $checkedNamaBarang ? $checkedNamaBarang->Nama : 'Tidak Ditemukan',
                    'Kode_Barang' => $getDataUjiSampel->Kode_Barang ?? '-',
                    'Status'      => $status,
                ];
            }

            return response()->json([
                'success'    => true,
                'status'     => 200,
                'message'    => 'Data berhasil diambil.',
                'result'     => $listData,
                'page'       => $page,
                'total_page' => ceil($total / $limit),
                'total_data' => $total
            ], 200);

        } catch (\Exception $e) {
            Log::channel('UjiValidasiFinalController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan"
            ], 500);
        }
    }


    public function viewInformasiMultiQrs($no_sub_sampel)
    {
        return inertia('vue/dashboard/hasil-akhir-validasi/HasilAkhirValidasiPcs', [
            'No_Sub_Sampel' => $no_sub_sampel
        ]);
    }
    public function viewInformasiJenisAnalisaMultiQrS($no_sampel, $no_fak_sub_sampel)
    {
        if($no_fak_sub_sampel === null || $no_fak_sub_sampel === "null"){
            return inertia('vue/dashboard/hasil-akhir-validasi/HasilAkhirValidasiNoPcsJenisAnalisa', [
                'No_Sampel' => $no_sampel,
            ]);
        }else {
            return inertia('vue/dashboard/hasil-akhir-validasi/HasilAkhirValidasiPcsJenisAnalisa', [
                'No_Sampel' => $no_sampel,
                'No_Fak_Sub_Sampel' => $no_fak_sub_sampel,
            ]);
        }
    }

     public function viewDataHasilAnalisaValidasiR($no_sampel, $no_fak_sub_sampel, $id_jenis_analisa)
    {
        // dd($no_fak_sub_sampel);
        if($no_fak_sub_sampel === null || $no_fak_sub_sampel === "null"){
            return inertia('vue/dashboard/hasil-akhir-validasi/HasilAkhirUntukDivalidasiNoPcs', [
                'No_Sampel' => $no_sampel,
                'Id_Jenis_Analisa' => $id_jenis_analisa
            ]);
        }else {
            return inertia('vue/dashboard/hasil-akhir-validasi/HasilAkhirUntukDivalidasi', [
                'No_Sampel' => $no_sampel,
                'No_Fak_Sub_Sampel' => $no_fak_sub_sampel,
                'Id_Jenis_Analisa' => $id_jenis_analisa
            ]);
        }
    }

    public function store($no_sampel)
    {
        DB::beginTransaction();

        try {
            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow; 
            $tanggalSqlServer = date('Y-m-d', strtotime($dt)); 
            $jamSqlServer = date('H:i:s', strtotime($dt));

            $getInformasiPo = DB::table('N_EMI_LAB_PO_Sampel')
                ->where('No_Sampel', $no_sampel)
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
                // PASTIKAN FILTER INI BENAR: Data di DB bernilai NULL, jika memang opsional gunakan where(function($q)...)
                ->whereNull('ps.Flag_Trial_Produksi') 
                ->whereNull('us.Flag_Resampling')
                ->whereNull('us.Status')
                ->get();

            // Mengambil data kode dikecualikan dari database
            $kodeDikecualikan = DB::table('N_EMI_LAB_Jenis_Analisa_Opsional')
                ->pluck('Kode_Analisa')
                ->toArray();

            $checkedJumlahStandarMutu = DB::table('N_EMI_LAB_Barang_Analisa as ba')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ba.Id_Jenis_Analisa', '=', 'ja.id')
                ->select('ba.Id_Jenis_Analisa')
                ->where('ba.Kode_Role', 'LAB')
                ->where('ba.Kode_Barang', $getInformasiPo->Kode_Barang)
                ->where('ba.Id_Master_Mesin', $getInformasiPo->Id_Mesin)
                ->whereNotIn('ja.Kode_Analisa', $kodeDikecualikan)
                ->groupBy('ba.Kode_Barang', 'ba.Id_Master_Mesin', 'ba.Id_Jenis_Analisa')
                ->pluck('ba.Id_Jenis_Analisa')
                ->toArray();

            $checkedJumlahAnalisa = DB::table('N_EMI_LAB_Uji_Sampel as us')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'us.Id_Jenis_Analisa', '=', 'ja.id')
                ->where('us.No_Po_Sampel', $no_sampel)
                ->whereNotIn('ja.Kode_Analisa', $kodeDikecualikan)
                ->pluck('us.Id_Jenis_Analisa')
                ->toArray();

            // Cek analisa yang kurang
            $analisaKurang = array_diff($checkedJumlahStandarMutu, $checkedJumlahAnalisa);

            if (!empty($analisaKurang)) {
                $detailKurang = DB::table('N_EMI_LAB_Jenis_Analisa')
                    ->whereIn('id', $analisaKurang)
                    ->pluck('Jenis_Analisa');
                
                return response()->json([
                    'success' => false,
                    'status' => 422,
                    'message' => 'Data analisa belum lengkap. Mohon lengkapi analisa berikut sebelum difinalisasi',
                    'detail' => [
                        'No_Sampel' => $no_sampel,
                        'Analisa_Kurang' => $detailKurang
                    ]
                ], 422);
            }

            // Cek kalau ada Flag_Selesai masih null
            $belumSelesai = DB::table('N_EMI_LAB_Uji_Sampel as us')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'us.Id_Jenis_Analisa', '=', 'ja.id')
                ->where('us.No_Po_Sampel', $no_sampel)
                ->where('us.Status_Keputusan_Sampel', 'terima')
                ->whereNull('us.Flag_Selesai')
                ->whereNotIn('ja.Kode_Analisa', $kodeDikecualikan)
                ->pluck('ja.Jenis_Analisa');

            if ($belumSelesai->count() > 0) {
                return response()->json([
                    'success' => false,
                    'status' => 422,
                    'message' => 'Masih ada analisa yang belum divalidasi.',
                    'detail' => [
                        'No_Sampel' => $no_sampel,
                        'Analisa_Belum_Validasi' => $belumSelesai
                    ]
                ], 422);
            }

            $flagFgValid = false;
            $idJenisAnalisaList = [];

            foreach ($getData as $item) {
                $checkedFg = DB::table('EMI_Master_Mesin')
                    ->where('Id_Master_Mesin', $item->Id_Mesin)
                    ->first();
                    
                if ($checkedFg && $checkedFg->Flag_FG === 'Y') {
                    $flagFgValid = true;

                    // Update Flag_Final hanya untuk data yang valid
                    DB::table('N_EMI_LAB_Uji_Sampel')
                        ->whereNull('Status')
                        ->where('No_Po_Sampel', $item->No_Po_Sampel)
                        ->where('No_Fak_Sub_Po', $item->No_Fak_Sub_Po)
                        ->update([
                            'Flag_Final' => 'Y'
                        ]);

                    $idJenisAnalisaList[] = $item->Id_Jenis_Analisa;
                }
            }

            if ($flagFgValid) {
                $adaYangTidakLayak = DB::table('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final')
                    ->where('No_Sampel', $no_sampel)
                    ->whereIn('Id_Jenis_Analisa', $idJenisAnalisaList)
                    ->where('Flag_Layak', 'T')
                    ->exists();

                $flagOkValue = $adaYangTidakLayak ? 'T' : 'Y';

                $payloadUjiFInal = [
                    'No_Po' => $getInformasiPo->No_Po,
                    // 'No_Split_Po' dihapus dari payload karena sudah ada di array kriteria pencarian updateOrInsert
                    // 'No_Batch' dihapus dari payload karena sudah ada di array kriteria pencarian
                    'No_Sampel' => $no_sampel,
                    'Tanggal' => $tanggalSqlServer,
                    'Jam' => $jamSqlServer,
                    'Flag_FG' => 'Y',
                    'Flag_Ok' => $flagOkValue,
                    'Id_User' => Auth::user()->UserId
                ];

                // 🚀 PERUBAHAN UTAMA: Menggunakan updateOrInsert
                DB::table('N_EMI_LAB_Hasil_Uji_Validasi_Final')
                    ->updateOrInsert(
                        [
                            // 1. Array pertama: Kriteria pencarian unik
                            'No_Split_Po' => $getInformasiPo->No_Split_Po,
                            'No_Batch'    => $getInformasiPo->No_Batch
                        ],
                        // 2. Array kedua: Kolom yang akan di-update (jika ketemu) ATAU di-insert (jika tidak ketemu)
                        $payloadUjiFInal
                    );

                DB::table('N_EMI_LAB_PO_Sampel')
                    ->where('No_Sampel', $no_sampel)
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
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => [
                    'error' => $e->getMessage(),
                ]
            ], 500);
        }
    }

    public function storeBulk(Request $request)
    {
        $no_sampel_list = $request->input('no_sampel_list', []);

        if (empty($no_sampel_list)) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Tidak ada data sampel yang dipilih.'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow;

            $tanggalSqlServer = date('Y-m-d', strtotime($dt));
            $jamSqlServer = date('H:i:s', strtotime($dt));

            $kodeDikecualikan = DB::table('N_EMI_LAB_Jenis_Analisa_Opsional')
                ->pluck('Kode_Analisa')
                ->toArray();

            $masterAnalisa = DB::table('N_EMI_LAB_Jenis_Analisa')
                ->pluck('Jenis_Analisa', 'id')
                ->toArray();

            // 🚀 FILTER UTAMA: Hanya ambil PO yang Flag_Trial_Produksi-nya NULL
            $poSampels = DB::table('N_EMI_LAB_PO_Sampel')
                ->whereIn('No_Sampel', $no_sampel_list)
                ->whereNull('Flag_Trial_Produksi') 
                ->get()
                ->keyBy('No_Sampel');

            $ujiSampels = DB::table('N_EMI_LAB_Uji_Sampel as us')
                ->join('N_EMI_LAB_PO_Sampel as ps', 'us.No_Po_Sampel', '=', 'ps.No_Sampel')
                ->select('us.*', 'ps.Kode_Barang', 'ps.Id_Mesin')
                ->whereIn('us.No_Po_Sampel', $no_sampel_list)
                ->whereNull('ps.Flag_Trial_Produksi') // Memastikan filter sesuai permintaan
                ->whereNull('us.Flag_Resampling')
                ->whereNull('us.Status')
                ->get()
                ->groupBy('No_Po_Sampel');

            $kodes = $poSampels->pluck('Kode_Barang')->unique()->toArray();
            $mesins = $poSampels->pluck('Id_Mesin')->unique()->toArray();

            $stdMutuGrouped = DB::table('N_EMI_LAB_Barang_Analisa as ba')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ba.Id_Jenis_Analisa', '=', 'ja.id')
                ->where('ba.Kode_Role', 'LAB')
                ->whereIn('ba.Kode_Barang', $kodes)
                ->whereIn('ba.Id_Master_Mesin', $mesins)
                ->whereNotIn('ja.Kode_Analisa', $kodeDikecualikan)
                ->select('ba.Kode_Barang', 'ba.Id_Master_Mesin', 'ba.Id_Jenis_Analisa')
                ->get()
                ->groupBy(function ($item) {
                    return $item->Kode_Barang . '_' . $item->Id_Master_Mesin;
                });

            $ujiAnalisaGrouped = DB::table('N_EMI_LAB_Uji_Sampel as us')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'us.Id_Jenis_Analisa', '=', 'ja.id')
                ->whereIn('us.No_Po_Sampel', $no_sampel_list)
                ->whereNotIn('ja.Kode_Analisa', $kodeDikecualikan)
                ->select('us.No_Po_Sampel', 'us.Id_Jenis_Analisa')
                ->get()
                ->groupBy('No_Po_Sampel');

            $belumSelesaiGrouped = DB::table('N_EMI_LAB_Uji_Sampel as us')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'us.Id_Jenis_Analisa', '=', 'ja.id')
                ->whereIn('us.No_Po_Sampel', $no_sampel_list)
                ->where('us.Status_Keputusan_Sampel', 'terima')
                ->whereNull('us.Flag_Selesai')
                ->whereNotIn('ja.Kode_Analisa', $kodeDikecualikan)
                ->select('us.No_Po_Sampel', 'ja.Jenis_Analisa')
                ->get()
                ->groupBy('No_Po_Sampel');

            $mesinFg = DB::table('EMI_Master_Mesin')
                ->whereIn('Id_Master_Mesin', $mesins)
                ->pluck('Flag_FG', 'Id_Master_Mesin')
                ->toArray();

            $tidakLayakData = DB::table('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final')
                ->whereIn('No_Sampel', $no_sampel_list)
                ->where('Flag_Layak', 'T')
                ->select('No_Sampel', 'Id_Jenis_Analisa')
                ->get()
                ->groupBy('No_Sampel');

            // CATATAN: Pengecekan existingFinals secara manual telah saya HAPUS 
            // karena fungsinya sudah digantikan secara otomatis oleh updateOrInsert.

            $poSampelUpdateCases = [];
            $berhasil = [];
            $gagal = [];
            $userId = Auth::user()->UserId;

            foreach ($no_sampel_list as $no_sampel) {
                $infoPo = $poSampels->get($no_sampel);

                if (!$infoPo) {
                    $gagal[] = [
                        'sampel' => $no_sampel,
                        'reason' => 'PO Tidak Ditemukan atau Flag Trial bukan NULL'
                    ];
                    continue;
                }

                $keyStd = $infoPo->Kode_Barang . '_' . $infoPo->Id_Mesin;

                $stdMutu = $stdMutuGrouped->has($keyStd)
                    ? $stdMutuGrouped->get($keyStd)->pluck('Id_Jenis_Analisa')->toArray()
                    : [];

                $analisaUji = $ujiAnalisaGrouped->has($no_sampel)
                    ? $ujiAnalisaGrouped->get($no_sampel)->pluck('Id_Jenis_Analisa')->toArray()
                    : [];

                $analisaKurang = array_diff($stdMutu, $analisaUji);

                if (!empty($analisaKurang)) {
                    $namaKurang = [];
                    foreach($analisaKurang as $idKrg) {
                        $namaKurang[] = $masterAnalisa[$idKrg] ?? 'Unknown';
                    }
                    $strNamaKurang = implode(', ', $namaKurang);

                    $gagal[] = [
                        'sampel' => $no_sampel,
                        'reason' => 'Analisa standar mutu belum terpenuhi: ' . $strNamaKurang
                    ];
                    continue;
                }

                if ($belumSelesaiGrouped->has($no_sampel)) {
                    $namaBelumValid = $belumSelesaiGrouped->get($no_sampel)->pluck('Jenis_Analisa')->toArray();
                    $strNamaBelumValid = implode(', ', $namaBelumValid);

                    $gagal[] = [
                        'sampel' => $no_sampel,
                        'reason' => 'Masih ada analisa yang belum divalidasi: ' . $strNamaBelumValid
                    ];
                    continue;
                }

                $listUji = $ujiSampels->get($no_sampel) ?? collect();

                $flagFgValid = false;
                $idJenisAnalisaList = [];
                $noFakSubPosToUpdate = [];

                foreach ($listUji as $item) {
                    if (($mesinFg[$item->Id_Mesin] ?? null) === 'Y') {
                        $flagFgValid = true;
                        $idJenisAnalisaList[] = $item->Id_Jenis_Analisa;
                        $noFakSubPosToUpdate[] = $item->No_Fak_Sub_Po;
                    }
                }

                if ($flagFgValid) {
                    // Update Flag_Final pada Uji_Sampel
                    DB::table('N_EMI_LAB_Uji_Sampel')
                        ->whereNull('Status')
                        ->where('No_Po_Sampel', $no_sampel)
                        ->whereIn('No_Fak_Sub_Po', $noFakSubPosToUpdate)
                        ->update([
                            'Flag_Final' => 'Y'
                        ]);

                    $tidakLayakDataSampel = $tidakLayakData->get($no_sampel) ?? collect();

                    $adaYangTidakLayak = collect($tidakLayakDataSampel)
                        ->contains(function ($tl) use ($idJenisAnalisaList) {
                            return in_array($tl->Id_Jenis_Analisa, $idJenisAnalisaList);
                        });

                    $flagOkValue = $adaYangTidakLayak ? 'T' : 'Y';

                    // 🚀 PERUBAHAN UTAMA: Menggunakan updateOrInsert langsung di dalam loop
                    DB::table('N_EMI_LAB_Hasil_Uji_Validasi_Final')
                        ->updateOrInsert(
                            [
                                'No_Split_Po' => $infoPo->No_Split_Po,
                                'No_Batch'    => $infoPo->No_Batch
                            ],
                            [
                                'No_Po'       => $infoPo->No_Po,
                                'No_Sampel'   => $no_sampel,
                                'Tanggal'     => $tanggalSqlServer,
                                'Jam'         => $jamSqlServer,
                                'Flag_FG'     => 'Y',
                                'Flag_Ok'     => $flagOkValue,
                                'Id_User'     => $userId
                            ]
                        );

                    $poSampelUpdateCases[] = $no_sampel;
                    $berhasil[] = $no_sampel;
                } else {
                    $gagal[] = [
                        'sampel' => $no_sampel,
                        'reason' => 'Mesin tidak memiliki Flag_FG Valid.'
                    ];
                }
            }

            if (!empty($poSampelUpdateCases)) {
                DB::table('N_EMI_LAB_PO_Sampel')
                    ->whereIn('No_Sampel', $poSampelUpdateCases)
                    ->update([
                        'Flag_Selesai' => 'Y'
                    ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Proses Finalisasi Bulk Selesai.',
                'result' => [
                    'berhasil' => $berhasil,
                    'gagal' => $gagal
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ], 500);
        }
    }
}
