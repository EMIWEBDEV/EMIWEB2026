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
           
            $getData = DB::table('N_EMI_LAB_Role_Menu')
                ->select(
                    'N_EMI_LAB_Role_Menu.Id_User',
                    DB::raw('COUNT(N_EMI_LAB_Role_Menu.Id_User) as total_data')  
                )
                ->join('N_EMI_LAB_Menus', 'N_EMI_LAB_Role_Menu.Id_Menu', '=', 'N_EMI_LAB_Menus.Id_Menu')
                ->leftJoin('N_EMI_LAB_Sub_Menus', 'N_EMI_LAB_Role_Menu.Id_Sub_Menu', '=', 'N_EMI_LAB_Sub_Menus.Id_Sub_Menu')
                ->groupBy('N_EMI_LAB_Role_Menu.Id_User') 
                ->get();

            if ($getData->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Data Tidak Ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'result' => $getData,
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

                $payload = [
                    'Kode_Perusahaan' => '001',
                    'Id_Menu' => $Id_Menu,
                    'Id_User' => $row['Id_User'],
                    'Id_Sub_Menu' => $row['Id_Sub_Menu'],
                ];

                DB::table('N_EMI_LAB_Role_Menu')->insert($payload);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Data Berhasil Disimpan'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('RoleMenuController')->error('Error: ' . $e->getMessage());
            return response()->json([
                    'success' => true,
                    'status' => 500,
                    'message' => "Terjadi Kesalahan",
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
