<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Vinkla\Hashids\Facades\Hashids;
use Illuminate\Support\Facades\Log;

class QuisyController extends Controller
{
    public function timbangan()
    {
        return inertia("vue/TestingTimbangan");
    }

    public function index()
    {
        $NamaPengguna = Auth::user()->Nama;
        $sampelData = DB::table('N_EMI_LAB_PO_Sampel')->get();
        $lastActivity = $sampelData->last(); 
        $lastHistory = DB::table('N_EMI_LAB_Printer_Template_Transaksi')
        ->join('N_EMI_LAB_Master_Printer_Templates', 'N_EMI_LAB_Printer_Template_Transaksi.Id_Master_Printer_Templates', '=', 'N_EMI_LAB_Master_Printer_Templates.Id_Master_Printer_Templates')
        ->where('N_EMI_LAB_Printer_Template_Transaksi.Flag_Default', 'Y')
        ->where('N_EMI_LAB_Printer_Template_Transaksi.Flag_Aktif', 'Y')
        ->orderBy('N_EMI_LAB_Printer_Template_Transaksi.Id_Template_Transaksi', 'desc')
        ->first();
        

        if ($lastActivity && $lastActivity->Tanggal) {
            $tanggalAsli = $lastActivity->Tanggal;

            try {
                $tanggalFix = preg_replace('/:(AM|PM)$/', ' $1', $tanggalAsli);
                $lastActivityFormatted = Carbon::parse($tanggalFix)->format('d M Y');
            } catch (\Exception $e) {
                $lastActivityFormatted = null;
            }
        } else {
            $lastActivityFormatted = null;
        }
  
        $implicitlyCompletedCount = DB::table('N_EMI_View_Order_Produksi')
            ->where('Tanggal', '<', '2025-08-16')
            ->whereNull('status') 
            ->count();

        $explicitlyCompletedCount = DB::table('N_EMI_View_Order_Produksi')
            ->where('Tanggal', '>=', '2025-08-16')
            ->whereNull('status') 
            ->whereIn('No_Faktur', function ($query) {
                $query->select('No_Po')
                    ->from('N_EMI_LAB_PO_Sampel')
                    ->where('Flag_Close_Po', 'Y')
                    ->orWhere('Flag_Selesai', 'Y'); 
            })
            ->count();
        $completedPoCount = $implicitlyCompletedCount + $explicitlyCompletedCount;

        $uncompletedPoCount = DB::table('N_EMI_View_Order_Produksi')
            ->whereNull('status')
            ->where('Tanggal', '>=', '2025-08-16') 
            ->whereNotIn('No_Faktur', function ($query) {
                $query->select('No_Po')
                    ->from('N_EMI_LAB_PO_Sampel')
                    ->where('Flag_Close_Po', 'Y')
                    ; 
            })
            ->count();

        return inertia('vue/quisy/HomeQuisy', [
            'lastActivity' => $lastActivityFormatted,
            'pengguna' => $NamaPengguna,
            'po_selesai' => $completedPoCount,
            'po_belum_selesai' => $uncompletedPoCount,
            'lastHistory' => $lastHistory,
            'url_client' => rtrim(env('URL_CLIENT'), '/'),
            'url_timbangan' => rtrim(env('URL_TIMBANGAN'), '/'),
        ]);
    }


