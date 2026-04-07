<?php

namespace App\Http\Controllers;

use App\Models\POSample;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

class POSampleController extends Controller
{
    public function index()
    {
        $ip = '192.168.13.81';
        $port = 9100;

        // Data yang mungkin panjang (contoh: URL dengan query parameters)
        $qrData = "https://csirt.banyuasinkab.go.id/emi/strrandom/sta https://docs.google.com/spreadsheets/d/1B1EbF6HcIz3RlOTOG5cZC_m9-cyPTBBcxpy15vakMf0/edit?resourcekey=&gid=592760196#gid=592760196 https://docs.google.com/spreadsheets/d/1B1EbF6HcIz3RlOTOG5cZC_m9-cyPTBBcxpy15vakMf0/edit?resourcekey=&gid=592760196#gid=592760196https://docs.google.com/spreadsheets/d/1B1EbF6HcIz3RlOTOG5cZC_m9-cyPTBBcxpy15vakMf0/edit?resourcekey=&gid=592760196#gid=592760196";

        // Perintah TSPL dengan pengaturan fixed size
        $tsplCommand  = "SIZE 50 mm, 20 mm\r\n";
        $tsplCommand .= "GAP 2 mm, 0\r\n";
        $tsplCommand .= "DIRECTION 1\r\n";
        $tsplCommand .= "CLS\r\n";

        // QR Code dengan ukuran tetap menggunakan parameter size (S7 untuk ukuran tetap)
        $tsplCommand .= "QRCODE 300,100,H,6,A,0,M2,S7,\"$qrData\"\r\n"; // S7 ukuran tetap dengan mode koreksi yang lebih tinggi

        // Teks pembantu (opsional)
        $tsplCommand .= "TEXT 100,180,\"0\",0,1,1,\"Scan this QR\"\r\n";
        $tsplCommand .= "PRINT 1,1\r\n";

        // Eksekusi print
        $fp = stream_socket_client("tcp://$ip:$port", $errno, $errstr, 5);
        if (!$fp) {
            return response()->json(['error' => "Connection failed: $errstr ($errno)"], 500);
        }

        fwrite($fp, $tsplCommand);
        fclose($fp);

        return response()->json([
            'status' => 'success',
            'data_length' => strlen($qrData),
            'note' => 'QR size tetap konsisten berapapun panjang datanya'
        ]);


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
            ->where('m.Url_Menu', 'Home')
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
            ->where('Id_Role', 1)
            ->where('Flag_Aktif', 'Y')
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
                $prefix = 'FS' . $currenMonth . $currentYear;
    
                $lastSample = DB::table('N_EMI_LAB_PO_Sampel')
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
                    'Jenis_Aktivitas' => "Pengambilan Sampel",
                    'Status_Aktivitas' => 'Berhasil',
                    'Keterangan' => 'Untuk Nomor Po '. $request->No_Po. ' Berhasil Melakukan Pengambilan Sampel Dan Menunggu Proses Cetak',
                    'Tanggal' => $tanggalSqlServer,
                    'Id_Mesin' => $IdMesin,
                    'Jam' => $jamSqlServer,
                    'Id_User' => $pengguna->UserId,
                    'Flag_Berhasil_Cetak_QrCode' => 'Y',
                ];
                
