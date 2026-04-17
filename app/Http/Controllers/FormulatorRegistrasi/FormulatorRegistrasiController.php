<?php

namespace App\Http\Controllers\FormulatorRegistrasi;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Vinkla\Hashids\Facades\Hashids;

class FormulatorRegistrasiController extends Controller
{
    public function index()
    {
        $NamaPengguna = Auth::user()->Nama;
        $sampelData = DB::table('N_EMI_LAB_PO_Sampel')->get();
        $lastActivity = $sampelData->last(); 
        $lastHistory = DB::table('N_EMI_LAB_Printer_Template_Transaksi')
        ->join('N_EMI_LAB_Master_Printer_Templates', 'N_EMI_LAB_Printer_Template_Transaksi.Id_Master_Printer_Templates', '=', 'N_EMI_LAB_Master_Printer_Templates.Id_Master_Printer_Templates')
        ->where('N_EMI_LAB_Printer_Template_Transaksi.Flag_Default', 'Y')
        ->where('N_EMI_LAB_Printer_Template_Transaksi.Flag_Aktif', 'Y')
        ->where('N_EMI_LAB_Printer_Template_Transaksi.Id_Role', 2)
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

        return inertia('vue/quisy/formulator/HomeFormulator', [
            'lastActivity' => $lastActivityFormatted,
            'pengguna' => $NamaPengguna,
            'po_selesai' => $completedPoCount,
            'po_belum_selesai' => $uncompletedPoCount,
            'lastHistory' => $lastHistory,
            'url_client' => rtrim(env('URL_CLIENT'), '/'),
            'url_timbangan' => rtrim(env('URL_TIMBANGAN'), '/'),
        ]);
    }

    public function getPoListWithCompletionStatusV2($computer_keys)
    {
        try {
                $identity = DB::table('N_EMI_LAB_Identity')
                ->select('id')
                ->where('Computer_Keys', $computer_keys)
                ->first();

            if (!$identity) {
                return ResponseHelper::error('Computer_Keys tidak ditemukan.', 404);
            }

            $idMesinList = DB::table('N_EMI_LAB_Binding_Identity')
                ->where('Id_Identity', $identity->id)
                ->pluck('Id_Mesin')
                ->toArray();

            if (empty($idMesinList)) {
                return ResponseHelper::error('Mesin tidak ditemukan untuk komputer ini.', 404);
            }

            $poList = DB::table('N_EMI_View_Trial_Order_Produksi as po')
                ->join('N_EMI_View_Barang as b', function ($join) {
                    $join->on('po.Kode_Perusahaan', '=', 'b.Kode_Perusahaan')
                        ->on('po.Kode_Stock_Owner', '=', 'b.Kode_Stock_Owner')
                        ->on('po.Kode_Barang', '=', 'b.Kode_Barang');
                })
                ->select('po.No_Faktur', 'po.Jumlah', 'po.Satuan', 'po.Tanggal', 'po.Kode_Barang', 'b.Nama')
                ->whereNull('po.status')
                ->whereNotIn('po.No_Faktur', function ($query) {
                    $query->select('No_Po')
                        ->from('N_LIMS_PO_Sampel')
                        ->where('Flag_Close_Po', 'Y');
                })
                ->where('po.Flag_Release', 'Y')
                ->orderBy('po.Tanggal', 'desc')
                ->orderBy('po.No_Faktur', 'desc')
                ->get();

            if ($poList->isEmpty()) {
                return ResponseHelper::success(null, 'Tidak ada data PO sama sekali.', 204);
            }

            $poNumbers = $poList->pluck('No_Faktur')->toArray();

            $poWithSamples = DB::table('N_LIMS_PO_Sampel')
                ->whereIn('No_Po', $poNumbers)
                ->distinct()
                ->pluck('No_Po')
                ->all();

            $processedPoList = $poList->map(function ($po, $index) use ($poWithSamples) {
                $po->is_selectable = true;
                $po->has_samples = in_array($po->No_Faktur, $poWithSamples);
                return $po;
            });

            return ResponseHelper::success($processedPoList, "Data Ditemukan", 200);
        }catch(\Exception $e){
            Log::channel('FormulatorRegistrasi')->error('Error'. $e->getMessage());
            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function getSplitPo($id, $computer_keys)
    {
        try {
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

            $splitOrders = DB::table('N_EMI_View_Trial_Split_Production_Order as po')
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

            $sampelData = DB::table('N_LIMS_PO_Sampel')
                ->select('No_Split_Po', 'No_Batch', DB::raw('count(distinct Id_Mesin) as mesin_input'))
                ->whereIn('No_Split_Po', $splitOrders->pluck('No_Transaksi')->toArray())
                ->whereIn('Id_Mesin', $idMesinList)
                ->groupBy('No_Split_Po', 'No_Batch')
                ->get()
                ->groupBy('No_Split_Po');

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
            return ResponseHelper::success($result, "Data Ditemukan", 200);
        }catch(\Exception $e){
            Log::channel('FormulatorRegistrasi')->error('ERROR '. $e->getMessage());
            return ResponseHelper::error("Terjadi Kesalahan", 500);
        }
    }

    public function getBatchPo($no_transaksi, $computer_keys)
    {
        try {
            if (empty($no_transaksi) || empty($computer_keys)) {
                return ResponseHelper::error('No_Transaksi dan Computer_Keys harus diisi.', 400);
            }

            $identity = DB::table('N_EMI_LAB_Identity')
                ->select('id')
                ->where('Computer_Keys', $computer_keys)
                ->first();

            if (!$identity) {
                return ResponseHelper::error('Computer_Keys tidak ditemukan.', 404);
            }

            $id_identity = $identity->id;

            $batchSP = DB::table('N_EMI_View_Trial_Split_Production_Order')
                ->select('No_PO', 'No_Transaksi', 'Jumlah_Batch')
                ->where('No_Transaksi', $no_transaksi)
                ->first();

            if (!$batchSP) {
                return ResponseHelper::error('Data tidak ditemukan.', 404);
            }

            $idMesinList = DB::table('N_EMI_LAB_Binding_Identity')
                ->where('Id_Identity', $id_identity)
                ->pluck('Id_Mesin')
                ->toArray();

            $totalMesin = count($idMesinList);

            $sampelData = DB::table('N_LIMS_PO_Sampel')
                ->select('No_Batch', DB::raw('COUNT(DISTINCT Id_Mesin) as mesin_input'))
                ->where('No_Split_Po', $batchSP->No_Transaksi)
                ->whereIn('Id_Mesin', $idMesinList)
                ->groupBy('No_Batch')
                ->get()
                ->keyBy('No_Batch');

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
            return ResponseHelper::success($data, 'Data berhasil diambil.', 200);
        } catch (\Throwable $e) {
            Log::channel('FormulatorRegistrasi')->error($e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'params' => [
                    'no_transaksi' => $no_transaksi,
                    'computer_keys' => $computer_keys
                ]
            ]);
            return ResponseHelper::error('Terjadi kesalahan pada server.', 500);
        }
    }

    public function getMachinesByComputerKeys($computerKey, $noSplitPo, $noBatch)
    {
        try {
            if (empty($computerKey) || empty($noSplitPo) || empty($noBatch)) {
                return ResponseHelper::error('Parameter tidak lengkap', 400);
            }

            $mesinList = DB::table('N_EMI_LAB_Binding_Identity as binding')
                ->join('EMI_Master_Mesin as mesin', 'binding.Id_Mesin', '=', 'mesin.Id_Master_Mesin')
                ->join('N_EMI_LAB_Identity as identity_tbl', 'binding.Id_Identity', '=', 'identity_tbl.id')
                ->select([
                    'mesin.Id_Master_Mesin',
                    'mesin.Nama_Mesin',
                    'mesin.Seri_Mesin',
                    'mesin.Flag_Multi_Qrcode',
                    'mesin.Jumlah_Print_QRCode',
                    'mesin.Flag_Kg'
                ])
                ->addSelect(['total_input_count' => function ($query) use ($noSplitPo, $noBatch) {
                    $query->selectRaw('count(*)')
                        ->from('N_LIMS_PO_Sampel as po')
                        ->whereColumn('po.Id_Mesin', 'mesin.Id_Master_Mesin')
                        ->where('po.No_Split_Po', $noSplitPo)
                        ->where('po.No_Batch', $noBatch);
                }])
                ->where('identity_tbl.Computer_Keys', $computerKey)
                ->groupBy([
                    'mesin.Id_Master_Mesin',
                    'mesin.Nama_Mesin',
                    'mesin.Seri_Mesin',
                    'mesin.Flag_Multi_Qrcode',
                    'mesin.Jumlah_Print_QRCode',
                    'mesin.Flag_Kg'
                ])
                ->get();

            if ($mesinList->isEmpty()) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $encodedData = $mesinList->map(function ($item) {
                $item->Id_Master_Mesin = Hashids::connection('custom')->encode($item->Id_Master_Mesin);
                $item->is_already_input = $item->total_input_count > 0;
                return $item;
            });

            return ResponseHelper::success($encodedData, 'Data Berhasil Diambil', 200);

        } catch (\Throwable $e) {
            Log::channel('FormulatorRegistrasi')->error($e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'params' => [
                    'computerKey' => $computerKey,
                    'noSplitPo' => $noSplitPo,
                    'noBatch' => $noBatch
                ]
            ]);

            return ResponseHelper::error('Terjadi kesalahan pada server.', 500);
        }
    }