    public function getPoListWithCompletionStatus($computer_keys)
    {

        $identity = DB::table('N_EMI_LAB_Identity')
            ->select('id')
            ->where('Computer_Keys', $computer_keys)
            ->first();

        if (!$identity) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Computer_Keys tidak ditemukan.',
            ], 404);
        }

        // Ambil mesin terkait identity
        $idMesinList = DB::table('N_EMI_LAB_Binding_Identity')
            ->where('Id_Identity', $identity->id)
            ->pluck('Id_Mesin')
            ->toArray();

        if (empty($idMesinList)) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Mesin tidak ditemukan untuk komputer ini.',
            ], 404);
        }

        $totalMesin = count($idMesinList);

        // Ambil data PO (seluruhnya)
        $poList = DB::table('N_EMI_View_Order_Produksi as po')
            ->join('N_EMI_View_Barang as b', function($join) {
                $join->on('po.Kode_Perusahaan', '=', 'b.Kode_Perusahaan')
                    ->on('po.Kode_Stock_Owner', '=', 'b.Kode_Stock_Owner')
                    ->on('po.Kode_Barang', '=', 'b.Kode_Barang');
            })
            ->select('po.No_Faktur', 'po.Jumlah', 'po.Satuan', 'po.Tanggal', 'po.Kode_Barang', 'b.Nama')
            ->whereNull('po.status')
            ->get();

        // Kalau memang tidak ada PO sama sekali
        if ($poList->isEmpty()) {
            return response()->json([
                'success' => true,
                'status' => 204,
                'message' => 'Tidak ada data PO sama sekali.',
                'data' => [],
            ], 204);
        }

        // Ambil split_po untuk PO yang diambil
        $splitOrders = DB::table('N_EMI_View_Split_Production_Order')
            ->select('No_PO', 'No_Transaksi', 'Jumlah', 'Jumlah_Batch')
            ->whereIn('No_PO', $poList->pluck('No_Faktur'))
            ->whereNull('Status')
            ->get();

        // Ambil data sampel PO
        $sampelData = DB::table('N_EMI_LAB_PO_Sampel')
            ->select('No_Split_Po', 'No_Batch', DB::raw('count(distinct Id_Mesin) as mesin_input'))
            ->whereIn('No_Split_Po', $splitOrders->pluck('No_Transaksi'))
            ->whereIn('Id_Mesin', $idMesinList)
            ->groupBy('No_Split_Po', 'No_Batch')
            ->get()
            ->groupBy('No_Split_Po');

        // Cek kelengkapan tiap split_po
        $splitPoCompletion = [];
        foreach ($splitOrders as $split) {
            $jumlahBatch = (int) $split->Jumlah_Batch;
            $sampel = $sampelData[$split->No_Transaksi] ?? collect();

            $completeCount = 0;
            for ($i = 1; $i <= $jumlahBatch; $i++) {
                $batch = $sampel->firstWhere('No_Batch', $i);
                if ($batch && $batch->mesin_input >= $totalMesin) {
                    $completeCount++;
                }
            }

            $splitPoCompletion[$split->No_PO][] = $completeCount === $jumlahBatch;
        }

        // Tandai PO is_complete true jika semua split_po complete
        $finalResult = [];
        foreach ($poList as $po) {
            $splitCompletion = $splitPoCompletion[$po->No_Faktur] ?? [];

            $isComplete = !empty($splitCompletion) && collect($splitCompletion)->every(fn($v) => $v === true);

            if (!$isComplete) {
                $finalResult[] = [
                    'No_Faktur' => $po->No_Faktur,
                    'Jumlah' => $po->Jumlah,
                    'Satuan' => $po->Satuan,
                    'Tanggal' => $po->Tanggal,
                    'Kode_Barang' => $po->Kode_Barang,
                    'Nama' => $po->Nama,
                    'is_complete' => false,
                ];
            }
        }

       
        if (empty($finalResult)) {
            return response()->json([
                'success' => true,
                'status' => 204,  
                'message' => 'Semua PO sudah lengkap (is_complete true), tidak ada data tersisa.',
                'data' => [],
            ], 204);
        }
        
        usort($finalResult, function ($a, $b) {
            return strtotime($b['Tanggal']) <=> strtotime($a['Tanggal']);
        });


        // Ada PO yang belum complete
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Data PO berhasil diambil, terdapat PO yang belum lengkap.',
            'data' => $finalResult,
        ]);
    }
    
    public function getPoListWithCompletionStatusV2($computer_keys)
    {
        // Validasi Computer Keys
        $identity = DB::table('N_EMI_LAB_Identity')
            ->select('id')
            ->where('Computer_Keys', $computer_keys)
            ->first();

        if (!$identity) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Computer_Keys tidak ditemukan.',
            ], 404);
        }

        // Ambil mesin terkait identity
        $idMesinList = DB::table('N_EMI_LAB_Binding_Identity')
            ->where('Id_Identity', $identity->id)
            ->pluck('Id_Mesin')
            ->toArray();

        if (empty($idMesinList)) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Mesin tidak ditemukan untuk komputer ini.',
            ], 404);
        }
        // Ambil data PO (seluruhnya)
        $poList = DB::table('N_EMI_View_Order_Produksi as po')
            ->join('N_EMI_View_Barang as b', function ($join) {
                $join->on('po.Kode_Perusahaan', '=', 'b.Kode_Perusahaan')
                    ->on('po.Kode_Stock_Owner', '=', 'b.Kode_Stock_Owner')
                    ->on('po.Kode_Barang', '=', 'b.Kode_Barang');
            })
            ->select('po.No_Faktur', 'po.Jumlah', 'po.Satuan', 'po.Tanggal', 'po.Kode_Barang', 'b.Nama')
            ->where('po.Tanggal', '>=', '2025-08-16')
            ->whereNull('po.status')
            ->whereNotIn('po.No_Faktur', function ($query) {
                $query->select('No_Po')
                    ->from('N_EMI_LAB_PO_Sampel')
                    ->where('Flag_Close_Po', 'Y');
            })
            // ->whereNotIn('po.No_Faktur', ['PRD0825-00051', 'PRD0825-00039'])
            ->orderBy('po.Tanggal', 'asc')
            ->orderBy('po.No_Faktur', 'asc')
            ->get();

        if ($poList->isEmpty()) {
            return response()->json([
                'success' => true,
                'status' => 204,
                'message' => 'Tidak ada data PO sama sekali.',
                'data' => [],
            ], 204);
        }

        $poNumbers = $poList->pluck('No_Faktur')->toArray();

        $poWithSamples = DB::table('N_EMI_LAB_PO_Sampel')
            ->whereIn('No_Po', $poNumbers)
            ->distinct()
            ->pluck('No_Po')
            ->all();

        $processedPoList = $poList->map(function ($po, $index) use ($poWithSamples) {
            $po->is_selectable = true;
            $po->has_samples = in_array($po->No_Faktur, $poWithSamples);
            return $po;
        });

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Data PO berhasil diambil, terdapat PO yang belum lengkap.',
            'data' => $processedPoList,
        ]);
    }

    public function closePoByProduksi(Request $request)
    {
        $pengguna = Auth::user()->UserId;
        DB::beginTransaction();

        try {

            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow; 
            $tanggalSqlServer = date('Y-m-d', strtotime($dt)); 
            $jamSqlServer = date('H:i:s', strtotime($dt));   

            $noPo = $request->input('No_Po');
            $getData = DB::table('N_EMI_LAB_PO_Sampel')
                        ->where('No_Po', $noPo)
                        ->first();

            $affectedRows = DB::table('N_EMI_LAB_PO_Sampel')
                ->where('No_Po', $noPo)
                ->update(
                    [
                        'Tanggal_Close_Po' => $tanggalSqlServer,
                        'Jam_Close_Po' => $jamSqlServer,
                        'Flag_Close_Po' => 'Y'
                    ]
                );

            if ($affectedRows === 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'PO tidak ditemukan atau sudah ditutup sebelumnya.',
                ], 404);
            }

            $payloadActivityProduksiSampel = [
                'No_Po' => $getData->No_Po,
                'Jenis_Aktivitas' => "Penutupan PO Untuk Pengambilan Sampel",
                'Status_Aktivitas' => 'berhasil',
                'Keterangan' => 'Untuk Nomor Po '. $getData->No_Po. ' Sudah Dinyatakan Selesai Dalam Mengambil Seluruh Sampel Untuk Kebutuhan Lab. Sehingga Untuk Nomor Production Order Ini Sudah Di Tutup',
                'Tanggal' => $tanggalSqlServer,
                'Jam' => $jamSqlServer,
                'Id_User' => $pengguna,
                'Flag_Berhasil_Cetak_QrCode' => null,
            ];
                    
            DB::table('N_EMI_LAB_Activity_Produksi_Sampel')->insert($payloadActivityProduksiSampel);

            // Jika berhasil, commit transaksi
            DB::commit();

            // 4. Kirim Respons Sukses
            return response()->json([
                'success' => true,
                'message' => 'PO dengan nomor ' . $noPo . ' berhasil ditutup.'
            ], 200); // OK

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('ProgressAnalisaSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                    'success' => true,
                    'status' => 500,
                    'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }

    public function getMachinesByComputerKeys($computerKey, $noSplitPo, $noBatch)
    {
        if (is_null($computerKey) || is_null($noSplitPo) || is_null($noBatch)) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => "Parameter tidak lengkap"
            ], 400);
        }

        $query = "
            SELECT 
                mesin.Id_Master_Mesin,
                mesin.Nama_Mesin,
                mesin.Seri_Mesin,
                mesin.Flag_Multi_Qrcode,
                mesin.Jumlah_Print_QRCode,
                mesin.Flag_Kg,
                (
                    SELECT COUNT(*) 
                    FROM N_EMI_LAB_PO_Sampel AS po
                    WHERE 
                        po.No_Split_Po = ? 
                        AND po.No_Batch = ? 
                        AND po.Id_Mesin = mesin.Id_Master_Mesin
                ) AS total_input_count
            FROM 
                N_EMI_LAB_Binding_Identity AS binding
            JOIN 
                EMI_Master_Mesin AS mesin ON binding.Id_Mesin = mesin.Id_Master_Mesin
            JOIN 
                N_EMI_LAB_Identity AS identity_tbl ON binding.Id_Identity = identity_tbl.id
            WHERE 
                identity_tbl.Computer_Keys = ?
            GROUP BY 
                mesin.Id_Master_Mesin,
                mesin.Nama_Mesin,
                mesin.Seri_Mesin,
                mesin.Flag_Multi_Qrcode,
                mesin.Jumlah_Print_QRCode,
                mesin.Flag_Kg
        ";
        $mesinList = DB::select($query, [$noSplitPo, $noBatch, $computerKey]);

        if (empty($mesinList)) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => "Data tidak ditemukan"
            ], 404);
        }

        $encodedData = collect($mesinList)->map(function ($item) {
            $item->Id_Master_Mesin = Hashids::connection('custom')->encode($item->Id_Master_Mesin);
            $item->is_already_input = $item->total_input_count > 0;
            return $item;
        });

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => "Data Berhasil Diambil",
            'data' => $encodedData
        ], 200);
    }


    public function getSplitPo($id, $computer_keys)
    {
        // FRANS
        //  Validasi komputer key
        $identity = DB::table('N_EMI_LAB_Identity')
            ->select('id')
            ->where('Computer_Keys', $computer_keys)
            ->first();

        if (!$identity) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Computer_Keys tidak ditemukan.',
            ], 404);
        }

        // 2. Ambil semua Id_Mesin terkait identity
        $idMesinList = DB::table('N_EMI_LAB_Binding_Identity')
            ->where('Id_Identity', $identity->id)
            ->pluck('Id_Mesin')
            ->toArray();

        if (empty($idMesinList)) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Mesin tidak ditemukan untuk komputer ini.',
            ], 404);
        }

        $totalMesin = count($idMesinList);

        // 3. Ambil semua split PO sekaligus dengan batch info
        $splitOrders = DB::table('N_EMI_View_Split_Production_Order as po')
            ->select('po.No_PO', 'po.No_Transaksi', 'po.Jumlah', 'po.Jumlah_Batch', 'po.Kode_Barang', 'po.Tanggal')
            ->where('po.No_PO', $id)
            ->get();

        if ($splitOrders->isEmpty()) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Data tidak ditemukan.',
            ], 404);
        }

        // 4. Ambil semua data PO_Sampel sekaligus (hindari query dalam loop)
        $sampelData = DB::table('N_EMI_LAB_PO_Sampel')
            ->select('No_Split_Po', 'No_Batch', DB::raw('count(distinct Id_Mesin) as mesin_input'))
            ->whereIn('No_Split_Po', $splitOrders->pluck('No_Transaksi')->toArray())
            ->whereIn('Id_Mesin', $idMesinList)
            ->groupBy('No_Split_Po', 'No_Batch')
            ->get()
            ->groupBy('No_Split_Po');

        // 5. Proses hasil akhir
        $result = [];
        foreach ($splitOrders as $splitOrder) {
            $jumlahBatch = (int) $splitOrder->Jumlah_Batch;
            $completeCount = 0;

            $sampelPerTransaksi = $sampelData[$splitOrder->No_Transaksi] ?? collect();

            for ($i = 1; $i <= $jumlahBatch; $i++) {
                $batch = $sampelPerTransaksi->firstWhere('No_Batch', $i);
                if ($batch && $batch->mesin_input >= $totalMesin) {
                    $completeCount++;
                }
            }

            $result[] = [
                'No_PO' => $splitOrder->No_PO,
                'No_Transaksi' => $splitOrder->No_Transaksi,
                'Tanggal' => $splitOrder->Tanggal,
                'Jumlah' => $splitOrder->Jumlah,
                'is_complete' => $completeCount === $jumlahBatch,
                'Kode_Barang' => $splitOrder->Kode_Barang
            ];
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Data berhasil diambil.',
            'data' => $result,
        ], 200);
    }

    public function getTotalPOBelumSelesai($computer_keys)
    {
        // Validasi Computer Keys
        $identity = DB::table('N_EMI_LAB_Identity')
            ->select('id')
            ->where('Computer_Keys', $computer_keys)
            ->first();

        if (!$identity) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Computer_Keys tidak ditemukan.',
            ], 404);
        }

        // Ambil mesin terkait identity
        $idMesinList = DB::table('N_EMI_LAB_Binding_Identity')
            ->where('Id_Identity', $identity->id)
            ->pluck('Id_Mesin')
            ->toArray();

        if (empty($idMesinList)) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Mesin tidak ditemukan untuk komputer ini.',
            ], 404);
        }

        $totalMesin = count($idMesinList);

        // Ambil data PO (seluruhnya)
        $poList = DB::table('N_EMI_View_Order_Produksi as po')
            ->join('N_EMI_View_Barang as b', function($join) {
                $join->on('po.Kode_Perusahaan', '=', 'b.Kode_Perusahaan')
                    ->on('po.Kode_Stock_Owner', '=', 'b.Kode_Stock_Owner')
                    ->on('po.Kode_Barang', '=', 'b.Kode_Barang');
            })
            ->select('po.No_Faktur', 'po.Jumlah', 'po.Tanggal', 'po.Kode_Barang', 'b.Nama')
            ->whereNull('po.status')
            ->get();

        // Kalau memang tidak ada PO sama sekali
        if ($poList->isEmpty()) {
            return response()->json([
                'success' => true,
                'status' => 204,
                'message' => 'Tidak ada data PO sama sekali.',
                'data' => [],
                'count' => 0, // tambah count = 0
            ], 204);
        }

        // Ambil split_po untuk PO yang diambil
        $splitOrders = DB::table('N_EMI_View_Split_Production_Order')
            ->select('No_PO', 'No_Transaksi', 'Jumlah', 'Jumlah_Batch')
            ->whereIn('No_PO', $poList->pluck('No_Faktur'))
            ->whereNull('status')
            ->get();

        // Ambil data sampel PO
        $sampelData = DB::table('N_EMI_LAB_PO_Sampel')
            ->select('No_Split_Po', 'No_Batch', DB::raw('count(distinct Id_Mesin) as mesin_input'))
            ->whereIn('No_Split_Po', $splitOrders->pluck('No_Transaksi'))
            ->whereIn('Id_Mesin', $idMesinList)
            ->groupBy('No_Split_Po', 'No_Batch')
            ->get()
            ->groupBy('No_Split_Po');

        // Cek kelengkapan tiap split_po
        $splitPoCompletion = [];
        foreach ($splitOrders as $split) {
            $jumlahBatch = (int) $split->Jumlah_Batch;
            $sampel = $sampelData[$split->No_Transaksi] ?? collect();

            $completeCount = 0;
            for ($i = 1; $i <= $jumlahBatch; $i++) {
                $batch = $sampel->firstWhere('No_Batch', $i);
                if ($batch && $batch->mesin_input >= $totalMesin) {
                    $completeCount++;
                }
            }

            $splitPoCompletion[$split->No_PO][] = $completeCount === $jumlahBatch;
        }

        $finalResult = [];
        foreach ($poList as $po) {
            $splitCompletion = $splitPoCompletion[$po->No_Faktur] ?? [];

            $isComplete = !empty($splitCompletion) && collect($splitCompletion)->every(fn($v) => $v === true);

            if (!$isComplete) {
                $finalResult[] = [
                    'No_Faktur' => $po->No_Faktur,
                    'Jumlah' => $po->Jumlah,
                    'Tanggal' => $po->Tanggal,
                    'Kode_Barang' => $po->Kode_Barang,
                    'Nama' => $po->Nama,
                    'is_complete' => false,
                ];
            }
        }

        $countBelumSelesai = count($finalResult) ?? 0 ;

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Data Ditemukan !',
           
            'data' => $countBelumSelesai,
        ]);
    }
    public function getTotalSPPOBelumSelesai($computer_keys)
    {
        // 1. Cari ID Identity
        $identityId = DB::table('N_EMI_LAB_Identity')
            ->where('Computer_Keys', $computer_keys)
            ->value('id');

        if (!$identityId) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Computer_Keys tidak ditemukan.',
            ], 404);
        }

        // 2. Ambil semua Id_Mesin terkait identity
        $idMesinList = DB::table('N_EMI_LAB_Binding_Identity')
            ->where('Id_Identity', $identityId)
            ->pluck('Id_Mesin');

        if ($idMesinList->isEmpty()) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Mesin tidak ditemukan untuk komputer ini.',
            ], 404);
        }

        $totalMesin = $idMesinList->count();

        // 3. Ambil Split Order dengan PO Sampel yang berelasi langsung
        $splitOrders = DB::table('N_EMI_View_Split_Production_Order as po')
            ->select('po.No_PO', 'po.No_Transaksi', 'po.Jumlah', 'po.Jumlah_Batch')
            ->whereNull('po.status')
            ->get();

        if ($splitOrders->isEmpty()) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Data tidak ditemukan.',
            ], 404);
        }

        $noTransaksiList = $splitOrders->pluck('No_Transaksi');

        // 4. Ambil semua data PO_Sampel sekaligus lalu dikelompokkan
        $sampelData = DB::table('N_EMI_LAB_PO_Sampel')
            ->select('No_Split_Po', 'No_Batch', DB::raw('count(distinct Id_Mesin) as mesin_input'))
            ->whereIn('No_Split_Po', $noTransaksiList)
            ->whereIn('Id_Mesin', $idMesinList)
            ->groupBy('No_Split_Po', 'No_Batch')
            ->get()
            ->groupBy('No_Split_Po');

        // 5. Loop hasil split dan hitung mana yang belum selesai
        $belumSelesaiCount = 0;

        foreach ($splitOrders as $order) {
            $sampel = $sampelData[$order->No_Transaksi] ?? collect();
            $jumlahBatch = (int) $order->Jumlah_Batch;
            $complete = 0;
            $groupedBatch = $sampel->keyBy('No_Batch');

            for ($i = 1; $i <= $jumlahBatch; $i++) {
                if (
                    isset($groupedBatch[$i]) &&
                    $groupedBatch[$i]->mesin_input >= $totalMesin
                ) {
                    $complete++;
                }
            }

            if ($complete < $jumlahBatch) {
                $belumSelesaiCount++;
            }
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Data berhasil diambil.',
            'data' => $belumSelesaiCount,
        ], 200);
    }

    public function getBatchPo($no_transaksi, $computer_keys)
    {
        if (empty($no_transaksi) || empty($computer_keys)) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'No_Transaksi dan Computer_Keys harus diisi.',
            ], 400);
        }

        // 1. Validasi komputer key dan ambil id identity
        $identity = DB::table('N_EMI_LAB_Identity')
            ->select('id')
            ->where('Computer_Keys', $computer_keys)
            ->first();

        if (!$identity) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Computer_Keys tidak ditemukan.',
            ], 404);
        }

        $id_identity = $identity->id;

        // 2. Ambil data batch split production order
        $batchSP = DB::table('N_EMI_View_Split_Production_Order')
            ->select('No_PO', 'No_Transaksi', 'Jumlah_Batch')
            ->where('No_Transaksi', $no_transaksi)
            // ->whereNull('status')
            ->first();

        if (!$batchSP) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Data tidak ditemukan.',
            ], 404);
        }

        // 3. Ambil list mesin untuk identity
        $idMesinList = DB::table('N_EMI_LAB_Binding_Identity')
            ->where('Id_Identity', $id_identity)
            ->pluck('Id_Mesin')
            ->toArray();

        $totalMesin = count($idMesinList);

        // 4. Ambil semua data PO Sampel sekaligus, group by No_Batch dengan hitungan mesin input
        $sampelData = DB::table('N_EMI_LAB_PO_Sampel')
            ->select('No_Batch', DB::raw('COUNT(DISTINCT Id_Mesin) as mesin_input'))
            ->where('No_Split_Po', $batchSP->No_Transaksi)
            ->whereIn('Id_Mesin', $idMesinList)
            ->groupBy('No_Batch')
            ->get()
            ->keyBy('No_Batch');  // supaya akses by No_Batch mudah

        // 5. Proses hasil batch
        $data = [];
        for ($i = 1; $i <= (int) $batchSP->Jumlah_Batch; $i++) {
            $mesin_input = $sampelData[$i]->mesin_input ?? 0;
            $is_complete = $mesin_input >= $totalMesin;

            $data[] = [
                'No_Batch' => $i,
                'No_PO' => $batchSP->No_PO,
                'No_Transaksi' => $batchSP->No_Transaksi,
                'Jumlah_Batch' => $batchSP->Jumlah_Batch,
                'is_complete' => $is_complete,
            ];
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Data berhasil diambil.',
            'data' => $data,
        ], 200);
    }

    public function HalamanCtkUlangQrcode()
    {
        return inertia("vue/quisy/CetakUlangQrCode");
    }

    public function getDataHistoriRegistrasiSampel(Request $request)
    {
        try {
            $limit = max(1, (int) $request->query('limit', 10));
            $page = max(1, (int) $request->query('page', 1));
            $offset = ($page - 1) * $limit;
            $search = trim($request->query('searchQuery', ''));
            $date = trim($request->query('dateFilter', ''));
            $po = trim($request->query('poFilter', ''));
            $status = trim($request->query('statusFilter', ''));
            $machine = trim($request->query('machineFilter', '')); 

            $baseQuery = DB::table('N_EMI_LAB_PO_Sampel as pos')
                ->join('EMI_Master_Mesin as mm', function ($join) {
                    $join->on('pos.kode_perusahaan', '=', 'mm.kode_perusahaan')
                        ->on('pos.id_mesin', '=', 'mm.id_master_mesin');
                })
                ->leftJoin('N_EMI_View_Order_Produksi as po', function ($join) {
                    $join->on('pos.No_Po', '=', 'po.No_Faktur')
                        ->on('pos.Kode_Perusahaan', '=', 'po.Kode_Perusahaan');
                })
                ->leftJoin('N_EMI_View_Barang as b', function ($join) {
                    $join->on('po.Kode_Perusahaan', '=', 'b.Kode_Perusahaan')
                        ->on('po.Kode_Stock_Owner', '=', 'b.Kode_Stock_Owner')
                        ->on('po.Kode_Barang', '=', 'b.Kode_Barang');
                });

            if ($search) {
                $baseQuery->where(function ($query) use ($search) {
                    $searchTerm = '%' . strtolower($search) . '%';
                    $query->whereRaw('LOWER(pos.No_Sampel) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(pos.No_Split_Po) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(pos.No_Po) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(mm.Nama_Mesin) LIKE ?', [$searchTerm]); 
                });
            }

            if ($date) {
                $baseQuery->where('pos.Tanggal', '=', $date);
            }

            if ($po) {
                $baseQuery->where(function ($query) use ($po) {
                    $poTerm = '%' . $po . '%';
                    $query->where('pos.No_Po', 'LIKE', $poTerm)
                        ->orWhere('pos.No_Split_Po', 'LIKE', $poTerm);
                });
            }

            if ($machine) {
                $baseQuery->where('mm.Nama_Mesin', 'LIKE', '%' . $machine . '%');
            }

            if ($status === 'dibatalkan') {
                $baseQuery->where('pos.Status', '=', 'Y');
            } elseif ($status === 'terdaftar') {
                $baseQuery->where(function ($query) {
                    $query->where('pos.Status', '!=', 'Y')->orWhereNull('pos.Status');
                });
            }

            $total = $baseQuery->clone()->count();

            if ($total === 0) {
                return response()->json([
                    'success' => true, 
                    'status' => 200, 
                    'message' => 'Data Tidak Ditemukan',
                    'result' => [], 
                    'page' => 1, 
                    'total_page' => 0, 
                    'total_data' => 0, 
                    'status_counts' => ['Terdaftar' => 0, 'Dibatalkan' => 0]
                ], 200);
            }

            $statusCountsResult = $baseQuery->clone()
                ->selectRaw("CASE WHEN pos.Status = 'Y' THEN 'Dibatalkan' ELSE 'Terdaftar' END as Status, COUNT(*) as total")
                ->groupBy('pos.Status')->get();

            $statusCounts = ['Terdaftar' => 0, 'Dibatalkan' => 0];
            foreach ($statusCountsResult as $row) {
                $statusCounts[$row->Status] = $row->total;
            }

            $mainData = $baseQuery->clone()
                ->select(
                    'pos.No_Po',
                    'pos.No_Sampel', 'pos.Status', 'pos.Tanggal', 
                    'pos.Jam', 'pos.No_Split_Po', 'pos.No_Batch',
                    'pos.Keterangan', 'pos.Id_User as Petugas', 
                    'pos.Berat_Sampel', 'pos.Jumlah_Pcs', 'pos.Id_Mesin', 'mm.Nama_Mesin', 
                    'mm.Seri_Mesin', 'mm.Flag_Multi_QrCode', 
                    'b.Nama as Nama_Barang'
                )
                ->orderBy('pos.Tanggal', 'DESC')->orderBy('pos.Jam', 'DESC')
                ->offset($offset)->limit($limit)->get();

            // Ambil anak QR Code
            $noSampels = $mainData->pluck('No_Sampel')->unique()->all();
            $children = DB::table('N_EMI_LAB_PO_Sampel_Multi_QrCode')
                ->whereIn('No_Po_Sampel', $noSampels)
                ->get()->groupBy('No_Po_Sampel');

            // Ambil tracking berdasarkan No_Po
            $noPos = $mainData->pluck('No_Po')->unique()->all();
            $trackingData = DB::table('N_EMI_LAB_Activity_Produksi_Sampel')
                ->select(
                    'N_EMI_LAB_Activity_Produksi_Sampel.No_Po', 
                    'N_EMI_LAB_Activity_Produksi_Sampel.No_Split_Po',
                    'N_EMI_LAB_Activity_Produksi_Sampel.No_Batch', 
                    'N_EMI_LAB_Activity_Produksi_Sampel.Jenis_Aktivitas', 
                    'N_EMI_LAB_Activity_Produksi_Sampel.Status_Aktivitas', 
                    'N_EMI_LAB_Activity_Produksi_Sampel.Keterangan', 
                    'N_EMI_LAB_Activity_Produksi_Sampel.Tanggal', 
                    'N_EMI_LAB_Activity_Produksi_Sampel.Jam', 
                    'N_EMI_LAB_Activity_Produksi_Sampel.Flag_Berhasil_Cetak_QrCode', 
                    'N_EMI_LAB_Activity_Produksi_Sampel.Id_User As Petugas',
                    'EMI_Master_Mesin.Nama_Mesin'
                 )
                ->whereIn('No_Po', $noPos)
                ->leftJoin('EMI_Master_Mesin', 'N_EMI_LAB_Activity_Produksi_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
                ->orderBy('Tanggal', 'DESC')->orderBy('Jam', 'DESC')
                ->get()
                ->groupBy('No_Po');

            // Mapping final
            $finalResult = $mainData->map(function ($item) use ($children, $trackingData) {
                $item->Id_Mesin = Hashids::connection('custom')->encode($item->Id_Mesin);
                $item->Berat_Sampel = (float) $item->Berat_Sampel;
                
                // sub no po
                $item->sub_no_po = $children->get($item->No_Sampel, collect())->map(function($child) {
                    $child->Id_Po_Sampel_Multi = Hashids::connection('custom')->encode($child->Id_Po_Sampel_Multi);
                    return $child;
                })->values();

                // tracking aktivitas
                $item->tracking = $trackingData->get($item->No_Po, collect())->values();

                return $item;
            });

            return response()->json([
                'success' => true, 
                'status' => 200, 
                'message' => 'Data Ditemukan', 
                'result' => $finalResult, 
                'page' => $page, 
                'total_page' => ceil($total / $limit), 
                'total_data' => (int) $total, 
                'status_counts' => $statusCounts,
            ], 200);

        } catch (\Exception $e) {
            Log::channel('QuisyController')->error('Error: ' . $e->getMessage());
            return response()->json([
                    'success' => true,
                    'status' => 500,
                    'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }

    public function cetakUlangQrCode(Request $request, $no_sampel, $id_mesin)
    {
        $userId =  Auth::user()->UserId;

        try {
            $id_mesin = Hashids::connection('custom')->decode($id_mesin)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Jenis Analisa tidak valid.'
            ], 400);
        }

        try {
            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow;
            $tanggalSqlServer = date('Y-m-d', strtotime($dt));
            $jamSqlServer = date('H:i:s', strtotime($dt));

            $getData = DB::table('N_EMI_LAB_PO_Sampel as q')
                ->leftJoin('EMI_Master_Mesin as m', 'q.Id_Mesin', '=', 'm.Id_Master_Mesin')
                ->select(
                    'q.id',
                    'q.Kode_Barang',
                    'q.Kode_Perusahaan',
                    'q.No_Sampel',
                    'q.Status',
                    'q.Tanggal',
                    'q.Jam',
                    'q.No_Po',
                    'q.No_Split_Po',
                    'q.No_Batch',
                    'm.Nama_Mesin',
                    'm.Seri_Mesin',
                    'q.Keterangan'
                )
                ->whereNull('q.status')
                ->where('q.No_Sampel', $no_sampel)
                ->first();

            if (empty($getData)) {
                return response()->json(['success' => false, 'status' => 404, 'message' => 'Data Tidak Ditemukan !'], 404);
            }

            $getInformasiMesin = DB::table('EMI_MASTER_MESIN')
                ->where('Id_Master_Mesin', $id_mesin)
                ->first();

            if (!$getInformasiMesin) {
                return response()->json(['success' => false, 'status' => 404, 'message' => 'Informasi Mesin Tidak Ditemukan'], 404);
            }

            if (!empty($request->Flag_Multi_QrCode) && $request->Flag_Multi_QrCode === "Y") {
                $jumlahCetak = (int) $request->Jumlah_Print;
                if ($jumlahCetak <= 0) {
                    $jumlahCetak = 1;
                }
            } else {
                $jumlahCetak = 1;
            }

            $getNamaSampel = DB::table('N_EMI_View_Barang')
                ->where('Kode_Barang', $getData->Kode_Barang)
                ->first();

            $batchStr     = $getData->No_Batch ?? '-';
            $tanggalStr   = date('d M Y');
            $namaMesinStr = $getInformasiMesin->Nama_Mesin ?? "Default";
            $qrDataStr    = $getData->No_Sampel;
            $noSplitStr   = $getData->No_Split_Po;
            $namaSampelStr = $getNamaSampel->Nama ?? '-';

            $transaksiTemplate = DB::table('N_EMI_LAB_Printer_Template_Transaksi')
                ->where('Flag_Default', 'Y')
                ->where('Id_Role', 1)
                ->where('Flag_Aktif', 'Y')
                ->orderByDesc('Id_Template_Transaksi')
                ->first();

            if (!$transaksiTemplate) {
                return response()->json(['success' => false, 'status' => 404, 'message' => 'Template Default Printer tidak ditemukan.'], 404);
            }

            $masterTemplate = DB::table('N_EMI_LAB_Master_Printer_Templates')
                ->where('Id_Master_Printer_Templates', $transaksiTemplate->Id_Master_Printer_Templates)
                ->first();

            if (!$masterTemplate) {
                return response()->json(['success' => false, 'status' => 404, 'message' => 'Master Template Printer tidak ditemukan.'], 404);
            }

            // 3. Ambil Item Template
            $templateItems = DB::table('N_EMI_LAB_Printer_Template_Items')
                ->where('Id_Master_Printer_Templates', $transaksiTemplate->Id_Master_Printer_Templates)
                ->where('Flag_Aktif', 'Y')
                ->get();

            if ($templateItems->isEmpty()) {
                return response()->json(['success' => false, 'status' => 404, 'message' => 'Item Template Printer kosong.'], 404);
            }

            $allPrintData = [];

            for ($i = 0; $i < $jumlahCetak; $i++) {
                $tsplContent = "";

                foreach ($templateItems as $item) {
                    $konten = $item->Isi_Konten;

                    $replacements = [
                        '{nama_sampel}' => $namaSampelStr,
                        '{qrData}'      => $qrDataStr,
                        '{no_split}'    => $noSplitStr,
                        '{batch}'       => $batchStr,
                        '{tanggal}'     => $tanggalStr,
                        '{namaMesin}'   => $namaMesinStr
                    ];

                    foreach ($replacements as $key => $val) {
                        $konten = str_replace($key, $val, $konten);
                    }

                    if ($item->Jenis === 'TEXT') {
                        $tsplContent .= "TEXT {$item->Posisi_X},{$item->Posisi_Y},\"{$item->Font}\",{$item->Rotation},{$item->Scale_X},{$item->Scale_Y},\"{$konten}\"\r\n";
                    } elseif ($item->Jenis === 'QRCODE') {
                        $tsplContent .= "QRCODE {$item->Posisi_X},{$item->Posisi_Y},{$item->Qr_Ecc},{$item->Qr_Size},A,{$item->Rotation},{$item->Qr_Model},\"{$konten}\"\r\n";
                    }
                }

                $allPrintData[] = [
                    'width'     => (int) $masterTemplate->Lebar_Label,
                    'height'    => (int) $masterTemplate->Tinggi_Label,
                    'gap'       => (int) $masterTemplate->Gap_Antar_Label,
                    'direction' => (int) ($masterTemplate->Direction ?? 1),
                    'data'      => $tsplContent
                ];
            }

            $payloadActivityProduksiSampel = [
                'No_Po'           => $getData->No_Po,
                'No_Split_Po'     => $getData->No_Split_Po,
                'No_Batch'        => $getData->No_Batch,
                'Jenis_Aktivitas' => "Cetak Ulang",
                'Status_Aktivitas'=> 'berhasil',
                'Keterangan'      => 'Untuk Nomor Po '. $getData->No_Po. ' Dilakukan Pencetakan Ulang QrCode',
                'Tanggal'         => $tanggalSqlServer,
                'Id_Mesin'        => $getInformasiMesin->Id_Master_Mesin,
                'Jam'             => $jamSqlServer,
                'Id_User'         => $userId,
                'Flag_Berhasil_Cetak_QrCode' => 'Y',
            ];
                        
            DB::table('N_EMI_LAB_Activity_Produksi_Sampel')->insert($payloadActivityProduksiSampel);

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'QrCode Berhasil Di Cetak Ulang',
                'print_jobs' => $allPrintData,
                'printer_url' => rtrim(env('URL_CLIENT'), '/')
            ], 200);

        } catch (\Exception $e) {
            $payloadActivityProduksiSampel = [
                'No_Po'           => $getData->No_Po ?? null,
                'No_Split_Po'     => $getData->No_Split_Po ?? null,
                'No_Batch'        => $getData->No_Batch ?? null,
                'Jenis_Aktivitas' => "Cetak Ulang QrCode",
                'Status_Aktivitas'=> 'gagal',
                'Keterangan'      => 'Gagal Mencetak QrCode',
                'Tanggal'         => $tanggalSqlServer ?? date('Y-m-d'),
                'Id_Mesin'        => $getInformasiMesin->Id_Master_Mesin ?? null,
                'Jam'             => $jamSqlServer ?? date('H:i:s'),
                'Id_User'         => $userId,
                'Flag_Berhasil_Cetak_QrCode' => null,
            ];
            
            DB::table('N_EMI_LAB_Activity_Produksi_Sampel')->insert($payloadActivityProduksiSampel);
            Log::channel('QuisyController')->error('Error: ' . $e->getMessage());
            return response()->json([
                    'success' => true,
                    'status' => 500,
                    'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }

    public function BukaKembaliPoYangSudahDiClose(Request $request, $No_Po)
    {
        $pengguna = Auth::user()->UserId;

        $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
        $dt = $waktuServer[0]->DateTimeNow; 
        $tanggalSqlServer = date('Y-m-d', strtotime($dt)); 
        $jamSqlServer = date('H:i:s', strtotime($dt));   

        DB::beginTransaction();

        $getData = DB::table('N_EMI_LAB_PO_Sampel')
                ->where('No_Po', $No_Po)
                ->first();

        if(!$getData){
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "Nomor Po Tidak Ditemukan"
                ], 404);
        }

        try {
            if(empty($No_Po)){
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "Nomor Po Tidak Ditemukan"
                ], 404);
            }

            DB::table('N_EMI_LAB_PO_Sampel')
                ->where('No_Po', $No_Po)->update(
                    [
                        'Tanggal_Buka_Ulang_Po' => $tanggalSqlServer,
                        'Jam_Buka_Ulang_Po' => $jamSqlServer,
                        'Flag_Close_Po' => null,
                        'Alasan_Buka_Ulang_Po' => $request->Alasan_Buka_Ulang_Po
                    ]
                );

             $payloadActivityProduksiSampel = [
                'No_Po' => $getData->No_Po,
                'Jenis_Aktivitas' => "Pembukaan Kembali Po Yang Sudah Di Close",
                'Status_Aktivitas' => 'berhasil',
                'Keterangan' => $request->Alasan_Buka_Ulang_Po,
                'Tanggal' => $tanggalSqlServer,
                'Jam' => $jamSqlServer,
                'Id_User' => $pengguna,
                'Flag_Berhasil_Cetak_QrCode' => null,
            ];

            DB::table('N_EMI_LAB_Activity_Produksi_Sampel')->insert($payloadActivityProduksiSampel);

            DB::commit();
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Data Berhasil Di Perbaharui"
            ], 200);
        }catch(\Exception $e){
            DB::rollback();
            Log::channel('QuisyController')->error('Error: ' . $e->getMessage());
            return response()->json([
                    'success' => true,
                    'status' => 500,
                    'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }

    public function LineChartTrenBeratSampel()
    {
        $getData = DB::table("N_EMI_LAB_PO_Sampel")
            ->select('Tanggal', 'Jam', 'Berat_Sampel', 'Jumlah_Pcs')
            ->whereNull('Status')
            ->get()
            ->map(function ($item) {
                // Cukup konversi ke tipe data numerik yang benar.
                // Tidak ada lagi logika untuk membuat string "X pcs".
                return [
                    'Tanggal'      => Carbon::parse($item->Tanggal)->format('d M Y'),
                    'Jam'          => $item->Jam,
                    'Berat_Sampel' => (float) $item->Berat_Sampel,
                    'Jumlah_Pcs'   => (int) $item->Jumlah_Pcs,
                ];
            });

        return response()->json([
            'success' => true,
            'status'  => 200,
            'result'  => $getData
        ], 200);
    }
    public function JumlahSampelPerMesin()
    {
        $getData = DB::table('N_EMI_LAB_PO_Sampel AS ps')
            ->join('EMI_Master_Mesin AS mm', 'ps.Id_Mesin', '=', 'mm.Id_Master_Mesin')
            ->select('mm.Nama_Mesin', DB::raw('COUNT(ps.id) as Jumlah_Sampel'))
            ->whereNull('ps.Status')
            ->groupBy('mm.Nama_Mesin')
            ->get();

        return response()->json([
            'success' => true,
            'status' => 200,
            'result' => $getData,
        ], 200);
    }

    public function DistribusiSampelTujuanPengujian()
    {
        $getData = DB::table('N_EMI_LAB_PO_Sampel')
        ->select('id', 'Flag_Khusus as flag_khusus')
        ->whereNull('Status')
        ->get()
        ->map(function ($item) {
                return [
                    'id' => $item->id = Hashids::connection('custom')->encode($item->id),
                    'flag_khusus' => $item->flag_khusus,
                ];
         });

        return response()->json([
            'success' => true,
            'status' => 200,
            'result' => $getData
        ], 200);
    }

    public function viewPelepasanPoClose()
    {
        return inertia("vue/quisy/PelepasanPOClose");
    }

    public function getDataCurrentPoYangDiClose(Request $request)
    {
    try {
            $limit = (int) $request->query('limit', 10);
            $page = (int) $request->query('page', 1);
            $offset = ($page - 1) * $limit;

            // Query dasar (tidak berubah)
            $query = DB::table('N_EMI_LAB_PO_Sampel as ps')
                ->leftJoin('N_EMI_View_Barang as vb', 'ps.Kode_Barang', '=', 'vb.Kode_Barang')
                ->where('ps.Flag_Close_Po', 'Y');

            // Filter (tidak berubah)
            if ($request->filled('date')) {
                $query->whereDate('ps.Tanggal', $request->date);
            }

            if ($request->filled('batch')) {
                $query->where('ps.No_Batch', 'like', "%{$request->batch}%");
            }
            
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('ps.No_Po', 'like', "%{$search}%")
                    ->orWhere('ps.No_Sampel', 'like', "%{$search}%")
                    ->orWhere('ps.No_Batch', 'like', "%{$search}%");
                });
            }
            
            // --- PERBAIKAN PADA PERHITUNGAN TOTAL ---

            $totalQuery = clone $query;

            // ✅ KUNCI PERBAIKAN: Menambahkan select('ps.No_Po') sebelum get()
            // Ini untuk menghindari error "invalid in the select list" di SQL Server.
            $total = $totalQuery
                ->select('ps.No_Po') // Cukup pilih satu kolom dari grup
                ->groupBy('ps.No_Po', 'ps.Kode_Barang', 'vb.Nama')
                ->get()
                ->count();

            // Query untuk mengambil data (tidak berubah)
            $getData = $query
                ->select(
                    'ps.No_Po',
                    'ps.Kode_Barang',
                    'vb.Nama as Nama_Barang',
                    DB::raw('MAX(ps.Tanggal) as Tanggal')
                )
                ->groupBy('ps.No_Po', 'ps.Kode_Barang', 'vb.Nama')
                ->orderBy('Tanggal', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get();
                
            // Mapping data (tidak berubah)
            $listData = $getData->map(function ($item) {
                return [
                    'No_Po'       => $item->No_Po,
                    'Nama_Barang' => $item->Nama_Barang ?? 'Tidak Ditemukan',
                    'Kode_Barang' => $item->Kode_Barang ?? '-',
                    'Tanggal'     => $item->Tanggal,
                    'Status'      => 'PO Closed',
                ];
            });

            // Response (tidak berubah)
            if ($getData->isEmpty()) {
                return response()->json([
                    'success' => true, 'status' => 200, 'message' => 'Tidak ada data sampel dari PO yang sudah ditutup.',
                    'result' => [], 'page' => $page, 'total_page' => 0, 'total_data' => 0,
                ], 200);
            }

            return response()->json([
                'success'    => true, 'status'     => 200, 'message'    => 'Data berhasil diambil.',
                'result'     => $listData, 'page'       => $page, 'total_page' => ceil($total / $limit),
                'total_data' => $total
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 'status' => 500, 'message' => ['error' => $e->getMessage()]
            ], 500);
        }
    }

}