                DB::table('N_EMI_LAB_Activity_Produksi_Sampel')->insert($payloadActivityProduksiSampel);
    
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
                    DB::table('N_EMI_LAB_PO_Sampel_Multi_QrCode')->insert($multiQrCodes);
                }
                
                $getDataPengguna = User::where('UserId', $pengguna->UserId)->first();
    
                if (!Hash::check($request->pin, $getDataPengguna->Pin)) {
                    return response()->json([
                        'success' => false,
                        'status' => 400,
                        'message' => 'PIN Salah !'
                    ], 401);
                }
                DB::table('N_EMI_LAB_PO_Sampel')->insert($payloadPoSampel);
                
                DB::commit();

            } catch (\Exception $e) {
                $payloadActivityProduksiSampel = [
                                    'No_Po' => $request->No_Po,
                                    'No_Split_Po' => $request->No_Split_Po,
                                    'No_Batch' => $request->No_Batch,
                                    'Jenis_Aktivitas' => "Pengambilan Sampel",
                                    'Status_Aktivitas' => 'gagal',
                                    'Keterangan' => 'Untuk Nomor Po '. $request->No_Po. ' Gagal Dalam Pengambilan Sampel, Data Tidak Tersimpan Di Sistem',
                                    'Tanggal' => $tanggalSqlServer,
                                    'Id_Mesin' => $IdMesin,
                                    'Jam' => $jamSqlServer,
                                    'Id_User' => $pengguna->UserId,
                                    'Flag_Berhasil_Cetak_QrCode' => null,
                ];
                    
                DB::table('N_EMI_LAB_Activity_Produksi_Sampel')->insert($payloadActivityProduksiSampel);
                DB::rollBack();
                Log::channel('POSampleController')->error('Error: ' . $e->getMessage());
                return response()->json([
                    'success' => true,
                    'status' => 500,
                    'message' => "Terjadi Kesalahan",
                ], 500); 
            }
            
            // ==========================================
            // BAGIAN PRINTING RUTIN (DYNAMIC LOOP)
            // ==========================================
            try {
                $getNamaSampel = DB::table('N_EMI_View_Barang')->where('Kode_Barang', $request->Kode_Barang)->first();
                $getNamaMesin = DB::table('EMI_Master_Mesin')->where('Id_Master_Mesin', $IdMesin)->first();
                
                $namaMesinStr  = $getNamaMesin->Nama_Mesin ?? "Default";
                $namaSampelStr = $getNamaSampel->Nama ?? '-';
                $batchStr      = $payloadPoSampel['No_Batch'];
                $tanggalStr    = date('d M Y', strtotime($payloadPoSampel['Tanggal']));
                $noSplitStr    = $request->No_Split_Po;
                $jenisAnalisaStr = ''; // Rutin kosong

                // Cek template valid
                if (!$transaksiTemplate || !$masterTemplate || $templateItems->isEmpty()) {
                     throw new \Exception("Template printer tidak ditemukan di database.");
                }

                // Siapkan Data Loop
                $dataToPrint = [];
                if ($request->Flag_Multi_Qrcode === "Y") {
                     foreach ($multiQrCodes as $item) {
                         $dataToPrint[] = $item['No_Po_Multi'];
                     }
                } else {
                     $dataToPrint[] = $payloadPoSampel["No_Sampel"];
                }

                // LOOPING CETAK
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

                    // Kirim API
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
                    'Jenis_Aktivitas' => "Cetak QrCode",
                    'Status_Aktivitas' => 'berhasil',
                    'Keterangan' => 'Untuk Nomor Po '. $request->No_Po. ' Berhasil Melakukan Cetak QrCode',
                    'Tanggal' => $tanggalSqlServer,
                    'Id_Mesin' => $IdMesin,
                    'Jam' => $jamSqlServer,
                    'Id_User' => $pengguna->UserId,
                    'Flag_Berhasil_Cetak_QrCode' => null,
                ];
                    
                DB::table('N_EMI_LAB_Activity_Produksi_Sampel')->insert($payloadActivityProduksiSampel);
            } catch (\Exception $e) {
                $payloadActivityProduksiSampel = [
                                    'No_Po' => $request->No_Po,
                                    'No_Split_Po' => $request->No_Split_Po,
                                    'No_Batch' => $request->No_Batch,
                                    'Jenis_Aktivitas' => "Cetak QrCode",
                                    'Status_Aktivitas' => 'gagal',
                                    'Keterangan' => 'Untuk Nomor Po '. $request->No_Po. ' Gagal Melakukan Cetak QrCode',
                                    'Tanggal' => $tanggalSqlServer,
                                    'Id_Mesin' => $IdMesin,
                                    'Jam' => $jamSqlServer,
                                    'Id_User' => $pengguna->UserId,
                                    'Flag_Berhasil_Cetak_QrCode' => null,
                    ];
                    
                    DB::table('N_EMI_LAB_Activity_Produksi_Sampel')->insert($payloadActivityProduksiSampel);
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
                    $prefix = 'FS' . $currenMonth . $currentYear;

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

                    if($request->Flag_Multi_Qrcode === 'Y'){
                        $jumlahLoop = $request->Jumlah_Print_QRCode;

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

                        DB::table('N_EMI_LAB_PO_Sampel')->insert($payloadPoSampels);

                        if (!empty($multiQrCodes)) {
                            DB::table('N_EMI_LAB_PO_Sampel_Multi_QrCode')->insert($multiQrCodes);
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
                      
                        DB::table('N_EMI_LAB_PO_Sampel')->insert($payloadPoSampels);
                    
                        if (!empty($multiQrCodes)) {
                            DB::table('N_EMI_LAB_PO_Sampel_Multi_QrCode')->insert($multiQrCodes);
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
                    Log::channel('POSampleController')->error('Error: ' . $e->getMessage());
                    return response()->json([
                        'success' => true,
                        'status' => 500,
                        'message' => "Terjadi Kesalahan",
                    ], 500); 
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
                    // Ambil nama Analisa
                    $jenisAnalisaFromDb = $namaAnalisa->Jenis_Analisa ?? '';

                    if (!$transaksiTemplate || !$masterTemplate || $templateItems->isEmpty()) {
                        throw new \Exception("Template printer tidak ditemukan di database.");
                    }

                    if ($request->Flag_Multi_Qrcode === "Y") {
                        $total = count($multiQrCodes);
                        foreach ($multiQrCodes as $index => $item) {
                            $qrDataStr = $item['No_Po_Multi'];
                            // Hanya print analisa di label terakhir (index == total - 1)
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
                                        'Jenis_Aktivitas' => "Cetak QrCode",
                                        'Status_Aktivitas' => 'berhasil',
                                        'Keterangan' => 'Untuk Nomor Po '. $request->No_Po. ' Berhasil Melakukan Cetak QrCode',
                                        'Tanggal' => $tanggalSqlServer,
                                        'Id_Mesin' => $IdMesin,
                                        'Jam' => $jamSqlServer,
                                        'Id_User' => $pengguna->UserId,
                                        'Flag_Berhasil_Cetak_QrCode' => null,
                    ];
                        
                    DB::table('N_EMI_LAB_Activity_Produksi_Sampel')->insert($payloadActivityProduksiSampel);
                } catch (\Exception $e) {
                    $payloadActivityProduksiSampel = [
                                        'No_Po' => $request->No_Po,
                                        'No_Split_Po' => $request->No_Split_Po,
                                        'No_Batch' => $request->No_Batch,
                                        'Jenis_Aktivitas' => "Cetak QrCode",
                                        'Status_Aktivitas' => 'gagal',
                                        'Keterangan' => 'Untuk Nomor Po '. $request->No_Po. ' Gagal Melakukan Cetak QrCode',
                                        'Tanggal' => $tanggalSqlServer,
                                        'Id_Mesin' => $IdMesin,
                                        'Jam' => $jamSqlServer,
                                        'Id_User' => $pengguna->UserId,
                                        'Flag_Berhasil_Cetak_QrCode' => null,
                    ];
                    
                    DB::table('N_EMI_LAB_Activity_Produksi_Sampel')->insert($payloadActivityProduksiSampel);
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
                    $prefix = 'FS' . $currenMonth . $currentYear;

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

                        DB::table('N_EMI_LAB_PO_Sampel')->insert($payloadPoSampels);

                        if (!empty($multiQrCodes)) {
                            DB::table('N_EMI_LAB_PO_Sampel_Multi_QrCode')->insert($multiQrCodes);
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

                        DB::table('N_EMI_LAB_PO_Sampel')->insert($payloadPoSampels);
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
                    Log::channel('POSampleController')->error('Error: ' . $e->getMessage());
                    return response()->json([
                        'success' => true,
                        'status' => 500,
                        'message' => "Terjadi Kesalahan",
                    ], 500); 
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
                                        'Jenis_Aktivitas' => "Cetak QrCode",
                                        'Status_Aktivitas' => 'berhasil',
                                        'Keterangan' => 'Untuk Nomor Po '. $request->No_Po. ' Berhasil Melakukan Cetak QrCode',
                                        'Tanggal' => $tanggalSqlServer,
                                        'Id_Mesin' => $IdMesin,
                                        'Jam' => $jamSqlServer,
                                        'Id_User' => $pengguna->UserId,
                                        'Flag_Berhasil_Cetak_QrCode' => null,
                    ];
                    
                    DB::table('N_EMI_LAB_Activity_Produksi_Sampel')->insert($payloadActivityProduksiSampel);
                } catch (\Exception $e) {
                    $payloadActivityProduksiSampel = [
                                        'No_Po' => $request->No_Po,
                                        'No_Split_Po' => $request->No_Split_Po,
                                        'No_Batch' => $request->No_Batch,
                                        'Jenis_Aktivitas' => "Cetak QrCode",
                                        'Status_Aktivitas' => 'gagal',
                                        'Keterangan' => 'Untuk Nomor Po '. $request->No_Po. ' Gagal Melakukan Cetak QrCode',
                                        'Tanggal' => $tanggalSqlServer,
                                        'Id_Mesin' => $IdMesin,
                                        'Jam' => $jamSqlServer,
                                        'Id_User' => $pengguna->UserId,
                                        'Flag_Berhasil_Cetak_QrCode' => null,
                    ];
                    
                    DB::table('N_EMI_LAB_Activity_Produksi_Sampel')->insert($payloadActivityProduksiSampel);
                    return response()->json([
                        'success' => true,
                        'message' => 'Data berhasil disimpan, namun terjadi kesalahan saat cetak: ' . $e->getMessage()
                    ], 201);
                }
            }
        }

       return response()->json([
            'success'     => true,
            'status'      => 201, // Ubah ke 201 karena berhasil create
            'message'     => 'Data berhasil disimpan dan siap dicetak',
            'print_jobs'  => $allPrintData, 
            'printer_url' => rtrim(env('URL_CLIENT'), '/')
        ], 201);
    }

    public function testingCetakQr()
    {
         $ip = '192.168.21.13';
                $port = 9100;

        $qrData = 'FS0725-0001';

        $tsplCommand  = "SIZE 50 mm, 20 mm\r\n";
        $tsplCommand .= "GAP 2 mm, 0\r\n";
        $tsplCommand .= "DIRECTION 1\r\n";
        $tsplCommand .= "CLS\r\n";
        $tsplCommand .= "TEXT 30,15,\"2\",0,1,1,\"LIFE CAT\"\r\n";
        $tsplCommand .= "TEXT 30,55,\"1\",0,1,1,\"$qrData\"\r\n";
        $tsplCommand .= "TEXT 30,80,\"1\",0,1,1,\"TESTFS\"\r\n";
        $tsplCommand .= "TEXT 30,100,\"1\",0,1,1,\"Batch 1\"\r\n";
        $tsplCommand .= "TEXT 30,127,\"2\",0,1,1,\"TANGGAL\"\r\n";
        $tsplCommand .= "TEXT 250,55,\"1\",0,1,1,\"GRINDER\"\r\n";
        $tsplCommand .= "QRCODE 250,80,H,3,A,0,M2,S7,\"$qrData\"\r\n";           
        $tsplCommand .= "PRINT 1\r\n";

        $fp = stream_socket_client("tcp://$ip:$port", $errno, $errstr, 5);
                            if (!$fp) {
                                return response()->json([
                                    'success' => true,
                                    'message' => 'Data berhasil disimpan, namun terjadi masalah pada printer saat cetak Multi QR: ' . $errstr
                                ], 201);
                            }

                            fwrite($fp, $tsplCommand);
                            fclose($fp);
    }
}