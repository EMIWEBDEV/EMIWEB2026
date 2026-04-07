<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema; 

class ProgressAnalisaSampelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return inertia("vue/dashboard/progress-sampel-analisa/HomeProgresAnalisa");
    }


    public function getDataCurrent(Request $request)
    {
        try {
            // --- Mengambil parameter dari request ---
            $limit = (int) $request->query('limit', 10);
            $search = $request->query('search');
            $filterStatus = $request->query('status');
            $filterNoSampel = $request->query('no_sampel');
            $filterBatch = $request->query('batch');
            $filterDate = $request->query('date'); 
            $filterPembatalan = $request->query('filter_pembatalan'); 
            $filterTanggalUji = $request->query('filter_tanggal_uji'); 

            $statusQuery = "
                CASE
                    WHEN (
                        sampel.Id_Mesin = 4
                        AND EXISTS (
                            SELECT 1
                            FROM N_EMI_LAB_Hasil_Uji_Validasi_Final v
                            WHERE v.No_Sampel = sampel.No_Sampel
                        )
                        AND EXISTS (
                            SELECT 1
                            FROM N_EMI_LAB_Uji_Sampel u
                            WHERE u.No_Po_Sampel = sampel.No_Sampel
                            AND u.Flag_Final = 'Y'
                        )
                    )
                    OR (
                        sampel.Id_Mesin != 4
                        AND EXISTS (
                            SELECT 1
                            FROM N_EMI_LAB_Uji_Sampel u
                            WHERE u.No_Po_Sampel = sampel.No_Sampel
                            AND u.Flag_Final = 'Y'
                        )
                    )
                    THEN 'Selesai (Analisa Final dan Siap Digunakan)'

                    WHEN EXISTS (
                        SELECT 1
                        FROM N_EMI_LAB_Uji_Sampel_Resampling_Log r
                        WHERE r.No_Po_Sampel = sampel.No_Sampel
                        AND (r.Flag_Selesai_Resampling IS NULL OR r.Flag_Selesai_Resampling != 'Y')
                    )
                    THEN 'Sedang Dalam Proses Re-Analisa (Resample)'

                    WHEN EXISTS (
                        SELECT 1
                        FROM N_EMI_LAB_Uji_Sampel u
                        WHERE u.No_Po_Sampel = sampel.No_Sampel
                    )
                    AND NOT EXISTS (
                        SELECT 1
                        FROM N_EMI_LAB_Uji_Sampel u
                        WHERE u.No_Po_Sampel = sampel.No_Sampel
                        AND (u.Flag_Selesai != 'Y'
                            OR LOWER(u.Status_Keputusan_Sampel) = 'menunggu')
                    )
                    THEN 'Menunggu Finalisasi Data Analisa'

                    WHEN EXISTS (
                        SELECT 1
                        FROM N_EMI_LAB_Uji_Sampel u
                        WHERE u.No_Po_Sampel = sampel.No_Sampel
                    )
                    THEN 'Dalam Proses (Sebagian masih diuji dan sebagian menunggu validasi)'

                    ELSE 'Belum Dimulai (Menunggu Uji Analisa)'
                END
            ";

            $priorityQuery = "
                CASE Status
                    WHEN 'Menunggu Finalisasi Data Analisa' THEN 1
                    WHEN 'Dalam Proses (Sebagian masih diuji dan sebagian menunggu validasi)' THEN 2
                    WHEN 'Sedang Dalam Proses Re-Analisa (Resample)' THEN 3
                    ELSE 999
                END
            ";
            
            $sampelTableName = 'N_EMI_LAB_PO_Sampel';
            $allColumns = Schema::getColumnListing($sampelTableName);
            $columnsToSelect = array_diff($allColumns, ['Status', 'status']);
            $prefixedColumns = array_map(function ($column) {
                return 'sampel.' . $column;
            }, $columnsToSelect);

            $innerQuery = DB::table("{$sampelTableName} as sampel")
                        ->leftJoin("EMI_Master_Mesin as mesin", "sampel.Id_Mesin", "=", "mesin.Id_Master_Mesin")
                        ->select($prefixedColumns)
                        ->addSelect(DB::raw("($statusQuery) as Status"))
                        ->addSelect("mesin.Nama_Mesin");

            $innerQuery->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('N_EMI_LAB_Uji_Sampel as uji')
                    ->whereColumn('uji.No_Po_Sampel', 'sampel.No_Sampel')
                    ->whereRaw("LOWER(uji.Status_Keputusan_Sampel) = 'tolak'");
            });

            if ($filterPembatalan === 'tampilkan') {
                $innerQuery->where('sampel.Status', 'Y');
            } else {
                $innerQuery->whereNull('sampel.Status');
            }

            if ($filterNoSampel) {
                $innerQuery->where('sampel.No_Sampel', 'like', '%' . $filterNoSampel . '%');
            }
            if ($filterBatch) {
                $innerQuery->where('sampel.No_Batch', $filterBatch);
            }
            if ($filterDate) {
                $innerQuery->whereDate('sampel.Tanggal', $filterDate);
            }

            if ($filterTanggalUji) {
                $innerQuery->whereExists(function ($query) use ($filterTanggalUji) {
                    $query->select(DB::raw(1))
                        ->from('N_EMI_LAB_Uji_Sampel as uji')
                        ->whereColumn('uji.No_Po_Sampel', 'sampel.No_Sampel')
                        ->whereDate('uji.Tanggal', $filterTanggalUji);
                });
            }
            
            if ($search) {
                $innerQuery->where(function ($q) use ($search) {
                    $q->where('sampel.No_Po', 'like', '%' . $search . '%')
                    ->orWhere('sampel.No_Split_Po', 'like', '%' . $search . '%')
                    ->orWhere('sampel.Kode_Barang', 'like', '%' . $search . '%')
                    ->orWhere('sampel.No_Sampel', 'like', '%' . $search . '%')
                    ->orWhere('sampel.No_Batch', 'like', '%' . $search . '%');
                });
            }

            $outerQuery = DB::query()->fromSub($innerQuery, 'results');

            if ($filterStatus) {
                $outerQuery->where('Status', 'like', '%' . $filterStatus . '%');
            }
            
            $paginatedData = $outerQuery->orderByRaw($priorityQuery)
                                        ->orderBy('Tanggal', 'desc')
                                        ->paginate($limit);

            return response()->json([
                'success' => true,
                'status'  => 200,
                'result'  => $paginatedData->items(),
                'page' => $paginatedData->currentPage(),
                'total_data' => $paginatedData->total(),
                'total_page' => $paginatedData->lastPage()
            ], 200);

        } catch (\Exception $e) {
                Log::channel('ProgressAnalisaSampelController')->error('Error: ' . $e->getMessage());
                return response()->json([
                    'success' => true,
                    'status' => 500,
                    'message' => "Terjadi Kesalahan",
                ], 500); 
        }
    }
  
    
}
