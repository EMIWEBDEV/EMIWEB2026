<?php

namespace App\Http\Controllers\ManagementAksesKonten;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Vinkla\Hashids\Facades\Hashids;

class ManagementAksesKontenController extends Controller
{
    public function index()
    {
        // dd(Session::get("user_permissions"));
        return inertia("vue/dashboard/management-konten/HomeManagementKonten");
    }

    public function getDataGroupBy(Request $request)
    {
        $page   = (int) $request->input('page', 1);
        $limit  = (int) $request->input('limit', 10);
        $offset = ($page - 1) * $limit;

        $contentData = DB::table('N_EMI_LAB_Role_Konten_Access as rka')
            ->leftJoin('N_EMI_LAB_Jenis_Analisa as ja', 'rka.Id_Jenis_Analisa', '=', 'ja.id')
            ->select(
                'rka.Id_Page_Access',
                'rka.Id_Jenis_Analisa',
                'rka.Flag_Diizinkan',
                'ja.Jenis_Analisa'
            )
            ->get();
            
        $contentMap = [];
        foreach ($contentData as $content) {
            $pageId = $content->Id_Page_Access;
            if (!isset($contentMap[$pageId])) {
                $contentMap[$pageId] = [];
            }
            
            if ($content->Id_Jenis_Analisa) {
                $contentMap[$pageId][] = [
                    'Id_Jenis_Analisa' => Hashids::connection('custom')->encode($content->Id_Jenis_Analisa),
                    'Nama_Analisa'     => $content->Jenis_Analisa ?? 'Analisa Tidak Ditemukan',
                    'Flag_Diizinkan'   => $content->Flag_Diizinkan
                ];
            }
        }
        
        $rawData = DB::table('N_EMI_LAB_Role_Menu_Access as rma')
            ->leftJoin('N_EMI_LAB_Page_Access_2 as pa', 'rma.Id_Page_Access', '=', 'pa.Id_Page_Access')
            ->leftJoin('N_EMI_LAB_Menus as m', 'pa.Id_Menu', '=', 'm.Id_Menu')
            ->leftJoin('N_EMI_LAB_Users as u', 'pa.Id_User', '=', 'u.UserId')
            ->select(
                'u.Id_Lab_Users',       // TAHAP 1: Ambil Primary Key yang berupa Integer
                'u.UserId as Id_User',  // Tetap ambil UserId untuk Username
                'u.Nama',
                'pa.Id_Page_Access',
                'pa.Urutan_Menu',
                'rma.Id_Aksi',
                'm.Nama_Menu',
                'rma.Flag_Diizinkan'
            )
            ->whereNotNull('pa.Id_Page_Access') 
            ->orderBy('pa.Urutan_Menu', 'asc')
            ->get();

        if ($rawData->isEmpty()) {
            return response()->json([
                'success' => false, 
                'status' => 404, 
                'message' => "Data Tidak Ditemukan!"
            ], 404);
        }

        $grouped = [];
        foreach ($rawData as $row) {
            if (!$row->Id_User) continue;

            $userId = $row->Id_User;
            if (!isset($grouped[$userId])) {
                $grouped[$userId] = [
                    'Id_User'  => Hashids::connection('custom')->encode($row->Id_Lab_Users),
                    'Nama'     => $row->Nama ?? 'Tanpa Nama',
                    'Username' => $row->Id_User ?? '-', 
                    'Pages'    => []
                ];
            }

            $pageId = $row->Id_Page_Access;
            
            if (!isset($grouped[$userId]['Pages'][$pageId])) {
                $grouped[$userId]['Pages'][$pageId] = [
                    'Id_Page_Access' => Hashids::connection('custom')->encode($pageId),
                    'Nama_Menu'      => $row->Nama_Menu ?? 'Menu Tidak Diketahui',
                    'Akses'          => [],
                    'Analisa'        => $contentMap[$pageId] ?? [] 
                ];
            }

            if ($row->Id_Aksi) {
                 $grouped[$userId]['Pages'][$pageId]['Akses'][] = [
                    'Id_Aksi'        => Hashids::connection('custom')->encode($row->Id_Aksi),
                    'Flag_Diizinkan' => $row->Flag_Diizinkan,
                ];
            }
        }

        $result = array_map(function ($user) {
            $user['Pages'] = array_values($user['Pages']);
            return $user;
        }, array_values($grouped));

        $total = count($result);
        $paged = array_slice($result, $offset, $limit);

        return response()->json([
            'success'    => true,
            'status'     => 200,
            'result'     => $paged,
            'page'       => $page,
            'total_page' => ceil($total / $limit),
            'total_data' => $total
        ]);
    }