    public function store(Request $request)
    {
        $userId = $request->namaKaryawan ?? Auth::user()->UserId;

        $checkedOnlyHuman = DB::table('N_EMI_LAB_Users')
                ->where('UserId', $userId)
                ->first();
        
       if (!$checkedOnlyHuman) {
            $checkedOnlyHuman = DB::table('N_EMI_LAB_Users')
                ->where('Nama', $userId)
                ->first();
        }

        if(!$checkedOnlyHuman){
            return response()->json([
                'success' => false,
                'status' => 403,
                'message' => 'Akun Anda Tidak Dikenali Oleh Sistem, Jangan Mencoba Kecurangan Atau lainnya, Jika masalah berlanjut segera hubungi pihak IT EVO'
            ], 403);
        }

        $hasAccessToHome = DB::table('N_EMI_LAB_Role_Menu AS rm')
            ->join('N_EMI_LAB_Menus AS m', 'rm.Id_Menu', '=', 'm.Id_Menu')
            ->where('rm.Id_User', $checkedOnlyHuman->UserId)
            ->where('m.Url_Menu', '/registrasi-material')
            ->exists();

        if (!$hasAccessToHome) {
            return response()->json([
                'success' => false,
                'status' => 403,
                'message' => 'Anda tidak memiliki izin untuk mengakses halaman Home'
            ], 403);
        }
        
        $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
        $dt = $waktuServer[0]->DateTimeNow; 

        $tanggalSqlServer = date('Y-m-d', strtotime($dt)); 
        $jamSqlServer = date('H:i:s', strtotime($dt));   

        $transaksiTemplate = DB::table('N_EMI_LAB_Printer_Template_Transaksi')
            ->where('Flag_Default', 'Y')
            ->where('Flag_Aktif', 'Y')
            ->where('Id_Role', 2)
            ->orderByDesc('Id_Template_Transaksi')
            ->first();

        $masterTemplate = null; 
        $templateItems = collect([]); 

        if ($transaksiTemplate) {
            $masterTemplate = DB::table('N_EMI_LAB_Master_Printer_Templates')
                ->where('Id_Master_Printer_Templates', $transaksiTemplate->Id_Master_Printer_Templates)
                ->first();

            $templateItems = DB::table('N_EMI_LAB_Printer_Template_Items')
                ->where('Id_Master_Printer_Templates', $transaksiTemplate->Id_Master_Printer_Templates)
                ->where('Flag_Aktif', 'Y')
                ->get();
        }

        $allPrintData = [];

        if($request->Sifat_Kegiatan === 'Rutin'){
            $request->validate([
                'Tanggal' => 'required',
                'No_Split_Po' => 'required',
                'No_Batch' => 'required',
                'Id_Mesin' => 'required',
                'Keterangan' => 'required',
                'Kode_Barang' => 'required',
                'No_Po' => 'required',
            ]);
    
            try {
                $IdMesin = Hashids::connection('custom')->decode($request->Id_Mesin)[0];
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'status'  => 400,
                    'message' => 'Format ID Jenis Analisa tidak valid.'
                ], 400);
            }
    
