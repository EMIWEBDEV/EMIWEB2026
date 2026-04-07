<?php

namespace App\Http\Controllers;

use App\Models\BindingMacMesin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

class BindingMacMesinController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = "
              SELECT
                    binding.Id_Master_Mac,
                    MAX(master.Mac_Address) AS Mac_Address,
                    MAX(master.Keterangan) AS Keterangan,
                    COUNT(binding.Id_Master_Mac) AS total_mac
                FROM
                    EMI_LAB_Binding_Mac_Mesin binding
                JOIN EMI_LAB_Master_Mac master ON binding.Id_Master_Mac = master.id
                GROUP BY
                    binding.Id_Master_Mac;
            ";
        $groupedData = DB::select($query);
        
        return view('binding-mac-mesin.index', [
            'groupedData' => $groupedData
        ]);
    }

    public function bindingDetail($id)
    {
        $query = "
            SELECT
                mesin.Nama_Mesin
            FROM
                EMI_LAB_Binding_Mac_Mesin AS binding
            JOIN EMI_LAB_Master_Mac AS master ON binding.Id_Master_Mac = master.id
            JOIN EMI_LAB_Mesin AS mesin ON binding.Id_Mesin = mesin.id
            WHERE
                binding.Id_Master_Mac = ?
            ORDER BY
                binding.Id_Master_Mac;
        ";

        $getData = DB::select($query, [$id]);
        return view('binding-mac-mesin.detail', [
            'data' => $getData
        ]);
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $getDataMacAddress = DB::table('EMI_LAB_Master_Mac')->get();
        $getDataNamaMesin = DB::table('EMI_LAB_Mesin')->get();
        return view("binding-mac-mesin.create", [
            'macAddress' => $getDataMacAddress,
            'mesin' => $getDataNamaMesin
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'Id_Master_Mac' => 'required',
            'Id_Mesin' => 'required'
        ]);

        DB::beginTransaction();

        try {
            $data = [
                'Id_Master_Mac' => $request->Id_Master_Mac,
                'Id_Mesin' => $request->Id_Mesin
            ];
        

            BindingMacMesin::create($data);
            DB::commit();
            return redirect()->route('bindingmac.index')->with('success', "Data Berhasil Disimpan");
        }catch(\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(BindingMacMesin $bindingMacMesin)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(BindingMacMesin $bindingMacMesin)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BindingMacMesin $bindingMacMesin)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BindingMacMesin $bindingMacMesin)
    {
        //
    }
}
