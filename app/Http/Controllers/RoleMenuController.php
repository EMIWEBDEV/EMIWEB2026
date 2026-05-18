<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

class RoleMenuController extends Controller
{
    public function index($UserId)
    {
        return inertia("vue/dashboard/role-menu/HomeRoleMenu", [
            'UserId' => $UserId
        ]);
    }
    public function viewAksesPage()
    {
        return inertia("vue/dashboard/role-menu/RoleMenu");
    }
    
    public function getDataRoleMenu()
    {
        try {
            $totalMenuAvailable = DB::table('N_EMI_LAB_Menus')
                ->where('Kode_Perusahaan', '001')
                ->count();

            $getData = DB::table('N_EMI_LAB_Page_Access_2')
                ->select('Id_User', DB::raw('COUNT(*) as total_data'))
                ->where('Kode_Perusahaan', '001')
                ->groupBy('Id_User')
                ->get();

            if ($getData->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'status'  => 404,
                    'message' => 'Data Tidak Ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'status'  => 200,
                'result'  => $getData,
                'meta'    => [
                    'total_users'          => $getData->count(),
                    'total_menu_available' => $totalMenuAvailable,
                    'total_assignments'    => $getData->sum('total_data'),
                ],
            ]);
        } catch (\Exception $e) {
            Log::channel('RoleMenuController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'status'  => 500,
                'message' => 'Terjadi Kesalahan',
            ], 500);
        }
    }

    public function getDataMenu(Request $request, $UserId)
    {
        try {
            $limit = (int) $request->query('limit', 10);
            $page = (int) $request->query('page', 1);
            $offset = ($page - 1) * $limit;
            
            $total = DB::table('N_EMI_LAB_Role_Menu')->count();

            $getData = DB::table('N_EMI_LAB_Role_Menu')
                ->select('N_EMI_LAB_Role_Menu.*','N_EMI_LAB_Menus.Nama_Menu', 'N_EMI_LAB_Menus.Url_Menu', 'N_EMI_LAB_Menus.Icon_Menu', 'N_EMI_LAB_Sub_Menus.Nama_Sub_Menu', 'N_EMI_LAB_Role_Menu.Id_User')
                ->join('N_EMI_LAB_Menus', 'N_EMI_LAB_Role_Menu.Id_Menu', '=', 'N_EMI_LAB_Menus.Id_Menu')
                ->leftJoin('N_EMI_LAB_Sub_Menus', 'N_EMI_LAB_Role_Menu.Id_Sub_Menu', '=', 'N_EMI_LAB_Sub_Menus.Id_Sub_Menu')
                ->where('N_EMI_LAB_Role_Menu.Id_User', $UserId)
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
            foreach ($getData as &$item) {
                $item->Id_Role_Menu = Hashids::connection('custom')->encode($item->Id_Role_Menu);
                $item->Id_Menu = Hashids::connection('custom')->encode($item->Id_Menu);
            }

            return response()->json([
                'success' => true,
                'status' => true,
                'data' => $getData,
                'page' => $page,
                'total_page' => ceil($total / $limit),
                'total_data' => $total
            ]);
        } catch (\Exception $e) {
            Log::channel('RoleMenuController')->error('Error: ' . $e->getMessage());
            return response()->json([
                    'success' => true,
                    'status' => 500,
                    'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }

    public function searchDataMenu(Request $request, $UserId)
    {
        try {
            $keyword = $request->input('q');

            if (empty($keyword)) {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => [
                        'error' => "Kata kunci pencarian tidak boleh kosong."
                    ]
                ], 400);
            }

            $getDataMenu = DB::table('N_EMI_LAB_Role_Menu')
            ->join('N_EMI_LAB_Menus', 'N_EMI_LAB_Role_Menu.Id_Menu', '=', 'N_EMI_LAB_Menus.Id_Menu')
            ->leftJoin('N_EMI_LAB_Sub_Menus', 'N_EMI_LAB_Role_Menu.Id_Sub_Menu', '=', 'N_EMI_LAB_Sub_Menus.Id_Sub_Menu')
            ->where('N_EMI_LAB_Role_Menu.Id_User', $UserId)
            ->whereRaw("LOWER(N_EMI_LAB_Menus.Nama_Menu) LIKE ?", ["%" . strtolower($keyword) . "%"])
            ->get();

            if ($getDataMenu->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' =>  "Data tidak ditemukan."
                ], 404);
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Data ditemukan.",
                'result' => $getDataMenu
            ], 200);

        } catch (\Exception $e) {
            Log::channel('RoleMenuController')->error('Error: ' . $e->getMessage());
            return response()->json([
                    'success' => true,
                    'status' => 500,
                    'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'data' => 'required|array',
            'data.*.Id_Menu' => 'required',
            'data.*.Id_User' => 'required',
            'data.*.Urutan_Menu' => 'required'
        ]);

        DB::beginTransaction();

        try {
            foreach ($request->data as $row) {
                try {
                    $Id_Menu = Hashids::connection('custom')->decode($row['Id_Menu'])[0];
                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'status' => 400,
                        'message' => 'Format Kunci Menu tidak valid.'
                    ], 400);
                }

                // Kriteria pencarian data (Kombinasi Unik)
                $conditions = [
                    'Kode_Perusahaan' => '001',
                    'Id_Menu'         => $Id_Menu,
                    'Id_User'         => $row['Id_User'],
                ];

                // Data yang akan disisipkan jika baru, atau diperbarui jika sudah ada
                $updates = [
                    'Urutan_Menu'     => $row['Urutan_Menu'],
                ];

                // Gunakan updateOrInsert sebagai pengganti insert
                DB::table('N_EMI_LAB_Page_Access_2')->updateOrInsert($conditions, $updates);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Data Akses Menu Berhasil Disimpan/Diperbarui'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('RoleMenuController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, // Diubah menjadi false karena ini blok error
                'status' => 500,
                'message' => "Terjadi Kesalahan Internal Server",
            ], 500); 
        }
    }
    
    public function getAllMenuByUser($UserId)
    {
        try {
            $getData = DB::table('N_EMI_LAB_Page_Access_2')
                ->select(
                    'N_EMI_LAB_Page_Access_2.Id_Page_Access',
                    'N_EMI_LAB_Page_Access_2.Id_Menu',
                    'N_EMI_LAB_Page_Access_2.Id_User',
                    'N_EMI_LAB_Page_Access_2.Urutan_Menu',
                    'N_EMI_LAB_Menus.Nama_Menu',
                    'N_EMI_LAB_Menus.Url_Menu',
                    'N_EMI_LAB_Menus.Icon_Menu',
                    'N_EMI_LAB_Menus.Nama_Header',
                    'N_EMI_LAB_Menus.Sub_Header',
                    'N_EMI_LAB_Menus.Sub_Sub_Header'
                )
                ->join('N_EMI_LAB_Menus', 'N_EMI_LAB_Page_Access_2.Id_Menu', '=', 'N_EMI_LAB_Menus.Id_Menu')
                ->where('N_EMI_LAB_Page_Access_2.Id_User', $UserId)
                ->orderBy('N_EMI_LAB_Page_Access_2.Urutan_Menu')
                ->get();

            if ($getData->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'status'  => 404,
                    'message' => 'Data Tidak Ditemukan'
                ], 404);
            }

            foreach ($getData as &$item) {
                $item->Id_Page_Access = Hashids::connection('custom')->encode($item->Id_Page_Access);
                $item->Id_Menu        = Hashids::connection('custom')->encode($item->Id_Menu);
            }

            return response()->json([
                'success' => true,
                'status'  => 200,
                'data'    => $getData,
            ]);
        } catch (\Exception $e) {
            Log::channel('RoleMenuController')->error('Error getAllMenuByUser: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Terjadi Kesalahan',
            ], 500);
        }
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'items'                    => 'required|array',
            'items.*.Id_Page_Access'   => 'required|string',
            'items.*.Urutan_Menu'      => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->items as $row) {
                $decoded = Hashids::connection('custom')->decode($row['Id_Page_Access']);
                if (empty($decoded)) {
                    throw new \Exception('Format Kunci tidak valid: ' . $row['Id_Page_Access']);
                }
                DB::table('N_EMI_LAB_Page_Access_2')
                    ->where('Id_Page_Access', $decoded[0])
                    ->update(['Urutan_Menu' => $row['Urutan_Menu']]);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Urutan Menu Berhasil Diperbarui',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('RoleMenuController')->error('Error reorder: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Terjadi Kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updatePageAccess(Request $request, $IdPageAccess)
    {
        try {
            $decoded = Hashids::connection('custom')->decode($IdPageAccess);
            if (empty($decoded)) throw new \Exception();
            $id = $decoded[0];
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'status' => 400, 'message' => 'Format ID tidak valid'], 400);
        }

        $getData = DB::table('N_EMI_LAB_Page_Access_2')->where('Id_Page_Access', $id)->first();
        if (empty($getData)) {
            return response()->json(['success' => false, 'status' => 404, 'message' => 'Data Tidak Ditemukan'], 404);
        }

        $request->validate(['Id_Menu' => 'required']);

        $menuDecoded = Hashids::connection('custom')->decode($request->Id_Menu);
        $Id_Menu = !empty($menuDecoded) ? $menuDecoded[0] : (int) $request->Id_Menu;

        DB::beginTransaction();
        try {
            DB::table('N_EMI_LAB_Page_Access_2')
                ->where('Id_Page_Access', $id)
                ->update(['Id_Menu' => $Id_Menu]);

            DB::commit();
            return response()->json(['success' => true, 'status' => 200, 'message' => 'Data Berhasil Diupdate']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('RoleMenuController')->error('Error updatePageAccess: ' . $e->getMessage());
            return response()->json(['success' => false, 'status' => 500, 'message' => 'Terjadi Kesalahan'], 500);
        }
    }

    public function getAvailableMenus($UserId)
    {
        try {
            $assignedMenuIds = DB::table('N_EMI_LAB_Page_Access_2')
                ->where('Id_User', $UserId)
                ->where('Kode_Perusahaan', '001')
                ->pluck('Id_Menu');

            $menus = DB::table('N_EMI_LAB_Menus')
                ->where('Kode_Perusahaan', '001')
                ->whereNotIn('Id_Menu', $assignedMenuIds)
                ->get();

            foreach ($menus as &$item) {
                $item->Id_Menu = Hashids::connection('custom')->encode($item->Id_Menu);
            }

            return response()->json([
                'success' => true,
                'status'  => 200,
                'data'    => $menus,
            ]);
        } catch (\Exception $e) {
            Log::channel('RoleMenuController')->error('Error getAvailableMenus: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Terjadi Kesalahan',
            ], 500);
        }
    }

    public function batchSavePageAccess(Request $request, $UserId)
    {
        $request->validate([
            'items'                  => 'required|array',
            'items.*.Id_Menu'        => 'required|string',
            'items.*.Urutan_Menu'    => 'required|integer|min:1',
            'items.*.Nama_Header'    => 'nullable|string|max:100',
            'items.*.Sub_Header'     => 'nullable|string|max:100',
            'items.*.Sub_Sub_Header' => 'nullable|string|max:100',
        ]);

        DB::beginTransaction();
        try {
            // Decode semua ID menu baru terlebih dahulu
            $newMenuIds = [];
            foreach ($request->items as $item) {
                $decoded = Hashids::connection('custom')->decode($item['Id_Menu']);
                if (empty($decoded)) {
                    throw new \Exception('Format ID Menu tidak valid: ' . $item['Id_Menu']);
                }
                $newMenuIds[] = $decoded[0];
            }

            // Hapus HANYA menu yang tidak ada di list baru agar Id_Page_Access lama tetap terjaga
            // sehingga referensi di N_EMI_LAB_Role_Menu_Access tidak rusak
            DB::table('N_EMI_LAB_Page_Access_2')
                ->where('Id_User', $UserId)
                ->where('Kode_Perusahaan', '001')
                ->whereNotIn('Id_Menu', $newMenuIds)
                ->delete();

            foreach ($request->items as $index => $item) {
                $Id_Menu = $newMenuIds[$index];

                // updateOrInsert: update record lama (Id_Page_Access tidak berubah) atau insert baru jika belum ada
                DB::table('N_EMI_LAB_Page_Access_2')->updateOrInsert(
                    [
                        'Kode_Perusahaan' => '001',
                        'Id_Menu'         => $Id_Menu,
                        'Id_User'         => $UserId,
                    ],
                    [
                        'Urutan_Menu' => $item['Urutan_Menu'],
                    ]
                );

                // Simpan grouping kembali ke N_EMI_LAB_Menus
                DB::table('N_EMI_LAB_Menus')
                    ->where('Id_Menu', $Id_Menu)
                    ->update([
                        'Nama_Header'     => $item['Nama_Header'] ?: null,
                        'Sub_Header'      => $item['Sub_Header'] ?: null,
                        'Sub_Sub_Header'  => $item['Sub_Sub_Header'] ?: null,
                    ]);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Menu berhasil disimpan',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('RoleMenuController')->error('Error batchSavePageAccess: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Terjadi Kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id_role_menu)
    {
        try {
            $id_role_menu = Hashids::connection('custom')->decode($id_role_menu)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Format Kunci Menu tidak valid.'
            ], 400);
        }

        $getData = DB::table('N_EMI_LAB_Role_Menu')->where('Id_Role_Menu', $id_role_menu)->first();

        if(empty($getData)){
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Data Tidak Ditemukan !'
            ], 404);
        }

        $request->validate([
            'Id_Menu' => 'required',
            'Id_User' => 'required'
        ]);

        try {
            $Id_Menu = Hashids::connection('custom')->decode($request->Id_Menu)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Format Kunci Menu tidak valid.'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $payload = [
                'Kode_Perusahaan' => '001',
                'Id_Menu' => $Id_Menu,
                'Id_User' => $request->Id_User,
                'Id_Sub_Menu' => $request->Id_Sub_Menu,
            ];

            DB::table('N_EMI_LAB_Role_Menu')->where('Id_Role_Menu', $id_role_menu)->update($payload);

            DB::commit();
            return response()->json([
                'success' => true, 
                'status' => 201,
                'message' => 'Data Berhasil Diupdate'
            ], 201);

        }catch(\Exception $e){
            DB::rollBack();
            Log::channel('RoleMenuController')->error('Error: ' . $e->getMessage());
            return response()->json([
                    'success' => true,
                    'status' => 500,
                    'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }
}
