<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

class ResamplingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return inertia("vue/dashboard/lab/resampling-analisa/HomeResamplingAnalisa");
    }
    public function ViewUjiResampling($no_sampel, $no_fak_sub_sampel, $id_jenis_analisa, $no_resampling)
    {
        return inertia("vue/dashboard/lab/resampling-analisa/UjiResampling", [
            'No_Sampel' => $no_sampel,
            'No_Fak_Sub_Sampel' => $no_fak_sub_sampel,
            'Id_Jenis_Analisa' => $id_jenis_analisa,
            'No_Resampling' => $no_resampling
        ]);
    }

    public function getDataResamplingCurrent(Request $request)
    {
        try {
            $limit = (int) $request->query('limit', 10);
            $page = (int) $request->query('page', 1);
            $offset = ($page - 1) * $limit;

            $search = $request->query('search');
            $status = $request->query('status'); // Y atau NULL
            $tanggalAwal = $request->query('tanggal_awal');
            $tanggalAkhir = $request->query('tanggal_akhir');
            $jenisAnalisa = $request->query('jenis_analisa'); // id jenis analisa

            $query = DB::table("N_EMI_LAB_Uji_Sampel_Resampling_Log as resampling")
                ->join('N_EMI_LAB_PO_Sampel as po', 'resampling.No_Po_Sampel', '=', 'po.No_Sampel')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'resampling.Id_Jenis_Analisa', '=', 'ja.id')
                ->select(
                    'resampling.*',
                    'ja.Jenis_Analisa',
                    'po.No_Po',
                    'po.No_Split_Po',
                    'po.No_Batch'
                );

            // 🔍 Search filter
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('po.No_Po', 'LIKE', "%$search%")
                    ->orWhere('po.No_Split_Po', 'LIKE', "%$search%")
                    ->orWhere('resampling.No_Sampel_Resampling_Origin', 'LIKE', "%$search%")
                    ->orWhere('resampling.No_Sampel_Resampling', 'LIKE', "%$search%")
                    ->orWhere('ja.Jenis_Analisa', 'LIKE', "%$search%");
                });
            }

            // 🎯 Filter status (selesai / belum)
            if ($status === 'Y') {
                $query->where('resampling.Flag_Selesai_Resampling', '=', 'Y');
            } elseif ($status === 'N') {
                $query->whereNull('resampling.Flag_Selesai_Resampling');
            }

            // 📅 Filter tanggal range
            if (!empty($tanggalAwal) && !empty($tanggalAkhir)) {
                $query->whereBetween('resampling.Tanggal', [$tanggalAwal, $tanggalAkhir]);
            }

            // 🧪 Filter Jenis Analisa
            if (!empty($jenisAnalisa)) {
                $query->where('resampling.Id_Jenis_Analisa', $jenisAnalisa);
            }

            // Hitung total
            $total = $query->count();

            // Urutkan: null Flag dulu, lalu terbaru berdasarkan tanggal+jam
            $getData = $query
                ->orderByRaw("CASE WHEN resampling.Flag_Selesai_Resampling IS NULL THEN 0 ELSE 1 END")
                ->orderByRaw("CONCAT(resampling.Tanggal, ' ', resampling.Jam) DESC")
                ->offset($offset)
                ->limit($limit)
                ->get();

            if ($getData->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Data Tidak Ditemukan'
                ], 404);
            }

            $encodedData = $getData->map(function ($item) {
                $item->Id_Resampling = Hashids::connection('custom')->encode($item->Id_Resampling);
                $item->Id_Jenis_Analisa = Hashids::connection('custom')->encode($item->Id_Jenis_Analisa);
                return $item;
            });

            return response()->json([
                'success' => true,
                'status' => 200,
                'result' => $encodedData,
                'page' => $page,
                'total_page' => ceil($total / $limit),
                'total_data' => $total
            ], 200);

        } catch (\Exception $e) {
            Log::channel('ResamplingController')->error('Error: ' . $e->getMessage());
            return response()->json([
                    'success' => true,
                    'status' => 500,
                    'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }



    public function getDetailSampelResampling($No_Sampel_Resampling_Origin, $No_Sampel_Resampling, $Id_Jenis_Analisa) 
    {
          try {
            $Id_Jenis_Analisa = Hashids::connection('custom')->decode($Id_Jenis_Analisa)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Jenis Analisa tidak valid.'
            ], 400);
        }

        $resampling = DB::table('N_EMI_LAB_Uji_Sampel_Resampling_Log')
        ->select('N_EMI_LAB_Uji_Sampel_Resampling_Log.*', 'N_EMI_LAB_PO_Sampel.Id_Mesin', 'N_EMI_LAB_PO_Sampel.Kode_Barang', 'N_EMI_LAB_PO_Sampel.No_Po', 'N_EMI_LAB_PO_Sampel.No_Split_Po', 'N_EMI_LAB_PO_Sampel.No_Batch', 'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa')
        ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Uji_Sampel_Resampling_Log.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
        ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LAB_Uji_Sampel_Resampling_Log.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
        ->where('No_Sampel_Resampling_Origin', $No_Sampel_Resampling_Origin)
        ->where('No_Sampel_Resampling', $No_Sampel_Resampling)
        ->where('Id_Jenis_Analisa', $Id_Jenis_Analisa)
        ->first();

         if ($resampling) {
            // Ubah ke array agar bisa dimodifikasi
            $resampling = (array) $resampling;

            // Encode ID ke hash sebelum dikembalikan
            if (isset($resampling['Id_Jenis_Analisa'])) {
                $resampling['Id_Jenis_Analisa'] = Hashids::connection('custom')->encode($resampling['Id_Jenis_Analisa']);
            }

            if (isset($resampling['Id_Resampling'])) {
                $resampling['Id_Resampling'] = Hashids::connection('custom')->encode($resampling['Id_Resampling']);
            }
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Data Ditemukan !',
            'result' => $resampling
        ]);
    }
}