            DB::beginTransaction();
            
            try {
                $pengguna = $checkedOnlyHuman;
                $currenMonth = date('m');
                $currentYear = date('y');
                $prefix = 'FT' . $currenMonth . $currentYear;
    
                $lastSample = DB::table('N_LIMS_PO_Sampel')
                                ->where('No_Sampel', 'like', $prefix . '-%')
                                ->orderByDesc('id')
                                ->value('No_Sampel');
    
                if ($lastSample) {
                    $lastNumber = (int) substr($lastSample, strpos($lastSample, '-') + 1);
                    $newNumber = $prefix . '-' . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
                } else {
                    $newNumber = $prefix . '-0001';
                }
    
                $payloadPoSampel = [
                    'Kode_Perusahaan' => '001',
                    'No_Sampel' => $newNumber,
                    'Status' => null,
                    'Tanggal' => $tanggalSqlServer,
                    'Jam' => $jamSqlServer,
                    'No_Split_Po' => $request->No_Split_Po,
                    'No_Batch' => $request->No_Batch,
                    'Id_Mesin' => $IdMesin,
                    'Keterangan' => $request->Keterangan,
                    'Kode_Barang' => $request->Kode_Barang,
                    'No_Po' => $request->No_Po,
                    'Berat_Sampel' => $request->Berat_Sampel,
                    'Id_User' => $pengguna->UserId,
                    'Jumlah_Pcs' => $request->Jumlah_Pcs
                ];

                $payloadActivityProduksiSampel = [
                    'No_Po' => $request->No_Po,
                    'No_Split_Po' => $request->No_Split_Po,
                    'No_Batch' => $request->No_Batch,
                    'Jenis_Aktivitas' => "Registrasi Material",
                    'Status_Aktivitas' => 'Berhasil',
                    'Keterangan' => 'Untuk Nomor Po '. $request->No_Po. ' Berhasil Melakukan Registrasi Material Dan Menunggu Proses Cetak',
                    'Tanggal' => $tanggalSqlServer,
                    'Id_Mesin' => $IdMesin,
                    'Jam' => $jamSqlServer,
                    'Id_User' => $pengguna->UserId,
                    'Flag_Berhasil_Cetak_QrCode' => 'Y',
                ];
                
                DB::table('N_EMI_LIMS_Activity_Produksi_Sampel')->insert($payloadActivityProduksiSampel);
    
                $multiQrCodes = [];
    
                if ($request->Flag_Multi_Qrcode === "Y") {
                    for ($i = 0; $i < $request->Jumlah_Print_QRCode; $i++) {
                        $multiQrCode = [
                            'Kode_Perusahaan' => '001',
                            'No_Po_Multi' => $payloadPoSampel['No_Sampel'] . '-' . ($i + 1),
                            'Kode_Barang' => $payloadPoSampel['Kode_Barang'],
                            'No_Po_Sampel' => $payloadPoSampel['No_Sampel'],
                            'Status' => null,
                            'Tanggal' => $tanggalSqlServer,
                            'Jam' => $jamSqlServer,
                        ];
                        $multiQrCodes[] = $multiQrCode;
                    }
                    DB::table('N_LIMS_PO_Sampel_Multi_QrCode')->insert($multiQrCodes);
                }
                
                $getDataPengguna = User::where('UserId', $pengguna->UserId)->first();
    
                if (!Hash::check($request->pin, $getDataPengguna->Pin)) {
                    return response()->json([
                        'success' => false,
                        'status' => 400,
                        'message' => 'PIN Salah !'
                    ], 401);
                }
                DB::table('N_LIMS_PO_Sampel')->insert($payloadPoSampel);
                
                DB::commit();

            } catch (\Exception $e) {
                Log::channel('FormulatorRegistrasiController')->error($e->getMessage());
                $payloadActivityProduksiSampel = [
                                    'No_Po' => $request->No_Po,
                                    'No_Split_Po' => $request->No_Split_Po,
                                    'No_Batch' => $request->No_Batch,
                                    'Jenis_Aktivitas' => "Registrasi Material",
                                    'Status_Aktivitas' => 'gagal',
                                    'Keterangan' => 'Untuk Nomor Po '. $request->No_Po. ' Gagal Dalam Registrasi Material, Data Tidak Tersimpan Di Sistem',
                                    'Tanggal' => $tanggalSqlServer,
                                    'Id_Mesin' => $IdMesin,
                                    'Jam' => $jamSqlServer,
                                    'Id_User' => $pengguna->UserId,
                                    'Flag_Berhasil_Cetak_QrCode' => null,
                ];
                    
                DB::table('N_EMI_LIMS_Activity_Produksi_Sampel')->insert($payloadActivityProduksiSampel);
                DB::rollBack();
                return ResponseHelper::error("Terjadi Kesalahan", 500);
            }
            
