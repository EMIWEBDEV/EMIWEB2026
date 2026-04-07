<?php

namespace App\Http\Controllers\FormulatorFinalisasi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FormulatorFinalisasiController extends Controller
{
    public function index()
    {
        return inertia("vue/dashboard/hasil-akhir-validasi/formulator/HasilAkhirValidasi");
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
            Log::channel('FormulatorFinalisasiController')->error('Error: ' . $e->getMessage());
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
            return inertia('vue/dashboard/hasil-akhir-validasi/formulator/HasilAkhirValidasiNoPcsJenisAnalisa', [
                'No_Sampel' => $no_sampel,
            ]);
        }else {
            return inertia('vue/dashboard/hasil-akhir-validasi/formulator/HasilAkhirValidasiPcsJenisAnalisa', [
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

            $getInformasiPo = DB::table('N_LIMS_PO_Sampel')
                ->where('No_Sampel', $no_sampel)
                ->first();

            if (!$getInformasiPo) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Informasi PO tidak ditemukan.'
                ], 404);
            }


            $getData = DB::table('N_EMI_LIMS_Uji_Sampel')
                ->where('No_Po_Sampel', $no_sampel)
                ->whereNull('Flag_Resampling')
                ->whereNull('Status')
                ->get();

            $kodeDikecualikan = ['HOMOGENITAS', 'MBLG-STR', 'PSZ'];

            // $checkedJumlahStandarMutu = DB::table('N_EMI_LAB_Barang_Analisa as ba')
            //     ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ba.Id_Jenis_Analisa', '=', 'ja.id')
            //     ->select('ba.Id_Jenis_Analisa')
            //     ->where('ba.Kode_Role', 'FLM')
            //     ->where('ja.Kode_Role', 'FLM')
            //     ->where('ba.Flag_Aktif', 'Y')
            //     ->where('ba.Kode_Barang', $getInformasiPo->Kode_Barang)
            //     ->where('ba.Id_Master_Mesin', $getInformasiPo->Id_Mesin)
            //     ->whereNotIn('ja.Kode_Analisa', $kodeDikecualikan)
            //     ->groupBy('ba.Kode_Barang', 'ba.Id_Master_Mesin', 'ba.Id_Jenis_Analisa')
            //     ->pluck('ba.Id_Jenis_Analisa')
            //     ->toArray();

            // $checkedJumlahAnalisa = DB::table('N_EMI_LIMS_Uji_Sampel as us')
            //     ->join('N_EMI_LAB_Jenis_Analisa as ja', 'us.Id_Jenis_Analisa', '=', 'ja.id')
            //     ->where('ja.Kode_Role', 'FLM')
            //     ->where('us.No_Po_Sampel', $no_sampel)
            //     ->whereNull('us.Flag_Resampling')
            //     ->whereNull('Status')
            //     ->where('us.Status_Keputusan_Sampel', 'terima')
            //     ->whereNotIn('ja.Kode_Analisa', $kodeDikecualikan)
            //     ->pluck('us.Id_Jenis_Analisa')
            //     ->toArray();

            // $analisaKurang = array_diff($checkedJumlahStandarMutu, $checkedJumlahAnalisa);
            // if (!empty($analisaKurang)) {
            //     $detailKurang = DB::table('N_EMI_LAB_Jenis_Analisa')
            //         ->whereIn('id', $analisaKurang)
            //         ->pluck('Jenis_Analisa');

            //     return response()->json([
            //         'success' => false, 'status' => 422,
            //         'message' => 'Data analisa belum lengkap. Mohon lengkapi analisa berikut sebelum difinalisasi',
            //         'detail' => ['No_Sampel' => $no_sampel, 'Analisa_Kurang' => $detailKurang]
            //     ], 422);
            // }

            $belumSelesai = DB::table('N_EMI_LIMS_Uji_Sampel as us')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'us.Id_Jenis_Analisa', '=', 'ja.id')
                ->where('ja.Kode_Role', 'FLM')
                ->where('us.No_Po_Sampel', $no_sampel)
                ->where('us.Status_Keputusan_Sampel', 'terima')
                ->whereNull('us.Flag_Selesai')
                ->whereNull('Status')
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

           

            DB::table('N_EMI_LIMS_Uji_Sampel')
                    ->where('No_Po_Sampel', $no_sampel)
                    ->whereNull('Status')
                    ->whereNull('Flag_Resampling')
                    ->where('Flag_Selesai', 'Y')
                    ->update([
                        'Flag_Final' => 'Y'
                    ]);

                $adaYangTidakLayak = DB::table('N_EMI_LIMS_Uji_Sampel')
                    ->where('No_Po_Sampel', $no_sampel)
                    ->where('Flag_Layak', 'T')   
                    ->whereNull('Status')          
                    ->whereNull('Flag_Resampling')
                    ->exists();

                $flagOkValue = $adaYangTidakLayak ? 'T' : 'Y';

                $payloadUjiFInal = [
                    'No_Po' => $getInformasiPo->No_Po,
                    'No_Split_Po' => $getInformasiPo->No_Split_Po,
                    'No_Batch' => $getInformasiPo->No_Batch,
                    'No_Sampel' => $no_sampel,
                    'Tanggal' => $tanggalSqlServer,
                    'Jam' => $jamSqlServer,
                    'Flag_FG' => 'Y',
                    'Flag_Ok' => $flagOkValue,
                    'Id_User' => Auth::user()->UserId
                ];

                $cekSudahAda = DB::table('N_EMI_LIMS_Hasil_Uji_Validasi_Final')
                    ->where('No_Split_Po', $getInformasiPo->No_Split_Po)
                    ->where('No_Batch', $getInformasiPo->No_Batch)
                    ->exists();

                if (!$cekSudahAda) {
                    DB::table('N_EMI_LIMS_Hasil_Uji_Validasi_Final')->insert($payloadUjiFInal);
                }

                DB::table('N_LIMS_PO_Sampel')
                    ->where('No_Sampel', $no_sampel)
                    ->update([
                        'Flag_Selesai' => 'Y'
                    ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Data Berhasil Disimpan"
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('FormulatorFinalisasiController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan"
            ], 500);
        }
    }
}
