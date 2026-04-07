<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

class MenuController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return inertia("vue/dashboard/menu/HomeMenu");
    }

    public function getDataMenu(Request $request)
    {
        try {
            $limit = (int) $request->query('limit', 10);
            $page = (int) $request->query('page', 1);
            $offset = ($page - 1) * $limit;

            $total = DB::table('N_EMI_LAB_Menus')->count();

            $getData = DB::table('N_EMI_LAB_Menus')
                ->offset($offset)
                ->limit($limit)
                ->get()
                ->map(function ($item) {
                    $item->Id_Menu = Hashids::connection('custom')->encode($item->Id_Menu);
                    return $item;
                });

            if ($getData->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Data Tidak Ditemukan'
                ], 404);
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
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => [
                    'error' => $e->getMessage()
                ]
            ], 500);
        }
    }

    public function getDataMenuJson()
    {
        try {
            
            $getData = DB::table('N_EMI_LAB_Menus')
                ->get()
                ->map(function ($item) {
                    $item->Id_Menu = Hashids::connection('custom')->encode($item->Id_Menu);
                    return $item;
                });

            if ($getData->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Data Tidak Ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'status' => true,
                'result' => $getData,
            ]);
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

    public function searchDataMenu(Request $request)
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

            $getDataMenu = DB::table('N_EMI_LAB_Menus')
            ->whereRaw("LOWER(Nama_Menu) LIKE ?", ["%" . strtolower($keyword) . "%"])
            ->get()
            ->map(function ($item) {
                $item->Id_Menu = Hashids::connection('custom')->encode($item->Id_Menu);
                return $item;
            });

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
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => [
                    'error' => $e->getMessage()
                ]
            ], 500);
        }
    }
    

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'Nama_Menu' => 'required',
            'Icon_Menu' => 'required',
            'Url_Menu' => 'required'
        ]);

        DB::beginTransaction();

        try {
            $payload = [
                'Kode_Perusahaan' => '001',
                'Nama_Menu' => $request->Nama_Menu,
                'Icon_Menu' => $request->Icon_Menu,
                'Url_Menu' => $request->Url_Menu,
            ];

            DB::table('N_EMI_LAB_Menus')->insert($payload);
            DB::commit();
            return response()->json([
                'success' => true, 
                'status' => 201,
                'message' => 'Data Berhasil Disimpan'
            ], 201);

        }catch(\Exception $e){
            DB::rollBack();
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => [
                    'error' => $e->getMessage()
                ]
            ], 500);
        }
    }
    public function update(Request $request, $Id_Menu)
    {
        try {
            $Id_Menu = Hashids::connection('custom')->decode($Id_Menu)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format Kunci Menu tidak valid.'
            ], 400);
        }

        $getData = DB::table('N_EMI_LAB_Menus')->where('Id_Menu', $Id_Menu)->first();

        if(empty($getData)){
            return response()->json([
                'status' => false,
                'status' => 404,
                'message' => 'Data Tidak Ditemukan !'
            ], 404);
        }

        $request->validate([
            'Nama_Menu' => 'required',
            'Icon_Menu' => 'required',
            'Url_Menu' => 'required'
        ]);

        DB::beginTransaction();

        try {
            $payload = [
                'Nama_Menu' => $request->Nama_Menu,
                'Icon_Menu' => $request->Icon_Menu,
                'Url_Menu' => $request->Url_Menu,
            ];

            DB::table('N_EMI_LAB_Menus')->where('Id_Menu', $Id_Menu)->update($payload);
            DB::commit();
            return response()->json([
                'success' => true, 
                'status' => 200,
                'message' => 'Data Berhasil Diupdate !'
            ], 200);

        }catch(\Exception $e){
            DB::rollBack();
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => [
                    'error' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Menu $menu)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Menu $menu)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Menu $menu)
    {
        //
    }
}