    public function getDataKlasifikasiAksiJson()
    {
        $getData = DB::table('N_EMI_LAB_Klasifikasi_Aksi')->get();

        if($getData->isEmpty()){
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => "Data Tidak Ditemukan !"
            ], 404);
        }

        $encodedData = $getData->map(function ($item) {
            $item->Id_Klasifikasi_Actions = Hashids::connection('custom')->encode($item->Id_Klasifikasi_Actions);
            return $item;
        });

        return ResponseHelper::success($encodedData, "Data Ditemukan", 200);
    }
    
    public function getDataPageAccessJson() 
    {
        $getData = DB::table('N_EMI_LAB_Page_Access_2')
            ->select(
                'N_EMI_LAB_Page_Access_2.Id_Page_Access', 
                'N_EMI_LAB_Users.Nama',
                'N_EMI_LAB_Menus.Nama_Menu'
            )
            ->join(
                'N_EMI_LAB_Menus',
                'N_EMI_LAB_Page_Access_2.Id_Menu',
                '=',
                'N_EMI_LAB_Menus.Id_Menu'
            )
            ->join(
                'N_EMI_LAB_Users',
                'N_EMI_LAB_Page_Access_2.Id_User',
                '=',
                'N_EMI_LAB_Users.UserId'
            )
            ->whereNotExists(function($query) {
                $query->select(DB::raw(1))
                    ->from('N_EMI_LAB_Role_Menu_Access')
                    ->whereRaw('N_EMI_LAB_Role_Menu_Access.Id_Page_Access = N_EMI_LAB_Page_Access_2.Id_Page_Access');
            })
            ->get();

        if($getData->isEmpty()){
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => "Data Tidak Ditemukan !"
            ], 404);
        }

        $encodedData = $getData->map(function ($item) {
            $item->Id_Page_Access = Hashids::connection('custom')->encode($item->Id_Page_Access);
            return $item;
        });

        return ResponseHelper::success($encodedData, "Data Ditemukan", 200);
    }

    public function getDataJenisAnalisa()
    {
        $getData = DB::table('N_EMI_LAB_Jenis_Analisa')->get();

        if($getData->isEmpty()){
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => "Data Tidak Ditemukan !"
            ], 404);
        }

        $encodedData = $getData->map(function ($item) {
            $item->id = Hashids::connection('custom')->encode($item->id);
            return $item;
        });

        return ResponseHelper::success($encodedData, "Data Ditemukan", 200);
    }

    public function store(Request $request)
    {
        // Validasi sesuai dengan key dari payload (access_rules)
        $validator = Validator::make($request->all(), [
            'access_rules' => 'required|array|min:1',
            'access_rules.*.ID_Page_Access' => 'required|string',
            'access_rules.*.Flag_Diizinkan' => 'required|string|in:Y,T',
            'access_rules.*.ID_Klasifikasi_Actions' => 'nullable|string',
            'access_rules.*.Id_Jenis_Indikator' => 'nullable|string',
            'access_rules.*.Id_Jenis_Soal' => 'nullable|string',
            'access_rules.*.Kategori' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'status' => 422, 'message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            // Ambil dari access_rules, bukan data
            $accessRules = $request->access_rules;
            $pageContentMap = []; 

            // 1. Lakukan pemetaan (mapping) terlebih dahulu untuk mencari menu mana saja
            // yang memiliki hak akses konten (Id_Jenis_Indikator tidak kosong)
            foreach ($accessRules as $rule) {
                if (empty($rule['ID_Klasifikasi_Actions']) && !empty($rule['Id_Jenis_Indikator'])) {
                    $decoded_page_id = Hashids::connection('custom')->decode($rule['ID_Page_Access'])[0];
                    $pageContentMap[$decoded_page_id] = true; 
                }
            }

            // 2. Lakukan iterasi kedua untuk menyimpan data ke database
            foreach ($accessRules as $rule) {
                $decoded_page_id = Hashids::connection('custom')->decode($rule['ID_Page_Access'])[0];
                
                // Jika baris ini adalah Aksi (Menu Access)
                if (!empty($rule['ID_Klasifikasi_Actions'])) {
                    $decoded_action_id = Hashids::connection('custom')->decode($rule['ID_Klasifikasi_Actions'])[0];
                    
                    // Deklarasikan Flag_Access_Konten berdasarkan mapping yang sudah dibuat di step 1
                    $flagAccessKonten = isset($pageContentMap[$decoded_page_id]) ? 'Y' : 'T';

                    DB::table('N_EMI_LAB_Role_Menu_Access')->updateOrInsert(
                        [
                            'Id_Page_Access' => $decoded_page_id,
                            'Id_Aksi' => $decoded_action_id,
                        ],
                        [
                            'Flag_Diizinkan' => 'Y',
                            'Flag_Access_Konten' => $flagAccessKonten,
                        ]
                    );
                }

                // Jika baris ini adalah Konten (Jenis Analisa)
                // Ciri-cirinya Aksi kosong, tapi Indikator terisi
                if (empty($rule['ID_Klasifikasi_Actions']) && !empty($rule['Id_Jenis_Indikator'])) {
                    $decoded_analisa_id = Hashids::connection('custom')->decode($rule['Id_Jenis_Indikator'])[0];

                    DB::table('N_EMI_LAB_Role_Konten_Access')->updateOrInsert(
                        [
                            'Id_Page_Access' => $decoded_page_id,
                            'Id_Jenis_Analisa' => $decoded_analisa_id, // Pastikan ini nama kolom yang benar di tabelmu
                        ],
                        [
                            'Flag_Diizinkan' => 'Y',
                        ]
                    );
                }
            }

            DB::commit();

            return response()->json(['success' => true, 'status' => 201, 'message' => "Data Hak Akses Berhasil Disimpan"], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("TERJADI KESALAHAN INSERT: " . $e->getMessage());
            return response()->json(['success' => false, 'status' => 500, 'message' => "Terjadi Kesalahan Pada Server: " . $e->getMessage()], 500);
        }
    }

    public function toggleAccess(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'Id_Page_Access' => 'required|string',
            'Id_Aksi' => 'required|string',
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Data yang dikirim tidak valid.',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $decoded_page_id = Hashids::connection('custom')->decode($request->Id_Page_Access)[0];
            $decoded_aksi_id = Hashids::connection('custom')->decode($request->Id_Aksi)[0];

            $flag = $request->is_active ? 'Y' : 'T';

            DB::table('N_EMI_LAB_Role_Menu_Access')->updateOrInsert(
                [
                    'Id_Page_Access' => $decoded_page_id,
                    'Id_Aksi' => $decoded_aksi_id
                ],
                [
                    'Flag_Diizinkan' => $flag,
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Hak akses berhasil diperbarui."
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("TERJADI KESALAHAN TOGGLE ACCESS: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan Pada Server."
            ], 500);
        }
    }

    public function toggleContentAccess(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'Id_Page_Access' => 'required|string',
            'Id_Jenis_Analisa' => 'required|string',
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Data tidak valid.',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $decoded_page_id = Hashids::connection('custom')->decode($request->Id_Page_Access)[0];
            $decoded_analisa_id = Hashids::connection('custom')->decode($request->Id_Jenis_Analisa)[0];

            $flag = $request->is_active ? 'Y' : 'T';

            DB::table('N_EMI_LAB_Role_Konten_Access')->updateOrInsert(
                [
                    'Id_Page_Access' => $decoded_page_id,
                    'Id_Jenis_Analisa' => $decoded_analisa_id,
                ],
                [
                    'Flag_Diizinkan' => $flag,
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Akses konten berhasil diperbarui.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('TOGGLE KONTEN ERROR: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Terjadi kesalahan server.'
            ], 500);
        }
    }
}