            try {
                $getNamaSampel = DB::table('N_EMI_View_Barang')->where('Kode_Barang', $request->Kode_Barang)->first();
                $getNamaMesin = DB::table('EMI_Master_Mesin')->where('Id_Master_Mesin', $IdMesin)->first();
                
                $namaMesinStr  = $getNamaMesin->Nama_Mesin ?? "Default";
                $namaSampelStr = $getNamaSampel->Nama ?? '-';
                $batchStr      = $payloadPoSampel['No_Batch'];
                $tanggalStr    = date('d M Y', strtotime($payloadPoSampel['Tanggal']));
                $noSplitStr    = $request->No_Split_Po;
                $jenisAnalisaStr = ''; 

                if (!$transaksiTemplate || !$masterTemplate || $templateItems->isEmpty()) {
                     throw new \Exception("Template printer tidak ditemukan di database.");
                }

                $dataToPrint = [];
                if ($request->Flag_Multi_Qrcode === "Y") {
                     foreach ($multiQrCodes as $item) {
                         $dataToPrint[] = $item['No_Po_Multi'];
                     }
                } else {
                     $dataToPrint[] = $payloadPoSampel["No_Sampel"];
                }

                foreach ($dataToPrint as $qrDataStr) {
                    $tsplContent = "";
                    foreach ($templateItems as $itemTmp) {
                        $konten = $itemTmp->Isi_Konten;
                        $replacements = [
                            '{nama_sampel}'   => $namaSampelStr,
                            '{qrData}'        => $qrDataStr,
                            '{no_split}'      => $noSplitStr,
                            '{batch}'         => $batchStr,
                            '{tanggal}'       => $tanggalStr,
                            '{namaMesin}'     => $namaMesinStr,
                            '{jenis_analisa}' => $jenisAnalisaStr
                        ];
                        foreach ($replacements as $key => $val) {
                            $konten = str_replace($key, $val, $konten);
                        }

                        if ($itemTmp->Jenis === 'TEXT') {
                            $tsplContent .= "TEXT {$itemTmp->Posisi_X},{$itemTmp->Posisi_Y},\"{$itemTmp->Font}\",{$itemTmp->Rotation},{$itemTmp->Scale_X},{$itemTmp->Scale_Y},\"{$konten}\"\r\n";
                        } elseif ($itemTmp->Jenis === 'QRCODE') {
                            $tsplContent .= "QRCODE {$itemTmp->Posisi_X},{$itemTmp->Posisi_Y},{$itemTmp->Qr_Ecc},{$itemTmp->Qr_Size},A,{$itemTmp->Rotation},{$itemTmp->Qr_Model},\"{$konten}\"\r\n";
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
                    'No_Po' => $request->No_Po,
                    'No_Split_Po' => $request->No_Split_Po,
                    'No_Batch' => $request->No_Batch,
                    'Jenis_Aktivitas' => "Cetak QrCode Material",
                    'Status_Aktivitas' => 'berhasil',
                    'Keterangan' => 'Untuk Nomor Po '. $request->No_Po. ' Berhasil Melakukan Cetak QrCode',
                    'Tanggal' => $tanggalSqlServer,
                    'Id_Mesin' => $IdMesin,
                    'Jam' => $jamSqlServer,
                    'Id_User' => $pengguna->UserId,
                    'Flag_Berhasil_Cetak_QrCode' => null,
                ];
                    
                DB::table('N_EMI_LIMS_Activity_Produksi_Sampel')->insert($payloadActivityProduksiSampel);
            } catch (\Exception $e) {
                Log::channel('FormulatorRegistrasiController')->error($e->getMessage());

                $payloadActivityProduksiSampel = [
                                    'No_Po' => $request->No_Po,
                                    'No_Split_Po' => $request->No_Split_Po,
                                    'No_Batch' => $request->No_Batch,
                                    'Jenis_Aktivitas' => "Cetak QrCode Material",
                                    'Status_Aktivitas' => 'gagal',
                                    'Keterangan' => 'Untuk Nomor Po '. $request->No_Po. ' Gagal Melakukan Cetak QrCode',
                                    'Tanggal' => $tanggalSqlServer,
                                    'Id_Mesin' => $IdMesin,
                                    'Jam' => $jamSqlServer,
                                    'Id_User' => $pengguna->UserId,
                                    'Flag_Berhasil_Cetak_QrCode' => null,
                ];
                    
                DB::table('N_EMI_LIMS_Activity_Produksi_Sampel')->insert($payloadActivityProduksiSampel);
                return response()->json([
                    'success' => true,
                    'message' => 'Data berhasil disimpan, namun terjadi kesalahan saat cetak: ' . $e->getMessage()
                ], 201);
            }
        }else {
            if($request->Opsi_Keterangan === 'ya'){
                $request->validate([
                    'No_Split_Po' => 'required',
                    'No_Batch' => 'required',
                    'Id_Mesin' => 'required',
                    'Kode_Barang' => 'required',
                    'No_Po' => 'required',
                    'Opsi_Keterangan' => 'required',
                    'Id_Jenis_Analisa_Khusus' => 'required',
                ]);
        
                try {
                    $IdMesin = Hashids::connection('custom')->decode($request->Id_Mesin)[0];
                    $Id_Jenis_Analisa_Khusus = Hashids::connection('custom')->decode($request->Id_Jenis_Analisa_Khusus)[0];
                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'status'  => 400,
                        'message' => 'Format Key tidak valid.'
                    ], 400);
                }

                DB::beginTransaction();
                
                try {
                    $pengguna = $checkedOnlyHuman;
                    $jumlahCetak = (int) $request->Jumlah_Print_QRCode;
                    $currenMonth = date('m');
                    $currentYear = date('y');
                    $prefix = 'FT' . $currenMonth . $currentYear;

                    $lastSample = DB::table('N_LIMS_PO_Sampel')
                        ->where('No_Sampel', 'like', $prefix . '-%')
                        ->orderByDesc('id')
                        ->value('No_Sampel');
                
                    $lastNumber = 0;
                    if ($lastSample) {
                        $lastNumber = (int) substr($lastSample, strpos($lastSample, '-') + 1);
                    }
                
                    $multiQrCodes = [];
                    $payloadPoSampels = [];

                    if($request->Flag_Multi_Qrcode === 'Y'){
                        $payloadPoSampels = [];
                        $multiQrCodes = [];

                        for ($i = 0; $i < $jumlahCetak; $i++) {
                            $isLast = $i === ($jumlahCetak - 1); 
                            $noUrut = $lastNumber + $i + 1;
                            $newNumber = $prefix . '-' . str_pad($noUrut, 4, '0', STR_PAD_LEFT);

                            $payloadPoSampels[] = [
                                'Kode_Perusahaan' => '001',
                                'No_Sampel' => $newNumber,
                                'Status' => null,
                                'Tanggal' => $tanggalSqlServer,
                                'Jam' => $jamSqlServer,
                                'No_Split_Po' => $request->No_Split_Po,
                                'No_Batch' => $request->No_Batch,
                                'Id_Mesin' => $IdMesin,
                                'Keterangan' => $request->Keterangan,
                                'Kode_Barang' => $request->Kode_Barang,
                                'No_Po' => $request->No_Po,
                                'Berat_Sampel' => $request->Berat_Sampel,
                                'Id_User' => $pengguna->UserId,
                                'Flag_Khusus' => $isLast ? 'Y' : null,
                                'Id_Jenis_Analisa_Khusus' => $isLast ? $Id_Jenis_Analisa_Khusus : null,
                            ];

                            for ($j = 0; $j < $request->Jumlah_Print_QRCode; $j++) {
                                $multiQrCodes[] = [
                                    'Kode_Perusahaan' => '001',
                                    'No_Po_Multi' => $newNumber . '-' . ($j + 1),
                                    'Kode_Barang' => $request->Kode_Barang,
                                    'No_Po_Sampel' => $newNumber,
                                    'Status' => null,
                                    'Tanggal' => $tanggalSqlServer,
                                    'Jam' => $jamSqlServer,
                                ];
                            }
                        }

                        DB::table('N_LIMS_PO_Sampel')->insert($payloadPoSampels);

                        if (!empty($multiQrCodes)) {
                            DB::table('N_LIMS_PO_Sampel_Multi_QrCode')->insert($multiQrCodes);
                        }

                        $getDataPengguna = User::where('UserId', $pengguna->UserId)->first();
                        if (!Hash::check($request->pin, $getDataPengguna->Pin)) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'status' => 400,
                                'message' => 'PIN Salah !'
                            ], 401);
                        }
                    
