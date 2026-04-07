<?php

namespace App\Http\Controllers;

use App\Models\POSample;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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
        // dd($request->all());
        // $checkedOnlyHuman = DB::table('N_EMI_LAB_Users')
        //         ->where('UserId', $request->namaKaryawan)
        //         ->first();
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
                return response()->json([
                    'message' => 'Gagal menyimpan data: ' . $e->getMessage()
                ], 500);
            }
            
            try {
                $getNamaSampel = DB::table('N_EMI_View_Barang')->where('Kode_Barang', $request->Kode_Barang)->first();
                $getNamaMesin = DB::table('EMI_Master_Mesin')->where('Id_Master_Mesin', $IdMesin)->first();
                $namaMesin = $getNamaMesin->Nama_Mesin ?? "Default";

                $no_Split = $request->No_Split_Po;
                if ($request->Flag_Multi_Qrcode === "Y") {
                    $batch = $payloadPoSampel['No_Batch'];
                    $tanggal = date('d M Y', strtotime($payloadPoSampel['Tanggal']));
                    foreach ($multiQrCodes as $item) {
                        $qrData = $item['No_Po_Multi'];
                        $tsplCommand  = "SIZE 50 mm, 20 mm\r\n";
                        $tsplCommand .= "GAP 2 mm, 0\r\n";
                        $tsplCommand .= "DIRECTION 1\r\n";
                        $tsplCommand .= "CLS\r\n";

                         $tsplCommand .= "TEXT 30,15,\"2\",0,1,1,\"$getNamaSampel->Nama\"\r\n";
                         $tsplCommand .= "TEXT 30,55,\"1\",0,1,1,\"$qrData\"\r\n";
                         $tsplCommand .= "TEXT 30,80,\"1\",0,1,1,\"$no_Split\"\r\n";
                         $tsplCommand .= "TEXT 30,100,\"1\",0,1,1,\"Batch $batch\"\r\n";
                         // Tanggal (tebal)
                         $tsplCommand .= "TEXT 30,127,\"2\",0,1,1,\"$tanggal\"\r\n";
     
                         $tsplCommand .= "TEXT 250,55,\"1\",0,1,1,\"$namaMesin\"\r\n";
                         $tsplCommand .= "QRCODE 250,80,H,3,A,0,M2,S7,\"$qrData\"\r\n";           
                        // baru print
                        $tsplCommand .= "PRINT 1\r\n";
    
                        $response = Http::timeout(360)->post('http://192.168.11.10:3000/print', [
                            'data' => $tsplCommand
                        ]);

                        if (!$response->successful()) {
                                return response()->json([
                                    'success' => true,
                                    'message' => '✅ Data disimpan, tapi gagal kirim ke printer (API).'
                                ]);
                        }
                    }
                } else {
                    $batch = $payloadPoSampel['No_Batch'];
                    $tanggal = date('d M Y', strtotime($payloadPoSampel['Tanggal']));
                    $qrData = $payloadPoSampel["No_Sampel"];

    
                    $tsplCommand  = "SIZE 50 mm, 20 mm\r\n";
                    $tsplCommand .= "GAP 2 mm, 0\r\n";
                    $tsplCommand .= "DIRECTION 1\r\n";
                    $tsplCommand .= "CLS\r\n";
                    $tsplCommand .= "TEXT 30,15,\"2\",0,1,1,\"$getNamaSampel->Nama\"\r\n";
                    $tsplCommand .= "TEXT 30,55,\"1\",0,1,1,\"$qrData\"\r\n";
                    $tsplCommand .= "TEXT 30,80,\"1\",0,1,1,\"$no_Split\"\r\n";
                    $tsplCommand .= "TEXT 30,100,\"1\",0,1,1,\"Batch $batch\"\r\n";
                    $tsplCommand .= "TEXT 30,127,\"2\",0,1,1,\"$tanggal\"\r\n";

                    $tsplCommand .= "TEXT 250,55,\"1\",0,1,1,\"$namaMesin\"\r\n";
                    $tsplCommand .= "QRCODE 250,80,H,3,A,0,M2,S7,\"$qrData\"\r\n";             
                    $tsplCommand .= "PRINT 1\r\n";
                    $response = Http::timeout(360)->post('http://192.168.11.10:3000/print', [
                            'data' => $tsplCommand
                    ]);
                        
                    if (!$response->successful()) {
                                return response()->json([
                                    'success' => true,
                                    'message' => '✅ Data disimpan, tapi gagal kirim ke printer (API).'
                                ]);
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
                            $isLast = $i === ($jumlahCetak - 1); // ini benar
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

                    
                        // Validasi PIN
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
                    
                            // Insert ke N_EMI_LAB_PO_Sampel
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
                    
                        // Simpan Multi QRCode
                        if (!empty($multiQrCodes)) {
                            DB::table('N_EMI_LAB_PO_Sampel_Multi_QrCode')->insert($multiQrCodes);
                        }
                    
                        // Validasi PIN
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
                    return response()->json([
                        'message' => 'Gagal menyimpan data: ' . $e->getMessage()
                    ], 500);
                }
                
                try {
                    $getNamaSampel = DB::table('N_EMI_View_Barang')->where('Kode_Barang', $request->Kode_Barang)->first();
                    $namaAnalisa = DB::table('N_EMI_LAB_Jenis_Analisa')->where('id', $Id_Jenis_Analisa_Khusus)->first();
                    $getNamaMesin = DB::table('EMI_Master_Mesin')->where('Id_Master_Mesin', $IdMesin)->first();
                    $namaMesin = $getNamaMesin->Nama_Mesin ?? "Default";
                    $no_Split = $request->No_Split_Po;
                    if ($request->Flag_Multi_Qrcode === "Y") {
                        $batch = $request->No_Batch;
                        $tanggal = date('d M Y', strtotime(date('Y-m-d')));

                        $total = count($multiQrCodes); // total item
                        foreach ($multiQrCodes as $index => $item) {
                            $qrData = $item['No_Po_Multi'];

                            $tsplCommand  = "SIZE 50 mm, 20 mm\r\n";
                            $tsplCommand .= "GAP 2 mm, 0\r\n";
                            $tsplCommand .= "DIRECTION 1\r\n";
                            $tsplCommand .= "CLS\r\n";

                            // Teks utama
                            $tsplCommand .= "TEXT 30,20,\"2\",0,1,1,\"{$getNamaSampel->Nama}\"\r\n";
                            $tsplCommand .= "TEXT 30,60,\"1\",0,1,1,\"$qrData\"\r\n";
                            $tsplCommand .= "TEXT 30,85,\"1\",0,1,1,\"$no_Split\"\r\n";
                            $tsplCommand .= "TEXT 30,105,\"1\",0,1,1,\"Batch $batch\"\r\n";
                            $tsplCommand .= "TEXT 30,127,\"2\",0,1,1,\"$tanggal\"\r\n";

                            if ($index === $total - 1) {
                                $tsplCommand .= "TEXT 30,20,\"2\",0,1,1,\"{$getNamaSampel->Nama}\"\r\n";
                                $tsplCommand .= "TEXT 30,60,\"1\",0,1,1,\"$qrData\"\r\n";
                                $tsplCommand .= "TEXT 30,85,\"1\",0,1,1,\"$no_Split\"\r\n";
                                $tsplCommand .= "TEXT 30,105,\"1\",0,1,1,\"Batch $batch\"\r\n";
                                $tsplCommand .= "TEXT 30,127,\"2\",0,1,1,\"$tanggal\"\r\n";
    
                                $tsplCommand .= "TEXT 250,46,\"1\",0,1,1,\"$namaMesin\"\r\n";
                                $tsplCommand .= "QRCODE 250,64,H,3,A,0,M2,S7,\"$qrData\"\r\n";
    
                                $tsplCommand .= "TEXT 230,145,\"1\",0,1,1,\"{$namaAnalisa->Jenis_Analisa}\"\r\n";
                            } else {
                                $tsplCommand .= "TEXT 30,15,\"2\",0,1,1,\"$getNamaSampel->Nama\"\r\n";
                                $tsplCommand .= "TEXT 30,55,\"1\",0,1,1,\"$qrData\"\r\n";
                                $tsplCommand .= "TEXT 30,80,\"1\",0,1,1,\"$no_Split\"\r\n";
                                $tsplCommand .= "TEXT 30,100,\"1\",0,1,1,\"Batch $batch\"\r\n";
                                $tsplCommand .= "TEXT 30,127,\"2\",0,1,1,\"$tanggal\"\r\n";

                                $tsplCommand .= "TEXT 250,55,\"1\",0,1,1,\"$namaMesin\"\r\n";
                                $tsplCommand .= "QRCODE 250,80,H,3,A,0,M2,S7,\"$qrData\"\r\n";      
                            }

                            $tsplCommand .= "PRINT 1\r\n";

                            $response = Http::timeout(360)->post('http://192.168.11.10:3000/print', [
                                'data' => $tsplCommand
                            ]);
                            
                            if (!$response->successful()) {
                                return response()->json([
                                    'success' => true,
                                    'message' => '✅ Data disimpan, tapi gagal kirim ke printer (API).'
                                ]);
                            }
                        }
                    }else {
                        $batch = $request->No_Batch;
                        $tanggal = date('d M Y', strtotime(date('Y-m-d')));

                        $total = count($payloadPoSampels); // total item
                        foreach ($payloadPoSampels as $index => $item) {
                            $qrData = $item['No_Sampel'];

                            $tsplCommand  = "SIZE 50 mm, 20 mm\r\n";
                            $tsplCommand .= "GAP 2 mm, 0\r\n";
                            $tsplCommand .= "DIRECTION 1\r\n";
                            $tsplCommand .= "CLS\r\n";

                            if ($index === $total - 1) {
                                $tsplCommand .= "TEXT 30,20,\"2\",0,1,1,\"{$getNamaSampel->Nama}\"\r\n";
                                $tsplCommand .= "TEXT 30,60,\"1\",0,1,1,\"$qrData\"\r\n";
                                $tsplCommand .= "TEXT 30,85,\"1\",0,1,1,\"$no_Split\"\r\n";
                                $tsplCommand .= "TEXT 30,105,\"1\",0,1,1,\"Batch $batch\"\r\n";
                                $tsplCommand .= "TEXT 30,127,\"2\",0,1,1,\"$tanggal\"\r\n";
    
                                $tsplCommand .= "TEXT 250,46,\"1\",0,1,1,\"$namaMesin\"\r\n";
                                $tsplCommand .= "QRCODE 250,64,H,3,A,0,M2,S7,\"$qrData\"\r\n";
    
                                $tsplCommand .= "TEXT 230,145,\"1\",0,1,1,\"{$namaAnalisa->Jenis_Analisa}\"\r\n";
                            } else {
                                $tsplCommand .= "TEXT 30,15,\"2\",0,1,1,\"$getNamaSampel->Nama\"\r\n";
                                $tsplCommand .= "TEXT 30,55,\"1\",0,1,1,\"$qrData\"\r\n";
                                $tsplCommand .= "TEXT 30,80,\"1\",0,1,1,\"$no_Split\"\r\n";
                                $tsplCommand .= "TEXT 30,100,\"1\",0,1,1,\"Batch $batch\"\r\n";
                                $tsplCommand .= "TEXT 30,127,\"2\",0,1,1,\"$tanggal\"\r\n";

                                $tsplCommand .= "TEXT 250,55,\"1\",0,1,1,\"$namaMesin\"\r\n";
                                $tsplCommand .= "QRCODE 250,80,H,3,A,0,M2,S7,\"$qrData\"\r\n";      
                            }

                            $tsplCommand .= "PRINT 1\r\n";

                            $response = Http::timeout(360)->post('http://192.168.11.10:3000/print', [
                                'data' => $tsplCommand
                             ]);
                        
                            if (!$response->successful()) {
                                return response()->json([
                                    'success' => true,
                                    'message' => '✅ Data disimpan, tapi gagal kirim ke printer (API).'
                                ]);
                            }
                        }
                    }
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

                    // Ambil No_Sampel terakhir
                    $lastSample = DB::table('N_EMI_LAB_PO_Sampel')
                        ->where('No_Sampel', 'like', $prefix . '-%')
                        ->orderByDesc('id')
                        ->value('No_Sampel');

                    $lastNumber = $lastSample
                        ? (int) substr($lastSample, strpos($lastSample, '-') + 1)
                        : 0;

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

                    // Validasi PIN
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
                    return response()->json([
                        'message' => 'Gagal menyimpan data: ' . $e->getMessage()
                    ], 500);
                }


                try {
                    $getNamaSampel = DB::table('N_EMI_View_Barang')->where('Kode_Barang', $request->Kode_Barang)->first();
                    $namaAnalisa = DB::table('N_EMI_LAB_Jenis_Analisa')->where('id', $Id_Jenis_Analisa_Khusus)->first();
                    $no_Split = $request->No_Split_Po;
                    if ($request->Flag_Multi_Qrcode === "Y") {
                        $batch = $request->No_Batch;
                        $tanggal = date('d M Y', strtotime(date('Y-m-d')));
                        $getNamaMesin = DB::table('EMI_Master_Mesin')->where('Id_Master_Mesin', $IdMesin)->first();
                        $namaMesin = $getNamaMesin->Nama_Mesin ?? "Default";

                        $total = count($multiQrCodes);
                        foreach ($multiQrCodes as $index => $item) {
                            $qrData = $item['No_Po_Multi'];

                            $tsplCommand  = "SIZE 50 mm, 20 mm\r\n";
                            $tsplCommand .= "GAP 2 mm, 0\r\n";
                            $tsplCommand .= "DIRECTION 1\r\n";
                            $tsplCommand .= "CLS\r\n";

                            // Teks utama
                            $tsplCommand .= "TEXT 30,20,\"2\",0,1,1,\"{$getNamaSampel->Nama}\"\r\n";
                            $tsplCommand .= "TEXT 30,60,\"1\",0,1,1,\"$qrData\"\r\n";
                            $tsplCommand .= "TEXT 30,85,\"1\",0,1,1,\"$no_Split\"\r\n";
                            $tsplCommand .= "TEXT 30,105,\"1\",0,1,1,\"Batch $batch\"\r\n";
                            $tsplCommand .= "TEXT 30,127,\"2\",0,1,1,\"$tanggal\"\r\n";

                            $tsplCommand .= "TEXT 250,46,\"1\",0,1,1,\"$namaMesin\"\r\n";
                            $tsplCommand .= "QRCODE 250,64,H,3,A,0,M2,S7,\"$qrData\"\r\n";
                            
                            $tsplCommand .= "TEXT 230,145,\"1\",0,1,1,\"{$namaAnalisa->Jenis_Analisa}\"\r\n";

                            $tsplCommand .= "PRINT 1\r\n";

                            $response = Http::timeout(360)->post('http://192.168.11.10:3000/print', [
                                'data' => $tsplCommand
                            ]);
                            
                            if (!$response->successful()) {
                            
                            return response()->json([
                                    'success' => true,
                                    'message' => '✅ Data disimpan, tapi gagal kirim ke printer (API).'
                                ]);
                            }
                        }
                    }else {
                        $batch = $request->No_Batch;
                        $tanggal = date('d M Y', strtotime(date('Y-m-d')));
                        $getNamaMesin = DB::table('EMI_Master_Mesin')->where('Id_Master_Mesin', $IdMesin)->first();
                        $namaMesin = $getNamaMesin->Nama_Mesin ?? "Default";
                        $total = count($payloadPoSampels);
                        foreach ($payloadPoSampels as $index => $item) {
                            $qrData = $item['No_Sampel'];

                            $tsplCommand  = "SIZE 50 mm, 20 mm\r\n";
                            $tsplCommand .= "GAP 2 mm, 0\r\n";
                            $tsplCommand .= "DIRECTION 1\r\n";
                            $tsplCommand .= "CLS\r\n";

                            $tsplCommand .= "TEXT 30,20,\"2\",0,1,1,\"{$getNamaSampel->Nama}\"\r\n";
                            $tsplCommand .= "TEXT 30,60,\"1\",0,1,1,\"$qrData\"\r\n";
                            $tsplCommand .= "TEXT 30,85,\"1\",0,1,1,\"$no_Split\"\r\n";
                            $tsplCommand .= "TEXT 30,105,\"1\",0,1,1,\"Batch $batch\"\r\n";
                            $tsplCommand .= "TEXT 30,127,\"2\",0,1,1,\"$tanggal\"\r\n";

                            $tsplCommand .= "TEXT 250,46,\"1\",0,1,1,\"$namaMesin\"\r\n";
                            $tsplCommand .= "QRCODE 250,64,H,3,A,0,M2,S7,\"$qrData\"\r\n";

                            $tsplCommand .= "TEXT 230,145,\"1\",0,1,1,\"{$namaAnalisa->Jenis_Analisa}\"\r\n";

                            $tsplCommand .= "PRINT 1\r\n";

                            $response = Http::timeout(360)->post('http://192.168.11.10:3000/print', [
                                'data' => $tsplCommand
                            ]);
                            
                            if (!$response->successful()) {
                                return response()->json([
                                    'success' => true,
                                    'message' => '✅ Data disimpan, tapi gagal kirim ke printer (API).'
                                        ]);
                                    }
                                }
                            }

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
            'success' => true,
            'message' => 'Data berhasil disimpan dan cetak selesai'
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
    /**
     * Display the specified resource.
     */
    public function show(POSample $pOSample)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(POSample $pOSample)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, POSample $pOSample)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(POSample $pOSample)
    {
        //
    }
}