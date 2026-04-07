<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\PrinterTemplatesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

class PrinterTemplatesControllerController extends Controller
{
    public function masterViewPrinter()
    {
        return inertia("vue/dashboard/printer/master/HomeMasterPrinter"); 
    }

    public function masterViewPrinterItems()
    {
        return inertia("vue/dashboard/printer/binding/HomePrinterItems"); 
    }

    public function masterViewPrinterTransaksi() 
    {
        $pengguna = Auth::user();
        $userId = $pengguna->UserId;
        // dd($pengguna);


        $lastHistory = DB::table('N_EMI_LAB_Printer_Template_Transaksi')
            ->where('Flag_Default', 'Y')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Id_Template_Transaksi', 'desc')
            ->first();

        $userRoles = DB::table('N_EMI_LAB_User_Roles as ur')
            ->join('N_EMI_LAB_Roles as r', 'ur.Id_Role', '=', 'r.Id_Role')
            ->where('ur.Id_User', $userId)
            ->where('r.Flag_Aktif', 'Y')
            ->select('r.Id_Role', 'r.Nama_Role', 'r.Kode_Role')
            ->get();

    
        return inertia("vue/dashboard/printer/transaksi/HomePrinterTransaksi", [
            'lastHistory' => $lastHistory,
            'pengguna' => $userId,
            'userRoles' => $userRoles
        ]);
    }