                        DB::commit();
                    }else {

                        for ($i = 0; $i < $jumlahCetak + 1; $i++) {
                            $isLast = $i === $jumlahCetak;
                            $noUrut = $lastNumber + $i + 1;
                            $newNumber = $prefix . '-' . str_pad($noUrut, 4, '0', STR_PAD_LEFT);
                    
                            $payloadPoSampels[] = [
                                'Kode_Perusahaan' => '001',
                                'No_Sampel' => $newNumber,
                                'Status' => null,
                                'Tanggal' => $tanggalSqlServer,
                                'Jam' => $jamSqlServer,
                                'No_Split_Po' => $request->No_Split_Po,
                                'No_Batch' => $request->No_Batch,
                                'Id_Mesin' => $IdMesin,
                                'Keterangan' => $request->Keterangan,
                                'Kode_Barang' => $request->Kode_Barang,
                                'No_Po' => $request->No_Po,
                                'Berat_Sampel' => $request->Berat_Sampel,
                                'Id_User' => $pengguna->UserId,
                                'Flag_Khusus' => $isLast ? 'Y' : null,
                                'Id_Jenis_Analisa_Khusus' => $isLast ? $Id_Jenis_Analisa_Khusus : null,
                            ];
                        }
                      
                        DB::table('N_LIMS_PO_Sampel')->insert($payloadPoSampels);
                    
                        if (!empty($multiQrCodes)) {
                            DB::table('N_LIMS_PO_Sampel_Multi_QrCode')->insert($multiQrCodes);
                        }
                    
                        $getDataPengguna = User::where('UserId', $pengguna->UserId)->first();
                        if (!Hash::check($request->pin, $getDataPengguna->Pin)) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'status' => 400,
                                'message' => 'PIN Salah !'
                            ], 401);
                        }
                        DB::commit();
                    }
                
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::channel('FormulatorRegistrasiController')->error($e->getMessage());
                    return ResponseHelper::error("Terjadi Kesalahan", 500);
                }

                try {
                    $getNamaSampel = DB::table('N_EMI_View_Barang')->where('Kode_Barang', $request->Kode_Barang)->first();
                    $namaAnalisa = DB::table('N_EMI_LAB_Jenis_Analisa')->where('id', $Id_Jenis_Analisa_Khusus)->first();
                    $getNamaMesin = DB::table('EMI_Master_Mesin')->where('Id_Master_Mesin', $IdMesin)->first();
                    
                    $namaMesinStr  = $getNamaMesin->Nama_Mesin ?? "Default";
                    $namaSampelStr = $getNamaSampel->Nama ?? '-';
                    $batchStr      = $request->No_Batch;
                    $tanggalStr    = date('d M Y', strtotime(date('Y-m-d')));
                    $noSplitStr    = $request->No_Split_Po;
                    $jenisAnalisaFromDb = $namaAnalisa->Jenis_Analisa ?? '';

                    if (!$transaksiTemplate || !$masterTemplate || $templateItems->isEmpty()) {
                        throw new \Exception("Template printer tidak ditemukan di database.");
                    }

                    if ($request->Flag_Multi_Qrcode === "Y") {
                        $total = count($multiQrCodes);
                        foreach ($multiQrCodes as $index => $item) {
                            $qrDataStr = $item['No_Po_Multi'];
                            $jenisAnalisaStr = ($index === $total - 1) ? $jenisAnalisaFromDb : "";

                            $tsplContent = "";
                            foreach ($templateItems as $itemTmp) {
                                $konten = $itemTmp->Isi_Konten;
                                $replacements = [
                                    '{nama_sampel}'   => $namaSampelStr,
                                    '{qrData}'        => $qrDataStr,
                                    '{no_split}'      => $noSplitStr,
                                    '{batch}'         => $batchStr,
                                    '{tanggal}'       => $tanggalStr,
                                    '{namaMesin}'     => $namaMesinStr,
                                    '{jenis_analisa}' => $jenisAnalisaStr
                                ];
                                foreach ($replacements as $key => $val) {
                                    $konten = str_replace($key, $val, $konten);
                                }

                                if ($itemTmp->Jenis === 'TEXT') {
                                    $tsplContent .= "TEXT {$itemTmp->Posisi_X},{$itemTmp->Posisi_Y},\"{$itemTmp->Font}\",{$itemTmp->Rotation},{$itemTmp->Scale_X},{$itemTmp->Scale_Y},\"{$konten}\"\r\n";
                                } elseif ($itemTmp->Jenis === 'QRCODE') {
                                    $tsplContent .= "QRCODE {$itemTmp->Posisi_X},{$itemTmp->Posisi_Y},{$itemTmp->Qr_Ecc},{$itemTmp->Qr_Size},A,{$itemTmp->Rotation},{$itemTmp->Qr_Model},\"{$konten}\"\r\n";
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
                    } else {
                        $total = count($payloadPoSampels);
                        foreach ($payloadPoSampels as $index => $item) {
                            $qrDataStr = $item['No_Sampel'];
                            $jenisAnalisaStr = ($index === $total - 1) ? $jenisAnalisaFromDb : "";

                            $tsplContent = "";
                            foreach ($templateItems as $itemTmp) {
                                $konten = $itemTmp->Isi_Konten;
                                $replacements = [
                                    '{nama_sampel}'   => $namaSampelStr,
                                    '{qrData}'        => $qrDataStr,
                                    '{no_split}'      => $noSplitStr,
                                    '{batch}'         => $batchStr,
                                    '{tanggal}'       => $tanggalStr,
                                    '{namaMesin}'     => $namaMesinStr,
                                    '{jenis_analisa}' => $jenisAnalisaStr
                                ];
                                foreach ($replacements as $key => $val) {
                                    $konten = str_replace($key, $val, $konten);
                                }

                                if ($itemTmp->Jenis === 'TEXT') {
                                    $tsplContent .= "TEXT {$itemTmp->Posisi_X},{$itemTmp->Posisi_Y},\"{$itemTmp->Font}\",{$itemTmp->Rotation},{$itemTmp->Scale_X},{$itemTmp->Scale_Y},\"{$konten}\"\r\n";
                                } elseif ($itemTmp->Jenis === 'QRCODE') {
                                    $tsplContent .= "QRCODE {$itemTmp->Posisi_X},{$itemTmp->Posisi_Y},{$itemTmp->Qr_Ecc},{$itemTmp->Qr_Size},A,{$itemTmp->Rotation},{$itemTmp->Qr_Model},\"{$konten}\"\r\n";
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
                    }
                    
                    $payloadActivityProduksiSampel = [
                                        'No_Po' => $request->No_Po,
                                        'No_Split_Po' => $request->No_Split_Po,
                                        'No_Batch' => $request->No_Batch,
                                        'Jenis_Aktivitas' => "Cetak QrCode Material",
                                        'Status_Aktivitas' => 'berhasil',
                                        'Keterangan' => 'Untuk Nomor Po '. $request->No_Po. ' Berhasil Melakukan Cetak QrCode',
                                        'Tanggal' => $tanggalSqlServer,
                                        'Id_Mesin' => $IdMesin,
                                        'Jam' => $jamSqlServer,
                                        'Id_User' => $pengguna->UserId,
                                        'Flag_Berhasil_Cetak_QrCode' => null,
                    ];
                        
                    DB::table('N_EMI_LIMS_Activity_Produksi_Sampel')->insert($payloadActivityProduksiSampel);
                } catch (\Exception $e) {
                    Log::channel('FormulatorRegistrasiController')->error($e->getMessage());
                    $payloadActivityProduksiSampel = [
                                        'No_Po' => $request->No_Po,
                                        'No_Split_Po' => $request->No_Split_Po,
                                        'No_Batch' => $request->No_Batch,
                                        'Jenis_Aktivitas' => "Cetak QrCode Material",
                                        'Status_Aktivitas' => 'gagal',
                                        'Keterangan' => 'Untuk Nomor Po '. $request->No_Po. ' Gagal Melakukan Cetak QrCode',
                                        'Tanggal' => $tanggalSqlServer,
                                        'Id_Mesin' => $IdMesin,
                                        'Jam' => $jamSqlServer,
                                        'Id_User' => $pengguna->UserId,
                                        'Flag_Berhasil_Cetak_QrCode' => null,
                    ];
                    
                    DB::table('N_EMI_LIMS_Activity_Produksi_Sampel')->insert($payloadActivityProduksiSampel);
                    return response()->json([
                        'success' => true,
                        'message' => 'Data berhasil disimpan, namun terjadi kesalahan saat cetak: ' . $e->getMessage()
                    ], 201);
                }
            }else {
                $request->validate([
                    'No_Split_Po' => 'required',
                    'No_Batch' => 'required',
                    'Id_Mesin' => 'required',
                    'Kode_Barang' => 'required',
                    'No_Po' => 'required',
                    'Opsi_Keterangan' => 'required',
                    'Id_Jenis_Analisa_Khusus' => 'required',
                ]);
        
                try {
                    $IdMesin = Hashids::connection('custom')->decode($request->Id_Mesin)[0];
                    $Id_Jenis_Analisa_Khusus = Hashids::connection('custom')->decode($request->Id_Jenis_Analisa_Khusus)[0];
                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'status'  => 400,
                        'message' => 'Format ID Jenis Analisa tidak valid.'
                    ], 400);
                }
        
                DB::beginTransaction();
                
                try {
                    $pengguna = $checkedOnlyHuman;
                    $jumlahCetak = (int) $request->Jumlah_Print_QRCode;
                    $currenMonth = date('m');
                    $currentYear = date('y');
                    $prefix = 'FT' . $currenMonth . $currentYear;

                    $lastSample = DB::table('N_EMI_LAB_PO_Sampel')
                        ->where('No_Sampel', 'like', $prefix . '-%')
                        ->orderByDesc('id')
                        ->value('No_Sampel');

                    $lastNumber = 0;
                    if ($lastSample) {
                        $lastNumber = (int) substr($lastSample, strpos($lastSample, '-') + 1);
                    }

                    $multiQrCodes = [];
                    $payloadPoSampels = [];

                    if ($request->Flag_Multi_Qrcode === 'Y') {
                        $newNumber = $prefix . '-' . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

                        $payloadPoSampel = [
                            'Kode_Perusahaan' => '001',
                            'No_Sampel' => $newNumber,
                            'Status' => null,
                            'Tanggal' => $tanggalSqlServer,
                            'Jam' => $jamSqlServer,
                            'No_Split_Po' => $request->No_Split_Po,
                            'No_Batch' => $request->No_Batch,
                            'Id_Mesin' => $IdMesin,
                            'Keterangan' => $request->Keterangan,
                            'Kode_Barang' => $request->Kode_Barang,
                            'No_Po' => $request->No_Po,
                            'Berat_Sampel' => $request->Berat_Sampel,
                            'Id_User' => $pengguna->UserId,
                            'Jumlah_Pcs' => $request->Jumlah_Pcs,
                            'Flag_Khusus' => 'Y',
                            'Id_Jenis_Analisa_Khusus' => $Id_Jenis_Analisa_Khusus,
                        ];
                        $payloadPoSampels[] = $payloadPoSampel;

                        for ($i = 0; $i < $jumlahCetak; $i++) {
                            $multiQrCodes[] = [
                                'Kode_Perusahaan' => '001',
                                'No_Po_Multi' => $newNumber . '-' . ($i + 1),
                                'Kode_Barang' => $request->Kode_Barang,
                                'No_Po_Sampel' => $newNumber,
                                'Status' => null,
                                'Tanggal' => $tanggalSqlServer,
                                'Jam' => $jamSqlServer,
                            ];
                        }

                        DB::table('N_LIMS_PO_Sampel')->insert($payloadPoSampels);

                        if (!empty($multiQrCodes)) {
                            DB::table('N_LIMS_PO_Sampel_Multi_QrCode')->insert($multiQrCodes);
                        }

                    } else {
                        for ($i = 0; $i < $jumlahCetak; $i++) {
                            $noUrut = $lastNumber + $i + 1;
                            $newNumber = $prefix . '-' . str_pad($noUrut, 4, '0', STR_PAD_LEFT);

                            $payloadPoSampels[] = [
                                'Kode_Perusahaan' => '001',
                                'No_Sampel' => $newNumber,
                                'Status' => null,
                                'Tanggal' => $tanggalSqlServer,
                                'Jam' => $jamSqlServer,
                                'No_Split_Po' => $request->No_Split_Po,
                                'No_Batch' => $request->No_Batch,
                                'Id_Mesin' => $IdMesin,
                                'Keterangan' => $request->Keterangan,
                                'Kode_Barang' => $request->Kode_Barang,
                                'No_Po' => $request->No_Po,
                                'Berat_Sampel' => $request->Berat_Sampel,
                                'Id_User' => $pengguna->UserId,
                                'Flag_Khusus' => 'Y',
                                'Id_Jenis_Analisa_Khusus' => $Id_Jenis_Analisa_Khusus,
                            ];
                        }

                        DB::table('N_LIMS_PO_Sampel')->insert($payloadPoSampels);
                    }

                    $getDataPengguna = User::where('UserId', $pengguna->UserId)->first();
                    if (!Hash::check($request->pin, $getDataPengguna->Pin)) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'status' => 401,
                            'message' => 'PIN Salah !'
                        ], 401);
                    }
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::channel('FormulatorRegistrasiController')->error($e->getMessage());
                    return ResponseHelper::error("Terjadi Kesalahan", 500);
                }

                try {
                    $getNamaSampel = DB::table('N_EMI_View_Barang')->where('Kode_Barang', $request->Kode_Barang)->first();
                    $namaAnalisa = DB::table('N_EMI_LAB_Jenis_Analisa')->where('id', $Id_Jenis_Analisa_Khusus)->first();
                    $getNamaMesin = DB::table('EMI_Master_Mesin')->where('Id_Master_Mesin', $IdMesin)->first();
                    
                    $namaMesinStr  = $getNamaMesin->Nama_Mesin ?? "Default";
                    $namaSampelStr = $getNamaSampel->Nama ?? '-';
                    $batchStr      = $request->No_Batch;
                    $tanggalStr    = date('d M Y', strtotime(date('Y-m-d')));
                    $noSplitStr    = $request->No_Split_Po;
                    $jenisAnalisaFromDb = $namaAnalisa->Jenis_Analisa ?? '';

                    if (!$transaksiTemplate || !$masterTemplate || $templateItems->isEmpty()) {
                        throw new \Exception("Template printer tidak ditemukan di database.");
                    }

                    if ($request->Flag_Multi_Qrcode === "Y") {
                        $total = count($multiQrCodes);
                        foreach ($multiQrCodes as $index => $item) {
                            $qrDataStr = $item['No_Po_Multi'];
                            $jenisAnalisaStr = ($index === $total - 1) ? $jenisAnalisaFromDb : "";

                            $tsplContent = "";
                            foreach ($templateItems as $itemTmp) {
                                $konten = $itemTmp->Isi_Konten;
                                $replacements = [
                                    '{nama_sampel}'   => $namaSampelStr,
                                    '{qrData}'        => $qrDataStr,
                                    '{no_split}'      => $noSplitStr,
                                    '{batch}'         => $batchStr,
                                    '{tanggal}'       => $tanggalStr,
                                    '{namaMesin}'     => $namaMesinStr,
                                    '{jenis_analisa}' => $jenisAnalisaStr
                                ];
                                foreach ($replacements as $key => $val) {
                                    $konten = str_replace($key, $val, $konten);
                                }

                                if ($itemTmp->Jenis === 'TEXT') {
                                    $tsplContent .= "TEXT {$itemTmp->Posisi_X},{$itemTmp->Posisi_Y},\"{$itemTmp->Font}\",{$itemTmp->Rotation},{$itemTmp->Scale_X},{$itemTmp->Scale_Y},\"{$konten}\"\r\n";
                                } elseif ($itemTmp->Jenis === 'QRCODE') {
                                    $tsplContent .= "QRCODE {$itemTmp->Posisi_X},{$itemTmp->Posisi_Y},{$itemTmp->Qr_Ecc},{$itemTmp->Qr_Size},A,{$itemTmp->Rotation},{$itemTmp->Qr_Model},\"{$konten}\"\r\n";
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
                    } else {
                        $total = count($payloadPoSampels);
                        foreach ($payloadPoSampels as $index => $item) {
                            $qrDataStr = $item['No_Sampel'];
                            $jenisAnalisaStr = ($index === $total - 1) ? $jenisAnalisaFromDb : "";

                            $tsplContent = "";
                            foreach ($templateItems as $itemTmp) {
                                $konten = $itemTmp->Isi_Konten;
                                $replacements = [
                                    '{nama_sampel}'   => $namaSampelStr,
                                    '{qrData}'        => $qrDataStr,
                                    '{no_split}'      => $noSplitStr,
                                    '{batch}'         => $batchStr,
                                    '{tanggal}'       => $tanggalStr,
                                    '{namaMesin}'     => $namaMesinStr,
                                    '{jenis_analisa}' => $jenisAnalisaStr
                                ];
                                foreach ($replacements as $key => $val) {
                                    $konten = str_replace($key, $val, $konten);
                                }

                                if ($itemTmp->Jenis === 'TEXT') {
                                    $tsplContent .= "TEXT {$itemTmp->Posisi_X},{$itemTmp->Posisi_Y},\"{$itemTmp->Font}\",{$itemTmp->Rotation},{$itemTmp->Scale_X},{$itemTmp->Scale_Y},\"{$konten}\"\r\n";
                                } elseif ($itemTmp->Jenis === 'QRCODE') {
                                    $tsplContent .= "QRCODE {$itemTmp->Posisi_X},{$itemTmp->Posisi_Y},{$itemTmp->Qr_Ecc},{$itemTmp->Qr_Size},A,{$itemTmp->Rotation},{$itemTmp->Qr_Model},\"{$konten}\"\r\n";
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
                    }
                    
                    $payloadActivityProduksiSampel = [
                                        'No_Po' => $request->No_Po,
                                        'No_Split_Po' => $request->No_Split_Po,
                                        'No_Batch' => $request->No_Batch,
                                        'Jenis_Aktivitas' => "Cetak QrCode Material",
                                        'Status_Aktivitas' => 'berhasil',
                                        'Keterangan' => 'Untuk Nomor Po '. $request->No_Po. ' Berhasil Melakukan Cetak QrCode',
                                        'Tanggal' => $tanggalSqlServer,
                                        'Id_Mesin' => $IdMesin,
                                        'Jam' => $jamSqlServer,
                                        'Id_User' => $pengguna->UserId,
                                        'Flag_Berhasil_Cetak_QrCode' => null,
                    ];
                    
                    DB::table('N_EMI_LIMS_Activity_Produksi_Sampel')->insert($payloadActivityProduksiSampel);
                } catch (\Exception $e) {
                    Log::channel('FormulatorRegistrasiController')->error($e->getMessage());
                    $payloadActivityProduksiSampel = [
                                        'No_Po' => $request->No_Po,
                                        'No_Split_Po' => $request->No_Split_Po,
                                        'No_Batch' => $request->No_Batch,
                                        'Jenis_Aktivitas' => "Cetak QrCode Material",
                                        'Status_Aktivitas' => 'gagal',
                                        'Keterangan' => 'Untuk Nomor Po '. $request->No_Po. ' Gagal Melakukan Cetak QrCode',
                                        'Tanggal' => $tanggalSqlServer,
                                        'Id_Mesin' => $IdMesin,
                                        'Jam' => $jamSqlServer,
                                        'Id_User' => $pengguna->UserId,
                                        'Flag_Berhasil_Cetak_QrCode' => null,
                    ];
                    DB::table('N_EMI_LIMS_Activity_Produksi_Sampel')->insert($payloadActivityProduksiSampel);
                    
                    return response()->json([
                        'success'     => true,
                        'status'      => 201, // Ubah ke 201 karena berhasil create
                        'message'     => 'Data berhasil disimpan dan siap dicetak',
                        'print_jobs'  => $allPrintData, 
                        'printer_url' => rtrim(env('URL_CLIENT'), '/')
                    ], 201);
                }
            }
        }

        return ResponseHelper::success(null, 'Data berhasil disimpan dan cetak selesai', 201);
    }
}