    public function getDataCurrent(Request $request)
    {
        try {

            $limit = (int) $request->query('limit', 10);
            $page = (int) $request->query('page', 1);
            $search = $request->query('search');
            $offset = ($page - 1) * $limit;

            $query = DB::table('N_EMI_LAB_Master_Printer_Templates');

            // 🔎 SEARCH
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('Nama_Template', 'like', "%{$search}%")
                    ->orWhere('Lebar_Label', 'like', "%{$search}%")
                    ->orWhere('Tinggi_Label', 'like', "%{$search}%")
                    ->orWhere('Flag_Aktif', 'like', "%{$search}%");
                });
            }

            // Total setelah filter
            $total = $query->count();

            // Data dengan pagination
            $getData = $query
                ->orderBy('Id_Master_Printer_Templates', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get();

            if ($getData->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "Data Tidak Ditemukan"
                ], 404);
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Data Ditemukan",
                'result' => $getData,
                'page' => $page,
                'total_page' => ceil($total / $limit),
                'total_data' => $total
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => [
                    'error' => $e->getMessage()
                ]
            ], 500);
        }
    }

    public function storeMasterTemplatePrinter(Request $request)
    {
        $pengguna = Auth::user()->UserId;

        $request->validate([
            'Nama_Template' => 'required|string|max:255',
            'Lebar_Label' => 'required|integer',
            'Tinggi_Label' => 'required|integer',
            'Gap_Antar_Label' => 'nullable|integer',
            'Direction' => 'required|integer'
        ]);

        DB::beginTransaction();

        try {

            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow;

            $tanggalSqlServer = date('Y-m-d', strtotime($dt));
            $jamSqlServer = date('H:i:s', strtotime($dt));

            $payload = [
                'Nama_Template' => $request->Nama_Template,
                'Lebar_Label' => $request->Lebar_Label,
                'Tinggi_Label' => $request->Tinggi_Label,
                'Gap_Antar_Label' => $request->Gap_Antar_Label ?? 0,
                'Direction' => $request->Direction,
                'Tanggal' => $tanggalSqlServer,
                'Jam' => $jamSqlServer,
                'Id_User'   => $pengguna
            ];

            DB::table('N_EMI_LAB_Master_Printer_Templates')->insert($payload);

            DB::commit();

            return ResponseHelper::success($payload, "Data Berhasil Disimpan", 201);
        } catch (\Exception $e) {

            DB::rollback();

            Log::channel('PrinterTemplatesController')->error($e->getMessage());

            return ResponseHelper::error("Terjadi Kesalahan Server", 500);
        }
    }

    public function getDataMasterPrintOptions()
    {
        try {
            $getData = DB::table('N_EMI_LAB_Master_Printer_Templates')
                ->select('Id_Master_Printer_Templates', 'Nama_Template')
                ->orderBy('Id_Master_Printer_Templates', 'desc')
                ->get();

            if ($getData->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "Data Tidak Ditemukan"
                ], 404);
            }

            $encodedData = $getData->map(function ($item) {
                $item->Id_Master_Printer_Templates = Hashids::connection('custom')
                    ->encode($item->Id_Master_Printer_Templates);
                return $item;
            });

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Data Ditemukan",
                'result' => $encodedData,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => [
                    'error' => $e->getMessage()
                ]
            ], 500);
        }
    }

    public function storeMultipleItems(Request $request)
    {
        DB::beginTransaction();
        try {
            // Ambil Waktu Server & User
            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow;
            
            $tanggalSqlServer = date('Y-m-d', strtotime($dt));
            $jamSqlServer = date('H:i:s', strtotime($dt));
            
            $pengguna = Auth::user();
            $userId = $pengguna->UserId ?? 'SYSTEM';

            // Ambil Data Items
            $items = $request->input('items');

            if (empty($items) || !is_array($items)) {
                throw new \Exception("Data items tidak valid atau kosong.");
            }

            $insertData = [];

            foreach ($items as $index => $item) {
                // LOGIKA DECODE HASHIDS
                $idEncoded = $item['Id_Master_Printer_Templates'];
                $decodedArray = Hashids::connection('custom')->decode($idEncoded);
         

                if (empty($decodedArray)) {
                    throw new \Exception("Format ID Master Template tidak valid pada baris ke-" . ($index + 1));
                }
                
                $idMaster = $decodedArray[0];

                $insertData[] = [
                    'Id_Master_Printer_Templates' => $idMaster,
                    'Jenis' => $item['Jenis'],
                    'Lebar_Label' => !empty($item['Lebar_Label']) ? $item['Lebar_Label'] : 0,
                    'Posisi_X' => $item['Posisi_X'] ?? 0,
                    'Posisi_Y' => $item['Posisi_Y'] ?? 0,
                    'Font' => !empty($item['Font']) ? $item['Font'] : null,
                    'Rotation' => isset($item['Rotation']) ? $item['Rotation'] : 0,
                    'Scale_X' => isset($item['Scale_X']) ? $item['Scale_X'] : 1,
                    'Scale_Y' => isset($item['Scale_Y']) ? $item['Scale_Y'] : 1,
                    'Isi_Konten' => $item['Isi_Konten'] ?? '',
                    'Qr_Ecc' => !empty($item['Qr_Ecc']) ? $item['Qr_Ecc'] : null,
                    'Qr_Size' => !empty($item['Qr_Size']) ? $item['Qr_Size'] : null,
                    'Qr_Model' => !empty($item['Qr_Model']) ? $item['Qr_Model'] : null,
                    'Tanggal' => $tanggalSqlServer,
                    'Jam' => $jamSqlServer,
                    'Id_User' => $userId,
                    'Flag_Aktif' => 'Y'
                ];
            }

            // Lakukan Insert Batch
            if (count($insertData) > 0) {
                DB::table('N_EMI_LAB_Printer_Template_Items')->insert($insertData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil disimpan'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400); // Mengembalikan 400 Bad Request jika gagal decode/validasi
        }
    }

    public function setFirst(Request $request)
    {
        $pengguna = Auth::user();
        $userId = $pengguna->UserId ?? 'SYSTEM';

        // --- Decode ID Template ---
        try {
            $decoded = Hashids::connection('custom')->decode($request->Id_Master_Printer_Templates);
            if (empty($decoded)) {
                throw new \Exception('Format ID Template tidak valid.');
            }
            $id_template = $decoded[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Template tidak valid.'
            ], 400);
        }
        // --------------------------

        try {

            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow;
            
            $tanggalSqlServer = date('Y-m-d', strtotime($dt));
            $jamSqlServer = date('H:i:s', strtotime($dt));

            // Cek apakah sudah ada yang default
            $checkDefault = DB::table('N_EMI_LAB_Printer_Template_Transaksi')
                ->where('Flag_Default', 'Y')
                ->where('Flag_Aktif', 'Y')
                ->exists();

            // LOGIC BARU:
            // Jika sudah ada default ($checkDefault == true), maka yang baru ini 'T'.
            // Jika belum ada ($checkDefault == false), maka yang baru ini 'Y'.
            $flagDefault = $checkDefault ? 'T' : 'Y';
            $keterangan = $checkDefault ? 'Menambah template tambahan' : 'Setup pertama kali (Default)';

            DB::table('N_EMI_LAB_Printer_Template_Transaksi')->insert([
                'Id_Master_Printer_Templates' => $id_template,
                'Flag_Default' => $flagDefault, // Dinamis sesuai kondisi di atas
                'Tanggal'      => $tanggalSqlServer,
                'Jam'          => $jamSqlServer,
                'Id_User'      => $userId,
                'Keterangan'   => $keterangan,
                'Flag_Aktif'   => 'Y'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Template berhasil ditambahkan.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function toggleTemplate(Request $request)
    {
        $pengguna = Auth::user();
        $userId = $pengguna->UserId ?? 'SYSTEM';

        DB::beginTransaction();

        try {
            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow;
            
            $tanggalSqlServer = date('Y-m-d', strtotime($dt));
            $jamSqlServer = date('H:i:s', strtotime($dt));

            DB::table('N_EMI_LAB_Printer_Template_Transaksi')
                ->where('Flag_Default', 'Y')
                ->where('Id_Role', $request->Id_Role) 
                ->update([
                    'Flag_Default' => 'T'
                ]);

            DB::table('N_EMI_LAB_Printer_Template_Transaksi')->insert([
                'Id_Master_Printer_Templates' => $request->Id_Master_Printer_Templates, 
                'Flag_Default' => 'Y',
                'Tanggal' => $tanggalSqlServer,
                'Jam' => $jamSqlServer,
                'Id_User' => $userId,
                'Id_Role' => $request->Id_Role, 
                'Keterangan' => 'Ganti template (' . $request->Nama_Role . ')',
                'Flag_Aktif' => 'Y'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Template berhasil diganti untuk role ' . $request->Nama_Role
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getCurrentTemplates(Request $request)
{
    try {
        $search = $request->input('search');
        $limit = $request->input('limit', 10);
        
        $rawUserRoles = $request->session()->get('User_Roles', []);
        $userRoles = collect($rawUserRoles)->pluck('Id_Role')->toArray();

        $query = DB::table('N_EMI_LAB_Master_Printer_Templates as m')
            ->leftJoin('N_EMI_LAB_Printer_Template_Transaksi as t', function($join) use ($userRoles) {
                $join->on('m.Id_Master_Printer_Templates', '=', 't.Id_Master_Printer_Templates')
                    ->where('t.Flag_Default', '=', 'Y')
                    ->where('t.Flag_Aktif', '=', 'Y');
                
                if (!empty($userRoles)) {
                    $join->whereIn('t.Id_Role', $userRoles);
                }
            })
            ->select(
                'm.Id_Master_Printer_Templates',
                'm.Nama_Template',
                DB::raw("CASE WHEN t.Id_Master_Printer_Templates IS NOT NULL THEN 'Y' ELSE 'T' END as Is_Active")
            )
            ->where('m.Flag_Aktif', 'Y');

        if (!empty($search)) {
            $query->where('m.Nama_Template', 'like', '%' . $search . '%');
        }

        $query->groupBy('m.Id_Master_Printer_Templates', 'm.Nama_Template', 't.Id_Master_Printer_Templates'); 

        $data = $query->paginate($limit);

        return response()->json([
            'success' => true,
            'result' => $data->items(),
            'total_page' => $data->lastPage(),
            'total_data' => $data->total()
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage() . ' di baris ' . $e->getLine()
        ], 500);
    }
}
}
