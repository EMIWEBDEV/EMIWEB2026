<?php

namespace App\Http\Controllers\FormulatorTrialSampel;

use Illuminate\Support\Facades\Validator; 
use Illuminate\Support\Facades\Session;
use MathParser\Interpreting\Evaluator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;
use App\Exports\ParticleSizeExport;
use Illuminate\Support\Facades\DB;
use App\Exports\RekapSampelExport;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Helpers\ResponseHelper;
use MathParser\StdMathParser;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class FormulatorTrialSampelController extends Controller
{
    protected function calculateFormulaServerSide($formula, $parameterValues, $decimalPlaces = 2)
    {
        try {
            $processedFormula = $formula;
            $parameterValues = collect($parameterValues);
            $functionPattern = '/(AVG|SUM)\(([^)]+?)\)/';

            while (preg_match($functionPattern, $processedFormula, $matches)) {
                $fullMatch = $matches[0];
                $functionName = $matches[1];
                $argsString = $matches[2];

                preg_match_all('/\[([^\]]+)\]/', $argsString, $paramMatches);
                $paramIds = $paramMatches[1] ?? [];

                $values = collect($paramIds)->map(function ($id) use ($parameterValues) {
                    $value = $parameterValues->get($id);
                    return is_numeric($value) ? (float)$value : null;
                })->filter()->all();

                $result = 0;
                if (!empty($values)) {
                    switch (strtoupper($functionName)) {
                        case 'SUM':
                            $result = array_sum($values);
                            break;
                        case 'AVG':
                            $result = array_sum($values) / count($values);
                            break;
                    }
                }
                $processedFormula = str_replace($fullMatch, (string)$result, $processedFormula);
            }

            preg_match_all('/\[([^\]]+)\]/', $processedFormula, $paramMatches);
            foreach ($paramMatches[1] ?? [] as $id) {
                $value = $parameterValues->get($id, 0);
                $processedFormula = str_replace("[$id]", (string)$value, $processedFormula);
            }

            $parser = new StdMathParser();
            $evaluator = new Evaluator();

            $AST = $parser->parse($processedFormula);
            $finalResult = $AST->accept($evaluator);

            return number_format((float)$finalResult, $decimalPlaces, '.', '');

        } catch (\Throwable $e) {
            Log::channel('UjiSampelController')->error('Error: ' . $e->getMessage());
            return number_format(0, $decimalPlaces, '.', '');
        }
    }
    protected function calculateFormulaServerSideV2($formula, $parameterValues, $decimalPlaces = 2)
    {
        try {
            if (empty($formula)) {
                return number_format(0, $decimalPlaces, '.', '');
            }

            $parameterValues = collect($parameterValues);

            $functionPattern = '/(AVG|SUM)\(([^)]+?)\)/';
            
            $processedFormula = preg_replace_callback($functionPattern, function ($matches) use ($parameterValues) {
                $functionName = $matches[1];
                $argsString = $matches[2]; 

                preg_match_all('/\[([^\]]+)\]/', $argsString, $paramMatches);
                $paramIds = $paramMatches[1] ?? [];

                $values = collect($paramIds)->map(function ($id) use ($parameterValues) {
                    $value = $parameterValues->get($id);
                    return is_numeric($value) ? (float)$value : null;
                })->filter()->all(); 

                $result = 0;
                if (!empty($values)) {
                    switch (strtoupper($functionName)) {
                        case 'SUM':
                            $result = array_sum($values);
                            break;
                        case 'AVG':
                            $result = array_sum($values) / count($values);
                            break;
                    }
                }
                return (string)$result;
            }, $formula); 

            preg_match_all('/\[([^\]]+)\]/', $processedFormula, $paramMatches);
            $searchKeys = [];
            $replaceValues = [];

            foreach (array_unique($paramMatches[1] ?? []) as $id) {
                $searchKeys[] = "[$id]"; 
                $paramValue = $parameterValues->get($id);
                $replaceValues[] = (string) (is_numeric($paramValue) ? $paramValue : 0); 
            }
            if (!empty($searchKeys)) {
                $processedFormula = str_replace($searchKeys, $replaceValues, $processedFormula);
            }

            $safeFormula = preg_replace('/[^0-9\+\-\*\/\.\(\)eE]/', '', $processedFormula);
            if (empty(trim($safeFormula))) {
                return number_format(0, $decimalPlaces, '.', '');
            }

            $parser = new StdMathParser();
            $evaluator = new Evaluator();
            $AST = $parser->parse($safeFormula);
            $finalResult = $AST->accept($evaluator);

            return number_format((float)$finalResult, $decimalPlaces, '.', '');

        } catch (\Throwable $e) {
            Log::channel('UjiSampelController')->error('Error: ' . $e->getMessage());
            return number_format(0, $decimalPlaces, '.', '');
        }
    }
    private function safeFloat($value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
    public function index()
    {
        return inertia('vue/lab/formulator/HomeLab')->withViewData([
             'layout' => 'layouts.master2',
         ]) ;
    }
    public function indexTesting()
    {
        return inertia('vue/lab/formulator/HomeLab2')->withViewData([
             'layout' => 'layouts.master2',
         ]) ;
    }
    public function viewConfirmedAnalisis()
    {
        return inertia('vue/dashboard/lab/formulator/ValidasiHasilTrial');
    }
    public function viewInformasiMultiQr($no_sub_sampel, $id_jenis_analisa)
    {
        return inertia('vue/dashboard/lab/formulator/page-confirmedv2/confirmedv2pcs', [
            'No_Sub_Sampel' => $no_sub_sampel,
            'id_jenis_analisa' => $id_jenis_analisa
        ]);
    }
    public function viewInformasiJenisAnalisaMultiQr($no_sampel, $no_fak_sub_sampel, $id_jenis_analisa)
    {
        return inertia('vue/dashboard/lab/formulator/page-confirmedv2/confirmedv2pcs-jenis-analisa', [
            'No_Sampel' => $no_sampel,
            'No_Fak_Sub_Sampel' => $no_fak_sub_sampel,
            'id_jenis_analisa' => $id_jenis_analisa,
        ]);
    }
    public function viewInformasiJenisAnalisaSingleQr($no_sampel)
    {
        return inertia('vue/dashboard/lab/formulator/page-confirmedv2/confirmedv2no-pcs-jenis-analisa', [
            'No_Sampel' => $no_sampel,
        ]);
    }
    public function viewDataHasilAnalisaValidasi($no_sampel, $no_fak_sub_sampel, $id_jenis_analisa)
    {
        try {
            $decodedArray = Hashids::connection('custom')->decode($id_jenis_analisa);
            $Id_Jenis_Analisa = $decodedArray[0] ?? null;
        } catch (\Exception $e) {
            abort(404, 'ID Jenis Analisa tidak valid');
        }
        $getInformasiAnalisa = DB::table('N_EMI_LAB_Jenis_Analisa')
            ->where('id', $Id_Jenis_Analisa)
            ->where('Kode_Role', 'FLM')
            ->select('Flag_Perhitungan', 'Jenis_Analisa') 
            ->first();

        if (!$getInformasiAnalisa) {
            abort(404, 'Data Jenis Analisa tidak ditemukan');
        }

        $getInformasi = DB::table('N_LIMS_PO_Sampel')
            ->where('No_Sampel', $no_sampel)
            ->select('Id_Mesin', 'Kode_Barang')
            ->first();

        $Id_Master_Mesin = $getInformasi->Id_Mesin;
        $Kode_Barang     = $getInformasi->Kode_Barang;
        $Flag_Perhitungan = $getInformasiAnalisa->Flag_Perhitungan;
        $hasStandardConfiguration = false;

        if ($Flag_Perhitungan === 'Y') {
            $hasStandardConfiguration = DB::table('N_EMI_LAB_Standar_Rentang')
                ->where('Kode_Role', 'FLM')
                ->where('Id_Jenis_Analisa', $Id_Jenis_Analisa)
                ->where('Kode_Barang', $Kode_Barang)
                ->where('Id_Master_Mesin', $Id_Master_Mesin)
                ->exists(); 

        } else {
            $hasStandardConfiguration = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->where('Kode_Role', 'FLM')
                ->where('Id_Jenis_Analisa', $Id_Jenis_Analisa)
                ->exists();
        }

        return inertia('vue/dashboard/lab/formulator/page-confirmedv2/verfikasiv2pcs', [
            'No_Sampel' => $no_sampel,
            'No_Fak_Sub_Sampel' => $no_fak_sub_sampel,
            'Id_Jenis_Analisa' => $id_jenis_analisa,
            'Has_Standard_Configuration' => $hasStandardConfiguration,
        ]);
    }
    public function viewDataHasilAnalisaValidasiSingleQrCode($no_sampel, $id_jenis_analisa)
    {
        try {
            $decodedArray = Hashids::connection('custom')->decode($id_jenis_analisa);
            $Id_Jenis_Analisa = $decodedArray[0] ?? null;
        } catch (\Exception $e) {
            abort(404, 'ID Jenis Analisa tidak valid');
        }
        $getInformasiAnalisa = DB::table('N_EMI_LAB_Jenis_Analisa')
            ->where('Kode_Role', 'FLM')
            ->where('id', $Id_Jenis_Analisa)
            ->select('Flag_Perhitungan', 'Jenis_Analisa') 
            ->first();

        if (!$getInformasiAnalisa) {
            abort(404, 'Data Jenis Analisa tidak ditemukan');
        }

        $getInformasi = DB::table('N_LIMS_PO_Sampel')
            ->where('No_Sampel', $no_sampel)
            ->select('Id_Mesin', 'Kode_Barang')
            ->first();

        $Id_Master_Mesin = $getInformasi->Id_Mesin;
        $Kode_Barang     = $getInformasi->Kode_Barang;
        $Flag_Perhitungan = $getInformasiAnalisa->Flag_Perhitungan;
        $hasStandardConfiguration = false;

        if ($Flag_Perhitungan === 'Y') {
            $hasStandardConfiguration = DB::table('N_EMI_LAB_Standar_Rentang')
                ->where('Kode_Role', 'FLM')
                ->where('Id_Jenis_Analisa', $Id_Jenis_Analisa)
                ->where('Kode_Barang', $Kode_Barang)
                ->where('Id_Master_Mesin', $Id_Master_Mesin)
                ->exists(); 

        } else {
            $hasStandardConfiguration = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->where('Id_Jenis_Analisa', $Id_Jenis_Analisa)
                ->where('Kode_Role', 'FLM')
                ->exists();
        }

        return inertia('vue/dashboard/lab/formulator/page-confirmedv2/verfikasiv2nopcs', [
            'No_Sampel' => $no_sampel,
            'Id_Jenis_Analisa' => $id_jenis_analisa,
            'Has_Standard_Configuration' => $hasStandardConfiguration,
        ]);
    }
    public function viewHasilAnalisa()
    {
        return inertia('vue/dashboard/lab/formulator/hasil-analisis/HasilAnalisa');
    }
    public function viewSubHasilAnalisa($id_jenis_analisa)
    {
        return inertia('vue/dashboard/lab/formulator/hasil-analisis/SubHasilAnalisa', [
            'id_jenis_analisa' => $id_jenis_analisa
        ]);
    }
    public function viewNestedSubHasilAnalisa($id_jenis_analisa, $no_po_sampel, $flag_multi)
    {
        if($flag_multi === 'Y'){
            return inertia('vue/dashboard/lab/formulator/hasil-analisis/NestedSubHasilAnalisa', [
                'id_jenis_analisa' => $id_jenis_analisa,
                'no_po_sampel' => $no_po_sampel,
                'flag_multi' => $flag_multi,
            ]);
        }else {
            return inertia('vue/dashboard/lab/formulator/hasil-analisis/DetailHasilAnalisa', [
                'id_jenis_analisa' => $id_jenis_analisa,
                'no_po_sampel' => $no_po_sampel,
                'flag_multi' => $flag_multi,
            ]);
        }
    }
    public function viewDetaiHasilMulti($id_jenis_analisa, $no_po_sampel, $flag_multi, $no_sub)
    {
        if($flag_multi === 'Y'){
            return inertia('vue/dashboard/lab/formulator/hasil-analisis/DetailHasilAnalisaMulti', [
                'id_jenis_analisa' => $id_jenis_analisa,
                'no_po_sampel' => $no_po_sampel,
                'flag_multi' => $flag_multi,
                'no_fak_sub' => $no_sub
            ]);
        }
    }
    public function refreshOtk()
    {
        $newToken = Str::uuid();
        Cache::put("form_otk:$newToken", now()->timestamp, now()->addMinutes(30));

        return response()->json([
            'formOtk' => $newToken,
        ]);
    }
    public function storeNotMultiRumus(Request $request)
    {
        // 1. Validasi input array
        $request->validate([
            'analyses' => 'required|array|min:1',
            'analyses.*.No_Po_Sampel' => 'required',
            'analyses.*.Id_Jenis_Analisa' => 'required',
            'analyses.*.parameters' => 'required|array',
            'analyses.*.parameters.*.Id_Quality_Control' => 'required',
            'analyses.*.parameters.*.Value_Parameter' => 'required'
        ], [
            'analyses.required' => 'Tidak ada data analisis yang dikirim.',
            'analyses.*.No_Po_Sampel.required' => 'Nomor PO Sampel Tidak Boleh Kosong di setiap baris.',
            'analyses.*.parameters.required' => 'Parameter tidak boleh kosong di setiap baris.',
        ]);

        DB::beginTransaction();

        try {
            $pengguna = Auth::user();
            $userId = $pengguna->UserId;

            $userExists = DB::table('N_EMI_LAB_Users')->where('UserId', $userId)->exists();

            if (!$userExists) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "User dengan ID $userId tidak ditemukan di sistem."
                ], 404);
            }

            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow; 


            $tanggalSqlServer = date('Y-m-d', strtotime($dt)); 
            $jamSqlServer = date('H:i:s', strtotime($dt));     


            $currentMonth = date('m');
            $currentYear = date('y');
            $prefix = 'FUS' . $currentMonth . $currentYear;
            $prefixLength = strlen($prefix);

            $lastNumber = DB::table('N_EMI_LAB_Uji_Sampel')
                ->where('No_Faktur', 'like', $prefix . '-%')
                ->selectRaw("MAX(CAST(SUBSTRING(No_Faktur, ? + 2, 10) AS INT)) as max_number", [$prefixLength])
                ->value('max_number') ?? 0;

            $savedResults = [];

            // 2. Loop melalui setiap data analisis yang dikirim
            foreach ($request->analyses as $analysisData) {
                $analysis = (object) $analysisData;

                $lastNumber++;
                $newNumber = $prefix . '-' . str_pad($lastNumber, 4, '0', STR_PAD_LEFT);

                foreach ($analysis->parameters as $parameter) {
                    $payloadUjiSample = [
                        "No_Faktur" => $newNumber,
                        "Kode_Perusahaan" => "001",
                        "Id_Jenis_Analisa" => $parameter['Id_Jenis_Analisa'],
                        "Hasil" => $parameter['Value_Parameter'],
                        "Flag_Perhitungan" => null,
                        "Status" => null,
                        'Tanggal' => $tanggalSqlServer,
                        'Jam' => $jamSqlServer,
                        "Id_User" => $pengguna->UserId,
                        "No_Po_Sampel" => $analysis->No_Po_Sampel,
                        'Status_Keputusan_Sampel' => 'menunggu',
                        'Tahapan_Ke' => 1,
                    ];

                    $payloadUjiSampelDetail = [
                        "Kode_Perusahaan" => "001",
                        "No_Faktur_Uji_Sample" => $newNumber,
                        "Id_Quality_Control" => $parameter['Id_Quality_Control'],
                        "Value_Parameter" => $parameter['Value_Parameter'],
                    ];

                    DB::table('N_EMI_LAB_Uji_Sampel')->insert($payloadUjiSample);
                    DB::table('N_EMI_LAB_Uji_Sampel_Detail')->insert($payloadUjiSampelDetail);
                }
                $savedResults[] = [$payloadUjiSample, $payloadUjiSampelDetail];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => "Data Berhasil Disimpan",
                'result' => $savedResults
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('UjiSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan"
            ], 500);
        }
    }
    public function storeNotMultiAndNoQrSementara(Request $request)
    {
        $request->validate([
            'analyses' => 'required|array|min:1',
            'analyses.*.No_Po_Sampel' => 'required',
            'analyses.*.Id_Jenis_Analisa' => 'required',
            'analyses.*.parameters' => 'required|array',
            'analyses.*.parameters.*.Id_Quality_Control' => 'required',
            'analyses.*.parameters.*.Value_Parameter' => 'required'
        ], [
            'analyses.required' => 'Tidak ada data analisis yang dikirim.',
            'analyses.*.No_Po_Sampel.required' => 'Nomor PO Sampel Tidak Boleh Kosong di setiap baris.',
            'analyses.*.parameters.required' => 'Parameter tidak boleh kosong di setiap baris.',
        ]);

        DB::beginTransaction();

        try {
            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow; 
            $tanggalSqlServer = date('Y-m-d', strtotime($dt)); 
            $jamSqlServer = date('H:i:s', strtotime($dt));

            $tanggal = date('Y-m-d');
            $jam = date('H:i:s');
            $prefix = 'TMP-FUS' . date('my');
            $pengguna = Auth::user();
            $userId = $pengguna->UserId;

            $userExists = DB::table('N_EMI_LAB_Users')->where('UserId', $userId)->exists();

            if (!$userExists) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "User dengan ID $userId tidak ditemukan di sistem."
                ], 404);
            }

            $lastNumberRecord = DB::table('N_EMI_LAB_Uji_Sampel_Sementara')
                ->where('No_Sementara', 'like', $prefix . '-%')
                ->orderBy('No_Sementara', 'desc')
                ->first();

            $lastNumber = $lastNumberRecord ? (int) substr($lastNumberRecord->No_Sementara, -4) : 0;

            $results = [];
            $resultParmasDatabasJe = [];

            $noPoSampel = $request->analyses[0]['No_Po_Sampel'];
            $idDecoded = Hashids::connection('custom')->decode($request->analyses[0]['Id_Jenis_Analisa']);
            $jenisAnalisa = isset($idDecoded[0]) ? $idDecoded[0] : null;

            $payloadActivityUjiSampel = [
                    'Kode_Perusahaan' => '001',
                    'No_Po_Sampel' => $noPoSampel,
                    'Jenis_Aktivitas' => 'save_draft',
                    'Keterangan' => $pengguna->Name . ' Menyimpan Data Analisa Sebagai Draft',
                    'Id_User' => $pengguna->UserId,
                    'Tanggal' => $tanggalSqlServer,
                    'Jam' => $jamSqlServer,
                    'Id_Jenis_Analisa' => $jenisAnalisa
                ];

                $idLogActivity = DB::table('N_EMI_LAB_Activity_Uji_Sampel')->insertGetId(
                    $payloadActivityUjiSampel,
                    'Id_Log_Activity'
                );

            foreach ($request->analyses as $analysis) {
                $idDecoded = Hashids::connection('custom')->decode($analysis['Id_Jenis_Analisa']);
                $jenisAnalisa = isset($idDecoded[0]) ? $idDecoded[0] : null;

                $keyConditions = [
                    'No_Po_Sampel' => $analysis['No_Po_Sampel'],
                    'No_Sementara' => $analysis['No_Sementara'],
                    'Id_Jenis_Analisa' => $jenisAnalisa
                ];

                $existing = DB::table('N_EMI_LAB_Uji_Sampel_Sementara')->where($keyConditions)->first();
                $allowInsert = true;
    

                if ($existing) {
                    $existingNoSementara = $existing->No_Sementara;
                    $parameterCount = count($analysis['parameters']);
                    $matched = 0;

                    foreach ($analysis['parameters'] as $param) {
                        $idDecodedQc = Hashids::connection('custom')->decode($param['Id_Quality_Control']);
                        $idDecodedNu = !empty($param['No_Urut']) ? Hashids::connection('custom')->decode($param['No_Urut']) : [];
                        $idNu = isset($idDecodedNu[0]) ? $idDecodedNu[0] : null;
                        $idQc = isset($idDecodedQc[0]) ? $idDecodedQc[0] : null;
                        

                        $existingParamsMap = [];
                        $existingParam = DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')
                            ->where('No_Sementara', $existingNoSementara)
                            ->where('Id_Quality_Control', $idQc)
                            ->where('No_Urut', $idNu)
                            ->first();
                            $key = $idQc . '-' . $idNu;
                            $existingParamsMap[$key] = $existingParam ? $existingParam->Value_Parameter : null;

                           if ($existingParam) {
                                $resultParmasDatabasJe[] = $existingParam->Value_Parameter;
                            } else {
                                $resultParmasDatabasJe[] = null; 
                                Log::warning("Data tidak ditemukan untuk: ", $param);
                            }
                

                        if ($existingParam) {
                            if (is_null($existingParam->Value_Parameter) && !is_null($param['Value_Parameter'])) {
                                $calculatedResults = [];

                                foreach ($analysis['formulas'] as $formula) {
                                    $idDecodedNu = Hashids::connection('custom')->decode($formula['No_Urut']);
                                    $idNu = isset($idDecodedNu[0]) ? $idDecodedNu[0] : null;
                                    $calculatedResults[] = [
                                        'No_Urut' => $idNu, 
                                        'Hasil_Perhitungan' => $formula['Hasil']
                                    ];
                                }

                                foreach ($calculatedResults as $result) {
                                        $hasilFloat = $this->safeFloat($result['Hasil_Perhitungan']);

                                        $getValueHasilLama =  DB::table('N_EMI_LAB_Uji_Sampel_Sementara')
                                                ->where('No_Sementara', $existingNoSementara)
                                                ->where('No_Urut', $result['No_Urut'])
                                                ->first();
                                                
                                        DB::table('N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail')
                                                ->insert([
                                                    "Kode_Perusahaan" => "001",
                                                    'Id_Log_Activity_Sampel' => $idLogActivity,
                                                    "No_Po_Sampel" => $analysis['No_Po_Sampel'],
                                                    "Id_Jenis_Analisa" => $jenisAnalisa,
                                                    "Value_Baru" => $hasilFloat,
                                                    "Value_Lama" => $this->safeFloat($getValueHasilLama->Hasil),
                                                    "Tanggal" => $tanggalSqlServer,
                                                    "Jam" => $jamSqlServer,
                                                    "Id_User" => $pengguna->UserId,
                                                    "Status_Submit" => "Drafted",
                                        ]);

                                        DB::table('N_EMI_LAB_Uji_Sampel_Sementara')
                                                ->where('No_Sementara', $existingNoSementara)
                                                ->where('No_Urut', $result['No_Urut'])
                                                ->update([
                                                    'Hasil' => $hasilFloat,
                                                    'Tanggal' => $tanggalSqlServer,
                                                    'Jam' => $jamSqlServer,
                                                    'Id_User' => $pengguna->UserId,
                                        ]);
                                }

                                $payloadActiviyUjiSampelDetail = [];
                                foreach ($analysis['parameters'] as $param) {
                                    $idDecodedNu = !empty($param['No_Urut']) ? Hashids::connection('custom')->decode($param['No_Urut']) : [];
                                    $idNuTu = isset($idDecodedNu[0]) ? $idDecodedNu[0] : null;
                                    $idDecodedQc = !empty($param['Id_Quality_Control']) ? Hashids::connection('custom')->decode($param['Id_Quality_Control']) : []; 
                                    $idQc = isset($idDecodedQc[0]) ? $idDecodedQc[0] : null;

                                    $getValueHasilLama =  DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')
                                                ->where('No_Sementara', $existingNoSementara)
                                                ->where('No_Urut', $idNuTu)
                                                ->first();

                                    $payloadActiviyUjiSampelDetail[] = [
                                        "Kode_Perusahaan" => "001",
                                        'Id_Log_Activity_Sampel' => $idLogActivity,
                                        "No_Po_Sampel" => $analysis['No_Po_Sampel'],
                                        "Id_Jenis_Analisa" => $jenisAnalisa,
                                        "Id_Quality_Control" => $idQc,
                                        "Value_Baru" => $this->safeFloat($param['Value_Parameter']),
                                        "Value_Lama" => $this->safeFloat($getValueHasilLama->Value_Parameter),
                                        "Tanggal" => $tanggalSqlServer,
                                        "Jam" => $jamSqlServer,
                                        "Id_User" => $pengguna->UserId,
                                        "Status_Submit" => "Drafted",
                                    ];

                                   DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')
                                        ->where('No_Sementara', $existingNoSementara)
                                        ->where('Id_Quality_Control', $idQc)
                                        ->where('No_Urut', $idNuTu)
                                        ->update([
                                            'Value_Parameter' => $this->safeFloat($param['Value_Parameter']),
                                            'Tanggal' => $tanggalSqlServer,
                                            'Jam' => $jamSqlServer,
                                            'Id_User' => $pengguna->UserId,
                                        ]);
                                }
                                DB::table('N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail')->insert($payloadActiviyUjiSampelDetail);

                                $allowInsert = false;
                               
                            } elseif ((float) $existingParam->Value_Parameter === (float) $param['Value_Parameter']) {
                                $matched++;
                            }
                        }
                    }

                    if ($matched === $parameterCount) {
                        $allowInsert = false;
                    }
                }

                if (!$allowInsert) {
                    continue;
                }
 

                $lastNumber++;
                $newNumber = $prefix . '-' . str_pad($lastNumber, 4, '0', STR_PAD_LEFT);

                $calculatedResults = [];

                foreach ($analysis['formulas'] as $formula) {
                                    $idDecodedNu =  !empty($formula['No_Urut']) ? Hashids::connection('custom')->decode($formula['No_Urut']) : [];
                                    $idNu = isset($idDecodedNu[0]) ? $idDecodedNu[0] : null;
                                    $calculatedResults[] = [
                                        'No_Urut' => $idNu, 
                                        'Hasil_Perhitungan' => $formula['Hasil']
                                    ];
                }



                foreach ($calculatedResults as $result) {
                        $hasilFloat = $this->safeFloat($result['Hasil_Perhitungan']);
                                             
                        DB::table('N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail')
                                        ->insert([
                                                    "Kode_Perusahaan" => "001",
                                                    'Id_Log_Activity_Sampel' => $idLogActivity,
                                                    "No_Po_Sampel" => $analysis['No_Po_Sampel'],
                                                    "Id_Jenis_Analisa" => $jenisAnalisa,
                                                    "Value_Baru" => $hasilFloat,
                                                    "Value_Lama" => $hasilFloat,
                                                    "Tanggal" => $tanggalSqlServer,
                                                    "Jam" => $jamSqlServer,
                                                    "Id_User" => $pengguna->UserId,
                                                    "Status_Submit" => "Drafted",
                                        ]);

                        DB::table('N_EMI_LAB_Uji_Sampel_Sementara')
                                                ->where('No_Sementara', $result['No_Sementara'])
                                                ->where('No_Urut', $result['No_Urut'])
                                                ->update([
                                                    'Hasil' => $hasilFloat,
                                                    'Tanggal' => $tanggalSqlServer,
                                                    'Jam' => $jamSqlServer,
                                                    'Id_User' => Auth::user()->UserId,
                        ]);
                }

                $payloadActiviyUjiSampelDetail = [];
                foreach ($analysis['parameters'] as $param) {
                    $idDecodedQc = Hashids::connection('custom')->decode($param['Id_Quality_Control']);
                    $idDecodedNu = !empty($param['No_Urut']) ? Hashids::connection('custom')->decode($param['No_Urut']) : [];
                    $idNu = isset($idDecodedNu[0]) ? $idDecodedNu[0] : null;
                    $idQc = isset($idDecodedQc[0]) ? $idDecodedQc[0] : null;

                    DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')->insert([
                        'Kode_Perusahaan' => '001',
                        'No_Sementara' => $newNumber,
                        'Id_Quality_Control' => $idQc,
                        'Value_Parameter' => $this->safeFloat($param['Value_Parameter']),
                        'Tanggal' => $tanggal,
                        'Jam' => $jam,
                        'Id_User' => $pengguna->UserId,
                    ]);

                    $payloadActiviyUjiSampelDetail[] = [
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $analysis['No_Po_Sampel'],
                        "Id_Jenis_Analisa" => $jenisAnalisa,
                        "Id_Quality_Control" => $idQc,
                        "Value_Baru" => $this->safeFloat($param['Value_Parameter']),
                        "Value_Lama" => $this->safeFloat($param['Value_Parameter']),
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $pengguna->UserId,
                        "Status_Submit" => "Drafted",
                    ];
                }

                DB::table('N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail')->insert($payloadActiviyUjiSampelDetail);

                $results[] = [
                    'No_Sementara' => $newNumber
                ];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Data Berhasil Disimpan',
                'result' => $results
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('UjiSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan"
            ], 500);
        }
    }
    public function storeMultiRumus(Request $request)
    {
      
        $request->validate([
            'analyses' => 'required|array|min:1',
            'analyses.*.No_Po_Sampel' => 'required|string',
            'analyses.*.Id_Jenis_Analisa' => 'required|string',
            'analyses.*.No_Sementara' => 'nullable|string',
            'analyses.*.is_multi_print' => 'required|string',
            'analyses.*.No_Po_Multi_Sampel' => 'nullable|string',
            'analyses.*.parameters' => 'required|array|min:1',
            'analyses.*.parameters.*.Id_Quality_Control' => 'required',
            'analyses.*.parameters.*.Value_Parameter' => 'required|numeric',
        ], [
            'analyses.required' => 'Tidak ada data analisis yang dikirim.',
            'analyses.*.parameters.required' => 'Parameter tidak boleh kosong untuk setiap baris.',
        ]);
      
        DB::beginTransaction();
        

        try {
            $results = [];
          
            $pengguna = Auth::user();
            
            $userId = $pengguna->UserId;

            $userExists = DB::table('N_EMI_LAB_Users')->where('UserId', $userId)->exists();

            if (!$userExists) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "User dengan ID $userId tidak ditemukan di sistem."
                ], 404);
            }

            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow; 
            $tanggalSqlServer = date('Y-m-d', strtotime($dt)); 
            $jamSqlServer = date('H:i:s', strtotime($dt));
            
            $tanggal = date('Y-m-d');
            $jam = date('H:i:s');

            $currentMonth = date('m');
            $currentYear = date('y');
            $prefix = 'FUS' . $currentMonth . $currentYear;
            $prefixLength = strlen($prefix);

            $lastNumber = DB::table('N_EMI_LAB_Uji_Sampel')
                ->where('No_Faktur', 'like', $prefix . '-%')
                ->lockForUpdate() 
                ->selectRaw("MAX(CAST(SUBSTRING(No_Faktur, ? + 2, 10) AS INT)) as max_number", [$prefixLength])
                ->value('max_number') ?? 0;

            $firstAnalysis = $request->analyses[0];
            $idDecoded = Hashids::connection('custom')->decode($firstAnalysis['Id_Jenis_Analisa']);
            $jenisAnalisa = isset($idDecoded[0]) ? $idDecoded[0] : null;

            $payloadActivityUjiSampel = [
                'Kode_Perusahaan' => '001',
                'No_Po_Sampel' => $firstAnalysis['No_Po_Sampel'],
                'No_Fak_Sub_Po' => $firstAnalysis['No_Po_Multi_Sampel'],
                'Jenis_Aktivitas' => 'save_submit',
                'Keterangan' => $pengguna->Nama . ' Berhasil Mengirimkan Data Analisa',
                'Id_User' => $pengguna->UserId,
                'Tanggal' => $tanggalSqlServer,
                'Jam' => $jamSqlServer,
                'Id_Jenis_Analisa' => $jenisAnalisa
            ];

            $idLogActivity = DB::table('N_EMI_LAB_Activity_Uji_Sampel')->insertGetId($payloadActivityUjiSampel, 'Id_Log_Activity');

            foreach ($request->analyses as $analysisData) {
                $noSementara = $analysisData['No_Sementara'] ?? null;
                $sumberData = (object) $analysisData;
                $idUserUntukInsert = $pengguna->UserId;  
                $isFromSementara = false;

                $isFlagKhusus = DB::table('N_EMI_LAB_PO_Sampel')
                        ->where('No_Sampel', $sumberData->No_Po_Sampel)
                        ->where('Flag_Khusus', 'Y')
                        ->exists();

                if (!$isFlagKhusus) {
                        $isAllowed = DB::table('N_EMI_LAB_Barang_Analisa')
                            ->where('Id_Jenis_Analisa', $jenisAnalisa)
                            ->where('Id_User', $userId)
                            ->exists();

                        if (!$isAllowed) {
                            return response()->json([
                                'success' => false,
                                'status' => 403,
                                'message' => "Anda tidak memiliki akses untuk Jenis Analisa ini"
                            ], 403);
                        }
                }

                $analysisData['parameters'] = collect($analysisData['parameters'])->map(function ($param) {
                    $decoded = Hashids::connection('custom')->decode($param['Id_Quality_Control']);
                    $decodedId = isset($decoded[0]) ? (string) $decoded[0] : null;
                    return [
                        'Id_Quality_Control' => $decodedId,
                        'Value_Parameter' => $param['Value_Parameter'],
                        'No_Urut' => $param['No_Urut'] ?? null,
                        'RV_INT' => $param['RV_INT'] ?? null
                    ];
                })->toArray();

                if ($noSementara) {
                    $dataSementara = DB::table('N_EMI_LAB_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->first();

                    if ($dataSementara) {
                        foreach ($analysisData['parameters'] as $paramFromRequest) {
                            $idDecoded = Hashids::connection('custom')->decode($paramFromRequest['No_Urut']);
                            $idDecodedRv = Hashids::connection('custom')->decode($paramFromRequest['RV_INT']);
                            $idNu = isset($idDecoded[0]) ? $idDecoded[0] : null;
                            $idRv = isset($idDecodedRv[0]) ? $idDecodedRv[0] : null;

                            if (empty($idNu) || empty($idRv)) {
                                return response()->json([
                                    'success' => false,
                                    'status' => 400,
                                    'message' => 'Data tidak lengkap untuk divalidasi. No_Urut atau RV_INT kosong pada data sementara.'
                                ], 400);
                            }

                            $dbParam = DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')
                                ->selectRaw('CAST(RV AS INT) AS RV_INT')
                                ->where('No_Sementara', $noSementara)
                                ->where('No_Urut', $idNu)
                                ->first();

                            if (!$dbParam) {
                                return response()->json([
                                    'success' => false,
                                    'status' => 404,
                                    'message' => 'Data parameter dengan No_Urut: ' . $paramFromRequest['No_Urut'] . ' tidak ditemukan.'
                                ], 404);
                            }

                            if ((int)$idRv !== (int)$dbParam->RV_INT) {
                                return response()->json([
                                    'success' => false,
                                    'status' => 409,
                                    'message' => 'Data sudah kedaluwarsa. Silakan refresh halaman.'
                                ], 409);
                            }
                        }

                        $isFromSementara = true;
                        $idUserUntukInsert = $dataSementara->Id_User;

                        $detailsSementara = DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')
                                            ->where('No_Sementara', $noSementara)
                                            ->get();

                        $requestParams = collect($analysisData['parameters'])->keyBy('Id_Quality_Control');

                        $mergedParameters = $detailsSementara->map(function ($dbParam) use ($requestParams) {
                            $qcId = (string) $dbParam->Id_Quality_Control;
                            $finalValue = $dbParam->Value_Parameter;

                            if ($requestParams->has($qcId) && is_null($dbParam->Value_Parameter)) {
                                $finalValue = $requestParams[$qcId]['Value_Parameter'];
                            }

                            return [
                                'Id_Quality_Control' => $qcId,
                                'Value_Parameter' => $finalValue
                            ];
                        })->toArray();

                        $sumberData = (object) [
                            'No_Po_Sampel' => $dataSementara->No_Po_Sampel,
                            'Id_Jenis_Analisa' => $jenisAnalisa,
                            'No_Po_Multi_Sampel' => $dataSementara->No_Fak_Sub_Po,
                            'is_multi_print' => $dataSementara->Flag_Multi_QrCode,
                            'parameters' => $mergedParameters,
                            'formulas' => $analysisData['formulas'] ?? [],
                        ];
                    }
                } else {
                    // Pastikan jika bukan dari sementara, kita tetap pakai decoded ID
                    $sumberData = (object) [
                        'No_Po_Sampel' => $analysisData['No_Po_Sampel'],
                        'Id_Jenis_Analisa' => $jenisAnalisa,
                        'No_Po_Multi_Sampel' => $analysisData['No_Po_Multi_Sampel'],
                        'is_multi_print' => $analysisData['is_multi_print'] ?? 'N',
                        'parameters' => $analysisData['parameters'],
                        'formulas' => $analysisData['formulas'] ?? [],
                    ];
                }

                $lastNumber++;
                $newNumber = $prefix . '-' . str_pad($lastNumber, 4, '0', STR_PAD_LEFT);
                $formulas = DB::table('N_EMI_LAB_Perhitungan')->where('Id_Jenis_Analisa', $sumberData->Id_Jenis_Analisa)->get();

                $parameterValues = collect($sumberData->parameters)->pluck('Value_Parameter', 'Id_Quality_Control');

                $calculatedResults = [];
                foreach ($formulas as $formula) {
                    $encodedFormula = $formula->Rumus;

                    // Ganti encoded ke decoded di formula agar cocok dengan hasil
                    preg_match_all('/\[(\d+)\]/', $encodedFormula, $matches);
                    if (!empty($matches[1])) {
                        foreach ($matches[1] as $originalId) {
                            $encoded = Hashids::connection('custom')->encode($originalId);
                            $encodedFormula = str_replace("[$originalId]", "[$encoded]", $encodedFormula);
                        }
                    }

                    $formula->Rumus = $encodedFormula;

                    $formulaFromRequest = collect($sumberData->formulas)->firstWhere('Rumus', $formula->Rumus);
                    $hasilDariRequest = $formulaFromRequest['Hasil_Perhitungan'] ?? null;
                    $rangeAwal = $formulaFromRequest['Range_Awal'] ?? null;
                    $rangeAkhir = $formulaFromRequest['Range_Akhir'] ?? null;

                    $resultValue = $hasilDariRequest ?: $this->calculateFormulaServerSide($formula->Rumus, $parameterValues, 0);

                    $calculatedResults[] = [
                        'Id_Perhitungan' => $formula->id,
                        'Id_Jenis_Analisa' => $formula->Id_Jenis_Analisa,
                        'Hasil_Perhitungan' => $resultValue,
                        'Range_Awal' => $rangeAwal,
                        'Range_Akhir' => $rangeAkhir
                    ];
                }

                // Build Insert Payload
                $payloadUjiSampleData = [];
                $payloadActivityUjiSampelHasil = [];
                foreach ($calculatedResults as $result) {
                    $hasilFloat = $this->safeFloat($result['Hasil_Perhitungan']);

                    $payloadUjiSampleData[] = [
                        "No_Faktur" => $newNumber,
                        "Kode_Perusahaan" => "001",
                        "Id_Jenis_Analisa" => $result['Id_Jenis_Analisa'],
                        "Id_Perhitungan" => $result['Id_Perhitungan'],
                        "Hasil" => $hasilFloat,
                        "Flag_Perhitungan" => "Y",
                        "Flag_Multi_QrCode" => $sumberData->is_multi_print,
                        "No_Fak_Sub_Po" => $sumberData->No_Po_Multi_Sampel,
                        "Status" => null,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $idUserUntukInsert,
                        "No_Po_Sampel" => $sumberData->No_Po_Sampel,
                        "Range_Awal" => $result['Range_Awal'],
                        "Range_Akhir" => $result['Range_Akhir'],
                    ];

                    $payloadActivityUjiSampelHasil[] = [
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $sumberData->No_Po_Sampel,
                        "No_Fak_Sub_Po" => $sumberData->No_Po_Multi_Sampel,
                        "Id_Jenis_Analisa" => $sumberData->Id_Jenis_Analisa,
                        "Id_Perhitungan" => $result['Id_Perhitungan'],
                        "Value_Baru" => $hasilFloat,
                        "Value_Lama" => $hasilFloat,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $idUserUntukInsert,
                        "Status_Submit" => "Submited",
                    ];
                }

                $payloadUjiSampleDetailData = [];
                $payloadActiviyUjiSampelDetail = [];
                foreach ($sumberData->parameters as $parameter) {
                    $paramValueFloat = $this->safeFloat($parameter['Value_Parameter']);

                    $payloadUjiSampleDetailData[] = [
                        "Kode_Perusahaan" => "001",
                        "No_Faktur_Uji_Sample" => $newNumber,
                        "Id_Quality_Control" => $parameter['Id_Quality_Control'],
                        "Value_Parameter" => $paramValueFloat,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $idUserUntukInsert,
                    ];

                    $payloadActiviyUjiSampelDetail[] = [
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $sumberData->No_Po_Sampel,
                        "No_Fak_Sub_Po" => $sumberData->No_Po_Multi_Sampel,
                        "Id_Jenis_Analisa" => $sumberData->Id_Jenis_Analisa,
                        "Id_Quality_Control" => $parameter['Id_Quality_Control'],
                        "Value_Baru" => $paramValueFloat,
                        "Value_Lama" => $paramValueFloat,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $idUserUntukInsert,
                        "Status_Submit" => "Submited",
                    ];
                }

                
                DB::table('N_EMI_LAB_Uji_Sampel')->insert($payloadUjiSampleData);
                DB::table('N_EMI_LAB_Uji_Sampel_Detail')->insert($payloadUjiSampleDetailData);
                DB::table('N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail')->insert($payloadActivityUjiSampelHasil);
                DB::table('N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail')->insert($payloadActiviyUjiSampelDetail);

                if ($isFromSementara) {
                    DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')->where('No_Sementara', $noSementara)->delete();
                    DB::table('N_EMI_LAB_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->delete();
                }

                $results[] = [
                    'generated_no_faktur' => $newNumber,
                    'status' => $isFromSementara ? 'temporary_table' : 'request',
                ];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => "Data berhasil diproses dan disimpan.",
                'results' => $results 
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('UjiSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan"
            ], 500);
        }
    }

    public function storeMultiRumusV2(Request $request)
    {
        if (is_string($request->input('analyses'))) {
            $request->merge([
                'analyses' => json_decode($request->input('analyses'), true)
            ]);
        }

        $request->validate([
            'analyses' => 'required|array|min:1',
            'analyses.*.No_Po_Sampel' => 'required|string',
            'analyses.*.Id_Jenis_Analisa' => 'required|string',
            'analyses.*.No_Sementara' => 'nullable|string',
            'analyses.*.is_multi_print' => 'required|string',
            'analyses.*.No_Po_Multi_Sampel' => 'nullable|string',
            'analyses.*.parameters' => 'required|array|min:1',
            'analyses.*.parameters.*.Id_Quality_Control' => 'required',
            'analyses.*.parameters.*.Value_Parameter' => 'required|numeric',
        ], [
            'analyses.required' => 'Tidak ada data analisis yang dikirim.',
            'analyses.*.parameters.required' => 'Parameter tidak boleh kosong untuk setiap baris.',
        ]);
       
        DB::beginTransaction();

        try {

            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow; 
            $tanggalSqlServer = date('Y-m-d', strtotime($dt)); 
            $jamSqlServer = date('H:i:s', strtotime($dt));

            $results = [];
           
            $pengguna = Auth::user();
            
            $userId = $pengguna->UserId;

            $userExists = DB::table('N_EMI_LAB_Users')->where('UserId', $userId)->exists();

            if (!$userExists) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "User dengan ID $userId tidak ditemukan di sistem."
                ], 404);
            }
            
            $currentMonth = date('m');
            $currentYear = date('y');
            $prefix = 'FUS' . $currentMonth . $currentYear;
            $prefixLength = strlen($prefix);

            $lastNumber = DB::table('N_EMI_LIMS_Uji_Sampel')
                ->where('No_Faktur', 'like', $prefix . '-%')
                ->lockForUpdate() 
                ->selectRaw("MAX(CAST(SUBSTRING(No_Faktur, ? + 2, 10) AS INT)) as max_number", [$prefixLength])
                ->value('max_number') ?? 0;

            $firstAnalysis = $request->analyses[0];
            $idDecoded = Hashids::connection('custom')->decode($firstAnalysis['Id_Jenis_Analisa']);
            $jenisAnalisa = isset($idDecoded[0]) ? $idDecoded[0] : null;

            $payloadActivityUjiSampel = [
                'Kode_Perusahaan' => '001',
                'No_Po_Sampel' => $firstAnalysis['No_Po_Sampel'],
                'No_Fak_Sub_Po' => $firstAnalysis['No_Po_Multi_Sampel'],
                'Jenis_Aktivitas' => 'save_submit',
                'Keterangan' => $pengguna->Nama . ' Berhasil Mengirimkan Data Analisa',
                'Id_User' => $pengguna->UserId,
                'Tanggal' => $tanggalSqlServer,
                'Jam' => $jamSqlServer,
                'Id_Jenis_Analisa' => $jenisAnalisa
            ];

            $idLogActivity = DB::table('N_EMI_LIMS_Activity_Uji_Sampel')->insertGetId($payloadActivityUjiSampel, 'Id_Log_Activity');
            $gcsFilePath = null;
            if ($request->hasFile('photo_data') && $request->flag_foto === 'Y') {
                $file = $request->file('photo_data');
                $extension = $file->getClientOriginalExtension() ?: 'png';
                
                // Hitung ukuran file yang baru saja tiba di server (sebelum masuk GCS)
                $receivedSizeMB = number_format($file->getSize() / 1048576, 2);
                Log::channel('FormulatorTrialSampelController')->info("📥 [UPLOAD FOTO] Menerima file murni dari Frontend. Ukuran: {$receivedSizeMB} MB");

                $fileName = 'formulator_' . Str::random(5) . '_' . time() . '.' . $extension;
                $gcsFilePath = 'berkas/formulator/' . $fileName;
                
                // Lempar ke GCS
                Storage::disk('gcs')->put($gcsFilePath, file_get_contents($file));

                // Hitung ulang ukuran file yang sudah sukses nongkrong di GCS
                $gcsFileSize = Storage::disk('gcs')->size($gcsFilePath);
                $gcsSizeMB = number_format($gcsFileSize / 1048576, 2);
                Log::channel('FormulatorTrialSampelController')->info("☁️ [GCS UPLOAD] Berhasil disimpan ke Cloud. Ukuran final: {$gcsSizeMB} MB | Path: {$gcsFilePath}");
            }
            foreach ($request->analyses as $analysisData) {
                $noSementara = $analysisData['No_Sementara'] ?? null;
                $sumberData = (object) $analysisData;
                $idUserUntukInsert = $pengguna->UserId;   
                $isFromSementara = false;

                $isFlagKhusus = DB::table('N_LIMS_PO_Sampel')
                        ->where('No_Sampel', $sumberData->No_Po_Sampel)
                        ->where('Flag_Khusus', 'Y')
                        ->exists();

                if (!$isFlagKhusus) {
                        $isAllowed = DB::table('N_EMI_LAB_Barang_Analisa')
                            ->where('Id_Jenis_Analisa', $jenisAnalisa)
                            ->where('Id_User', $userId)
                            ->where('Kode_Role', 'FLM')
                            ->exists();

                        if (!$isAllowed) {
                            return response()->json([
                                'success' => false,
                                'status' => 403,
                                'message' => "Anda tidak memiliki akses untuk Jenis Analisa ini"
                            ], 403);
                        }
                }

                $analysisData['parameters'] = collect($analysisData['parameters'])->map(function ($param) {
                    $decoded = Hashids::connection('custom')->decode($param['Id_Quality_Control']);
                    $decodedId = isset($decoded[0]) ? (string) $decoded[0] : null;
                    return [
                        'Id_Quality_Control' => $decodedId,
                        'Value_Parameter' => $param['Value_Parameter'],
                        'No_Urut' => $param['No_Urut'] ?? null,
                        'RV_INT' => $param['RV_INT'] ?? null
                    ];
                })->toArray();

                if ($noSementara) {
                    $dataSementara = DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->first();

                    if ($dataSementara) {
                        foreach ($analysisData['parameters'] as $paramFromRequest) {
                            $idDecoded = Hashids::connection('custom')->decode($paramFromRequest['No_Urut']);
                            $idDecodedRv = Hashids::connection('custom')->decode($paramFromRequest['RV_INT']);
                            $idNu = isset($idDecoded[0]) ? $idDecoded[0] : null;
                            $idRv = isset($idDecodedRv[0]) ? $idDecodedRv[0] : null;

                            if (empty($idNu) || empty($idRv)) {
                                return response()->json([
                                    'success' => false,
                                    'status' => 400,
                                    'message' => 'Data tidak lengkap untuk divalidasi. No_Urut atau RV_INT kosong pada data sementara.'
                                ], 400);
                            }

                            $dbParam = DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')
                                ->selectRaw('CAST(RV AS INT) AS RV_INT')
                                ->where('No_Sementara', $noSementara)
                                ->where('No_Urut', $idNu)
                                ->first();

                            if (!$dbParam) {
                                return response()->json([
                                    'success' => false,
                                    'status' => 404,
                                    'message' => 'Data parameter dengan No_Urut: ' . $paramFromRequest['No_Urut'] . ' tidak ditemukan.'
                                ], 404);
                            }

                            if ((int)$idRv !== (int)$dbParam->RV_INT) {
                                return response()->json([
                                    'success' => false,
                                    'status' => 409,
                                    'message' => 'Data sudah kedaluwarsa. Silakan refresh halaman.'
                                ], 409);
                            }
                        }

                        $isFromSementara = true;
                        $idUserUntukInsert = $dataSementara->Id_User;

                        $detailsSementara = DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')
                                                    ->where('No_Sementara', $noSementara)
                                                    ->get();

                        $requestParams = collect($analysisData['parameters'])->keyBy('Id_Quality_Control');

                        $mergedParameters = $detailsSementara->map(function ($dbParam) use ($requestParams) {
                            $qcId = (string) $dbParam->Id_Quality_Control;
                            $finalValue = $dbParam->Value_Parameter;

                            if ($requestParams->has($qcId) && is_null($dbParam->Value_Parameter)) {
                                $finalValue = $requestParams[$qcId]['Value_Parameter'];
                            }

                            return [
                                'Id_Quality_Control' => $qcId,
                                'Value_Parameter' => $finalValue
                            ];
                        })->toArray();

                        $sumberData = (object) [
                            'No_Po_Sampel' => $dataSementara->No_Po_Sampel,
                            'Id_Jenis_Analisa' => $jenisAnalisa,
                            'No_Po_Multi_Sampel' => $dataSementara->No_Fak_Sub_Po,
                            'is_multi_print' => $dataSementara->Flag_Multi_QrCode,
                            'parameters' => $mergedParameters,
                            'formulas' => $analysisData['formulas'] ?? [],
                            "Id_Mesin" => $analysisData['id_mesin']
                        ];
                    }
                } else {
                    $sumberData = (object) [
                        'No_Po_Sampel' => $analysisData['No_Po_Sampel'],
                        'Id_Jenis_Analisa' => $jenisAnalisa,
                        'No_Po_Multi_Sampel' => $analysisData['No_Po_Multi_Sampel'],
                        'is_multi_print' => $analysisData['is_multi_print'] ?? 'N',
                        'parameters' => $analysisData['parameters'],
                        'formulas' => $analysisData['formulas'] ?? [],
                        "Id_Mesin" => $analysisData['id_mesin']
                    ];
                }

                $lastNumber++;
                $newNumber = $prefix . '-' . str_pad($lastNumber, 4, '0', STR_PAD_LEFT);
                $formulas = DB::table('N_EMI_LAB_Perhitungan')
                    ->where('Id_Jenis_Analisa', $sumberData->Id_Jenis_Analisa)
                    ->where('Kode_Role', 'FLM')
                    ->get();

                $getKodeBarang = DB::table('N_LIMS_PO_Sampel')->where('No_Sampel', $sumberData->No_Po_Sampel)->first();
                

                $parameterValues = collect($sumberData->parameters)->pluck('Value_Parameter', 'Id_Quality_Control');

                $calculatedResults = [];
                foreach ($formulas as $formula) {
                    $encodedFormula = $formula->Rumus;

                    preg_match_all('/\[(\d+)\]/', $encodedFormula, $matches);
                    if (!empty($matches[1])) {
                        foreach ($matches[1] as $originalId) {
                            $encoded = Hashids::connection('custom')->encode($originalId);
                            $encodedFormula = str_replace("[$originalId]", "[$encoded]", $encodedFormula);
                        }
                    }

                    $formula->Rumus = $encodedFormula;

                    $formulaFromRequest = collect($sumberData->formulas)->firstWhere('Rumus', $formula->Rumus);
                  
                    $hasilDariRequest = $formulaFromRequest['Hasil_Perhitungan'] ?? null;
                    $getDataRange = DB::table("N_EMI_LAB_Standar_Rentang")
                                    ->where('Kode_Barang', $getKodeBarang->Kode_Barang)
                                    ->where('Id_Jenis_Analisa', $sumberData->Id_Jenis_Analisa)
                                    ->where('Id_Master_Mesin', $sumberData->Id_Mesin)
                                    ->where('Id_Perhitungan', $formula->id)
                                    ->where('Kode_Role', 'FLM')
                                    ->first();
                    
                    $rangeAwal = $getDataRange?->Range_Awal;
                    $rangeAkhir = $getDataRange?->Range_Akhir;

                    $resultValue = $hasilDariRequest ?: $this->calculateFormulaServerSide($formula->Rumus, $parameterValues, 0);

                    $calculatedResults[] = [
                        'No_Po_Sampel' => $sumberData->No_Po_Sampel,
                        'No_Sub_Sampel' => $sumberData->No_Po_Multi_Sampel,
                        'Id_Perhitungan' => $formula->id,
                        'Id_Jenis_Analisa' => $formula->Id_Jenis_Analisa,
                        'Hasil_Perhitungan' => $resultValue,
                        'Range_Awal' => $rangeAwal,
                        'Range_Akhir' => $rangeAkhir
                    ];
                }

                $payloadUjiSampleData = [];
                $payloadActivityUjiSampelHasil = [];
                
                foreach ($calculatedResults as $result) {
                    $RentangAwal = $result['Range_Awal'];
                    $hasilFloat = $this->safeFloat($result['Hasil_Perhitungan']);
                    $Flag_Layak = null;

                    $checkNonPerhitungan = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                        ->where('Id_Jenis_Analisa', $result['Id_Jenis_Analisa'])
                        ->where('Flag_Aktif', 'Y')
                        ->where('Kode_Role', 'FLM')
                        ->get();

                    if ($checkNonPerhitungan->isNotEmpty()) {
                        $match = $checkNonPerhitungan->where('Nilai_Kriteria', $hasilFloat)->first();
                        
                        if ($match) {
                            $Flag_Layak = $match->Flag_Layak;
                        } else {
                            $Flag_Layak = 'T';
                        }
                    } else {
                        if (!is_null($RentangAwal) && $hasilFloat < (float)$RentangAwal) {
                            $Flag_Layak = 'T';
                        } else {
                            $Flag_Layak = 'Y';
                        }
                    }

                    $getDataMesin = DB::table('EMI_Master_Mesin')
                                    ->where('Id_Master_Mesin', $sumberData->Id_Mesin)
                                    ->where('Flag_FG', 'Y')
                                    ->first();

                    if($getDataMesin){
                            $payloadUjiSampleData[] = [
                            "No_Faktur" => $newNumber,
                            "Kode_Perusahaan" => "001",
                            "Flag_Foto" => $request->flag_foto,
                            "Id_Jenis_Analisa" => $result['Id_Jenis_Analisa'],
                            "Id_Perhitungan" => $result['Id_Perhitungan'],
                            "Hasil" => $hasilFloat,
                            "Flag_Perhitungan" => "Y",
                            "Flag_Multi_QrCode" => $sumberData->is_multi_print,
                            "No_Fak_Sub_Po" => $sumberData->No_Po_Multi_Sampel,
                            "Status" => null,
                            'Tahapan_Ke' => 1,
                            "Tanggal" => $tanggalSqlServer,
                            "Jam" => $jamSqlServer,
                            "Id_User" => $idUserUntukInsert,
                            "No_Po_Sampel" => $sumberData->No_Po_Sampel,
                            "Range_Awal" => $result['Range_Awal'],
                            "Range_Akhir" => $result['Range_Akhir'],
                            "Flag_Resampling" => null,
                            "Status_Keputusan_Sampel" => "menunggu",
                            'Flag_Layak' => $Flag_Layak,
                            "Flag_Final" => null,
                            'Id_Mesin' => $sumberData->Id_Mesin
                        ];
                    }else {
                        $payloadUjiSampleData[] = [
                            "No_Faktur" => $newNumber,
                            "Flag_Foto" => $request->flag_foto,
                            "Kode_Perusahaan" => "001",
                            "Id_Jenis_Analisa" => $result['Id_Jenis_Analisa'],
                            "Id_Perhitungan" => $result['Id_Perhitungan'],
                            "Hasil" => $hasilFloat,
                            "Flag_Perhitungan" => "Y",
                            "Flag_Multi_QrCode" => $sumberData->is_multi_print,
                            "No_Fak_Sub_Po" => $sumberData->No_Po_Multi_Sampel,
                            "Status" => null,
                            'Tahapan_Ke' => 1,
                            "Tanggal" => $tanggalSqlServer,
                            "Jam" => $jamSqlServer,
                            "Id_User" => $idUserUntukInsert,
                            "No_Po_Sampel" => $sumberData->No_Po_Sampel,
                            "Range_Awal" => $result['Range_Awal'],
                            "Range_Akhir" => $result['Range_Akhir'],
                            "Flag_Resampling" => null,
                            "Status_Keputusan_Sampel" => "menunggu",
                            'Flag_Layak' => $Flag_Layak,
                            "Flag_Final" => null,
                            'Id_Mesin' => $sumberData->Id_Mesin
                        ];
                    }

                    

                    $payloadActivityUjiSampelHasil[] = [
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $sumberData->No_Po_Sampel,
                        "No_Fak_Sub_Po" => $sumberData->No_Po_Multi_Sampel,
                        "Id_Jenis_Analisa" => $sumberData->Id_Jenis_Analisa,
                        "Id_Perhitungan" => $result['Id_Perhitungan'],
                        "Value_Baru" => $hasilFloat,
                        "Value_Lama" => $hasilFloat,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $idUserUntukInsert,
                        "Status_Submit" => "Submited",
                    ];
                }

                $payloadUjiSampleDetailData = [];
                $payloadActiviyUjiSampelDetail = [];
                foreach ($sumberData->parameters as $parameter) {
                    $paramValueFloat = $this->safeFloat($parameter['Value_Parameter']);

                    $payloadUjiSampleDetailData[] = [
                        "Kode_Perusahaan" => "001",
                        "No_Faktur_Uji_Sample" => $newNumber,
                        "Id_Quality_Control" => $parameter['Id_Quality_Control'],
                        "Value_Parameter" => $paramValueFloat,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $idUserUntukInsert,
                    ];

                    $payloadActiviyUjiSampelDetail[] = [
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $sumberData->No_Po_Sampel,
                        "No_Fak_Sub_Po" => $sumberData->No_Po_Multi_Sampel,
                        "Id_Jenis_Analisa" => $sumberData->Id_Jenis_Analisa,
                        "Id_Quality_Control" => $parameter['Id_Quality_Control'],
                        "Value_Baru" => $paramValueFloat,
                        "Value_Lama" => $paramValueFloat,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $idUserUntukInsert,
                        "Status_Submit" => "Submited",
                    ];
                }

                
                DB::table('N_EMI_LIMS_Uji_Sampel')->insert($payloadUjiSampleData);
                DB::table('N_EMI_LIMS_Uji_Sampel_Detail')->insert($payloadUjiSampleDetailData);
                DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail')->insert($payloadActivityUjiSampelHasil);
                DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail')->insert($payloadActiviyUjiSampelDetail);

                if ($gcsFilePath) {
                    DB::table('N_EMI_LIMS_Berkas_Uji_Lab')->insert([
                        'No_Faktur' => $newNumber,
                        'No_Sampel' => $sumberData->No_Po_Sampel,
                        'Berkas_Key' => Str::random(32),
                        'File_Path' => $gcsFilePath
                    ]);
                }

                if ($isFromSementara) {
                    DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')->where('No_Sementara', $noSementara)->delete();
                    DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->delete();
                }

                $results[] = [
                    'generated_no_faktur' => $newNumber,
                    'status' => $isFromSementara ? 'temporary_table' : 'request',
                ];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => "Data berhasil diproses dan disimpan.",
                'results' => $results 
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($gcsFilePath) && Storage::disk('gcs')->exists($gcsFilePath)) {
                Storage::disk('gcs')->delete($gcsFilePath);
            }
            Log::channel("FormulatorTrialSampelController")->error('Error: ' . $e->getMessage());
            return response('FormulatorTrialSampelController')->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan Server: " . $e->getMessage(),
                'trace' => $e->getTraceAsString() 
            ], 500);
        }
    }
    
    public function storeMultiRumusV2Testing(Request $request)
    {
        // 1. Parsing Input JSON (kalau dikirim sebagai string dari form-data)
        if (is_string($request->input('analyses'))) {
            $request->merge([
                'analyses' => json_decode($request->input('analyses'), true)
            ]);
        }

        // 2. Validasi Minimal (Biar nggak error null pointer aja)
        $request->validate([
            'analyses' => 'required|array|min:1',
        ]);

        try {
            $gcsFilePath = null;
            $gcsSizeMB   = 0;
            $uploadStatus = 'Tidak ada file yang diunggah';

            // 3. PROSES INTI: Upload File ke GCS
            if ($request->hasFile('photo_data')) {
                $file = $request->file('photo_data');
                $extension = $file->getClientOriginalExtension() ?: 'png';
                
                $fileName = 'formulator_dummy_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
                $gcsFilePath = 'berkas/formulator/' . $fileName; // Folder khusus testing
                
                // Tembak langsung ke GCS
                Storage::disk('gcs')->put($gcsFilePath, file_get_contents($file));

                // Cek ukuran file di GCS untuk memastikan file benar-benar masuk
                $gcsFileSize = Storage::disk('gcs')->size($gcsFilePath);
                $gcsSizeMB = number_format($gcsFileSize / 1048576, 2);
                
                Log::channel('FormulatorTrialSampelController')->info("☁️ [TEST GCS] File berhasil masuk! Path: {$gcsFilePath} | Size: {$gcsSizeMB} MB");
                $uploadStatus = 'Upload ke GCS SUKSES!';
            }

            // 4. Return Response (Tanpa nyentuh DB sama sekali)
            return response()->json([
                'success' => true,
                'status'  => 201,
                'message' => "Testing GCS Selesai.",
                'data_dummy' => [
                    'generated_no_faktur' => 'DUMMY-FAKTUR-' . rand(1000, 9999), // Tembak angka sembarangan
                    'gcs_status'          => $uploadStatus,
                    'gcs_file_path'       => $gcsFilePath,
                    'gcs_file_size_mb'    => $gcsSizeMB
                ]
            ], 201);

        } catch (\Exception $e) {
            // Kalau gagal, catch error-nya biar gampang di-debug
            Log::channel("FormulatorTrialSampelController")->error('Error Testing GCS: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => "Gagal upload ke GCS: " . $e->getMessage(),
            ], 500);
        }
    }

    public function storeMultiRumusResamplingV2(Request $request)
    {
        if (is_string($request->input('analyses'))) {
            $request->merge([
                'analyses' => json_decode($request->input('analyses'), true)
            ]);
        }

        $request->validate([
            'analyses' => 'required|array|min:1',
            'analyses.*.No_Po_Sampel' => 'required|string',
            'analyses.*.Id_Jenis_Analisa' => 'required|string',
            'analyses.*.No_Sementara' => 'nullable|string',
            'analyses.*.is_multi_print' => 'required|string',
            'analyses.*.No_Po_Multi_Sampel' => 'nullable|string',
            'analyses.*.Id_Resampling' => 'required|string',
            'analyses.*.parameters' => 'required|array|min:1',
            'analyses.*.parameters.*.Id_Quality_Control' => 'required',
            'analyses.*.parameters.*.Value_Parameter' => 'required|numeric',
        ], [
            'analyses.required' => 'Tidak ada data analisis yang dikirim.',
            'analyses.*.parameters.required' => 'Parameter tidak boleh kosong untuk setiap baris.',
            'analyses.*.Id_Resampling.required' => 'Id_Resampling wajib diisi.',
        ]);

        DB::beginTransaction();

        try {
            $results = [];
            $oldFilesToDeleteGcs = [];
            $pengguna = Auth::user();
            $userId = $pengguna->UserId;

            $userExists = DB::table('N_EMI_LAB_Users')->where('UserId', $userId)->exists();

            if (!$userExists) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "User dengan ID $userId tidak ditemukan di sistem."
                ], 404);
            }

            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow;
            $tanggalSqlServer = date('Y-m-d', strtotime($dt));
            $jamSqlServer = date('H:i:s', strtotime($dt));

            $currentMonth = date('m', strtotime($dt));
            $currentYear = date('y', strtotime($dt));
            $prefix = 'FUS' . $currentMonth . $currentYear;
            $prefixLength = strlen($prefix);

            $lastNumber = DB::table('N_EMI_LIMS_Uji_Sampel')
                ->where('No_Faktur', 'like', $prefix . '-%')
                ->lockForUpdate()
                ->selectRaw("MAX(CAST(SUBSTRING(No_Faktur, ? + 2, 10) AS INT)) as max_number", [$prefixLength])
                ->value('max_number') ?? 0;

            $firstAnalysis = $request->analyses[0];
            $idDecoded = Hashids::connection('custom')->decode($firstAnalysis['Id_Jenis_Analisa']);
            $jenisAnalisa = isset($idDecoded[0]) ? $idDecoded[0] : null;

            if (!$jenisAnalisa) {
                DB::rollBack();
                return response()->json(['success' => false, 'status' => 400, 'message' => 'Format Id_Jenis_Analisa tidak valid.'], 400);
            }

            $payloadActivityUjiSampel = [
                'Kode_Perusahaan' => '001',
                'No_Po_Sampel' => $firstAnalysis['No_Po_Sampel'],
                'No_Fak_Sub_Po' => null,
                'Jenis_Aktivitas' => 'save_submit_resampling',
                'Keterangan' => $pengguna->Nama . ' Berhasil Mengirimkan Data Resampling',
                'Id_User' => $pengguna->UserId,
                'Tanggal' => $tanggalSqlServer,
                'Jam' => $jamSqlServer,
                'Id_Jenis_Analisa' => $jenisAnalisa
            ];

            $idLogActivity = DB::table('N_EMI_LIMS_Activity_Uji_Sampel')->insertGetId($payloadActivityUjiSampel, 'Id_Log_Activity');

            $gcsFilePath = null;
            if ($request->hasFile('photo_data') && $request->flag_foto === 'Y') {
                $file = $request->file('photo_data');
                $extension = $file->getClientOriginalExtension() ?: 'png';
                
                $receivedSizeMB = number_format($file->getSize() / 1048576, 2);
                Log::channel('FormulatorTrialSampelController')->info("📥 [UPLOAD FOTO] Menerima file murni dari Frontend. Ukuran: {$receivedSizeMB} MB");

                $fileName = 'formulator_' . Str::random(5) . '_' . time() . '.' . $extension;
                $gcsFilePath = 'berkas/formulator/' . $fileName;

                Storage::disk('gcs')->put($gcsFilePath, file_get_contents($file));

                $gcsFileSize = Storage::disk('gcs')->size($gcsFilePath);
                $gcsSizeMB = number_format($gcsFileSize / 1048576, 2);
                Log::channel('FormulatorTrialSampelController')->info("☁️ [GCS UPLOAD] Berhasil disimpan ke Cloud. Ukuran final: {$gcsSizeMB} MB | Path: {$gcsFilePath}");
            }

            foreach ($request->analyses as $index => $analysisData) {
                $noSementara = $analysisData['No_Sementara'] ?? null;
                $idUserUntukInsert = $pengguna->UserId;
                $isFromSementara = false;

                try {
                    $decoded = Hashids::connection('custom')->decode($analysisData['Id_Resampling']);
                    if (empty($decoded)) {
                        throw new \Exception('Invalid ID Resampling');
                    }
                    $id_resampling = $decoded[0];
                } catch (\Exception $e) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'status' => 400,
                        'message' => 'Format Id_Resampling tidak valid.'
                    ], 400);
                }

                $resamplingLog = DB::table('N_EMI_LIMS_Uji_Sampel_Resampling_Log')
                    ->where('Id_Resampling', $id_resampling)
                    ->first();

                if (!$resamplingLog) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'status' => 404,
                        'message' => "Data log resampling dengan ID $id_resampling tidak ditemukan."
                    ], 404);
                }

                $noFakSubPoYangBenar = $resamplingLog->No_Sampel_Resampling;

                if ($index === 0) {
                    DB::table('N_EMI_LIMS_Activity_Uji_Sampel')
                        ->where('Id_Log_Activity', $idLogActivity)
                        ->update(['No_Fak_Sub_Po' => $noFakSubPoYangBenar]);
                }

                $isFlagKhusus = DB::table('N_LIMS_PO_Sampel')
                    ->where('No_Sampel', $analysisData['No_Po_Sampel'])
                    ->where('Flag_Khusus', 'Y')
                    ->exists();

                if (!$isFlagKhusus) {
                    $isAllowed = DB::table('N_EMI_LAB_Barang_Analisa')
                        ->where('Kode_Role', 'FLM')
                        ->where('Id_Jenis_Analisa', $jenisAnalisa)
                        ->where('Id_User', $userId)
                        ->exists();

                    if (!$isAllowed) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'status' => 403,
                            'message' => "Anda tidak memiliki akses untuk Jenis Analisa ini"
                        ], 403);
                    }
                }

                $analysisData['parameters'] = collect($analysisData['parameters'])->map(function ($param) {
                    $decoded = Hashids::connection('custom')->decode($param['Id_Quality_Control']);
                    $decodedId = isset($decoded[0]) ? (string) $decoded[0] : null;
                    return [
                        'Id_Quality_Control' => $decodedId,
                        'Value_Parameter' => $param['Value_Parameter'],
                        'No_Urut' => $param['No_Urut'] ?? null,
                        'RV_INT' => $param['RV_INT'] ?? null
                    ];
                })->toArray();

                if ($noSementara) {
                    $dataSementara = DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->first();

                    if ($dataSementara) {
                        foreach ($analysisData['parameters'] as $paramFromRequest) {
                            $idDecoded = Hashids::connection('custom')->decode($paramFromRequest['No_Urut']);
                            $idDecodedRv = Hashids::connection('custom')->decode($paramFromRequest['RV_INT']);
                            $idNu = isset($idDecoded[0]) ? $idDecoded[0] : null;
                            $idRv = isset($idDecodedRv[0]) ? $idDecodedRv[0] : null;

                            if (empty($idNu) || empty($idRv)) {
                                DB::rollBack();
                                return response()->json([
                                    'success' => false,
                                    'status' => 400,
                                    'message' => 'Data tidak lengkap untuk divalidasi. No_Urut atau RV_INT kosong pada data sementara.'
                                ], 400);
                            }
                        }

                        $isFromSementara = true;
                        $idUserUntukInsert = $dataSementara->Id_User;

                        $detailsSementara = DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')
                            ->where('No_Sementara', $noSementara)
                            ->get();

                        $requestParams = collect($analysisData['parameters'])->keyBy('Id_Quality_Control');

                        $mergedParameters = $detailsSementara->map(function ($dbParam) use ($requestParams) {
                            $qcId = (string) $dbParam->Id_Quality_Control;
                            $finalValue = $dbParam->Value_Parameter;

                            if ($requestParams->has($qcId) && is_null($dbParam->Value_Parameter)) {
                                $finalValue = $requestParams[$qcId]['Value_Parameter'];
                            }

                            return [
                                'Id_Quality_Control' => $qcId,
                                'Value_Parameter' => $finalValue
                            ];
                        })->toArray();

                        $sumberData = (object) [
                            'No_Po_Sampel' => $dataSementara->No_Po_Sampel,
                            'Id_Jenis_Analisa' => $jenisAnalisa,
                            'No_Po_Multi_Sampel' => $noFakSubPoYangBenar,
                            'is_multi_print' => $dataSementara->Flag_Multi_QrCode,
                            'parameters' => $mergedParameters,
                            'formulas' => $analysisData['formulas'] ?? [],
                            "Id_Mesin" => $analysisData['id_mesin'],
                            "Tahapan_Ke" => $analysisData['Tahapan_Ke'],
                            "Id_Resampling" => $analysisData['Id_Resampling']
                        ];
                    }
                } else {
                    $sumberData = (object) [
                        'No_Po_Sampel' => $analysisData['No_Po_Sampel'],
                        'Id_Jenis_Analisa' => $jenisAnalisa,
                        'No_Po_Multi_Sampel' => $noFakSubPoYangBenar,
                        'is_multi_print' => $analysisData['is_multi_print'] ?? 'N',
                        'parameters' => $analysisData['parameters'],
                        'formulas' => $analysisData['formulas'] ?? [],
                        "Id_Mesin" => $analysisData['id_mesin'],
                        "Tahapan_Ke" => $analysisData['Tahapan_Ke'],
                        "Id_Resampling" => $analysisData['Id_Resampling']
                    ];
                }

                $lastNumber++;
                $newNumber = $prefix . '-' . str_pad($lastNumber, 4, '0', STR_PAD_LEFT);

                $formulas = DB::table('N_EMI_LAB_Perhitungan')
                    ->where('Kode_Role', 'FLM')
                    ->where('Id_Jenis_Analisa', $sumberData->Id_Jenis_Analisa)
                    ->get();

                $parameterValues = collect($sumberData->parameters)->pluck('Value_Parameter', 'Id_Quality_Control');
                $getKodeBarang = DB::table('N_LIMS_PO_Sampel')->where('No_Sampel', $sumberData->No_Po_Sampel)->first();


                $calculatedResults = [];
                foreach ($formulas as $formula) {
                    $encodedFormula = $formula->Rumus;
                    
                    preg_match_all('/\[(\d+)\]/', $encodedFormula, $matches);
                    if (!empty($matches[1])) {
                        foreach ($matches[1] as $originalId) {
                            $encoded = Hashids::connection('custom')->encode($originalId);
                            $encodedFormula = str_replace("[$originalId]", "[$encoded]", $encodedFormula);
                        }
                    }

                    $formula->Rumus = $encodedFormula;

                    $formulaFromRequest = collect($sumberData->formulas)->firstWhere('Rumus', $formula->Rumus);
                 
                    $hasilDariRequest = $formulaFromRequest['Hasil_Perhitungan'] ?? null;
                    $getDataRange = DB::table("N_EMI_LAB_Standar_Rentang")
                                    ->where('Kode_Barang', $getKodeBarang->Kode_Barang)
                                    ->where('Id_Jenis_Analisa', $sumberData->Id_Jenis_Analisa)
                                    ->where('Id_Master_Mesin', $sumberData->Id_Mesin)
                                    ->where('Id_Perhitungan', $formula->id)
                                    ->first();
                    
                    $rangeAwal = $getDataRange?->Range_Awal;
                    $rangeAkhir = $getDataRange?->Range_Akhir;

                    $resultValue = $hasilDariRequest ?: $this->calculateFormulaServerSide($formula->Rumus, $parameterValues, 0);

                    $calculatedResults[] = [
                        'No_Po_Sampel' => $sumberData->No_Po_Sampel,
                        'No_Sub_Sampel' => $sumberData->No_Po_Multi_Sampel,
                        'Id_Perhitungan' => $formula->id,
                        'Id_Jenis_Analisa' => $formula->Id_Jenis_Analisa,
                        'Hasil_Perhitungan' => $resultValue,
                        'Range_Awal' => $rangeAwal,
                        'Range_Akhir' => $rangeAkhir
                    ];
                }

                $payloadUjiSampleData = [];
                $payloadActivityUjiSampelHasil = [];


                foreach ($calculatedResults as $result) {
                    $RentangAwal = (float) $result['Range_Awal'];
                    $hasilFloat = $this->safeFloat($result['Hasil_Perhitungan']);
                    $Flag_Layak = ($hasilFloat < $RentangAwal) ? 'T' : 'Y';

                    $getDataMesin = DB::table('EMI_Master_Mesin')
                        ->where('Id_Master_Mesin', $sumberData->Id_Mesin)
                        ->where('Flag_FG', 'Y')
                        ->first();

                    $tahapanKe = $getDataMesin ? $sumberData->Tahapan_Ke : 1;

                    $payloadUjiSampleData[] = [
                        "No_Faktur" => $newNumber,
                        "Kode_Perusahaan" => "001",
                        "Flag_Foto" => $request->flag_foto,
                        "Id_Jenis_Analisa" => $result['Id_Jenis_Analisa'],
                        "Id_Perhitungan" => $result['Id_Perhitungan'],
                        "Hasil" => $hasilFloat,
                        "Flag_Perhitungan" => "Y",
                        "Flag_Multi_QrCode" => $sumberData->is_multi_print,
                        "No_Fak_Sub_Po" => $sumberData->No_Po_Multi_Sampel,
                        "Status" => null,
                        'Tahapan_Ke' => $tahapanKe,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $idUserUntukInsert,
                        "No_Po_Sampel" => $sumberData->No_Po_Sampel,
                        "Range_Awal" => $result['Range_Awal'],
                        "Range_Akhir" => $result['Range_Akhir'],
                        "Flag_Resampling" => null,
                        "Status_Keputusan_Sampel" => "menunggu",
                        'Flag_Layak' => $Flag_Layak,
                        "Flag_Final" => null,
                        'Id_Mesin' => $sumberData->Id_Mesin
                    ];

                    $payloadActivityUjiSampelHasil[] = [
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $sumberData->No_Po_Sampel,
                        "No_Fak_Sub_Po" => $sumberData->No_Po_Multi_Sampel,
                        "Id_Jenis_Analisa" => $sumberData->Id_Jenis_Analisa,
                        "Id_Perhitungan" => $result['Id_Perhitungan'],
                        "Value_Baru" => $hasilFloat,
                        "Value_Lama" => $hasilFloat,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $idUserUntukInsert,
                        "Status_Submit" => "Submited",
                    ];
                }

                $payloadUjiSampleDetailData = [];
                $payloadActiviyUjiSampelDetail = [];
                foreach ($sumberData->parameters as $parameter) {
                    $paramValueFloat = $this->safeFloat($parameter['Value_Parameter']);

                    $payloadUjiSampleDetailData[] = [
                        "Kode_Perusahaan" => "001",
                        "No_Faktur_Uji_Sample" => $newNumber,
                        "Id_Quality_Control" => $parameter['Id_Quality_Control'],
                        "Value_Parameter" => $paramValueFloat,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $idUserUntukInsert,
                    ];

                    $payloadActiviyUjiSampelDetail[] = [
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $sumberData->No_Po_Sampel,
                        "No_Fak_Sub_Po" => $sumberData->No_Po_Multi_Sampel,
                        "Id_Jenis_Analisa" => $sumberData->Id_Jenis_Analisa,
                        "Id_Quality_Control" => $parameter['Id_Quality_Control'],
                        "Value_Baru" => $paramValueFloat,
                        "Value_Lama" => $paramValueFloat,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $idUserUntukInsert,
                        "Status_Submit" => "Submited",
                    ];
                }

                DB::table('N_EMI_LIMS_Uji_Sampel')->insert($payloadUjiSampleData);
                DB::table('N_EMI_LIMS_Uji_Sampel_Detail')->insert($payloadUjiSampleDetailData);

                DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail')->insert($payloadActivityUjiSampelHasil);
                DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail')->insert($payloadActiviyUjiSampelDetail);

                if ($gcsFilePath) {
                    // 1. Cari No_Faktur lama khusus untuk Sub PO dan Jenis Analisa ini saja
                    $oldFakturs = DB::table('N_EMI_LIMS_Uji_Sampel')
                        ->where('No_Po_Sampel', $sumberData->No_Po_Sampel)
                        ->where('No_Fak_Sub_Po', $sumberData->No_Po_Multi_Sampel)
                        ->where('Id_Jenis_Analisa', $sumberData->Id_Jenis_Analisa)
                        ->pluck('No_Faktur')
                        ->toArray();

                    if (!empty($oldFakturs)) {
                        // 2. Ambil data berkas lamanya berdasarkan No_Faktur spesifik
                        $oldBerkasRecords = DB::table('N_EMI_LIMS_Berkas_Uji_Lab')
                            ->whereIn('No_Faktur', $oldFakturs)
                            ->get();

                        foreach ($oldBerkasRecords as $berkas) {
                            if (!empty($berkas->File_Path)) {
                                $oldFilesToDeleteGcs[] = $berkas->File_Path;
                            }
                        }

                        // 4. Hapus HANYA record berkas dari analisa yang di-resampling
                        DB::table('N_EMI_LIMS_Berkas_Uji_Lab')
                            ->whereIn('No_Faktur', $oldFakturs)
                            ->delete();
                    }

                    // 5. Insert data berkas yang baru dengan No_Faktur yang baru
                    DB::table('N_EMI_LIMS_Berkas_Uji_Lab')->insert([
                        'No_Faktur' => $newNumber,
                        'No_Sampel' => $sumberData->No_Po_Sampel,
                        'Berkas_Key' => Str::random(32),
                        'File_Path' => $gcsFilePath
                    ]);
                }

                if ($isFromSementara) {
                    DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')->where('No_Sementara', $noSementara)->delete();
                    DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->delete();
                }

                DB::table("N_EMI_LIMS_Uji_Sampel_Resampling_Log")
                    ->where('Id_Resampling', $id_resampling)
                    ->update([
                        'Flag_Selesai_Resampling' => 'Y'
                    ]);

                $results[] = [
                    'generated_no_faktur' => $newNumber,
                    'no_fak_sub_po_used' => $sumberData->No_Po_Multi_Sampel,
                    'status' => $isFromSementara ? 'temporary_table' : 'request',
                ];
            }

            DB::commit();

            if (!empty($oldFilesToDeleteGcs)) {
                $uniqueFilesToDelete = array_unique($oldFilesToDeleteGcs);
                foreach ($uniqueFilesToDelete as $oldPath) {
                    if (Storage::disk('gcs')->exists($oldPath)) {
                        Storage::disk('gcs')->delete($oldPath);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => "Data resampling berhasil diproses dan disimpan.",
                'results' => $results
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($gcsFilePath) && Storage::disk('gcs')->exists($gcsFilePath)) {
                Storage::disk('gcs')->delete($gcsFilePath);
            }
            Log::channel('FormulatorTrialSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan"
            ], 500);
        }
    }

    public function storeMultiQrCodeNotRumus(Request $request)
    {

        if (is_string($request->input('analyses'))) {
            $request->merge([
                'analyses' => json_decode($request->input('analyses'), true)
            ]);
        }

        $request->validate([
            'analyses' => 'required|array|min:1',
            'analyses.*.No_Po_Sampel' => 'required|string',
            'analyses.*.Id_Jenis_Analisa' => 'required|string',
            'analyses.*.No_Sementara' => 'nullable|string',
            'analyses.*.is_multi_print' => 'required|string',
            'analyses.*.No_Po_Multi_Sampel' => 'nullable|string',
            'analyses.*.parameters' => 'required|array|min:1',
            'analyses.*.parameters.*.Id_Quality_Control' => 'required',
            'analyses.*.parameters.*.Value_Parameter' => 'required',
        ], [
            'analyses.required' => 'Tidak ada data analisis yang dikirim.',
            'analyses.*.parameters.required' => 'Parameter tidak boleh kosong untuk setiap baris.',
        ]);
        
        DB::beginTransaction();
        
        try {
            $results = [];
            
            $pengguna = Auth::user();
            $userId = $pengguna->UserId;

            $userExists = DB::table('N_EMI_LAB_Users')->where('UserId', $userId)->exists();

            if (!$userExists) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "User dengan ID $userId tidak ditemukan di sistem."
                ], 404);
            }

            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow; 
            $tanggalSqlServer = date('Y-m-d', strtotime($dt)); 
            $jamSqlServer = date('H:i:s', strtotime($dt));
            
            $currentMonth = date('m');
            $currentYear = date('y');
            $prefix = 'FUS' . $currentMonth . $currentYear;
            $prefixLength = strlen($prefix);

            $lastNumber = DB::table('N_EMI_LIMS_Uji_Sampel')
                ->where('No_Faktur', 'like', $prefix . '-%')
                ->lockForUpdate() 
                ->selectRaw("MAX(CAST(SUBSTRING(No_Faktur, ? + 2, 10) AS INT)) as max_number", [$prefixLength])
                ->value('max_number') ?? 0;

            $firstAnalysis = $request->analyses[0];
            $idDecodedFirst = Hashids::connection('custom')->decode($firstAnalysis['Id_Jenis_Analisa']);
            $jenisAnalisaIdFirst = isset($idDecodedFirst[0]) ? $idDecodedFirst[0] : null;

            $payloadActivityUjiSampel = [
                'Kode_Perusahaan' => '001',
                'No_Po_Sampel' => $firstAnalysis['No_Po_Sampel'],
                'No_Fak_Sub_Po' => $firstAnalysis['No_Po_Multi_Sampel'],
                'Jenis_Aktivitas' => 'save_submit',
                'Keterangan' => $pengguna->Nama . ' Berhasil Mengirimkan Data Analisa',
                'Id_User' => $pengguna->UserId,
                'Tanggal' => $tanggalSqlServer,
                'Jam' => $jamSqlServer,
                'Id_Jenis_Analisa' => $jenisAnalisaIdFirst
            ];

            $idLogActivity = DB::table('N_EMI_LIMS_Activity_Uji_Sampel')->insertGetId($payloadActivityUjiSampel, 'Id_Log_Activity');

            $uploadedFilesData = [];
            if ($request->hasFile('photos') && $request->flag_foto === 'Y') {
                foreach ($request->file('photos') as $index => $file) {
                    $extension = $file->getClientOriginalExtension() ?: 'png';
                    $receivedSizeMB = number_format($file->getSize() / 1048576, 2);
                    Log::channel('FormulatorTrialSampelController')->info("📥 [UPLOAD FOTO MULTIPLE] Menerima file indeks {$index} murni dari Frontend. Ukuran: {$receivedSizeMB} MB");

                    $fileName = 'formulator_' . Str::random(5) . '_' . time() . '_' . $index . '.' . $extension;
                    $gcsFilePath = 'berkas/formulator/' . $fileName;
                    
                    Storage::disk('gcs')->put($gcsFilePath, file_get_contents($file));

                    $gcsFileSize = Storage::disk('gcs')->size($gcsFilePath);
                    $gcsSizeMB = number_format($gcsFileSize / 1048576, 2);
                    Log::channel('FormulatorTrialSampelController')->info("☁️ [GCS UPLOAD MULTIPLE] Berhasil disimpan ke Cloud (Indeks {$index}). Ukuran final: {$gcsSizeMB} MB | Path: {$gcsFilePath}");

                    $note = $request->input("notes.$index") ?? '';

                    $uploadedFilesData[] = [
                        'File_Path' => $gcsFilePath,
                        'Keterangan' => $note
                    ];
                }
            }

            foreach ($request->analyses as $analysisData) {
                $idDecoded = Hashids::connection('custom')->decode($analysisData['Id_Jenis_Analisa']);
                $jenisAnalisaId = isset($idDecoded[0]) ? $idDecoded[0] : null;

                $rulesNonHitung = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                    ->where('Kode_Role', 'FLM')        
                    ->select('Nilai_Kriteria', 'Keterangan_Kriteria', 'Flag_Layak')
                    ->where('Id_Jenis_Analisa', $jenisAnalisaId)
                    ->where('Flag_Aktif', 'Y')
                    ->get();

                $inputMap = []; 
                $ruleDetailsMap = []; 
                
                $standardRule = $rulesNonHitung->firstWhere('Flag_Layak', 'Y');
                $standardRangeValue = $standardRule ? (float)$standardRule->Nilai_Kriteria : null;

                foreach ($rulesNonHitung as $rule) {
                    $valFloat = (float)$rule->Nilai_Kriteria;
                    $inputMap[$rule->Keterangan_Kriteria] = $valFloat;
                    
                    // Map untuk lookup hasil inputan (Flag Layak & Label)
                    $ruleDetailsMap[(string)$valFloat] = [
                        'label' => $rule->Keterangan_Kriteria,
                        'layak' => $rule->Flag_Layak
                    ];
                }

                $noSementara = $analysisData['No_Sementara'] ?? null;
                $sumberData = (object) $analysisData;
                $idUserUntukInsert = $pengguna->UserId;  
                $isFromSementara = false;

                $isFlagKhusus = DB::table('N_LIMS_PO_Sampel')
                                ->where('No_Sampel', $sumberData->No_Po_Sampel)
                                ->where('Flag_Khusus', 'Y')
                                ->exists();
                            

                if (!$isFlagKhusus) {
                        $isAllowed = DB::table('N_EMI_LAB_Barang_Analisa')
                            ->where('Kode_Role', 'FLM')
                            ->where('Id_Jenis_Analisa', $jenisAnalisaId)
                            ->where('Id_User', $userId)
                            ->exists();

                        if (!$isAllowed) {
                            return response()->json([
                                'success' => false,
                                'status' => 403,
                                'message' => "Anda tidak memiliki akses untuk Jenis Analisa ini"
                            ], 403);
                        }
                }

                $analysisData['parameters'] = collect($analysisData['parameters'])->map(function ($param) use ($inputMap) {
                    $decoded = Hashids::connection('custom')->decode($param['Id_Quality_Control']);
                    $decodedId = isset($decoded[0]) ? (string) $decoded[0] : null;
                    $valueParameter = $param['Value_Parameter']; 

                    if (isset($inputMap[$valueParameter])) {
                        $valueParameter = $inputMap[$valueParameter];
                    }

                    return [
                        'Id_Quality_Control' => $decodedId,
                        'Value_Parameter' => $valueParameter,
                        'No_Urut' => $param['No_Urut'] ?? null,
                        'RV_INT' => $param['RV_INT'] ?? null
                    ];
                })->toArray();

                if ($noSementara) {
                    $dataSementara = DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->first();

                    if ($dataSementara) {
                        foreach ($analysisData['parameters'] as $paramFromRequest) {
                            $idDecodedNu = Hashids::connection('custom')->decode($paramFromRequest['No_Urut']);
                            $idDecodedRv = Hashids::connection('custom')->decode($paramFromRequest['RV_INT']);
                            $idNu = isset($idDecodedNu[0]) ? $idDecodedNu[0] : null;
                            $idRv = isset($idDecodedRv[0]) ? $idDecodedRv[0] : null;

                            if (empty($idNu) || empty($idRv)) {
                                return response()->json([
                                    'success' => false,
                                    'status' => 400,
                                    'message' => 'Data tidak lengkap untuk divalidasi. No_Urut atau RV_INT kosong pada data sementara.'
                                ], 400);
                            }

                            $dbParam = DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')
                                ->selectRaw('CAST(RV AS INT) AS RV_INT')
                                ->where('No_Sementara', $noSementara)
                                ->where('No_Urut', $idNu)
                                ->first();

                            if (!$dbParam) {
                                return response()->json([
                                    'success' => false,
                                    'status' => 404,
                                    'message' => 'Data parameter dengan No_Urut: ' . $paramFromRequest['No_Urut'] . ' tidak ditemukan.'
                                ], 404);
                            }

                            if ((int)$idRv !== (int)$dbParam->RV_INT) {
                                return response()->json([
                                    'success' => false,
                                    'status' => 409,
                                    'message' => 'Data sudah kedaluwarsa. Silakan refresh halaman.'
                                ], 409);
                            }
                        }

                        $isFromSementara = true;
                        $idUserUntukInsert = $dataSementara->Id_User;

                        $detailsSementara = DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')
                                                ->where('No_Sementara', $noSementara)
                                                ->get();

                        $requestParams = collect($analysisData['parameters'])->keyBy('Id_Quality_Control');

                        $mergedParameters = $detailsSementara->map(function ($dbParam) use ($requestParams) {
                            $qcId = (string) $dbParam->Id_Quality_Control;
                            $finalValue = $dbParam->Value_Parameter;

                            if ($requestParams->has($qcId) && is_null($dbParam->Value_Parameter)) {
                                $finalValue = $requestParams[$qcId]['Value_Parameter'];
                            }

                            return [
                                'Id_Quality_Control' => $qcId,
                                'Value_Parameter' => $finalValue
                            ];
                        })->toArray();

                        $sumberData = (object) [
                            'No_Po_Sampel' => $dataSementara->No_Po_Sampel,
                            'Id_Jenis_Analisa' => $jenisAnalisaId,
                            'No_Po_Multi_Sampel' => $dataSementara->No_Fak_Sub_Po,
                            'is_multi_print' => $dataSementara->Flag_Multi_QrCode,
                            'parameters' => $mergedParameters,
                            'formulas' => $analysisData['formulas'] ?? [],
                            "Id_Mesin" => $analysisData['id_mesin']
                        ];
                    }
                } else {
                    $sumberData = (object) [
                        'No_Po_Sampel' => $analysisData['No_Po_Sampel'],
                        'Id_Jenis_Analisa' => $jenisAnalisaId,
                        'No_Po_Multi_Sampel' => $analysisData['No_Po_Multi_Sampel'],
                        'is_multi_print' => $analysisData['is_multi_print'] ?? 'N',
                        'parameters' => $analysisData['parameters'],
                        'formulas' => $analysisData['formulas'] ?? [],
                        "Id_Mesin" => $analysisData['id_mesin']
                    ];
                }

                $lastNumber++;
                $newNumber = $prefix . '-' . str_pad($lastNumber, 4, '0', STR_PAD_LEFT);
                
                $payloadUjiSampleData = [];
                $payloadActivityUjiSampelHasil = [];
                $payloadUjiSampleDetailData = [];
                $payloadActiviyUjiSampelDetail = [];
                
                foreach ($sumberData->parameters as $parameter) {
                    $paramValueFloat = $this->safeFloat($parameter['Value_Parameter']);
                    $flagString = null;
                    $nilaiHasilString = null;
                    $Flag_Layak = null;

                    $checkNonPerhitungan = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                        ->where('Id_Jenis_Analisa', $sumberData->Id_Jenis_Analisa)
                        ->where('Flag_Aktif', 'Y')
                        ->where('Kode_Role', 'FLM')
                        ->get();

                    if ($checkNonPerhitungan->isNotEmpty()) {
                        $match = $checkNonPerhitungan->where('Nilai_Kriteria', $paramValueFloat)->first();
                            if ($match) {
                                $Flag_Layak = $match->Flag_Layak;
                            } else {
                                $Flag_Layak = 'T';
                            }
                    }else {
                        $Flag_Layak = 'Y';
                    } 
                    
                    $rangeAwal = $standardRangeValue;
                    $rangeAkhir = $standardRangeValue;

                    $payloadUjiSampleData[] = [
                        "No_Faktur" => $newNumber,
                        "Kode_Perusahaan" => "001",
                        "Id_Jenis_Analisa" => $sumberData->Id_Jenis_Analisa,
                        "Hasil" => $paramValueFloat,
                        "Flag_Foto" => $request->flag_foto,
                        "Flag_Perhitungan" => null,
                        "Flag_Multi_QrCode" => $sumberData->is_multi_print,
                        "No_Fak_Sub_Po" => $sumberData->No_Po_Multi_Sampel,
                        "Status" => null,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $idUserUntukInsert,
                        "No_Po_Sampel" => $sumberData->No_Po_Sampel,
                        'Status_Keputusan_Sampel' => 'menunggu',
                        'Tahapan_Ke' => 1,
                        'Id_Mesin' => $sumberData->Id_Mesin,
                        "Flag_String" => $flagString,
                        "Nilai_Hasil_String" => $nilaiHasilString,
                        "Flag_Layak" => $Flag_Layak,
                        "Range_Awal" => $rangeAwal,
                        "Range_Akhir" => $rangeAkhir
                    ];

                    $payloadActivityUjiSampelHasil[] = [
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $sumberData->No_Po_Sampel,
                        "No_Fak_Sub_Po" => $sumberData->No_Po_Multi_Sampel,
                        "Id_Jenis_Analisa" => $sumberData->Id_Jenis_Analisa,
                        "Value_Baru" => $paramValueFloat,
                        "Value_Lama" => $paramValueFloat,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $idUserUntukInsert,
                        "Status_Submit" => "Submited",
                    ];

                    $payloadUjiSampleDetailData[] = [
                        "Kode_Perusahaan" => "001",
                        "No_Faktur_Uji_Sample" => $newNumber,
                        "Id_Quality_Control" => $parameter['Id_Quality_Control'],
                        "Value_Parameter" => $paramValueFloat,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $idUserUntukInsert,
                    ];

                    $payloadActiviyUjiSampelDetail[] = [
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $sumberData->No_Po_Sampel,
                        "No_Fak_Sub_Po" => $sumberData->No_Po_Multi_Sampel,
                        "Id_Jenis_Analisa" => $sumberData->Id_Jenis_Analisa,
                        "Id_Quality_Control" => $parameter['Id_Quality_Control'],
                        "Value_Baru" => $paramValueFloat,
                        "Value_Lama" => $paramValueFloat,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $idUserUntukInsert,
                        "Status_Submit" => "Submited",
                    ];
                }

                DB::table('N_EMI_LIMS_Uji_Sampel')->insert($payloadUjiSampleData);
                DB::table('N_EMI_LIMS_Uji_Sampel_Detail')->insert($payloadUjiSampleDetailData);
                DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail')->insert($payloadActivityUjiSampelHasil);
                DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail')->insert($payloadActiviyUjiSampelDetail);

                if (!empty($uploadedFilesData)) {
                    $berkasPayload = [];
                    foreach ($uploadedFilesData as $fileData) {
                        $berkasPayload[] = [
                            'No_Faktur' => $newNumber,
                            'No_Sampel' => $analysisData['No_Po_Sampel'],
                            'Berkas_Key' => Str::random(32),
                            'File_Path' => $fileData['File_Path'],
                            'Keterangan' => $fileData['Keterangan'] 
                        ];
                    }
                    DB::table('N_EMI_LIMS_Berkas_Uji_Lab')->insert($berkasPayload);
                }

                if ($isFromSementara) {
                    DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')->where('No_Sementara', $noSementara)->delete();
                    DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->delete();
                }

                $results[] = [
                    'generated_no_faktur' => $newNumber,
                    'status' => $isFromSementara ? 'temporary_table' : 'request',
                ];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => "Data berhasil diproses dan disimpan.",
                'results' => $results 
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            if (!empty($uploadedFilesData)) {
                foreach ($uploadedFilesData as $fileData) {
                    if (Storage::disk('gcs')->exists($fileData['File_Path'])) {
                        Storage::disk('gcs')->delete($fileData['File_Path']);
                        Log::channel('FormulatorTrialSampelController')->info("🗑️ [GCS ROLLBACK] File dihapus karena proses DB gagal: {$fileData['File_Path']}");
                    }
                }
            }
            Log::channel('FormulatorTrialSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan"
            ], 500);
        }
    }
    
    public function storeMultiQrCodeNotRumusResampling(Request $request)
    {
        if (is_string($request->input('analyses'))) {
            $request->merge([
                'analyses' => json_decode($request->input('analyses'), true)
            ]);
        }

        $request->validate([
            'analyses' => 'required|array|min:1',
            'analyses.*.No_Po_Sampel' => 'required|string',
            'analyses.*.Id_Jenis_Analisa' => 'required|string',
            'analyses.*.No_Sementara' => 'nullable|string',
            'analyses.*.is_multi_print' => 'required|string',
            'analyses.*.No_Po_Multi_Sampel' => 'nullable|string',
            'analyses.*.parameters' => 'required|array|min:1',
            'analyses.*.parameters.*.Id_Quality_Control' => 'required',
            'analyses.*.parameters.*.Value_Parameter' => 'required',
        ], [
            'analyses.required' => 'Tidak ada data analisis yang dikirim.',
            'analyses.*.parameters.required' => 'Parameter tidak boleh kosong untuk setiap baris.',
        ]);
        
        DB::beginTransaction();

        $berkasInsertsTemplate = []; 
        $oldFilesToDeleteGcs = [];   
        
        try {
            $results = [];
            $pengguna = Auth::user();
            $userId = $pengguna->UserId;

            $userExists = DB::table('N_EMI_LAB_Users')->where('UserId', $userId)->exists();

            if (!$userExists) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "User dengan ID $userId tidak ditemukan di sistem."
                ], 404);
            }

            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow; 
            $tanggalSqlServer = date('Y-m-d', strtotime($dt)); 
            $jamSqlServer = date('H:i:s', strtotime($dt));
        
            $currentMonth = date('m');
            $currentYear = date('y');
            $prefix = 'FUS' . $currentMonth . $currentYear;
            $prefixLength = strlen($prefix);

            $lastNumber = DB::table('N_EMI_LIMS_Uji_Sampel')
                ->where('No_Faktur', 'like', $prefix . '-%')
                ->lockForUpdate() 
                ->selectRaw("MAX(CAST(SUBSTRING(No_Faktur, ? + 2, 10) AS INT)) as max_number", [$prefixLength])
                ->value('max_number') ?? 0;

            $firstAnalysis = $request->analyses[0];
            $idDecoded = Hashids::connection('custom')->decode($firstAnalysis['Id_Jenis_Analisa']);
            $jenisAnalisaId = isset($idDecoded[0]) ? $idDecoded[0] : null;
            $noPoSampel = $firstAnalysis['No_Po_Sampel'];

            $oldBerkasRecords = DB::table('N_EMI_LIMS_Berkas_Uji_Lab')
                    ->where('No_Sampel', $noPoSampel)
                    ->get();

                foreach ($oldBerkasRecords as $berkas) {
                    if (!empty($berkas->File_Path)) {
                        $oldFilesToDeleteGcs[] = $berkas->File_Path; // Simpan path untuk dihapus di GCS nanti
                    }
                }
            if ($oldBerkasRecords->count() > 0) {
                DB::table('N_EMI_LIMS_Berkas_Uji_Lab')
                    ->where('No_Sampel', $noPoSampel)
                    ->delete();
            }

            if ($request->hasFile('photos') && $request->flag_foto === 'Y') {
                $photos = $request->file('photos');
                $notes = $request->input('notes', []);

                foreach ($photos as $index => $file) {
                    $extension = $file->getClientOriginalExtension() ?: 'png';
                    $fileName = 'formulator_' . Str::random(5) . '_' . time() . '_' . $index . '.' . $extension;
                    $gcsFilePath = 'berkas/formulator/' . $fileName;
                    Storage::disk('gcs')->put($gcsFilePath, file_get_contents($file));
                    $note = isset($notes[$index]) && !empty($notes[$index]) ? $notes[$index] : '-';
                    $berkasInsertsTemplate[] = [
                        'No_Sampel' => $noPoSampel,
                        'Berkas_Key' => Str::random(32),
                        'File_Path' => $gcsFilePath,
                        'Keterangan' => $note
                    ];
                }
            }

            $dataResampling = DB::table('N_EMI_LIMS_Uji_Sampel_Resampling_Log')
                ->where('No_Po_Sampel', $firstAnalysis['No_Po_Sampel'])
                ->where('Id_Jenis_Analisa', $jenisAnalisaId)
                ->whereNull('Flag_Selesai_Resampling') 
                ->orderBy('Tanggal', 'desc') 
                ->first();

            if (!$dataResampling) {
                throw new \Exception("Data Resampling Aktif tidak ditemukan untuk Sampel ini. Pastikan proses resampling sudah dibuat.");
            }

            $fixedNoFakSubPo = $dataResampling->No_Sampel_Resampling;
            $fixedTahapanKe = $dataResampling->Tahapan_Ke;

            $payloadActivityUjiSampel = [
                'Kode_Perusahaan' => '001',
                'No_Po_Sampel' => $firstAnalysis['No_Po_Sampel'],
                'No_Fak_Sub_Po' => $fixedNoFakSubPo,
                'Jenis_Aktivitas' => 'save_submit',
                'Keterangan' => $pengguna->Nama . ' Berhasil Mengirimkan Data Analisa',
                'Id_User' => $pengguna->UserId,
                'Tanggal' => $tanggalSqlServer,
                'Jam' => $jamSqlServer,
                'Id_Jenis_Analisa' => $jenisAnalisaId 
            ];

            $idLogActivity = DB::table('N_EMI_LIMS_Activity_Uji_Sampel')->insertGetId($payloadActivityUjiSampel, 'Id_Log_Activity');

            $jenisAnalisaRecord = DB::table('N_EMI_LAB_Jenis_Analisa')
                ->select('Kode_Analisa', 'Flag_Perhitungan')
                ->where('id', $jenisAnalisaId)
                ->where('Kode_Role', 'FLM')
                ->first();

            $isPerhitungan = $jenisAnalisaRecord && $jenisAnalisaRecord->Flag_Perhitungan === 'Y';
            $flagPerhitunganVal = $isPerhitungan ? 'Y' : null;
           
            foreach ($request->analyses as $analysisData) {
                $noSementara = $analysisData['No_Sementara'] ?? null;
                $sumberData = (object) $analysisData;
                $isFromSementara = false;

                $poData = DB::table('N_LIMS_PO_Sampel')
                    ->where('No_Sampel', $sumberData->No_Po_Sampel)
                    ->select('Kode_Barang', 'Flag_Khusus')
                    ->first();
                
                $kodeBarang = $poData->Kode_Barang ?? '';
                $isFlagKhusus = ($poData->Flag_Khusus ?? null) === 'Y';

                if (!$isFlagKhusus) {
                        $isAllowed = DB::table('N_EMI_LAB_Barang_Analisa')
                            ->where('Id_Jenis_Analisa', $jenisAnalisaId)
                            ->where('Kode_Role', 'FLM')
                            ->where('Id_User', $userId)
                            ->exists();

                        if (!$isAllowed) {
                            return response()->json([
                                'success' => false,
                                'status' => 403,
                                'message' => "Anda tidak memiliki akses untuk Jenis Analisa ini"
                            ], 403);
                        }
                }

                $analysisData['parameters'] = collect($analysisData['parameters'])->map(function ($param) {
                    $decoded = Hashids::connection('custom')->decode($param['Id_Quality_Control']);
                    $decodedId = isset($decoded[0]) ? (string) $decoded[0] : null;
                    $valueParameter = $param['Value_Parameter']; 
                    return [
                        'Id_Quality_Control' => $decodedId,
                        'Value_Parameter' => $valueParameter,
                        'No_Urut' => $param['No_Urut'] ?? null,
                        'RV_INT' => $param['RV_INT'] ?? null
                    ];
                })->toArray();

                if ($noSementara) {
                    $dataSementara = DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->first();

                    if ($dataSementara) {
                        foreach ($analysisData['parameters'] as $paramFromRequest) {
                            $idDecoded = Hashids::connection('custom')->decode($paramFromRequest['No_Urut']);
                            $idDecodedRv = Hashids::connection('custom')->decode($paramFromRequest['RV_INT']);
                            $idNu = isset($idDecoded[0]) ? $idDecoded[0] : null;
                            $idRv = isset($idDecodedRv[0]) ? $idDecodedRv[0] : null;

                            if (empty($idNu) || empty($idRv)) {
                                return response()->json([
                                    'success' => false,
                                    'status' => 400,
                                    'message' => 'Data tidak lengkap untuk divalidasi. No_Urut atau RV_INT kosong pada data sementara.'
                                ], 400);
                            }

                            $dbParam = DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')
                                ->selectRaw('CAST(RV AS INT) AS RV_INT')
                                ->where('No_Sementara', $noSementara)
                                ->where('No_Urut', $idNu)
                                ->first();

                            if (!$dbParam) {
                                return response()->json([
                                    'success' => false,
                                    'status' => 404,
                                    'message' => 'Data parameter dengan No_Urut: ' . $paramFromRequest['No_Urut'] . ' tidak ditemukan.'
                                ], 404);
                            }

                            if ((int)$idRv !== (int)$dbParam->RV_INT) {
                                return response()->json([
                                    'success' => false,
                                    'status' => 409,
                                    'message' => 'Data sudah kedaluwarsa. Silakan refresh halaman.'
                                ], 409);
                            }
                        }

                        $isFromSementara = true;

                        $detailsSementara = DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')
                                                    ->where('No_Sementara', $noSementara)
                                                    ->get();

                        $requestParams = collect($analysisData['parameters'])->keyBy('Id_Quality_Control');

                        $mergedParameters = $detailsSementara->map(function ($dbParam) use ($requestParams) {
                            $qcId = (string) $dbParam->Id_Quality_Control;
                            $finalValue = $dbParam->Value_Parameter;

                            if ($requestParams->has($qcId) && is_null($dbParam->Value_Parameter)) {
                                $finalValue = $requestParams[$qcId]['Value_Parameter'];
                            }

                            return [
                                'Id_Quality_Control' => $qcId,
                                'Value_Parameter' => $finalValue
                            ];
                        })->toArray();

                        $sumberData = (object) [
                            'No_Po_Sampel' => $dataSementara->No_Po_Sampel,
                            'Id_Jenis_Analisa' => $jenisAnalisaId, 
                            'No_Po_Multi_Sampel' => $dataSementara->No_Fak_Sub_Po,
                            'is_multi_print' => $dataSementara->Flag_Multi_QrCode,
                            'parameters' => $mergedParameters,
                            'formulas' => $analysisData['formulas'] ?? [],
                            'id_mesin' => $analysisData['id_mesin']
                        ];
                    }
                } else {
                    $sumberData = (object) [
                        'No_Po_Sampel' => $analysisData['No_Po_Sampel'],
                        'Id_Jenis_Analisa' => $jenisAnalisaId, 
                        'No_Po_Multi_Sampel' => $analysisData['No_Po_Multi_Sampel'],
                        'is_multi_print' => $analysisData['is_multi_print'] ?? null,
                        'parameters' => $analysisData['parameters'],
                        'formulas' => $analysisData['formulas'] ?? [],
                        "id_mesin" => $analysisData['id_mesin']
                    ];
                }

                $lastNumber++;
                $newNumber = $prefix . '-' . str_pad($lastNumber, 4, '0', STR_PAD_LEFT);
                
                $payloadUjiSampleData = [];
                $payloadActivityUjiSampelHasil = [];
                $payloadUjiSampleDetailData = [];
                $payloadActiviyUjiSampelDetail = [];

                foreach ($sumberData->parameters as $parameter) {
                    $paramValueFloat = $this->safeFloat($parameter['Value_Parameter']);

                    $flagString = null;
                    $nilaiHasilString = null;
                    
                    $rangeAwal = null;
                    $rangeAkhir = null;
                    $flagLayak = 'Y';

                    if ($isPerhitungan) {
                        $standarRentang = DB::table('N_EMI_LAB_Standar_Rentang')
                            ->where('Kode_Role', 'FLM')
                            ->where('Kode_Perusahaan', '001')
                            ->where('Id_Jenis_Analisa', $jenisAnalisaId)
                            ->where('Kode_Barang', $kodeBarang)
                            ->where('Id_Master_Mesin', $analysisData['id_mesin'])
                            ->first();

                        if ($standarRentang) {
                            $rangeAwal = $standarRentang->Range_Awal;
                            $rangeAkhir = $standarRentang->Range_Akhir;

                            if ($paramValueFloat >= $rangeAwal && $paramValueFloat <= $rangeAkhir) {
                                $flagLayak = 'Y';
                            } else {
                                $flagLayak = 'T';
                            }
                        } else {
                            $rangeAwal = null;
                            $rangeAkhir = null;
                            $flagLayak = 'Y';
                        }
                    } else {
                        $standarNon = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                            ->where('Kode_Perusahaan', '001')
                            ->where('Kode_Role', 'FLM')
                            ->where('Id_Jenis_Analisa', $jenisAnalisaId)
                            ->where('Nilai_Kriteria', $paramValueFloat)
                            ->where('Flag_Aktif', 'Y')
                            ->first();

                        if ($standarNon) {
                            $rangeAwal = $standarNon->Nilai_Kriteria;
                            $rangeAkhir = $standarNon->Nilai_Kriteria;
                            $flagLayak = $standarNon->Flag_Layak;
                            $flagString = ($standarNon->Flag_Layak == 'Y') ? 'Y' : 'T';
                            $nilaiHasilString = $standarNon->Keterangan_Kriteria;
                        } else {
                            $rangeAwal = null;
                            $rangeAkhir = null;
                            $flagLayak = 'Y';
                            $flagString = 'T';
                        }
                    }

                    $payloadUjiSampleData[] = [
                        "No_Faktur" => $newNumber,
                        "Kode_Perusahaan" => "001",
                        "Flag_Foto" => $request->flag_foto,
                        "Id_Jenis_Analisa" => $jenisAnalisaId,
                        "Hasil" => $paramValueFloat,
                        "Flag_Perhitungan" => $flagPerhitunganVal,
                        "Flag_Multi_QrCode" => $analysisData['is_multi_print'] ?? 'N', 
                        "No_Fak_Sub_Po" => $fixedNoFakSubPo, 
                        "Status" => null,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $pengguna->UserId,
                        "No_Po_Sampel" => $firstAnalysis['No_Po_Sampel'],
                        'Status_Keputusan_Sampel' => 'menunggu',
                        'Tahapan_Ke' => $fixedTahapanKe, 
                        'Id_Mesin' => $analysisData['id_mesin'], 
                        "Flag_String" => $flagString,
                        "Nilai_Hasil_String" => $nilaiHasilString,
                        "Range_Awal" => $rangeAwal,
                        "Range_Akhir" => $rangeAkhir,
                        "Flag_Layak" => $flagLayak
                    ];

                    $payloadActivityUjiSampelHasil[] = [
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $firstAnalysis['No_Po_Sampel'],
                        "No_Fak_Sub_Po" => $fixedNoFakSubPo, 
                        "Id_Jenis_Analisa" => $jenisAnalisaId,
                        "Value_Baru" => $paramValueFloat,
                        "Value_Lama" => $paramValueFloat,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $pengguna->UserId,
                        "Status_Submit" => "Submited Resampling",
                    ];

                    $payloadUjiSampleDetailData[] = [
                        "Kode_Perusahaan" => "001",
                        "No_Faktur_Uji_Sample" => $newNumber,
                        "Id_Quality_Control" => $parameter['Id_Quality_Control'],
                        "Value_Parameter" => $paramValueFloat,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $pengguna->UserId,
                    ];

                    $payloadActiviyUjiSampelDetail[] = [
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $firstAnalysis['No_Po_Sampel'],
                        "No_Fak_Sub_Po" => $fixedNoFakSubPo, 
                        "Id_Jenis_Analisa" => $jenisAnalisaId,
                        "Id_Quality_Control" => $parameter['Id_Quality_Control'],
                        "Value_Baru" => $paramValueFloat,
                        "Value_Lama" => $paramValueFloat,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $pengguna->UserId,
                        "Status_Submit" => "Submited Resampling",
                    ];
                }

                DB::table('N_EMI_LIMS_Uji_Sampel')->insert($payloadUjiSampleData);
                DB::table('N_EMI_LIMS_Uji_Sampel_Detail')->insert($payloadUjiSampleDetailData);
                DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail')->insert($payloadActivityUjiSampelHasil);
                DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail')->insert($payloadActiviyUjiSampelDetail);
                if (!empty($berkasInsertsTemplate)) {
                    $berkasToInsert = array_map(function($item) use ($newNumber) {
                        $item['No_Faktur'] = $newNumber;
                        $item['Berkas_Key'] = Str::random(32); // Pastikan key unik per baris (jika multi analisis)
                        return $item;
                    }, $berkasInsertsTemplate);

                    DB::table('N_EMI_LIMS_Berkas_Uji_Lab')->insert($berkasToInsert);
                }
                if ($isFromSementara) {
                    DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')->where('No_Sementara', $noSementara)->delete();
                    DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->delete();
                }

                DB::table("N_EMI_LIMS_Uji_Sampel_Resampling_Log")
                    ->where('Id_Resampling', $dataResampling->Id_Resampling) 
                    ->update([
                        'Flag_Selesai_Resampling' => 'Y'
                    ]);

                $results[] = [
                    'generated_no_faktur' => $newNumber,
                    'status' => $isFromSementara ? 'temporary_table' : 'request',
                ];
            }

            DB::commit();

            if (!empty($oldFilesToDeleteGcs)) {
                $uniqueFilesToDelete = array_unique($oldFilesToDeleteGcs);
                foreach ($uniqueFilesToDelete as $oldPath) {
                    if (Storage::disk('gcs')->exists($oldPath)) {
                        Storage::disk('gcs')->delete($oldPath);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => "Data berhasil diproses dan disimpan.",
                'results' => $results 
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            if (!empty($berkasInsertsTemplate)) {
                foreach ($berkasInsertsTemplate as $berkasItem) {
                    if (Storage::disk('gcs')->exists($berkasItem['File_Path'])) {
                        Storage::disk('gcs')->delete($berkasItem['File_Path']);
                    }
                }
            }
            Log::channel('FormulatorTrialSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan". $e->getMessage()
            ], 500);
        }
    }
  
    public function storeMultiRumusNotMultiQrCode(Request $request)
    {
        
        if (is_string($request->input('analyses'))) {
            $request->merge([
                'analyses' => json_decode($request->input('analyses'), true)
            ]);
        }

        $request->validate([
            'analyses' => 'required|array|min:1',
            'analyses.*.No_Po_Sampel' => 'required|string',
            'analyses.*.Id_Jenis_Analisa' => 'required|string',
            'analyses.*.No_Sementara' => 'nullable|string',
            'analyses.*.parameters' => 'required|array|min:1',
            'analyses.*.parameters.*.Id_Quality_Control' => 'required',
            'analyses.*.parameters.*.Value_Parameter' => 'required|numeric',
        ], [
            'analyses.required' => 'Tidak ada data analisis yang dikirim.',
            'analyses.*.parameters.required' => 'Parameter tidak boleh kosong untuk setiap baris.',
        ]);
       
        DB::beginTransaction();

        try {
            $results = [];
           
            $pengguna = Auth::user();
            $userId = $pengguna->UserId;

            $userExists = DB::table('N_EMI_LAB_Users')->where('UserId', $userId)->exists();

            if (!$userExists) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "User dengan ID $userId tidak ditemukan di sistem."
                ], 404);
            }

            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow; 
            $tanggalSqlServer = date('Y-m-d', strtotime($dt)); 
            $jamSqlServer = date('H:i:s', strtotime($dt));

            $currentMonth = date('m');
            $currentYear = date('y');
            $prefix = 'FUS' . $currentMonth . $currentYear;
            $prefixLength = strlen($prefix);

            $lastNumber = DB::table('N_EMI_LIMS_Uji_Sampel')
                ->where('No_Faktur', 'like', $prefix . '-%')
                ->lockForUpdate() 
                ->selectRaw("MAX(CAST(SUBSTRING(No_Faktur, ? + 2, 10) AS INT)) as max_number", [$prefixLength])
                ->value('max_number') ?? 0;

            $firstAnalysis = $request->analyses[0];
            $idDecoded = Hashids::connection('custom')->decode($firstAnalysis['Id_Jenis_Analisa']);
            $jenisAnalisa = isset($idDecoded[0]) ? $idDecoded[0] : null;

            $payloadActivityUjiSampel = [
                'Kode_Perusahaan' => '001',
                'No_Po_Sampel' => $firstAnalysis['No_Po_Sampel'],
                'Jenis_Aktivitas' => 'save_submit',
                'Keterangan' => $pengguna->Nama . ' Berhasil Mengirimkan Data Analisa',
                'Id_User' => $pengguna->UserId,
                'Tanggal' => $tanggalSqlServer,
                'Jam' => $jamSqlServer,
                'Id_Jenis_Analisa' => $jenisAnalisa
            ];

            $idLogActivity = DB::table('N_EMI_LIMS_Activity_Uji_Sampel')->insertGetId($payloadActivityUjiSampel, 'Id_Log_Activity');
            $gcsFilePath = null;
            if ($request->hasFile('photo_data') && $request->flag_foto === 'Y') {
                $file = $request->file('photo_data');
                $extension = $file->getClientOriginalExtension() ?: 'png';
                
                // Hitung ukuran file yang baru saja tiba di server (sebelum masuk GCS)
                $receivedSizeMB = number_format($file->getSize() / 1048576, 2);
                Log::channel('FormulatorTrialSampelController')->info("📥 [UPLOAD FOTO] Menerima file murni dari Frontend. Ukuran: {$receivedSizeMB} MB");

                $fileName = 'formulator_' . Str::random(5) . '_' . time() . '.' . $extension;
                $gcsFilePath = 'berkas/formulator/' . $fileName;
                
                // Lempar ke GCS
                Storage::disk('gcs')->put($gcsFilePath, file_get_contents($file));

                // Hitung ulang ukuran file yang sudah sukses nongkrong di GCS
                $gcsFileSize = Storage::disk('gcs')->size($gcsFilePath);
                $gcsSizeMB = number_format($gcsFileSize / 1048576, 2);
                Log::channel('FormulatorTrialSampelController')->info("☁️ [GCS UPLOAD] Berhasil disimpan ke Cloud. Ukuran final: {$gcsSizeMB} MB | Path: {$gcsFilePath}");
            }
            foreach ($request->analyses as $analysisData) {
                $noSementara = $analysisData['No_Sementara'] ?? null;
                $sumberData = (object) $analysisData;
                $idUserUntukInsert = $pengguna->UserId;   
                $isFromSementara = false;
                $isFlagKhusus = DB::table('N_LIMS_PO_Sampel')
                        ->where('No_Sampel', $sumberData->No_Po_Sampel)
                        ->where('Flag_Khusus', 'Y')
                        ->exists();

                if (!$isFlagKhusus) {
                        $isAllowed = DB::table('N_EMI_LAB_Barang_Analisa')
                            ->where("Kode_Role", 'FLM')
                            ->where('Id_Jenis_Analisa', $jenisAnalisa)
                            ->where('Id_User', $userId)
                            ->exists();

                        if (!$isAllowed) {
                            return response()->json([
                                'success' => false,
                                'status' => 403,
                                'message' => "Anda tidak memiliki akses untuk Jenis Analisa ini"
                            ], 403);
                        }
                }

                $analysisData['parameters'] = collect($analysisData['parameters'])->map(function ($param) {
                    $decoded = Hashids::connection('custom')->decode($param['Id_Quality_Control']);
                    $decodedId = isset($decoded[0]) ? (string) $decoded[0] : null;
                    return [
                        'Id_Quality_Control' => $decodedId,
                        'Value_Parameter' => $param['Value_Parameter'],
                        'No_Urut' => $param['No_Urut'] ?? null,
                        'RV_INT' => $param['RV_INT'] ?? null
                    ];
                })->toArray();

                if ($noSementara) {
                    $dataSementara = DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->first();

                    if ($dataSementara) {
                        foreach ($analysisData['parameters'] as $paramFromRequest) {
                            $idDecoded = Hashids::connection('custom')->decode($paramFromRequest['No_Urut']);
                            $idDecodedRv = Hashids::connection('custom')->decode($paramFromRequest['RV_INT']);
                            $idNu = isset($idDecoded[0]) ? $idDecoded[0] : null;
                            $idRv = isset($idDecodedRv[0]) ? $idDecodedRv[0] : null;

                            if (empty($idNu) || empty($idRv)) {
                                return response()->json([
                                    'success' => false,
                                    'status' => 400,
                                    'message' => 'Data tidak lengkap untuk divalidasi. No_Urut atau RV_INT kosong pada data sementara.'
                                ], 400);
                            }

                            $dbParam = DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')
                                ->selectRaw('CAST(RV AS INT) AS RV_INT')
                                ->where('No_Sementara', $noSementara)
                                ->where('No_Urut', $idNu)
                                ->first();

                            if (!$dbParam) {
                                return response()->json([
                                    'success' => false,
                                    'status' => 404,
                                    'message' => 'Data parameter dengan No_Urut: ' . $paramFromRequest['No_Urut'] . ' tidak ditemukan.'
                                ], 404);
                            }

                            if ((int)$idRv !== (int)$dbParam->RV_INT) {
                                return response()->json([
                                    'success' => false,
                                    'status' => 409,
                                    'message' => 'Data sudah kedaluwarsa. Silakan refresh halaman.'
                                ], 409);
                            }
                        }

                        $isFromSementara = true;
                        $idUserUntukInsert = $dataSementara->Id_User;

                        $detailsSementara = DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')
                                                    ->where('No_Sementara', $noSementara)
                                                    ->get();

                        $requestParams = collect($analysisData['parameters'])->keyBy('Id_Quality_Control');

                        $mergedParameters = $detailsSementara->map(function ($dbParam) use ($requestParams) {
                            $qcId = (string) $dbParam->Id_Quality_Control;
                            $finalValue = $dbParam->Value_Parameter;

                            if ($requestParams->has($qcId) && is_null($dbParam->Value_Parameter)) {
                                $finalValue = $requestParams[$qcId]['Value_Parameter'];
                            }

                            return [
                                'Id_Quality_Control' => $qcId,
                                'Value_Parameter' => $finalValue
                            ];
                        })->toArray();

                        $sumberData = (object) [
                            'No_Po_Sampel' => $dataSementara->No_Po_Sampel,
                            'Id_Jenis_Analisa' => $jenisAnalisa,
                            'is_multi_print' => $dataSementara->Flag_Multi_QrCode,
                            'parameters' => $mergedParameters,
                            'formulas' => $analysisData['formulas'] ?? [],
                            'Id_Mesin' => $analysisData['id_mesin'] ?? [],
                        ];
                    }
                } else {
                    $sumberData = (object) [
                        'No_Po_Sampel' => $analysisData['No_Po_Sampel'],
                        'Id_Jenis_Analisa' => $jenisAnalisa,
                        'is_multi_print' => $analysisData['is_multi_print'] ?? 'N',
                        'parameters' => $analysisData['parameters'],
                        'formulas' => $analysisData['formulas'] ?? [],
                        'Id_Mesin' => $analysisData['id_mesin'] ?? [],
                    ];
                }

                $lastNumber++;
                $newNumber = $prefix . '-' . str_pad($lastNumber, 4, '0', STR_PAD_LEFT);
                $formulas = DB::table('N_EMI_LAB_Perhitungan')
                            ->where("Kode_Role", 'FLM')
                            ->where('Id_Jenis_Analisa', $sumberData->Id_Jenis_Analisa)
                            ->get();

                $parameterValues = collect($sumberData->parameters)->pluck('Value_Parameter', 'Id_Quality_Control');

                $calculatedResults = [];
                foreach ($formulas as $formula) {
                    $encodedFormula = $formula->Rumus;

                    preg_match_all('/\[(\d+)\]/', $encodedFormula, $matches);
                    if (!empty($matches[1])) {
                        foreach ($matches[1] as $originalId) {
                            $encoded = Hashids::connection('custom')->encode($originalId);
                            $encodedFormula = str_replace("[$originalId]", "[$encoded]", $encodedFormula);
                        }
                    }

                    $formula->Rumus = $encodedFormula;

                    $formulaFromRequest = collect($sumberData->formulas)->firstWhere('Rumus', $formula->Rumus);
                    $hasilDariRequest = $formulaFromRequest['Hasil_Perhitungan'] ?? null;
                    $rangeAwal = $formulaFromRequest['Range_Awal'] ?? null;
                    $rangeAkhir = $formulaFromRequest['Range_Akhir'] ?? null;

                    $resultValue = $hasilDariRequest ?: $this->calculateFormulaServerSide($formula->Rumus, $parameterValues, 0);

                    $calculatedResults[] = [
                        'Id_Perhitungan' => $formula->id,
                        'Id_Jenis_Analisa' => $formula->Id_Jenis_Analisa,
                        'Hasil_Perhitungan' => $resultValue,
                        'Range_Awal' => $rangeAwal,
                        'Range_Akhir' => $rangeAkhir
                    ];
                }

            
                $payloadUjiSampleData = [];
                $payloadActivityUjiSampelHasil = [];
                foreach ($calculatedResults as $result) {
                    $hasilFloat = $this->safeFloat($result['Hasil_Perhitungan']);
                    $RentangAwal = $result['Range_Awal'];
                    $Flag_Layak = null;

                    $checkNonPerhitungan = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                        ->where("Kode_Role", 'FLM')
                        ->where('Id_Jenis_Analisa', $result['Id_Jenis_Analisa'])
                        ->where('Flag_Aktif', 'Y')
                        ->get();

                    if ($checkNonPerhitungan->isNotEmpty()) {
                        $match = $checkNonPerhitungan->where('Nilai_Kriteria', $hasilFloat)->first();
                        
                        if ($match) {
                            $Flag_Layak = $match->Flag_Layak;
                        } else {
                            $Flag_Layak = 'T';
                        }
                    } else {
                        if (!is_null($RentangAwal) && $hasilFloat < (float)$RentangAwal) {
                            $Flag_Layak = 'T';
                        } else {
                            $Flag_Layak = 'Y';
                        }
                    }

                    $payloadUjiSampleData[] = [
                        "No_Faktur" => $newNumber,
                        "Kode_Perusahaan" => "001",
                        "Id_Jenis_Analisa" => $result['Id_Jenis_Analisa'],
                        "Id_Perhitungan" => $result['Id_Perhitungan'],
                        "Flag_Foto" => $request->flag_foto,
                        "Hasil" => $hasilFloat,
                        "Flag_Perhitungan" => 'Y',
                        "Flag_Multi_QrCode" => null,
                        "Status" => null,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        'Tahapan_Ke' => 1,
                        'Status_Keputusan_Sampel' => 'menunggu',
                        "Id_User" => $idUserUntukInsert,
                        'Flag_Layak' => $Flag_Layak,
                        "No_Po_Sampel" => $sumberData->No_Po_Sampel,
                        "Id_Mesin" => $sumberData->Id_Mesin,
                        "Range_Awal" => $result['Range_Awal'],
                        "Range_Akhir" => $result['Range_Akhir'],
                    ];

                    $payloadActivityUjiSampelHasil[] = [
                        "Kode_Perusahaan" => "001",
                        "Id_Perhitungan" => $result['Id_Perhitungan'],
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $sumberData->No_Po_Sampel,
                        "Id_Jenis_Analisa" => $sumberData->Id_Jenis_Analisa,
                        "Value_Baru" => $hasilFloat,
                        "Value_Lama" => $hasilFloat,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $idUserUntukInsert,
                        "Status_Submit" => "Submited",
                    ];
                }

                $payloadUjiSampleDetailData = [];
                $payloadActiviyUjiSampelDetail = [];
                foreach ($sumberData->parameters as $parameter) {
                    $paramValueFloat =$this->safeFloat($parameter['Value_Parameter']);

                    $payloadUjiSampleDetailData[] = [
                        "Kode_Perusahaan" => "001",
                        "No_Faktur_Uji_Sample" => $newNumber,
                        "Id_Quality_Control" => $parameter['Id_Quality_Control'],
                        "Value_Parameter" => $paramValueFloat,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $idUserUntukInsert,
                    ];

                    $payloadActiviyUjiSampelDetail[] = [
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $sumberData->No_Po_Sampel,
                        "Id_Jenis_Analisa" => $sumberData->Id_Jenis_Analisa,
                        "Id_Quality_Control" => $parameter['Id_Quality_Control'],
                        "Value_Baru" => $paramValueFloat,
                        "Value_Lama" => $paramValueFloat,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $idUserUntukInsert,
                        "Status_Submit" => "Submited",
                    ];
                }

                DB::table('N_EMI_LIMS_Uji_Sampel')->insert($payloadUjiSampleData);
                DB::table('N_EMI_LIMS_Uji_Sampel_Detail')->insert($payloadUjiSampleDetailData);
                DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail')->insert($payloadActivityUjiSampelHasil);
                DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail')->insert($payloadActiviyUjiSampelDetail);

                if ($gcsFilePath) {
                    DB::table('N_EMI_LIMS_Berkas_Uji_Lab')->insert([
                        'No_Faktur' => $newNumber,
                        'No_Sampel' => $sumberData->No_Po_Sampel,
                        'Berkas_Key' => Str::random(32),
                        'File_Path' => $gcsFilePath
                    ]);
                }

                if ($isFromSementara) {
                    DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')->where('No_Sementara', $noSementara)->delete();
                    DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->delete();
                }

                $results[] = [
                    'generated_no_faktur' => $newNumber,
                    'status' => $isFromSementara ? 'temporary_table' : 'request',
                ];
            }
            DB::commit();
            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => "Data berhasil diproses dan disimpan.",
                'results' => $results 
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($gcsFilePath) && Storage::disk('gcs')->exists($gcsFilePath)) {
                Storage::disk('gcs')->delete($gcsFilePath);
            }
            Log::channel('FormulatorTrialSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan"
            ], 500);
        }
    }

    public function storeMultiRumusNotMultiQrCodeResampling(Request $request)
    {
        if (is_string($request->input('analyses'))) {
            $request->merge([
                'analyses' => json_decode($request->input('analyses'), true)
            ]);
        }

        $request->validate([
            'analyses' => 'required|array|min:1',
            'analyses.*.No_Po_Sampel' => 'required|string',
            'analyses.*.Id_Jenis_Analisa' => 'required|string',
            'analyses.*.No_Sementara' => 'nullable|string',
            'analyses.*.parameters' => 'required|array|min:1',
            'analyses.*.parameters.*.Id_Quality_Control' => 'required',
            'analyses.*.parameters.*.Value_Parameter' => 'required|numeric',
        ], [
            'analyses.required' => 'Tidak ada data analisis yang dikirim.',
            'analyses.*.parameters.required' => 'Parameter tidak boleh kosong untuk setiap baris.',
        ]);
       
        DB::beginTransaction();

        try {
            $results = [];
            $oldFilesToDeleteGcs = [];
            $pengguna = Auth::user();
            $userId = $pengguna->UserId;

            $userExists = DB::table('N_EMI_LAB_Users')->where('UserId', $userId)->exists();

            if (!$userExists) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "User dengan ID $userId tidak ditemukan di sistem."
                ], 404);
            }

            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow; 
            $tanggalSqlServer = date('Y-m-d', strtotime($dt)); 
            $jamSqlServer = date('H:i:s', strtotime($dt));

            $currentMonth = date('m');
            $currentYear = date('y');
            $prefix = 'FUS' . $currentMonth . $currentYear;
            $prefixLength = strlen($prefix);

            $lastNumber = DB::table('N_EMI_LIMS_Uji_Sampel')
                ->where('No_Faktur', 'like', $prefix . '-%')
                ->lockForUpdate() 
                ->selectRaw("MAX(CAST(SUBSTRING(No_Faktur, ? + 2, 10) AS INT)) as max_number", [$prefixLength])
                ->value('max_number') ?? 0;

            $firstAnalysis = $request->analyses[0];
            $idDecoded = Hashids::connection('custom')->decode($firstAnalysis['Id_Jenis_Analisa']);
            $jenisAnalisa = isset($idDecoded[0]) ? $idDecoded[0] : null;

            $payloadActivityUjiSampel = [
                'Kode_Perusahaan' => '001',
                'No_Po_Sampel' => $firstAnalysis['No_Po_Sampel'],
                'Jenis_Aktivitas' => 'save_submit',
                'Keterangan' => $pengguna->Nama . ' Berhasil Mengirimkan Data Analisa',
                'Id_User' => $pengguna->UserId,
                'Tanggal' => $tanggalSqlServer,
                'Jam' => $jamSqlServer,
                'Id_Jenis_Analisa' => $jenisAnalisa
            ];

            $idLogActivity = DB::table('N_EMI_LIMS_Activity_Uji_Sampel')->insertGetId($payloadActivityUjiSampel, 'Id_Log_Activity');

            $gcsFilePath = null;
            if ($request->hasFile('photo_data') && $request->flag_foto === 'Y') {
                $file = $request->file('photo_data');
                $extension = $file->getClientOriginalExtension() ?: 'png';
                $receivedSizeMB = number_format($file->getSize() / 1048576, 2);
                Log::channel('FormulatorTrialSampelController')->info("📥 [UPLOAD FOTO] Menerima file murni dari Frontend. Ukuran: {$receivedSizeMB} MB");

                $fileName = 'formulator_' . Str::random(5) . '_' . time() . '.' . $extension;
                $gcsFilePath = 'berkas/formulator/' . $fileName;
                
                Storage::disk('gcs')->put($gcsFilePath, file_get_contents($file));

                $gcsFileSize = Storage::disk('gcs')->size($gcsFilePath);
                $gcsSizeMB = number_format($gcsFileSize / 1048576, 2);
                Log::channel('FormulatorTrialSampelController')->info("☁️ [GCS UPLOAD] Berhasil disimpan ke Cloud. Ukuran final: {$gcsSizeMB} MB | Path: {$gcsFilePath}");
            }

           

            foreach ($request->analyses as $analysisData) {
                $noSementara = $analysisData['No_Sementara'] ?? null;
                $sumberData = (object) $analysisData;
                $idUserUntukInsert = $pengguna->UserId;   
                $isFromSementara = false;


                try {
                    $decoded = Hashids::connection('custom')->decode($analysisData['Id_Resampling']);
                    if (empty($decoded)) {
                        throw new \Exception('Invalid ID Resampling');
                    }
                    $id_resampling = $decoded[0];
                } catch (\Exception $e) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'status' => 400,
                        'message' => 'Format Id_Resampling tidak valid.'
                    ], 400);
                }

                $isFlagKhusus = DB::table('N_LIMS_PO_Sampel')
                        ->where('No_Sampel', $sumberData->No_Po_Sampel)
                        ->where('Flag_Khusus', 'Y')
                        ->exists();

                if (!$isFlagKhusus) {
                        $isAllowed = DB::table('N_EMI_LAB_Barang_Analisa')
                            ->where('Kode_Role', 'FLM')
                            ->where('Id_Jenis_Analisa', $jenisAnalisa)
                            ->where('Id_User', $userId)
                            ->exists();

                        if (!$isAllowed) {
                            return response()->json([
                                'success' => false,
                                'status' => 403,
                                'message' => "Anda tidak memiliki akses untuk Jenis Analisa ini"
                            ], 403);
                        }
                }

                $analysisData['parameters'] = collect($analysisData['parameters'])->map(function ($param) {
                    $decoded = Hashids::connection('custom')->decode($param['Id_Quality_Control']);
                    $decodedId = isset($decoded[0]) ? (string) $decoded[0] : null;
                    return [
                        'Id_Quality_Control' => $decodedId,
                        'Value_Parameter' => $param['Value_Parameter'],
                        'No_Urut' => $param['No_Urut'] ?? null,
                        'RV_INT' => $param['RV_INT'] ?? null
                    ];
                })->toArray();

                if ($noSementara) {
                    $dataSementara = DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->first();

                    if ($dataSementara) {
                        foreach ($analysisData['parameters'] as $paramFromRequest) {
                            $idDecoded = Hashids::connection('custom')->decode($paramFromRequest['No_Urut']);
                            $idDecodedRv = Hashids::connection('custom')->decode($paramFromRequest['RV_INT']);
                            $idNu = isset($idDecoded[0]) ? $idDecoded[0] : null;
                            $idRv = isset($idDecodedRv[0]) ? $idDecodedRv[0] : null;

                            if (empty($idNu) || empty($idRv)) {
                                return response()->json([
                                    'success' => false,
                                    'status' => 400,
                                    'message' => 'Data tidak lengkap untuk divalidasi. No_Urut atau RV_INT kosong pada data sementara.'
                                ], 400);
                            }

                            $dbParam = DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')
                                ->selectRaw('CAST(RV AS INT) AS RV_INT')
                                ->where('No_Sementara', $noSementara)
                                ->where('No_Urut', $idNu)
                                ->first();

                            if (!$dbParam) {
                                return response()->json([
                                    'success' => false,
                                    'status' => 404,
                                    'message' => 'Data parameter dengan No_Urut: ' . $paramFromRequest['No_Urut'] . ' tidak ditemukan.'
                                ], 404);
                            }

                            if ((int)$idRv !== (int)$dbParam->RV_INT) {
                                return response()->json([
                                    'success' => false,
                                    'status' => 409,
                                    'message' => 'Data sudah kedaluwarsa. Silakan refresh halaman.'
                                ], 409);
                            }
                        }

                        $isFromSementara = true;
                        $idUserUntukInsert = $dataSementara->Id_User;

                        $detailsSementara = DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')
                                                    ->where('No_Sementara', $noSementara)
                                                    ->get();

                        $requestParams = collect($analysisData['parameters'])->keyBy('Id_Quality_Control');

                        $mergedParameters = $detailsSementara->map(function ($dbParam) use ($requestParams) {
                            $qcId = (string) $dbParam->Id_Quality_Control;
                            $finalValue = $dbParam->Value_Parameter;

                            if ($requestParams->has($qcId) && is_null($dbParam->Value_Parameter)) {
                                $finalValue = $requestParams[$qcId]['Value_Parameter'];
                            }

                            return [
                                'Id_Quality_Control' => $qcId,
                                'Value_Parameter' => $finalValue
                            ];
                        })->toArray();

                        $sumberData = (object) [
                            'No_Po_Sampel' => $dataSementara->No_Po_Sampel,
                            'Id_Jenis_Analisa' => $jenisAnalisa,
                            'is_multi_print' => $dataSementara->Flag_Multi_QrCode,
                            'parameters' => $mergedParameters,
                            'formulas' => $analysisData['formulas'] ?? [],
                            'Id_Mesin' => $analysisData['id_mesin'] ?? [],
                        ];
                    }
                } else {
                    $sumberData = (object) [
                        'No_Po_Sampel' => $analysisData['No_Po_Sampel'],
                        'Id_Jenis_Analisa' => $jenisAnalisa,
                        'is_multi_print' => $analysisData['is_multi_print'] ?? 'N',
                        'parameters' => $analysisData['parameters'],
                        'formulas' => $analysisData['formulas'] ?? [],
                        'Id_Mesin' => $analysisData['id_mesin'] ?? [],
                    ];
                }

                $lastNumber++;
                $newNumber = $prefix . '-' . str_pad($lastNumber, 4, '0', STR_PAD_LEFT);
                $formulas = DB::table('N_EMI_LAB_Perhitungan')
                            ->where('Id_Jenis_Analisa', $sumberData->Id_Jenis_Analisa)
                            ->where('Kode_Role', 'FLM')
                            ->get();

                $parameterValues = collect($sumberData->parameters)->pluck('Value_Parameter', 'Id_Quality_Control');

                $calculatedResults = [];
                foreach ($formulas as $formula) {
                    $encodedFormula = $formula->Rumus;

                    preg_match_all('/\[(\d+)\]/', $encodedFormula, $matches);
                    if (!empty($matches[1])) {
                        foreach ($matches[1] as $originalId) {
                            $encoded = Hashids::connection('custom')->encode($originalId);
                            $encodedFormula = str_replace("[$originalId]", "[$encoded]", $encodedFormula);
                        }
                    }

                    $formula->Rumus = $encodedFormula;

                    $formulaFromRequest = collect($sumberData->formulas)->firstWhere('Rumus', $formula->Rumus);
                    $hasilDariRequest = $formulaFromRequest['Hasil_Perhitungan'] ?? null;
                    $rangeAwal = $formulaFromRequest['Range_Awal'] ?? null;
                    $rangeAkhir = $formulaFromRequest['Range_Akhir'] ?? null;

                    $resultValue = $hasilDariRequest ?: $this->calculateFormulaServerSide($formula->Rumus, $parameterValues, 0);

                    $calculatedResults[] = [
                        'Id_Perhitungan' => $formula->id,
                        'Id_Jenis_Analisa' => $formula->Id_Jenis_Analisa,
                        'Hasil_Perhitungan' => $resultValue,
                        'Range_Awal' => $rangeAwal,
                        'Range_Akhir' => $rangeAkhir
                    ];
                }

            
                $payloadUjiSampleData = [];
                $payloadActivityUjiSampelHasil = [];
                foreach ($calculatedResults as $result) {
                    $hasilFloat = $this->safeFloat($result['Hasil_Perhitungan']);
                    $RentangAwal = $result['Range_Awal'];
                    $Flag_Layak = null;

                    $checkNonPerhitungan = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                        ->where('Id_Jenis_Analisa', $result['Id_Jenis_Analisa'])
                        ->where('Kode_Role', 'FLM')
                        ->where('Flag_Aktif', 'Y')
                        ->get();

                    if ($checkNonPerhitungan->isNotEmpty()) {
                        $match = $checkNonPerhitungan->where('Nilai_Kriteria', $hasilFloat)->first();
                        
                        if ($match) {
                            $Flag_Layak = $match->Flag_Layak;
                        } else {
                            $Flag_Layak = 'T';
                        }
                    } else {
                        if (!is_null($RentangAwal) && $hasilFloat < (float)$RentangAwal) {
                            $Flag_Layak = 'T';
                        } else {
                            $Flag_Layak = 'Y';
                        }
                    }

                    $payloadUjiSampleData[] = [
                        "No_Faktur" => $newNumber,
                        "Flag_Foto" => $request->flag_foto,
                        "Kode_Perusahaan" => "001",
                        "Id_Jenis_Analisa" => $result['Id_Jenis_Analisa'],
                        "Id_Perhitungan" => $result['Id_Perhitungan'],
                        "Hasil" => $hasilFloat,
                        "Flag_Perhitungan" => 'Y',
                        "Flag_Multi_QrCode" => null,
                        "Status" => null,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        'Tahapan_Ke' => 1,
                        'Status_Keputusan_Sampel' => 'menunggu',
                        "Id_User" => $idUserUntukInsert,
                        'Flag_Layak' => $Flag_Layak,
                        "No_Po_Sampel" => $sumberData->No_Po_Sampel,
                        "Id_Mesin" => $sumberData->Id_Mesin,
                        "Range_Awal" => $result['Range_Awal'],
                        "Range_Akhir" => $result['Range_Akhir'],
                    ];

                    $payloadActivityUjiSampelHasil[] = [
                        "Kode_Perusahaan" => "001",
                        "Id_Perhitungan" => $result['Id_Perhitungan'],
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $sumberData->No_Po_Sampel,
                        "Id_Jenis_Analisa" => $sumberData->Id_Jenis_Analisa,
                        "Value_Baru" => $hasilFloat,
                        "Value_Lama" => $hasilFloat,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $idUserUntukInsert,
                        "Status_Submit" => "Submited",
                    ];
                }

                $payloadUjiSampleDetailData = [];
                $payloadActiviyUjiSampelDetail = [];
                foreach ($sumberData->parameters as $parameter) {
                    $paramValueFloat =$this->safeFloat($parameter['Value_Parameter']);

                    $payloadUjiSampleDetailData[] = [
                        "Kode_Perusahaan" => "001",
                        "No_Faktur_Uji_Sample" => $newNumber,
                        "Id_Quality_Control" => $parameter['Id_Quality_Control'],
                        "Value_Parameter" => $paramValueFloat,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $idUserUntukInsert,
                    ];

                    $payloadActiviyUjiSampelDetail[] = [
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $sumberData->No_Po_Sampel,
                        "Id_Jenis_Analisa" => $sumberData->Id_Jenis_Analisa,
                        "Id_Quality_Control" => $parameter['Id_Quality_Control'],
                        "Value_Baru" => $paramValueFloat,
                        "Value_Lama" => $paramValueFloat,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $idUserUntukInsert,
                        "Status_Submit" => "Submited",
                    ];
                }

                DB::table('N_EMI_LIMS_Uji_Sampel')->insert($payloadUjiSampleData);
                DB::table('N_EMI_LIMS_Uji_Sampel_Detail')->insert($payloadUjiSampleDetailData);
                DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail')->insert($payloadActivityUjiSampelHasil);
                DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail')->insert($payloadActiviyUjiSampelDetail);
                if ($gcsFilePath) {
                    $oldFakturs = DB::table('N_EMI_LIMS_Uji_Sampel')
                        ->where('No_Po_Sampel', $sumberData->No_Po_Sampel)
                        ->where('Id_Jenis_Analisa', $sumberData->Id_Jenis_Analisa)
                        ->pluck('No_Faktur')
                        ->toArray();

                    if (!empty($oldFakturs)) {
                        $oldBerkasRecords = DB::table('N_EMI_LIMS_Berkas_Uji_Lab')
                            ->whereIn('No_Faktur', $oldFakturs)
                            ->get();

                        foreach ($oldBerkasRecords as $berkas) {
                            if (!empty($berkas->File_Path)) {
                                $oldFilesToDeleteGcs[] = $berkas->File_Path;
                            }
                        }

                        DB::table('N_EMI_LIMS_Berkas_Uji_Lab')
                            ->whereIn('No_Faktur', $oldFakturs)
                            ->delete();
                    }

                    DB::table('N_EMI_LIMS_Berkas_Uji_Lab')->insert([
                        'No_Faktur' => $newNumber,
                        'No_Sampel' => $sumberData->No_Po_Sampel,
                        'Berkas_Key' => Str::random(32),
                        'File_Path' => $gcsFilePath
                    ]);
                }

                if ($isFromSementara) {
                    DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')->where('No_Sementara', $noSementara)->delete();
                    DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->delete();
                }

                DB::table("N_EMI_LIMS_Uji_Sampel_Resampling_Log")
                    ->where('Id_Resampling', $id_resampling)
                    ->update([
                        'Flag_Selesai_Resampling' => 'Y'
                    ]);

                $results[] = [
                    'generated_no_faktur' => $newNumber,
                    'status' => $isFromSementara ? 'temporary_table' : 'request',
                ];
            }

            DB::commit();
            if (!empty($oldFilesToDeleteGcs)) {
                $uniqueFilesToDelete = array_unique($oldFilesToDeleteGcs);
                foreach ($uniqueFilesToDelete as $oldPath) {
                    if (Storage::disk('gcs')->exists($oldPath)) {
                        Storage::disk('gcs')->delete($oldPath);
                    }
                }
            }
            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => "Data berhasil diproses dan disimpan.",
                'results' => $results 
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($gcsFilePath) && Storage::disk('gcs')->exists($gcsFilePath)) {
                Storage::disk('gcs')->delete($gcsFilePath);
            }
            Log::channel('FormulatorTrialSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan". $e->getMessage()
            ], 500);
        }
    }

    public function storeNoRumusNotMultiQrCode(Request $request)
    {
        if (is_string($request->input('analyses'))) {
            $request->merge([
                'analyses' => json_decode($request->input('analyses'), true)
            ]);
        }

        $request->validate([
            'analyses' => 'required|array|min:1',
            'analyses.*.No_Po_Sampel' => 'required|string',
            'analyses.*.Id_Jenis_Analisa' => 'required|string',
            'analyses.*.No_Sementara' => 'nullable|string',
            'analyses.*.parameters' => 'required|array|min:1',
            'analyses.*.parameters.*.Id_Quality_Control' => 'required',
            'analyses.*.parameters.*.Value_Parameter' => 'required',
        ], [
            'analyses.required' => 'Tidak ada data analisis yang dikirim.',
            'analyses.*.parameters.required' => 'Parameter tidak boleh kosong untuk setiap baris.',
        ]);

        DB::beginTransaction();

        try {
            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow;
            $tanggalSqlServer = date('Y-m-d', strtotime($dt));
            $jamSqlServer = date('H:i:s', strtotime($dt));

            $pengguna = Auth::user();
            $userId = $pengguna->UserId;

            if (!DB::table('N_EMI_LAB_Users')->where('UserId', $userId)->exists()) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "User dengan ID $userId tidak ditemukan di sistem."
                ], 404);
            }

            $listHashIds = collect($request->analyses)->pluck('Id_Jenis_Analisa')->unique();
            $decodedIds = $listHashIds->map(function ($hash) {
                $decode = Hashids::connection('custom')->decode($hash);
                return $decode[0] ?? null;
            })->filter()->toArray();

            $masterJenisAnalisa = DB::table('N_EMI_LAB_Jenis_Analisa')
                ->whereIn('id', $decodedIds)
                ->where('Kode_Role', 'FLM')
                ->select('id', 'Kode_Analisa', 'Flag_Perhitungan')
                ->get()
                ->keyBy('id');

            // Ambil Standar Range
            $standarNonHitung = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->whereIn('Id_Jenis_Analisa', $decodedIds)
                ->where('Kode_Role', 'FLM')
                ->where('Kode_Perusahaan', '001')
                ->where('Flag_Aktif', 'Y')
                ->get()
                ->groupBy('Id_Jenis_Analisa');

            $allowedAnalyses = DB::table('N_EMI_LAB_Barang_Analisa')
                ->where('Id_User', $userId)
                ->where('Kode_Role', 'FLM')
                ->whereIn('Id_Jenis_Analisa', $decodedIds)
                ->pluck('Id_Jenis_Analisa')
                ->toArray();

            $firstAnalysisHash = $request->analyses[0]['Id_Jenis_Analisa'];
            $firstIdDecoded = Hashids::connection('custom')->decode($firstAnalysisHash)[0] ?? null;

            $payloadActivityUjiSampel = [
                'Kode_Perusahaan' => '001',
                'No_Po_Sampel' => $request->analyses[0]['No_Po_Sampel'],
                'Jenis_Aktivitas' => 'save_submit',
                'Keterangan' => $pengguna->Nama . ' Berhasil Mengirimkan Data Analisa',
                'Id_User' => $userId,
                'Tanggal' => $tanggalSqlServer,
                'Jam' => $jamSqlServer,
                'Id_Jenis_Analisa' => $firstIdDecoded
            ];

            $idLogActivity = DB::table('N_EMI_LIMS_Activity_Uji_Sampel')->insertGetId($payloadActivityUjiSampel, 'Id_Log_Activity');

            $results = [];

            $currentMonth = date('m');
            $currentYear = date('y');
            $prefix = 'FUS' . $currentMonth . $currentYear;
            $prefixLength = strlen($prefix);

            $lastNumber = DB::table('N_EMI_LIMS_Uji_Sampel')
                ->where('No_Faktur', 'like', $prefix . '-%')
                ->lockForUpdate()
                ->selectRaw("MAX(CAST(SUBSTRING(No_Faktur, ? + 2, 10) AS INT)) as max_number", [$prefixLength])
                ->value('max_number') ?? 0;
            
            $uploadedFilesData = [];
            if ($request->hasFile('photos') && $request->flag_foto === 'Y') {
                foreach ($request->file('photos') as $index => $file) {
                    $extension = $file->getClientOriginalExtension() ?: 'png';
                    $receivedSizeMB = number_format($file->getSize() / 1048576, 2);
                    Log::channel('FormulatorTrialSampelController')->info("📥 [UPLOAD FOTO MULTIPLE] Menerima file indeks {$index} murni dari Frontend. Ukuran: {$receivedSizeMB} MB");

                    $fileName = 'formulator_' . Str::random(5) . '_' . time() . '_' . $index . '.' . $extension;
                    $gcsFilePath = 'berkas/formulator/' . $fileName;
                    
                    Storage::disk('gcs')->put($gcsFilePath, file_get_contents($file));

                    $gcsFileSize = Storage::disk('gcs')->size($gcsFilePath);
                    $gcsSizeMB = number_format($gcsFileSize / 1048576, 2);
                    Log::channel('FormulatorTrialSampelController')->info("☁️ [GCS UPLOAD MULTIPLE] Berhasil disimpan ke Cloud (Indeks {$index}). Ukuran final: {$gcsSizeMB} MB | Path: {$gcsFilePath}");

                    $note = $request->input("notes.$index") ?? '';

                    $uploadedFilesData[] = [
                        'File_Path' => $gcsFilePath,
                        'Keterangan' => $note
                    ];
                }
            }

            foreach ($request->analyses as $analysisData) {
                $idJenisAnalisa = Hashids::connection('custom')->decode($analysisData['Id_Jenis_Analisa'])[0] ?? null;

                if (!$idJenisAnalisa || !isset($masterJenisAnalisa[$idJenisAnalisa])) {
                    throw new \Exception("Jenis Analisa tidak valid atau tidak ditemukan.");
                }

                $infoAnalisa = $masterJenisAnalisa[$idJenisAnalisa];

                $isFlagKhusus = DB::table('N_LIMS_PO_Sampel')
                    ->where('No_Sampel', $analysisData['No_Po_Sampel'])
                    ->where('Flag_Khusus', 'Y')
                    ->exists();

                if (!$isFlagKhusus) {
                    if (!in_array($idJenisAnalisa, $allowedAnalyses)) {
                        return response()->json([
                            'success' => false,
                            'status' => 403,
                            'message' => "Anda tidak memiliki akses untuk Jenis Analisa ini"
                        ], 403);
                    }
                }

                $parameters = collect($analysisData['parameters'])->map(function ($param) {
                    $decoded = !empty($param['Id_Quality_Control']) ? Hashids::connection('custom')->decode($param['Id_Quality_Control']) : [];
                    $decodedId = isset($decoded[0]) ? (string) $decoded[0] : null;
                    $valueParameter = $param['Value_Parameter'];

                    return [
                        'Id_Quality_Control' => $decodedId,
                        'Value_Parameter' => $valueParameter,
                        'No_Urut' => $param['No_Urut'] ?? null,
                        'RV_INT' => $param['RV_INT'] ?? null
                    ];
                })->toArray();

                $noSementara = $analysisData['No_Sementara'] ?? null;
                $isFromSementara = false;
                $sumberData = (object) [
                    'No_Po_Sampel' => $analysisData['No_Po_Sampel'],
                    'Id_Jenis_Analisa' => $idJenisAnalisa,
                    'Id_Mesin' => $analysisData['id_mesin'] ?? null,
                    'formulas' => $analysisData['formulas'] ?? [],
                    'parameters' => $parameters
                ];

                if ($noSementara) {
                    $dataSementara = DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->first();

                    if ($dataSementara) {
                        foreach ($parameters as $paramFromRequest) {
                            $idNu = !empty($paramFromRequest['No_Urut']) ? Hashids::connection('custom')->decode($paramFromRequest['No_Urut'])[0] ?? null : null;
                            if ($idNu) {
                                $dbParam = DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')
                                    ->selectRaw('CAST(RV AS INT) AS RV_INT')
                                    ->where('No_Sementara', $noSementara)
                                    ->where('No_Urut', $idNu)
                                    ->first();

                                $reqRv = !empty($paramFromRequest['RV_INT']) ? Hashids::connection('custom')->decode($paramFromRequest['RV_INT'])[0] ?? null : null;

                                if ($dbParam && $reqRv !== null && (int)$reqRv !== (int)$dbParam->RV_INT) {
                                    return response()->json(['success' => false, 'status' => 409, 'message' => 'Data sudah kedaluwarsa. Silakan refresh halaman.'], 409);
                                }
                            }
                        }

                        $isFromSementara = true;
                        $detailsSementara = DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')->where('No_Sementara', $noSementara)->get();
                        $requestParams = collect($parameters)->keyBy('Id_Quality_Control');

                        $mergedParameters = $detailsSementara->map(function ($dbParam) use ($requestParams) {
                            $qcId = (string) $dbParam->Id_Quality_Control;
                            $finalValue = $dbParam->Value_Parameter;
                            if ($requestParams->has($qcId) && is_null($dbParam->Value_Parameter)) {
                                $finalValue = $requestParams[$qcId]['Value_Parameter'];
                            }
                            return ['Id_Quality_Control' => $qcId, 'Value_Parameter' => $finalValue];
                        })->toArray();
                        
                        $sumberData->parameters = $mergedParameters;
                    }
                }

                $lastNumber++;
                $newNumber = $prefix . '-' . str_pad($lastNumber, 4, '0', STR_PAD_LEFT);

                $calculatedResults = [];
                foreach ($sumberData->formulas as $formula) {
                    $hasilPerhitungan = $formula['Value_Parameter'];

                    $calculatedResults[] = [
                        'Id_Jenis_Analisa' => $sumberData->Id_Jenis_Analisa,
                        'Hasil_Perhitungan' => $hasilPerhitungan
                    ];
                }

                $payloadUjiSampleData = [];
                $payloadActivityUjiSampelHasil = [];
                $payloadUjiSampleDetailData = [];
                $payloadActiviyUjiSampelDetail = [];

                $flagPerhitunganValue = ($infoAnalisa->Flag_Perhitungan === 'Y') ? 'Y' : null;

                foreach ($calculatedResults as $result) {
                    $hasilFloat = (float) $result['Hasil_Perhitungan'];
                    $flagString = null;
                    $nilaiHasilString = null;
                    $flagLayak = 'Y';
                    $rangeAwal = null;
                    $rangeAkhir = null;

                    if ($flagPerhitunganValue !== 'Y') {
                        $standards = $standarNonHitung[$result['Id_Jenis_Analisa']] ?? collect([]);
                        $standardReference = $standards->firstWhere('Flag_Layak', 'Y');
                        if ($standardReference) {
                            $rangeAwal = $standardReference->Nilai_Kriteria;
                            $rangeAkhir = $standardReference->Nilai_Kriteria;
                        }

                        if ($standards->isNotEmpty()) {
                            $match = $standards->first(function($item) use ($hasilFloat) {
                                return (float)$item->Nilai_Kriteria == $hasilFloat;
                            });

                            if ($match) {
                                $flagLayak = $match->Flag_Layak;
                                $flagString = ($match->Flag_Layak == 'Y') ? 'Y' : 'T';
                                $nilaiHasilString = $match->Keterangan_Kriteria;
                            } else {
                                $flagLayak = 'T';
                                $flagString = 'T';
                            }
                        }
                    }

                    // dd($flagLayak);

                    $payloadUjiSampleData[] = [
                        "No_Faktur" => $newNumber,
                        "Kode_Perusahaan" => "001",
                        "Id_Jenis_Analisa" => $result['Id_Jenis_Analisa'],
                        "Hasil" => $hasilFloat,
                        "Flag_Foto" => $request->flag_foto,
                        "Flag_Perhitungan" => $flagPerhitunganValue, 
                        "Flag_Multi_QrCode" => null,
                        "Status" => null,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $userId,
                        "No_Po_Sampel" => $sumberData->No_Po_Sampel,
                        "Id_Mesin" => $sumberData->Id_Mesin,
                        'Status_Keputusan_Sampel' => 'menunggu',
                        'Flag_Layak' => $flagLayak,
                        'Range_Awal' => $rangeAwal,
                        'Range_Akhir' => $rangeAkhir,
                        'Tahapan_Ke' => 1,
                        "Flag_String" => $flagString,
                        "Nilai_Hasil_String" => $nilaiHasilString
                    ];

                    $payloadActivityUjiSampelHasil[] = [
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $sumberData->No_Po_Sampel,
                        "Id_Jenis_Analisa" => $sumberData->Id_Jenis_Analisa,
                        "Value_Baru" => $hasilFloat,
                        "Value_Lama" => $hasilFloat,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $userId,
                        "Status_Submit" => "Submited",
                    ];
                }

                foreach ($sumberData->parameters as $parameter) {
                    $paramValueFloat = (float) $parameter['Value_Parameter'];

                    $payloadUjiSampleDetailData[] = [
                        "Kode_Perusahaan" => "001",
                        "No_Faktur_Uji_Sample" => $newNumber,
                        "Id_Quality_Control" => $parameter['Id_Quality_Control'],
                        "Value_Parameter" => $paramValueFloat,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $userId,
                    ];

                    $payloadActiviyUjiSampelDetail[] = [
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $sumberData->No_Po_Sampel,
                        "Id_Jenis_Analisa" => $sumberData->Id_Jenis_Analisa,
                        "Id_Quality_Control" => $parameter['Id_Quality_Control'],
                        "Value_Baru" => $paramValueFloat,
                        "Value_Lama" => $paramValueFloat,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $userId,
                        "Status_Submit" => "Submited",
                    ];
                }

                if (!empty($uploadedFilesData)) {
                    $berkasPayload = [];
                    foreach ($uploadedFilesData as $fileData) {
                        $berkasPayload[] = [
                            'No_Faktur' => $newNumber,
                            'No_Sampel' => $analysisData['No_Po_Sampel'],
                            'Berkas_Key' => Str::random(32),
                            'File_Path' => $fileData['File_Path'],
                            'Keterangan' => $fileData['Keterangan'] 
                        ];
                    }
                    DB::table('N_EMI_LIMS_Berkas_Uji_Lab')->insert($berkasPayload);
                }

                if (!empty($payloadUjiSampleData)) {
                    DB::table('N_EMI_LIMS_Uji_Sampel')->insert($payloadUjiSampleData);
                    DB::table('N_EMI_LIMS_Uji_Sampel_Detail')->insert($payloadUjiSampleDetailData);
                    DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail')->insert($payloadActivityUjiSampelHasil);
                    DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail')->insert($payloadActiviyUjiSampelDetail);
                }

                if ($isFromSementara) {
                    DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')->where('No_Sementara', $noSementara)->delete();
                    DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->delete();
                }

                $results[] = [
                    'generated_no_faktur' => $newNumber,
                    'status' => $isFromSementara ? 'temporary_table' : 'request',
                ];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => "Data berhasil diproses dan disimpan.",
                'results' => $results
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            if (!empty($uploadedFilesData)) {
                foreach ($uploadedFilesData as $fileData) {
                    if (Storage::disk('gcs')->exists($fileData['File_Path'])) {
                        Storage::disk('gcs')->delete($fileData['File_Path']);
                        Log::channel('FormulatorTrialSampelController')->info("🗑️ [GCS ROLLBACK] File dihapus karena proses DB gagal: {$fileData['File_Path']}");
                    }
                }
            }
            Log::channel('UjiSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan"
            ], 500);
        }
    }

    public function storeNoRumusNotMultiQrCodeResampling(Request $request)
    {
        if (is_string($request->input('analyses'))) {
            $request->merge([
                'analyses' => json_decode($request->input('analyses'), true)
            ]);
        }

        $request->validate([
            'analyses' => 'required|array|min:1',
            'analyses.*.No_Po_Sampel' => 'required|string',
            'analyses.*.Id_Jenis_Analisa' => 'required|string',
            'analyses.*.No_Sementara' => 'nullable|string',
            'analyses.*.parameters' => 'required|array|min:1',
            'analyses.*.parameters.*.Id_Quality_Control' => 'required',
            'analyses.*.parameters.*.Value_Parameter' => 'required',
        ], [
            'analyses.required' => 'Tidak ada data analisis yang dikirim.',
            'analyses.*.parameters.required' => 'Parameter tidak boleh kosong untuk setiap baris.',
        ]);

        DB::beginTransaction();
        
        $berkasInsertsTemplate = []; // Penampung untuk insert berkas (Multiple foto)
        $oldFilesToDeleteGcs = [];   // Penampung path GCS file lama yang akan didelete

        try {
            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow;
            $tanggalSqlServer = date('Y-m-d', strtotime($dt));
            $jamSqlServer = date('H:i:s', strtotime($dt));
            $pengguna = Auth::user();
            $userId = $pengguna->UserId;

            if (!DB::table('N_EMI_LAB_Users')->where('UserId', $userId)->exists()) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "User dengan ID $userId tidak ditemukan di sistem."
                ], 404);
            }

            $listHashIds = collect($request->analyses)->pluck('Id_Jenis_Analisa')->unique();
            $decodedIds = $listHashIds->map(function ($hash) {
                $decode = Hashids::connection('custom')->decode($hash);
                return $decode[0] ?? null;
            })->filter()->toArray();

            $masterJenisAnalisa = DB::table('N_EMI_LAB_Jenis_Analisa')
                ->where('Kode_Role', 'FLM')
                ->whereIn('id', $decodedIds)
                ->select('id', 'Kode_Analisa', 'Flag_Perhitungan')
                ->get()
                ->keyBy('id');

            $standarNonHitung = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->whereIn('Id_Jenis_Analisa', $decodedIds)
                ->where('Kode_Role', 'FLM')
                ->where('Kode_Perusahaan', '001')
                ->where('Flag_Aktif', 'Y')
                ->get()
                ->groupBy('Id_Jenis_Analisa');

            $firstAnalysisHash = $request->analyses[0]['Id_Jenis_Analisa'];
            $firstIdDecoded = Hashids::connection('custom')->decode($firstAnalysisHash)[0] ?? null;
            $noPoSampel = $request->analyses[0]['No_Po_Sampel']; // Nomor Sampel Utama

            // 1. CARI & HAPUS BERKAS LAMA BERDASARKAN No_Sampel (Agar tidak duplikat)
            $oldBerkasRecords = DB::table('N_EMI_LIMS_Berkas_Uji_Lab')
                ->where('No_Sampel', $noPoSampel)
                ->get();

            foreach ($oldBerkasRecords as $berkas) {
                if (!empty($berkas->File_Path)) {
                    $oldFilesToDeleteGcs[] = $berkas->File_Path;
                }
            }

            // Langsung hapus dari Database sebelum proses insert baru dimulai
            if ($oldBerkasRecords->count() > 0) {
                DB::table('N_EMI_LIMS_Berkas_Uji_Lab')
                    ->where('No_Sampel', $noPoSampel)
                    ->delete();
            }

            // 2. PROSES MULTIPLE UPLOAD FOTO BARU KE GCS
            if ($request->hasFile('photos') && $request->flag_foto === 'Y') {
                $photos = $request->file('photos');
                $notes = $request->input('notes', []);

                foreach ($photos as $index => $file) {
                    $extension = $file->getClientOriginalExtension() ?: 'png';
                    $fileName = 'formulator_' . Str::random(5) . '_' . time() . '_' . $index . '.' . $extension;
                    $gcsFilePath = 'berkas/formulator/' . $fileName;
                    
                    // Lempar ke GCS
                    Storage::disk('gcs')->put($gcsFilePath, file_get_contents($file));

                    $note = isset($notes[$index]) && !empty($notes[$index]) ? $notes[$index] : '-';

                    $berkasInsertsTemplate[] = [
                        'No_Sampel' => $noPoSampel,
                        'Berkas_Key' => Str::random(32),
                        'File_Path' => $gcsFilePath,
                        'Id_Jenis_Analisa' => null, 
                        'Keterangan' => $note
                    ];
                }
            }

            $dataResamplingFirst = DB::table('N_EMI_LIMS_Uji_Sampel_Resampling_Log')
                ->where('No_Po_Sampel', $noPoSampel)
                ->where('Id_Jenis_Analisa', $firstIdDecoded)
                ->whereNull('Flag_Selesai_Resampling') 
                ->orderByDesc('Tanggal')
                ->orderByDesc('Jam')
                ->orderByDesc('Id_Resampling')
                ->first();

            if (!$dataResamplingFirst) {
                throw new \Exception("Data Resampling Aktif tidak ditemukan.");
            }

            $payloadActivityUjiSampel = [
                'Kode_Perusahaan' => '001',
                'No_Po_Sampel' => $noPoSampel,
                'Jenis_Aktivitas' => 'save_submit',
                'Keterangan' => $pengguna->Nama . ' Berhasil Mengirimkan Data Analisa Resampling',
                'Id_User' => $userId,
                'Tanggal' => $tanggalSqlServer,
                'Jam' => $jamSqlServer,
                'Id_Jenis_Analisa' => $firstIdDecoded
            ];

            $idLogActivity = DB::table('N_EMI_LIMS_Activity_Uji_Sampel')->insertGetId($payloadActivityUjiSampel, 'Id_Log_Activity');

            $results = [];

            $currentMonth = date('m');
            $currentYear = date('y');
            $prefix = 'FUS' . $currentMonth . $currentYear;
            $prefixLength = strlen($prefix);

            $lastNumber = DB::table('N_EMI_LIMS_Uji_Sampel')
                ->where('No_Faktur', 'like', $prefix . '-%')
                ->lockForUpdate()
                ->selectRaw("MAX(CAST(SUBSTRING(No_Faktur, ? + 2, 10) AS INT)) as max_number", [$prefixLength])
                ->value('max_number') ?? 0;

            foreach ($request->analyses as $analysisData) {
                $idJenisAnalisa = Hashids::connection('custom')->decode($analysisData['Id_Jenis_Analisa'])[0] ?? null;

                if (!$idJenisAnalisa || !isset($masterJenisAnalisa[$idJenisAnalisa])) {
                    throw new \Exception("Jenis Analisa tidak valid atau tidak ditemukan.");
                }
                
                $infoAnalisa = $masterJenisAnalisa[$idJenisAnalisa];
                $dataResampling = $dataResamplingFirst;
                    
                if (!$dataResampling) {
                    throw new \Exception("Data Resampling Aktif tidak ditemukan untuk analisa: " . $analysisData['No_Po_Sampel']);
                }

                $isFlagKhusus = DB::table('N_LIMS_PO_Sampel')
                    ->where('No_Sampel', $analysisData['No_Po_Sampel'])
                    ->where('Flag_Khusus', 'Y')
                    ->exists();

                if (!$isFlagKhusus) {
                    $allowed = DB::table('N_EMI_LAB_Barang_Analisa')
                        ->where('Kode_Role', 'FLM')
                        ->where('Id_User', $userId)
                        ->where('Id_Jenis_Analisa', $idJenisAnalisa)
                        ->exists();
                    
                    if (!$allowed) {
                        return response()->json([
                            'success' => false,
                            'status' => 403,
                            'message' => "Anda tidak memiliki akses untuk Jenis Analisa ini"
                        ], 403);
                    }
                }

                $parameters = collect($analysisData['parameters'])->map(function ($param) {
                    $decoded = !empty($param['Id_Quality_Control']) ? Hashids::connection('custom')->decode($param['Id_Quality_Control']) : [];
                    $decodedId = isset($decoded[0]) ? (string) $decoded[0] : null;
                    $valueParameter = $param['Value_Parameter'];

                    return [
                        'Id_Quality_Control' => $decodedId,
                        'Value_Parameter' => $valueParameter,
                        'No_Urut' => $param['No_Urut'] ?? null,
                        'RV_INT' => $param['RV_INT'] ?? null
                    ];
                })->toArray();

                $noSementara = $analysisData['No_Sementara'] ?? null;
                $isFromSementara = false;
                $sumberData = (object) [
                    'No_Po_Sampel' => $analysisData['No_Po_Sampel'],
                    'Id_Jenis_Analisa' => $idJenisAnalisa,
                    'Id_Mesin' => $analysisData['id_mesin'] ?? null,
                    'formulas' => $analysisData['formulas'] ?? [],
                    'parameters' => $parameters
                ];

                if ($noSementara) {
                    $dataSementara = DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->first();

                    if ($dataSementara) {
                        foreach ($parameters as $paramFromRequest) {
                            $idNu = !empty($paramFromRequest['No_Urut']) ? Hashids::connection('custom')->decode($paramFromRequest['No_Urut'])[0] ?? null : null;
                            if ($idNu) {
                                $dbParam = DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')
                                    ->selectRaw('CAST(RV AS INT) AS RV_INT')
                                    ->where('No_Sementara', $noSementara)
                                    ->where('No_Urut', $idNu)
                                    ->first();

                                $reqRv = !empty($paramFromRequest['RV_INT']) ? Hashids::connection('custom')->decode($paramFromRequest['RV_INT'])[0] ?? null : null;

                                if ($dbParam && $reqRv !== null && (int)$reqRv !== (int)$dbParam->RV_INT) {
                                    return response()->json(['success' => false, 'status' => 409, 'message' => 'Data sudah kedaluwarsa. Silakan refresh halaman.'], 409);
                                }
                            }
                        }

                        $isFromSementara = true;
                        $detailsSementara = DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')->where('No_Sementara', $noSementara)->get();
                        $requestParams = collect($parameters)->keyBy('Id_Quality_Control');

                        $mergedParameters = $detailsSementara->map(function ($dbParam) use ($requestParams) {
                            $qcId = (string) $dbParam->Id_Quality_Control;
                            $finalValue = $dbParam->Value_Parameter;
                            if ($requestParams->has($qcId) && is_null($dbParam->Value_Parameter)) {
                                $finalValue = $requestParams[$qcId]['Value_Parameter'];
                            }
                            return ['Id_Quality_Control' => $qcId, 'Value_Parameter' => $finalValue];
                        })->toArray();
                        
                        $sumberData->parameters = $mergedParameters;
                    }
                }

                $lastNumber++;
                $newNumber = $prefix . '-' . str_pad($lastNumber, 4, '0', STR_PAD_LEFT);

                $calculatedResults = [];
                foreach ($sumberData->formulas as $formula) {
                    $hasilPerhitungan = $formula['Value_Parameter'];

                    $calculatedResults[] = [
                        'Id_Jenis_Analisa' => $sumberData->Id_Jenis_Analisa,
                        'Hasil_Perhitungan' => $hasilPerhitungan
                    ];
                }

                $payloadUjiSampleData = [];
                $payloadActivityUjiSampelHasil = [];
                $payloadUjiSampleDetailData = [];
                $payloadActiviyUjiSampelDetail = [];

                $flagPerhitunganValue = ($infoAnalisa->Flag_Perhitungan === 'Y') ? 'Y' : null;

                foreach ($calculatedResults as $result) {
                    $hasilFloat = (float) $result['Hasil_Perhitungan'];
                    $flagString = null;
                    $nilaiHasilString = null;
                    $flagLayak = 'Y';
                    $rangeAwal = null;
                    $rangeAkhir = null;

                    if ($flagPerhitunganValue !== 'Y') {
                        $standards = $standarNonHitung[$result['Id_Jenis_Analisa']] ?? collect([]);

                        $standardReference = $standards->firstWhere('Flag_Layak', 'Y');
                        if ($standardReference) {
                            $rangeAwal = $standardReference->Nilai_Kriteria;
                            $rangeAkhir = $standardReference->Nilai_Kriteria;
                        }

                        if ($standards->isNotEmpty()) {
                            $match = $standards->first(function($item) use ($hasilFloat) {
                                return (float)$item->Nilai_Kriteria == $hasilFloat;
                            });

                            if ($match) {
                                $flagLayak = $match->Flag_Layak;
                                $flagString = ($match->Flag_Layak == 'Y') ? 'Y' : 'T';
                                $nilaiHasilString = $match->Keterangan_Kriteria;
                            } else {
                                $flagLayak = 'T';
                                $flagString = 'T';
                            }
                        }
                    }

                    $tahapanKe = $dataResampling->Tahapan_Ke ?? 1;

                    $payloadUjiSampleData[] = [
                        "No_Faktur" => $newNumber,
                        "Flag_Foto" => $request->flag_foto,
                        "Kode_Perusahaan" => "001",
                        "Id_Jenis_Analisa" => $result['Id_Jenis_Analisa'],
                        "Hasil" => $hasilFloat,
                        "Flag_Perhitungan" => $flagPerhitunganValue,
                        "Flag_Multi_QrCode" => null,
                        "Status" => null,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $userId,
                        "No_Po_Sampel" => $sumberData->No_Po_Sampel,
                        "Id_Mesin" => $sumberData->Id_Mesin,
                        'Status_Keputusan_Sampel' => 'menunggu',
                        'Flag_Layak' => $flagLayak,
                        'Range_Awal' => $rangeAwal,
                        'Range_Akhir' => $rangeAkhir,
                        'Tahapan_Ke' => $tahapanKe,
                        "Flag_String" => $flagString,
                        "Nilai_Hasil_String" => $nilaiHasilString
                    ];

                    $payloadActivityUjiSampelHasil[] = [
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $sumberData->No_Po_Sampel,
                        "Id_Jenis_Analisa" => $sumberData->Id_Jenis_Analisa,
                        "Value_Baru" => $hasilFloat,
                        "Value_Lama" => $hasilFloat,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $userId,
                        "Status_Submit" => "Submited Resampling",
                    ];
                }

                foreach ($sumberData->parameters as $parameter) {
                    $paramValueFloat = (float) $parameter['Value_Parameter'];

                    $payloadUjiSampleDetailData[] = [
                        "Kode_Perusahaan" => "001",
                        "No_Faktur_Uji_Sample" => $newNumber,
                        "Id_Quality_Control" => $parameter['Id_Quality_Control'],
                        "Value_Parameter" => $paramValueFloat,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $userId,
                    ];

                    $payloadActiviyUjiSampelDetail[] = [
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $sumberData->No_Po_Sampel,
                        "Id_Jenis_Analisa" => $sumberData->Id_Jenis_Analisa,
                        "Id_Quality_Control" => $parameter['Id_Quality_Control'],
                        "Value_Baru" => $paramValueFloat,
                        "Value_Lama" => $paramValueFloat,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $userId,
                        "Status_Submit" => "Submited Resampling",
                    ];
                }

                if (!empty($payloadUjiSampleData)) {
                    DB::table('N_EMI_LIMS_Uji_Sampel')->insert($payloadUjiSampleData);
                    DB::table('N_EMI_LIMS_Uji_Sampel_Detail')->insert($payloadUjiSampleDetailData);
                    DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail')->insert($payloadActivityUjiSampelHasil);
                    DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail')->insert($payloadActiviyUjiSampelDetail);
                }

                // 3. Masukkan data berkas foto uji lab dengan No_Faktur saat ini
                if (!empty($berkasInsertsTemplate)) {
                    $berkasToInsert = array_map(function($item) use ($newNumber) {
                        $item['No_Faktur'] = $newNumber;
                        $item['Berkas_Key'] = Str::random(32); // Pastikan key unik per baris (jika ada multi analisis)
                        return $item;
                    }, $berkasInsertsTemplate);

                    DB::table('N_EMI_LIMS_Berkas_Uji_Lab')->insert($berkasToInsert);
                }

                if ($isFromSementara) {
                    DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')->where('No_Sementara', $noSementara)->delete();
                    DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->delete();
                }

                $results[] = [
                    'generated_no_faktur' => $newNumber,
                    'status' => $isFromSementara ? 'temporary_table' : 'request',
                ];
            }

            DB::table("N_EMI_LIMS_Uji_Sampel_Resampling_Log")
                ->where('Id_Resampling', $dataResamplingFirst->Id_Resampling) 
                ->update([
                    'Flag_Selesai_Resampling' => 'Y'
                ]);

            // 4. COMMIT DATABASE
            DB::commit();

            // Penghapusan Fisik GCS dilakukan SETELAH commit agar aman
            if (!empty($oldFilesToDeleteGcs)) {
                $uniqueFilesToDelete = array_unique($oldFilesToDeleteGcs);
                foreach ($uniqueFilesToDelete as $oldPath) {
                    if (Storage::disk('gcs')->exists($oldPath)) {
                        Storage::disk('gcs')->delete($oldPath);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => "Data berhasil diproses dan disimpan.",
                'results' => $results
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Bersihkan bucket GCS apabila database gagal di commit (Rollback foto baru)
            if (!empty($berkasInsertsTemplate)) {
                foreach ($berkasInsertsTemplate as $berkasItem) {
                    if (Storage::disk('gcs')->exists($berkasItem['File_Path'])) {
                        Storage::disk('gcs')->delete($berkasItem['File_Path']);
                    }
                }
            }

            Log::channel('UjiSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan: " . $e->getMessage()
            ], 500);
        }
    }

    public function storeMultiRumusSementara(Request $request)
    {
        $request->validate([
            'analyses' => 'required|array|min:1',
            'analyses.*.No_Po_Sampel' => 'required|string',
            'analyses.*.Id_Jenis_Analisa' => 'required|string',
            'analyses.*.parameters' => 'present|array',
            'analyses.*.parameters.*.Id_Quality_Control' => 'required|string',
            'analyses.*.parameters.*.Value_Parameter' => 'nullable|numeric',
        ]);

        DB::beginTransaction();

        try {
            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow; 
            $tanggalSqlServer = date('Y-m-d', strtotime($dt)); 
            $jamSqlServer = date('H:i:s', strtotime($dt));

            $pengguna = Auth::user();
            $userId = $pengguna->UserId;

            $userExists = DB::table('N_EMI_LAB_Users')->where('UserId', $userId)->exists();

            if (!$userExists) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "User dengan ID $userId tidak ditemukan di sistem."
                ], 404);
            }

            $prefix = 'TMP-FUS' . date('my');

            $lastNumberRecord = DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')
                ->where('No_Sementara', 'like', $prefix . '-%')
                ->orderBy('No_Sementara', 'desc')
                ->first();

            $lastNumber = $lastNumberRecord ? (int) substr($lastNumberRecord->No_Sementara, -4) : 0;

            $results = [];
            $resultParmasDatabasJe = [];

            $noPoSampel = $request->analyses[0]['No_Po_Sampel'];
            $No_Po_Multi_Sampel = $request->analyses[0]['No_Po_Multi_Sampel'];
            $idDecoded = Hashids::connection('custom')->decode($request->analyses[0]['Id_Jenis_Analisa']);
            $jenisAnalisa = isset($idDecoded[0]) ? $idDecoded[0] : null;

            $payloadActivityUjiSampel = [
                    'Kode_Perusahaan' => '001',
                    'No_Po_Sampel' => $noPoSampel,
                    'No_Fak_Sub_Po' => $No_Po_Multi_Sampel,
                    'Jenis_Aktivitas' => 'save_draft',
                    'Keterangan' => $pengguna->Nama . ' Menyimpan Data Analisa Sebagai Draft',
                    'Id_User' => $pengguna->UserId,
                    'Tanggal' => $tanggalSqlServer,
                    'Jam' => $jamSqlServer,
                    'Id_Jenis_Analisa' => $jenisAnalisa
                ];

                $idLogActivity = DB::table('N_EMI_LIMS_Activity_Uji_Sampel')->insertGetId(
                    $payloadActivityUjiSampel,
                    'Id_Log_Activity'
                );

            foreach ($request->analyses as $analysis) {
                $idDecoded = Hashids::connection('custom')->decode($analysis['Id_Jenis_Analisa']);
                $jenisAnalisa = isset($idDecoded[0]) ? $idDecoded[0] : null;

                $isFlagKhusus = DB::table('N_LIMS_PO_Sampel')
                        ->where('No_Sampel', $noPoSampel)
                        ->where('Flag_Khusus', 'Y')
                        ->exists();

                if (!$isFlagKhusus) {
                        $isAllowed = DB::table('N_EMI_LAB_Barang_Analisa')
                            ->where('Id_Jenis_Analisa', $jenisAnalisa)
                            ->where('Id_User', $userId)
                            ->exists();

                        if (!$isAllowed) {
                            return response()->json([
                                'success' => false,
                                'status' => 403,
                                'message' => "Anda tidak memiliki akses untuk Jenis Analisa ini ($jenisAnalisa) pada No PO: $noPoSampel"
                            ], 403);
                        }
                }

                $keyConditions = [
                    'No_Po_Sampel' => $analysis['No_Po_Sampel'],
                    'No_Sementara' => $analysis['No_Sementara'],
                    'No_Fak_Sub_Po' => $analysis['No_Po_Multi_Sampel'],
                    'Id_Jenis_Analisa' => $jenisAnalisa
                ];

                $existing = DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')->where($keyConditions)->first();
                $allowInsert = true;

                if ($existing) {
                    $existingNoSementara = $existing->No_Sementara;
                    $parameterCount = count($analysis['parameters']);
                    $matched = 0;

                    foreach ($analysis['parameters'] as $param) {
                        $idDecodedQc = Hashids::connection('custom')->decode($param['Id_Quality_Control']);
                        $idDecodedNu = !empty($param['No_Urut']) ? Hashids::connection('custom')->decode($param['No_Urut']) : [];
                        $idNu = isset($idDecodedNu[0]) ? $idDecodedNu[0] : null;
                        $idQc = isset($idDecodedQc[0]) ? $idDecodedQc[0] : null;
                        

                        $existingParamsMap = [];
                        $existingParam = DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')
                            ->where('No_Sementara', $existingNoSementara)
                            ->where('Id_Quality_Control', $idQc)
                            ->where('No_Urut', $idNu)
                            ->first();
                            $key = $idQc . '-' . $idNu;
                            $existingParamsMap[$key] = $existingParam ? $existingParam->Value_Parameter : null;

                           if ($existingParam) {
                                $resultParmasDatabasJe[] = $existingParam->Value_Parameter;
                            } else {
                                $resultParmasDatabasJe[] = null; 
                                Log::warning("Data tidak ditemukan untuk: ", $param);
                            }
                

                        if ($existingParam) {
                            if (is_null($existingParam->Value_Parameter) && !is_null($param['Value_Parameter'])) {
                                $parameterValues = collect($analysis['parameters'])->pluck('Value_Parameter', 'Id_Quality_Control');
                                $calculatedResults = [];

                                foreach ($analysis['formulas'] as $formula) {
                                    $idDecodedNu = Hashids::connection('custom')->decode($formula['No_Urut']);
                                    $idNu = isset($idDecodedNu[0]) ? $idDecodedNu[0] : null;
                                    $hasilPerhitungan = $this->calculateFormulaServerSide($formula['Rumus'], $parameterValues, $formula['Digit']);
                                    $calculatedResults[] = [
                                        'No_Urut' => $idNu, 
                                        'Hasil_Perhitungan' => $hasilPerhitungan
                                    ];
                                }

                                foreach ($calculatedResults as $result) {
                                        $hasilFloat = $this->safeFloat($result['Hasil_Perhitungan']);

                                        $getValueHasilLama =  DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')
                                                ->where('No_Sementara', $existingNoSementara)
                                                ->where('No_Urut', $result['No_Urut'])
                                                ->first();
                                               
                                                
                                        DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail')
                                                ->insert([
                                                    "Kode_Perusahaan" => "001",
                                                    'Id_Log_Activity_Sampel' => $idLogActivity,
                                                    "No_Po_Sampel" => $analysis['No_Po_Sampel'],
                                                    "No_Fak_Sub_Po" => $analysis['No_Po_Multi_Sampel'],
                                                    "Id_Jenis_Analisa" => $jenisAnalisa,
                                                    "Value_Baru" => $hasilFloat,
                                                    "Value_Lama" => $this->safeFloat($getValueHasilLama->Hasil),
                                                    "Tanggal" => $tanggalSqlServer,
                                                    "Jam" => $jamSqlServer,
                                                    "Id_User" => $pengguna->UserId,
                                                    "Status_Submit" => "Drafted",
                                        ]);

                                            DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')
                                                ->where('No_Sementara', $existingNoSementara)
                                                ->where('No_Urut', $result['No_Urut'])
                                                ->update([
                                                    'Hasil' => $hasilFloat,
                                                    'Tanggal' => $tanggalSqlServer,
                                                    'Jam' => $jamSqlServer,
                                                    'Id_User' => $pengguna->UserId,
                                        ]);
                                }

                                $payloadActiviyUjiSampelDetail = [];
                                foreach ($analysis['parameters'] as $param) {
                                    $idDecodedNu = !empty($param['No_Urut']) ? Hashids::connection('custom')->decode($param['No_Urut']) : [];
                                    $idNuTu = isset($idDecodedNu[0]) ? $idDecodedNu[0] : null;
                                    $idDecodedQc = !empty($param['Id_Quality_Control']) ? Hashids::connection('custom')->decode($param['Id_Quality_Control']) : []; 
                                    $idQc = isset($idDecodedQc[0]) ? $idDecodedQc[0] : null;

                                    $getValueHasilLama =  DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')
                                                ->where('No_Sementara', $existingNoSementara)
                                                ->where('No_Urut', $idNuTu)
                                                ->first();

                                    $payloadActiviyUjiSampelDetail[] = [
                                        "Kode_Perusahaan" => "001",
                                        'Id_Log_Activity_Sampel' => $idLogActivity,
                                        "No_Po_Sampel" => $analysis['No_Po_Sampel'],
                                        "No_Fak_Sub_Po" => $analysis['No_Po_Multi_Sampel'],
                                        "Id_Jenis_Analisa" => $jenisAnalisa,
                                        "Id_Quality_Control" => $idQc,
                                        "Value_Baru" => $this->safeFloat($param['Value_Parameter']),
                                        "Value_Lama" => $this->safeFloat($getValueHasilLama->Value_Parameter),
                                        "Tanggal" => $tanggalSqlServer,
                                        "Jam" => $jamSqlServer,
                                        "Id_User" => $pengguna->UserId,
                                        "Status_Submit" => "Drafted",
                                    ];

                                   DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')
                                        ->where('No_Sementara', $existingNoSementara)
                                        ->where('Id_Quality_Control', $idQc)
                                        ->where('No_Urut', $idNuTu)
                                        ->update([
                                            'Value_Parameter' => $this->safeFloat($param['Value_Parameter']),
                                            'Tanggal' => $tanggalSqlServer,
                                            'Jam' => $jamSqlServer,
                                            'Id_User' => $pengguna->UserId,
                                        ]);
                                }
                                DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail')->insert($payloadActiviyUjiSampelDetail);

                                $allowInsert = false;
                               
                            } elseif ((float) $existingParam->Value_Parameter === (float) $param['Value_Parameter']) {
                                $matched++;
                            }
                        }
                    }

                    if ($matched === $parameterCount) {
                        $allowInsert = false;
                    }
                }

                if (!$allowInsert) {
                    continue;
                }

                $lastNumber++;
                $newNumber = $prefix . '-' . str_pad($lastNumber, 4, '0', STR_PAD_LEFT);

                $parameterValues = collect($analysis['parameters'])->pluck('Value_Parameter', 'Id_Quality_Control');

                $calculatedResults = [];
                foreach ($analysis['formulas'] as $formula) {
                    $hasilPerhitungan = $this->calculateFormulaServerSide($formula['Rumus'], $parameterValues, $formula['Digit']);
                    $calculatedResults[] = [
                        'Hasil_Perhitungan' => $hasilPerhitungan
                    ];
                }

                $payloadActivityUjiSampelHasil = [];
                $payloadUjiSampeSementara = [];
                foreach ($calculatedResults as $result) {
                    $hasilFloat = $this->safeFloat($result['Hasil_Perhitungan']);
                    $payloadUjiSampeSementara[] = [
                        'Kode_Perusahaan' => '001',
                        'No_Sementara' => $newNumber,
                        'No_Po_Sampel' => $analysis['No_Po_Sampel'],
                        'No_Fak_Sub_Po' => $analysis['No_Po_Multi_Sampel'],
                        'Id_Jenis_Analisa' => $jenisAnalisa,
                        'Hasil' => $hasilFloat,
                        'Flag_Perhitungan' => 'Y',
                        'Flag_Multi_QrCode' => $analysis['is_multi_print'],
                        'Status' => null,
                        'Tanggal' => $tanggalSqlServer,
                        'Jam' => $jamSqlServer,
                        'Id_User' => $pengguna->UserId,
                    ];

                    $payloadActivityUjiSampelHasil[] = [
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $analysis['No_Po_Sampel'],
                        "No_Fak_Sub_Po" => $analysis['No_Po_Multi_Sampel'],
                        "Id_Jenis_Analisa" => $jenisAnalisa,
                        "Value_Baru" => $hasilFloat,
                        "Value_Lama" => $hasilFloat,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $pengguna->UserId,
                        "Status_Submit" => "Drafted",
                    ];
                }

                $payloadActiviyUjiSampelDetailSementara = [];
                $payloadActiviyUjiSampelDetail = [];
                foreach ($analysis['parameters'] as $param) {
                    $idDecodedQc = Hashids::connection('custom')->decode($param['Id_Quality_Control']);
                    $idQc = isset($idDecodedQc[0]) ? $idDecodedQc[0] : null;

                    $payloadActiviyUjiSampelDetailSementara[] = [
                        'Kode_Perusahaan' => '001',
                        'No_Sementara' => $newNumber,
                        'Id_Quality_Control' => $idQc,
                        'Value_Parameter' => $this->safeFloat($param['Value_Parameter']),
                        'Tanggal' => $tanggalSqlServer,
                        'Jam' => $jamSqlServer,
                        'Id_User' => $pengguna->UserId,
                    ];

                    $payloadActiviyUjiSampelDetail[] = [
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $analysis['No_Po_Sampel'],
                        "No_Fak_Sub_Po" => $analysis['No_Po_Multi_Sampel'],
                        "Id_Jenis_Analisa" => $jenisAnalisa,
                        "Id_Quality_Control" => $idQc,
                        "Value_Baru" => $this->safeFloat($param['Value_Parameter']),
                        "Value_Lama" => $this->safeFloat($param['Value_Parameter']),
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $pengguna->UserId,
                        "Status_Submit" => "Drafted",
                    ];
                }

                DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')->insert($payloadUjiSampeSementara);
                DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')->insert($payloadActiviyUjiSampelDetailSementara);
                DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail')->insert($payloadActivityUjiSampelHasil);
                DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail')->insert($payloadActiviyUjiSampelDetail);


                $results[] = [
                    'No_Sementara' => $newNumber
                ];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Data Berhasil Disimpan',
                'result' => $results
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('UjiSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan"
            ], 500);
        }
    }

    public function storeMultiQrCodeNotPerhitunganSementara(Request $request)
    {

        $request->validate([
            'analyses' => 'required|array|min:1',
            'analyses.*.No_Po_Sampel' => 'required|string',
            'analyses.*.Id_Jenis_Analisa' => 'required|string',
            'analyses.*.parameters' => 'present|array',
            'analyses.*.parameters.*.Id_Quality_Control' => 'required|string',
            'analyses.*.parameters.*.Value_Parameter' => 'nullable',
        ]);

        DB::beginTransaction();

        try {

            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow; 
            $tanggalSqlServer = date('Y-m-d', strtotime($dt)); 
            $jamSqlServer = date('H:i:s', strtotime($dt));

            $pengguna = Auth::user();
            $userId = $pengguna->UserId;

            $userExists = DB::table('N_EMI_LAB_Users')->where('UserId', $userId)->exists();

            if (!$userExists) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "User dengan ID $userId tidak ditemukan di sistem."
                ], 404);
            }
            $tanggal = date('Y-m-d');
            $jam = date('H:i:s');
            $prefix = 'TMP-FUS' . date('my');

            $lastNumberRecord = DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')
                ->where('No_Sementara', 'like', $prefix . '-%')
                ->orderBy('No_Sementara', 'desc')
                ->first();

            $lastNumber = $lastNumberRecord ? (int) substr($lastNumberRecord->No_Sementara, -4) : 0;

            $results = [];
            $resultParmasDatabasJe = [];

            $noPoSampel = $request->analyses[0]['No_Po_Sampel'];
            $No_Po_Multi_Sampel = $request->analyses[0]['No_Po_Multi_Sampel'];
            $idDecoded = Hashids::connection('custom')->decode($request->analyses[0]['Id_Jenis_Analisa']);
            $jenisAnalisa = isset($idDecoded[0]) ? $idDecoded[0] : null;

            $payloadActivityUjiSampel = [
                    'Kode_Perusahaan' => '001',
                    'No_Po_Sampel' => $noPoSampel,
                    'No_Fak_Sub_Po' => $No_Po_Multi_Sampel,
                    'Jenis_Aktivitas' => 'save_draft',
                    'Keterangan' => $pengguna->Nama . ' Menyimpan Data Analisa Sebagai Draft',
                    'Id_User' => $pengguna->UserId,
                    'Tanggal' => $tanggalSqlServer,
                    'Jam' => $jamSqlServer,
                    'Id_Jenis_Analisa' => $jenisAnalisa
                ];

                $idLogActivity = DB::table('N_EMI_LIMS_Activity_Uji_Sampel')->insertGetId(
                    $payloadActivityUjiSampel,
                    'Id_Log_Activity'
                );

            foreach ($request->analyses as $analysis) {
                 foreach ($analysis['parameters'] as &$param) {
                    if ($param['Value_Parameter'] === '-') {
                        $param['Value_Parameter'] = -999999;
                    } elseif ($param['Value_Parameter'] === '+') {
                        $param['Value_Parameter'] = -88888888;
                    }
                }

                unset($param);

                $idDecoded = Hashids::connection('custom')->decode($analysis['Id_Jenis_Analisa']);
                $jenisAnalisa = isset($idDecoded[0]) ? $idDecoded[0] : null;

                $isFlagKhusus = DB::table('N_LIMS_PO_Sampel')
                        ->where('No_Sampel', $noPoSampel)
                        ->where('Flag_Khusus', 'Y')
                        ->exists();

                if (!$isFlagKhusus) {
                        $isAllowed = DB::table('N_EMI_LAB_Barang_Analisa')
                            ->where('Id_Jenis_Analisa', $jenisAnalisa)
                            ->where('Kode_Role', 'FLM')
                            ->where('Id_User', $userId)
                            ->exists();

                        if (!$isAllowed) {
                            return response()->json([
                                'success' => false,
                                'status' => 403,
                                'message' => "Anda tidak memiliki akses untuk Jenis Analisa ini ($jenisAnalisa) pada No PO: $noPoSampel"
                            ], 403);
                        }
                }

                $keyConditions = [
                    'No_Po_Sampel' => $analysis['No_Po_Sampel'],
                    'No_Sementara' => $analysis['No_Sementara'],
                    'No_Fak_Sub_Po' => $analysis['No_Po_Multi_Sampel'],
                    'Id_Jenis_Analisa' => $jenisAnalisa
                ];

                $existing = DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')->where($keyConditions)->first();
                $allowInsert = true;

                if ($existing) {
                    $existingNoSementara = $existing->No_Sementara;
                    $parameterCount = count($analysis['parameters']);
                    $matched = 0;

                    foreach ($analysis['parameters'] as $param) {
                        $idDecodedQc = Hashids::connection('custom')->decode($param['Id_Quality_Control']);
                        $idDecodedNu = !empty($param['No_Urut']) ? Hashids::connection('custom')->decode($param['No_Urut']) : [];
                        $idNu = isset($idDecodedNu[0]) ? $idDecodedNu[0] : null;
                        $idQc = isset($idDecodedQc[0]) ? $idDecodedQc[0] : null;
                        

                        $existingParamsMap = [];
                        $existingParam = DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')
                            ->where('No_Sementara', $existingNoSementara)
                            ->where('Id_Quality_Control', $idQc)
                            ->where('No_Urut', $idNu)
                            ->first();
                            $key = $idQc . '-' . $idNu;
                            $existingParamsMap[$key] = $existingParam ? $existingParam->Value_Parameter : null;

                           if ($existingParam) {
                                $resultParmasDatabasJe[] = $existingParam->Value_Parameter;
                            } else {
                                $resultParmasDatabasJe[] = null; 
                                Log::warning("Data tidak ditemukan untuk: ", $param);
                            }

                        if ($existingParam) {
                            if (is_null($existingParam->Value_Parameter) && !is_null($param['Value_Parameter'])) {
                                $parameterValues = collect($analysis['parameters'])->pluck('Value_Parameter', 'Id_Quality_Control');
                                $calculatedResults = [];

                                foreach ($analysis['formulas'] as $formula) {
                                    $idDecodedNu = Hashids::connection('custom')->decode($formula['No_Urut']);
                                    $idNu = isset($idDecodedNu[0]) ? $idDecodedNu[0] : null;
                                    $hasilPerhitungan = $this->calculateFormulaServerSide($formula['Rumus'], $parameterValues, $formula['Digit']);
                                    $calculatedResults[] = [
                                        'No_Urut' => $idNu, 
                                        'Hasil_Perhitungan' => $hasilPerhitungan
                                    ];
                                }

                                foreach ($calculatedResults as $result) {
                                        $hasilFloat = $this->safeFloat($result['Hasil_Perhitungan']);

                                        $getValueHasilLama =  DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')
                                                ->where('No_Sementara', $existingNoSementara)
                                                ->where('No_Urut', $result['No_Urut'])
                                                ->first();
                                               
                                                
                                        DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail')
                                                ->insert([
                                                    "Kode_Perusahaan" => "001",
                                                    'Id_Log_Activity_Sampel' => $idLogActivity,
                                                    "No_Po_Sampel" => $analysis['No_Po_Sampel'],
                                                    "No_Fak_Sub_Po" => $analysis['No_Po_Multi_Sampel'],
                                                    "Id_Jenis_Analisa" => $jenisAnalisa,
                                                    "Value_Baru" => $hasilFloat,
                                                    "Value_Lama" => $this->safeFloat($getValueHasilLama->Hasil),
                                                    "Tanggal" => $tanggalSqlServer,
                                                    "Jam" => $jamSqlServer,
                                                    "Id_User" => $pengguna->UserId,
                                                    "Status_Submit" => "Drafted",
                                        ]);

                                            DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')
                                                ->where('No_Sementara', $existingNoSementara)
                                                ->where('No_Urut', $result['No_Urut'])
                                                ->update([
                                                    'Hasil' => $hasilFloat,
                                                    'Tanggal' => $tanggalSqlServer,
                                                    'Jam' => $jamSqlServer,
                                                    'Id_User' => $pengguna->UserId,
                                        ]);
                                }

                                $payloadActiviyUjiSampelDetail = [];
                                foreach ($analysis['parameters'] as $param) {
                                    $idDecodedNu = !empty($param['No_Urut']) ? Hashids::connection('custom')->decode($param['No_Urut']) : [];
                                    $idNuTu = isset($idDecodedNu[0]) ? $idDecodedNu[0] : null;
                                    $idDecodedQc = !empty($param['Id_Quality_Control']) ? Hashids::connection('custom')->decode($param['Id_Quality_Control']) : []; 
                                    $idQc = isset($idDecodedQc[0]) ? $idDecodedQc[0] : null;

                                    $getValueHasilLama =  DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')
                                                ->where('No_Sementara', $existingNoSementara)
                                                ->where('No_Urut', $idNuTu)
                                                ->first();

                                    $payloadActiviyUjiSampelDetail[] = [
                                        "Kode_Perusahaan" => "001",
                                        'Id_Log_Activity_Sampel' => $idLogActivity,
                                        "No_Po_Sampel" => $analysis['No_Po_Sampel'],
                                        "No_Fak_Sub_Po" => $analysis['No_Po_Multi_Sampel'],
                                        "Id_Jenis_Analisa" => $jenisAnalisa,
                                        "Id_Quality_Control" => $idQc,
                                        "Value_Baru" => $this->safeFloat($param['Value_Parameter']),
                                        "Value_Lama" => $this->safeFloat($getValueHasilLama->Value_Parameter),
                                        "Tanggal" => $tanggalSqlServer,
                                        "Jam" => $jamSqlServer,
                                        "Id_User" => $pengguna->UserId,
                                        "Status_Submit" => "Drafted",
                                    ];

                                   DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')
                                        ->where('No_Sementara', $existingNoSementara)
                                        ->where('Id_Quality_Control', $idQc)
                                        ->where('No_Urut', $idNuTu)
                                        ->update([
                                            'Value_Parameter' => $this->safeFloat($param['Value_Parameter']),
                                            'Tanggal' => $tanggalSqlServer,
                                            'Jam' => $jamSqlServer,
                                            'Id_User' => $pengguna->UserId,
                                        ]);
                                }
                                DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail')->insert($payloadActiviyUjiSampelDetail);

                                $allowInsert = false;
                               
                            } elseif ((float) $existingParam->Value_Parameter === (float) $param['Value_Parameter']) {
                                $matched++;
                            }
                        }
                    }

                    if ($matched === $parameterCount) {
                        $allowInsert = false;
                    }
                }

                if (!$allowInsert) {
                    continue;
                }

                $lastNumber++;
                $newNumber = $prefix . '-' . str_pad($lastNumber, 4, '0', STR_PAD_LEFT);

                $payloadActivityUjiSampelHasil = [];
                $payloadUjiSampeSementara = [];
                $payloadActiviyUjiSampelDetailSementara = [];
                $payloadActiviyUjiSampelDetail = [];

                foreach ($analysis['parameters'] as $param) {
                    $idDecodedQc = Hashids::connection('custom')->decode($param['Id_Quality_Control']);
                    $idQc = isset($idDecodedQc[0]) ? $idDecodedQc[0] : null;

                    $payloadUjiSampeSementara[] = [
                        'Kode_Perusahaan' => '001',
                        'No_Sementara' => $newNumber,
                        'No_Po_Sampel' => $analysis['No_Po_Sampel'],
                        'No_Fak_Sub_Po' => $analysis['No_Po_Multi_Sampel'],
                        'Id_Jenis_Analisa' => $jenisAnalisa,
                        'Hasil' => $this->safeFloat($param['Value_Parameter']),
                        'Flag_Perhitungan' => null,
                        'Flag_Multi_QrCode' => 'Y',
                        'Status' => null,
                        'Tanggal' => $tanggalSqlServer,
                        'Jam' => $jamSqlServer,
                        'Id_User' => $pengguna->UserId,
                    ];

                    $payloadActivityUjiSampelHasil[] = [
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $analysis['No_Po_Sampel'],
                        "No_Fak_Sub_Po" => $analysis['No_Po_Multi_Sampel'],
                        "Id_Jenis_Analisa" => $jenisAnalisa,
                        "Value_Baru" => $this->safeFloat($param['Value_Parameter']),
                        "Value_Lama" => $this->safeFloat($param['Value_Parameter']),
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $pengguna->UserId,
                        "Status_Submit" => "Drafted",
                    ];

                    $payloadActiviyUjiSampelDetailSementara[] = [
                        'Kode_Perusahaan' => '001',
                        'No_Sementara' => $newNumber,
                        'Id_Quality_Control' => $idQc,
                        'Value_Parameter' => $this->safeFloat($param['Value_Parameter']),
                        'Tanggal' => $tanggalSqlServer,
                        'Jam' => $jamSqlServer,
                        'Id_User' => $pengguna->UserId,
                    ];

                    $payloadActiviyUjiSampelDetail[] = [
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $analysis['No_Po_Sampel'],
                        "No_Fak_Sub_Po" => $analysis['No_Po_Multi_Sampel'],
                        "Id_Jenis_Analisa" => $jenisAnalisa,
                        "Id_Quality_Control" => $idQc,
                        "Value_Baru" => $this->safeFloat($param['Value_Parameter']),
                        "Value_Lama" => $this->safeFloat($param['Value_Parameter']),
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $pengguna->UserId,
                        "Status_Submit" => "Drafted",
                    ];
                }

                DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')->insert($payloadUjiSampeSementara);
                DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')->insert($payloadActiviyUjiSampelDetailSementara);
                DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail')->insert($payloadActivityUjiSampelHasil);
                DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail')->insert($payloadActiviyUjiSampelDetail);


                $results[] = [
                    'No_Sementara' => $newNumber
                ];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Data Berhasil Disimpan',
                'result' => $results
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('UjiSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan"
            ], 500);
        }
    }

    public function storeMultiQrCodeNotPerhitunganSementaraResampling(Request $request)
    {

        $request->validate([
            'analyses' => 'required|array|min:1',
            'analyses.*.No_Po_Sampel' => 'required|string',
            'analyses.*.Id_Jenis_Analisa' => 'required|string',
            'analyses.*.parameters' => 'present|array',
            'analyses.*.parameters.*.Id_Quality_Control' => 'required|string',
            'analyses.*.parameters.*.Value_Parameter' => 'nullable',
        ]);

        DB::beginTransaction();

        try {

            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow; 
            $tanggalSqlServer = date('Y-m-d', strtotime($dt)); 
            $jamSqlServer = date('H:i:s', strtotime($dt));

            $pengguna = Auth::user();
            $userId = $pengguna->UserId;

            $userExists = DB::table('N_EMI_LAB_Users')->where('UserId', $userId)->exists();

            if (!$userExists) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "User dengan ID $userId tidak ditemukan di sistem."
                ], 404);
            }

            $prefix = 'TMP-FUS' . date('my');

            $lastNumberRecord = DB::table('N_EMI_LAB_Uji_Sampel_Sementara')
                ->where('No_Sementara', 'like', $prefix . '-%')
                ->orderBy('No_Sementara', 'desc')
                ->first();

            $lastNumber = $lastNumberRecord ? (int) substr($lastNumberRecord->No_Sementara, -4) : 0;

            $results = [];
            $resultParmasDatabasJe = [];

            $noPoSampel = $request->analyses[0]['No_Po_Sampel'];
            $No_Po_Multi_Sampel = $request->analyses[0]['No_Po_Multi_Sampel'];
            $idDecoded = Hashids::connection('custom')->decode($request->analyses[0]['Id_Jenis_Analisa']);
            $jenisAnalisa = isset($idDecoded[0]) ? $idDecoded[0] : null;

            $payloadActivityUjiSampel = [
                    'Kode_Perusahaan' => '001',
                    'No_Po_Sampel' => $noPoSampel,
                    'No_Fak_Sub_Po' => $No_Po_Multi_Sampel,
                    'Jenis_Aktivitas' => 'save_draft',
                    'Keterangan' => $pengguna->Nama . ' Menyimpan Data Analisa Sebagai Draft',
                    'Id_User' => $pengguna->UserId,
                    'Tanggal' => $tanggalSqlServer,
                    'Jam' => $jamSqlServer,
                    'Id_Jenis_Analisa' => $jenisAnalisa
                ];

                $idLogActivity = DB::table('N_EMI_LAB_Activity_Uji_Sampel')->insertGetId(
                    $payloadActivityUjiSampel,
                    'Id_Log_Activity'
                );

            foreach ($request->analyses as $analysis) {
                 foreach ($analysis['parameters'] as &$param) {
                    if ($param['Value_Parameter'] === '-') {
                        $param['Value_Parameter'] = -999999;
                    } elseif ($param['Value_Parameter'] === '+') {
                        $param['Value_Parameter'] = -88888888;
                    }
                }
                // Hapus referensi setelah loop selesai (praktik yang baik)
                unset($param);

                $idDecoded = Hashids::connection('custom')->decode($analysis['Id_Jenis_Analisa']);
                $jenisAnalisa = isset($idDecoded[0]) ? $idDecoded[0] : null;

                $isFlagKhusus = DB::table('N_EMI_LAB_PO_Sampel')
                        ->where('No_Sampel', $noPoSampel)
                        ->where('Flag_Khusus', 'Y')
                        ->exists();

                if (!$isFlagKhusus) {
                        $isAllowed = DB::table('N_EMI_LAB_Barang_Analisa')
                            ->where('Id_Jenis_Analisa', $jenisAnalisa)
                            ->where('Id_User', $userId)
                            ->exists();

                        if (!$isAllowed) {
                            return response()->json([
                                'success' => false,
                                'status' => 403,
                                'message' => "Anda tidak memiliki akses untuk Jenis Analisa ini ($jenisAnalisa) pada No PO: $noPoSampel"
                            ], 403);
                        }
                }

                $keyConditions = [
                    'No_Po_Sampel' => $analysis['No_Po_Sampel'],
                    'No_Sementara' => $analysis['No_Sementara'],
                    'No_Fak_Sub_Po' => $analysis['No_Po_Multi_Sampel'],
                    'Id_Jenis_Analisa' => $jenisAnalisa
                ];

                $existing = DB::table('N_EMI_LAB_Uji_Sampel_Sementara')->where($keyConditions)->first();
                $allowInsert = true;

                if ($existing) {
                    $existingNoSementara = $existing->No_Sementara;
                    $parameterCount = count($analysis['parameters']);
                    $matched = 0;

                    foreach ($analysis['parameters'] as $param) {
                        $idDecodedQc = Hashids::connection('custom')->decode($param['Id_Quality_Control']);
                        $idDecodedNu = !empty($param['No_Urut']) ? Hashids::connection('custom')->decode($param['No_Urut']) : [];
                        $idNu = isset($idDecodedNu[0]) ? $idDecodedNu[0] : null;
                        $idQc = isset($idDecodedQc[0]) ? $idDecodedQc[0] : null;
                        

                        $existingParamsMap = [];
                        $existingParam = DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')
                            ->where('No_Sementara', $existingNoSementara)
                            ->where('Id_Quality_Control', $idQc)
                            ->where('No_Urut', $idNu)
                            ->first();
                            $key = $idQc . '-' . $idNu;
                            $existingParamsMap[$key] = $existingParam ? $existingParam->Value_Parameter : null;

                           if ($existingParam) {
                                $resultParmasDatabasJe[] = $existingParam->Value_Parameter;
                            } else {
                                $resultParmasDatabasJe[] = null; 
                                Log::warning("Data tidak ditemukan untuk: ", $param);
                            }

                        if ($existingParam) {
                            if (is_null($existingParam->Value_Parameter) && !is_null($param['Value_Parameter'])) {
                                $parameterValues = collect($analysis['parameters'])->pluck('Value_Parameter', 'Id_Quality_Control');
                                $calculatedResults = [];

                                foreach ($analysis['formulas'] as $formula) {
                                    $idDecodedNu = Hashids::connection('custom')->decode($formula['No_Urut']);
                                    $idNu = isset($idDecodedNu[0]) ? $idDecodedNu[0] : null;
                                    $hasilPerhitungan = $this->calculateFormulaServerSide($formula['Rumus'], $parameterValues, $formula['Digit']);
                                    $calculatedResults[] = [
                                        'No_Urut' => $idNu, 
                                        'Hasil_Perhitungan' => $hasilPerhitungan
                                    ];
                                }

                                foreach ($calculatedResults as $result) {
                                        $hasilFloat = $this->safeFloat($result['Hasil_Perhitungan']);

                                        $getValueHasilLama =  DB::table('N_EMI_LAB_Uji_Sampel_Sementara')
                                                ->where('No_Sementara', $existingNoSementara)
                                                ->where('No_Urut', $result['No_Urut'])
                                                ->first();
                                               
                                                
                                        DB::table('N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail')
                                                ->insert([
                                                    "Kode_Perusahaan" => "001",
                                                    'Id_Log_Activity_Sampel' => $idLogActivity,
                                                    "No_Po_Sampel" => $analysis['No_Po_Sampel'],
                                                    "No_Fak_Sub_Po" => $analysis['No_Po_Multi_Sampel'],
                                                    "Id_Jenis_Analisa" => $jenisAnalisa,
                                                    "Value_Baru" => $hasilFloat,
                                                    "Value_Lama" => $this->safeFloat($getValueHasilLama->Hasil),
                                                    "Tanggal" => $tanggalSqlServer,
                                                    "Jam" => $jamSqlServer,
                                                    "Id_User" => $pengguna->UserId,
                                                    "Status_Submit" => "Drafted",
                                        ]);

                                            DB::table('N_EMI_LAB_Uji_Sampel_Sementara')
                                                ->where('No_Sementara', $existingNoSementara)
                                                ->where('No_Urut', $result['No_Urut'])
                                                ->update([
                                                    'Hasil' => $hasilFloat,
                                                    'Tanggal' => $tanggalSqlServer,
                                                    'Jam' => $jamSqlServer,
                                                    'Id_User' => $pengguna->UserId,
                                        ]);
                                }

                                $payloadActiviyUjiSampelDetail = [];
                                foreach ($analysis['parameters'] as $param) {
                                    $idDecodedNu = !empty($param['No_Urut']) ? Hashids::connection('custom')->decode($param['No_Urut']) : [];
                                    $idNuTu = isset($idDecodedNu[0]) ? $idDecodedNu[0] : null;
                                    $idDecodedQc = !empty($param['Id_Quality_Control']) ? Hashids::connection('custom')->decode($param['Id_Quality_Control']) : []; 
                                    $idQc = isset($idDecodedQc[0]) ? $idDecodedQc[0] : null;

                                    $getValueHasilLama =  DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')
                                                ->where('No_Sementara', $existingNoSementara)
                                                ->where('No_Urut', $idNuTu)
                                                ->first();

                                    $payloadActiviyUjiSampelDetail[] = [
                                        "Kode_Perusahaan" => "001",
                                        'Id_Log_Activity_Sampel' => $idLogActivity,
                                        "No_Po_Sampel" => $analysis['No_Po_Sampel'],
                                        "No_Fak_Sub_Po" => $analysis['No_Po_Multi_Sampel'],
                                        "Id_Jenis_Analisa" => $jenisAnalisa,
                                        "Id_Quality_Control" => $idQc,
                                        "Value_Baru" => $this->safeFloat($param['Value_Parameter']),
                                        "Value_Lama" => $this->safeFloat($getValueHasilLama->Value_Parameter),
                                        "Tanggal" => $tanggalSqlServer,
                                        "Jam" => $jamSqlServer,
                                        "Id_User" => $pengguna->UserId,
                                        "Status_Submit" => "Drafted",
                                    ];

                                   DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')
                                        ->where('No_Sementara', $existingNoSementara)
                                        ->where('Id_Quality_Control', $idQc)
                                        ->where('No_Urut', $idNuTu)
                                        ->update([
                                            'Value_Parameter' => $this->safeFloat($param['Value_Parameter']),
                                            'Tanggal' => $tanggalSqlServer,
                                            'Jam' => $jamSqlServer,
                                            'Id_User' => $pengguna->UserId,
                                        ]);
                                }
                                DB::table('N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail')->insert($payloadActiviyUjiSampelDetail);

                                $allowInsert = false;
                               
                            } elseif ((float) $existingParam->Value_Parameter === (float) $param['Value_Parameter']) {
                                $matched++;
                            }
                        }
                    }

                    if ($matched === $parameterCount) {
                        $allowInsert = false;
                    }
                }

                if (!$allowInsert) {
                    continue;
                }

                $lastNumber++;
                $newNumber = $prefix . '-' . str_pad($lastNumber, 4, '0', STR_PAD_LEFT);

                $payloadActivityUjiSampelHasil = [];
                $payloadUjiSampeSementara = [];
                $payloadActiviyUjiSampelDetailSementara = [];
                $payloadActiviyUjiSampelDetail = [];

                foreach ($analysis['parameters'] as $param) {
                    $idDecodedQc = Hashids::connection('custom')->decode($param['Id_Quality_Control']);
                    $idQc = isset($idDecodedQc[0]) ? $idDecodedQc[0] : null;

                    $payloadUjiSampeSementara[] = [
                        'Kode_Perusahaan' => '001',
                        'No_Sementara' => $newNumber,
                        'No_Po_Sampel' => $analysis['No_Po_Sampel'],
                        'No_Fak_Sub_Po' => $analysis['No_Po_Multi_Sampel'],
                        'Id_Jenis_Analisa' => $jenisAnalisa,
                        'Hasil' => $this->safeFloat($param['Value_Parameter']),
                        'Flag_Perhitungan' => null,
                        'Flag_Multi_QrCode' => 'Y',
                        'Status' => null,
                        'Tanggal' => $tanggalSqlServer,
                        'Jam' => $jamSqlServer,
                        'Id_User' => $pengguna->UserId,
                    ];

                    $payloadActivityUjiSampelHasil[] = [
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $analysis['No_Po_Sampel'],
                        "No_Fak_Sub_Po" => $analysis['No_Po_Multi_Sampel'],
                        "Id_Jenis_Analisa" => $jenisAnalisa,
                        "Value_Baru" => $this->safeFloat($param['Value_Parameter']),
                        "Value_Lama" => $this->safeFloat($param['Value_Parameter']),
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $pengguna->UserId,
                        "Status_Submit" => "Drafted",
                    ];

                    $payloadActiviyUjiSampelDetailSementara[] = [
                        'Kode_Perusahaan' => '001',
                        'No_Sementara' => $newNumber,
                        'Id_Quality_Control' => $idQc,
                        'Value_Parameter' => $this->safeFloat($param['Value_Parameter']),
                        'Tanggal' => $tanggalSqlServer,
                        'Jam' => $jamSqlServer,
                        'Id_User' => $pengguna->UserId,
                    ];

                    $payloadActiviyUjiSampelDetail[] = [
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $analysis['No_Po_Sampel'],
                        "No_Fak_Sub_Po" => $analysis['No_Po_Multi_Sampel'],
                        "Id_Jenis_Analisa" => $jenisAnalisa,
                        "Id_Quality_Control" => $idQc,
                        "Value_Baru" => $this->safeFloat($param['Value_Parameter']),
                        "Value_Lama" => $this->safeFloat($param['Value_Parameter']),
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $pengguna->UserId,
                        "Status_Submit" => "Drafted",
                    ];
                }

                DB::table('N_EMI_LAB_Uji_Sampel_Sementara')->insert($payloadUjiSampeSementara);
                DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')->insert($payloadActiviyUjiSampelDetailSementara);
                DB::table('N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail')->insert($payloadActivityUjiSampelHasil);
                DB::table('N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail')->insert($payloadActiviyUjiSampelDetail);


                $results[] = [
                    'No_Sementara' => $newNumber
                ];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Data Berhasil Disimpan',
                'result' => $results
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('UjiSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan"
            ], 500);
        }
    }

    public function storeNotMultiRumusSementara(Request $request)
    {

        $request->validate([
            'analyses' => 'required|array|min:1',
            'analyses.*.No_Po_Sampel' => 'required|string',
            'analyses.*.Id_Jenis_Analisa' => 'required|string',
            'analyses.*.parameters' => 'present|array',
            'analyses.*.parameters.*.Id_Quality_Control' => 'required|string',
            'analyses.*.parameters.*.Value_Parameter' => 'nullable|numeric',
        ]);

        DB::beginTransaction();

        try {

            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow; 
            $tanggalSqlServer = date('Y-m-d', strtotime($dt)); 
            $jamSqlServer = date('H:i:s', strtotime($dt));

            $pengguna = Auth::user();
            $userId = $pengguna->UserId;

            $userExists = DB::table('N_EMI_LAB_Users')->where('UserId', $userId)->exists();

            if (!$userExists) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "User dengan ID $userId tidak ditemukan di sistem."
                ], 404);
            }

            $prefix = 'TMP-FUS' . date('my');

            $lastNumberRecord = DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')
                ->where('No_Sementara', 'like', $prefix . '-%')
                ->orderBy('No_Sementara', 'desc')
                ->first();

            $lastNumber = $lastNumberRecord ? (int) substr($lastNumberRecord->No_Sementara, -4) : 0;

            $results = [];
            $resultParmasDatabasJe = [];

            $noPoSampel = $request->analyses[0]['No_Po_Sampel'];
            $idDecoded = Hashids::connection('custom')->decode($request->analyses[0]['Id_Jenis_Analisa']);
            $jenisAnalisa = isset($idDecoded[0]) ? $idDecoded[0] : null;

            $payloadActivityUjiSampel = [
                    'Kode_Perusahaan' => '001',
                    'No_Po_Sampel' => $noPoSampel,
                    'Jenis_Aktivitas' => 'save_draft',
                    'Keterangan' => $pengguna->Nama . ' Menyimpan Data Analisa Sebagai Draft',
                    'Id_User' => $pengguna->UserId,
                    'Tanggal' => $tanggalSqlServer,
                    'Jam' => $jamSqlServer,
                    'Id_Jenis_Analisa' => $jenisAnalisa
                ];

                $idLogActivity = DB::table('N_EMI_LIMS_Activity_Uji_Sampel')->insertGetId(
                    $payloadActivityUjiSampel,
                    'Id_Log_Activity'
                );

            foreach ($request->analyses as $analysis) {
                $idDecoded = Hashids::connection('custom')->decode($analysis['Id_Jenis_Analisa']);
                $jenisAnalisa = isset($idDecoded[0]) ? $idDecoded[0] : null;

                $isFlagKhusus = DB::table('N_LIMS_PO_Sampel')
                        ->where('No_Sampel', $noPoSampel)
                        ->where('Flag_Khusus', 'Y')
                        ->exists();

                if (!$isFlagKhusus) {
                        $isAllowed = DB::table('N_EMI_LAB_Barang_Analisa')
                            ->where('Id_Jenis_Analisa', $jenisAnalisa)
                            ->where('Id_User', $userId)
                            ->where('Kode_Role', 'FLM')
                            ->exists();

                        if (!$isAllowed) {
                            return response()->json([
                                'success' => false,
                                'status' => 403,
                                'message' => "Anda tidak memiliki akses untuk Jenis Analisa ini ($jenisAnalisa) pada No PO: $noPoSampel"
                            ], 403);
                        }
                }

                $keyConditions = [
                    'No_Po_Sampel' => $analysis['No_Po_Sampel'],
                    'No_Sementara' => $analysis['No_Sementara'],
                    'Id_Jenis_Analisa' => $jenisAnalisa
                ];

                $existing = DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')->where($keyConditions)->first();
                $allowInsert = true;

                if ($existing) {
                    $existingNoSementara = $existing->No_Sementara;
                    $parameterCount = count($analysis['parameters']);
                    $matched = 0;

                    foreach ($analysis['parameters'] as $param) {
                        $idDecodedQc = Hashids::connection('custom')->decode($param['Id_Quality_Control']);
                        $idDecodedNu = !empty($param['No_Urut']) ? Hashids::connection('custom')->decode($param['No_Urut']) : [];
                        $idNu = isset($idDecodedNu[0]) ? $idDecodedNu[0] : null;
                        $idQc = isset($idDecodedQc[0]) ? $idDecodedQc[0] : null;
                        

                        $existingParamsMap = [];
                        $existingParam = DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')
                            ->where('No_Sementara', $existingNoSementara)
                            ->where('Id_Quality_Control', $idQc)
                            ->where('No_Urut', $idNu)
                            ->first();
                            $key = $idQc . '-' . $idNu;
                            $existingParamsMap[$key] = $existingParam ? $existingParam->Value_Parameter : null;

                           if ($existingParam) {
                                $resultParmasDatabasJe[] = $existingParam->Value_Parameter;
                            } else {
                                $resultParmasDatabasJe[] = null; 
                                Log::warning("Data tidak ditemukan untuk: ", $param);
                            }
                

                        if ($existingParam) {
                            if (is_null($existingParam->Value_Parameter) && !is_null($param['Value_Parameter'])) {
                                $parameterValues = collect($analysis['parameters'])->pluck('Value_Parameter', 'Id_Quality_Control');
                                $calculatedResults = [];

                                foreach ($analysis['formulas'] as $formula) {
                                    $idDecodedNu = Hashids::connection('custom')->decode($formula['No_Urut']);
                                    $idNu = isset($idDecodedNu[0]) ? $idDecodedNu[0] : null;
                                    $hasilPerhitungan = $this->calculateFormulaServerSide($formula['Rumus'], $parameterValues, $formula['Digit']);
                                    $calculatedResults[] = [
                                        'No_Urut' => $idNu, 
                                        'Hasil_Perhitungan' => $hasilPerhitungan
                                    ];
                                }

                                foreach ($calculatedResults as $result) {
                                        $hasilFloat = $this->safeFloat($result['Hasil_Perhitungan']);

                                        $getValueHasilLama =  DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')
                                                ->where('No_Sementara', $existingNoSementara)
                                                ->where('No_Urut', $result['No_Urut'])
                                                ->first();
                                               
                                                
                                        DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail')
                                                ->insert([
                                                    "Kode_Perusahaan" => "001",
                                                    'Id_Log_Activity_Sampel' => $idLogActivity,
                                                    "No_Po_Sampel" => $analysis['No_Po_Sampel'],
                                                    "Id_Jenis_Analisa" => $jenisAnalisa,
                                                    "Value_Baru" => $hasilFloat,
                                                    "Value_Lama" => $this->safeFloat($getValueHasilLama->Hasil),
                                                    "Tanggal" => $tanggalSqlServer,
                                                    "Jam" => $jamSqlServer,
                                                    "Id_User" => $pengguna->UserId,
                                                    "Status_Submit" => "Drafted",
                                        ]);

                                            DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')
                                                ->where('No_Sementara', $existingNoSementara)
                                                ->where('No_Urut', $result['No_Urut'])
                                                ->update([
                                                    'Hasil' => $hasilFloat,
                                                    'Tanggal' => $tanggalSqlServer,
                                                    'Jam' => $jamSqlServer,
                                                    'Id_User' => $pengguna->UserId,
                                        ]);

                                        

                                }

                                $payloadActiviyUjiSampelDetail = [];
                                foreach ($analysis['parameters'] as $param) {
                                    $idDecodedNu = !empty($param['No_Urut']) ? Hashids::connection('custom')->decode($param['No_Urut']) : [];
                                    $idNuTu = isset($idDecodedNu[0]) ? $idDecodedNu[0] : null;
                                    $idDecodedQc = !empty($param['Id_Quality_Control']) ? Hashids::connection('custom')->decode($param['Id_Quality_Control']) : []; 
                                    $idQc = isset($idDecodedQc[0]) ? $idDecodedQc[0] : null;

                                    $getValueHasilLama =  DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')
                                                ->where('No_Sementara', $existingNoSementara)
                                                ->where('No_Urut', $idNuTu)
                                                ->first();

                                    $payloadActiviyUjiSampelDetail[] = [
                                        "Kode_Perusahaan" => "001",
                                        'Id_Log_Activity_Sampel' => $idLogActivity,
                                        "No_Po_Sampel" => $analysis['No_Po_Sampel'],
                                        "Id_Jenis_Analisa" => $jenisAnalisa,
                                        "Id_Quality_Control" => $idQc,
                                        "Value_Baru" => $this->safeFloat($param['Value_Parameter']),
                                        "Value_Lama" => $this->safeFloat($getValueHasilLama->Value_Parameter),
                                        "Tanggal" => $tanggalSqlServer,
                                        "Jam" => $jamSqlServer,
                                        "Id_User" => $pengguna->UserId,
                                        "Status_Submit" => "Drafted",
                                    ];

                                   DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')
                                        ->where('No_Sementara', $existingNoSementara)
                                        ->where('Id_Quality_Control', $idQc)
                                        ->where('No_Urut', $idNuTu)
                                        ->update([
                                            'Value_Parameter' => $this->safeFloat($param['Value_Parameter']),
                                            'Tanggal' => $tanggalSqlServer,
                                            'Jam' => $jamSqlServer,
                                            'Id_User' => $pengguna->UserId,
                                        ]);
                                }
                                DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail')->insert($payloadActiviyUjiSampelDetail);

                                $allowInsert = false;
                               
                            } elseif ((float) $existingParam->Value_Parameter === (float) $param['Value_Parameter']) {
                                $matched++;
                            }
                        }
                    }

                    if ($matched === $parameterCount) {
                        $allowInsert = false;
                    }
                }

                if (!$allowInsert) {
                    continue;
                }

                $lastNumber++;
                $newNumber = $prefix . '-' . str_pad($lastNumber, 4, '0', STR_PAD_LEFT);

                $parameterValues = collect($analysis['parameters'])->pluck('Value_Parameter', 'Id_Quality_Control');

                $calculatedResults = [];
                foreach ($analysis['formulas'] as $formula) {
                    $hasilPerhitungan = $this->calculateFormulaServerSide($formula['Rumus'], $parameterValues, $formula['Digit']);
                    $calculatedResults[] = [
                        'Hasil_Perhitungan' => $hasilPerhitungan
                    ];
                }

                $payloadActivityUjiSampelHasil = [];
                $payloadUjiSampeSementara = [];
                foreach ($calculatedResults as $result) {
                    $hasilFloat = $this->safeFloat($result['Hasil_Perhitungan']);
                    $payloadUjiSampeSementara[] = [
                        'Kode_Perusahaan' => '001',
                        'No_Sementara' => $newNumber,
                        'No_Po_Sampel' => $analysis['No_Po_Sampel'],
                        'Id_Jenis_Analisa' => $jenisAnalisa,
                        'Hasil' => $hasilFloat,
                        'Flag_Perhitungan' => 'Y',
                        'Flag_Multi_QrCode' => $analysis['is_multi_print'],
                        'Status' => null,
                        'Tanggal' => $tanggalSqlServer,
                        'Jam' => $jamSqlServer,
                        'Id_User' => $pengguna->UserId,
                    ];

                    $payloadActivityUjiSampelHasil[] = [
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $analysis['No_Po_Sampel'],
                        "Id_Jenis_Analisa" => $jenisAnalisa,
                        "Value_Baru" => $hasilFloat,
                        "Value_Lama" => $hasilFloat,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $pengguna->UserId,
                        "Status_Submit" => "Drafted",
                    ];
                }

                $payloadActiviyUjiSampelDetailSementara = [];
                $payloadActiviyUjiSampelDetail = [];
                foreach ($analysis['parameters'] as $param) {
                    $idDecodedQc = Hashids::connection('custom')->decode($param['Id_Quality_Control']);
                    $idQc = isset($idDecodedQc[0]) ? $idDecodedQc[0] : null;

                    $payloadActiviyUjiSampelDetailSementara[] = [
                        'Kode_Perusahaan' => '001',
                        'No_Sementara' => $newNumber,
                        'Id_Quality_Control' => $idQc,
                        'Value_Parameter' => $this->safeFloat($param['Value_Parameter']),
                        'Tanggal' => $tanggalSqlServer,
                        'Jam' => $jamSqlServer,
                        'Id_User' => $pengguna->UserId,
                    ];

                    $payloadActiviyUjiSampelDetail[] = [
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $analysis['No_Po_Sampel'],
                        "Id_Jenis_Analisa" => $jenisAnalisa,
                        "Id_Quality_Control" => $idQc,
                        "Value_Baru" => $this->safeFloat($param['Value_Parameter']),
                        "Value_Lama" => $this->safeFloat($param['Value_Parameter']),
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $pengguna->UserId,
                        "Status_Submit" => "Drafted",
                    ];
                }

                DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')->insert($payloadUjiSampeSementara);
                DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')->insert($payloadActiviyUjiSampelDetailSementara);
                DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail')->insert($payloadActivityUjiSampelHasil);
                DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail')->insert($payloadActiviyUjiSampelDetail);


                $results[] = [
                    'No_Sementara' => $newNumber
                ];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Data Berhasil Disimpan',
                'result' => $results
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('UjiSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan"
            ], 500);
        }
    }
    
    public function storeNotMultiRumusSementaraNoPerhitunganJe(Request $request)
    {
        $request->validate([
            'analyses' => 'required|array|min:1',
            'analyses.*.No_Po_Sampel' => 'required|string',
            'analyses.*.Id_Jenis_Analisa' => 'required|string',
            'analyses.*.parameters' => 'present|array',
            'analyses.*.parameters.*.Id_Quality_Control' => 'required|string',
            'analyses.*.parameters.*.Value_Parameter' => 'nullable',
        ]);

        DB::beginTransaction();

        try {
            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow;
            $tanggalSqlServer = date('Y-m-d', strtotime($dt));
            $jamSqlServer = date('H:i:s', strtotime($dt));

            $pengguna = Auth::user();
            $userId = $pengguna->UserId;

            $userExists = DB::table('N_EMI_LAB_Users')->where('UserId', $userId)->exists();

            if (!$userExists) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "User dengan ID $userId tidak ditemukan."
                ], 404);
            }

            $prefix = 'TMP-FUS' . date('my');
            $lastNumberRecord = DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')
                ->where('No_Sementara', 'like', $prefix . '-%')
                ->orderBy('No_Sementara', 'desc')
                ->first();

            $lastNumber = $lastNumberRecord ? (int) substr($lastNumberRecord->No_Sementara, -4) : 0;

            $firstAnalysis = $request->analyses[0];
            $idDecodedJenis = Hashids::connection('custom')->decode($firstAnalysis['Id_Jenis_Analisa']);
            $jenisAnalisaGlobal = $idDecodedJenis[0] ?? null;

            $payloadActivityUjiSampel = [
                'Kode_Perusahaan' => '001',
                'No_Po_Sampel' => $firstAnalysis['No_Po_Sampel'],
                'Jenis_Aktivitas' => 'save_draft',
                'Keterangan' => $pengguna->Nama . ' Menyimpan Data Analisa (No Calc) Sebagai Draft',
                'Id_User' => $pengguna->UserId,
                'Tanggal' => $tanggalSqlServer,
                'Jam' => $jamSqlServer,
                'Id_Jenis_Analisa' => $jenisAnalisaGlobal
            ];

            $idLogActivity = DB::table('N_EMI_LIMS_Activity_Uji_Sampel')->insertGetId($payloadActivityUjiSampel, 'Id_Log_Activity');

            $batchUjiSementara = [];
            $batchDetailSementara = [];
            $batchLogHasil = [];
            $batchLogParameter = [];
            $results = [];

            foreach ($request->analyses as $analysis) {
                $idDecoded = Hashids::connection('custom')->decode($analysis['Id_Jenis_Analisa']);
                $jenisAnalisa = $idDecoded[0] ?? null;

                $lastNumber++;
                $newNumber = $prefix . '-' . str_pad($lastNumber, 4, '0', STR_PAD_LEFT);
                $capturedResultValue = null; 

                foreach ($analysis['parameters'] as $param) {
                    $idDecodedQc = Hashids::connection('custom')->decode($param['Id_Quality_Control']);
                    $idQc = $idDecodedQc[0] ?? null;
                    $cleanValue = $this->safeFloat($param['Value_Parameter']);
                    
                    if ($capturedResultValue === null && $cleanValue !== null) {
                        $capturedResultValue = $cleanValue;
                    }

                    $batchDetailSementara[] = [
                        'Kode_Perusahaan' => '001',
                        'No_Sementara' => $newNumber,
                        'Id_Quality_Control' => $idQc,
                        'Value_Parameter' => $cleanValue,
                        'Tanggal' => $tanggalSqlServer,
                        'Jam' => $jamSqlServer,
                        'Id_User' => $pengguna->UserId
                    ];

                    $batchLogParameter[] = [
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $analysis['No_Po_Sampel'],
                        "Id_Jenis_Analisa" => $jenisAnalisa,
                        "Id_Quality_Control" => $idQc,
                        "Value_Baru" => $cleanValue,
                        "Value_Lama" => null,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $pengguna->UserId,
                        "Status_Submit" => "Drafted",
                        "Alasan_Mengubah_Data" => '-'
                    ];
                }

                $finalHeaderValue = $capturedResultValue !== null ? $capturedResultValue : 0;

                $batchUjiSementara[] = [
                    'Kode_Perusahaan' => '001',
                    'No_Sementara' => $newNumber,
                    'No_Po_Sampel' => $analysis['No_Po_Sampel'],
                    'Id_Jenis_Analisa' => $jenisAnalisa,
                    'Hasil' => $finalHeaderValue, 
                    'Tanggal' => $tanggalSqlServer,
                    'Jam' => $jamSqlServer,
                    'Id_User' => $pengguna->UserId,
                ];

                $batchLogHasil[] = [
                    "Kode_Perusahaan" => "001",
                    'Id_Log_Activity_Sampel' => $idLogActivity,
                    "No_Po_Sampel" => $analysis['No_Po_Sampel'],
                    "Id_Jenis_Analisa" => $jenisAnalisa,
                    "Value_Baru" => $finalHeaderValue,
                    "Value_Lama" => 0,
                    "Tanggal" => $tanggalSqlServer,
                    "Jam" => $jamSqlServer,
                    "Id_User" => $pengguna->UserId,
                    "Status_Submit" => "Drafted",
                ];

                $results[] = [
                    'No_Sementara' => $newNumber
                ];
            }

            if (!empty($batchUjiSementara)) {
                foreach (array_chunk($batchUjiSementara, 500) as $chunk) {
                    DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')->insert($chunk);
                }
            }
            
            if (!empty($batchDetailSementara)) {
                foreach (array_chunk($batchDetailSementara, 500) as $chunk) {
                    DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')->insert($chunk);
                }
            }

            if (!empty($batchLogHasil)) {
                foreach (array_chunk($batchLogHasil, 500) as $chunk) {
                    DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail')->insert($chunk);
                }
            }

            if (!empty($batchLogParameter)) {
                foreach (array_chunk($batchLogParameter, 500) as $chunk) {
                    DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail')->insert($chunk);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Data Berhasil Disimpan',
                'result' => $results
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('UjiSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan"
            ], 500);
        }
    }

    public function updateDataForDraft(Request $request)
    {

        $request->validate([
            'analyses' => 'required|array',
            'analyses.*.No_Po_Sampel' => 'required|string',
            'analyses.*.Id_Jenis_Analisa' => 'required|string',
            'analyses.*.message' => 'sometimes|string',
            'analyses.*.reason' => 'sometimes|string',
            'analyses.*.is_multi_print' => 'sometimes|string|in:Y,N',
            'analyses.*.No_Po_Multi_Sampel' => 'nullable|string',
            'analyses.*.parameters' => 'required|array',
            'analyses.*.parameters.*.Id_Quality_Control' => 'required|string',
            'analyses.*.parameters.*.Value_Parameter' => 'required',
            'analyses.*.parameters.*.No_Urut' => 'sometimes|string',
            'analyses.*.parameters.*.RV_INT' => 'sometimes|string',
            'analyses.*.formulas' => 'sometimes|array',
        ], [
            'analyses.required' => 'Tidak ada data analisis yang dikirim.',
        ]);



         DB::beginTransaction();

        try {

            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow; 
            $tanggalSqlServer = date('Y-m-d', strtotime($dt)); 
            $jamSqlServer = date('H:i:s', strtotime($dt));

            $pengguna = Auth::user();
            $userId = $pengguna->UserId;

            $userExists = DB::table('N_EMI_LAB_Users')->where('UserId', $userId)->exists();

            if (!$userExists) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "User dengan ID $userId tidak ditemukan di sistem."
                ], 404);
            }

            $noPoSampel = $request->analyses[0]['No_Po_Sampel'];
            $noMultiPoSampel = $request->analyses[0]['No_Po_Multi_Sampel'];
            $idDecoded = Hashids::connection('custom')->decode($request->analyses[0]['Id_Jenis_Analisa']);
            $jenisAnalisa = isset($idDecoded[0]) ? $idDecoded[0] : null;

            $payloadActivityUjiSampel = [
                        'Kode_Perusahaan' => '001',
                        'No_Po_Sampel' => $noPoSampel,
                        'No_Fak_Sub_Po' => $noMultiPoSampel,
                        'Jenis_Aktivitas' => 'save_update',
                        'Keterangan' => $pengguna->Nama. ' Berhasil Mengupdate Hasil Analisa',
                        'Id_User' => $pengguna->UserId,
                        'Tanggal' => $tanggalSqlServer,
                        'Jam' => $jamSqlServer,
                        'Id_Jenis_Analisa' => $jenisAnalisa
            ];

            $idLogActivity = DB::table('N_EMI_LIMS_Activity_Uji_Sampel')->insertGetId(
                    $payloadActivityUjiSampel,
                    'Id_Log_Activity' 
            );
          
            $results = [];

            foreach ($request->analyses as $analysisData) {
                 foreach ($analysisData['parameters'] as &$param) {
                    if ($param['Value_Parameter'] === '-') {
                        $param['Value_Parameter'] = -999999;
                        $param['Value_Parameter_Lama'] = -999999;
                    } elseif ($param['Value_Parameter'] === '+') {
                        $param['Value_Parameter'] = -88888888;
                        $param['Value_Parameter_Lama'] = -88888888;
                    }
                }
                unset($param);

                $analysis = (object) $analysisData;
                $idDecodedNu = Hashids::connection('custom')->decode($analysis->No_Urut);
                $idNu = isset($idDecodedNu[0]) ? $idDecodedNu[0] : null;
                $rvDecode = Hashids::connection('custom')->decode($analysis->RV_INT);
                $rvNew = isset($rvDecode[0]) ? $rvDecode[0] : null;

                $getDataRvInteger = DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')
                    ->selectRaw('CAST(RV AS INT) AS RV_INT')
                    ->where('No_Sementara', $analysis->No_Sementara)
                    ->where('No_Urut', $idNu)
                    ->first();

                if (!$getDataRvInteger) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Data tidak ditemukan untuk No_Sementara: ' . $analysis->No_Sementara,
                    ], 404);
                }

                if ((int)$rvNew !== (int)$getDataRvInteger->RV_INT) {
                    return response()->json([
                        'success' => false,
                        'status' => 400,
                        'message' => 'Data tidak valid: RV_INT tidak sama dengan yang ada di database.',
                    ], 400);
                }

                $formulas = DB::table('N_EMI_LAB_Perhitungan')
                    ->where('Id_Jenis_Analisa', $jenisAnalisa)
                    ->get();
                
                $parameterValues = [];

                foreach ($analysis->parameters as $parameter) {
                    $idDecodedQc = Hashids::connection('custom')->decode($parameter['Id_Quality_Control']);
                    $idQc = $idDecodedQc[0] ?? null;

                    $getOldValue = DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')
                        ->where('No_Urut', $idNu)
                        ->where('Id_Quality_Control', $idQc)
                        ->value('Value_Parameter');

                    $parameterValues[$idQc] = $parameter['Value_Parameter'];

                    DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail')->insert([
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $analysis->No_Po_Sampel,
                        "Id_Jenis_Analisa" => $jenisAnalisa,
                         "No_Fak_Sub_Po" => $analysis->No_Po_Multi_Sampel,
                        "Id_Quality_Control" => $idQc,
                        "Value_Baru" => $this->safeFloat($parameter["Value_Parameter"]),
                        "Value_Lama" => $this->safeFloat($getOldValue),
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $pengguna->UserId,
                        "Status_Submit" => "Drafted",
                        'Alasan_Mengubah_Data' => $analysis->reason,
                    ]);
                
                    DB::table("N_EMI_LIMS_Uji_Sampel_Detail_Sementara")
                        ->where('No_Urut', $idNu)
                        ->where('Id_Quality_Control', $idQc)
                        ->update(['Value_Parameter' => $this->safeFloat($parameter['Value_Parameter'])]);
                }
                
                $calculatedResults = [];
                foreach ($formulas as $formula) {
                    $result = $this->calculateFormulaServerSide($formula->Rumus, $parameterValues, $formula->Hasil_Perhitungan);
                    $calculatedResults[] = [
                        'Id_Jenis_Analisa' => $formula->Id_Jenis_Analisa,
                        'Hasil_Perhitungan' => $result
                    ];
                }

                foreach ($calculatedResults as $result) {
                    $getValueLama = DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')
                        ->where("No_Sementara", $analysis->No_Sementara)
                        ->value('Hasil');

                    DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail')->insert([
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $analysis->No_Po_Sampel,
                        "No_Fak_Sub_Po" => $analysis->No_Po_Multi_Sampel,
                        "Id_Jenis_Analisa" => $jenisAnalisa,
                        "Value_Baru" => (float) $result['Hasil_Perhitungan'],
                        "Value_Lama" => $this->safeFloat($getValueLama),
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $pengguna->UserId,
                        "Status_Submit" => "Drafted",
                    ]);

                    DB::table("N_EMI_LIMS_Uji_Sampel_Sementara")
                        ->where('No_Sementara', $analysis->No_Sementara)
                        ->update(['Hasil' => $this->safeFloat($result['Hasil_Perhitungan'])]);
                }

                $results[] = [
                    'No_Sementara' => $analysis->No_Sementara,
                    'updated_parameters' => $parameterValues
                ];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => "Data Berhasil Di Update",
                'result' => $results
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('UjiSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan"
            ], 500);
        }

    }
    
    public function updateDataForDraftNotMultiQr(Request $request)
    {
        $request->validate([
            'analyses' => 'required|array',
        ], [
            'analyses.required' => 'Tidak ada data analisis yang dikirim.',
        ]);

        DB::beginTransaction();

        try {
            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow;
            $tanggalSqlServer = date('Y-m-d', strtotime($dt));
            $jamSqlServer = date('H:i:s', strtotime($dt));

            $pengguna = Auth::user();
            $userId = $pengguna->UserId;

            $userExists = DB::table('N_EMI_LAB_Users')->where('UserId', $userId)->exists();

            if (!$userExists) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "User dengan ID $userId tidak ditemukan di sistem."
                ], 404);
            }

            $firstAnalysis = $request->analyses[0];
            $noPoSampel = $firstAnalysis['No_Po_Sampel'];
            $idDecoded = Hashids::connection('custom')->decode($firstAnalysis['Id_Jenis_Analisa']);
            $jenisAnalisa = $idDecoded[0] ?? null;

            $payloadActivityUjiSampel = [
                'Kode_Perusahaan' => '001',
                'No_Po_Sampel' => $noPoSampel,
                'Jenis_Aktivitas' => 'save_update',
                'Keterangan' => "$pengguna->Nama Berhasil Mengupdate Hasil Analisa",
                'Id_User' => $pengguna->UserId,
                'Tanggal' => $tanggalSqlServer,
                'Jam' => $jamSqlServer,
                'Id_Jenis_Analisa' => $jenisAnalisa
            ];

            $idLogActivity = DB::table('N_EMI_LIMS_Activity_Uji_Sampel')->insertGetId($payloadActivityUjiSampel, 'Id_Log_Activity');

            $listNoSementara = array_column($request->analyses, 'No_Sementara');
            
            $existingDetails = DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')
                ->whereIn('No_Sementara', $listNoSementara)
                ->select('No_Sementara', 'Id_Quality_Control', 'Value_Parameter', 'No_Urut')
                ->get()
                ->groupBy('No_Sementara');
            
            $existingHeaders = DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')
                ->whereIn('No_Sementara', $listNoSementara)
                ->pluck('Hasil', 'No_Sementara');

            $results = [];

            foreach ($request->analyses as $analysisData) {
                $analysis = (object) $analysisData;
                $idDecodedNu = Hashids::connection('custom')->decode($analysis->No_Urut ?? '');
                $idNu = $idDecodedNu[0] ?? null;

                $currentDetails = $existingDetails[$analysis->No_Sementara] ?? collect([]);
                $updatedValueForHeader = 0;

                foreach ($analysisData['parameters'] as $parameter) {
                    $idDecodedQc = Hashids::connection('custom')->decode($parameter['Id_Quality_Control']);
                    $idQc = $idDecodedQc[0] ?? null;

                    $valueBaru = $parameter['Value_Parameter'];
                    
                    $oldData = $currentDetails->where('Id_Quality_Control', $idQc)->first();
                    $valueLama = $oldData ? $oldData->Value_Parameter : null;
                    
                    if (!$idNu && $oldData) {
                        $idNu = $oldData->No_Urut;
                    }

                    DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail')->insert([
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $analysis->No_Po_Sampel,
                        "Id_Jenis_Analisa" => $jenisAnalisa,
                        "Id_Quality_Control" => $idQc,
                        "Value_Baru" => $this->safeFloat($valueBaru),
                        "Value_Lama" => $this->safeFloat($valueLama),
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $pengguna->UserId,
                        "Status_Submit" => "Drafted",
                        'Alasan_Mengubah_Data' => $analysis->reason ?? '-',
                    ]);

                    $queryUpdate = DB::table("N_EMI_LIMS_Uji_Sampel_Detail_Sementara");
                    if ($idNu) {
                        $queryUpdate->where('No_Urut', $idNu);
                    } else {
                        $queryUpdate->where('No_Sementara', $analysis->No_Sementara);
                    }
                    
                    $queryUpdate->where('Id_Quality_Control', $idQc)
                        ->update(['Value_Parameter' => $this->safeFloat($valueBaru)]);

                    $updatedValueForHeader = $valueBaru;
                }

                $hasilLama = $existingHeaders[$analysis->No_Sementara] ?? 0;

                DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail')->insert([
                    "Kode_Perusahaan" => "001",
                    'Id_Log_Activity_Sampel' => $idLogActivity,
                    "No_Po_Sampel" => $analysis->No_Po_Sampel,
                    "Id_Jenis_Analisa" => $jenisAnalisa,
                    "Value_Baru" => $this->safeFloat($updatedValueForHeader),
                    "Value_Lama" => $this->safeFloat($hasilLama),
                    "Tanggal" => $tanggalSqlServer,
                    "Jam" => $jamSqlServer,
                    "Id_User" => $pengguna->UserId,
                    "Status_Submit" => "Drafted",
                ]);

                DB::table("N_EMI_LIMS_Uji_Sampel_Sementara")
                    ->where('No_Sementara', $analysis->No_Sementara)
                    ->update(['Hasil' => $this->safeFloat($updatedValueForHeader)]);

                $results[] = [
                    'No_Sementara' => $analysis->No_Sementara,
                    'Result' => $updatedValueForHeader
                ];
            }
            DB::commit();
            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => "Data berhasil disimpan.",
                'result' => $results
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('UjiSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan"
            ], 500);
        }
    }

    public function updateDataForDraftNoRumusNotMultiQr(Request $request)
    {
        $request->validate([
            'analyses' => 'required|array',
        ], [
            'analyses.required' => 'Tidak ada data analisis yang dikirim.',
        ]);

        DB::beginTransaction();

        try {
            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow;
            $tanggalSqlServer = date('Y-m-d', strtotime($dt));
            $jamSqlServer = date('H:i:s', strtotime($dt));

            $pengguna = Auth::user();
            $userId = $pengguna->UserId;

            $userExists = DB::table('N_EMI_LAB_Users')->where('UserId', $userId)->exists();

            if (!$userExists) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "User dengan ID $userId tidak ditemukan."
                ], 404);
            }

            $firstRaw = $request->analyses[0];
            $idDecodedJenis = Hashids::connection('custom')->decode($firstRaw['Id_Jenis_Analisa']);
            $jenisAnalisaGlobal = $idDecodedJenis[0] ?? null;

            $idLogActivity = DB::table('N_EMI_LIMS_Activity_Uji_Sampel')->insertGetId([
                'Kode_Perusahaan' => '001',
                'No_Po_Sampel' => $firstRaw['No_Po_Sampel'],
                'Jenis_Aktivitas' => 'save_update',
                'Keterangan' => "$pengguna->Nama Berhasil Mengupdate Hasil Analisa",
                'Id_User' => $pengguna->UserId,
                'Tanggal' => $tanggalSqlServer,
                'Jam' => $jamSqlServer,
                'Id_Jenis_Analisa' => $jenisAnalisaGlobal
            ], 'Id_Log_Activity');

            $listNoSementara = [];
            $listNoUrut = [];

            foreach ($request->analyses as $a) {
                $listNoSementara[] = $a['No_Sementara'];
                if (isset($a['No_Urut'])) {
                    $decodedNu = Hashids::connection('custom')->decode($a['No_Urut']);
                    if (isset($decodedNu[0])) {
                        $listNoUrut[] = $decodedNu[0];
                    }
                }
            }

            $existingDetails = DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')
                ->whereIn('No_Urut', $listNoUrut)
                ->get()
                ->keyBy('No_Urut');

            $existingHeaders = DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')
                ->whereIn('No_Sementara', $listNoSementara)
                ->get()
                ->keyBy('No_Sementara');

            $batchLogParameter = [];
            $batchLogHasil = [];
            $updateListDetail = [];
            $updateListHeader = [];
            $results = [];

            foreach ($request->analyses as $analysisData) {
                $noSementara = $analysisData['No_Sementara'];
                $idDecodedNu = Hashids::connection('custom')->decode($analysisData['No_Urut']);
                $idNu = $idDecodedNu[0] ?? null;
                $idDecodedJenis = Hashids::connection('custom')->decode($analysisData['Id_Jenis_Analisa']);
                $jenisAnalisa = $idDecodedJenis[0] ?? null;

                $dbRow = $existingDetails->get($idNu);
                $finalValueToSave = null;
                $updatedParamsMap = [];

                foreach ($analysisData['parameters'] as $param) {
                    $idDecodedQc = Hashids::connection('custom')->decode($param['Id_Quality_Control']);
                    $idQc = $idDecodedQc[0] ?? null;

                    $newValue = $this->safeFloat($param['Value_Parameter']);
                    $finalValueToSave = $newValue;
                    $oldValue = $dbRow ? $dbRow->Value_Parameter : null;

                    $batchLogParameter[] = [
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $analysisData['No_Po_Sampel'],
                        "Id_Jenis_Analisa" => $jenisAnalisa,
                        "Id_Quality_Control" => $idQc,
                        "Value_Baru" => $newValue,
                        "Value_Lama" => $this->safeFloat($oldValue),
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $pengguna->UserId,
                        "Status_Submit" => "Drafted",
                        'Alasan_Mengubah_Data' => $analysisData['reason'] ?? '-',
                    ];

                    $updateListDetail[] = [
                        'conditions' => ['No_Urut' => $idNu, 'Id_Quality_Control' => $idQc],
                        'values' => [
                            'Value_Parameter' => $newValue,
                        ]
                    ];

                    $updatedParamsMap[$idQc] = $newValue;
                }

                $oldHeader = $existingHeaders->get($noSementara);
                $oldHeaderVal = $oldHeader ? $oldHeader->Hasil : null;

                $batchLogHasil[] = [
                    "Kode_Perusahaan" => "001",
                    'Id_Log_Activity_Sampel' => $idLogActivity,
                    "No_Po_Sampel" => $analysisData['No_Po_Sampel'],
                    "Id_Jenis_Analisa" => $jenisAnalisa,
                    "Value_Baru" => $finalValueToSave,
                    "Value_Lama" => $this->safeFloat($oldHeaderVal),
                    "Tanggal" => $tanggalSqlServer,
                    "Jam" => $jamSqlServer,
                    "Id_User" => $pengguna->UserId,
                    "Status_Submit" => "Drafted",
                ];

                $updateListHeader[] = [
                    'conditions' => ['No_Sementara' => $noSementara],
                    'values' => [
                        'Hasil' => $finalValueToSave,
                        'Tanggal' => $tanggalSqlServer,
                        'Jam' => $jamSqlServer,
                        'Id_User' => $pengguna->UserId,
                    ]
                ];

                $results[] = [
                    'No_Sementara' => $noSementara,
                    'updated_parameters' => $updatedParamsMap
                ];
            }

            if (!empty($batchLogParameter)) {
                DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail')->insert($batchLogParameter);
            }
            if (!empty($batchLogHasil)) {
                DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail')->insert($batchLogHasil);
            }

            foreach ($updateListDetail as $item) {
                DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')
                    ->where($item['conditions'])
                    ->update($item['values']);
            }

            foreach ($updateListHeader as $item) {
                DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')
                    ->where($item['conditions'])
                    ->update($item['values']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => "Data dari semua baris berhasil disimpan.",
                'result' => $results
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('UjiSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan"
            ], 500);
        }
    }

    public function deleteDataForDraft(Request $request, $no_sementara)
    {
        $request->validate([
            'analyses' => 'required|array',
        ], [
            'analyses.required' => 'Tidak ada data analisis yang dikirim.',
        ]);

        DB::beginTransaction();

        try {
            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow; 
            $tanggalSqlServer = date('Y-m-d', strtotime($dt)); 
            $jamSqlServer = date('H:i:s', strtotime($dt));

            $pengguna = Auth::user();
            $userId = $pengguna->UserId;

            $userExists = DB::table('N_EMI_LAB_Users')->where('UserId', $userId)->exists();

            if (!$userExists) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "User dengan ID $userId tidak ditemukan di sistem."
                ], 404);
            }

            foreach ($request->analyses as $analysisData) {
                $analysis = (object) $analysisData;

                $noPoSampel = $analysis->No_Po_Sampel;
                $noFakSubPo = $analysis->No_Po_Multi_Sampel ?? null;
                $reason = $analysis->reason ?? '-';

                $idDecoded = Hashids::connection('custom')->decode($analysis->Id_Jenis_Analisa);
                $jenisAnalisa = $idDecoded[0] ?? null;

                $idLogActivity = DB::table('N_EMI_LIMS_Activity_Uji_Sampel')->insertGetId([
                    'Kode_Perusahaan' => '001',
                    'No_Po_Sampel' => $noPoSampel,
                    'No_Fak_Sub_Po' => $noFakSubPo,
                    'Jenis_Aktivitas' => 'save_delete',
                    'Keterangan' => "$pengguna->Nama Menghapus draft hasil analisa",
                    'Id_User' => $pengguna->UserId,
                    'Tanggal' => $tanggalSqlServer,
                    'Jam' => $jamSqlServer,
                    'Id_Jenis_Analisa' => $jenisAnalisa
                ], 'Id_Log_Activity');

                $parameterDb = DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')
                    ->where('No_Sementara', $no_sementara)
                    ->get()
                    ->keyBy('Id_Quality_Control');

                $parameterFrontend = collect($analysis->parameters);

                $paramLogs = [];
                foreach ($parameterFrontend as $param) {
                    $idDecodedQc = Hashids::connection('custom')->decode($param['Id_Quality_Control']);
                    $idQc = $idDecodedQc[0] ?? null;

                    $paramDb = $parameterDb[$idQc] ?? null;

                    $paramLogs[] = [
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $noPoSampel,
                        "No_Fak_Sub_Po" => $noFakSubPo,
                        "Id_Jenis_Analisa" => $jenisAnalisa,
                        "Id_Quality_Control" => $idQc,
                        "Value_Baru" => $this->safeFloat($paramDb->Value_Parameter ?? 0),
                        "Value_Lama" => $this->safeFloat($paramDb->Value_Parameter ?? 0),
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $pengguna->UserId,
                        "Status_Submit" => "Deleted",
                        'Alasan_Mengubah_Data' => $reason,
                    ];
                }

                DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail')->insert($paramLogs);

                foreach ($analysis->formulas as $formula) {
                    $hasilLama = DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')
                                ->where('No_Sementara', $no_sementara)
                                ->value('Hasil');

                    DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail')->insert([
                        'Kode_Perusahaan' => '001',
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        'No_Po_Sampel' => $noPoSampel,
                        'No_Fak_Sub_Po' => $noFakSubPo,
                        'Id_Jenis_Analisa' => $jenisAnalisa,
                        'Value_Lama' => $this->safeFloat($hasilLama),
                        'Value_Baru' => $this->safeFloat($hasilLama),
                        'Tanggal' => $tanggalSqlServer,
                        'Jam' => $jamSqlServer,
                        'Id_User' => $pengguna->UserId,
                        'Status_Submit' => 'Deleted'
                    ]);
                }           
            }

            DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')->where('No_Sementara', $no_sementara)->delete();
            DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')->where('No_Sementara', $no_sementara)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Data Berhasil Dihapus dan Aktivitas Dicatat.",
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('UjiSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan"
            ], 500);
        }
    }

    public function deleteDataForDraftNotMultiQrCode(Request $request)
    {
        $request->validate([
            'analyses' => 'required|array',
        ]);

        DB::beginTransaction();

        try {

            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow; 
            $tanggalSqlServer = date('Y-m-d', strtotime($dt)); 
            $jamSqlServer = date('H:i:s', strtotime($dt));
            $pengguna = Auth::user();
            $userId = $pengguna->UserId;

            $userExists = DB::table('N_EMI_LAB_Users')->where('UserId', $userId)->exists();

            if (!$userExists) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "User dengan ID $userId tidak ditemukan di sistem."
                ], 404);
            }

            foreach ($request->analyses as $analysisData) {
                $analysis = (object) $analysisData;

                $noSementara = $analysis->No_Sementara;
                $noPoSampel = $analysis->No_Po_Sampel;
                $noFakSubPo = $analysis->No_Po_Multi_Sampel ?? null;
                $reason = $analysis->reason ?? '-';

                $idDecoded = Hashids::connection('custom')->decode($analysis->Id_Jenis_Analisa);
                $jenisAnalisa = $idDecoded[0] ?? null;

                $idLogActivity = DB::table('N_EMI_LIMS_Activity_Uji_Sampel')->insertGetId([
                    'Kode_Perusahaan' => '001',
                    'No_Po_Sampel' => $noPoSampel,
                    'Jenis_Aktivitas' => 'save_delete',
                    'Keterangan' => "$pengguna->Nama Menghapus draft hasil analisa",
                    'Id_User' => $pengguna->UserId,
                    'Tanggal' => $tanggalSqlServer,
                    'Jam' => $jamSqlServer,
                    'Id_Jenis_Analisa' => $jenisAnalisa
                ], 'Id_Log_Activity');

                // Ambil semua parameter lama dari database
                $parameterDb = DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')
                    ->where('No_Sementara', $noSementara)
                    ->get()
                    ->keyBy('Id_Quality_Control');

                $parameterFrontend = collect($analysis->parameters);

                // Parameter logs
                $paramLogs = [];

                foreach ($parameterFrontend as $param) {
                    $idDecodedQc = Hashids::connection('custom')->decode($param['Id_Quality_Control']);
                    $idQc = $idDecodedQc[0] ?? null;

                    $paramDb = $parameterDb[$idQc] ?? null;

                    $paramLogs[] = [
                        'Kode_Perusahaan' => '001',
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        'No_Po_Sampel' => $noPoSampel,
                        'Id_Jenis_Analisa' => $jenisAnalisa,
                        'Id_Quality_Control' => $idQc,
                        'Value_Lama' => $this->safeFloat($paramDb->Value_Parameter ?? 0),
                        'Value_Baru' => $this->safeFloat($paramDb->Value_Parameter ?? 0),
                        'Tanggal' => $tanggalSqlServer,
                        'Jam' => $jamSqlServer,
                        'Id_User' => $pengguna->UserId,
                        'Status_Submit' => 'Deleted',
                        'Alasan_Mengubah_Data' => $reason
                    ];
                }

                DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail')->insert($paramLogs);

                foreach ($analysis->formulas as $formula) {
                    $hasilLama = DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')
                        ->where('No_Sementara', $noSementara)
                        ->value('Hasil');

                    DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail')->insert([
                        'Kode_Perusahaan' => '001',
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        'No_Po_Sampel' => $noPoSampel,
                        'No_Fak_Sub_Po' => $noFakSubPo,
                        'Id_Jenis_Analisa' => $jenisAnalisa,
                        'Value_Lama' => $this->safeFloat($hasilLama),
                        'Value_Baru' => $this->safeFloat($hasilLama),
                        'Tanggal' => $tanggalSqlServer,
                        'Jam' => $jamSqlServer,
                        'Id_User' => $pengguna->UserId,
                        'Status_Submit' => 'Deleted'
                    ]);
                }

                DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')->where('No_Sementara', $noSementara)->delete();
                DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->delete();
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Data berhasil dihapus dan semua aktivitas dicatat.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('UjiSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan"
            ], 500);
        }
    }
   

    public function deleteDataForDraftNoRumusNotMultiQrCode(Request $request)
    {
        $request->validate([
            'analyses' => 'required|array',
        ]);

        DB::beginTransaction();

        try {

            $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
            $dt = $waktuServer[0]->DateTimeNow; 
            $tanggalSqlServer = date('Y-m-d', strtotime($dt)); 
            $jamSqlServer = date('H:i:s', strtotime($dt));

            $kodePerusahaan = '001';
            $tanggal = date('Y-m-d');
            $jam = date('H:i:s');
            $pengguna = Auth::user();
            $userId = $pengguna->UserId;

            $userExists = DB::table('N_EMI_LAB_Users')->where('UserId', $userId)->exists();

            if (!$userExists) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "User dengan ID $userId tidak ditemukan di sistem."
                ], 404);
            }

            $activityParams = [];
            $activityResults = [];
            $allNoSementara = [];
            $allLogIds = [];

            foreach ($request->analyses as $analysisData) {
                $analysis = (object) $analysisData;

                $noSementara = $analysis->No_Sementara;
                $noPoSampel = $analysis->No_Po_Sampel;
                $noFakSubPo = $analysis->No_Po_Multi_Sampel ?? null;
                $reason = $analysis->reason ?? '-';

                $idDecoded = Hashids::connection('custom')->decode($analysis->Id_Jenis_Analisa);
                $jenisAnalisa = $idDecoded[0] ?? null;

                // Insert aktivitas (disimpan log ID untuk relasi)
                $idLogActivity = DB::table('N_EMI_LAB_Activity_Uji_Sampel')->insertGetId([
                    'Kode_Perusahaan' => $kodePerusahaan,
                    'No_Po_Sampel' => $noPoSampel,
                    'Jenis_Aktivitas' => 'save_delete',
                    'Keterangan' => "$pengguna->Nama Menghapus draft hasil analisa",
                    'Id_User' => $pengguna->UserId,
                    'Tanggal' => $tanggalSqlServer,
                    'Jam' => $jamSqlServer,
                    'Id_Jenis_Analisa' => $jenisAnalisa
                ], 'Id_Log_Activity');

                $allLogIds[] = $idLogActivity;
                $allNoSementara[] = $noSementara;

                // Ambil semua parameter database sekaligus per loop utama (N+1 dihindari)
                $parameterDb = DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')
                    ->where('No_Sementara', $noSementara)
                    ->get()
                    ->keyBy('Id_Quality_Control');

                // Siapkan log parameter
                foreach ($analysis->parameters as $param) {
                    $idDecodedQc = Hashids::connection('custom')->decode($param['Id_Quality_Control']);
                    $idQc = $idDecodedQc[0] ?? null;

                    $valueLama = $this->safeFloat($parameterDb[$idQc]->Value_Parameter ?? 0);

                    $activityParams[] = [
                        'Kode_Perusahaan' => $kodePerusahaan,
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        'No_Po_Sampel' => $noPoSampel,
                        'Id_Jenis_Analisa' => $jenisAnalisa,
                        'Id_Quality_Control' => $idQc,
                        'Value_Lama' => $valueLama,
                        'Value_Baru' => $valueLama,
                        'Tanggal' => $tanggalSqlServer,
                        'Jam' => $jamSqlServer,
                        'Id_User' => $pengguna->UserId,
                        'Status_Submit' => 'Deleted',
                        'Alasan_Mengubah_Data' => $reason
                    ];
                }

                // Ambil hasil lama hanya sekali untuk tiap formula loop (hindari query berulang)
                $hasilLama = DB::table('N_EMI_LAB_Uji_Sampel_Sementara')
                    ->where('No_Sementara', $noSementara)
                    ->value('Hasil');

                $safeHasil = $this->safeFloat($hasilLama);

                foreach ($analysis->formulas as $_) {
                    $activityResults[] = [
                        'Kode_Perusahaan' => $kodePerusahaan,
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        'No_Po_Sampel' => $noPoSampel,
                        'No_Fak_Sub_Po' => $noFakSubPo,
                        'Id_Jenis_Analisa' => $jenisAnalisa,
                        'Value_Lama' => $safeHasil,
                        'Value_Baru' => $safeHasil,
                        'Tanggal' => $tanggalSqlServer,
                        'Jam' => $jamSqlServer,
                        'Id_User' => $pengguna->UserId,
                        'Status_Submit' => 'Deleted'
                    ];
                }
            }

            // Bulk insert log parameter dan hasil
            if (!empty($activityParams)) {
                DB::table('N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail')->insert($activityParams);
            }

            if (!empty($activityResults)) {
                DB::table('N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail')->insert($activityResults);
            }

            // Bulk delete data berdasarkan semua No_Sementara yang terkumpul
            DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')->whereIn('No_Sementara', $allNoSementara)->delete();
            DB::table('N_EMI_LAB_Uji_Sampel_Sementara')->whereIn('No_Sementara', $allNoSementara)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Data berhasil dihapus dan semua aktivitas dicatat.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('UjiSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan"
            ], 500);
        }
    }
    
    public function storeConfirmedUjiSampel(Request $request)
    {
        $request->validate([
            'analyses' => 'required|array',
        ]);

        $userId = Auth::user()->UserId;

            $userExists = DB::table('N_EMI_LAB_Users')->where('UserId', $userId)->exists();

            if (!$userExists) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "User dengan ID $userId tidak ditemukan di sistem."
                ], 404);
            }
    
        foreach ($request->analyses as $analisis){
            $analysis = (object) $analisis;

            if($analysis->Flag_Multi_QrCode === 'Y'){
                DB::beginTransaction();

                try {
                    DB::table('N_EMI_LAB_Uji_Sampel')
                            ->where('No_Po_Sampel', $analysis->No_Po_Sampel)
                            ->where('No_Fak_Sub_Po', $analysis->No_Fak_Sub_Po)
                            ->where('Id_Jenis_Analisa', $analysis->Id_Jenis_Analisa) 
                            ->whereNull('Flag_Selesai')
                            ->update(['Flag_Selesai' => 'Y']);
                            
                    DB::commit();

                    return response()->json([
                        'success' => true,
                        'message' => 'Data berhasil diupdate dan status penyelesaian telah diperiksa.'
                    ], 200);

                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::channel('UjiSampelController')->error('Error: ' . $e->getMessage());
                    return response()->json([
                        'success' => false,
                        'status' => 500,
                        'message' => "Terjadi Kesalahan"
                    ], 500);
                }
            }else {
                DB::beginTransaction();

                try {
                    DB::table('N_EMI_LAB_Uji_Sampel')
                            ->where('No_Po_Sampel', $analysis->No_Po_Sampel)
                            ->where('Id_Jenis_Analisa', $analysis->Id_Jenis_Analisa) 
                            ->whereNull('Flag_Selesai')
                            ->update(['Flag_Selesai' => 'Y']);
                
                    DB::commit();
                    return response()->json([
                        'success' => true,
                        'message' => 'Data berhasil diupdate dan status penyelesaian telah diperiksa.'
                    ], 200);

                }catch(\Exception $e){
                    DB::rollBack();
                    Log::channel('UjiSampelController')->error('Error: ' . $e->getMessage());
                    return response()->json([
                        'success' => false,
                        'status' => 500,
                        'message' => "Terjadi Kesalahan"
                    ], 500);
                }
            }
        }
    }

    public function storeConfirmedUjiSampelV2(Request $request)
    {
        $request->validate([
            'analyses' => 'required|array',
        ]);

        $waktuServer = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
        $dt = $waktuServer[0]->DateTimeNow;
        $tanggalSqlServer = date('Y-m-d', strtotime($dt));
        $jamSqlServer = date('H:i:s', strtotime($dt));

        $userId = Auth::user()->UserId;

        $userExists = DB::table('N_EMI_LAB_Users')->where('UserId', $userId)->exists();

        if (!$userExists) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => "User dengan ID $userId tidak ditemukan di sistem."
            ], 404);
        }

        foreach ($request->analyses as $analisis) {
            $analysis = (object) $analisis;

    
            if ($analysis->Flag_Multi_QrCode === 'Y') {
                $adaTidakLayak = DB::table('N_EMI_LIMS_Uji_Sampel')
                        ->where('No_Po_Sampel', $analysis->No_Po_Sampel)
                        ->where('No_Fak_Sub_Po', $analysis->No_Fak_Sub_Po)
                        ->where('Id_Jenis_Analisa', $analysis->Id_Jenis_Analisa)
                        ->whereNull('Status') 
                        ->whereNull('Flag_Resampling')
                        ->where('Flag_Layak', 'T') 
                        ->exists();

                    $statusKelayakan = $adaTidakLayak ? 'T' : 'Y';

                    DB::beginTransaction();

                    try {
                        DB::table('N_EMI_LIMS_Uji_Sampel')
                            ->where('No_Po_Sampel', $analysis->No_Po_Sampel)
                            ->where('No_Fak_Sub_Po', $analysis->No_Fak_Sub_Po)
                            ->where('Id_Jenis_Analisa', $analysis->Id_Jenis_Analisa)
                            ->whereNull('Status')
                            ->whereNull('Flag_Resampling')
                            ->update([
                                'Status_Keputusan_Sampel' => 'terima',
                                'Flag_Selesai' => 'Y',
                            ]);

                        $payloadUjiFinalDetail = [
                            'No_Sampel' => $analysis->No_Po_Sampel,
                            'No_Sub_Sampel' => $analysis->No_Fak_Sub_Po,
                            'Id_Jenis_Analisa' => $analysis->Id_Jenis_Analisa,
                            'Tahapan_Ke' => $analysis->Tahapan_Ke,
                            'Flag_Layak' => $statusKelayakan,
                            'Tanggal' => $tanggalSqlServer,
                            'Jam' => $jamSqlServer,
                            'Id_User' => $userId
                        ];
                        DB::table('N_EMI_LIMS_Hasil_Uji_Validasi_Detail_Final')->insert($payloadUjiFinalDetail);
                        DB::commit();
                        return ResponseHelper::success(null, "Data berhasil diupdate dan status penyelesaian telah diperiksa.", 200);
                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::channel('UjiSampelController')->error('Error: ' . $e->getMessage());
                        return response()->json([
                            'success' => false,
                            'status' => 500,
                            'message' => "Terjadi Kesalahan"
                        ], 500);
                    }
            } else {
                $adaTidakLayak = DB::table('N_EMI_LIMS_Uji_Sampel')
                        ->where('No_Po_Sampel', $analysis->No_Po_Sampel)
                        ->where('No_Fak_Sub_Po', $analysis->No_Fak_Sub_Po)
                        ->where('Id_Jenis_Analisa', $analysis->Id_Jenis_Analisa)
                        ->whereNull('Status') 
                        ->where('Flag_Layak', 'T') 
                        ->exists();

                    $statusKelayakan = $adaTidakLayak ? 'T' : 'Y';

                    DB::beginTransaction();

                    try {
                        DB::table('N_EMI_LIMS_Uji_Sampel')
                            ->where('No_Po_Sampel', $analysis->No_Po_Sampel)
                            ->where('Id_Jenis_Analisa', $analysis->Id_Jenis_Analisa)
                            ->whereNull('Status')
                            ->update([
                                'Status_Keputusan_Sampel' => 'terima',
                                'Flag_Selesai' => 'Y',
                            ]);

                        $payloadUjiFinalDetail = [
                            'No_Sampel' => $analysis->No_Po_Sampel,
                            'No_Sub_Sampel' => $analysis->No_Fak_Sub_Po,
                            'Id_Jenis_Analisa' => $analysis->Id_Jenis_Analisa,
                            'Tahapan_Ke' => $analysis->Tahapan_Ke,
                            'Flag_Layak' => $statusKelayakan,
                            'Tanggal' => $tanggalSqlServer,
                            'Jam' => $jamSqlServer,
                            'Id_User' => $userId
                        ];

                        DB::table('N_EMI_LIMS_Hasil_Uji_Validasi_Detail_Final')->insert($payloadUjiFinalDetail);
                        DB::commit();
                        return ResponseHelper::success(null, "Data berhasil diupdate dan status penyelesaian telah diperiksa.", 200);
                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::channel('UjiSampelController')->error('Error: ' . $e->getMessage());
                        return response()->json([
                            'success' => false,
                            'status' => 500,
                            'message' => "Terjadi Kesalahan"
                        ], 500);
                    }
            }
        }
    }

    public function finalisasiNoPoSampel($no_sampel)
    {
        $userId = Auth::user()->UserId;

            $userExists = DB::table('N_EMI_LAB_Users')->where('UserId', $userId)->exists();

            if (!$userExists) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "User dengan ID $userId tidak ditemukan di sistem."
                ], 404);
            }

        try {
          
            if (!$no_sampel || !is_string($no_sampel)) {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => 'Nomor sampel tidak valid'
                ], 400);
            }

            DB::beginTransaction();

            $exists = DB::table('N_EMI_LAB_PO_Sampel')
            ->where('No_Sampel', $no_sampel)
            ->whereNull('Flag_Selesai')
            ->exists();

            if (!$exists) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Data sampel tidak ditemukan'
                ], 404);
            }

            // Update dan cek apakah berhasil
            $updated = DB::table('N_EMI_LAB_PO_Sampel')
            ->where('No_Sampel', $no_sampel)
            ->whereNull('Flag_Selesai')
            ->update([
                'Flag_Selesai' => 'Y'
            ]);

            if ($updated === 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => 'Gagal memperbarui data. Mungkin sudah selesai.'
                ], 400);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Data berhasil difinalisasi'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('UjiSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan"
            ], 500);
        }
    }

    // versi not otpimasi codignan
    public function getDetailSampelUji($no_sampel) 
    {
        $parts = explode('-', $no_sampel);
        $base_no_sampel = count($parts) > 2 ? implode('-', array_slice($parts, 0, -1)) : $no_sampel;

        $sampelRow = DB::table('N_EMI_LAB_PO_Sampel as q')
            ->leftJoin('EMI_Master_Mesin as m', 'q.Id_Mesin', '=', 'm.Id_Master_Mesin')
            ->leftJoin('N_EMI_View_Barang as b', 'q.Kode_Barang', '=', 'b.Kode_Barang')
            ->where('q.No_Sampel', '=', $base_no_sampel)
            ->select(
                'q.id as sampel_id',  'q.Berat_Sampel', 'q.Kode_Perusahaan', 'q.No_Sampel', 'q.No_Po', 'q.Kode_Barang',
                'q.Tanggal', 'q.Jumlah_Pcs', 'q.Jam', 'q.No_Split_Po', 'q.No_Batch', 'q.Keterangan',
                'm.Nama_Mesin', 'm.Seri_Mesin', 'm.Flag_Multi_Qrcode', 'm.Jumlah_Print_QRCode',
                'b.Nama as Nama_Barang',
                'q.Id_Mesin'
            )
            ->first();

        if (!$sampelRow) {
            return response()->json(['success' => false, 'status' => 404, 'message' => 'Data tidak ditemukan'], 404);
        }

        $checkedSelesai = DB::table('N_EMI_LAB_PO_Sampel')
            ->whereNull("Status")
            ->where('No_Sampel', $base_no_sampel)
            ->where('Flag_Selesai', 'Y')
            ->first();

        if ($checkedSelesai) {
            return response()->json([
                'success' => true,
                'status' => 200,
                'finished' => true,
                'message' => 'Untuk Nomor Sampel ' . $base_no_sampel . ' Sudah Ditutup, Terimakasih Atas Kinerja Kerasnya, Tetap Semangat Dan Jaga Kondisi Ya ☺️'
            ], 200);
        }

        // Analisa Default
        $analisaList = DB::table('N_EMI_LAB_Barang_Analisa as ba')
            ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ba.Id_Jenis_Analisa', '=', 'ja.id')
            ->leftJoin('N_EMI_LAB_Mesin_Analisa as ma', 'ja.Id_Mesin', '=', 'ma.No_Urut')
            ->where('ba.Kode_Barang', $sampelRow->Kode_Barang)
            ->where('ba.Id_Master_Mesin', $sampelRow->Id_Mesin)
            ->where('ba.Id_User', Auth::user()->UserId)
            ->select(
                'ja.id as analisa_id',
                'ja.Kode_Analisa',
                'ja.Jenis_Analisa',
                'ma.Nama_Mesin as Nama_Mesin_Analisa'
            )
            ->get();

        // Analisa Khusus
        $getAnalisa = DB::table('N_EMI_LAB_PO_Sampel')
            ->whereNull('Status')
            ->where('No_Sampel', $base_no_sampel)
            ->where('Flag_Khusus', 'Y')
            ->get();

        $getAnalsiaOpsional = DB::table('N_EMI_LAB_Jenis_Analisa as ja')
            ->leftJoin('N_EMI_LAB_Mesin_Analisa as ma', 'ja.Id_Mesin', '=', 'ma.No_Urut')
            ->whereIn('ja.id', $getAnalisa->pluck('Id_Jenis_Analisa_Khusus'))
            ->select(
                'ja.id as analisa_id',
                'ja.Kode_Analisa',
                'ja.Jenis_Analisa',
                'ma.Nama_Mesin as Nama_Mesin_Analisa'
            )
            ->get();

        // Analisa Berkala
        $getAnalisaBerkala = collect();
        if ($getAnalisa->isNotEmpty()) {
            $getAnalisaBerkala = DB::table('N_EMI_LAB_Jenis_Analisa_Berkala as berkala')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'berkala.Id_Jenis_Analisa', '=', 'ja.id')
                ->join('N_EMI_LAB_Jenis_Analisa as sub_ja', 'berkala.Id_Sub_Jenis_Analisa', '=', 'sub_ja.id')
                ->select(
                    'berkala.Id_Jenis_Analisa as analisa_id',
                    'berkala.Id_Sub_Jenis_Analisa as analisa_sub_id',
                    'ja.Kode_Analisa as Kode_Analisa',
                    'ja.Jenis_Analisa as Jenis_Analisa',
                    'sub_ja.Jenis_Analisa as Sub_Jenis_Analisa'
                )
                ->whereIn('berkala.Id_Jenis_Analisa', $getAnalisa->pluck('Id_Jenis_Analisa_Khusus'))
                ->get();
        }

        $listYangDigunakan = $getAnalisaBerkala->isNotEmpty()
            ? $getAnalisaBerkala
            : ($getAnalsiaOpsional->isNotEmpty() ? $getAnalsiaOpsional : $analisaList);

        $analisa = collect($listYangDigunakan)->map(function ($item) use ($base_no_sampel) {
            /** @var object $item */
            
            $analisaId = isset($item->analisa_sub_id) ? $item->analisa_sub_id : $item->analisa_id;
            $ujiSampelEntries = DB::table('N_EMI_LAB_Uji_Sampel')
                ->where('No_Po_Sampel', $base_no_sampel)
                ->where('Id_Jenis_Analisa', $analisaId)
                ->get();

            $isDone = true;

            if ($ujiSampelEntries->isEmpty()) {
                $isDone = false;
            } else {
                $isMultiQR = $ujiSampelEntries->contains(fn($entry) => $entry->Flag_Multi_QrCode === 'Y');

                if ($isMultiQR) {
                    $expectedSubPoCount = DB::table('N_EMI_LAB_PO_Sampel_Multi_QrCode')
                        ->where('No_Po_Sampel', $base_no_sampel)
                        ->count();

                    $doneSubPoCount = $ujiSampelEntries
                        ->where('Flag_Selesai', 'Y')
                        ->unique('No_Fak_Sub_Po')
                        ->count();

                    $isDone = $doneSubPoCount >= $expectedSubPoCount;
                } else {
                    $isDone = $ujiSampelEntries->every(fn($entry) => $entry->Flag_Selesai === 'Y');
                }
            }

            return [
                'id' => Hashids::connection('custom')->encode($analisaId),
                'Kode_Analisa' => $item->Kode_Analisa ?? null,
                'Jenis_Analisa' => isset($item->Sub_Jenis_Analisa)
                    ? $item->Jenis_Analisa . ' - ' . $item->Sub_Jenis_Analisa
                    : $item->Jenis_Analisa,
                'Nama_Mesin' => $item->Nama_Mesin_Analisa ?? null,
                'is_done' => $isDone,
            ];
        });

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Data Ditemukan !',
            'result' => [
                'id' => Hashids::connection('custom')->encode($sampelRow->sampel_id),
                'nama_barang' => $sampelRow->Nama_Barang,
                'no_sampel' => $sampelRow->No_Sampel,
                'Berat_Sampel' => (float)$sampelRow->Berat_Sampel,
                'Jumlah_Pcs' => (Int) $sampelRow->Jumlah_Pcs,
                'no_po' => $sampelRow->No_Po,
                'tanggal' => $sampelRow->Tanggal,
                'jam' => $sampelRow->Jam,
                'no_split_po' => $sampelRow->No_Split_Po,
                'no_batch' => $sampelRow->No_Batch,
                'nama_mesin' => $sampelRow->Nama_Mesin,
                'seri_mesin' => $sampelRow->Seri_Mesin,
                'keterangan' => $sampelRow->Keterangan,
                'kode_barang' => $sampelRow->Kode_Barang,
                'Id_Mesin' => $sampelRow->Id_Mesin,
                'kode_perusahaan' => $sampelRow->Kode_Perusahaan,
                'is_multi_print' => $sampelRow->Flag_Multi_Qrcode,
                'jumlah_print' => $sampelRow->Jumlah_Print_QRCode,
                'analisa' => $analisa,
            ]
        ]);
    }

    public function getDetailSampelUjiV2($no_sampel) 
    {

        try {
            $parts = explode('-', $no_sampel);
            $base_no_sampel = count($parts) > 2 ? implode('-', array_slice($parts, 0, -1)) : $no_sampel;

            $sampelRow = DB::table('N_LIMS_PO_Sampel as q')
                ->leftJoin('EMI_Master_Mesin as m', 'q.Id_Mesin', '=', 'm.Id_Master_Mesin')
                ->leftJoin('N_EMI_View_Barang as b', 'q.Kode_Barang', '=', 'b.Kode_Barang')
                ->whereNull("q.Status")
                ->where('q.No_Sampel', '=', $base_no_sampel)
                ->select(
                    'q.id as sampel_id',  'q.Berat_Sampel', 'q.Kode_Perusahaan', 'q.No_Sampel', 'q.No_Po', 'q.Kode_Barang',
                    'q.Tanggal', 'q.Jumlah_Pcs', 'q.Jam', 'q.No_Split_Po', 'q.No_Batch', 'q.Keterangan',
                    'm.Nama_Mesin', 'm.Seri_Mesin', 'm.Flag_Multi_Qrcode', 'm.Jumlah_Print_QRCode', 'm.Flag_FG',
                    'b.Nama as Nama_Barang',
                    'q.Id_Mesin'
                )
                ->first();

            if (!$sampelRow) {
                return response()->json(['success' => false, 'status' => 404, 'message' => 'Data tidak ditemukan'], 404);
            }

            $checkedSelesai = DB::table('N_LIMS_PO_Sampel')
                ->whereNull("Status")
                ->where('No_Sampel', $base_no_sampel)
                ->where('Flag_Selesai', 'Y')
                ->first();

            if ($checkedSelesai) {
                return response()->json([
                    'success' => true,
                    'status' => 200,
                    'finished' => true,
                    'message' => 'Untuk Nomor Sampel ' . $base_no_sampel . ' Sudah Ditutup, Terimakasih Atas Kinerja Kerasnya, Tetap Semangat Dan Jaga Kondisi Ya ☺️'
                ], 200);
            }

            $analisaList = DB::table('N_EMI_LAB_Barang_Analisa as ba')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ba.Id_Jenis_Analisa', '=', 'ja.id')
                ->leftJoin('N_EMI_LAB_Mesin_Analisa as ma', 'ja.Id_Mesin', '=', 'ma.No_Urut')
                ->where('ba.Kode_Barang', $sampelRow->Kode_Barang)
                ->where('ba.Id_Master_Mesin', $sampelRow->Id_Mesin)
                ->where('ba.Id_User', Auth::user()->UserId)
                ->where('ba.Flag_Aktif', 'Y')
                ->where('ba.Kode_Role', 'FLM')
                ->where('ja.Kode_Role', 'FLM')
                ->select(
                    'ja.id as analisa_id',
                    'ja.Kode_Analisa',
                    'ja.Jenis_Analisa',
                    'ma.Nama_Mesin as Nama_Mesin_Analisa'
                )
                ->get();



            $getAnalisa = DB::table('N_LIMS_PO_Sampel')
                ->whereNull('Status')
                ->where('No_Sampel', $base_no_sampel)
                ->where('Flag_Khusus', 'Y')
                ->get();

            $getAnalsiaOpsional = DB::table('N_EMI_LAB_Jenis_Analisa as ja')
                ->leftJoin('N_EMI_LAB_Mesin_Analisa as ma', 'ja.Id_Mesin', '=', 'ma.No_Urut')
                ->whereIn('ja.id', $getAnalisa->pluck('Id_Jenis_Analisa_Khusus'))
                ->where('ja.Kode_Role', 'FLM')
                ->where('ma.Kode_Role', 'FLM')
                ->select(
                    'ja.id as analisa_id',
                    'ja.Kode_Analisa',
                    'ja.Jenis_Analisa',
                    'ma.Nama_Mesin as Nama_Mesin_Analisa'
                )
                ->get();

            $getAnalisaBerkala = collect();
            if ($getAnalisa->isNotEmpty()) {
                $getAnalisaBerkala = DB::table('N_EMI_LAB_Jenis_Analisa_Berkala as berkala')
                    ->join('N_EMI_LAB_Jenis_Analisa as ja', 'berkala.Id_Jenis_Analisa', '=', 'ja.id')
                    ->join('N_EMI_LAB_Jenis_Analisa as sub_ja', 'berkala.Id_Sub_Jenis_Analisa', '=', 'sub_ja.id')
                    ->select(
                        'berkala.Id_Jenis_Analisa as analisa_id',
                        'berkala.Id_Sub_Jenis_Analisa as analisa_sub_id',
                        'ja.Kode_Analisa as Kode_Analisa',
                        'ja.Jenis_Analisa as Jenis_Analisa',
                        'sub_ja.Jenis_Analisa as Sub_Jenis_Analisa'
                    )
                    ->whereIn('berkala.Id_Jenis_Analisa', $getAnalisa->pluck('Id_Jenis_Analisa_Khusus'))
                    ->where('berkala.Kode_Role', 'FLM')
                    ->where('ja.Kode_Role', 'FLM')
                    ->get();
            }

            $listYangDigunakan = $getAnalisaBerkala->isNotEmpty()
                ? $getAnalisaBerkala
                : ($getAnalsiaOpsional->isNotEmpty() ? $getAnalsiaOpsional : $analisaList);

            $analisa = collect($listYangDigunakan)->map(function ($item) use ($base_no_sampel) {
                
                $analisaId = isset($item->analisa_sub_id) ? $item->analisa_sub_id : $item->analisa_id;
                $ujiSampelEntries = DB::table('N_EMI_LIMS_Uji_Sampel')
                    ->where('No_Po_Sampel', $base_no_sampel)
                    ->where('Id_Jenis_Analisa', $analisaId)
                    ->get();

                $isDone = true;

                if ($ujiSampelEntries->isEmpty()) {
                    $isDone = false;
                } else {
                    $isMultiQR = $ujiSampelEntries->contains(fn($entry) => $entry->Flag_Multi_QrCode === 'Y');

                    if ($isMultiQR) {
                        $expectedSubPoCount = DB::table('N_LIMS_PO_Sampel_Multi_QrCode')
                            ->where('No_Po_Sampel', $base_no_sampel)
                            ->count();

                        $doneSubPoCount = $ujiSampelEntries
                            ->where('Flag_Selesai', 'Y')
                            ->unique('No_Fak_Sub_Po')
                            ->count();

                        $isDone = $doneSubPoCount >= $expectedSubPoCount;
                    } else {
                        $isDone = $ujiSampelEntries->every(fn($entry) => $entry->Flag_Selesai === 'Y');
                    }
                }

                return [
                    'id' => Hashids::connection('custom')->encode($analisaId),
                    'Kode_Analisa' => $item->Kode_Analisa ?? null,
                    'Jenis_Analisa' => isset($item->Sub_Jenis_Analisa)
                        ? $item->Jenis_Analisa . ' - ' . $item->Sub_Jenis_Analisa
                        : $item->Jenis_Analisa,
                    'Nama_Mesin' => $item->Nama_Mesin_Analisa ?? null,
                    'is_done' => $isDone,
                ];
            });

            $isResampling = $sampelRow->Flag_FG === 'Y';

            return ResponseHelper::success(
                [
                    'id' => Hashids::connection('custom')->encode($sampelRow->sampel_id),
                    'nama_barang' => $sampelRow->Nama_Barang,
                    'no_sampel' => $sampelRow->No_Sampel,
                    'Berat_Sampel' => (float) $sampelRow->Berat_Sampel,
                    'Jumlah_Pcs' => (int) $sampelRow->Jumlah_Pcs,
                    'no_po' => $sampelRow->No_Po,
                    'tanggal' => $sampelRow->Tanggal,
                    'jam' => $sampelRow->Jam,
                    'no_split_po' => $sampelRow->No_Split_Po,
                    'no_batch' => $sampelRow->No_Batch,
                    'nama_mesin' => $sampelRow->Nama_Mesin,
                    'seri_mesin' => $sampelRow->Seri_Mesin,
                    'keterangan' => $sampelRow->Keterangan,
                    'kode_barang' => $sampelRow->Kode_Barang,
                    'Id_Mesin' => $sampelRow->Id_Mesin,
                    'kode_perusahaan' => $sampelRow->Kode_Perusahaan,
                    'is_multi_print' => $sampelRow->Flag_Multi_Qrcode,
                    'jumlah_print' => $sampelRow->Jumlah_Print_QRCode,
                    'is_resampling' => $isResampling,
                    'analisa' => $analisa,
                ],
                'Data Ditemukan !'
            );

        } catch (\Exception $e) {
            Log::channel('UjiSampelController')->error('Error getDetailSampelUjiV2: ' . $e->getMessage(), [
                'no_sampel' => $no_sampel,
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Terjadi kesalahan pada server. Silakan hubungi admin.'
            ], 500);
        }
    }

    public function getDetailSampelUjiV2Testing($no_sampel) 
    {
        try {
            // Manipulasi nomor sampel seperti kode aslinya
            $parts = explode('-', $no_sampel);
            $base_no_sampel = count($parts) > 2 ? implode('-', array_slice($parts, 0, -1)) : $no_sampel;

            // Bikin array dummy untuk list analisa
            $dummyAnalisa = [
                [
                    'id'            => Hashids::connection('custom')->encode(1),
                    'Kode_Analisa'  => 'ANL-001',
                    'Jenis_Analisa' => 'Analisa Fisik - Uji Viskositas Dummy',
                    'Nama_Mesin'    => 'Mesin Viscometer A',
                    'is_done'       => true,
                ],
                [
                    'id'            => Hashids::connection('custom')->encode(2),
                    'Kode_Analisa'  => 'ANL-002',
                    'Jenis_Analisa' => 'Analisa Kimia - Uji pH Dummy',
                    'Nama_Mesin'    => 'pH Meter B',
                    'is_done'       => false,
                ]
            ];

            // Bikin array dummy main response yang memetakan kolom-kolom N_LIMS_PO_Sampel
            $dummyData = [
                'id'              => Hashids::connection('custom')->encode(999), // int
                'nama_barang'     => 'Barang Dummy Testing', // Langsung diisi nama barang dummy
                'no_sampel'       => $base_no_sampel, // varchar 30
                'Berat_Sampel'    => 15.5, // float
                'Jumlah_Pcs'      => 100, // int
                'no_po'           => 'PO-DUMMY-202603', // varchar 30
                'tanggal'         => date('Y-m-d H:i:s'), // datetime
                'jam'             => date('H:i:s'), // varchar 8
                'no_split_po'     => 'SPLIT-01', // varchar 30
                'no_batch'        => 123456, // int
                'nama_mesin'      => 'Mesin Dummy Testing',
                'seri_mesin'      => 'SR-999',
                'keterangan'      => 'Ini adalah keterangan dummy text panjang untuk testing API tanpa perlu hit ke database sungguhan.', // text
                'kode_barang'     => 'BRG-DUM-001', // varchar 30
                'Id_Mesin'        => 5, // int
                'kode_perusahaan' => 'DUM', // varchar 3
                'is_multi_print'  => 'Y', 
                'jumlah_print'    => 2,
                'is_resampling'   => false, // boolean dari pengecekan Flag_FG
                'analisa'         => $dummyAnalisa,
            ];

            // Langsung return success dengan ResponseHelper
            return ResponseHelper::success(
                $dummyData,
                'Data Dummy Ditemukan !'
            );

        } catch (\Exception $e) {
            // Menangkap error misal Hashids bermasalah, dll.
            Log::channel('UjiSampelController')->error('Error getDetailSampelUjiV2: ' . $e->getMessage(), [
                'no_sampel' => $no_sampel,
                'line'      => $e->getLine(),
                'file'      => $e->getFile(),
            ]);
            
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Terjadi kesalahan pada server. Silakan hubungi admin.'
            ], 500);
        }
    }

    public function getDetailResamplingV1($no_sampel, $no_sub_sampel, $no_resampling, $id_jenis_analisa) 
    {
        try {
            $Id_Jenis_Analisa = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Jenis Analisa tidak valid.'
            ], 400);
        }

        $parts = explode('-', $no_sampel);
        $base_no_sampel = count($parts) > 2 ? implode('-', array_slice($parts, 0, -1)) : $no_sampel;

        $sampelRow = DB::table('N_LIMS_PO_Sampel as q')
            ->leftJoin('EMI_Master_Mesin as m', 'q.Id_Mesin', '=', 'm.Id_Master_Mesin')
            ->leftJoin('N_EMI_View_Barang as b', 'q.Kode_Barang', '=', 'b.Kode_Barang')
            ->where('q.No_Sampel', '=', $base_no_sampel)
            ->select(
                'q.id as sampel_id', 'q.Berat_Sampel', 'q.Kode_Perusahaan', 'q.No_Sampel', 'q.No_Po', 'q.Kode_Barang',
                'q.Tanggal', 'q.Jumlah_Pcs', 'q.Jam', 'q.No_Split_Po', 'q.No_Batch', 'q.Keterangan',
                'm.Nama_Mesin', 'm.Seri_Mesin', 'm.Flag_Multi_Qrcode', 'm.Jumlah_Print_QRCode', 'm.Flag_FG',
                'b.Nama as Nama_Barang',
                'q.Id_Mesin'
            )
            ->first();

        if (!$sampelRow) {
            return response()->json(['success' => false, 'status' => 404, 'message' => 'Data tidak ditemukan'], 404);
        }

        $checkedResempling = DB::table('N_EMI_LIMS_Uji_Sampel_Resampling_Log as r')
            ->join('N_EMI_LAB_Jenis_Analisa as ja', 'r.Id_Jenis_Analisa', '=', 'ja.id')
            ->select('r.*', 'ja.Jenis_Analisa')
            ->where('r.No_Po_Sampel', $sampelRow->No_Sampel)
            ->where('r.No_Sampel_Resampling_Origin', $no_sub_sampel)
            ->where('r.No_Sampel_Resampling', $no_resampling)
            ->where('r.Id_Jenis_Analisa', $Id_Jenis_Analisa)
            ->first();


        $checkedSelesai = DB::table('N_LIMS_PO_Sampel')
            ->whereNull("Status")
            ->where('No_Sampel', $base_no_sampel)
            ->where('Flag_Selesai', 'Y')
            ->first();

        if ($checkedSelesai) {
            return response()->json([
                'success' => true,
                'status' => 200,
                'finished' => true,
                'message' => 'Untuk Nomor Sampel ' . $base_no_sampel . ' Sudah Ditutup, Terimakasih Atas Kinerja Kerasnya, Tetap Semangat Dan Jaga Kondisi Ya ☺️'
            ], 200);
        }

        // Ambil data analisa hanya berdasarkan Id_Jenis_Analisa
        $analisa = collect();

        $analisaData = DB::table('N_EMI_LAB_Jenis_Analisa as ja')
            ->leftJoin('N_EMI_LAB_Mesin_Analisa as ma', 'ja.Id_Mesin', '=', 'ma.No_Urut')
            ->where('ja.id', $Id_Jenis_Analisa)
            ->select(
                'ja.id as analisa_id',
                'ja.Kode_Analisa',
                'ja.Jenis_Analisa',
                'ma.Nama_Mesin as Nama_Mesin_Analisa'
            )
            ->first();

        if ($analisaData) {
            $ujiSampelEntries = DB::table('N_EMI_LIMS_Uji_Sampel')
                ->where('No_Po_Sampel', $base_no_sampel)
                ->where('Id_Jenis_Analisa', $Id_Jenis_Analisa)
                ->get();

            $isDone = true;

            if ($ujiSampelEntries->isEmpty()) {
                $isDone = false;
            } else {
                $isMultiQR = $ujiSampelEntries->contains(fn($entry) => $entry->Flag_Multi_QrCode === 'Y');

                if ($isMultiQR) {
                    $expectedSubPoCount = DB::table('N_LIMS_PO_Sampel_Multi_QrCode')
                        ->where('No_Po_Sampel', $base_no_sampel)
                        ->count();

                    $doneSubPoCount = $ujiSampelEntries
                        ->where('Flag_Selesai', 'Y')
                        ->unique('No_Fak_Sub_Po')
                        ->count();

                    $isDone = $doneSubPoCount >= $expectedSubPoCount;
                } else {
                    $isDone = $ujiSampelEntries->every(fn($entry) => $entry->Flag_Selesai === 'Y');
                }
            }

            $analisa->push([
                'id' => Hashids::connection('custom')->encode($analisaData->analisa_id),
                'Kode_Analisa' => $analisaData->Kode_Analisa ?? null,
                'Jenis_Analisa' => $analisaData->Jenis_Analisa,
                'Nama_Mesin' => $analisaData->Nama_Mesin_Analisa ?? null,
                'is_done' => $isDone,
            ]);
        }

        $isResampling = $sampelRow->Flag_FG === 'Y';

        $resamplingInfo = $checkedResempling ? [
            'Id_Resampling' => Hashids::connection('custom')->encode($checkedResempling->Id_Resampling),
            'No_Po_Sampel' => $checkedResempling->No_Po_Sampel,
            'Tahapan_Ke' => $checkedResempling->Tahapan_Ke,
            'No_Sampel_Resampling_Origin' => $checkedResempling->No_Sampel_Resampling_Origin,
            'No_Sampel_Resampling' => $checkedResempling->No_Sampel_Resampling,
            'Keterangan_Resempling' => $checkedResempling->Keterangan,
            'Tanggal' => $checkedResempling->Tanggal,
            'Jam' => $checkedResempling->Jam,
            'Id_User' => $checkedResempling->Id_User,
            'Id_Jenis_Analisa' => Hashids::connection('custom')->encode($checkedResempling->Id_Jenis_Analisa),
            'Flag_Selesai_Resampling' => $checkedResempling->Flag_Selesai_Resampling,
            'Jenis_Analisa' => $checkedResempling->Jenis_Analisa,
        ] : null;

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Data Ditemukan !',
            'result' => [
                'id' => Hashids::connection('custom')->encode($sampelRow->sampel_id),
                'nama_barang' => $sampelRow->Nama_Barang,
                'no_sampel' => $sampelRow->No_Sampel,
                'Berat_Sampel' => (float)$sampelRow->Berat_Sampel,
                'Jumlah_Pcs' => (int)$sampelRow->Jumlah_Pcs,
                'no_po' => $sampelRow->No_Po,
                'tanggal' => $sampelRow->Tanggal,
                'jam' => $sampelRow->Jam,
                'no_split_po' => $sampelRow->No_Split_Po,
                'no_batch' => $sampelRow->No_Batch,
                'nama_mesin' => $sampelRow->Nama_Mesin,
                'seri_mesin' => $sampelRow->Seri_Mesin,
                'keterangan' => $sampelRow->Keterangan,
                'kode_barang' => $sampelRow->Kode_Barang,
                'Id_Mesin' => $sampelRow->Id_Mesin,
                'kode_perusahaan' => $sampelRow->Kode_Perusahaan,
                'is_multi_print' => $sampelRow->Flag_Multi_Qrcode,
                'jumlah_print' => $sampelRow->Jumlah_Print_QRCode,
                'is_resampling' => $isResampling,
                'analisa' => $analisa,
                'resampling_info' => $resamplingInfo
            ]
        ]);
    }

    public function getParameterAndPerhitunganOld($id_analisa)
    {
        try {
            $hash = Hashids::connection('custom');

            $decodedId = $hash->decode($id_analisa);

            if (empty($decodedId)) {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => 'ID tidak valid.',
                ], 400);
            }

            $realId = $decodedId[0];

            $parameters = DB::table('N_EMI_LAB_Binding_jenis_analisa as b')
                ->join('EMI_Quality_Control as q', 'q.Id_QC_Formula', '=', 'b.Id_Quality_Control')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ja.id', '=', 'b.Id_Jenis_Analisa')
                ->leftJoin('EMI_Kategori_Komponen as kk', 'kk.Id_Kategori_Komponen', '=', 'q.Id_Kategori_Komponen')
                ->where('b.Id_Jenis_Analisa', $realId)
                ->select(
                    'b.id',
                    'b.Id_Quality_Control as id_qc',
                    'b.Id_Jenis_Analisa',
                    'q.Keterangan as nama_parameter',
                    'kk.Keterangan as type_inputan',
                    'q.Satuan as satuan',
                    'q.Kode_Uji as kode_uji',
                    'ja.Kode_Analisa as kode_analisa',
                    'ja.Jenis_Analisa as jenis_analisa',
                    'ja.Flag_Perhitungan as flag_perhitungan',
                    'ja.Flag_Foto as sesi_foto'
                )
                ->get();

            if ($parameters->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "Data tidak ditemukan",
                ], 404);
            }

          
            $switchIds = $parameters
                ->where('type_inputan', 'Switch')
                ->pluck('id_qc')
                ->unique()
                ->values();

            $switchOptions = collect();

            if ($switchIds->isNotEmpty()) {
                $switchOptions = DB::table('EMI_Switch')
                    ->whereIn('Id_QC_Formula', $switchIds)
                    ->select('Id_QC_Formula', 'Keterangan', 'Label_Keterangan')
                    ->get()
                    ->groupBy('Id_QC_Formula');
            }

            $hashedParameters = $parameters->map(function ($param) use ($switchOptions, $hash) {

                $options = null;

                if ($param->type_inputan === 'Switch' && isset($switchOptions[$param->id_qc])) {
                    $options = $switchOptions[$param->id_qc]
                        ->map(fn($opt) => [
                            'value' => $opt->Keterangan,
                            'label' => $opt->Label_Keterangan ?? $opt->Keterangan
                        ])
                        ->values()
                        ->toArray();
                }

                return [
                    'id' => $hash->encode($param->id),
                    'id_qc' => $hash->encode($param->id_qc),
                    'id_jenis_analisa' => $hash->encode($param->Id_Jenis_Analisa),
                    'nama_parameter' => $param->nama_parameter,
                    'type_inputan' => $param->type_inputan,
                    'satuan' => $param->satuan,
                    'kode_uji' => $param->kode_uji,
                    'kode_analisa' => $param->kode_analisa,
                    'jenis_analisa' => $param->jenis_analisa,
                    'flag_perhitungan' => $param->flag_perhitungan,
                    'sesi_foto' => $param->sesi_foto,
                    'option' => $options
                ];
            });

            /*
            |--------------------------------------------------------------------------
            | FORMULA
            |--------------------------------------------------------------------------
            */
            $hashedFormula = null;
            $isPerhitungan = $parameters->first()->flag_perhitungan === 'Y';

            if ($isPerhitungan) {
                $formulas = DB::table('N_EMI_LAB_Perhitungan as p')
                    ->where('p.Id_Jenis_Analisa', $realId)
                    ->select(
                        'p.Id',
                        'p.Id_Jenis_Analisa',
                        'p.Rumus',
                        'p.Nama_Kolom',
                        'p.Hasil_Perhitungan'
                    )
                    ->get();

                $hashedFormula = $formulas->map(function ($rumus) use ($hash) {

                    $processedRumus = preg_replace_callback(
                        '/\[(\d+)\]/',
                        fn($m) => '[' . $hash->encode($m[1]) . ']',
                        $rumus->Rumus
                    );

                    return [
                        'id' => $hash->encode($rumus->Id),
                        'id_jenis_analisa' => $hash->encode($rumus->Id_Jenis_Analisa),
                        'rumus' => $processedRumus,
                        'nama_kolom' => $rumus->Nama_Kolom,
                        'digit' => $rumus->Hasil_Perhitungan,
                    ];
                });
            }

            /*
            |--------------------------------------------------------------------------
            | RESPONSE
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Data Ditemukan !",
                'result' => [
                    'parameter' => $hashedParameters,
                    'formula' => $hashedFormula,
                    'sesi_foto' => $parameters->first()->sesi_foto
                ]
            ], 200);

        } catch (\Exception $e) {

            Log::channel('FormulatorTrialSampelController')->error(
                'Error getParameterAndPerhitunganOld: ' . $e->getMessage(),
                [
                    'id_analisa' => $id_analisa,
                    'line' => $e->getLine(),
                    'file' => $e->getFile()
                ]
            );

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Terjadi kesalahan pada server. Silahkan Hubungi Admin'
            ], 500);
        }
    }
    
    public function getParameterAndPerhitunganOldTesting($id_analisa)
    {
        try {
            $hash = Hashids::connection('custom');

            // Bikin ID dummy dan langsung di-encode biar frontend nggak error
            $dummyIdAnalisa = $hash->encode(99);
            $dummyIdParam1  = $hash->encode(1);
            $dummyIdQc1     = $hash->encode(101);
            $dummyIdParam2  = $hash->encode(2);
            $dummyIdQc2     = $hash->encode(102);
            $dummyIdFormula = $hash->encode(10);

            // 1. Data Dummy Parameter (Contoh: 1 Numeric, 1 Switch)
            $hashedParameters = [
                [
                    'id'               => $dummyIdParam1,
                    'id_qc'            => $dummyIdQc1,
                    'id_jenis_analisa' => $dummyIdAnalisa,
                    'nama_parameter'   => 'Parameter Numeric (Dummy)',
                    'type_inputan'     => 'Numeric',
                    'satuan'           => 'kg',
                    'kode_uji'         => 'UJ-DUMMY-1',
                    'kode_analisa'     => 'ANL-DUMMY',
                    'jenis_analisa'    => 'Analisa Fisik (Dummy)',
                    'flag_perhitungan' => 'Y',
                    'sesi_foto'        => 'Y',
                    'option'           => null
                ],
                [
                    'id'               => $dummyIdParam2,
                    'id_qc'            => $dummyIdQc2,
                    'id_jenis_analisa' => $dummyIdAnalisa,
                    'nama_parameter'   => 'Parameter Switch (Dummy)',
                    'type_inputan'     => 'Switch',
                    'satuan'           => '-',
                    'kode_uji'         => 'UJ-DUMMY-2',
                    'kode_analisa'     => 'ANL-DUMMY',
                    'jenis_analisa'    => 'Analisa Fisik (Dummy)',
                    'flag_perhitungan' => 'N',
                    'sesi_foto'        => 'Y',
                    'option'           => [
                        ['value' => 'OK', 'label' => 'Bagus / OK'],
                        ['value' => 'NG', 'label' => 'Jelek / NG']
                    ]
                ]
            ];

            // 2. Data Dummy Formula (Menggunakan ID QC Parameter 1)
            $hashedFormula = [
                [
                    'id'               => $dummyIdFormula,
                    'id_jenis_analisa' => $dummyIdAnalisa,
                    'rumus'            => '[' . $dummyIdQc1 . '] * 1.5', // Rumus simpel
                    'nama_kolom'       => 'Hasil Perhitungan (Dummy)',
                    'digit'            => 2,
                ]
            ];

            /*
            |--------------------------------------------------------------------------
            | RESPONSE BYPASS
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => "Data Dummy Parameter Ditemukan !",
                'result'  => [
                    'parameter' => $hashedParameters,
                    'formula'   => $hashedFormula,
                    'sesi_foto' => 'Y' // Sesuai request: bypass selalu Y
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::channel('FormulatorTrialSampelController')->error(
                'Error getParameterAndPerhitunganOldTesting: ' . $e->getMessage(),
                [
                    'id_analisa' => $id_analisa,
                    'line'       => $e->getLine(),
                    'file'       => $e->getFile()
                ]
            );

            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Terjadi kesalahan pada server. Silahkan Hubungi Admin'
            ], 500);
        }
    }

    public function getParameterAndPerhitungan($id_mesin, $id_analisa)
    {
        $decodedId = Hashids::connection('custom')->decode($id_analisa);

        if (empty($decodedId)) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'ID tidak valid.',
            ], 400);
        }

        $id_analisa = $decodedId[0];

        $parameterQuery = "
            SELECT 
                b.id,
                b.Id_Quality_Control as id_qc,
                b.Id_Jenis_Analisa,
                q.Keterangan as nama_parameter,
                kk.Keterangan AS type_inputan,
                q.Satuan as satuan,
                q.Kode_Uji as kode_uji,
                ja.Kode_Analisa as kode_analisa,
                ja.Jenis_Analisa as jenis_analisa,
                ja.Flag_Perhitungan as flag_perhitungan
            FROM N_EMI_LAB_Binding_jenis_analisa b
            JOIN EMI_Quality_Control q ON q.Id_QC_Formula = b.Id_Quality_Control
            JOIN N_EMI_LAB_Jenis_Analisa ja ON ja.id = b.Id_Jenis_Analisa
            LEFT JOIN EMI_Kategori_Komponen kk ON kk.Id_Kategori_Komponen = q.Id_Kategori_Komponen
            WHERE b.Id_Jenis_Analisa = ?
        ";

        $getParameter = DB::select($parameterQuery, [$id_analisa]);

        if (empty($getParameter)) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => "Data tidak ditemukan",
            ], 404);
        }

        $isPerhitungan = $getParameter[0]->flag_perhitungan === 'Y';

        $getDataRumus = $isPerhitungan
            ? DB::select("
                SELECT 
                    N_EMI_LAB_Perhitungan.Id, 
                    N_EMI_LAB_Perhitungan.Id_Jenis_Analisa, 
                    N_EMI_LAB_Perhitungan.Rumus AS rumus, 
                    N_EMI_LAB_Perhitungan.Nama_Kolom AS nama_kolom,
                    N_EMI_LAB_Perhitungan.Hasil_Perhitungan AS digit,
                    N_EMI_LAB_Standar_Rentang.Range_Awal,
                    N_EMI_LAB_Standar_Rentang.Range_Akhir
                FROM N_EMI_LAB_Perhitungan
                LEFT JOIN N_EMI_LAB_Standar_Rentang 
                    ON N_EMI_LAB_Perhitungan.Id = N_EMI_LAB_Standar_Rentang.Id_Perhitungan
                    AND N_EMI_LAB_Standar_Rentang.Id_Master_Mesin = ?
                WHERE N_EMI_LAB_Perhitungan.Id_Jenis_Analisa = ?;
            ", [$id_mesin,$id_analisa])
            : null;

        // 🔒 Encode hash ID di parameter
        $hashedParameters = array_map(function ($param) {
            return [
                'id' => Hashids::connection('custom')->encode($param->id),
                'id_qc' =>  Hashids::connection('custom')->encode($param->id_qc),
                'id_jenis_analisa' => Hashids::connection('custom')->encode($param->Id_Jenis_Analisa),
                'nama_parameter' => $param->nama_parameter,
                'type_inputan' => $param->type_inputan,
                'satuan' => $param->satuan,
                'kode_uji' => $param->kode_uji,
                'kode_analisa' => $param->kode_analisa,
                'jenis_analisa' => $param->jenis_analisa,
                'flag_perhitungan' => $param->flag_perhitungan,
            ];
        }, $getParameter);

    
        $hashedFormula = $getDataRumus ? array_map(function ($rumus) {
            $processedRumus = preg_replace_callback(
                '/\[(\d+)\]/', 
                function ($matches) {
                    $idToEncode = $matches[1];
                    $encodedId = Hashids::connection('custom')->encode($idToEncode);
                    return '[' . $encodedId . ']';
                },
                $rumus->rumus 
            );

            return [
                'id' => Hashids::connection('custom')->encode($rumus->Id),
                'id_jenis_analisa' => Hashids::connection('custom')->encode($rumus->Id_Jenis_Analisa),
                'rumus' => $processedRumus, 
                'nama_kolom' => $rumus->nama_kolom,
                'digit' => $rumus->digit,
               
            ];
        }, $getDataRumus) : null;

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => "Data Ditemukan !",
            'result' => [
                'parameter' => $hashedParameters,
                'formula' => $hashedFormula
            ]
        ], 200);
    }
    
    public function getDataParameterUjiSampelByNoSampel($no_sampel)
    {
        
        $getNoSampel = DB::table('N_EMI_LAB_Po_Sampel')
                    ->where('No_Sampel', $no_sampel)
                    ->first();

        if (!$getNoSampel) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Data tidak ditemukan!',
                'data' => null
            ]);
        }
        
        $query = 
            'select a.kode_perusahaan,a.kode_barang,b.Flag_Ket_Lewat_Range,  
                a.id_qc_formula,b.kode_uji,b.Flag_Tampil_Android,b.keterangan,b.satuan,b.id_kategori_komponen,                      
                    c.keterangan as komponen,a.min_range,a.max_range,                      
                    a.min_nilai_seharusnya,a.max_nilai_seharusnya 
                    from EMI_Quality_Control a, EMI_Quality_Control b, EMI_Kategori_Komponen c  where                      
                    a.kode_perusahaan = b.kode_perusahaan and a.Id_QC_Formula = b.Id_QC_Formula                      
                    and b.kode_perusahaan = c.kode_perusahaan and b.id_kategori_komponen = c.Id_Kategori_Komponen                     
                    
                    and a.Kode_barang = ? and a.Kode_Perusahaan = ?
            ';

        $getData = DB::select($query, [$getNoSampel->Kode_Barang, $getNoSampel->Kode_Perusahaan]);
    
        if (empty($getData)) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Data tidak ditemukan!',
                'data' => null
            ]);
        }

        return response()->json(
            [
                'success' => false,
                'status' => 200,
                'message' => 'Data Ditemukan !',
                'data' => $getData
            ], 200
        );
    }
    public function getPoSampelMultiQrDetail($no_PO_Multiqr, $id_jenis_analisa)
    {
        try {
            $id_jenis_analisa = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Jenis Analisa tidak valid.'
            ], 400);
        }

        $getNoSampel = DB::table('N_LIMS_PO_Sampel_Multi_QrCode')
            ->select(
                'N_LIMS_PO_Sampel_Multi_QrCode.No_Po_Multi as no_ticket',
                'N_LIMS_PO_Sampel_Multi_QrCode.No_Po_Sampel as sampel',
            )
            ->where('N_LIMS_PO_Sampel_Multi_QrCode.No_Po_Multi', $no_PO_Multiqr)
            ->first();
        
        
        if(empty($getNoSampel)){
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Data Dengan Nomor '. $no_PO_Multiqr.' Tidak Ditemukan'
            ], 404);
        }
            
        $isDone = DB::table('N_EMI_LIMS_Uji_Sampel')
                ->where('No_Po_Sampel', $getNoSampel->sampel)
                ->where('No_Fak_Sub_Po', $no_PO_Multiqr)
                ->where('Id_Jenis_Analisa', $id_jenis_analisa)
                ->where('Flag_Selesai', 'Y')
                ->first(); 
        
        if ($isDone) {
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Selamat, nomor uji sampel {$no_PO_Multiqr} sudah diselesaikan",
                'result' => [
                    'is_done' => $isDone
                ]
            ]);
        }
    
        if (empty($getNoSampel)) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => "Data Tidak Ditemukan!"
            ], 404);
        }
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Data Ditemukan!',
            'result' => [
                'no_ticket' => $getNoSampel->no_ticket,
                'sampel' => $getNoSampel->sampel,
            ]
        ], 200);
    }
    public function getPoSampelMultiQrDetailV2($no_PO_Multiqr, $id_jenis_analisa)
    {
        try {
            $id_jenis_analisa = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Jenis Analisa tidak valid.'
            ], 400);
        }

        $getNoSampel = DB::table('N_EMI_LAB_PO_Sampel_Multi_QrCode')
            ->select(
                'N_EMI_LAB_PO_Sampel_Multi_QrCode.No_Po_Multi as no_ticket',
                'N_EMI_LAB_PO_Sampel_Multi_QrCode.No_Po_Sampel as sampel'
            )
            ->where('N_EMI_LAB_PO_Sampel_Multi_QrCode.No_Po_Multi', $no_PO_Multiqr)
            ->first();

        if (empty($getNoSampel)) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Data Dengan Nomor ' . $no_PO_Multiqr . ' Tidak Ditemukan'
            ], 404);
        }

        // ✅ CEK APAKAH SUDAH DIGUNAKAN UNTUK RESAMPLING YANG BELUM SELESAI
        $resamplingCheck = DB::table('N_EMI_LAB_Uji_Sampel_Resampling_Log')
            ->where('No_Sampel_Resampling', $no_PO_Multiqr)
            ->where('Id_Jenis_Analisa', $id_jenis_analisa)
            ->first();

        if ($resamplingCheck) {
            return response()->json([
                'success' => false,
                'status' => 409,
                'message' => "Nomor sampel {$no_PO_Multiqr} sudah digunakan untuk proses resampling. 
                    Karena hasil akhir uji sebelumnya tidak sesuai, silakan selesaikan proses resampling di menu resampling."
            ], 409);
        }

        // CEK APAKAH UJI SAMPEL SUDAH SELESAI
        $isDone = DB::table('N_EMI_LAB_Uji_Sampel')
            ->where('No_Po_Sampel', $getNoSampel->sampel)
            ->where('No_Fak_Sub_Po', $no_PO_Multiqr)
            ->where('Id_Jenis_Analisa', $id_jenis_analisa)
            ->where('Flag_Selesai', 'Y')
            ->first();

        if ($isDone) {
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Selamat, nomor uji sampel {$no_PO_Multiqr} sudah diselesaikan",
                'result' => [
                    'is_done' => $isDone
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Data Ditemukan!',
            'result' => [
                'no_ticket' => $getNoSampel->no_ticket,
                'sampel' => $getNoSampel->sampel,
            ]
        ], 200);
    }

    public function getPoSampelMultiQrDetailV3($no_sampel, $no_PO_Multiqr, $id_jenis_analisa)
    {
        try {
            $decodedId = Hashids::connection('custom')->decode($id_jenis_analisa);
            
            if (empty($decodedId)) {
                return response()->json([
                    'success' => false,
                    'status'  => 400,
                    'message' => 'Format ID Jenis Analisa tidak valid.'
                ], 400);
            }

            $realIdJenisAnalisa = $decodedId[0];

            $baseSampel = implode('-', array_slice(explode('-', $no_sampel), 0, 2));
            $basePoMultiQr = implode('-', array_slice(explode('-', $no_PO_Multiqr), 0, 2));

            if ($baseSampel !== $basePoMultiQr) {
                return response()->json([
                    'success' => false,
                    'status'  => 400,
                    'message' => "Validasi Gagal: Nomor PO Multi QR '{$no_PO_Multiqr}' tidak sesuai dengan induk Nomor Sampel '{$baseSampel}'."
                ], 400);
            }

            $getNoSampel = DB::table('N_LIMS_PO_Sampel_Multi_QrCode')
                ->select(
                    'No_Po_Multi as no_ticket',
                    'No_Po_Sampel as sampel'
                )
                ->where('No_Po_Multi', $no_PO_Multiqr)
                ->first();

            if (!$getNoSampel) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Data Dengan Nomor ' . $no_PO_Multiqr . ' Tidak Ditemukan'
                ], 404);
            }

            $resamplingCheck = DB::table('N_EMI_LIMS_Uji_Sampel_Resampling_Log')
                ->where('No_Sampel_Resampling', $no_PO_Multiqr)
                ->where('Id_Jenis_Analisa', $realIdJenisAnalisa)
                ->first();

            if ($resamplingCheck) {
                return response()->json([
                    'success' => false,
                    'status' => 409,
                    'message' => "Nomor sampel {$no_PO_Multiqr} sudah digunakan untuk proses resampling. Karena hasil akhir uji sebelumnya tidak sesuai, silakan selesaikan proses resampling di menu resampling."
                ], 409);
            }

            $isDone = DB::table('N_EMI_LIMS_Uji_Sampel')
                ->where('No_Po_Sampel', $getNoSampel->sampel)
                ->where('No_Fak_Sub_Po', $no_PO_Multiqr)
                ->where('Id_Jenis_Analisa', $realIdJenisAnalisa)
                ->where('Flag_Selesai', 'Y')
                ->first();

            if ($isDone) {
                return response()->json([
                    'success' => true,
                    'status' => 200,
                    'message' => "Selamat, nomor uji sampel {$no_PO_Multiqr} sudah diselesaikan",
                    'result' => [
                        'is_done' => $isDone
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Data Ditemukan!',
                'result' => [
                    'no_ticket' => $getNoSampel->no_ticket,
                    'sampel' => $getNoSampel->sampel,
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::channel('ujiSampelController')->error('Error getPoSampelMultiQrDetailV3: ' . $e->getMessage(), [
                'no_sampel' => $no_sampel,
                'no_PO_Multiqr' => $no_PO_Multiqr,
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Terjadi kesalahan pada server. Silakan hubungi admin.'
            ], 500);
        }
    }

    public function getPoSampelMultiQrDetailV3Testing($no_sampel, $no_PO_Multiqr, $id_jenis_analisa)
    {
        try {
            // Karena ini murni testing/dummy, kita bypass semua query database (DB::table)
            // dan langsung return array sukses dengan data sembarangan.

            // Membuat nomor acak untuk data dummy
            $randomNumber = rand(10000, 99999);
            $dummyTicket = 'MQ-' . date('Ymd') . '-' . $randomNumber;
            $dummySampel = 'SMPL-' . date('Ymd') . '-' . $randomNumber;

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Data Dummy Multi QR Ditemukan!',
                'result'  => [
                    'no_ticket' => $dummyTicket, // Nomor sembarangan
                    'sampel'    => $dummySampel, // Nomor sembarangan
                    'Flag_Foto' => 'Y',          // Wajib flag foto Y sesuai permintaan
                    'is_done'   => false         // Status is_done default false untuk dummy
                ]
            ], 200);

        } catch (\Exception $e) {
            // Tangkap error jika kebetulan ada kesalahan sistem (misal typo dsb)
            Log::channel('ujiSampelController')->error('Error getPoSampelMultiQrDetailV3Testing: ' . $e->getMessage(), [
                'no_sampel'     => $no_sampel,
                'no_PO_Multiqr' => $no_PO_Multiqr,
                'line'          => $e->getLine(),
                'file'          => $e->getFile()
            ]);

            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Terjadi kesalahan pada server (Dummy). Silakan hubungi admin.'
            ], 500);
        }
    }

    public function getPoSampelMultiQrDetailForRumus($no_PO_Multiqr, $id_jenis_analisa)
    {
 
        try {
            $kodeRole = collect(Session::get('User_Roles', []))->pluck('Kode_Role')->toArray();

            if (empty($kodeRole)) {
                return response()->json([
                    'success' => false,
                    'status'  => 404,
                    'message' => 'Data tidak ditemukan (Role kosong).'
                ], 404);
            }

            $decodedId = Hashids::connection('custom')->decode($id_jenis_analisa);
            
            if (empty($decodedId)) {
                return response()->json([
                    'success' => false,
                    'status'  => 400,
                    'message' => 'Format ID Jenis Analisa tidak valid.'
                ], 400);
            }

            $decoded_id_jenis_analisa = $decodedId[0];

            $jenisAnalisa = DB::table('N_EMI_LAB_Jenis_Analisa')
                ->where('id', $decoded_id_jenis_analisa)
                ->whereIn('Kode_Role', $kodeRole)
                ->first();

            if (!$jenisAnalisa) {
                return response()->json([
                    'success' => false,
                    'status'  => 404,
                    'message' => 'Jenis Analisa tidak ditemukan.'
                ], 404);
            }

            $kodeAnalisa = $jenisAnalisa->Kode_Analisa;

            $getNoSampel = DB::table('N_LIMS_PO_Sampel_Multi_QrCode as psmq')
                ->select('psmq.No_Po_Multi as no_ticket', 'psmq.No_Po_Sampel as sampel', 'us.Flag_Multi_QrCode')
                ->leftJoin('N_EMI_LIMS_Uji_Sampel as us', 'psmq.No_Po_Sampel', '=', 'us.No_Po_Sampel')
                ->where('psmq.No_Po_Multi', $no_PO_Multiqr)
                ->first();

            if (!$getNoSampel) {
                return response()->json([
                    'success' => false,
                    'status'  => 404,
                    'message' => 'Data Tidak Ditemukan!'
                ], 404);
            }

            $checkSubmit = DB::table('N_EMI_LIMS_Uji_Sampel as us')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'us.Id_Jenis_Analisa', '=', 'ja.id')
                ->join('N_LIMS_PO_Sampel as po', 'us.No_Po_Sampel', '=', 'po.No_Sampel')
                ->join('EMI_Master_Mesin as m', 'po.Id_Mesin', '=', 'm.Id_Master_Mesin')
                ->whereIn('ja.Kode_Role', $kodeRole)
                ->where('us.No_Po_Sampel', $getNoSampel->sampel)
                ->where('us.No_Fak_Sub_Po', $getNoSampel->no_ticket)
                ->where('us.Flag_Multi_QrCode', $getNoSampel->Flag_Multi_QrCode)
                ->where('us.Id_Jenis_Analisa', $decoded_id_jenis_analisa)
                ->groupBy('us.No_Po_Sampel')
                ->select(
                    'us.No_Po_Sampel',
                    DB::raw('MAX(CAST(us.No_Fak_Sub_Po AS VARCHAR(MAX))) AS No_Fak_Sub_Po'),
                    DB::raw('MAX(us.Tanggal) AS Tanggal_Pengujian_Sampel'),
                    DB::raw('MAX(us.Jam) AS Jam_Pengujian_Sampel'),
                    DB::raw('MAX(us.Flag_Multi_QrCode) AS Flag_Multi_QrCode'),
                    DB::raw('MAX(CAST(ja.Kode_Analisa AS VARCHAR(MAX))) AS Kode_Analisa'),
                    DB::raw('MAX(CAST(ja.Jenis_Analisa AS VARCHAR(MAX))) AS Jenis_Analisa'),
                    DB::raw('MAX(CAST(po.Kode_Barang AS VARCHAR(MAX))) AS Kode_Barang'),
                    DB::raw('MAX(CAST(po.No_Split_Po AS VARCHAR(MAX))) AS No_Split_Po'),
                    DB::raw('MAX(CAST(po.No_Batch AS VARCHAR(MAX))) AS No_Batch'),
                    DB::raw('MAX(CAST(po.No_Po AS VARCHAR(MAX))) AS No_Po'),
                    DB::raw('MAX(CAST(po.Keterangan AS VARCHAR(MAX))) AS Catatan_Po_Sampel'),
                    DB::raw('MAX(CAST(po.Status AS VARCHAR(MAX))) AS Status'),
                    DB::raw('MAX(po.Tanggal) AS Tanggal_Po_Sampel'),
                    DB::raw('MAX(po.Jam) AS Jam_Po_Sampel'),
                    DB::raw('MAX(CAST(m.Nama_Mesin AS VARCHAR(MAX))) AS Nama_Mesin'),
                    DB::raw('MAX(CAST(m.Seri_Mesin AS VARCHAR(MAX))) AS Seri_Mesin')
                )->get();

            $checkedIsDraft = DB::table('N_EMI_LIMS_Uji_Sampel_Sementara')
                ->select('No_Urut', 'No_Sementara', 'No_Po_Sampel', 'No_Fak_Sub_Po', 'Id_Jenis_Analisa', DB::raw('CAST(RV AS INT) AS RV_INT'))
                ->where('No_Po_Sampel', $getNoSampel->sampel)
                ->where('No_Fak_Sub_Po', $getNoSampel->no_ticket)
                ->where('Id_Jenis_Analisa', $decoded_id_jenis_analisa)
                ->get();

            $checkedIsDraftDetail = collect();
            if ($checkedIsDraft->isNotEmpty()) {
                $noSementaraValues = $checkedIsDraft->pluck('No_Sementara')->toArray();

                $checkedIsDraftDetail = DB::table('N_EMI_LIMS_Uji_Sampel_Detail_Sementara')
                    ->select('No_Urut', 'No_Sementara', 'Id_Quality_Control', 'Value_Parameter', 'Id_User', DB::raw('CAST(RV AS INT) AS RV_INT'))
                    ->whereIn('No_Sementara', $noSementaraValues)
                    ->get();
            }

            $perhitungans = DB::table('N_EMI_LAB_Perhitungan')
                ->select('Rumus')
                ->whereIn('Kode_Role', $kodeRole)
                ->where('Id_Jenis_Analisa', $decoded_id_jenis_analisa)
                ->get()
                ->map(function ($item) {
                    $itemArray = (array) $item;
                    $rumus = $itemArray['Rumus'];
                    $hashedRumus = preg_replace_callback('/\[(\d+)\]/', function ($matches) {
                        $angka = $matches[1];
                        $hashed = Hashids::connection('custom')->encode($angka);
                        return '[' . $hashed . ']';
                    }, $rumus);

                    return ['Rumus' => $hashedRumus];
                });

            $encodedDraftSummary = $checkedIsDraft
                ->groupBy('No_Sementara')
                ->flatMap(function ($group, $noSementara) use ($perhitungans) {
                    return $group->values()->map(function ($item, $index) use ($perhitungans) {
                        $itemArray = (array) $item;
                        $rumusItem = $perhitungans[$index] ?? ['Rumus' => null];

                        return [
                            'No_Urut'          => Hashids::connection('custom')->encode($itemArray['No_Urut']),
                            'No_Sementara'     => $itemArray['No_Sementara'],
                            'No_Po_Sampel'     => $itemArray['No_Po_Sampel'],
                            'No_Fak_Sub_Po'    => $itemArray['No_Fak_Sub_Po'],
                            'Id_Jenis_Analisa' => Hashids::connection('custom')->encode($itemArray['Id_Jenis_Analisa']),
                            'RV_INT'           => Hashids::connection('custom')->encode($itemArray['RV_INT']),
                            'Rumus'            => $rumusItem['Rumus'],
                        ];
                    });
                });

            $encodedDraftDetail = collect($checkedIsDraftDetail)->map(function ($item) use ($kodeAnalisa) {
                $itemArray = (array) $item;
                $valueParameter = null; 

                if (isset($itemArray['Value_Parameter'])) {
                    if ($kodeAnalisa === 'MBLG-STR') {
                        if ($itemArray['Value_Parameter'] == -88888888) {
                            $valueParameter = '+';
                        } elseif ($itemArray['Value_Parameter'] == -999999) {
                            $valueParameter = '-';
                        } else {
                            $valueParameter = round((float)$itemArray['Value_Parameter'], 4);
                        }
                    } else {
                        $valueParameter = round((float)$itemArray['Value_Parameter'], 4);
                    }
                }
                return [
                    'No_Urut'            => Hashids::connection('custom')->encode($itemArray['No_Urut']),
                    'No_Sementara'       => $itemArray['No_Sementara'],
                    'Id_Quality_Control' => Hashids::connection('custom')->encode($itemArray['Id_Quality_Control']),
                    'Value_Parameter'    => $valueParameter,
                    'RV_INT'             => Hashids::connection('custom')->encode($itemArray['RV_INT']),
                ];
            });

            $result = [
                'no_ticket' => $getNoSampel->no_ticket,
                'sampel'    => $getNoSampel->sampel,
                'is_submit' => $checkSubmit->isEmpty() ? null : $checkSubmit->toArray(),
                'is_draft'  => collect($encodedDraftSummary)->groupBy('No_Sementara')->map(function ($groupedSummaries, $noSementara) use ($encodedDraftDetail) {
                    return [
                        'no_sementara' => $noSementara,
                        'hasil'        => $groupedSummaries->toArray(),
                        'parameter'    => $encodedDraftDetail
                            ->where('No_Sementara', $noSementara)
                            ->values()
                            ->toArray(),
                    ];
                })->values()->toArray()
            ];

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Data Ditemukan!',
                'result'  => $result
            ], 200);

        } catch (\Exception $e) {
            Log::channel('ujiSampelController')->error('Error getPoSampelMultiQrDetailForRumus: ' . $e->getMessage(), [
                'no_PO_Multiqr' => $no_PO_Multiqr,
                'line'          => $e->getLine(),
                'file'          => $e->getFile()
            ]);

            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Terjadi kesalahan pada server. Silakan hubungi admin.'
            ], 500);
        }
    }

    public function getPoSampelNotRumusNotQrDetailForRumus($no_PO_Multiqr, $id_jenis_analisa)
    {

        try {
            $decoded_id_jenis_analisa = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Jenis Analisa tidak valid.'
            ], 400);
        }

        $getNoSampelRaw = DB::selectOne("
            SELECT
                psmq.No_Po_Multi AS no_ticket,
                psmq.No_Po_Sampel AS sampel,
                us.Flag_Multi_QrCode
            FROM N_EMI_LAB_PO_Sampel_Multi_QrCode psmq
            LEFT JOIN N_EMI_LAB_Uji_Sampel us
                ON psmq.No_Po_Sampel = us.No_Po_Sampel
            WHERE No_Po_Multi = ?
        ", [$no_PO_Multiqr]);
 

        if (empty($getNoSampelRaw)) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Data Tidak Ditemukan!'
            ], 404);
        }

        // --- 3. Ambil Data Submit & Draft (RAW) ---
        // Query tetap sama, kita hanya akan memodifikasi hasilnya nanti.
        $getNoSampel = $getNoSampelRaw;

        $checkSubmit = collect(DB::select("
                SELECT
                    us.No_Po_Sampel,
                    MAX(CAST(us.No_Fak_Sub_Po AS VARCHAR(MAX))) AS No_Fak_Sub_Po,
                    MAX(us.Tanggal) AS Tanggal_Pengujian_Sampel,
                    MAX(us.Jam) AS Jam_Pengujian_Sampel,
                    MAX(us.Flag_Multi_QrCode) AS Flag_Multi_QrCode,
                    MAX(CAST(ja.Kode_Analisa AS VARCHAR(MAX))) AS Kode_Analisa,
                    MAX(CAST(ja.Jenis_Analisa AS VARCHAR(MAX))) AS Jenis_Analisa,
                    MAX(CAST(po.Kode_Barang AS VARCHAR(MAX))) AS Kode_Barang,
                    MAX(CAST(po.No_Split_Po AS VARCHAR(MAX))) AS No_Split_Po,
                    MAX(CAST(po.No_Batch AS VARCHAR(MAX))) AS No_Batch,
                    MAX(CAST(po.No_Po AS VARCHAR(MAX))) AS No_Po,
                    MAX(CAST(po.Keterangan AS VARCHAR(MAX))) AS Catatan_Po_Sampel,
                    MAX(CAST(po.Status AS VARCHAR(MAX))) AS Status,
                    MAX(po.Tanggal) AS Tanggal_Po_Sampel,
                    MAX(po.Jam) AS Jam_Po_Sampel,
                    MAX(CAST(m.Nama_Mesin AS VARCHAR(MAX))) AS Nama_Mesin,
                    MAX(CAST(m.Seri_Mesin AS VARCHAR(MAX))) AS Seri_Mesin
                FROM N_EMI_LAB_Uji_Sampel us
                INNER JOIN N_EMI_LAB_Jenis_Analisa ja ON us.Id_Jenis_Analisa = ja.id
                INNER JOIN N_EMI_LAB_PO_Sampel po ON us.No_Po_Sampel = po.No_Sampel
                INNER JOIN EMI_Master_Mesin m ON po.Id_Mesin = m.Id_Master_Mesin
                WHERE
                    us.No_Po_Sampel = ? AND
                    us.No_Fak_Sub_Po = ? AND
                    us.Flag_Multi_QrCode = ? AND
                    us.Id_Jenis_Analisa = ?
                GROUP BY us.No_Po_Sampel
            ", [
                $getNoSampelRaw->sampel,
                $getNoSampelRaw->no_ticket,
                $getNoSampelRaw->Flag_Multi_QrCode,
                $decoded_id_jenis_analisa
            ]));

        $checkedIsDraft = collect(DB::select("
            SELECT
                No_Urut, No_Sementara, No_Po_Sampel, No_Fak_Sub_Po,
                Id_Jenis_Analisa, CAST(RV AS INT) AS RV_INT
            FROM N_EMI_LAB_Uji_Sampel_Sementara
            WHERE No_Po_Sampel = ? AND No_Fak_Sub_Po = ? AND Id_Jenis_Analisa = ?
        ", [
            $getNoSampel->sampel,
            $getNoSampel->no_ticket,
            $decoded_id_jenis_analisa
        ]));

        $checkedIsDraftDetail = collect();
        if (!$checkedIsDraft->isEmpty()) {
            $noSementaraParams = implode(',', array_fill(0, count($checkedIsDraft), '?'));
            $noSementaraValues = $checkedIsDraft->pluck("No_Sementara")->toArray();

            $checkedIsDraftDetail = collect(DB::select("
                SELECT
                    No_Urut, No_Sementara, Id_Quality_Control, Value_Parameter,
                    Id_User, CAST(RV AS INT) AS RV_INT
                FROM N_EMI_LAB_Uji_Sampel_Detail_Sementara
                WHERE No_Sementara IN ($noSementaraParams)
            ", $noSementaraValues));
        }
        
        $perhitungans = collect(DB::select("
                SELECT Rumus
                FROM N_EMI_LAB_Perhitungan
                WHERE Id_Jenis_Analisa = ?
            ", [$decoded_id_jenis_analisa]))->map(function ($item) {
                $itemArray = (array) $item;

                // Gunakan regex untuk cari semua [angka]
                $rumus = $itemArray['Rumus'];
                $hashedRumus = preg_replace_callback('/\[(\d+)\]/', function ($matches) {
                    $angka = $matches[1];
                    $hashed = Hashids::connection('custom')->encode($angka);
                    return '[' . $hashed . ']';
                }, $rumus);

                return ['Rumus' => $hashedRumus];
            });

            $encodedDraftSummary = $checkedIsDraft->values()->map(function ($item, $index) use ($perhitungans) {
                $itemArray = (array) $item;

                // Ambil rumus berdasarkan urutan yang sama (misal: draft ke-0 cocok ke rumus ke-0)
                $rumusItem = $perhitungans[$index] ?? ['Rumus' => null];

                return [
                    'No_Urut'          => Hashids::connection('custom')->encode($itemArray['No_Urut']),
                    'No_Sementara'     => $itemArray['No_Sementara'],
                    'No_Po_Sampel'     => $itemArray['No_Po_Sampel'],
                    'No_Fak_Sub_Po'    => $itemArray['No_Fak_Sub_Po'],
                    'Id_Jenis_Analisa' => Hashids::connection('custom')->encode($itemArray['Id_Jenis_Analisa']),
                    'RV_INT'           => Hashids::connection('custom')->encode($itemArray['RV_INT']),
                    'Rumus'            => $rumusItem['Rumus'],
                ];
        });

        $encodedDraftDetail = $checkedIsDraftDetail->map(function ($item) {
            $itemArray = (array) $item;
            return [
                'No_Urut'              => Hashids::connection('custom')->encode($itemArray['No_Urut']),
                'No_Sementara'         => $itemArray['No_Sementara'],
                'Id_Quality_Control'   => Hashids::connection('custom')->encode($itemArray['Id_Quality_Control']),
                'Value_Parameter'      => $itemArray['Value_Parameter'],
                'RV_INT'                   => Hashids::connection('custom')->encode($itemArray['RV_INT']),
            ];
        });

        $result = [
            'no_ticket' => $getNoSampel->no_ticket,
            'sampel'    => $getNoSampel->sampel,
            'is_submit' => $checkSubmit->isEmpty() ? null : $checkSubmit->toArray(),
            'is_draft' => collect($encodedDraftSummary)->groupBy('No_Sementara')->map(function ($groupedSummaries, $noSementara) use ($encodedDraftDetail) {
                        return [
                            'no_sementara' => $noSementara,
                            'hasil' => $groupedSummaries->toArray(),
                            'parameter' => $encodedDraftDetail
                                ->where('No_Sementara', $noSementara)
                                ->values()
                                ->toArray(),
                        ];
                    })->values()->toArray()
        ];

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Data Ditemukan!',
            'result'  => $result
        ], 200);
    }

    public function getPoSampelNotMultiQrDetailForRumus($no_po_sampel, $id_jenis_analisa)
    {
        $kodeRoles = collect(Session::get('User_Roles', []))->pluck('Kode_Role')->toArray();
        if (empty($kodeRoles)) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Data tidak ditemukan (Role kosong).'
            ], 404);
        }

        try {
            $id_jenis_analisa = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Jenis Analisa tidak valid.'
            ], 400);
        }

        $jenisAnalisa = DB::table('N_EMI_LAB_Jenis_Analisa')
                        ->where('id', $id_jenis_analisa)
                        ->whereIn('Kode_Role', $kodeRoles)
                        ->first();

        if (!$jenisAnalisa) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Jenis Analisa tidak ditemukan.'
            ], 404);
        }

        $kodeAnalisa = $jenisAnalisa->Kode_Analisa;

   

        $checkSubmit = collect(DB::select("
                SELECT 
                    us.No_Po_Sampel,
                    MAX(us.Tanggal) AS Tanggal_Pengujian_Sampel,
                    MAX(us.Jam) AS Jam_Pengujian_Sampel,
                    MAX(us.Flag_Multi_QrCode) AS Flag_Multi_QrCode,
                    MAX(CAST(ja.Kode_Analisa AS VARCHAR(MAX))) AS Kode_Analisa,
                    MAX(CAST(ja.Jenis_Analisa AS VARCHAR(MAX))) AS Jenis_Analisa,
                    MAX(CAST(po.Kode_Barang AS VARCHAR(MAX))) AS Kode_Barang,
                    MAX(CAST(po.No_Split_Po AS VARCHAR(MAX))) AS No_Split_Po,
                    MAX(CAST(po.No_Batch AS VARCHAR(MAX))) AS No_Batch,
                    MAX(CAST(po.No_Po AS VARCHAR(MAX))) AS No_Po,
                    MAX(CAST(po.Keterangan AS VARCHAR(MAX))) AS Catatan_Po_Sampel,
                    MAX(CAST(po.Status AS VARCHAR(MAX))) AS Status,
                    MAX(po.Tanggal) AS Tanggal_Po_Sampel,
                    MAX(po.Jam) AS Jam_Po_Sampel,
                    MAX(CAST(m.Nama_Mesin AS VARCHAR(MAX))) AS Nama_Mesin,
                    MAX(CAST(m.Seri_Mesin AS VARCHAR(MAX))) AS Seri_Mesin
                FROM N_EMI_LIMS_Uji_Sampel us
                INNER JOIN N_EMI_LAB_Jenis_Analisa ja ON us.Id_Jenis_Analisa = ja.id
                INNER JOIN N_LIMS_PO_Sampel po ON us.No_Po_Sampel = po.No_Sampel
                INNER JOIN EMI_Master_Mesin m ON po.Id_Mesin = m.Id_Master_Mesin
                WHERE 
                    us.No_Po_Sampel = ? AND
                    us.Flag_Multi_QrCode IS NULL AND
                    us.Id_Jenis_Analisa = ? AND
                    us.Status IS NULL
                GROUP BY us.No_Po_Sampel
            ", [
                $no_po_sampel,
                $id_jenis_analisa
        ]));
       
        $checkedIsDraft = collect(DB::select("
            SELECT 
                No_Urut,
                No_Sementara,
                No_Po_Sampel,
                Id_Jenis_Analisa,
                No_Sementara,
                CAST(RV AS INT) AS RV_INT
            FROM N_EMI_LIMS_Uji_Sampel_Sementara
            WHERE No_Po_Sampel = ?
            AND Id_Jenis_Analisa = ?
        ", [
            $no_po_sampel,
            $id_jenis_analisa
        ]));

        $checkedIsDraftDetail = collect();
        if (!$checkedIsDraft->isEmpty()) {
            $noSementaraParams = implode(',', array_fill(0, count($checkedIsDraft), '?'));
            $noSementaraValues = $checkedIsDraft->pluck("No_Sementara")->toArray();

            $checkedIsDraftDetail = collect(DB::select("
                SELECT
                    No_Urut, No_Sementara, Id_Quality_Control, Value_Parameter,
                    Id_User, CAST(RV AS INT) AS RV_INT
                FROM N_EMI_LIMS_Uji_Sampel_Detail_Sementara
                WHERE No_Sementara IN ($noSementaraParams)
            ", $noSementaraValues));
        }
        
        $perhitungans = collect(DB::select("
                SELECT Rumus
                FROM N_EMI_LAB_Perhitungan
                WHERE Id_Jenis_Analisa = ?
            ", [$id_jenis_analisa]))->map(function ($item) {
                $itemArray = (array) $item;

                // Gunakan regex untuk cari semua [angka]
                $rumus = $itemArray['Rumus'];
                $hashedRumus = preg_replace_callback('/\[(\d+)\]/', function ($matches) {
                    $angka = $matches[1];
                    $hashed = Hashids::connection('custom')->encode($angka);
                    return '[' . $hashed . ']';
                }, $rumus);

                return ['Rumus' => $hashedRumus];
            });

            $encodedDraftSummary = $checkedIsDraft
            ->groupBy('No_Sementara')
            ->flatMap(function ($group, $noSementara) use ($perhitungans) {
                return $group->values()->map(function ($item, $index) use ($perhitungans) {
                    $itemArray = (array) $item;
                    $rumusItem = $perhitungans[$index] ?? ['Rumus' => null];
        
                    return [
                        'No_Urut'          => Hashids::connection('custom')->encode($itemArray['No_Urut']),
                        'No_Sementara'     => $itemArray['No_Sementara'],
                        'No_Po_Sampel'     => $itemArray['No_Po_Sampel'],
                        'Id_Jenis_Analisa' => Hashids::connection('custom')->encode($itemArray['Id_Jenis_Analisa']),
                        'RV_INT'           => Hashids::connection('custom')->encode($itemArray['RV_INT']),
                        'Rumus'            => $rumusItem['Rumus'],
                    ];
                });
            });
        

        $encodedDraftDetail = $checkedIsDraftDetail->map(function ($item) use ($kodeAnalisa) {
            $itemArray = (array) $item;
            $valueParameter = null; // Default value

            if (isset($itemArray['Value_Parameter'])) {
                // Cek jika Kode_Analisa adalah MBLG-STR
                if ($kodeAnalisa === 'MBLG-STR') {
                    if ($itemArray['Value_Parameter'] == -88888888) {
                        $valueParameter = '+';
                    } elseif ($itemArray['Value_Parameter'] == -999999) {
                        $valueParameter = '-';
                    } else {
                        $valueParameter = round((float)$itemArray['Value_Parameter'], 4);
                    }
                } else {
                    $valueParameter = round((float)$itemArray['Value_Parameter'], 4);
                }
            }
            return [
                'No_Urut'              => Hashids::connection('custom')->encode($itemArray['No_Urut']),
                'No_Sementara'         => $itemArray['No_Sementara'],
                'Id_Quality_Control'   => Hashids::connection('custom')->encode($itemArray['Id_Quality_Control']),
                'Value_Parameter' => $valueParameter,
                'RV_INT'                   => Hashids::connection('custom')->encode($itemArray['RV_INT']),
            ];
        });

        $result = [
            'sampel' => $no_po_sampel,
            'is_submit' => $checkSubmit->isEmpty() ? null : collect($checkSubmit->toArray())->map(function ($item) {
                    $isDone = DB::table('N_EMI_LAB_PO_Sampel')
                        ->where('No_Sampel', $item->No_Po_Sampel)
                        ->first();
                        
                return array_merge((array) $item, [
                    'is_done' => $isDone ? ($isDone->Flag_Selesai === 'Y') : false, 
                ]);
            }),
            'is_draft' => collect($encodedDraftSummary)->groupBy('No_Sementara')->map(function ($groupedSummaries, $noSementara) use ($encodedDraftDetail) {
                return [
                    'no_sementara' => $noSementara,
                    'hasil' => $groupedSummaries->toArray(),
                    'parameter' => $encodedDraftDetail
                        ->where('No_Sementara', $noSementara)
                        ->values()
                        ->toArray(),
                ];
            })->values()->toArray()
        ];
        

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Data Ditemukan!',
            'result'  => $result
        ], 200);
    }

    public function updateDataSampelForDraft(Request $request)
    {
        $request->validate([
            'analyses' => 'required|array|min:1',
            'analyses.*.No_Po_Sampel' => 'required|string',
            'analyses.*.No_Po_Multi_Sampel' => 'required|string',
            'analyses.*.Id_Jenis_Analisa' => 'required|integer',
            'analyses.*.parameters' => 'required|array',
            'analyses.*.parameters.*.Id_Quality_Control' => 'required',
            'analyses.*.parameters.*.Value_Parameter' => 'required|numeric',
        ]);

        DB::beginTransaction();

        try {
            $username = "frans"; 
            $tanggal = date('Y-m-d');
            $jam = date('H:i:s');

            $prefix = 'TMP-FUS' . date('my');
            $lastNumberRecord = DB::table('N_EMI_LAB_Uji_Sampel_Sementara')
                ->where('No_Sementara', 'like', $prefix . '-%')
                ->orderBy('No_Sementara', 'desc')
                ->first();

            $lastNumber = 0;
            if ($lastNumberRecord) {
                $lastNumber = (int) substr($lastNumberRecord->No_Sementara, -4);
            }

           
            $payloadUjiSampleData = [];
            $payloadUjiSampleDetailData = [];
            $payloadActiviyUjiSampelDetail = [];
            $payloadActivityUjiSampelHasil = [];
            $results = [];

            $groupedAnalyses = collect($request->analyses)->groupBy(function ($item) {
                return $item['No_Po_Sampel'] . '|' . $item['No_Po_Multi_Sampel'] . '|' . $item['Id_Jenis_Analisa'];
            });

            $noPoSampel = $request->analyses[0]['No_Po_Sampel'];
            $noMultiPoSampel = $request->analyses[0]['No_Po_Multi_Sampel'];
            $jenisAnalisa = $request->analyses[0]['Id_Jenis_Analisa'];

            $payloadActivityUjiSampel = [
                    'Kode_Perusahaan' => '001',
                    'No_Po_Sampel' => $noPoSampel,
                    'No_Fak_Sub_Po' => $noMultiPoSampel,
                    'Jenis_Aktivitas' => 'save_draft',
                    'Keterangan' => $username. ' Menyimpan Data Analisa Sebagai Draft',
                    'Id_User' => $username,
                    'Tanggal' => date('Y-m-d'),
                    'Jam' => date('H:i:s'),
                    'Id_Jenis_Analisa' => $jenisAnalisa
            ];

            $idLogActivity = DB::table('N_EMI_LAB_Activity_Uji_Sampel')->insertGetId(
                $payloadActivityUjiSampel,
                'Id_Log_Activity' 
            );


            foreach ($groupedAnalyses as $group) {
                $firstAnalysis = (object) $group->first();

                $existingCount = DB::table('N_EMI_LAB_Uji_Sampel_Sementara')
                    ->where('No_Po_Sampel', $firstAnalysis->No_Po_Sampel)
                    ->where('No_Fak_Sub_Po', $firstAnalysis->No_Po_Multi_Sampel)
                    ->where('Id_Jenis_Analisa', $firstAnalysis->Id_Jenis_Analisa)
                    ->count();

                if ($group->count() <= $existingCount) {
                    continue;
                }
                $newAnalysesToProcess = $group->slice($existingCount);

                $formulas = DB::table('N_EMI_LAB_Perhitungan')
                    ->where('Id_Jenis_Analisa', $firstAnalysis->Id_Jenis_Analisa)
                    ->get();

                foreach ($newAnalysesToProcess as $analysisData) {
                    $analysis = (object) $analysisData;
                    
                    $lastNumber++;
                    $newNumber = $prefix . '-' . str_pad($lastNumber, 4, '0', STR_PAD_LEFT);

                    $parameterValues = collect($analysis->parameters)->pluck('Value_Parameter', 'Id_Quality_Control');

                    $hasilPerhitungan = 0;
                    if ($formulas->isNotEmpty()) {
                        $formula = $formulas->first(); 
                        $hasilPerhitungan = $this->calculateFormulaServerSide($formula->Rumus, $parameterValues, $formula->Hasil_Perhitungan);
                    }
                    
             
                    $payloadUjiSampleData[] = [
                        "Kode_Perusahaan" => "001",
                        "No_Sementara" => $newNumber,
                        "No_Po_Sampel" => $analysis->No_Po_Sampel,
                        "No_Fak_Sub_Po" => $analysis->No_Po_Multi_Sampel,
                        "Id_Jenis_Analisa" => $analysis->Id_Jenis_Analisa,
                        "Hasil" => $hasilPerhitungan,
                        "Flag_Perhitungan" => "Y",
                        "Flag_Multi_QrCode" => $analysis->is_multi_print,
                        "Status" => null,
                        "Tanggal" => $tanggal,
                        "Jam" => $jam,
                        "Id_User" => $username,
                    ];

                   $payloadActivityUjiSampelHasil[] = [
                            "Kode_Perusahaan" => "001",
                            'Id_Log_Activity_Sampel' => $idLogActivity,
                            "No_Po_Sampel" => $analysis->No_Po_Sampel,
                            "No_Fak_Sub_Po" => $analysis->No_Po_Multi_Sampel,
                            "Id_Jenis_Analisa" => $analysis->Id_Jenis_Analisa,
                            "Value_Baru" =>$hasilPerhitungan,
                            "Value_Lama" =>$hasilPerhitungan,
                            "Tanggal" => $tanggal,
                            "Jam" => $jam,
                            "Id_User" => $username,
                            "Status_Submit" => "Drafted",
                    ];
                    
                    foreach ($analysis->parameters as $parameter) {
                        $payloadUjiSampleDetailData[] = [
                            "Kode_Perusahaan" => "001",
                            "No_Sementara" => $newNumber,
                            "Id_Quality_Control" => $parameter['Id_Quality_Control'],
                            "Value_Parameter" => $parameter['Value_Parameter'],
                            "Tanggal" => $tanggal,
                            "Jam" => $jam,
                            "Id_User" => $username,
                        ];

                        $payloadActiviyUjiSampelDetail[] = [
                            "Kode_Perusahaan" => "001",
                            'Id_Log_Activity_Sampel' => $idLogActivity,
                            "No_Po_Sampel" => $analysis->No_Po_Sampel,
                            "No_Fak_Sub_Po" => $analysis->No_Po_Multi_Sampel,
                            "Id_Jenis_Analisa" => $analysis->Id_Jenis_Analisa,
                            "Id_Quality_Control" => $parameter['Id_Quality_Control'],
                            "Value_Baru" => $parameter['Value_Parameter'],
                            "Value_Lama" => $parameter['Value_Parameter'],
                            "Tanggal" => $tanggal,
                            "Jam" => $jam,
                            "Id_User" => $username,
                            "Status_Submit" => "Drafted",
                        ];
                    }
                    $results[] = ['No_Sementara' => $newNumber];
                }
            }

          
            if (!empty($payloadUjiSampleData)) {
                DB::table('N_EMI_LAB_Uji_Sampel_Sementara')->insert($payloadUjiSampleData);
                DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')->insert($payloadUjiSampleDetailData);
                DB::table('N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail')->insert($payloadActiviyUjiSampelDetail);
                DB::table('N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail')->insert($payloadActivityUjiSampelHasil);
            }

         

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => "Data Berhasil Disimpan",
                'result' => $results
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('UjiSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan"
            ], 500);
        }
    }
    
    public function getDetailHasilSubmit($no_PO_Multiqr, $id_jenis_analisa)
    {
        // Decode ID Jenis Analisa
        try {
            $id_jenis_analisa = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Jenis Analisa tidak valid.'
            ], 400);
        }

        // Ambil informasi dasar No PO dan No Sampel
        $getNoSampelRaw = DB::selectOne("
            SELECT 
                psmq.No_Po_Multi AS no_ticket, 
                psmq.No_Po_Sampel AS sampel,
                us.Flag_Multi_QrCode
            FROM N_LIMS_PO_Sampel_Multi_QrCode psmq
            LEFT JOIN N_EMI_LIMS_Uji_Sampel us 
                ON psmq.No_Po_Sampel = us.No_Po_Sampel
            WHERE No_Po_Multi = ?
        ", [$no_PO_Multiqr]);

        if (empty($getNoSampelRaw)) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Data Tidak Ditemukan!'
            ], 404);
        }

        // Ambil semua No_Fak_Sub_Po terkait
        $dataSubPO = DB::select("
            SELECT 
                us.No_Fak_Sub_Po 
            FROM N_EMI_LIMS_Uji_Sampel us
            WHERE 
                us.No_Po_Sampel = ? AND
                us.No_Fak_Sub_Po = ? AND
                us.Flag_Multi_QrCode = ? AND
                us.Id_Jenis_Analisa = ?
            GROUP BY us.No_Fak_Sub_Po
        ", [
            $getNoSampelRaw->sampel,
            $getNoSampelRaw->no_ticket,
            $getNoSampelRaw->Flag_Multi_QrCode,
            $id_jenis_analisa
        ]);

        $result = [];

        foreach ($dataSubPO as $subpo) {
            // JOIN langsung dengan tabel perhitungan agar pembulatan sesuai
            $hasilRaw = DB::select("
                SELECT 
                    us.No_Faktur,
                    us.Hasil AS Hasil_Perhitungan,
                    p.Hasil_Perhitungan AS Pembulatan_Digit
                FROM N_EMI_LIMS_Uji_Sampel us
                LEFT JOIN N_EMI_LAB_Perhitungan p 
                    ON p.id = us.Id_Perhitungan 
                    AND p.Kode_Perusahaan = us.Kode_Perusahaan
                WHERE 
                    us.No_Po_Sampel = ? AND
                    us.No_Fak_Sub_Po = ? AND
                    us.Flag_Multi_QrCode = ? AND
                    us.Id_Jenis_Analisa = ?
            ", [
                $getNoSampelRaw->sampel,
                $subpo->No_Fak_Sub_Po,
                $getNoSampelRaw->Flag_Multi_QrCode,
                $id_jenis_analisa
            ]);

            $hasil = [];
            foreach ($hasilRaw as $item) {
                $digit = is_numeric($item->Pembulatan_Digit) ? (int) $item->Pembulatan_Digit : 2;
                $hasil[] = [
                    'No_Faktur' => $item->No_Faktur,
                    'Hasil_Perhitungan' => $item->Hasil_Perhitungan !== null
                        ? number_format((float)$item->Hasil_Perhitungan, $digit, '.', '')
                        : null
                ];
            }

            // Sorting berdasarkan No_Faktur (opsional)
            usort($hasil, function ($a, $b) {
                return strcmp($a['No_Faktur'], $b['No_Faktur']);
            });

            // Ambil daftar No_Faktur untuk ambil parameter
            $noFakturList = array_column($hasil, 'No_Faktur');
            $parameter = [];

            if (!empty($noFakturList)) {
                $placeholders = implode(',', array_fill(0, count($noFakturList), '?'));
                $parameterRaw = DB::select("
                    SELECT 
                        Value_Parameter 
                    FROM N_EMI_LIMS_Uji_Sampel_Detail
                    WHERE No_Faktur_Uji_Sample IN ($placeholders)
                ", $noFakturList);

                $parameter = array_map(function ($item) {
                    return [
                        'Value_Parameter' => $item->Value_Parameter !== null
                            ? round($item->Value_Parameter, 4)
                            : null
                    ];
                }, $parameterRaw);
            }

            $result[] = [
                'No_Fak_Sub_Po' => $subpo->No_Fak_Sub_Po,
                'hasil' => $hasil,
                'parameter' => $parameter
            ];
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => "Data Ditemukan",
            'result' => $result
        ], 200);
    }

    public function getDetailHasilSubmitNotMultiQrCode($no_po_sampel, $id_jenis_analisa)
    {
        try {
            $id_jenis_analisa = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Jenis Analisa tidak valid.'
            ], 400);
        }

        $dataSubPO = DB::select("
            SELECT us.No_Fak_Sub_Po 
            FROM N_EMI_LIMS_Uji_Sampel_Sementara us
            WHERE 
                us.No_Po_Sampel = ? AND
                us.Flag_Multi_QrCode IS NULL AND
                us.Id_Jenis_Analisa = ?
            GROUP BY us.No_Fak_Sub_Po
        ", [$no_po_sampel, $id_jenis_analisa]);

        $result = [];

        foreach ($dataSubPO as $subpo) {
            $hasilRaw = DB::select("
                SELECT 
                    us.No_Faktur,
                    us.Hasil AS Hasil_Perhitungan,
                    us.Id_Jenis_Analisa,
                    p.Hasil_Perhitungan AS Pembulatan_Digit
                FROM N_EMI_LIMS_Uji_Sampel us
                LEFT JOIN N_EMI_LAB_Perhitungan p ON p.id = us.Id_Perhitungan 
                    AND p.Kode_Perusahaan = us.Kode_Perusahaan
                WHERE 
                    us.No_Po_Sampel = ? AND
                    us.Flag_Multi_QrCode IS NULL AND
                    us.Id_Jenis_Analisa = ?
            ", [$no_po_sampel, $id_jenis_analisa]);

            $hasil = [];

            foreach ($hasilRaw as $item) {
                $digit = is_numeric($item->Pembulatan_Digit) ? (int) $item->Pembulatan_Digit : 2;
                $hasil[] = [
                    'No_Faktur' => $item->No_Faktur,
                    'Hasil_Perhitungan' => $item->Hasil_Perhitungan !== null
                        ? number_format((float)$item->Hasil_Perhitungan, $digit, '.', '')
                        : null
                ];
            }

            usort($hasil, function ($a, $b) {
                return strcmp($a['No_Faktur'], $b['No_Faktur']);
            });

            $noFakturList = array_column($hasil, 'No_Faktur');
            $parameter = [];

            if (!empty($noFakturList)) {
                $placeholders = implode(',', array_fill(0, count($noFakturList), '?'));
                $parameterRaw = DB::select("
                    SELECT 
                        Value_Parameter 
                    FROM N_EMI_LIMS_Uji_Sampel_Detail
                    WHERE No_Faktur_Uji_Sample IN ($placeholders)
                ", $noFakturList);

                $parameter = array_map(function ($item) {
                    return [
                        'Value_Parameter' => $item->Value_Parameter !== null
                            ? round($item->Value_Parameter, 4)
                            : null
                    ];
                }, $parameterRaw);
            }

            $result[] = [
                'hasil' => $hasil,
                'parameter' => $parameter
            ];
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => "Data Ditemukan",
            'result' => $result
        ], 200);
    }

    public function getDetailHasilSubmitNotMultiQrCodeNorumus($no_po_sampel, $id_jenis_analisa)
    {
    
        try {
            $id_jenis_analisa = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Jenis Analisa tidak valid.'
            ], 400);
        }


        // Ambil semua No_Fak_Sub_Po terkait
        $dataSubPO = DB::select("
            SELECT 
                us.No_Po_Sampel 
            FROM N_EMI_LAB_Uji_Sampel us
            WHERE 
                us.No_Po_Sampel = ? AND
                us.Flag_Multi_QrCode IS NULL AND
                us.Id_Jenis_Analisa = ?
            GROUP BY us.No_Po_Sampel
        ", [
            $no_po_sampel,
            $id_jenis_analisa
        ]);
        
        

        $result = [];

        foreach ($dataSubPO as $subpo) {
            // Ambil semua hasil berdasarkan No_Fak_Sub_Po
            $hasil = DB::select("
                SELECT 
                    us.No_Faktur,
                    us.Hasil AS Hasil_Perhitungan
                FROM N_EMI_LAB_Uji_Sampel us
                WHERE 
                    us.No_Po_Sampel = ? AND
                    us.Flag_Multi_QrCode IS NULL AND
                    us.Id_Jenis_Analisa = ?
            ", [
                $no_po_sampel,
                $id_jenis_analisa
            ]);

            $noFakturList = array_column($hasil, 'No_Faktur');

            // Ambil parameter untuk semua No_Faktur
            $parameter = [];
            if (!empty($noFakturList)) {
                $placeholders = implode(',', array_fill(0, count($noFakturList), '?'));
                $parameter = DB::select("
                    SELECT 
                        Value_Parameter 
                    FROM N_EMI_LAB_Uji_Sampel_Detail
                    WHERE No_Faktur_Uji_Sample IN ($placeholders)
                ", $noFakturList);
            }

            $result[] = [
                'hasil' => $hasil,
                'parameter' => $parameter
            ];
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => "Data Ditemukan",
            'result' => $result
        ], 200);
    }

    public function getDataTrackingInformasi($no_po_sampel, $no_PO_Multiqr, $id_jenis_analisa)
    {
        try {
            $kodeRoles = collect(Session::get('User_Roles', []))->pluck('Kode_Role')->toArray();
            
            if (empty($kodeRoles)) {
                return ResponseHelper::error('Data tidak ditemukan (Role kosong).', 404);
            }

            try {
                $id_jenis_analisa = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
            } catch (\Exception $e) {
                return ResponseHelper::error('Format ID Jenis Analisa tidak valid.', 400);
            }

            $parts = explode('-', $no_po_sampel);
            $base_no_sampel = count($parts) > 2 ? implode('-', array_slice($parts, 0, -1)) : $no_po_sampel;

            $getNoSampelRaw = DB::table('N_LIMS_PO_Sampel_Multi_QrCode as psmq')
                ->leftJoin('N_EMI_LIMS_Uji_Sampel as us', 'psmq.No_Po_Sampel', '=', 'us.No_Po_Sampel')
                ->select('psmq.No_Po_Multi as no_ticket', 'psmq.No_Po_Sampel as sampel', 'us.Flag_Multi_QrCode')
                ->where('psmq.No_Po_Multi', $no_PO_Multiqr)
                ->first();

            if (empty($getNoSampelRaw)) {
                return ResponseHelper::error('Data Tidak Ditemukan!', 404);
            }

            $result = DB::table('N_EMI_LIMS_Activity_Uji_Sampel AS aus')
                ->join('N_EMI_LAB_Jenis_Analisa AS ja', 'aus.Id_Jenis_Analisa', '=', 'ja.id')
                ->join('N_LIMS_PO_Sampel AS ps', 'aus.No_Po_Sampel', '=', 'ps.No_Sampel')
                ->select(
                    'aus.*',
                    'ps.No_Po',
                    'ps.No_Split_Po',
                    'ps.No_Batch',
                    'ja.Kode_Analisa',
                    'ja.Jenis_Analisa',
                    'ja.Flag_Perhitungan'
                )
                ->whereIn('ja.Kode_Role', $kodeRoles)
                ->where('aus.No_Po_Sampel', $base_no_sampel)
                ->where('aus.No_Fak_Sub_Po', $no_PO_Multiqr)
                ->where('aus.Id_Jenis_Analisa', $id_jenis_analisa)
                ->orderBy('aus.Id_Log_Activity', 'desc')
                ->get();

            if ($result->isEmpty()) {
                return ResponseHelper::success([], 'Data Ditemukan', 200);
            }

            $pluckedNoPoSampel = $result->pluck('No_Po_Sampel')->unique()->toArray();
            $pluckedNoFakSubPo = $result->pluck('No_Fak_Sub_Po')->unique()->toArray();
            $pluckedIdLogActivity = $result->pluck('Id_Log_Activity')->unique()->toArray();

            $getHasilAnalisa = DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail AS ahd')
                ->leftJoin('N_EMI_LIMS_Activity_Uji_Sampel AS aus', 'aus.Id_Log_Activity', '=', 'ahd.Id_Log_Activity_Sampel')
                ->leftJoin('N_EMI_LAB_Perhitungan AS p', function ($join) {
                    $join->on('ahd.Id_Perhitungan', '=', 'p.id')
                        ->on('ahd.Kode_Perusahaan', '=', 'p.Kode_Perusahaan');
                })
                ->select(
                    'ahd.No_Po_Sampel',
                    'ahd.Id_Log_Activity_Sampel',
                    'ahd.No_Fak_Sub_Po',
                    'ahd.Value_Baru',
                    'ahd.Value_Lama',
                    'ahd.Tanggal',
                    'ahd.Jam',
                    'ahd.Id_User',
                    'ahd.Status_Submit',
                    'p.Hasil_Perhitungan AS Pembulatan_Digit'
                )
                ->whereIn('p.Kode_Role', $kodeRoles)
                ->whereIn('ahd.No_Po_Sampel', $pluckedNoPoSampel)
                ->whereIn('ahd.No_Fak_Sub_Po', $pluckedNoFakSubPo)
                ->whereIn('ahd.Id_Log_Activity_Sampel', $pluckedIdLogActivity)
                ->where('ahd.Id_Jenis_Analisa', $id_jenis_analisa)
                ->get();

            $getParameterAnalisa = DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail')
                ->select(
                    'No_Po_Sampel',
                    'Id_Log_Activity_Sampel',
                    'No_Fak_Sub_Po',
                    'Value_Baru',
                    'Value_Lama',
                    'Tanggal',
                    'Jam',
                    'Id_User',
                    'Status_Submit',
                    'Alasan_Mengubah_Data'
                )
                ->whereIn('No_Po_Sampel', $pluckedNoPoSampel)
                ->whereIn('No_Fak_Sub_Po', $pluckedNoFakSubPo)
                ->whereIn('Id_Log_Activity_Sampel', $pluckedIdLogActivity)
                ->where('Id_Jenis_Analisa', $id_jenis_analisa)
                ->get();

            $finalResult = $result->map(function ($item) use ($getHasilAnalisa, $getParameterAnalisa) {
                $filteredHasil = $getHasilAnalisa->where('Id_Log_Activity_Sampel', $item->Id_Log_Activity)->values();
                $filteredParameter = $getParameterAnalisa->where('Id_Log_Activity_Sampel', $item->Id_Log_Activity)->values();

                $encodedHasil = $filteredHasil->map(function ($hasilItem) {
                    $digit = is_numeric($hasilItem->Pembulatan_Digit) ? (int) $hasilItem->Pembulatan_Digit : 2;

                    $hasilItem->Value_Lama = is_numeric($hasilItem->Value_Lama)
                        ? number_format((float)$hasilItem->Value_Lama, $digit, '.', '')
                        : $hasilItem->Value_Lama;

                    $hasilItem->Value_Baru = is_numeric($hasilItem->Value_Baru)
                        ? number_format((float)$hasilItem->Value_Baru, $digit, '.', '')
                        : $hasilItem->Value_Baru;

                    return $hasilItem;
                });

                $encodedParameter = $filteredParameter->map(function ($paramItem) {
                    $paramItem->Id_Log_Activity_Sampel = Hashids::connection('custom')->encode($paramItem->Id_Log_Activity_Sampel);

                    $paramItem->Value_Baru = $paramItem->Value_Baru !== null
                        ? round((float)$paramItem->Value_Baru, 4)
                        : null;

                    $paramItem->Value_Lama = $paramItem->Value_Lama !== null
                        ? round((float)$paramItem->Value_Lama, 4)
                        : null;

                    return $paramItem;
                });

                return [
                    'Id_Log_Activity' => Hashids::connection('custom')->encode($item->Id_Log_Activity),
                    'Id_Jenis_Analisa' => Hashids::connection('custom')->encode($item->Id_Jenis_Analisa),
                    'Kode_Perusahaan' => $item->Kode_Perusahaan,
                    'No_Po' => $item->No_Po,
                    'No_Split_Po' => $item->No_Split_Po,
                    'No_Batch' => $item->No_Batch,
                    'No_Po_Sampel' => $item->No_Po_Sampel,
                    'No_Fak_Sub_Po' => $item->No_Fak_Sub_Po,
                    'Jenis_Aktivitas' => $item->Jenis_Aktivitas,
                    'Keterangan' => $item->Keterangan,
                    'Alasan' => $encodedParameter->pluck('Alasan_Mengubah_Data')->filter()->unique()->values()->first() ?? null,
                    'Id_User' => $item->Id_User,
                    'Tanggal' => $item->Tanggal,
                    'Jam' => $item->Jam,
                    'Kode_Analisa' => $item->Kode_Analisa,
                    'Jenis_Analisa' => $item->Jenis_Analisa,
                    'Flag_Perhitungan' => $item->Flag_Perhitungan,
                    'hasil' => $encodedHasil,
                    'parameter' => $encodedParameter
                ];
            });

            return ResponseHelper::success($finalResult, 'Data Ditemukan', 200);

        } catch (\Exception $e) {
            Log::channel('FormulatorTrialSampelController')->error('Error getDataTrackingInformasi: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return ResponseHelper::error('Terjadi kesalahan sistem.', 500);
        }
    }

    public function getDataTrackingInformasiNotMultiQrCode($no_po_sampel,$id_jenis_analisa)
    {
        $kodeRoles = collect(Session::get('User_Roles', []))->pluck('Kode_Role')->toArray();
        if (empty($kodeRoles)) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Data tidak ditemukan (Role kosong).'
            ], 404);
        }

        try {
            $id_jenis_analisa = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Jenis Analisa tidak valid.'
            ], 400);
        }
        

       $result = collect(
            DB::table('N_EMI_LIMS_Activity_Uji_Sampel')
                ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LIMS_Activity_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
                ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LIMS_Activity_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
                ->select('N_EMI_LIMS_Activity_Uji_Sampel.*', 'N_EMI_LAB_PO_Sampel.No_Po', 'N_EMI_LAB_PO_Sampel.No_Split_Po', 'N_EMI_LAB_PO_Sampel.No_Batch', 'N_EMI_LAB_Jenis_Analisa.Kode_Analisa', 'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa', 'N_EMI_LAB_Jenis_Analisa.Flag_Perhitungan')
                ->where('N_EMI_LIMS_Activity_Uji_Sampel.No_Po_Sampel', $no_po_sampel)
                ->where('N_EMI_LIMS_Activity_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa)
                ->orderBy('N_EMI_LIMS_Activity_Uji_Sampel.Id_Log_Activity', 'desc') 
                ->get()
        );

        $getHasilAnalisa = collect(
            DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail')
                ->select('No_Po_Sampel', 'Id_Log_Activity_Sampel' ,'No_Fak_Sub_Po',  'Value_Baru', 'Value_Lama', 'Tanggal', 'Jam', 'Id_User', 'Status_Submit')
                ->whereIn('No_Po_Sampel', $result->pluck('No_Po_Sampel'))
                ->whereIn('Id_Log_Activity_Sampel', $result->pluck('Id_Log_Activity'))
                ->where('Id_Jenis_Analisa', $id_jenis_analisa)
                ->get()
        );

        $getParameterAnalisa = collect(
            DB::table('N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail')
              ->select('No_Po_Sampel', 'Id_Log_Activity_Sampel' ,'No_Fak_Sub_Po',  'Value_Baru', 'Value_Lama', 'Tanggal', 'Jam', 'Id_User', 'Status_Submit', 'Alasan_Mengubah_Data')
                ->whereIn('No_Po_Sampel', $result->pluck('No_Po_Sampel'))
                ->whereIn('Id_Log_Activity_Sampel', $result->pluck('Id_Log_Activity'))
                ->where('Id_Jenis_Analisa', $id_jenis_analisa)
                ->get()
        );

        // 1. Ambil semua hasil pembulatan (tanpa mapping ID)
        $roundingDigitRows = DB::table('N_EMI_LAB_Perhitungan')
        ->select('Hasil_Perhitungan')
        ->where('Id_Jenis_Analisa', $id_jenis_analisa)
        ->whereIn('Kode_Role', $kodeRoles)
        ->get()
        ->pluck('Hasil_Perhitungan')
        ->values();

     
        $finalResult = $result->map(function ($item) use ($getHasilAnalisa, $getParameterAnalisa, $roundingDigitRows) {
            /** @var object $item */

            // Proses filter 'hasil' dan 'parameter' tetap sama
            $filteredHasil = $getHasilAnalisa->where('Id_Log_Activity_Sampel', $item->Id_Log_Activity)->values();
            $filteredParameter = $getParameterAnalisa->where('Id_Log_Activity_Sampel', $item->Id_Log_Activity)->values();
            /**
             * @param TValue $hasilItem
             */
            // Encode ID di dalam nested array 'hasil'
     
            $encodedHasil = $filteredHasil->values()->map(function($hasilItem, $index) use ($roundingDigitRows) {
                $precision = $roundingDigitRows[$index] ?? 2; // fallback kalau tidak cukup
            
                $hasilItem->Value_Lama = is_numeric($hasilItem->Value_Lama)
                    ? number_format((float)$hasilItem->Value_Lama, $precision, '.', '')
                    : $hasilItem->Value_Lama;
            
                $hasilItem->Value_Baru = is_numeric($hasilItem->Value_Baru)
                    ? number_format((float)$hasilItem->Value_Baru, $precision, '.', '')
                    : $hasilItem->Value_Baru;
            
                return $hasilItem;
            });

             /**
             * @param TValue $paramItem
             */

            // Encode ID di dalam nested array 'parameter'
            $encodedParameter = $filteredParameter->map(function($paramItem) {
                    $paramItem->Id_Log_Activity_Sampel = Hashids::connection('custom')->encode($paramItem->Id_Log_Activity_Sampel);

                    $paramItem->Value_Baru = $paramItem->Value_Baru !== null
                        ? round($paramItem->Value_Baru, 4)
                        : null;

                    $paramItem->Value_Lama = $paramItem->Value_Lama !== null
                        ? round($paramItem->Value_Lama, 4)
                        : null;

                    return $paramItem;
            });

            // Sekarang, buat array hasil akhir dengan ID yang sudah di-encode
            return [
                // ENCODE ID DI LEVEL UTAMA
                'Id_Log_Activity' => Hashids::connection('custom')->encode($item->Id_Log_Activity),
                'Id_Jenis_Analisa' => Hashids::connection('custom')->encode($item->Id_Jenis_Analisa),

                'Kode_Perusahaan' => $item->Kode_Perusahaan,
                'No_Po' => $item->No_Po,
                'No_Split_Po' => $item->No_Split_Po,
                'No_Batch' => $item->No_Batch,
                'No_Po_Sampel' => $item->No_Po_Sampel,
                'No_Fak_Sub_Po' => $item->No_Fak_Sub_Po,
                'Jenis_Aktivitas' => $item->Jenis_Aktivitas,
                'Keterangan' => $item->Keterangan,
                'Alasan' => $encodedParameter // Menggunakan parameter yang sudah di-encode
                    ->pluck('Alasan_Mengubah_Data')
                    ->filter()
                    ->unique()
                    ->values()
                    ->first() ?? null,
                'Id_User' => $item->Id_User,
                'Tanggal' => $item->Tanggal,
                'Jam' => $item->Jam,
                'Kode_Analisa' => $item->Kode_Analisa,
                'Jenis_Analisa' => $item->Jenis_Analisa,
                'Flag_Perhitungan' => $item->Flag_Perhitungan,

                // Gunakan hasil dan parameter yang ID-nya sudah di-encode
                'hasil' => $encodedHasil,
                'parameter' => $encodedParameter
            ];
        });

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => "Data Ditemukan",
            'result' => $finalResult
        ], 200);
    }

    public function getDataConfirmedSelesai()
    {
        $result = collect(
            DB::table('N_EMI_LAB_Uji_Sampel')
                ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
                ->select(
                    DB::raw('MAX(N_EMI_LAB_Uji_Sampel.Tanggal) as Tanggal'),
                    DB::raw('MAX(N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa) as Id_Jenis_Analisa'),
                    DB::raw('MAX(N_EMI_LAB_Jenis_Analisa.Jenis_Analisa) as Jenis_Analisa'),
                    DB::raw('MAX(N_EMI_LAB_Jenis_Analisa.Kode_Analisa) as Kode_Analisa'),
               
                )
                ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
                ->whereNull('N_EMI_LAB_Uji_Sampel.Flag_Selesai')
                ->groupBy('N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa')
                ->orderByDesc('Tanggal')
                ->get()
        )->map(function ($item) {
            /** @var object $item */
            $item->Id_Jenis_Analisa = Hashids::connection('custom')->encode($item->Id_Jenis_Analisa);
            return $item;
        });

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => "Data Ditemukan",
            'result' => $result
        ], 200);
    }

    public function getDataConfirmedSelesaiV2(Request $request)
    {
        $searchQuery = $request->input('q', '');
        $limit = $request->input('limit', 10);
        $filterTanggalMulai = $request->input('tanggal_mulai');
        $filterTanggalSelesai = $request->input('tanggal_selesai');
        $filterQrCode = $request->input('qrcode');

        // 1. Base Query: Grouping by Sample & Analysis Type
        $baseQuery = DB::table('N_EMI_LIMS_Uji_Sampel')
            ->join('N_LIMS_PO_Sampel', 'N_EMI_LIMS_Uji_Sampel.No_Po_Sampel', '=', 'N_LIMS_PO_Sampel.No_Sampel')
            ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
            ->select(
                'N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa',
                'N_EMI_LIMS_Uji_Sampel.No_Po_Sampel',
                'N_EMI_LIMS_Uji_Sampel.Flag_Multi_QrCode',
                'N_EMI_LIMS_Uji_Sampel.Tanggal',
                'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa',
                'N_EMI_LAB_Jenis_Analisa.Kode_Analisa'
            )
            ->where('N_EMI_LAB_Jenis_Analisa.Kode_Role', 'FLM')
            ->whereNull('N_EMI_LIMS_Uji_Sampel.Status')
            ->whereNull('N_EMI_LIMS_Uji_Sampel.Flag_Selesai')
            ->where('N_EMI_LIMS_Uji_Sampel.Status_Keputusan_Sampel', 'menunggu')
            ->groupBy(
                'N_EMI_LIMS_Uji_Sampel.No_Po_Sampel',
                'N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa',
                'N_EMI_LIMS_Uji_Sampel.Flag_Multi_QrCode',
                'N_EMI_LIMS_Uji_Sampel.Tanggal',
                'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa',
                'N_EMI_LAB_Jenis_Analisa.Kode_Analisa'
            );

        if (!empty($searchQuery)) {
            $baseQuery->where(function ($query) use ($searchQuery) {
                $query->where('N_EMI_LIMS_Uji_Sampel.No_Po_Sampel', 'like', "%$searchQuery%")
                    ->orWhere('N_LIMS_PO_Sampel.No_Po', 'like', "%$searchQuery%")
                    ->orWhere('N_LIMS_PO_Sampel.No_Split_Po', 'like', "%$searchQuery%")
                    ->orWhere('N_LIMS_PO_Sampel.No_Batch', 'like', "%$searchQuery%");
            });
        }

        if ($filterTanggalMulai && $filterTanggalSelesai) {
            $baseQuery->whereBetween('N_EMI_LIMS_Uji_Sampel.Tanggal', [$filterTanggalMulai, $filterTanggalSelesai]);
        }

        if ($filterQrCode) {
            if ($filterQrCode === 'multi') {
                $baseQuery->where('N_EMI_LIMS_Uji_Sampel.Flag_Multi_QrCode', 'Y');
            } elseif ($filterQrCode === 'single') {
                $baseQuery->where(function ($query) {
                    $query->where('N_EMI_LIMS_Uji_Sampel.Flag_Multi_QrCode', '!=', 'Y')
                        ->orWhereNull('N_EMI_LIMS_Uji_Sampel.Flag_Multi_QrCode');
                });
            }
        }

        $paginatedData = $baseQuery->orderByDesc('N_EMI_LIMS_Uji_Sampel.Tanggal')->paginate($limit);
        $noPoSampelList = $paginatedData->pluck('No_Po_Sampel')->toArray();

        // 2. Fetch ALL history details for these samples
        // Sorted strictly to ensure the first item found is the latest update
        $allInfoRows = DB::table('N_EMI_LIMS_Uji_Sampel')
            ->join('N_LIMS_PO_Sampel', 'N_EMI_LIMS_Uji_Sampel.No_Po_Sampel', '=', 'N_LIMS_PO_Sampel.No_Sampel')
            ->join('EMI_Master_Mesin', 'N_LIMS_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
            ->select(
                'N_EMI_LIMS_Uji_Sampel.No_Po_Sampel',
                'N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa',
                'N_LIMS_PO_Sampel.No_Po',
                'N_LIMS_PO_Sampel.Tanggal as Tanggal_Registrasi',
                'N_LIMS_PO_Sampel.Jam as Jam_Registrasi',
                'N_LIMS_PO_Sampel.Kode_Barang',
                'N_LIMS_PO_Sampel.No_Split_Po',
                'N_LIMS_PO_Sampel.No_Batch',
                'N_EMI_LIMS_Uji_Sampel.Jam',
                'N_EMI_LIMS_Uji_Sampel.Tanggal',
                'EMI_Master_Mesin.Nama_Mesin',
                'N_EMI_LIMS_Uji_Sampel.Flag_Layak',
                'N_EMI_LIMS_Uji_Sampel.Id_User',
                'N_EMI_LIMS_Uji_Sampel.Tahapan_Ke'
            )
            ->whereIn('N_EMI_LIMS_Uji_Sampel.No_Po_Sampel', $noPoSampelList)
            ->orderByDesc('N_EMI_LIMS_Uji_Sampel.Tahapan_Ke')
            ->orderByDesc('N_EMI_LIMS_Uji_Sampel.Tanggal')
            ->orderByDesc('N_EMI_LIMS_Uji_Sampel.Jam')
            ->get();

        // Group by Sample ID only. We will filter by Analysis ID inside the loop.
        $groupedInfos = $allInfoRows->groupBy('No_Po_Sampel');

        $kodeBarangList = $allInfoRows->pluck('Kode_Barang')->unique()->filter()->toArray();
        $barangList = DB::table('N_EMI_View_Barang')
            ->whereIn('Kode_Barang', $kodeBarangList)
            ->pluck('Nama', 'Kode_Barang');

        $paginatedData->getCollection()->transform(function ($item) use ($groupedInfos, $barangList) {
            $rawIdJenisAnalisa = $item->Id_Jenis_Analisa;
            
            // Encode ID for response
            $item->Id_Jenis_Analisa = Hashids::connection('custom')->encode($item->Id_Jenis_Analisa);

            // Get all history for this sample
            $sampleHistory = $groupedInfos->get($item->No_Po_Sampel);
            
            // Find specific row for THIS Analysis Type (Reza for Particle, Frans for Salmonella)
            // Because of the OrderByDesc in query, first() is the latest.
            $specificInfo = null;
            if ($sampleHistory) {
                $specificInfo = $sampleHistory->where('Id_Jenis_Analisa', $rawIdJenisAnalisa)->first();
            }

            if ($specificInfo) {
                // Assign specific User/Time data
                $item->Id_User            = $specificInfo->Id_User;
                $item->Jam                = $specificInfo->Jam;
                $item->Flag_Layak         = $specificInfo->Flag_Layak;
                
                // Assign Common PO Data
                $item->Tanggal_Registrasi = $specificInfo->Tanggal_Registrasi;
                $item->Jam_Registrasi     = $specificInfo->Jam_Registrasi;
                $item->Nama_Barang        = $barangList[$specificInfo->Kode_Barang] ?? null;
                $item->po_info = [
                    'No_Po'         => $specificInfo->No_Po,
                    'No_Split_Po'   => $specificInfo->No_Split_Po,
                    'No_Batch'      => $specificInfo->No_Batch,
                    'Kode_Barang'   => $specificInfo->Kode_Barang,
                    'Nama_Mesin'    => $specificInfo->Nama_Mesin,
                ];
            } else {
                // Fallback if data missing (should not happen if inner join is correct)
                $item->Id_User = null;
                $item->Jam = null;
                $item->Flag_Layak = null;
                $item->Tanggal_Registrasi = null;
                $item->Jam_Registrasi = null;
                $item->Nama_Barang = null;
                $item->po_info = null;
            }

            // Logic Status Keputusan (Lolos/Tidak)
            $autoLolosKodes = ['PSZ'];
            $kodeAnalisa = trim($item->Kode_Analisa);

            if (in_array($kodeAnalisa, $autoLolosKodes)) {
                $item->Status_Sampel = "Lolos Uji";
            } else {
                // Calculate status based ONLY on rows for this Analysis Type
                if ($sampleHistory && $sampleHistory->isNotEmpty()) {
                    $filteredRows = $sampleHistory->where('Id_Jenis_Analisa', $rawIdJenisAnalisa);

                    if ($filteredRows->isNotEmpty()) {
                        $maxTahapan = $filteredRows->max('Tahapan_Ke');
                        $specificFlags = $filteredRows->where('Tahapan_Ke', $maxTahapan)
                            ->pluck('Flag_Layak')
                            ->toArray();

                        if (empty($specificFlags)) {
                            $item->Status_Sampel = "Tidak Lolos Uji";
                        } else {
                            $allLolos = true;
                            foreach ($specificFlags as $flag) {
                                if ($flag !== 'Y') {
                                    $allLolos = false;
                                    break;
                                }
                            }
                            $item->Status_Sampel = $allLolos ? "Lolos Uji" : "Tidak Lolos Uji";
                        }
                    } else {
                        $item->Status_Sampel = "Tidak Lolos Uji";
                    }
                } else {
                    $item->Status_Sampel = "Tidak Lolos Uji";
                }
            }

            unset($item->Flag_Layak);
            return $item;
        });

        if ($paginatedData->total() === 0) {
            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => "Data tidak ditemukan sesuai kriteria pencarian Anda.",
                'result'  => [
                    'data' => [],
                    'pagination' => [
                        'page'      => 1,
                        'limit'     => (int)$limit,
                        'totalPage' => 0,
                        'totalData' => 0,
                    ]
                ]
            ], 200);
        }

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => "Data Ditemukan",
            'result'  => [
                'data' => $paginatedData->getCollection(),
                'pagination' => [
                    'page'      => $paginatedData->currentPage(),
                    'limit'     => $paginatedData->perPage(),
                    'totalPage' => $paginatedData->lastPage(),
                    'totalData' => $paginatedData->total(),
                ]
            ]
        ], 200);
    }

    public function getDataValidasiHasilAkhirDanCloseSampel(Request $request)
    {
        $perPage = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $qrType = $request->input('qr_type'); 

        $query = DB::table('N_EMI_LIMS_Uji_Sampel as uji')
            ->join('N_LIMS_PO_Sampel as po', 'uji.No_Po_Sampel', '=', 'po.No_Sampel')
            ->join('N_EMI_View_Barang as brg', 'po.Kode_Barang', '=', 'brg.Kode_Barang')
            ->join('N_EMI_LIMS_Uji_Pra_Final as pra', 'uji.No_Po_Sampel', '=', 'pra.No_Sampel')
            ->select(
                'uji.No_Po_Sampel',
                DB::raw('MAX(uji.Tanggal) as Tanggal'),
                DB::raw('MAX(uji.Jam) as Jam'),
                'uji.Flag_Multi_QrCode',
                'po.No_Po',
                'po.No_Split_Po',
                'po.Kode_Barang',
                'brg.Nama as Nama_Barang'
            )
            ->whereNull('uji.Status')
            ->where('uji.Flag_Selesai', 'Y')
            ->whereNull('uji.Flag_Final')
            ->where('uji.Status_Keputusan_Sampel', 'terima')
            ->where(function($q) {
                $q->where('uji.Flag_Resampling', '!=', 'Y')
                ->orWhereNull('uji.Flag_Resampling');
            })
            ->where('pra.Flag_Setuju', 'Y');

        if (!empty($startDate) && !empty($endDate)) {
            $query->whereBetween('uji.Tanggal', [$startDate, $endDate]);
        }

        if (!empty($qrType)) {
            if ($qrType === 'Y') {
                $query->where('uji.Flag_Multi_QrCode', 'Y');
            } else {
                $query->where(function($q) {
                    $q->where('uji.Flag_Multi_QrCode', '!=', 'Y')
                      ->orWhereNull('uji.Flag_Multi_QrCode');
                });
            }
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('uji.No_Po_Sampel', 'LIKE', "%{$search}%")
                  ->orWhere('po.No_Po', 'LIKE', "%{$search}%")
                  ->orWhere('po.No_Split_Po', 'LIKE', "%{$search}%")
                  ->orWhere('po.Kode_Barang', 'LIKE', "%{$search}%")
                  ->orWhere('brg.Nama', 'LIKE', "%{$search}%");
            });
        }

        $query->groupBy(
            'uji.No_Po_Sampel',
            'uji.Flag_Multi_QrCode',
            'po.No_Po',
            'po.No_Split_Po',
            'po.Kode_Barang',
            'brg.Nama'
        )
        ->orderByDesc(DB::raw('MAX(uji.Tanggal)'))
        ->orderByDesc(DB::raw('MAX(uji.Jam)'));

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        return ResponseHelper::successWithPaginationV2(
            $paginated->items(),
            $paginated->currentPage(),
            $paginated->perPage(),
            $paginated->total(),
            "Data Ditemukan",
            200,
            'v1'
        );
    }

    public function validasiDataMultiQrCodeV2($No_Po_Sampel, $id_jenis_analisa)
    {
       try {
            $id_jenis_analisa = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Jenis Analisa tidak valid.'
            ], 400);
        }

        $result = collect(
            DB::table('N_EMI_LIMS_Uji_Sampel')
                ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
                ->select(
                    'N_EMI_LIMS_Uji_Sampel.*',
                    'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa',
                    'N_EMI_LAB_Jenis_Analisa.Kode_Analisa'
                )
                ->where('N_EMI_LAB_Jenis_Analisa.Kode_Role', 'FLM')
                ->where('N_EMI_LIMS_Uji_Sampel.No_Po_Sampel', $No_Po_Sampel)
                ->where('N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa)
                ->whereNull('N_EMI_LIMS_Uji_Sampel.Status')
                ->whereNull('N_EMI_LIMS_Uji_Sampel.Flag_Selesai')
                ->where('N_EMI_LIMS_Uji_Sampel.Status_Keputusan_Sampel', 'menunggu')
                ->orderByDesc('Tanggal')
                ->get()
        )
        ->unique(function ($item) {
                /** @var object $item */
            return $item->No_Po_Sampel . '|' . $item->No_Fak_Sub_Po;
        })
        ->values() 
        ->map(function ($item) {
             /** @var object $item */
            $item->Id_Jenis_Analisa = Hashids::connection('custom')->encode($item->Id_Jenis_Analisa);
            return $item;
        });

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => "Data Ditemukan",
            'result' => $result
        ], 200);
    }

    public function validasiHasilAkhirDariValidasiAwal($No_Po_Sampel)
    {
         $result = collect(
            DB::table('N_EMI_LAB_Uji_Sampel')
                ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
                ->select(
                    'N_EMI_LAB_Uji_Sampel.*',
                    'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa',
                    'N_EMI_LAB_Jenis_Analisa.Kode_Analisa'
                )
                ->where('No_Po_Sampel', $No_Po_Sampel)
                ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
                ->where('N_EMI_LAB_Uji_Sampel.Flag_Selesai', 'Y')
                ->whereNull('N_EMI_LAB_Uji_Sampel.Flag_Final')
                ->where('N_EMI_LAB_Uji_Sampel.Status_Keputusan_Sampel', 'terima')
                ->orderByDesc('Tanggal')
                ->get()
        )->map(function ($item) {
            /** @var object $item */
            $item->Id_Jenis_Analisa = Hashids::connection('custom')->encode($item->Id_Jenis_Analisa);
            return $item;
        })->unique(function ($item) {
            return $item->No_Po_Sampel . '-' . $item->No_Fak_Sub_Po;
        })->values(); 

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => "Data Ditemukan",
            'result' => $result
        ], 200);
    }

    public function validasiDataJenisAnalisaMultiQrCodeV2($No_Po_Sampel, $No_Fak_Sub_Po, $id_jenis_analisa) 
    {
         try {
            $id_jenis_analisa = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Jenis Analisa tidak valid.'
            ], 400);
        }

        $result = collect(
            DB::table('N_EMI_LIMS_Uji_Sampel')
                ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
                ->select(
                    'N_EMI_LIMS_Uji_Sampel.*',
                    'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa',
                    'N_EMI_LAB_Jenis_Analisa.Kode_Analisa'
                )
                ->where('N_EMI_LAB_Jenis_Analisa.Kode_Role', 'FLM')
                ->where('No_Po_Sampel', $No_Po_Sampel)
                ->where('No_Fak_Sub_Po', $No_Fak_Sub_Po)
                ->where('Id_Jenis_Analisa', $id_jenis_analisa)
                ->whereNull('N_EMI_LIMS_Uji_Sampel.Status')
                ->whereNull('N_EMI_LIMS_Uji_Sampel.Flag_Selesai')
                ->where('N_EMI_LIMS_Uji_Sampel.Status_Keputusan_Sampel', 'menunggu')
                ->orderByDesc('Tanggal')
                ->get()
        )
        ->map(function ($item) {
            /** @var \stdClass $item */
            $item->Id_Jenis_Analisa = Hashids::connection('custom')->encode($item->Id_Jenis_Analisa);
            return $item;
        })
        ->unique(function ($item) {
            return $item->Id_Jenis_Analisa;
        })
        ->values(); // Reset index

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => "Data Ditemukan",
            'result' => $result
        ], 200);
    }

    public function validasiDataJenisAnalisaSingleQrCodeV2($No_Po_Sampel) 
    {
        $result = collect(
            DB::table('N_EMI_LIMS_Uji_Sampel')
                ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
                ->select(
                'N_EMI_LIMS_Uji_Sampel.*',
                'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa',
                'N_EMI_LAB_Jenis_Analisa.Kode_Analisa'
                )
                ->where('N_EMI_LAB_Jenis_Analisa.Kode_Role', 'FLM')
                ->where('No_Po_Sampel', $No_Po_Sampel)
                ->whereNull('N_EMI_LIMS_Uji_Sampel.Status')
                ->whereNull('N_EMI_LIMS_Uji_Sampel.Flag_Selesai')
                ->where('N_EMI_LIMS_Uji_Sampel.Status_Keputusan_Sampel', 'menunggu')
                ->orderByDesc('Tanggal')
                ->get()
        )
        // Step 1: Encode ID Jenis Analisa dulu
        ->map(function ($item) {
            /** @var \stdClass $item */
            $item->Id_Jenis_Analisa = Hashids::connection('custom')->encode($item->Id_Jenis_Analisa);
            return $item;
        })
        ->unique(function ($item) {
            return $item->Id_Jenis_Analisa;
        })
        ->values(); 

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => "Data Ditemukan",
            'result' => $result
        ], 200);
    }

    public function validasiHasilAkhirDariValidasiAwalJenisAnalisaV1($No_Po_Sampel)
    {
        $result = collect(
        DB::table('N_EMI_LIMS_Uji_Sampel')
            ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
            ->select(
                'N_EMI_LIMS_Uji_Sampel.*',
                'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa',
                'N_EMI_LAB_Jenis_Analisa.Kode_Analisa'
            )
            ->where('No_Po_Sampel', $No_Po_Sampel)
            ->whereNull('N_EMI_LIMS_Uji_Sampel.Status')
            ->where('N_EMI_LIMS_Uji_Sampel.Flag_Selesai', 'Y')
            ->where('N_EMI_LIMS_Uji_Sampel.Status_Keputusan_Sampel', 'terima')
            ->orderByDesc('Tanggal')
            ->get()
            )->map(function ($item) {
                /** @var object $item */
                $item->Id_Jenis_Analisa = Hashids::connection('custom')->encode($item->Id_Jenis_Analisa);
                return $item;
            })->unique(function ($item) {
                // Kombinasi unik berdasarkan No_Po_Sampel dan Id_Jenis_Analisa (yang sudah di-hash)
                return $item->No_Po_Sampel . '-' . $item->Id_Jenis_Analisa;
            })->values(); // reset index agar rapih

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Data Ditemukan",
                'result' => $result
            ], 200);
    }

    public function getDataConfirmedSelesaiByJenisAnalisa($id_jenis_analisa)
    {
        try {
            $id_jenis_analisa = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Jenis Analisa tidak valid.'
            ], 400);
        }

        $ujiSampel = DB::table('N_EMI_LAB_Uji_Sampel')
            ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
            ->join('EMI_Master_Mesin', 'N_EMI_LAB_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
            ->select(
                'N_EMI_LAB_Uji_Sampel.No_Faktur',
                'N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode',
                'N_EMI_LAB_Uji_Sampel.Flag_Perhitungan',
                'N_EMI_LAB_Uji_Sampel.No_Po_Sampel',
                'N_EMI_LAB_Uji_Sampel.No_Fak_Sub_Po',
                'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa',
                'N_EMI_LAB_Uji_Sampel.Tanggal as Tanggal_Pengujian',
                'N_EMI_LAB_Uji_Sampel.Jam as Jam_Pengujian',
                'N_EMI_LAB_Uji_Sampel.Hasil as Hasil_Akhir_Analisa',
                'N_EMI_LAB_PO_Sampel.Tanggal as Tanggal_Pengajuan',
                'N_EMI_LAB_PO_Sampel.Jam as Jam_Pengajuan',
                'N_EMI_LAB_PO_Sampel.No_Po',
                'N_EMI_LAB_PO_Sampel.Keterangan as Catatan',
                'N_EMI_LAB_PO_Sampel.No_Split_Po',
                'N_EMI_LAB_PO_Sampel.No_Batch',
                'N_EMI_LAB_PO_Sampel.Kode_Barang',
                'EMI_Master_Mesin.Id_Master_Mesin',
                'EMI_Master_Mesin.Seri_Mesin',
                'EMI_Master_Mesin.Nama_Mesin', 
                DB::raw("ISNULL((SELECT x.Hasil_Perhitungan FROM N_EMI_LAB_Perhitungan x 
                        WHERE x.id = N_EMI_LAB_Uji_Sampel.Id_Perhitungan 
                        AND x.Kode_Perusahaan = N_EMI_LAB_Uji_Sampel.Kode_Perusahaan), 0) AS Pembulatan")
                )
            ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
            ->where('N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa)
            ->whereNull('N_EMI_LAB_Uji_Sampel.Flag_Selesai')
            ->get();


        if ($ujiSampel->isEmpty()) {
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Data Tidak Ditemukan',
                'result' => [
                    'informasi_detail' => [],
                    'data_sampel' => []
                ]
            ], 200);
        }

        foreach ($ujiSampel as $item) {
            $item->Hasil_Akhir_Analisa = number_format((float)$item->Hasil_Akhir_Analisa, $item->Pembulatan, '.', '');
        }

        $noFakturList = $ujiSampel->pluck('No_Faktur')->unique();

        $parameterRaw = DB::table('N_EMI_LAB_Uji_Sampel_Detail')
            ->select(
                'Id_Uji_Sample_Detail',
                'No_Faktur_Uji_Sample',
                'Id_Quality_Control',
                'Value_Parameter as Hasil_Analisa',
                'Tanggal as Tanggal_Parameter_Analisa',
                'Jam as Jam_Parameter_Analisa'
            )
            ->whereIn('No_Faktur_Uji_Sample', $noFakturList)
            ->get();

        $parameterGrouped = [];
        foreach ($parameterRaw as $param) {
            $encodedId = Hashids::connection('custom')->encode($param->Id_Uji_Sample_Detail);
            if (!isset($parameterGrouped[$param->No_Faktur_Uji_Sample])) {
                $parameterGrouped[$param->No_Faktur_Uji_Sample] = [];
            }

            $parameterGrouped[$param->No_Faktur_Uji_Sample][$encodedId] = [
                'id' => $encodedId,
                'id_quality_control' => Hashids::connection('custom')->encode($param->Id_Quality_Control),
                'no_faktur' => $param->No_Faktur_Uji_Sample,
                'hasil_analisa' => number_format((float)$param->Hasil_Analisa, 4, '.', ''),
                'tanggal' => $param->Tanggal_Parameter_Analisa,
                'jam' => $param->Jam_Parameter_Analisa,
            ];
        }

        $dataGrouped = [];

        foreach ($ujiSampel as $item) {
            $item->parameter = array_values($parameterGrouped[$item->No_Faktur] ?? []);

            $noPo = $item->No_Po_Sampel;     
            $subPo = $item->No_Fak_Sub_Po;   
            $idJenisAnalisa = $item->Id_Jenis_Analisa;   

            if ($item->Flag_Multi_QrCode === 'Y') {

                $sisaMultiQrBelumSelesai = DB::table('N_EMI_LAB_Uji_Sampel')
                        ->where('No_Po_Sampel', $noPo)
                        ->where('No_Fak_Sub_Po', $subPo)
                        ->where('Id_Jenis_Analisa', $idJenisAnalisa)
                        ->whereNull('Flag_Selesai')
                        ->count();
                
                if ($sisaMultiQrBelumSelesai === 0){
                    continue;
                }       

                if (!isset($dataGrouped[$noPo])) {
                    $dataGrouped[$noPo] = [
                        'flag_multi' => 'Y',
                        'sampel' => []
                    ];
                }

                $dataGrouped[$noPo]['sampel'][$subPo][] = $item;
            } else {
                $dataGrouped[$noPo][] = $item;
            }
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Data Ditemukan',
            'result' => [
                'data_sampel' => $dataGrouped
            ]
        ], 200);
    }
    public function getDataConfirmedSelesaiByJenisAnalisaV2($id_jenis_analisa)
    {
        try {
            $id_jenis_analisa = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Jenis Analisa tidak valid.'
            ], 400);
        }

        $ujiSampel = DB::table('N_EMI_LAB_Uji_Sampel')
            ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
            ->join('EMI_Master_Mesin', 'N_EMI_LAB_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
            ->select(
                'N_EMI_LAB_Uji_Sampel.No_Faktur',
                'N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode',
                'N_EMI_LAB_Uji_Sampel.Flag_Perhitungan',
                'N_EMI_LAB_Uji_Sampel.No_Po_Sampel',
                'N_EMI_LAB_Uji_Sampel.No_Fak_Sub_Po',
                'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa',
                'N_EMI_LAB_Uji_Sampel.Tanggal as Tanggal_Pengujian',
                'N_EMI_LAB_Uji_Sampel.Jam as Jam_Pengujian',
                'N_EMI_LAB_Uji_Sampel.Hasil as Hasil_Akhir_Analisa',
                'N_EMI_LAB_Uji_Sampel.Tahapan_Ke',
                'N_EMI_LAB_Uji_Sampel.Flag_Resampling',
                'N_EMI_LAB_Uji_Sampel.Flag_Layak',
                'N_EMI_LAB_PO_Sampel.Tanggal as Tanggal_Pengajuan',
                'N_EMI_LAB_PO_Sampel.Jam as Jam_Pengajuan',
                'N_EMI_LAB_PO_Sampel.No_Po',
                'N_EMI_LAB_PO_Sampel.Keterangan as Catatan',
                'N_EMI_LAB_PO_Sampel.No_Split_Po',
                'N_EMI_LAB_PO_Sampel.No_Batch',
                'N_EMI_LAB_PO_Sampel.Kode_Barang',
                'EMI_Master_Mesin.Id_Master_Mesin',
                'EMI_Master_Mesin.Flag_FG',
                'EMI_Master_Mesin.Seri_Mesin',
                'EMI_Master_Mesin.Nama_Mesin', 
                DB::raw("ISNULL((SELECT x.Hasil_Perhitungan FROM N_EMI_LAB_Perhitungan x 
                        WHERE x.id = N_EMI_LAB_Uji_Sampel.Id_Perhitungan 
                        AND x.Kode_Perusahaan = N_EMI_LAB_Uji_Sampel.Kode_Perusahaan), 0) AS Pembulatan")
                )
            ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
            ->where('N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa)
            ->whereNull('N_EMI_LAB_Uji_Sampel.Flag_Selesai')
            ->where('Status_Keputusan_Sampel', 'menunggu')
            ->get();


        if ($ujiSampel->isEmpty()) {
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Data Tidak Ditemukan',
                'result' => [
                    'informasi_detail' => [],
                    'data_sampel' => []
                ]
            ], 200);
        }

        foreach ($ujiSampel as $item) {
            $item->Hasil_Akhir_Analisa = number_format((float)$item->Hasil_Akhir_Analisa, $item->Pembulatan, '.', '');
        }

        $noFakturList = $ujiSampel->pluck('No_Faktur')->unique();

        $parameterRaw = DB::table('N_EMI_LAB_Uji_Sampel_Detail')
            ->select(
                'Id_Uji_Sample_Detail',
                'No_Faktur_Uji_Sample',
                'Id_Quality_Control',
                'Value_Parameter as Hasil_Analisa',
                'Tanggal as Tanggal_Parameter_Analisa',
                'Jam as Jam_Parameter_Analisa'
            )
            ->whereIn('No_Faktur_Uji_Sample', $noFakturList)
            ->get();

        $parameterGrouped = [];
        foreach ($parameterRaw as $param) {
            $encodedId = Hashids::connection('custom')->encode($param->Id_Uji_Sample_Detail);
            if (!isset($parameterGrouped[$param->No_Faktur_Uji_Sample])) {
                $parameterGrouped[$param->No_Faktur_Uji_Sample] = [];
            }

            $parameterGrouped[$param->No_Faktur_Uji_Sample][$encodedId] = [
                'id' => $encodedId,
                'id_quality_control' => Hashids::connection('custom')->encode($param->Id_Quality_Control),
                'no_faktur' => $param->No_Faktur_Uji_Sample,
                'hasil_analisa' => number_format((float)$param->Hasil_Analisa, 4, '.', ''),
                'tanggal' => $param->Tanggal_Parameter_Analisa,
                'jam' => $param->Jam_Parameter_Analisa,
            ];
        }

        $dataGrouped = [];

        foreach ($ujiSampel as $item) {
            $item->parameter = array_values($parameterGrouped[$item->No_Faktur] ?? []);
            $noPo = $item->No_Po_Sampel;     
            $subPo = $item->No_Fak_Sub_Po;   
            $idJenisAnalisa = $item->Id_Jenis_Analisa; 
            $isResampling = $item->Flag_FG === 'Y';
            $isResampling = $item->Flag_FG === 'Y';
            $item->is_resampling = $isResampling; 


            if ($item->Flag_Multi_QrCode === 'Y') {
                $sisaMultiQrBelumSelesai = DB::table('N_EMI_LAB_Uji_Sampel')
                        ->where('No_Po_Sampel', $noPo)
                        ->where('No_Fak_Sub_Po', $subPo)
                        ->where('Id_Jenis_Analisa', $idJenisAnalisa)
                        ->whereNull('Flag_Selesai')
                        ->count();
                
                if ($sisaMultiQrBelumSelesai === 0){
                    continue;
                }       

                if (!isset($dataGrouped[$noPo])) {
                    $dataGrouped[$noPo] = [
                        'flag_multi' => 'Y',
                        'sampel' => []
                    ];
                }

                $dataGrouped[$noPo]['sampel'][$subPo][] = $item;
            } else {
                $dataGrouped[$noPo][] = $item;
            }
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Data Ditemukan',
            'result' => [
                'data_sampel' => $dataGrouped
            ]
        ], 200);
    }

    public function getDataSubSampelCurrentV1($no_Sampel, $id_jenis_analisa)
    {
        try {
            $id_jenis_analisa = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Jenis Analisa tidak valid.'
            ], 400);
        }

        try {
            $getDataSubPo = DB::table('N_LIMS_PO_Sampel_Multi_QrCode as multi')
            ->select('No_Po_Sampel','No_Po_Multi')
            ->where('multi.No_Po_Sampel', $no_Sampel)
            ->whereNotIn('multi.No_Po_Multi', function($query) use ($no_Sampel, $id_jenis_analisa) {
                $query->select('No_Fak_Sub_Po')
                    ->from('N_EMI_LIMS_Uji_Sampel')
                    ->where('No_Po_Sampel', $no_Sampel)
                    ->where('Id_Jenis_Analisa', $id_jenis_analisa);
            })
            ->get();

            return response()->json([
                'success' => true,
                'status' => 200,
                'result' => $getDataSubPo
            ], 200);
        }catch(\Exception $e){
            Log::channel("FormulatorTrialSampelController")->error($e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan"
            ], 500);
        }
    }

    public function getDataHasilAnalisaSelesai()
    {
       try {
            $result = collect(
                DB::table('N_EMI_LIMS_Uji_Sampel')
                    ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
                    ->select(
                        DB::raw('MAX(N_EMI_LIMS_Uji_Sampel.Tanggal) as Tanggal'),
                        DB::raw('MAX(N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa) as Id_Jenis_Analisa'),
                        DB::raw('MAX(N_EMI_LAB_Jenis_Analisa.Jenis_Analisa) as Jenis_Analisa'),
                        DB::raw('MAX(N_EMI_LAB_Jenis_Analisa.Kode_Analisa) as Kode_Analisa'),
                
                    )
                    ->where('N_EMI_LAB_Jenis_Analisa.Kode_Role', 'FLM')
                    ->whereNull('N_EMI_LIMS_Uji_Sampel.Status')
                    ->where('N_EMI_LIMS_Uji_Sampel.Flag_Selesai', 'Y')
                    ->groupBy('N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa')
                    ->orderByDesc('Tanggal')
                    ->get()
            )->map(function ($item) {
                /** @var object $item */
                $item->Id_Jenis_Analisa = Hashids::connection('custom')->encode($item->Id_Jenis_Analisa);
                return $item;
            });

        return ResponseHelper::success($result, "Data Ditemukan", 200);
       }catch(\Exception $e){
            Log::channel("FormulatorTrialSampelController")->error($e->getMessage());
            return ResponseHelper::error("Terjadi Kesalahan", 500);
       }
    }

    public function getDataHasilAnalisaSelesaiByJenisAnalisa(Request $request, $id_jenis_analisa)
    {
        try {
            $id = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Jenis Analisa tidak valid.'
            ], 400);
        }

        // Ambil parameter dari request
        $searchQuery = $request->input('q', ''); 
        $limit = $request->input('limit', 10);
        $filterTanggalMulai = $request->input('tanggal_mulai');
        $filterTanggalSelesai = $request->input('tanggal_selesai');
        $filterMesin = $request->input('mesin');
        $filterQrCode = $request->input('qrcode');
        $filterStatus = $request->input('status');
 

        $baseQuery = DB::table('N_EMI_LIMS_Uji_Sampel')
            ->join('N_LIMS_PO_Sampel', 'N_EMI_LIMS_Uji_Sampel.No_Po_Sampel', '=', 'N_LIMS_PO_Sampel.No_Sampel')
            ->join('EMI_Master_Mesin', 'N_LIMS_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
            ->select(
                'N_EMI_LIMS_Uji_Sampel.No_Faktur',
                'N_EMI_LIMS_Uji_Sampel.Flag_Multi_QrCode',
                'N_EMI_LIMS_Uji_Sampel.Flag_Perhitungan',
                'N_EMI_LIMS_Uji_Sampel.No_Po_Sampel',
                'N_EMI_LIMS_Uji_Sampel.Status',
                'N_EMI_LIMS_Uji_Sampel.No_Fak_Sub_Po',
                'N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa',
                'N_EMI_LIMS_Uji_Sampel.Tanggal as Tanggal_Pengujian',
                'N_EMI_LIMS_Uji_Sampel.Jam as Jam_Pengujian',
                'N_EMI_LIMS_Uji_Sampel.Hasil as Hasil_Akhir_Analisa',
                'N_LIMS_PO_Sampel.Tanggal as Tanggal_Pengajuan',
                'N_LIMS_PO_Sampel.Jam as Jam_Pengajuan',
                'N_LIMS_PO_Sampel.No_Po',
                'N_LIMS_PO_Sampel.Keterangan as Catatan',
                'N_LIMS_PO_Sampel.No_Split_Po',
                'N_LIMS_PO_Sampel.No_Batch',
                'N_LIMS_PO_Sampel.Kode_Barang',
                'N_EMI_LIMS_Uji_Sampel.Status_Keputusan_Sampel',
                'N_LIMS_PO_Sampel.Flag_Selesai as is_selesai',
                'EMI_Master_Mesin.Seri_Mesin',
                'EMI_Master_Mesin.Nama_Mesin',
                DB::raw("ISNULL((SELECT x.Hasil_Perhitungan FROM N_EMI_LAB_Perhitungan x 
                        WHERE x.id = N_EMI_LIMS_Uji_Sampel.Id_Perhitungan 
                        AND x.Kode_Perusahaan = N_EMI_LIMS_Uji_Sampel.Kode_Perusahaan), 0) AS Pembulatan")
            )
            ->whereNull('N_EMI_LIMS_Uji_Sampel.Status')
            ->where('N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa', $id)
            ->where('N_EMI_LIMS_Uji_Sampel.Flag_Selesai', 'Y')
            ->where('N_EMI_LIMS_Uji_Sampel.Flag_Final', 'Y');

        if (!empty($searchQuery)) {
            $baseQuery->where(function ($query) use ($searchQuery) {
                $query->where('N_EMI_LIMS_Uji_Sampel.No_Po_Sampel', 'like', '%' . $searchQuery . '%')
                      ->orWhere('N_LIMS_PO_Sampel.No_Po', 'like', '%' . $searchQuery . '%')
                      ->orWhere('N_LIMS_PO_Sampel.No_Split_Po', 'like', '%' . $searchQuery . '%')
                      ->orWhere('N_LIMS_PO_Sampel.No_Batch', 'like', '%' . $searchQuery . '%')
                      ->orWhere('EMI_Master_Mesin.Nama_Mesin', 'like', '%' . $searchQuery . '%');
            });
        }

        // 2. Filter Tanggal Pengujian
        if ($filterTanggalMulai && $filterTanggalSelesai) {
            $baseQuery->whereBetween('N_EMI_LIMS_Uji_Sampel.Tanggal', [$filterTanggalMulai, $filterTanggalSelesai]);
        }

        // 3. Filter Mesin
        if ($filterMesin) {
            $baseQuery->where('N_LIMS_PO_Sampel.Id_Mesin', $filterMesin);
        }

        // 4. Filter Tipe QRCode
        if ($filterQrCode) {
            if ($filterQrCode === 'multi') {
                $baseQuery->where('N_EMI_LIMS_Uji_Sampel.Flag_Multi_QrCode', 'Y');
            } elseif ($filterQrCode === 'single') {
                $baseQuery->where(function ($query) {
                    $query->where('N_EMI_LIMS_Uji_Sampel.Flag_Multi_QrCode', '!=', 'Y')
                          ->orWhereNull('N_EMI_LIMS_Uji_Sampel.Flag_Multi_QrCode');
                });
            }
        }

        // 5. Filter Status Keputusan
        if ($filterStatus) {
            if ($filterStatus === 'dibatalkan') {
                $baseQuery->where('N_EMI_LIMS_Uji_Sampel.Status', 'Y');
            } else {
                // Untuk 'terima' atau 'tolak', pastikan bukan yang dibatalkan
                $baseQuery->where(function ($query) {
                    $query->where('N_EMI_LIMS_Uji_Sampel.Status', '!=', 'Y')
                          ->orWhereNull('N_EMI_LIMS_Uji_Sampel.Status');
                })->where('N_EMI_LIMS_Uji_Sampel.Status_Keputusan_Sampel', $filterStatus);
            }
        }

        // Urutkan dari data terbaru ke terlama dan lakukan pagination
        $ujiSampel = $baseQuery
            ->orderBy('N_EMI_LIMS_Uji_Sampel.Tanggal', 'desc')
            ->orderBy('N_EMI_LIMS_Uji_Sampel.Jam', 'desc')
            ->paginate($limit);

        if ($ujiSampel->isEmpty()) {
            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Data Tidak Ditemukan',
                'result'  => [
                    'data_sampel' => [],
                    'pagination'  => []
                ]
            ], 200);
        }
        
        // Proses data seperti sebelumnya, namun hanya untuk data di halaman saat ini
        foreach ($ujiSampel as $item) {
            $item->Hasil_Akhir_Analisa = number_format((float)$item->Hasil_Akhir_Analisa, $item->Pembulatan, '.', '');
        }

        // Grouping data
        $dataGrouped = [];
        foreach ($ujiSampel as $item) {
            if (!isset($dataGrouped[$item->No_Po_Sampel])) {
                $finalStatus = '';

                if ($item->Status === 'Y') {
                    $finalStatus = 'Dibatalkan';
                } else {
                    $finalStatus = $item->Status_Keputusan_Sampel;
                }

                 $namaBarang = DB::table('N_EMI_View_Barang')
                                ->where('Kode_Barang', $item->Kode_Barang)
                                ->value('Nama'); 

                 $dataGrouped[$item->No_Po_Sampel] = [
                    'flag_multi'        => $item->Flag_Multi_QrCode === 'Y' ? 'Y' : null,
                    'is_selesai'        => $item->is_selesai,
                    'nama_mesin'        => $item->Nama_Mesin,
                    'nama_barang'       => $namaBarang ?? 'Nama Barang Tidak Ditemukan', 
                    'no_po'             => $item->No_Po,
                    'no_split_po'       => $item->No_Split_Po,
                    'no_batch'          => $item->No_Batch,
                    'tanggal_pengujian' => $item->Tanggal_Pengujian,
                    'jam_pengujian'     => $item->Jam_Pengujian,
                    'tanggal_pengajuan' => $item->Tanggal_Pengajuan,
                    'jam_pengajuan'     => $item->Jam_Pengajuan,
                    'status_keputusan'  => $finalStatus,
                ];
            }
        }
        
        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Data Ditemukan',
            'result'  => [
                'data_sampel' => $dataGrouped,
                'pagination'  => [
                    'page'      => $ujiSampel->currentPage(),
                    'limit'     => $ujiSampel->perPage(),
                    'totalPage' => $ujiSampel->lastPage(),
                    'totalData' => $ujiSampel->total(),
                ],
            ]
        ], 200);
    }

    public function getDataHasilAnalisaSubPoByJenisAnalisa($id_jenis_analisa, $no_po_sampel)
    {
        try {
            $id_jenis_analisa = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Jenis Analisa tidak valid.'
            ], 400);
        }
        
        try {
            $ujiSampel = DB::table('N_EMI_LIMS_Uji_Sampel')
                ->select('No_Po_Sampel', 'No_Fak_Sub_Po')
                ->whereNull('Status')
                ->where('No_Po_Sampel', $no_po_sampel)
                ->where('Id_Jenis_Analisa', $id_jenis_analisa)
                ->where('Flag_Multi_QrCode', 'Y')
                ->where('Flag_Selesai', 'Y')
                ->where('Status_Keputusan_Sampel', 'terima')
                ->get()
                ->unique(function($item) {
                    return $item->No_Po_Sampel . '-' . $item->No_Fak_Sub_Po; // Gabungkan keduanya untuk memastikan tidak ada duplikat
                })
                ->values();

            if ($ujiSampel->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'status' => 200,
                    'message' => 'Data Tidak Ditemukan',
                
                ], 200);
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Data Ditemukan',
                'result' => $ujiSampel
            ], 200);
        }catch(\Exception $e){
            Log::channel("FormulatorTrialSampelController")->error($e->getMessage());
            return ResponseHelper::error("Terjadi Kesalahan", 500);
        }
    }

    public function getDataHasilAnalisaPerhitunganByMulti($id_jenis_analisa, $no_po_sampel, $flag_multi, $no_sub)
    {
        try {
            $id_jenis_analisa = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Jenis Analisa tidak valid.'
            ], 400);
        }

        $ujiSampel = DB::table('N_EMI_LAB_Uji_Sampel')
            ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
            ->join('EMI_Master_Mesin', 'N_EMI_LAB_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
            ->leftJoin('N_EMI_LAB_Perhitungan', function ($join) {
                $join->on('N_EMI_LAB_Perhitungan.id', '=', 'N_EMI_LAB_Uji_Sampel.Id_Perhitungan')
                    ->on('N_EMI_LAB_Perhitungan.Kode_Perusahaan', '=', 'N_EMI_LAB_Uji_Sampel.Kode_Perusahaan');
            })
            ->select(
                'N_EMI_LAB_PO_Sampel.Kode_Barang',
                'N_EMI_LAB_Uji_Sampel.No_Faktur',
                'N_EMI_LAB_Uji_Sampel.No_Po_Sampel',
                'N_EMI_LAB_Uji_Sampel.No_Fak_Sub_Po',
               
                'N_EMI_LAB_Uji_Sampel.Tanggal as Tanggal_Pengujian',
                'N_EMI_LAB_Uji_Sampel.Hasil as Hasil_Akhir_Analisa',
                'N_EMI_LAB_PO_Sampel.No_Po',
                'N_EMI_LAB_PO_Sampel.No_Split_Po',
                'N_EMI_LAB_Uji_Sampel.Id_Perhitungan',
                'EMI_Master_Mesin.Id_Master_Mesin',
                DB::raw("ISNULL(N_EMI_LAB_Perhitungan.Hasil_Perhitungan, 0) AS Pembulatan")
            )
            ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
            ->where('N_EMI_LAB_Uji_Sampel.No_Po_Sampel', $no_po_sampel)
            ->where('N_EMI_LAB_Uji_Sampel.No_Fak_Sub_po', $no_sub)
            ->where('N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa)
            ->where('N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode', $flag_multi)
            ->where('N_EMI_LAB_Uji_Sampel.Flag_Selesai', 'Y')
            ->get();


        if ($ujiSampel->isEmpty()) {
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Data Tidak Ditemukan',
            ], 200);
        }

        foreach ($ujiSampel as $item) {
            $item->Hasil_Akhir_Analisa = number_format((float)$item->Hasil_Akhir_Analisa, $item->Pembulatan, '.', '');
        }

        $parameterRaw = DB::table('N_EMI_LAB_Uji_Sampel_Detail')
            ->select(
                'Id_Uji_Sample_Detail',
                'No_Faktur_Uji_Sample',
                'Id_Quality_Control',
                'Value_Parameter as Hasil_Analisa',
                'Tanggal as Tanggal_Parameter_Analisa',
                'Jam as Jam_Parameter_Analisa'
            )
            ->whereIn('No_Faktur_Uji_Sample', $ujiSampel->pluck('No_Faktur'))
            ->get()
            ->groupBy('No_Faktur_Uji_Sample');

        $result = [];

        foreach ($ujiSampel as $sampel) {
            $item = (array) $sampel;
            $parameters = $parameterRaw->get($sampel->No_Faktur)?->values() ?? [];
        
            // transformasi setiap parameter
            $transformedParameters = $parameters->map(function ($param) use ($id_jenis_analisa) {
                return [
                    'Id_Jenis_Analisa' => Hashids::connection('custom')->encode($id_jenis_analisa),
                    'Id_Uji_Sample_Detail' => Hashids::connection('custom')->encode($param->Id_Uji_Sample_Detail),
                    'No_Faktur_Uji_Sample' => $param->No_Faktur_Uji_Sample,
                    'Id_Quality_Control' => Hashids::connection('custom')->encode($param->Id_Quality_Control),
                    'Hasil_Analisa' => round(floatval($param->Hasil_Analisa), 4),
                    'Tanggal_Parameter_Analisa' => $param->Tanggal_Parameter_Analisa,
                    'Jam_Parameter_Analisa' => $param->Jam_Parameter_Analisa,
                ];
            });
        
            $item['parameter'] = $transformedParameters;
            $result[] = $item;
        }

        $informasi = DB::table('N_EMI_LAB_Uji_Sampel')
            ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
            ->join('EMI_Master_Mesin', 'N_EMI_LAB_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
            ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
            ->select(
                'N_EMI_LAB_Uji_Sampel.No_Faktur',
                'N_EMI_LAB_Uji_Sampel.No_Po_Sampel',
                'N_EMI_LAB_Uji_Sampel.No_Fak_Sub_Po',
                'N_EMI_LAB_Uji_Sampel.Tanggal as Tanggal_Pengujian',
                'N_EMI_LAB_Uji_Sampel.Jam as Jam_Pengujian',
                'N_EMI_LAB_PO_Sampel.Tanggal as Tanggal_Pengajuan',
                'N_EMI_LAB_PO_Sampel.Jam as Jam_Pengajuan',
                'N_EMI_LAB_PO_Sampel.No_Po',
                'N_EMI_LAB_PO_Sampel.Keterangan as Catatan',
                'N_EMI_LAB_PO_Sampel.No_Split_Po',
                'N_EMI_LAB_PO_Sampel.No_Batch',
                'N_EMI_LAB_PO_Sampel.Kode_Barang',
                'EMI_Master_Mesin.Seri_Mesin',
                'EMI_Master_Mesin.Nama_Mesin', 
                'N_EMI_LAB_Jenis_Analisa.Kode_Analisa',
                'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa'
            )
            ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
            ->where('N_EMI_LAB_Uji_Sampel.No_Po_Sampel', $result[0]['No_Po_Sampel'])
            ->where('N_EMI_LAB_Uji_Sampel.No_Fak_Sub_po', $result[0]['No_Fak_Sub_Po'])
            ->where('N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa)
            ->where('N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode', $flag_multi)
            ->where('N_EMI_LAB_Uji_Sampel.Flag_Selesai', 'Y')
            ->first();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Data Ditemukan',
            'result' => [
                'informasi' => $informasi,
                'sampel' => $result
            ]
        ], 200);
    }

    public function getDataHasilAnalisaPerhitunganByMultiV2($id_jenis_analisa, $no_po_sampel, $flag_multi, $no_sub)
    {
        try {
            $id_jenis_analisa_decoded = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'status' => 400, 'message' => 'Format ID Jenis Analisa tidak valid.'], 400);
        }

        try {
            $ujiSampel = DB::table('N_EMI_LIMS_Uji_Sampel') 
                ->join('N_LIMS_PO_Sampel', 'N_EMI_LIMS_Uji_Sampel.No_Po_Sampel', '=', 'N_LIMS_PO_Sampel.No_Sampel')
                ->join('EMI_Master_Mesin', 'N_LIMS_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
                ->leftJoin('N_EMI_LAB_Perhitungan', function ($join) {
                    $join->on('N_EMI_LAB_Perhitungan.id', '=', 'N_EMI_LIMS_Uji_Sampel.Id_Perhitungan')
                        ->on('N_EMI_LAB_Perhitungan.Kode_Perusahaan', '=', 'N_EMI_LIMS_Uji_Sampel.Kode_Perusahaan');
                })
                ->select(
                    'N_LIMS_PO_Sampel.Kode_Barang',
                    'N_EMI_LIMS_Uji_Sampel.No_Faktur',
                    'N_EMI_LIMS_Uji_Sampel.No_Po_Sampel',
                    'N_EMI_LIMS_Uji_Sampel.No_Fak_Sub_Po',
                    'N_EMI_LIMS_Uji_Sampel.Flag_Layak',
                    'N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa',
                    'N_EMI_LIMS_Uji_Sampel.Tanggal as Tanggal_Pengujian',
                    'N_EMI_LIMS_Uji_Sampel.Hasil as Hasil_Akhir_Analisa',
                    'N_EMI_LIMS_Uji_Sampel.Flag_Perhitungan',
                    'N_EMI_LIMS_Uji_Sampel.Range_Awal',
                    'N_EMI_LIMS_Uji_Sampel.Range_Akhir',
                    'N_LIMS_PO_Sampel.No_Po',
                    'N_LIMS_PO_Sampel.No_Split_Po',
                    'N_EMI_LIMS_Uji_Sampel.Flag_Foto', // Penambahan Field Flag_Foto
                    DB::raw("ISNULL(N_EMI_LAB_Perhitungan.Hasil_Perhitungan, 0) AS Pembulatan"),
                    DB::raw("CASE WHEN N_EMI_LIMS_Uji_Sampel.Range_Awal IS NOT NULL THEN CAST(1 AS BIT) ELSE CAST(0 AS BIT) END AS is_sop")
                )
                ->whereNull('N_EMI_LIMS_Uji_Sampel.Status')
                ->where('N_EMI_LIMS_Uji_Sampel.No_Po_Sampel', $no_po_sampel)
                ->where('N_EMI_LIMS_Uji_Sampel.No_Fak_Sub_po', $no_sub)
                ->where('N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa_decoded)
                ->where('N_EMI_LIMS_Uji_Sampel.Flag_Multi_QrCode', $flag_multi)
                ->where('N_EMI_LIMS_Uji_Sampel.Flag_Selesai', 'Y')
                ->get();

            if ($ujiSampel->isEmpty()) {
                return response()->json(['success' => true, 'status' => 200, 'message' => 'Data Tidak Ditemukan'], 200);
            }

            // Deklarasi Sesi Foto dan Faktur List
            $hasSesiFoto = $ujiSampel->contains('Flag_Foto', 'Y') ? 'Y' : 'T';
            $fakturList = $ujiSampel->pluck('No_Faktur')->unique()->toArray();

            // Pengambilan data Berkas / Foto
            // PERUBAHAN: Menambahkan 'Keterangan' pada select
            $berkasRaw = DB::table('N_EMI_LIMS_Berkas_Uji_Lab')
                ->select('Id_Berkas_Uji_Lab', 'No_Faktur', 'Berkas_Key', 'Keterangan')
                ->whereIn('No_Faktur', $fakturList)
                ->get()
                ->groupBy('No_Faktur');

            $rawRules = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->select('Nilai_Kriteria', 'Keterangan_Kriteria')
                ->where('Id_Jenis_Analisa', $id_jenis_analisa_decoded)
                ->where('Flag_Aktif', 'Y')
                ->where('Kode_Role', 'FLM')
                ->get();

            $rulesMap = [];
            foreach ($rawRules as $rule) {
                $key = (string)((float)$rule->Nilai_Kriteria);
                $rulesMap[$key] = $rule->Keterangan_Kriteria;
            }

            $parameterRaw = DB::table('N_EMI_LIMS_Uji_Sampel_Detail')
                ->select(
                    'Id_Uji_Sample_Detail',
                    'No_Faktur_Uji_Sample',
                    'Id_Quality_Control',
                    'Value_Parameter as Hasil_Analisa',
                    'Tanggal as Tanggal_Parameter_Analisa',
                    'Jam as Jam_Parameter_Analisa'
                )
                ->whereIn('No_Faktur_Uji_Sample', $fakturList) // Optimalisasi ke $fakturList
                ->get()
                ->groupBy('No_Faktur_Uji_Sample');

            $result = [];

            foreach ($ujiSampel as $item) {
                $item->is_sop = (bool) $item->is_sop;
                $item->Range_Awal = (float) $item->Range_Awal;
                $item->Range_Akhir = (float) $item->Range_Akhir;

                $valHeader = (float)$item->Hasil_Akhir_Analisa;
                $keyHeader = (string)$valHeader;

                if (is_null($item->Flag_Perhitungan) && isset($rulesMap[$keyHeader])) {
                    $item->Hasil_Akhir_Analisa = $rulesMap[$keyHeader];
                } else {
                    $item->Hasil_Akhir_Analisa = number_format($valHeader, $item->Pembulatan, '.', '');
                }

                $params = $parameterRaw->get($item->No_Faktur);
                $transformedParameters = [];

                if ($params) {
                    foreach ($params as $param) {
                        $valParam = (float)$param->Hasil_Analisa;
                        $keyParam = (string)$valParam;
                        
                        if (is_null($item->Flag_Perhitungan) && isset($rulesMap[$keyParam])) {
                            $hasilTampil = $rulesMap[$keyParam];
                        } else {
                            $hasilTampil = round($valParam, 4);
                        }

                        $transformedParameters[] = [
                            'Id_Jenis_Analisa' => Hashids::connection('custom')->encode($id_jenis_analisa_decoded),
                            'Id_Uji_Sample_Detail' => Hashids::connection('custom')->encode($param->Id_Uji_Sample_Detail),
                            'No_Faktur_Uji_Sample' => $param->No_Faktur_Uji_Sample,
                            'Id_Quality_Control' => Hashids::connection('custom')->encode($param->Id_Quality_Control),
                            'Hasil_Analisa' => $hasilTampil,
                            'Tanggal_Parameter_Analisa' => $param->Tanggal_Parameter_Analisa,
                            'Jam_Parameter_Analisa' => $param->Jam_Parameter_Analisa,
                        ];
                    }
                }

                // Proses mapping berkas foto untuk item ini
                $berkas = $berkasRaw->get($item->No_Faktur);
                $fotoList = [];

                if ($berkas) {
                    foreach ($berkas as $file) {
                        // PERUBAHAN: Menambahkan 'Keterangan' ke array respons
                        $fotoList[] = [
                            'Berkas_Key' => $file->Berkas_Key,
                            'Keterangan' => $file->Keterangan,
                        ];
                    }
                }

                $item->foto_analisa = $fotoList;
                unset($item->Flag_Foto); // Hapus Flag_Foto dari response JSON final

                $item->parameter = $transformedParameters;
                $result[] = $item;
            }

            $informasi = DB::table('N_EMI_LIMS_Uji_Sampel')
                ->join('N_LIMS_PO_Sampel', 'N_EMI_LIMS_Uji_Sampel.No_Po_Sampel', '=', 'N_LIMS_PO_Sampel.No_Sampel')
                ->join('EMI_Master_Mesin', 'N_LIMS_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
                ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
                ->select(
                    'N_EMI_LIMS_Uji_Sampel.No_Faktur',
                    'N_EMI_LIMS_Uji_Sampel.No_Po_Sampel',
                    'N_EMI_LIMS_Uji_Sampel.No_Fak_Sub_Po',
                    'N_EMI_LIMS_Uji_Sampel.Tanggal as Tanggal_Pengujian',
                    'N_EMI_LIMS_Uji_Sampel.Jam as Jam_Pengujian',
                    'N_LIMS_PO_Sampel.Tanggal as Tanggal_Pengajuan',
                    'N_LIMS_PO_Sampel.Jam as Jam_Pengajuan',
                    'N_LIMS_PO_Sampel.No_Po',
                    'N_LIMS_PO_Sampel.Keterangan as Catatan',
                    'N_LIMS_PO_Sampel.No_Split_Po',
                    'N_LIMS_PO_Sampel.No_Batch',
                    'N_LIMS_PO_Sampel.Kode_Barang',
                    'EMI_Master_Mesin.Seri_Mesin',
                    'EMI_Master_Mesin.Nama_Mesin', 
                    'N_EMI_LAB_Jenis_Analisa.Kode_Analisa',
                    'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa'
                )
                ->whereNull('N_EMI_LIMS_Uji_Sampel.Status')
                ->where('N_EMI_LAB_Jenis_Analisa.Kode_Role', 'FLM')
                ->where('N_EMI_LIMS_Uji_Sampel.No_Po_Sampel', $no_po_sampel)
                ->where('N_EMI_LIMS_Uji_Sampel.No_Fak_Sub_po', $no_sub)
                ->where('N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa_decoded)
                ->where('N_EMI_LIMS_Uji_Sampel.Flag_Multi_QrCode', $flag_multi)
                ->where('N_EMI_LIMS_Uji_Sampel.Flag_Selesai', 'Y')
                ->first();

            // Tambahkan sesi foto ke object informasi
            if ($informasi) {
                $informasi->sesi_foto = $hasSesiFoto;
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Data Ditemukan',
                'result' => [
                    'informasi' => $informasi,
                    'sampel' => $result
                ]
            ], 200);

        } catch (\Exception $e) {
            // Bisa tambahkan Log::error($e->getMessage()); jika dibutuhkan
            return response()->json([
                'success' => false, 
                'status' => 500, 
                'message' => 'Terjadi kesalahan pada server. Silahkan hubungi administrator.'
            ], 500);
        }
    }

    public function getVerifikasiHasilAnalisaPerhitunganByMultiV2($id_jenis_analisa, $no_po_sampel, $no_sub)
    {
        try {
            $id_jenis_analisa_decoded = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
        } catch (\Exception $e) {
            return \App\Helpers\ResponseHelper::error('Format ID Jenis Analisa tidak valid.', 400);
        }

        try {
            $ujiSampel = DB::table('N_EMI_LIMS_Uji_Sampel')
            ->join('N_LIMS_PO_Sampel', 'N_EMI_LIMS_Uji_Sampel.No_Po_Sampel', '=', 'N_LIMS_PO_Sampel.No_Sampel')
            ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
            ->join('EMI_Master_Mesin', 'N_LIMS_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
            ->leftJoin('N_EMI_LAB_Perhitungan', function ($join) {
                $join->on('N_EMI_LAB_Perhitungan.id', '=', 'N_EMI_LIMS_Uji_Sampel.Id_Perhitungan')
                    ->on('N_EMI_LAB_Perhitungan.Kode_Perusahaan', '=', 'N_EMI_LIMS_Uji_Sampel.Kode_Perusahaan');
            })
            ->select(
                'N_LIMS_PO_Sampel.Kode_Barang',
                'N_LIMS_PO_Sampel.tanggal as Tanggal_Registrasi',
                'N_LIMS_PO_Sampel.jam as Jam_Registrasi',
                'N_EMI_LAB_Jenis_Analisa.Kode_Analisa',
                'N_EMI_LIMS_Uji_Sampel.No_Faktur',
                'N_EMI_LIMS_Uji_Sampel.Flag_String',
                'N_EMI_LIMS_Uji_Sampel.Nilai_Hasil_String',
                'N_EMI_LIMS_Uji_Sampel.No_Po_Sampel',
                'N_EMI_LIMS_Uji_Sampel.No_Fak_Sub_Po',
                'N_LIMS_PO_Sampel.No_Batch',
                'N_EMI_LIMS_Uji_Sampel.Tahapan_Ke',
                'N_EMI_LIMS_Uji_Sampel.Flag_Multi_QrCode',
                'N_EMI_LIMS_Uji_Sampel.Flag_Resampling',
                'N_EMI_LIMS_Uji_Sampel.Status_Keputusan_Sampel',
                'N_EMI_LIMS_Uji_Sampel.Flag_Layak',
                'N_EMI_LIMS_Uji_Sampel.Flag_Final',
                'N_EMI_LIMS_Uji_Sampel.Id_Mesin',
                'N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa',
                'N_EMI_LIMS_Uji_Sampel.Tanggal as Tanggal_Pengujian',
                'N_EMI_LIMS_Uji_Sampel.Hasil as Hasil_Akhir_Analisa',
                'N_EMI_LIMS_Uji_Sampel.Flag_Perhitungan',
                'N_EMI_LIMS_Uji_Sampel.Range_Awal',
                'N_EMI_LIMS_Uji_Sampel.Range_Akhir',
                'N_LIMS_PO_Sampel.No_Po',
                'N_LIMS_PO_Sampel.No_Split_Po',
                'EMI_Master_Mesin.Flag_FG',
                'N_EMI_LIMS_Uji_Sampel.Flag_Foto',
                DB::raw("ISNULL(N_EMI_LAB_Perhitungan.Hasil_Perhitungan, 0) AS Pembulatan")
            )
            ->whereNull('N_EMI_LIMS_Uji_Sampel.Status')
            ->where('N_EMI_LAB_Jenis_Analisa.Kode_Role', 'FLM')
            ->where('N_EMI_LIMS_Uji_Sampel.No_Po_Sampel', $no_po_sampel)
            ->where('N_EMI_LIMS_Uji_Sampel.No_Fak_Sub_po', $no_sub)
            ->where('N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa_decoded)
            ->where('N_EMI_LIMS_Uji_Sampel.Status_Keputusan_Sampel', 'menunggu')
            ->get();

            if ($ujiSampel->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            $hasSesiFoto = $ujiSampel->contains('Flag_Foto', 'Y') ? 'Y' : 'T';
            $fakturList = $ujiSampel->pluck('No_Faktur')->unique()->toArray();

            $berkasRaw = DB::table('N_EMI_LIMS_Berkas_Uji_Lab')
                ->select('Id_Berkas_Uji_Lab', 'No_Faktur', 'Berkas_Key', 'Keterangan')
                ->whereIn('No_Faktur', $fakturList)
                ->get()
                ->groupBy('No_Faktur');

            $rawRules = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->select('Nilai_Kriteria', 'Keterangan_Kriteria')
                ->where('Id_Jenis_Analisa', $id_jenis_analisa_decoded)
                ->where('Flag_Aktif', 'Y')
                ->get();

            $rulesMap = [];
            foreach ($rawRules as $rule) {
                $key = (string)((float)$rule->Nilai_Kriteria);
                $rulesMap[$key] = $rule->Keterangan_Kriteria;
            }

            $parameterRaw = DB::table('N_EMI_LIMS_Uji_Sampel_Detail')
                ->select(
                    'Id_Uji_Sample_Detail',
                    'No_Faktur_Uji_Sample',
                    'Id_Quality_Control',
                    'Value_Parameter as Hasil_Analisa',
                    'Tanggal as Tanggal_Parameter_Analisa',
                    'Jam as Jam_Parameter_Analisa'
                )
                ->whereIn('No_Faktur_Uji_Sample', $fakturList)
                ->get()
                ->groupBy('No_Faktur_Uji_Sample');

            $result = [];

            foreach ($ujiSampel as $item) {
                $item->Range_Awal = (float) $item->Range_Awal;
                $item->Range_Akhir = (float) $item->Range_Akhir;

                $valHeader = (float)$item->Hasil_Akhir_Analisa;
                $keyHeader = (string)$valHeader;

                if (is_null($item->Flag_Perhitungan) && isset($rulesMap[$keyHeader])) {
                    $item->Hasil_Akhir_Analisa = $rulesMap[$keyHeader];
                } else {
                    $item->Hasil_Akhir_Analisa = number_format($valHeader, $item->Pembulatan, '.', '');
                }

                $params = $parameterRaw->get($item->No_Faktur);
                $transformedParameters = [];

                if ($params) {
                    foreach ($params as $param) {
                        $valParam = (float)$param->Hasil_Analisa;
                        $keyParam = (string)$valParam;

                        if (is_null($item->Flag_Perhitungan) && isset($rulesMap[$keyParam])) {
                            $hasilTampil = $rulesMap[$keyParam];
                        } else {
                            $hasilTampil = round($valParam, 4);
                        }

                        $transformedParameters[] = [
                            'Id_Jenis_Analisa' => Hashids::connection('custom')->encode($id_jenis_analisa_decoded),
                            'Id_Uji_Sample_Detail' => Hashids::connection('custom')->encode($param->Id_Uji_Sample_Detail),
                            'No_Faktur_Uji_Sample' => $param->No_Faktur_Uji_Sample,
                            'Id_Quality_Control' => Hashids::connection('custom')->encode($param->Id_Quality_Control),
                            'Hasil_Analisa' => $hasilTampil,
                            'Tanggal_Parameter_Analisa' => $param->Tanggal_Parameter_Analisa,
                            'Jam_Parameter_Analisa' => $param->Jam_Parameter_Analisa,
                        ];
                    }
                }

                $berkas = $berkasRaw->get($item->No_Faktur);
                $fotoList = [];

                if ($berkas) {
                    foreach ($berkas as $file) {
                        $fotoList[] = [
                            'Berkas_Key' => $file->Berkas_Key,
                            'Keterangan' => $file->Keterangan ?? 'Tidak Ada Keterangan'
                        ];
                    }
                }

                $item->foto_analisa = $fotoList;
                unset($item->Flag_Foto);
                $item->parameter = $transformedParameters;
                $result[] = $item;
            }

            return ResponseHelper::success(
                [
                    'informasi' => [
                        'sesi_foto' => $hasSesiFoto
                    ],
                    'sampel' => $result
                ],
                'Data Ditemukan',
                200
            );
        }catch(\Exception $e){
            Log::channel("FormulatorTrialSampelController")->error($e->getMessage());
            return ResponseHelper::error('Terjadi kesalahan pada server. Silahkan hubungi administrator.', 500);
        }
    }

    public function generateFotoToken(Request $request)
    {
        if (!auth()->check()) abort(401);

        $roles = Session::get("User_Roles", []);

        $isFLM = collect($roles)->contains(fn($r) => ($r->Kode_Role ?? null) === 'FLM');

        if (!$isFLM) abort(403);

        $keys = $request->keys;

        $result = [];

        foreach ($keys as $key) {
            $token = Str::random(40);

            Cache::put(
                "foto_token:$token",
                [
                    "key" => $key,
                    "user" => Auth::user()->UserId
                ],
                now()->addSeconds(30)
            );

            $result[$key] = $token;
        }

        return response()->json($result);
    }

    public function streamFoto(Request $request, $key)
    {
        if (!auth()->check()) abort(401);

        $token = $request->query("token");
        if (!$token) abort(401);

        $payload = Cache::get("foto_token:$token");

        if (!$payload) abort(401, "Token expired");

        Cache::forget("foto_token:$token");

        if ($payload["key"] !== $key) abort(403);

        if ($payload["user"] !== auth()->id()) abort(403);

        $roles = Session::get("User_Roles", []);

        $isFLM = collect($roles)->contains(function ($r) {
            return ($r->Kode_Role ?? null) === 'FLM';
        });

        if (!$isFLM) abort(403);

        $berkas = DB::table('N_EMI_LIMS_Berkas_Uji_Lab')
            ->where('Berkas_Key', $key)
            ->first();

        if (!$berkas) abort(404);

        return Storage::disk('gcs')->response($berkas->File_Path);
    }

    public function getVerifikasiHasilAnalisaPerhitunganBySingleQrV2($id_jenis_analisa, $no_po_sampel)
    {
        try {
            $decodedId = Hashids::connection('custom')->decode($id_jenis_analisa);
            
            if (empty($decodedId)) {
                throw new \Exception('Format ID Jenis Analisa tidak valid.');
            }
            
            $id_jenis_analisa_int = $decodedId[0];

            $ujiSampel = DB::table('N_EMI_LIMS_Uji_Sampel') 
                ->join('N_LIMS_PO_Sampel', 'N_EMI_LIMS_Uji_Sampel.No_Po_Sampel', '=', 'N_LIMS_PO_Sampel.No_Sampel')
                ->join('EMI_Master_Mesin', 'N_LIMS_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
                ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
                ->leftJoin('N_EMI_LAB_Perhitungan', function ($join) {
                    $join->on('N_EMI_LAB_Perhitungan.id', '=', 'N_EMI_LIMS_Uji_Sampel.Id_Perhitungan')
                        ->on('N_EMI_LAB_Perhitungan.Kode_Perusahaan', '=', 'N_EMI_LIMS_Uji_Sampel.Kode_Perusahaan');
                })
                ->leftJoin('N_EMI_LAB_Standar_Rentang', function ($join) {
                    $join->on('N_EMI_LAB_Standar_Rentang.Id_Jenis_Analisa', '=', 'N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa')
                        ->on('N_EMI_LAB_Standar_Rentang.Id_Master_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
                        ->on('N_EMI_LAB_Standar_Rentang.Kode_Barang', '=', 'N_LIMS_PO_Sampel.Kode_Barang');
                })
                ->select(
                    'N_EMI_LIMS_Uji_Sampel.Kode_Perusahaan',
                    'N_LIMS_PO_Sampel.Kode_Barang',
                    'N_LIMS_PO_Sampel.Tanggal as Tanggal_Registrasi',
                    'N_LIMS_PO_Sampel.Jam as Jam_Registrasi',
                    'N_EMI_LIMS_Uji_Sampel.No_Faktur',
                    'N_EMI_LIMS_Uji_Sampel.No_Po_Sampel',
                    'N_EMI_LIMS_Uji_Sampel.No_Fak_Sub_Po', 
                    'N_LIMS_PO_Sampel.No_Batch', 
                    'N_EMI_LIMS_Uji_Sampel.Tahapan_Ke', 
                    'N_EMI_LIMS_Uji_Sampel.Flag_Multi_QrCode', 
                    'N_EMI_LIMS_Uji_Sampel.Flag_Resampling', 
                    'N_EMI_LIMS_Uji_Sampel.Status_Keputusan_Sampel', 
                    'N_EMI_LIMS_Uji_Sampel.Flag_Layak', 
                    'N_EMI_LIMS_Uji_Sampel.Flag_Final', 
                    'N_EMI_LIMS_Uji_Sampel.Id_Mesin', 
                    'N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa',
                    'N_EMI_LIMS_Uji_Sampel.Tanggal as Tanggal_Pengujian',
                    'N_EMI_LIMS_Uji_Sampel.Hasil as Hasil_Akhir_Analisa',
                    'N_LIMS_PO_Sampel.No_Po',
                    'N_LIMS_PO_Sampel.No_Split_Po',
                    'EMI_Master_Mesin.Flag_FG',
                    'N_EMI_LAB_Jenis_Analisa.Flag_Perhitungan',
                    'N_EMI_LIMS_Uji_Sampel.Flag_Foto', 
                    DB::raw("ISNULL(N_EMI_LAB_Perhitungan.Hasil_Perhitungan, 0) AS Pembulatan"),
                    DB::raw("CASE WHEN N_EMI_LAB_Standar_Rentang.Id_Standar_Rentang IS NOT NULL THEN N_EMI_LAB_Standar_Rentang.Range_Awal ELSE NULL END AS Range_Awal"),
                    DB::raw("CASE WHEN N_EMI_LAB_Standar_Rentang.Id_Standar_Rentang IS NOT NULL THEN N_EMI_LAB_Standar_Rentang.Range_Akhir ELSE NULL END AS Range_Akhir")
                )
                ->whereNull('N_EMI_LIMS_Uji_Sampel.Status')
                ->where('N_EMI_LAB_Jenis_Analisa.Kode_Role', 'FLM')
                ->where('N_EMI_LIMS_Uji_Sampel.No_Po_Sampel', $no_po_sampel)
                ->where('N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa_int)
                ->where('N_EMI_LIMS_Uji_Sampel.Status_Keputusan_Sampel', 'menunggu')
                ->get();

            if ($ujiSampel->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            $hasSesiFoto = $ujiSampel->contains('Flag_Foto', 'Y') ? 'Y' : 'T';
            $fakturList = $ujiSampel->pluck('No_Faktur')->unique()->toArray();

            $berkasRaw = DB::table('N_EMI_LIMS_Berkas_Uji_Lab')
                ->select('Id_Berkas_Uji_Lab', 'No_Faktur', 'Berkas_Key', 'Keterangan')
                ->whereIn('No_Faktur', $fakturList)
                ->get()
                ->groupBy('No_Faktur');

            $kodePerusahaan = $ujiSampel->first()->Kode_Perusahaan;

            $referensiNonHitung = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->select('Nilai_Kriteria', 'Keterangan_Kriteria')
                ->where('Id_Jenis_Analisa', $id_jenis_analisa_int)
                ->where('Kode_Role', 'FLM')
                ->where('Kode_Perusahaan', $kodePerusahaan)
                ->where('Flag_Aktif', 'Y')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [(string)floatval($item->Nilai_Kriteria) => $item->Keterangan_Kriteria];
                });

            $parameterRaw = DB::table('N_EMI_LIMS_Uji_Sampel_Detail')
                ->select(
                    'Id_Uji_Sample_Detail',
                    'No_Faktur_Uji_Sample',
                    'Id_Quality_Control',
                    'Value_Parameter as Hasil_Analisa',
                    'Tanggal as Tanggal_Parameter_Analisa',
                    'Jam as Jam_Parameter_Analisa'
                )
                ->whereIn('No_Faktur_Uji_Sample', $ujiSampel->pluck('No_Faktur'))
                ->get()
                ->groupBy('No_Faktur_Uji_Sample');

            $result = [];

            foreach ($ujiSampel as $sampel) {
                $item = (array) $sampel;
                $item['Range_Awal'] = (float) $sampel->Range_Awal;
                $item['Range_Akhir'] = (float) $sampel->Range_Akhir;
                $item['Hasil_Akhir_Analisa'] = number_format((float)$sampel->Hasil_Akhir_Analisa, $sampel->Pembulatan, '.', '');

                $parameters = $parameterRaw->get($sampel->No_Faktur)?->values() ?? collect([]);
            
                $transformedParameters = $parameters->map(function ($param) use ($sampel, $referensiNonHitung) {
                    $hasilFloat = floatval($param->Hasil_Analisa);
                    $hasilString = (string)$hasilFloat;
                    $hasilTampil = null;

                    if ($sampel->Flag_Perhitungan == 'Y') {
                        $hasilTampil = round($hasilFloat, 4);
                    } else {
                        if (isset($referensiNonHitung[$hasilString])) {
                            $hasilTampil = $referensiNonHitung[$hasilString];
                        } else {
                            $hasilTampil = round($hasilFloat, 4);
                        }
                    }

                    return [
                        'Id_Jenis_Analisa' => Hashids::connection('custom')->encode($sampel->Id_Jenis_Analisa),
                        'Id_Uji_Sample_Detail' => Hashids::connection('custom')->encode($param->Id_Uji_Sample_Detail),
                        'No_Faktur_Uji_Sample' => $param->No_Faktur_Uji_Sample,
                        'Id_Quality_Control' => Hashids::connection('custom')->encode($param->Id_Quality_Control),
                        'Hasil_Analisa' => $hasilTampil,
                        'Tanggal_Parameter_Analisa' => $param->Tanggal_Parameter_Analisa,
                        'Jam_Parameter_Analisa' => $param->Jam_Parameter_Analisa,
                    ];
                });

                $berkas = $berkasRaw->get($sampel->No_Faktur);
                $fotoList = [];

                if ($berkas) {
                    foreach ($berkas as $file) {
                        $fotoList[] = [
                            'Berkas_Key' => $file->Berkas_Key,
                            'Keterangan' => $file->Keterangan ?? 'Tidak Ada Keterangan',
                        ];
                    }
                }

                $item['foto_analisa'] = $fotoList;
                unset($item['Flag_Foto']);
                $item['parameter'] = $transformedParameters;
                $result[] = $item;
            }

            return ResponseHelper::success(
            [
                'informasi' => [
                    'sesi_foto' => $hasSesiFoto
                ],
                'sampel' => $result
            ],
            'Data Ditemukan',
            200
        );

        } catch (\Exception $e) {
            Log::channel('FormulatorTrialSampelController')->error('Error pada function ' . __FUNCTION__, [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'params' => [
                    'id_jenis_analisa' => $id_jenis_analisa,
                    'no_po_sampel' => $no_po_sampel
                ],
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseHelper::error('Terjadi kesalahan pada server. Silahkan hubungi administrator.', 500);
        }
    }

    public function getVerifikasiHasilAnalisaFinalKeputusanV1($id_jenis_analisa, $no_po_sampel, $no_sub)
    {
        // Try Catch Pertama: Decode ID Jenis Analisa
        try {
            $id_jenis_analisa_decoded = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
        } catch (\Exception $e) {
            return \App\Helpers\ResponseHelper::error('Format ID Jenis Analisa tidak valid.', 400);
        }

        // Try Catch Kedua: Logika Utama Data & Database
        try {
            $ujiSampel = DB::table('N_EMI_LIMS_Uji_Sampel') 
                ->join('N_LIMS_PO_Sampel', 'N_EMI_LIMS_Uji_Sampel.No_Po_Sampel', '=', 'N_LIMS_PO_Sampel.No_Sampel')
                ->join('EMI_Master_Mesin', 'N_LIMS_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
                ->leftJoin('N_EMI_LAB_Perhitungan', function ($join) {
                    $join->on('N_EMI_LAB_Perhitungan.id', '=', 'N_EMI_LIMS_Uji_Sampel.Id_Perhitungan')
                        ->on('N_EMI_LAB_Perhitungan.Kode_Perusahaan', '=', 'N_EMI_LIMS_Uji_Sampel.Kode_Perusahaan');
                })
                ->select(
                    'N_LIMS_PO_Sampel.Kode_Barang',
                    'N_EMI_LIMS_Uji_Sampel.No_Faktur',
                    'N_EMI_LIMS_Uji_Sampel.No_Po_Sampel',
                    'N_EMI_LIMS_Uji_Sampel.No_Fak_Sub_Po', 
                    'N_LIMS_PO_Sampel.No_Batch', 
                    'N_EMI_LIMS_Uji_Sampel.Tahapan_Ke', 
                    'N_EMI_LIMS_Uji_Sampel.Flag_Multi_QrCode', 
                    'N_EMI_LIMS_Uji_Sampel.Flag_Resampling', 
                    'N_EMI_LIMS_Uji_Sampel.Status_Keputusan_Sampel', 
                    'N_EMI_LIMS_Uji_Sampel.Flag_Layak', 
                    'N_EMI_LIMS_Uji_Sampel.Flag_Final', 
                    'N_EMI_LIMS_Uji_Sampel.Id_Mesin', 
                    'N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa',
                    'N_EMI_LIMS_Uji_Sampel.Tanggal as Tanggal_Pengujian',
                    'N_EMI_LIMS_Uji_Sampel.Hasil as Hasil_Akhir_Analisa',
                    'N_EMI_LIMS_Uji_Sampel.Flag_Perhitungan',
                    'N_EMI_LIMS_Uji_Sampel.Range_Awal',
                    'N_EMI_LIMS_Uji_Sampel.Range_Akhir',
                    'N_LIMS_PO_Sampel.No_Po',
                    'N_LIMS_PO_Sampel.No_Split_Po',
                    'EMI_Master_Mesin.Flag_FG',
                    'N_EMI_LIMS_Uji_Sampel.Flag_Foto', // Penambahan Field Flag_Foto
                    DB::raw("ISNULL(N_EMI_LAB_Perhitungan.Hasil_Perhitungan, 0) AS Pembulatan")
                )
                ->whereNull('N_EMI_LIMS_Uji_Sampel.Status')
                ->where('N_EMI_LIMS_Uji_Sampel.No_Po_Sampel', $no_po_sampel)
                ->where('N_EMI_LIMS_Uji_Sampel.No_Fak_Sub_po', $no_sub)
                ->where('N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa_decoded)
                ->where('N_EMI_LIMS_Uji_Sampel.Status_Keputusan_Sampel', 'terima')
                ->get();

            if ($ujiSampel->isEmpty()) {
                return \App\Helpers\ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            // Deklarasi Sesi Foto dan Faktur List
            $hasSesiFoto = $ujiSampel->contains('Flag_Foto', 'Y') ? 'Y' : 'T';
            $fakturList = $ujiSampel->pluck('No_Faktur')->unique()->toArray();

            // Ambil Data Berkas (Foto) dari Database
            $berkasRaw = DB::table('N_EMI_LIMS_Berkas_Uji_Lab')
                ->select('Id_Berkas_Uji_Lab', 'No_Faktur', 'Berkas_Key')
                ->whereIn('No_Faktur', $fakturList)
                ->get()
                ->groupBy('No_Faktur');

            $rawRules = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->select('Nilai_Kriteria', 'Keterangan_Kriteria')
                ->where("Kode_Role", "FLM")
                ->where('Id_Jenis_Analisa', $id_jenis_analisa_decoded)
                ->where('Flag_Aktif', 'Y')
                ->get();

            $rulesMap = [];
            foreach ($rawRules as $rule) {
                $key = (string)((float)$rule->Nilai_Kriteria);
                $rulesMap[$key] = $rule->Keterangan_Kriteria;
            }

            $parameterRaw = DB::table('N_EMI_LIMS_Uji_Sampel_Detail')
                ->select(
                    'Id_Uji_Sample_Detail',
                    'No_Faktur_Uji_Sample',
                    'Id_Quality_Control',
                    'Value_Parameter as Hasil_Analisa',
                    'Tanggal as Tanggal_Parameter_Analisa',
                    'Jam as Jam_Parameter_Analisa'
                )
                ->whereIn('No_Faktur_Uji_Sample', $fakturList) // Optimasi menggunakan array fakturList
                ->get()
                ->groupBy('No_Faktur_Uji_Sample');

            $result = [];

            foreach ($ujiSampel as $item) {
                $item->Range_Awal = (float) $item->Range_Awal;
                $item->Range_Akhir = (float) $item->Range_Akhir;
                
                $valHeader = (float)$item->Hasil_Akhir_Analisa;
                $keyHeader = (string)$valHeader;

                if (is_null($item->Flag_Perhitungan) && isset($rulesMap[$keyHeader])) {
                    $item->Hasil_Akhir_Analisa = $rulesMap[$keyHeader];
                } else {
                    $item->Hasil_Akhir_Analisa = number_format($valHeader, $item->Pembulatan, '.', '');
                }

                $params = $parameterRaw->get($item->No_Faktur);
                $transformedParameters = [];

                if ($params) {
                    foreach ($params as $param) {
                        $valParam = (float)$param->Hasil_Analisa;
                        $keyParam = (string)$valParam;

                        if (is_null($item->Flag_Perhitungan) && isset($rulesMap[$keyParam])) {
                            $hasilTampil = $rulesMap[$keyParam];
                        } else {
                            $hasilTampil = round($valParam, 4);
                        }

                        $transformedParameters[] = [
                            'Id_Jenis_Analisa' => Hashids::connection('custom')->encode($id_jenis_analisa_decoded),
                            'Id_Uji_Sample_Detail' => Hashids::connection('custom')->encode($param->Id_Uji_Sample_Detail),
                            'No_Faktur_Uji_Sample' => $param->No_Faktur_Uji_Sample,
                            'Id_Quality_Control' => Hashids::connection('custom')->encode($param->Id_Quality_Control),
                            'Hasil_Analisa' => $hasilTampil,
                            'Tanggal_Parameter_Analisa' => $param->Tanggal_Parameter_Analisa,
                            'Jam_Parameter_Analisa' => $param->Jam_Parameter_Analisa,
                        ];
                    }
                }

                // Transformasi Data Foto
                $berkas = $berkasRaw->get($item->No_Faktur);
                $fotoList = [];

                if ($berkas) {
                    foreach ($berkas as $file) {
                        $fotoList[] = [
                            'Berkas_Key' => $file->Berkas_Key,
                        ];
                    }
                }

                $item->foto_analisa = $fotoList;
                unset($item->Flag_Foto); // Dihapus karena sudah ada informasinya di object level atas
                $item->parameter = $transformedParameters;
                $result[] = $item;
            }

            // Return Data Sesuai Pola Baru
            return \App\Helpers\ResponseHelper::success(
                [
                    'informasi' => [
                        'sesi_foto' => $hasSesiFoto
                    ],
                    'sampel' => $result
                ],
                'Data Ditemukan',
                200
            );

        } catch (\Exception $e) {
            Log::channel("FormulatorTrialSampelController")->error($e->getMessage());
            return \App\Helpers\ResponseHelper::error('Terjadi kesalahan pada server. Silahkan hubungi administrator.', 500);
        }
    }

    public function getVerifikasiHasilAnalisaFinalKeputusanV1NoPcs($id_jenis_analisa, $no_po_sampel)
    {  
        try {
            $id_jenis_analisa_decoded = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
        } catch (\Exception $e) {
            return \App\Helpers\ResponseHelper::error('Format ID Jenis Analisa tidak valid.', 400);
        }

        try {
            $ujiSampel = DB::table('N_EMI_LIMS_Uji_Sampel') 
                ->join('N_LIMS_PO_Sampel', 'N_EMI_LIMS_Uji_Sampel.No_Po_Sampel', '=', 'N_LIMS_PO_Sampel.No_Sampel')
                ->join('EMI_Master_Mesin', 'N_LIMS_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
                ->leftJoin('N_EMI_LAB_Perhitungan', function ($join) {
                    $join->on('N_EMI_LAB_Perhitungan.id', '=', 'N_EMI_LIMS_Uji_Sampel.Id_Perhitungan')
                        ->on('N_EMI_LAB_Perhitungan.Kode_Perusahaan', '=', 'N_EMI_LIMS_Uji_Sampel.Kode_Perusahaan');
                })
                ->leftJoin('N_EMI_LAB_Standar_Rentang', function ($join) {
                    $join->on('N_EMI_LAB_Standar_Rentang.Id_Jenis_Analisa', '=', 'N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa')
                        ->on('N_EMI_LAB_Standar_Rentang.Id_Master_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
                        ->on('N_EMI_LAB_Standar_Rentang.Kode_Barang', '=', 'N_LIMS_PO_Sampel.Kode_Barang');
                })
                ->select(
                    'N_LIMS_PO_Sampel.Kode_Barang',
                    'N_EMI_LIMS_Uji_Sampel.No_Faktur',
                    'N_EMI_LIMS_Uji_Sampel.No_Po_Sampel',
                    'N_EMI_LIMS_Uji_Sampel.No_Fak_Sub_Po', 
                    'N_LIMS_PO_Sampel.No_Batch', 
                    'N_EMI_LIMS_Uji_Sampel.Tahapan_Ke', 
                    'N_EMI_LIMS_Uji_Sampel.Flag_Multi_QrCode', 
                    'N_EMI_LIMS_Uji_Sampel.Flag_Resampling', 
                    'N_EMI_LIMS_Uji_Sampel.Status_Keputusan_Sampel', 
                    'N_EMI_LIMS_Uji_Sampel.Flag_Layak', 
                    'N_EMI_LIMS_Uji_Sampel.Flag_Final', 
                    'N_EMI_LIMS_Uji_Sampel.Id_Mesin', 
                    'N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa',
                    'N_EMI_LIMS_Uji_Sampel.Tanggal as Tanggal_Pengujian',
                    'N_EMI_LIMS_Uji_Sampel.Hasil as Hasil_Akhir_Analisa',
                    'N_LIMS_PO_Sampel.No_Po',
                    'N_LIMS_PO_Sampel.No_Split_Po',
                    'EMI_Master_Mesin.Flag_FG',
                    'N_EMI_LIMS_Uji_Sampel.Flag_Foto',
                    DB::raw("ISNULL(N_EMI_LAB_Perhitungan.Hasil_Perhitungan, 0) AS Pembulatan"),
                    DB::raw("CASE 
                                WHEN N_EMI_LAB_Standar_Rentang.Id_Standar_Rentang IS NOT NULL THEN N_EMI_LAB_Standar_Rentang.Range_Awal
                                ELSE NULL
                            END AS Range_Awal"),
                    DB::raw("CASE 
                                WHEN N_EMI_LAB_Standar_Rentang.Id_Standar_Rentang IS NOT NULL THEN N_EMI_LAB_Standar_Rentang.Range_Akhir
                                ELSE NULL
                            END AS Range_Akhir")
                )
                ->whereNull('N_EMI_LIMS_Uji_Sampel.Status')
                ->where('N_EMI_LIMS_Uji_Sampel.No_Po_Sampel', $no_po_sampel)
                ->where('N_EMI_LIMS_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa_decoded)
                ->where('N_EMI_LIMS_Uji_Sampel.Status_Keputusan_Sampel', 'terima')
                ->get();

            if ($ujiSampel->isEmpty()) {
                return \App\Helpers\ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            $hasSesiFoto = $ujiSampel->contains('Flag_Foto', 'Y') ? 'Y' : 'T';
            $fakturList = $ujiSampel->pluck('No_Faktur')->unique()->toArray();

            // PERUBAHAN DISINI: Tambahkan kolom 'Keterangan' pada metode select
            $berkasRaw = DB::table('N_EMI_LIMS_Berkas_Uji_Lab')
                ->select('Id_Berkas_Uji_Lab', 'No_Faktur', 'Berkas_Key', 'Keterangan') 
                ->whereIn('No_Faktur', $fakturList)
                ->get()
                ->groupBy('No_Faktur');

            $parameterRaw = DB::table('N_EMI_LIMS_Uji_Sampel_Detail')
                ->select(
                    'Id_Uji_Sample_Detail',
                    'No_Faktur_Uji_Sample',
                    'Id_Quality_Control',
                    'Value_Parameter as Hasil_Analisa',
                    'Tanggal as Tanggal_Parameter_Analisa',
                    'Jam as Jam_Parameter_Analisa'
                )
                ->whereIn('No_Faktur_Uji_Sample', $fakturList) 
                ->get()
                ->groupBy('No_Faktur_Uji_Sample');

            // --- AMBIL DATA STANDAR NON PERHITUNGAN (CARA ANDA) ---
            $rawRules = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->select('Nilai_Kriteria', 'Keterangan_Kriteria')
                ->where("Kode_Role", "FLM")
                ->where('Id_Jenis_Analisa', $id_jenis_analisa_decoded)
                ->where('Flag_Aktif', 'Y')
                ->get();

            // Ubah menjadi Array Dictionary agar O(1) Lookup (Sangat Cepat)
            $rulesDict = [];
            foreach ($rawRules as $rule) {
                // Gunakan (string)floatval agar "-999999.0" atau "-999999" dianggap kunci yang sama
                $kunci = (string)floatval($rule->Nilai_Kriteria);
                $rulesDict[$kunci] = $rule->Keterangan_Kriteria;
            }

            $result = [];

            foreach ($ujiSampel as $sampel) {
                $sampel->Range_Awal = (float) $sampel->Range_Awal;
                $sampel->Range_Akhir = (float) $sampel->Range_Akhir;

                // --- CEK HASIL AKHIR ANALISA ---
                $kunciHasil = (string)floatval($sampel->Hasil_Akhir_Analisa);
                if (isset($rulesDict[$kunciHasil])) {
                    // Jika angkanya ada di dictionary, ambil keterangannya (misal: "Wangi")
                    $sampel->Hasil_Akhir_Analisa = $rulesDict[$kunciHasil];
                } else {
                    // Jika tidak ada, gunakan nilai aslinya dengan format pembulatan
                    $sampel->Hasil_Akhir_Analisa = number_format((float)$sampel->Hasil_Akhir_Analisa, $sampel->Pembulatan, '.', '');
                }

                $item = (array) $sampel;
                $parameters = $parameterRaw->get($sampel->No_Faktur)?->values() ?? collect([]);
            
                $transformedParameters = $parameters->map(function ($param) use ($id_jenis_analisa_decoded, $rulesDict) {
                    
                    // --- CEK HASIL PARAMETER ANALISA ---
                    $kunciParam = (string)floatval($param->Hasil_Analisa);
                    $hasilFinalParam = isset($rulesDict[$kunciParam]) 
                                        ? $rulesDict[$kunciParam] 
                                        : round(floatval($param->Hasil_Analisa), 4);

                    return [
                        'Id_Jenis_Analisa' => Hashids::connection('custom')->encode($id_jenis_analisa_decoded),
                        'Id_Uji_Sample_Detail' => Hashids::connection('custom')->encode($param->Id_Uji_Sample_Detail),
                        'No_Faktur_Uji_Sample' => $param->No_Faktur_Uji_Sample,
                        'Id_Quality_Control' => Hashids::connection('custom')->encode($param->Id_Quality_Control),
                        'Hasil_Analisa' => $hasilFinalParam, // Hasil yang sudah dicek
                        'Tanggal_Parameter_Analisa' => $param->Tanggal_Parameter_Analisa,
                        'Jam_Parameter_Analisa' => $param->Jam_Parameter_Analisa,
                    ];
                });

                $berkas = $berkasRaw->get($sampel->No_Faktur);
                $fotoList = [];

                if ($berkas) {
                    foreach ($berkas as $file) {
                        $fotoList[] = [
                            'Berkas_Key' => $file->Berkas_Key,
                            // PERUBAHAN DISINI: Mapping data Keterangan ke dalam array
                            'Keterangan' => $file->Keterangan ?? null,
                        ];
                    }
                }
            
                $item['foto_analisa'] = $fotoList;
                unset($item['Flag_Foto']); 
                $item['parameter'] = $transformedParameters;
                
                $result[] = $item;
            }

            return ResponseHelper::success(
                [
                    'informasi' => [
                        'sesi_foto' => $hasSesiFoto
                    ],
                    'sampel' => $result
                ],
                'Data Ditemukan',
                200
            );

        } catch (\Exception $e) {
            Log::channel("FormulatorTrialSampelController")->error($e->getMessage());
            return ResponseHelper::error('Terjadi kesalahan pada server. Silahkan hubungi administrator.', 500);
        }
    }

    public function getDataHasilAnalisaPerhitunganByNoMulti($id_jenis_analisa, $no_po_sampel)
    {
        try {
            $id_jenis_analisa = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Jenis Analisa tidak valid.'
            ], 400);
        }

        $ujiSampel = DB::table('N_EMI_LAB_Uji_Sampel')
            ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
            ->join('EMI_Master_Mesin', 'N_EMI_LAB_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
            ->leftJoin('N_EMI_LAB_Perhitungan', function ($join) {
                $join->on('N_EMI_LAB_Perhitungan.id', '=', 'N_EMI_LAB_Uji_Sampel.Id_Perhitungan')
                    ->on('N_EMI_LAB_Perhitungan.Kode_Perusahaan', '=', 'N_EMI_LAB_Uji_Sampel.Kode_Perusahaan');
            })
            ->select(
                'N_EMI_LAB_Uji_Sampel.No_Faktur',
                'N_EMI_LAB_Uji_Sampel.No_Po_Sampel',
                'N_EMI_LAB_Uji_Sampel.No_Fak_Sub_Po',
                'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa',
                'N_EMI_LAB_Uji_Sampel.Tanggal as Tanggal_Pengujian',
                'N_EMI_LAB_Uji_Sampel.Hasil as Hasil_Akhir_Analisa',
                'N_EMI_LAB_PO_Sampel.No_Po',
                'N_EMI_LAB_PO_Sampel.No_Split_Po',
                DB::raw("ISNULL(N_EMI_LAB_Perhitungan.Hasil_Perhitungan, 0) AS Pembulatan")
            )
            ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
            ->where('N_EMI_LAB_Uji_Sampel.No_Po_Sampel', $no_po_sampel)
            ->where('N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa)
            ->whereNull('N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode')
            ->where('N_EMI_LAB_Uji_Sampel.Flag_Selesai', 'Y')
            ->get();


        if ($ujiSampel->isEmpty()) {
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Data Tidak Ditemukan',
            ], 200);
        }

        foreach ($ujiSampel as $item) {
            $item->Hasil_Akhir_Analisa = number_format((float)$item->Hasil_Akhir_Analisa, $item->Pembulatan, '.', '');
        }

        $parameterRaw = DB::table('N_EMI_LAB_Uji_Sampel_Detail')
            ->select(
                'Id_Uji_Sample_Detail',
                'No_Faktur_Uji_Sample',
                'Id_Quality_Control',
                'Value_Parameter as Hasil_Analisa',
                'Tanggal as Tanggal_Parameter_Analisa',
                'Jam as Jam_Parameter_Analisa'
            )
            ->whereIn('No_Faktur_Uji_Sample', $ujiSampel->pluck('No_Faktur'))
            ->get()
            ->groupBy('No_Faktur_Uji_Sample');

        $result = [];

        foreach ($ujiSampel as $sampel) {
            $item = (array) $sampel;
            $parameters = $parameterRaw->get($sampel->No_Faktur)?->values() ?? [];
        
            // transformasi setiap parameter
            $transformedParameters = $parameters->map(function ($param) use ($id_jenis_analisa) {
                return [
                    'Id_Jenis_Analisa' => Hashids::connection('custom')->encode($id_jenis_analisa),
                    'Id_Uji_Sample_Detail' => Hashids::connection('custom')->encode($param->Id_Uji_Sample_Detail),
                    'No_Faktur_Uji_Sample' => $param->No_Faktur_Uji_Sample,
                    'Id_Quality_Control' => Hashids::connection('custom')->encode($param->Id_Quality_Control),
                    'Hasil_Analisa' => round(floatval($param->Hasil_Analisa), 4),
                    'Tanggal_Parameter_Analisa' => $param->Tanggal_Parameter_Analisa,
                    'Jam_Parameter_Analisa' => $param->Jam_Parameter_Analisa,
                ];
            });
        
            $item['parameter'] = $transformedParameters;
            $result[] = $item;
        }

        $informasi = DB::table('N_EMI_LAB_Uji_Sampel')
            ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
            ->join('EMI_Master_Mesin', 'N_EMI_LAB_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
            ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
            ->select(
                'N_EMI_LAB_Uji_Sampel.No_Faktur',
                'N_EMI_LAB_Uji_Sampel.No_Po_Sampel',
                'N_EMI_LAB_Uji_Sampel.No_Fak_Sub_Po',
                'N_EMI_LAB_Uji_Sampel.Flag_Perhitungan',
                'N_EMI_LAB_Uji_Sampel.Tanggal as Tanggal_Pengujian',
                'N_EMI_LAB_Uji_Sampel.Jam as Jam_Pengujian',
                'N_EMI_LAB_PO_Sampel.Tanggal as Tanggal_Pengajuan',
                'N_EMI_LAB_PO_Sampel.Jam as Jam_Pengajuan',
                'N_EMI_LAB_PO_Sampel.No_Po',
                'N_EMI_LAB_PO_Sampel.Keterangan as Catatan',
                'N_EMI_LAB_PO_Sampel.No_Split_Po',
                'N_EMI_LAB_PO_Sampel.No_Batch',
                'N_EMI_LAB_PO_Sampel.Kode_Barang',
                'EMI_Master_Mesin.Seri_Mesin',
                'EMI_Master_Mesin.Nama_Mesin', 
                'N_EMI_LAB_Jenis_Analisa.Kode_Analisa',
                'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa'
            )
            ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
            ->where('N_EMI_LAB_Uji_Sampel.No_Po_Sampel', $result[0]['No_Po_Sampel'])
            ->where('N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa)
            ->whereNull('N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode')
            ->where('N_EMI_LAB_Uji_Sampel.Flag_Selesai', 'Y')
            ->first();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Data Ditemukan',
            'result' => [
                'informasi' => $informasi,
                'sampel' => $result
            ]
        ], 200);
    }
   
    public function getDataHasilAnalisaPerhitunganByNoMultiV2($id_jenis_analisa, $no_po_sampel)
    {
        try {
            $decoded_id_jenis_analisa = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Jenis Analisa tidak valid.'
            ], 400);
        }

        try {
            $daftarSampel = DB::table('N_EMI_LIMS_Uji_Sampel as uji')
                ->join('N_LIMS_PO_Sampel as po', 'uji.No_Po_Sampel', '=', 'po.No_Sampel')
                ->join('EMI_Master_Mesin as mesin', 'po.Id_Mesin', '=', 'mesin.Id_Master_Mesin')
                ->join('N_EMI_LAB_Jenis_Analisa as jenis', 'uji.Id_Jenis_Analisa', '=', 'jenis.id')
                ->leftJoin('N_EMI_LAB_Perhitungan as hitung', function ($join) {
                    $join->on('hitung.id', '=', 'uji.Id_Perhitungan')
                        ->on('hitung.Kode_Perusahaan', '=', 'uji.Kode_Perusahaan');
                })
                ->leftJoin('N_EMI_LAB_Standar_Rentang as standar', function ($join) {
                    $join->on('standar.Id_Jenis_Analisa', '=', 'uji.Id_Jenis_Analisa')
                        ->on('standar.Id_Master_Mesin', '=', 'mesin.Id_Master_Mesin')
                        ->on('standar.Kode_Barang', '=', 'po.Kode_Barang');
                })
                ->select(
                    'uji.Kode_Perusahaan', // Ditambahkan untuk kebutuhan query Standar Rentang Non Perhitungan
                    'uji.No_Faktur', 'uji.No_Po_Sampel', 'uji.No_Fak_Sub_Po', 'uji.Flag_Perhitungan',
                    'uji.Tanggal as Tanggal_Pengujian', 'uji.Jam as Jam_Pengujian',
                    'po.Tanggal as Tanggal_Pengajuan', 'po.Jam as Jam_Pengajuan', 'po.No_Po',
                    'po.Keterangan as Catatan', 'po.No_Split_Po', 'po.No_Batch', 'po.Kode_Barang',
                    'mesin.Seri_Mesin', 'mesin.Nama_Mesin', 'jenis.Kode_Analisa', 'jenis.Jenis_Analisa',
                    'uji.Hasil as Hasil_Akhir_Analisa',
                    'uji.Flag_Foto', 
                    DB::raw("ISNULL(hitung.Hasil_Perhitungan, 0) AS Pembulatan"),
                    DB::raw("CAST(CASE WHEN standar.Id_Standar_Rentang IS NOT NULL THEN 1 ELSE 0 END AS BIT) as is_sop"),
                    'standar.Range_Awal', 'standar.Range_Akhir'
                )
                ->whereNull('uji.Status')
                ->where('uji.No_Po_Sampel', $no_po_sampel)
                ->where('uji.Id_Jenis_Analisa', $decoded_id_jenis_analisa)
                ->whereNull('uji.Flag_Multi_QrCode')
                ->where('uji.Flag_Selesai', 'Y')
                ->orderBy('uji.No_Faktur')
                ->get(); 

            if ($daftarSampel->isEmpty()) {
                return response()->json([
                    'success' => true, 'status' => 200, 'message' => 'Data Tidak Ditemukan'
                ], 200);
            }

            // Deklarasi Sesi Foto dan Faktur List
            $hasSesiFoto = $daftarSampel->contains('Flag_Foto', 'Y') ? 'Y' : 'T';
            $fakturList = $daftarSampel->pluck('No_Faktur')->unique()->toArray();
            $kodePerusahaan = $daftarSampel->first()->Kode_Perusahaan;

            // Pengambilan Data Standar Rentang Non Perhitungan (Dinamis)
            $referensiNonHitung = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->select('Nilai_Kriteria', 'Keterangan_Kriteria')
                ->where('Id_Jenis_Analisa', $decoded_id_jenis_analisa)
                ->where('Kode_Perusahaan', $kodePerusahaan)
                ->where('Flag_Aktif', 'Y')
                // ->where('Kode_Role', 'FLM') // Opsional: Aktifkan jika memang perlu dilimitasi berdasarkan Role FLM/LAB
                ->get()
                ->mapWithKeys(function ($item) {
                    return [(string)floatval($item->Nilai_Kriteria) => $item->Keterangan_Kriteria];
                });

            // Pengambilan data Berkas / Foto
            $berkasRaw = DB::table('N_EMI_LIMS_Berkas_Uji_Lab') 
                ->select('Id_Berkas_Uji_Lab', 'No_Faktur', 'Berkas_Key', 'Keterangan')
                ->whereIn('No_Faktur', $fakturList)
                ->get()
                ->groupBy('No_Faktur');
        
            $parameterRaw = DB::table('N_EMI_LIMS_Uji_Sampel_Detail')
                ->select(
                    'Id_Uji_Sample_Detail', 'No_Faktur_Uji_Sample', 'Id_Quality_Control',
                    'Value_Parameter as Hasil_Analisa',
                    'Tanggal as Tanggal_Parameter_Analisa', 'Jam as Jam_Parameter_Analisa'
                )
                ->whereIn('No_Faktur_Uji_Sample', $fakturList) 
                ->get()
                ->groupBy('No_Faktur_Uji_Sample');

            $hasilProses = [];

            foreach ($daftarSampel as $sampel) {
                
                $parameterDetails = $parameterRaw->get($sampel->No_Faktur);
                $transformedParameters = [];

                if ($parameterDetails) {
                    foreach ($parameterDetails as $param) {
                        $hasilFloat = floatval($param->Hasil_Analisa);
                        $hasilString = (string)$hasilFloat;
                        $hasilTampil = null;

                        // Mengganti Hardcode dengan pengecekan dinamis berdasarkan Flag_Perhitungan dan Referensi
                        if ($sampel->Flag_Perhitungan == 'Y') {
                            $hasilTampil = round($hasilFloat, 4);
                        } else {
                            if (isset($referensiNonHitung[$hasilString])) {
                                $hasilTampil = $referensiNonHitung[$hasilString];
                            } else {
                                $hasilTampil = round($hasilFloat, 4);
                            }
                        }

                        $transformedParameters[] = [
                            'Id_Jenis_Analisa'          => $id_jenis_analisa,
                            'Id_Uji_Sample_Detail'      => Hashids::connection('custom')->encode($param->Id_Uji_Sample_Detail),
                            'No_Faktur_Uji_Sample'      => $param->No_Faktur_Uji_Sample,
                            'Id_Quality_Control'        => Hashids::connection('custom')->encode($param->Id_Quality_Control),
                            'Hasil_Analisa'             => $hasilTampil,
                            'Tanggal_Parameter_Analisa' => $param->Tanggal_Parameter_Analisa,
                            'Jam_Parameter_Analisa'     => $param->Jam_Parameter_Analisa,
                        ];
                    }
                }

                $berkas = $berkasRaw->get($sampel->No_Faktur);
                $fotoList = [];

                if ($berkas) {
                    foreach ($berkas as $file) {
                        $fotoList[] = [
                            'Berkas_Key' => $file->Berkas_Key,
                            'Keterangan' => $file->Keterangan ?? 'Tidak Ada Keterangan',
                        ];
                    }
                }

                $sampel->is_sop = (bool) $sampel->is_sop;
                $sampel->Range_Awal = (float) $sampel->Range_Awal;
                $sampel->Range_Akhir = (float) $sampel->Range_Akhir;
                $sampel->Hasil_Akhir_Analisa = number_format((float)$sampel->Hasil_Akhir_Analisa, $sampel->Pembulatan, '.', '');
                
                $sampelData = (array) $sampel;
                unset($sampelData['Flag_Foto']); // Hapus Flag_Foto dari output array
                $sampelData['foto_analisa'] = $fotoList; // Masukkan list foto
                $sampelData['parameter'] = $transformedParameters; // Masukkan list parameter
                
                $hasilProses[] = $sampelData;
            }

            $sampelPertama = $daftarSampel->first();
            $informasi = [
                'No_Faktur'         => $sampelPertama->No_Faktur,
                'No_Po_Sampel'      => $sampelPertama->No_Po_Sampel,
                'Flag_Perhitungan'  => $sampelPertama->Flag_Perhitungan,
                'Tanggal_Pengujian' => $sampelPertama->Tanggal_Pengujian,
                'Jam_Pengujian'     => $sampelPertama->Jam_Pengujian,
                'Tanggal_Pengajuan' => $sampelPertama->Tanggal_Pengajuan,
                'Jam_Pengajuan'     => $sampelPertama->Jam_Pengajuan,
                'No_Po'             => $sampelPertama->No_Po,
                'Catatan'           => $sampelPertama->Catatan,
                'No_Split_Po'       => $sampelPertama->No_Split_Po,
                'No_Batch'          => $sampelPertama->No_Batch,
                'Kode_Barang'       => $sampelPertama->Kode_Barang,
                'Seri_Mesin'        => $sampelPertama->Seri_Mesin,
                'Nama_Mesin'        => $sampelPertama->Nama_Mesin,
                'Kode_Analisa'      => $sampelPertama->Kode_Analisa,
                'Jenis_Analisa'     => $sampelPertama->Jenis_Analisa,
                'sesi_foto'         => $hasSesiFoto // Penambahan sesi_foto
            ];

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Data Ditemukan',
                'result'  => [
                    'informasi' => $informasi,
                    'sampel'    => $hasilProses
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::channel("FormulatorTrialSampelController")->error($e->getMessage());
            return response()->json([
                'success' => false, 
                'status'  => 500, 
                'message' => 'Terjadi kesalahan pada server. Silahkan hubungi administrator.',
            ], 500);
        }
    }

    public function downloadRekapSampel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'analysis' => 'required|array',
            'analysis.*' => 'required|string',
            'startDate' => 'required|date',
            'endDate' => 'required|date|after_or_equal:startDate',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'status' => 400, 'message' => 'Input tidak valid.', 'errors' => $validator->errors()], 400);
        }

        $tempPath = storage_path('app/temp_exports/' . uniqid());
        File::makeDirectory($tempPath, 0775, true, true);
        $filePaths = [];

        $checkedIdMaster = $request->Id_Master_Mesin;
        $filterMesinId = null;
        if ($checkedIdMaster !== "all") {
            $decoded = Hashids::connection('custom')->decode($checkedIdMaster);
            $filterMesinId = $decoded[0] ?? null;
        }

        $targetAnalyses = [];
        $analysisIds = [];
        foreach ($request->analysis as $index => $hashedIdAnalisa) {
            $decodedId = Hashids::connection('custom')->decode($hashedIdAnalisa)[0] ?? null;
            if ($decodedId) {
                $analysisIds[] = $decodedId;
                $targetAnalyses[] = ['id' => $decodedId, 'index' => $index];
            }
        }

        if (empty($analysisIds)) {
            return response()->json(['success' => false, 'status' => 404, 'message' => 'Tidak ada analisa valid.'], 404);
        }

        $jenisAnalisaMap = DB::table('N_EMI_LAB_Jenis_Analisa')
            ->whereIn('id', $analysisIds)
            ->where('Kode_Role', 'FLM')
            ->get()
            ->keyBy('id');
        
        $parametersMap = DB::table('N_EMI_LAB_Binding_jenis_analisa as b')
            ->join('EMI_Quality_Control as q', 'q.Id_QC_Formula', '=', 'b.Id_Quality_Control')
            ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ja.id', '=', 'b.Id_Jenis_Analisa')
            ->leftJoin('EMI_Kategori_Komponen as kk', 'kk.Id_Kategori_Komponen', '=', 'q.Id_Kategori_Komponen')
            ->whereIn('b.Id_Jenis_Analisa', $analysisIds)
            ->where('b.Kode_Role', 'FLM')
            ->select(
                'b.id', 'b.Id_Quality_Control as id_qc', 'b.Id_Jenis_Analisa',
                'q.Keterangan as nama_parameter', 'kk.Keterangan AS type_inputan',
                'q.Satuan as satuan', 'q.Kode_Uji as kode_uji', 'ja.Kode_Analisa as kode_analisa',
                'ja.Jenis_Analisa as jenis_analisa', 'ja.Flag_Perhitungan as flag_perhitungan'
            )
            ->get()
            ->groupBy('Id_Jenis_Analisa');

        $rumusMap = DB::table('N_EMI_LAB_Perhitungan')
            ->whereIn('Id_Jenis_Analisa', $analysisIds)
            ->where('Kode_Role', 'FLM')
            ->select('Id', 'Id_Jenis_Analisa', 'Rumus as rumus', 'Nama_Kolom as nama_kolom', 'Hasil_Perhitungan as digit')
            ->get()
            ->groupBy('Id_Jenis_Analisa');

        $nonCalcMap = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
            ->whereIn('Id_Jenis_Analisa', $analysisIds)
            ->where('Kode_Role', 'FLM')
            ->where('Flag_Aktif', 'Y')
            ->select('Id_Jenis_Analisa', 'Nilai_Kriteria', 'Keterangan_Kriteria')
            ->get()
            ->groupBy('Id_Jenis_Analisa');

        $requestFlags = $request->Flag_Perhitungan ?? [];

        foreach ($targetAnalyses as $target) {
            $id_analisa = $target['id'];
            $index = $target['index'];

            $getNamaJenisAnalisa = $jenisAnalisaMap->get($id_analisa);
            if (!$getNamaJenisAnalisa) continue;

            $getParameter = $parametersMap->get($id_analisa);
            if (!$getParameter || $getParameter->isEmpty()) continue;

            $flagPerhitungan = $requestFlags[$index] ?? null;
            $isPerhitungan = $flagPerhitungan === 'Y';
            $isFoto = $getNamaJenisAnalisa->Flag_Foto === 'Y';
            $getDataRumus = $isPerhitungan ? ($rumusMap->get($id_analisa) ?? collect([])) : null;

            $currentNonCalcLookup = [];
            if (!$isPerhitungan && isset($nonCalcMap[$id_analisa])) {
                foreach ($nonCalcMap[$id_analisa] as $item) {
                    $cleanKey = (string)((float)$item->Nilai_Kriteria);
                    $currentNonCalcLookup[$cleanKey] = $item->Keterangan_Kriteria;
                }
            }

            $hashedIdJenisAnalisa = Hashids::connection('custom')->encode($id_analisa);

            $queryHeader = DB::table('N_EMI_LIMS_Uji_Sampel as US')
                ->join('N_LIMS_PO_Sampel as PO', 'US.No_Po_Sampel', '=', 'PO.No_Sampel')
                ->join('EMI_Master_Mesin as M', 'PO.Id_Mesin', '=', 'M.Id_Master_Mesin')
                ->leftJoin('N_EMI_LAB_Perhitungan as C', function ($join) {
                    $join->on('C.id', '=', 'US.Id_Perhitungan')
                        ->on('C.Kode_Perusahaan', '=', 'US.Kode_Perusahaan')
                        ->where('C.Kode_Role', 'FLM');
                })
                ->select(
                    'US.No_Faktur', 'US.No_Po_Sampel', 'US.No_Fak_Sub_Po',
                    'US.Id_Jenis_Analisa', 'US.Id_Mesin', 
                    'US.Tanggal as Tanggal_Pengujian',
                    'US.Hasil as Hasil_Akhir_Analisa',
                    'PO.No_Po', 'PO.No_Split_Po', 'PO.Flag_Selesai',
                    DB::raw("ISNULL(C.Hasil_Perhitungan, 0) AS Pembulatan")
                )
                ->where('US.Id_Jenis_Analisa', $id_analisa)
                ->where('US.Flag_Selesai', 'Y')
                ->whereNull('US.Status')
                ->whereBetween('US.Tanggal', [$request->startDate, $request->endDate]);

            if ($filterMesinId) {
                $queryHeader->where('US.Id_Mesin', $filterMesinId);
            }

            $ujiSampel = $queryHeader->get(); 

            if ($ujiSampel->isEmpty()) continue;

            $allFaktur = $ujiSampel->pluck('No_Faktur')->unique()->values();
            $berkasMap = DB::table('N_EMI_LIMS_Berkas_Uji_Lab')
                ->whereIn('No_Faktur', $allFaktur)
                ->pluck('File_Path', 'No_Faktur');

            $parameterRaw = DB::table('N_EMI_LIMS_Uji_Sampel_Detail as D')
                ->select(
                    'D.Id_Uji_Sample_Detail', 
                    'D.No_Faktur_Uji_Sample', 
                    'D.Id_Quality_Control', 
                    'D.Value_Parameter as Hasil_Analisa', 
                    'D.Tanggal as Tanggal_Parameter_Analisa', 
                    'D.Jam as Jam_Parameter_Analisa'
                )
                ->whereIn('D.No_Faktur_Uji_Sample', $allFaktur)
                ->get()
                ->groupBy('No_Faktur_Uji_Sample');

            $result = []; 

            foreach ($ujiSampel as $itemObj) {
                $item = (array) $itemObj; 
                $cleanVal = (string)((float)$item['Hasil_Akhir_Analisa']);

                if (!$isPerhitungan && isset($currentNonCalcLookup[$cleanVal])) {
                    $item['Hasil_Akhir_Analisa'] = $currentNonCalcLookup[$cleanVal];
                } else {
                    $item['Hasil_Akhir_Analisa'] = number_format((float)$item['Hasil_Akhir_Analisa'], $item['Pembulatan'], '.', '');
                }

                if ($isFoto) {
                    $item['image_path'] = $berkasMap[$item['No_Faktur']] ?? null;
                }

                $rawParams = $parameterRaw->get($item['No_Faktur']);
                $processedParams = [];

                if ($rawParams) {
                    foreach ($rawParams as $param) {
                        $valParam = $param->Hasil_Analisa;
                        $cleanParamVal = (string)((float)$valParam);

                        if (!$isPerhitungan && isset($currentNonCalcLookup[$cleanParamVal])) {
                            $finalHasil = $currentNonCalcLookup[$cleanParamVal];
                        } else {
                            $finalHasil = is_numeric($valParam) ? round(floatval($valParam), 4) : $valParam;
                        }

                        $processedParams[] = [
                            'Id_Jenis_Analisa' => $hashedIdJenisAnalisa, 
                            'Id_Uji_Sample_Detail' => Hashids::connection('custom')->encode($param->Id_Uji_Sample_Detail),
                            'No_Faktur_Uji_Sample' => $param->No_Faktur_Uji_Sample,
                            'Id_Quality_Control' => Hashids::connection('custom')->encode($param->Id_Quality_Control),
                            'Hasil_Analisa' => $finalHasil,
                            'Tanggal_Parameter_Analisa' => $param->Tanggal_Parameter_Analisa,
                            'Jam_Parameter_Analisa' => $param->Jam_Parameter_Analisa,
                        ];
                    }
                }
                
                $item['parameter'] = $processedParams;
                $result[] = $item; 
            }

            $hashedParameters = $getParameter->map(function ($param) {
                return [
                    'id' => Hashids::connection('custom')->encode($param->id),
                    'id_qc' => Hashids::connection('custom')->encode($param->id_qc),
                    'id_jenis_analisa' => Hashids::connection('custom')->encode($param->Id_Jenis_Analisa),
                    'nama_parameter' => $param->nama_parameter,
                    'type_inputan' => $param->type_inputan,
                    'satuan' => $param->satuan,
                    'kode_uji' => $param->kode_uji,
                    'kode_analisa' => $param->kode_analisa,
                    'jenis_analisa' => $param->jenis_analisa,
                    'flag_perhitungan' => $param->flag_perhitungan,
                ];
            })->toArray();

            $hashedFormula = ($getDataRumus && $getDataRumus->isNotEmpty()) ? $getDataRumus->map(function ($rumus) {
                $processedRumus = preg_replace_callback('/\[(\d+)\]/', function ($matches) {
                    return '[' . Hashids::connection('custom')->encode($matches[1]) . ']';
                }, $rumus->rumus);
                return [
                    'id' => Hashids::connection('custom')->encode($rumus->Id),
                    'id_jenis_analisa' => Hashids::connection('custom')->encode($rumus->Id_Jenis_Analisa),
                    'rumus' => $processedRumus,
                    'nama_kolom' => $rumus->nama_kolom,
                    'digit' => $rumus->digit,
                ];
            })->toArray() : [];

            $start = date('d-m-Y', strtotime($request->startDate));
            $end = date('d-m-Y', strtotime($request->endDate));
            $namaAnalisa = ucwords(strtolower($getNamaJenisAnalisa->Jenis_Analisa));
            $safeNamaAnalisa = preg_replace('/[^A-Za-z0-9\-]/', '_', $namaAnalisa);
            $excelFileName = 'Rekap ' . $safeNamaAnalisa . ' Periode ' . $start . '_sampai_' . $end . '.xlsx';

            Excel::store(
                new RekapSampelExport($result, $hashedParameters, $hashedFormula, $namaAnalisa, $isFoto ? 'Y' : 'T'),
                'temp_exports/' . basename($tempPath) . '/' . $excelFileName
            );

            $filePaths[] = $tempPath . DIRECTORY_SEPARATOR . $excelFileName;
        }

        if (empty($filePaths)) {
            return response()->json(['success' => false, 'status' => 404, 'message' => 'Tidak ada data yang diproses.'], 404);
        }

        if (count($filePaths) === 1) {
            return response()->download($filePaths[0])->deleteFileAfterSend(true);
        }

        $zip = new ZipArchive;
        $zipFileName = 'Rekap Sampel ' . date('d-m-Y_H-i-s') . '.zip';
        $zipPath = $tempPath . DIRECTORY_SEPARATOR . $zipFileName;

        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            foreach ($filePaths as $file) {
                if (File::exists($file)) $zip->addFile($file, basename($file));
            }
            $zip->close();
        } else {
            File::deleteDirectory($tempPath);
            return response()->json(['success' => false, 'status' => 500, 'message' => 'Gagal membuat ZIP.'], 500);
        }

        return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
    }

    public function downloadRekapSampelPrafinalisasi(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'No_Po_Sampel' => 'required|string',
            'format'       => 'required|in:excel,pdf',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'status' => 400, 'message' => 'Input tidak valid.', 'errors' => $validator->errors()], 400);
        }

        try {
            $noPoSampel = $request->No_Po_Sampel;

            // 1. Cari semua analisa yang SELESAI untuk sampel ini
            $availableAnalyses = \Illuminate\Support\Facades\DB::table('N_EMI_LIMS_Uji_Sampel')
                ->where('No_Po_Sampel', $noPoSampel)
                ->where('Flag_Selesai', 'Y')
                ->whereNull('Status')
                ->pluck('Id_Jenis_Analisa')
                ->unique()
                ->toArray();

            if (empty($availableAnalyses)) {
                return response()->json(['success' => false, 'status' => 404, 'message' => 'Tidak ada data analisa yang valid/selesai untuk sampel ini.'], 404);
            }

            // 2. Ambil master data pendukung
            $jenisAnalisaMap = \Illuminate\Support\Facades\DB::table('N_EMI_LAB_Jenis_Analisa')
                ->whereIn('id', $availableAnalyses)
                ->get()
                ->keyBy('id');

            $parametersMap = \Illuminate\Support\Facades\DB::table('N_EMI_LAB_Binding_jenis_analisa as b')
                ->join('EMI_Quality_Control as q', 'q.Id_QC_Formula', '=', 'b.Id_Quality_Control')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ja.id', '=', 'b.Id_Jenis_Analisa')
                ->leftJoin('EMI_Kategori_Komponen as kk', 'kk.Id_Kategori_Komponen', '=', 'q.Id_Kategori_Komponen')
                ->whereIn('b.Id_Jenis_Analisa', $availableAnalyses)
                ->select(
                    'b.id', 'b.Id_Quality_Control as id_qc', 'b.Id_Jenis_Analisa',
                    'q.Keterangan as nama_parameter', 'kk.Keterangan AS type_inputan',
                    'q.Satuan as satuan', 'q.Kode_Uji as kode_uji', 'ja.Kode_Analisa as kode_analisa',
                    'ja.Jenis_Analisa as jenis_analisa', 'ja.Flag_Perhitungan as flag_perhitungan'
                )
                ->get()
                ->groupBy('Id_Jenis_Analisa');

            $rumusMap = \Illuminate\Support\Facades\DB::table('N_EMI_LAB_Perhitungan')
                ->whereIn('Id_Jenis_Analisa', $availableAnalyses)
                ->select('Id', 'Id_Jenis_Analisa', 'Rumus as rumus', 'Nama_Kolom as nama_kolom', 'Hasil_Perhitungan as digit')
                ->get()
                ->groupBy('Id_Jenis_Analisa');

            $nonCalcMap = \Illuminate\Support\Facades\DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->whereIn('Id_Jenis_Analisa', $availableAnalyses)
                ->where('Flag_Aktif', 'Y')
                ->select('Id_Jenis_Analisa', 'Nilai_Kriteria', 'Keterangan_Kriteria')
                ->get()
                ->groupBy('Id_Jenis_Analisa');

            $berkasMap = \Illuminate\Support\Facades\DB::table('N_EMI_LIMS_Berkas_Uji_Lab')
                ->where('No_Sampel', $noPoSampel)
                ->pluck('File_Path', 'No_Faktur');

            $sheetsData = []; // Array untuk menampung data per sheet/tab

            // 3. Looping setiap analisa untuk dijadikan 1 Sheet
            foreach ($availableAnalyses as $id_analisa) {
                $getNamaJenisAnalisa = $jenisAnalisaMap->get($id_analisa);
                if (!$getNamaJenisAnalisa) continue;

                $getParameter = $parametersMap->get($id_analisa) ?? collect([]);
                $getDataRumus = $rumusMap->get($id_analisa) ?? collect([]);
                $isPerhitungan = $getNamaJenisAnalisa->Flag_Perhitungan === 'Y';
                $isFoto = $getNamaJenisAnalisa->Flag_Foto === 'Y';

                $currentNonCalcLookup = [];
                if (!$isPerhitungan && isset($nonCalcMap[$id_analisa])) {
                    foreach ($nonCalcMap[$id_analisa] as $item) {
                        $cleanKey = (string)((float)$item->Nilai_Kriteria);
                        $currentNonCalcLookup[$cleanKey] = $item->Keterangan_Kriteria;
                    }
                }

                $hashedIdJenisAnalisa = \Vinkla\Hashids\Facades\Hashids::connection('custom')->encode($id_analisa);

                $ujiSampel = \Illuminate\Support\Facades\DB::table('N_EMI_LIMS_Uji_Sampel as US')
                    ->join('N_LIMS_PO_Sampel as PO', 'US.No_Po_Sampel', '=', 'PO.No_Sampel')
                    ->join('EMI_Master_Mesin as M', 'PO.Id_Mesin', '=', 'M.Id_Master_Mesin')
                    ->leftJoin('N_EMI_LAB_Perhitungan as C', function ($join) {
                        $join->on('C.id', '=', 'US.Id_Perhitungan')->on('C.Kode_Perusahaan', '=', 'US.Kode_Perusahaan');
                    })
                    ->select(
                        'US.No_Faktur', 'US.No_Po_Sampel', 'US.No_Fak_Sub_Po',
                        'US.Id_Jenis_Analisa', 'US.Id_Mesin', 
                        'US.Tanggal as Tanggal_Pengujian',
                        'US.Hasil as Hasil_Akhir_Analisa',
                        'PO.No_Po', 'PO.No_Split_Po', 'PO.Flag_Selesai',
                        \Illuminate\Support\Facades\DB::raw("ISNULL(C.Hasil_Perhitungan, 2) AS Pembulatan")
                    )
                    ->where('US.Id_Jenis_Analisa', $id_analisa)
                    ->where('US.No_Po_Sampel', $noPoSampel)
                    ->where('US.Flag_Selesai', 'Y')
                    ->whereNull('US.Status')
                    ->get(); 

                if ($ujiSampel->isEmpty()) continue;

                $parameterRaw = \Illuminate\Support\Facades\DB::table('N_EMI_LIMS_Uji_Sampel_Detail as D')
                    ->join('N_EMI_LIMS_Uji_Sampel as US', 'D.No_Faktur_Uji_Sample', '=', 'US.No_Faktur')
                    ->select(
                        'D.Id_Uji_Sample_Detail', 
                        'D.No_Faktur_Uji_Sample', 
                        'D.Id_Quality_Control', 
                        'D.Value_Parameter as Hasil_Analisa', 
                        'D.Tanggal as Tanggal_Parameter_Analisa', 
                        'D.Jam as Jam_Parameter_Analisa'
                    )
                    ->where('US.Id_Jenis_Analisa', $id_analisa)
                    ->where('US.No_Po_Sampel', $noPoSampel)
                    ->where('US.Flag_Selesai', 'Y')
                    ->whereNull('US.Status')
                    ->get()
                    ->groupBy('No_Faktur_Uji_Sample');

                $result = []; 
                foreach ($ujiSampel as $itemObj) {
                    $item = (array) $itemObj; 
                    $cleanVal = (string)((float)$item['Hasil_Akhir_Analisa']);
                    
                    if (!$isPerhitungan && isset($currentNonCalcLookup[$cleanVal])) {
                        $item['Hasil_Akhir_Analisa'] = $currentNonCalcLookup[$cleanVal];
                    } else {
                        $item['Hasil_Akhir_Analisa'] = number_format((float)$item['Hasil_Akhir_Analisa'], $item['Pembulatan'], '.', '');
                    }

                    if ($isFoto) {
                        $item['image_path'] = $berkasMap[$item['No_Faktur']] ?? null;
                    }

                    $rawParams = $parameterRaw->get($item['No_Faktur']);
                    $processedParams = [];

                    if ($rawParams) {
                        foreach ($rawParams as $param) {
                            $valParam = $param->Hasil_Analisa;
                            $cleanParamVal = (string)((float)$valParam);

                            if (!$isPerhitungan && isset($currentNonCalcLookup[$cleanParamVal])) {
                                $finalHasil = $currentNonCalcLookup[$cleanParamVal];
                            } else {
                                $finalHasil = is_numeric($valParam) ? round(floatval($valParam), 4) : $valParam;
                            }

                            $processedParams[] = [
                                'Id_Jenis_Analisa' => $hashedIdJenisAnalisa, 
                                'Id_Uji_Sample_Detail' => \Vinkla\Hashids\Facades\Hashids::connection('custom')->encode($param->Id_Uji_Sample_Detail),
                                'No_Faktur_Uji_Sample' => $param->No_Faktur_Uji_Sample,
                                'Id_Quality_Control' => \Vinkla\Hashids\Facades\Hashids::connection('custom')->encode($param->Id_Quality_Control),
                                'Hasil_Analisa' => $finalHasil,
                                'Tanggal_Parameter_Analisa' => $param->Tanggal_Parameter_Analisa,
                                'Jam_Parameter_Analisa' => $param->Jam_Parameter_Analisa,
                            ];
                        }
                    }
                    
                    $item['parameter'] = $processedParams;
                    $result[] = $item; 
                }

                $hashedParameters = $getParameter->map(function ($param) {
                    return [
                        'id' => \Vinkla\Hashids\Facades\Hashids::connection('custom')->encode($param->id),
                        'id_qc' => \Vinkla\Hashids\Facades\Hashids::connection('custom')->encode($param->id_qc),
                        'id_jenis_analisa' => \Vinkla\Hashids\Facades\Hashids::connection('custom')->encode($param->Id_Jenis_Analisa),
                        'nama_parameter' => $param->nama_parameter,
                        'type_inputan' => $param->type_inputan,
                        'satuan' => $param->satuan,
                        'kode_uji' => $param->kode_uji,
                        'kode_analisa' => $param->kode_analisa,
                        'jenis_analisa' => $param->jenis_analisa,
                        'flag_perhitungan' => $param->flag_perhitungan,
                    ];
                })->toArray();

                $hashedFormula = $getDataRumus->map(function ($rumus) {
                    $processedRumus = preg_replace_callback('/\[(\d+)\]/', function ($matches) {
                        return '[' . \Vinkla\Hashids\Facades\Hashids::connection('custom')->encode($matches[1]) . ']';
                    }, $rumus->rumus);
                    return [
                        'id' => \Vinkla\Hashids\Facades\Hashids::connection('custom')->encode($rumus->Id),
                        'id_jenis_analisa' => \Vinkla\Hashids\Facades\Hashids::connection('custom')->encode($rumus->Id_Jenis_Analisa),
                        'rumus' => $processedRumus,
                        'nama_kolom' => $rumus->nama_kolom,
                        'digit' => $rumus->digit,
                    ];
                })->toArray();

                $namaAnalisa = ucwords(strtolower($getNamaJenisAnalisa->Jenis_Analisa));
                $safeNamaAnalisa = preg_replace('/[^A-Za-z0-9\- \(\)]/', '_', $namaAnalisa);
                
                // Simpan ke array sheets
                $sheetsData[] = [
                    'data' => $result,
                    'parameters' => $hashedParameters,
                    'rumus' => $hashedFormula,
                    'namaAnalisa' => $safeNamaAnalisa,
                    'flagFoto' => $isFoto ? 'Y' : 'T'
                ];
            }

            if (empty($sheetsData)) {
                return response()->json(['success' => false, 'status' => 404, 'message' => 'Tidak ada data yang diproses.'], 404);
            }

            // 4. Langsung download 1 File Excel (Multiple Sheets di dalamnya)
            $tanggalCetak = \Carbon\Carbon::now()->isoFormat('D MMMM YYYY HH:mm');
            $excelFileName = 'Detail_Prafinal_Sampel_' . $noPoSampel . '.xlsx';

            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\RekapSampelPrafinalisasiExport($sheetsData, $tanggalCetak),
                $excelFileName
            );

        } catch(\Exception $e){
            \Illuminate\Support\Facades\Log::error('Error Export Detail Sampel: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan Internal Server."
            ], 500); 
        }
    }

    public function getMesinForCetakLaporan()
    {
        $getData = DB::table("EMI_Master_Mesin")
        ->select('Id_Master_Mesin', 'Nama_Mesin')
        ->get();

        if(empty($getData)){
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => "Data Tidak Ditemukan"
            ], 404);
        }

        $mappedData = $getData->map(function ($item) {
            // Misal kamu ingin hash Id_Master_Mesin menggunakan Hash::make
            $item->Id_Master_Mesin = Hashids::connection('custom')->encode($item->Id_Master_Mesin);
            return $item;
        });

        return response()->json([
            'success' => true,
            'status' => 200,
            "message" => "Data Ditemukan",
            "result" => $mappedData
        ], 200);
    }

    public function downloadRekapSampelByPdf(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'analysis'          => 'required|array',
            'analysis.*'        => 'required|string',
            'Flag_Perhitungan'  => 'nullable|array',
            'startDate'         => 'required|date',
            'endDate'           => 'required|date|after_or_equal:startDate',
            'Id_Master_Mesin'   => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Input tidak valid', 'errors' => $validator->errors()], 400);
        }

        try {
            $pdfFilesData = [];
            $checkedIdMaster = $request->Id_Master_Mesin;

            if ($checkedIdMaster && $checkedIdMaster !== "all") {
                $decoded = Hashids::connection('custom')->decode($checkedIdMaster);
                $checkedIdMaster = $decoded[0] ?? null;
            }

            $logoPath = public_path('assets/images/thumb-excel.png');

            foreach ($request->analysis as $key => $hashedId) {
                if (empty($hashedId)) continue;

                $flagPerhitungan = $request->Flag_Perhitungan[$key] ?? null;

                $generatedFiles = $this->generatePdfDataForAnalysis(
                    $hashedId,
                    $flagPerhitungan,
                    $request->startDate,
                    $request->endDate,
                    $checkedIdMaster,
                    $logoPath
                );

                if (!empty($generatedFiles)) {
                    $pdfFilesData = array_merge($pdfFilesData, $generatedFiles);
                }
            }

            if (empty($pdfFilesData)) {
                return response()->json([
                    'success' => false,
                    'status'  => 404,
                    'message' => 'Data tidak ditemukan.'
                ], 404);
            }

            if (count($pdfFilesData) === 1) {
                $singleFile = $pdfFilesData[0];
                $pdf = PDF::loadView('pdf.rekap-sampel', $singleFile['viewData'])
                        ->setPaper('a4', 'landscape')
                        ->setOption('margin-bottom', 10)
                        ->setOption('footer-center', 'Halaman [page] dari [toPage]')
                        ->setOption('enable-local-file-access', true);

                return $pdf->download($singleFile['fileName']);
            }

            $zip = new ZipArchive();
            $zipFileName = 'Rekap_Sampel_Full_' . now()->format('Ymd_His') . '.zip';
            
            if (!File::exists(public_path('temp_pdf'))) {
                File::makeDirectory(public_path('temp_pdf'), 0755, true);
            }
            $zipPath = public_path('temp_pdf/' . $zipFileName);

            if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
                return response()->json(['message' => 'Gagal membuat file zip.'], 500);
            }

            foreach ($pdfFilesData as $pdfData) {
                $pdf = PDF::loadView('pdf.rekap-sampel', $pdfData['viewData'])
                        ->setPaper('a4', 'landscape')
                        ->setOption('margin-bottom', 10)
                        ->setOption('footer-center', 'Halaman [page] dari [toPage]')
                        ->setOption('enable-local-file-access', true);

                $zip->addFromString($pdfData['fileName'], $pdf->output());
            }

            $zip->close();

            return response()->download($zipPath)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::channel('UjiSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                    'success' => false,
                    'status' => 500,
                    'message' => "Terjadi Kesalahan: " . $e->getMessage(),
            ], 500);
        }
    }

    private function generatePdfDataForAnalysis(string $hashedId, ?string $flagPerhitungan, string $startDate, string $endDate, $checkedIdMaster, string $logoPath): array
    {
        $decodedId = Hashids::connection('custom')->decode($hashedId);
        if (empty($decodedId)) return [];
        $id_analisa = $decodedId[0];

        $isPerhitungan = $flagPerhitungan === 'Y';

        $getNamaJenisAnalisa = DB::table('N_EMI_LAB_Jenis_Analisa')->where('Kode_Role', 'FLM')->where('id', $id_analisa)->first();
        if (!$getNamaJenisAnalisa) return [];

        $getParameter = DB::table('N_EMI_LAB_Binding_jenis_analisa as b')
            ->join('EMI_Quality_Control as q', 'q.Id_QC_Formula', '=', 'b.Id_Quality_Control')
            ->where('b.Id_Jenis_Analisa', $id_analisa)
            ->where('b.Kode_Role', 'FLM')
            ->select('q.Keterangan as nama_parameter')
            ->get();

        $getDataRumus = [];
        if ($isPerhitungan) {
            $getDataRumus = DB::table('N_EMI_LAB_Perhitungan')
                ->where('Kode_Role', 'FLM')
                ->where('Id_Jenis_Analisa', $id_analisa)
                ->select('Nama_Kolom as nama_kolom')
                ->get();
        }

        $standarMap = [];
        if (!$isPerhitungan) {
            $rawStandar = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->where('Id_Jenis_Analisa', $id_analisa)
                ->where('Kode_Role', 'FLM')
                ->where('Flag_Aktif', 'Y')
                ->get();

            foreach ($rawStandar as $std) {
                $cleanKey = (string)((float)$std->Nilai_Kriteria);
                $standarMap[$cleanKey] = $std->Keterangan_Kriteria;
            }
        }

        $ujiSampelQuery = DB::table('N_EMI_LIMS_Uji_Sampel as us')
            ->join('N_LIMS_PO_Sampel as ps', 'us.No_Po_Sampel', '=', 'ps.No_Sampel')
            ->leftJoin('N_EMI_LAB_Perhitungan as p', 'p.id', '=', 'us.Id_Perhitungan')
            ->select(
                'us.No_Faktur', 'us.No_Po_Sampel', 'ps.No_Po', 'ps.No_Batch', 'ps.No_Split_Po',
                'us.Id_Mesin', 'ps.Kode_Barang', 'us.Tanggal as Tanggal_Pengujian',
                'us.Hasil as Hasil_Akhir_Analisa', DB::raw("COALESCE(p.Hasil_Perhitungan, 2) AS Pembulatan")
            )
            ->where('p.Kode_Role', 'FLM')
            ->where('us.Id_Jenis_Analisa', $id_analisa)
            ->whereBetween('us.Tanggal', [$startDate, $endDate])
            ->where('us.Flag_Selesai', 'Y')
            ->whereNull('us.Status');

        if ($checkedIdMaster !== "all" && $checkedIdMaster) {
            $ujiSampelQuery->where('us.Id_Mesin', $checkedIdMaster);
        }

        $ujiSampelQuery->orderBy('us.Tanggal', 'asc')->orderBy('ps.No_Split_Po', 'asc');

        $ujiSampel = $ujiSampelQuery->get();

        if ($ujiSampel->isEmpty()) {
            return [];
        }

        $kodeBarangIds = $ujiSampel->pluck('Kode_Barang')->unique()->filter();
        $idMesinIds    = $ujiSampel->pluck('Id_Mesin')->unique()->filter();

        $namaBarangMap = DB::table('N_EMI_View_Barang')->whereIn('Kode_Barang', $kodeBarangIds)->pluck('Nama', 'Kode_Barang');
        $namaMesinMap = DB::table('EMI_Master_Mesin')->whereIn('Id_Master_Mesin', $idMesinIds)->pluck('Nama_Mesin', 'Id_Master_Mesin');

        $ujiSampel->each(function ($row) use ($namaBarangMap, $namaMesinMap) {
            $namaBarang = $namaBarangMap[$row->Kode_Barang] ?? '-';
            $namaMesin  = $namaMesinMap[$row->Id_Mesin] ?? '-';
            $row->Nama_Sampel_Format = "{$namaBarang}-{$row->No_Po_Sampel}-{$namaMesin}";
        });

        $allFakturIds = $ujiSampel->pluck('No_Faktur');
        $parameterRaw = collect();

        foreach ($allFakturIds->chunk(1000) as $chunkFakturs) {
            $batchData = DB::table('N_EMI_LIMS_Uji_Sampel_Detail as usd')
                ->join('EMI_Quality_Control as qc', 'qc.Id_QC_Formula', '=', 'usd.Id_Quality_Control')
                ->whereIn('usd.No_Faktur_Uji_Sample', $chunkFakturs)
                ->select('usd.No_Faktur_Uji_Sample', 'usd.Value_Parameter as Hasil_Analisa')
                ->get();
            
            $parameterRaw = $parameterRaw->merge($batchData);
        }
        
        $parameterRaw = $parameterRaw->groupBy('No_Faktur_Uji_Sample');

        $dataTerproses = $ujiSampel->groupBy('No_Faktur')->map(function ($grup) use ($parameterRaw, $isPerhitungan, $standarMap) {
            $itemPertama = $grup->first();
            $faktur = $itemPertama->No_Faktur;

            $hasilParameter = collect($parameterRaw->get($faktur) ?? [])
                ->map(function ($p) use ($standarMap, $isPerhitungan) {
                    $valRaw = $p->Hasil_Analisa;
                    $cleanVal = (string)((float)$valRaw);
                    if (!$isPerhitungan && isset($standarMap[$cleanVal])) {
                        return $standarMap[$cleanVal];
                    }
                    return round((float)$valRaw, 4);
                })->all();

            $hasilAkhir = $grup->map(function($item) use ($isPerhitungan, $standarMap) {
                if ($isPerhitungan) {
                    return number_format((float)$item->Hasil_Akhir_Analisa, $item->Pembulatan, '.', '');
                } else {
                    $cleanVal = (string)((float)$item->Hasil_Akhir_Analisa);
                    if (isset($standarMap[$cleanVal])) return $standarMap[$cleanVal];
                    return $item->Hasil_Akhir_Analisa;
                }
            })->all();

            return [
                'item'            => $itemPertama,
                'parameters'      => $hasilParameter,
                'results'         => $hasilAkhir,
                '_original_group' => $grup
            ];
        })->values();

        $rataRataGlobal = [];
        if ($isPerhitungan && $dataTerproses->isNotEmpty()) {
            $jumlahKolomRumus = count($getDataRumus);
            for ($i = 0; $i < $jumlahKolomRumus; $i++) {
                $kolomData = $dataTerproses->pluck('results.' . $i)->filter(fn($val) => is_numeric($val));
                $pembulatan = $dataTerproses->first()['_original_group'][$i]->Pembulatan ?? 2;

                if ($kolomData->isNotEmpty()) {
                    $rataRataGlobal[] = number_format($kolomData->avg(), $pembulatan, '.', '');
                } else {
                    $rataRataGlobal[] = '-';
                }
            }
        }

        $headings = ['NO', 'TANGGAL ANALISA', 'NO PO', 'NO SPLIT PO', 'NO BATCH', 'NAMA SAMPEL'];
        
        foreach ($getParameter as $p) {
            $headings[] = strtoupper($p->nama_parameter);
        }

        if ($isPerhitungan) {
            foreach ($getDataRumus as $r) {
                $headings[] = strtoupper($r->nama_kolom);
            }
        }

        $flatCollection = $dataTerproses->map(function ($data, $key) use ($isPerhitungan) {
            $item = $data['item'];
            $baris = [
                'no'          => $key + 1,
                'tanggal'     => \Carbon\Carbon::parse($item->Tanggal_Pengujian)->isoFormat('DD-MMMM-YYYY'),
                'no_po'       => $item->No_Po,
                'no_split_po' => $item->No_Split_Po,
                'no_batch'    => $item->No_Batch,
                'nama_sampel' => $item->Nama_Sampel_Format,
            ];

            $merged = array_merge($baris, $data['parameters']);
            if ($isPerhitungan) {
                $merged = array_merge($merged, $data['results']);
            }
            return $merged;
        });

        $limitPerFile = 1000;
        $chunks = $flatCollection->chunk($limitPerFile);
        $totalChunks = $chunks->count();

        $resultFiles = [];
        $namaAnalisaClean = preg_replace('/[^A-Za-z0-9\-]/', '_', $getNamaJenisAnalisa->Jenis_Analisa);
        $dateRange = \Carbon\Carbon::parse($startDate)->format('Ymd') . '-' . \Carbon\Carbon::parse($endDate)->format('Ymd');

        foreach ($chunks as $index => $chunk) {
            $partNumber = $index + 1;
            $suffix = ($totalChunks > 1) ? "_Part_{$partNumber}" : "";
            $fileName = "Rekap_Sampel_{$namaAnalisaClean}_{$dateRange}{$suffix}.pdf";

            $rataRataUntukFileIni = ($index === $totalChunks - 1) ? $rataRataGlobal : [];

            $resultFiles[] = [
                'fileName' => $fileName,
                'viewData' => [
                    'namaAnalisa'       => ucwords(strtolower($getNamaJenisAnalisa->Jenis_Analisa)) . ($totalChunks > 1 ? " (Part $partNumber)" : ""),
                    'periode'           => \Carbon\Carbon::parse($startDate)->format('d M Y') . ' s/d ' . \Carbon\Carbon::parse($endDate)->format('d M Y'),
                    'logoPath'          => $logoPath,
                    'headings'          => $headings,
                    'collection'        => $chunk,
                    'apakahPerhitungan' => $isPerhitungan,
                    'rataRata'          => $rataRataUntukFileIni,
                    'rumusCount'        => count($getDataRumus),
                ]
            ];
        }

        return $resultFiles;
    }

    public function downloadRekapSampelByPdfParticleSize(Request $request)
    {
        // 1. Validasi diubah untuk menerima SATU data, bukan array
        $validator = Validator::make($request->all(), [
            'analysis'        => 'required|string', // Diubah dari 'required|array' menjadi string
            'Flag_Perhitungan' => 'nullable|string',// Diubah dari array menjadi string
            'startDate'       => 'required|date',
            'endDate'         => 'required|date|after_or_equal:startDate',
            'Id_Master_Mesin' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Input tidak valid', 'errors' => $validator->errors()], 400);
        }

        $checkedIdMaster = $request->Id_Master_Mesin;

        if ($checkedIdMaster && $checkedIdMaster !== "all") {
            $decoded = Hashids::connection('custom')->decode($checkedIdMaster);
            $checkedIdMaster = $decoded[0] ?? null;
        }

        $logoBase64 = 'data:image/png;base64,' . base64_encode(File::get(public_path('assets/images/thumb-excel.png')));

        // 2. Tidak ada lagi perulangan, proses satu ID analisa secara langsung
        $pdfResult = $this->generatePdfDataForAnalysisParticleSize(
            $request->analysis, // Ambil langsung dari request
            $request->Flag_Perhitungan,
            $request->startDate,
            $request->endDate,
            $checkedIdMaster,
            $logoBase64
        );

        // 3. Cek jika data tidak ditemukan
        if (empty($pdfResult)) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Data Particle Size tidak ditemukan untuk kriteria yang dipilih.'
            ], 404);
        }

        // 4. Hapus semua logika ZIP, langsung generate dan download PDF
        $pdf = PDF::loadView($pdfResult['view'], $pdfResult['viewData'])->setPaper('a4', 'landscape');

        // Langsung kembalikan response download PDF
        return $pdf->download($pdfResult['fileName']);
    }

    public function downloadRekapSampelByExcelParticleSize(Request $request)
    {
        // 1. Validasi input (sama persis dengan fungsi PDF)
        $validator = Validator::make($request->all(), [
            'analysis'         => 'required|string',
            'Flag_Perhitungan' => 'nullable|string',
            'startDate'        => 'required|date',
            'endDate'          => 'required|date|after_or_equal:startDate',
            'Id_Master_Mesin'  => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Input tidak valid', 'errors' => $validator->errors()], 400);
        }

        try {
            $checkedIdMaster = $request->Id_Master_Mesin;

                if ($checkedIdMaster && $checkedIdMaster !== "all") {
                    $decoded = Hashids::connection('custom')->decode($checkedIdMaster);
                    $checkedIdMaster = $decoded[0] ?? null;
                }
                
                // 2. Memanggil fungsi yang sama untuk memproses data
                // Kita tidak perlu logo di sini karena Excel export menanganinya sendiri
                $processedData = $this->generatePdfDataForAnalysisParticleSize(
                    $request->analysis,
                    $request->Flag_Perhitungan,
                    $request->startDate,
                    $request->endDate,
                    $checkedIdMaster,
                    '' // logoBase64 tidak diperlukan, bisa diisi string kosong
                );

                // 3. Cek jika data tidak ditemukan
                if (empty($processedData) || empty($processedData['viewData']['reports'])) {
                    return response()->json([
                        'success' => false,
                        'status'  => 404,
                        'message' => 'Data Particle Size tidak ditemukan untuk kriteria yang dipilih.'
                    ], 404);
                }
                
                // 4. Membuat nama file dan memicu download Excel
                $startDate = Carbon::parse($request->startDate)->format('Ymd');
                $endDate = Carbon::parse($request->endDate)->format('Ymd');
                $fileName = "Rekap_Particle_Size_{$startDate}-{$endDate}.xlsx";
                
                // Menggunakan data 'reports' yang sudah ada di dalam 'viewData'
                $reports = $processedData['viewData']['reports'];

                return Excel::download(new ParticleSizeExport($reports), $fileName);
        }catch(\Exception $e){
            Log::channel('UjiSampelController')->error('Error: ' . $e->getMessage());
            return response()->json([
                    'success' => true,
                    'status' => 500,
                    'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }

    private function generatePdfDataForAnalysisParticleSize(
        string $hashedId,
        ?string $flagPerhitungan,
        string $startDate,
        string $endDate,
        $checkedIdMaster,
        string $logoBase64
        ): ?array {
        $decodedId = Hashids::connection('custom')->decode($hashedId);
        if (empty($decodedId)) return null;
        $id_analisa = $decodedId[0];

        $isPerhitungan = $flagPerhitungan === 'Y';

        $getNamaJenisAnalisa = DB::table('N_EMI_LAB_Jenis_Analisa')->where('id', $id_analisa)->first();
        if (!$getNamaJenisAnalisa) return null;

        $getParameter = DB::select("
            SELECT q.Keterangan as nama_parameter 
            FROM N_EMI_LAB_Binding_jenis_analisa b 
            JOIN EMI_Quality_Control q ON q.Id_QC_Formula = b.Id_Quality_Control 
            WHERE b.Id_Jenis_Analisa = ?", [$id_analisa]);
        
        $getDataRumus = $isPerhitungan 
            ? DB::select("SELECT Nama_Kolom as nama_kolom FROM N_EMI_LAB_Perhitungan WHERE Id_Jenis_Analisa = ?", [$id_analisa])
            : [];

        // Data uji sampel utama
        $ujiSampelQuery = DB::table('N_EMI_LAB_Uji_Sampel as us')
            ->join('N_EMI_LAB_PO_Sampel as ps', 'us.No_Po_Sampel', '=', 'ps.No_Sampel')
            ->leftJoin('N_EMI_LAB_Perhitungan as p', 'p.id', '=', 'us.Id_Perhitungan')
            ->select(
                'us.No_Faktur', 'us.No_Po_Sampel', 'ps.No_Po', 'ps.No_Batch', 'ps.No_Split_Po',
                'us.Id_Mesin', 'ps.Kode_Barang', 'us.Tanggal as Tanggal_Pengujian',
                'us.Hasil as Hasil_Akhir_Analisa', DB::raw("ISNULL(p.Hasil_Perhitungan, 2) AS Pembulatan")
            )
            ->where('us.Id_Jenis_Analisa', $id_analisa)
            ->whereBetween('us.Tanggal', [$startDate, $endDate])
            ->where('us.Flag_Selesai', 'Y')
            ->whereNull('us.Status');

        if ($checkedIdMaster !== "all") {
            $ujiSampelQuery->where('us.Id_Mesin', $checkedIdMaster);
        }

        $ujiSampel = $ujiSampelQuery->get();
        if ($ujiSampel->isEmpty()) {
            return null;
        }

        // Lookup barang & mesin (hilangkan N+1)
        $kodeBarangIds = $ujiSampel->pluck('Kode_Barang')->unique()->filter();
        $idMesinIds    = $ujiSampel->pluck('Id_Mesin')->unique()->filter();

        $namaBarangMap = DB::table('N_EMI_View_Barang')
            ->whereIn('Kode_Barang', $kodeBarangIds)
            ->pluck('Nama', 'Kode_Barang');

        $namaMesinMap = DB::table('EMI_Master_Mesin')
            ->whereIn('Id_Master_Mesin', $idMesinIds)
            ->pluck('Nama_Mesin', 'Id_Master_Mesin');

        $ujiSampel->each(function ($row) use ($namaBarangMap, $namaMesinMap) {
            $namaBarang = $namaBarangMap[$row->Kode_Barang] ?? '-';
            $namaMesin  = $namaMesinMap[$row->Id_Mesin] ?? '-';
            $row->Nama_Sampel_Format = "{$namaBarang}-{$row->No_Po_Sampel}-{$namaMesin}";
        });

        // Ambil detail parameter
        $parameterRaw = DB::table('N_EMI_LAB_Uji_Sampel_Detail as usd')
            ->join('EMI_Quality_Control as qc', 'qc.Id_QC_Formula', '=', 'usd.Id_Quality_Control')
            ->whereIn('usd.No_Faktur_Uji_Sample', $ujiSampel->pluck('No_Faktur'))
            ->get([
                'usd.No_Faktur_Uji_Sample',
                'usd.Value_Parameter as Hasil_Analisa',
                'qc.Keterangan as nama_parameter'
            ])
            ->groupBy('No_Faktur_Uji_Sample');

        /**
         * =========================
         *  KHUSUS PSZ (Particle Size)
         * =========================
         */
        if ($getNamaJenisAnalisa->Kode_Analisa === 'PSZ') {
            $meshParameterName = 'UKURAN MESH';

            $meshKeyMap = [
                4.0   => '4',
                3.35  => '3.35',
                2.0   => '2',
                1.0   => '1',
                850.0 => '850',
                600.0 => '600',
                250.0 => '250',
            ];

            $meshMap = $parameterRaw
                ->flatten()
                ->where('nama_parameter', $meshParameterName)
                ->pluck('Hasil_Analisa', 'No_Faktur_Uji_Sample');

            $ujiSampel->each(function ($sampel) use ($meshMap, $meshKeyMap) {
                $rawMesh = (float) $meshMap->get($sampel->No_Faktur);
                $sampel->Ukuran_Mesh = $meshKeyMap[$rawMesh] ?? (string) $rawMesh;
            });

            $groupedSamples = $ujiSampel->groupBy(function($item) {
                return $item->No_Split_Po . '|' . $item->Tanggal_Pengujian . '|' . $item->Id_Mesin;
            });

            $processedReports = [];
            foreach ($groupedSamples as $group) {
                $particleMap = $group->pluck('Hasil_Akhir_Analisa', 'Ukuran_Mesh');

                $calc = [];
                $calc['>4mm']        = (float) $particleMap->get('4', 0);
                $calc['>3.35mm']     = $calc['>4mm'] + (float) $particleMap->get('3.35', 0);
                $calc['<3.35mm']     = 100 - $calc['>3.35mm'];
                $calc['2-3.35mm']    = (float) $particleMap->get('2', 0);
                $calc['1-2mm']       = (float) $particleMap->get('1', 0);
                $calc['0.850-1mm']   = (float) $particleMap->get('850', 0);
                $calc['0.6-0.850mm'] = (float) $particleMap->get('600', 0);
                $calc['0.25-0.6mm']  = (float) $particleMap->get('250', 0);

                $sumIntermediate = $calc['2-3.35mm'] + $calc['1-2mm'] + $calc['0.850-1mm'] + $calc['0.6-0.850mm'] + $calc['0.25-0.6mm'];
                $calc['<0.25mm']  = $calc['<3.35mm'] - $sumIntermediate;

                $finalCalc = collect($calc)->map(fn($v) => number_format($v, 2, '.', ''))->all();

                $firstSample = $group->first();
                $namaProduk  = $namaBarangMap[$firstSample->Kode_Barang] ?? 'N/A';
                $namaMesin   = $namaMesinMap[$firstSample->Id_Mesin] ?? 'N/A';

                $processedReports[] = [
                    'info' => [
                        'nama_sampel'        => "$namaProduk - $namaMesin",
                        'tanggal_produksi_1' => Carbon::parse($firstSample->Tanggal_Pengujian)->isoFormat('D MMMM YYYY'),
                        'produk'             => "$namaProduk (" . $firstSample->No_Split_Po . ")",
                    ],
                    'values' => $finalCalc,
                ];
            }

            $fileName = 'Rekap_Particle_Size_' . Carbon::parse($startDate)->format('Ymd') . '-' . Carbon::parse($endDate)->format('Ymd') . '.pdf';

            return [
                'view'     => 'pdf.rekap-particlesize',
                'viewData' => ['reports' => $processedReports, 'logoBase64' => $logoBase64],
                'fileName' => $fileName,
                ];
        }

        return null;
    }


    private function formatTanggalIndoLengkap($tanggal)
    {
        $bulanIndo = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
            4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September',
            10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $carbon = \Carbon\Carbon::parse($tanggal);
        $day = $carbon->format('d');
        $month = (int)$carbon->format('m');
        $year = $carbon->format('Y');

        return "$day-{$bulanIndo[$month]}-$year";
    }

    public function downloadRekapSampelByPdfV2(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'analysis'         => 'required|array',
            'analysis.*'       => 'required|string',
            'Flag_Perhitungan' => 'required|array',
            'startDate'        => 'required|date',
            'endDate'          => 'required|date|after_or_equal:startDate',
            'Id_Master_Mesin'  => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Input tidak valid', 'errors' => $validator->errors()], 400);
        }

        try {
            $start = $request->startDate;
            $end = $request->endDate;
            $checkedIdMaster = $request->Id_Master_Mesin;

            if ($checkedIdMaster && $checkedIdMaster !== "all") {
                $decoded = \Vinkla\Hashids\Facades\Hashids::connection('custom')->decode($checkedIdMaster);
                $checkedIdMaster = $decoded[0] ?? null;
            }

            $decodedIds = [];
            $flagMap = [];

            foreach ($request->analysis as $index => $encoded) {
                $id = \Vinkla\Hashids\Facades\Hashids::connection('custom')->decode($encoded)[0] ?? null;
                if ($id) {
                    $decodedIds[] = $id;
                    $flagMap[$id] = $request->Flag_Perhitungan[$index] ?? null;
                }
            }

            if (empty($decodedIds)) {
                return response()->json(['message' => 'Data analisa tidak valid'], 400);
            }

            $jenisAnalisaAll = \Illuminate\Support\Facades\DB::table('N_EMI_LAB_Jenis_Analisa')
                ->where('Kode_Role', 'FLM')
                ->whereIn('id', $decodedIds)->get()->keyBy('id');

            $analisaHeaders = [];
            foreach ($decodedIds as $id) {
                if (isset($jenisAnalisaAll[$id])) {
                    $analisaHeaders[] = [
                        'id'        => $id,
                        'nama'      => $jenisAnalisaAll[$id]->Jenis_Analisa,
                        'kode'      => $jenisAnalisaAll[$id]->Kode_Analisa,
                        'flag_foto' => $jenisAnalisaAll[$id]->Flag_Foto ?? 'T',
                        'is_hitung' => $jenisAnalisaAll[$id]->Flag_Perhitungan == 'Y'
                    ];
                }
            }

            $standarRentangRaw = \Illuminate\Support\Facades\DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->where('Kode_Role', 'FLM')
                ->whereIn('Id_Jenis_Analisa', $decodedIds)
                ->where('Flag_Aktif', 'Y')
                ->get();

            $standarRentangMap = [];
            foreach ($standarRentangRaw as $row) {
                $keyVal = (string)((float)$row->Nilai_Kriteria);
                $standarRentangMap[$row->Id_Jenis_Analisa][$keyVal] = $row->Keterangan_Kriteria;
            }

            // [PERBAIKAN FATAL BUG 1 & 2]: Tambah No_Faktur dan pindahkan where p.Kode_Role ke dalam fungsi leftJoin
            $query = \Illuminate\Support\Facades\DB::table('N_EMI_LIMS_Uji_Sampel as us')
                ->join('N_LIMS_PO_Sampel as ps', 'us.No_Po_Sampel', '=', 'ps.No_Sampel')
                ->leftJoin('N_EMI_LAB_Perhitungan as p', function($join) {
                    $join->on('p.id', '=', 'us.Id_Perhitungan')
                        ->where('p.Kode_Role', 'FLM');
                })
                ->select(
                    'us.No_Faktur', // <-- Tadi ini hilang
                    'us.Id_Jenis_Analisa',
                    'ps.No_Po', 'ps.No_Split_Po', 'ps.Kode_Barang', 'us.Id_Mesin',
                    'us.Tanggal as Tanggal_Pengujian',
                    'us.Hasil',
                    \Illuminate\Support\Facades\DB::raw("COALESCE(p.Hasil_Perhitungan, 2) as Pembulatan")
                )
                ->whereIn('us.Id_Jenis_Analisa', $decodedIds)
                ->whereBetween('us.Tanggal', [$start, $end])
                ->where('us.Flag_Selesai', 'Y')
                ->whereNull('us.Status');

            if ($checkedIdMaster !== "all" && $checkedIdMaster) {
                $query->where('us.Id_Mesin', $checkedIdMaster);
            }

            $query->orderBy('us.Tanggal', 'asc')->orderBy('ps.No_Split_Po', 'asc');

            $rawData = $query->get();

            if ($rawData->isEmpty()) {
                return response()->json(['success' => false, 'status' => 404, 'message' => "Data tidak ditemukan"], 404);
            }

            $globalTotalNilai = array_fill(0, count($analisaHeaders), 0);
            $globalJumlahDataValid = array_fill(0, count($analisaHeaders), 0);

            $allKodeBarang = $rawData->pluck('Kode_Barang')->unique()->values();
            $allIdMesin = $rawData->pluck('Id_Mesin')->unique()->values();
            $allNoFaktur = $rawData->pluck('No_Faktur')->filter()->unique()->values(); // Ambil No Faktur

            $refBarang = \Illuminate\Support\Facades\DB::table('N_EMI_View_Barang')->whereIn('Kode_Barang', $allKodeBarang)->pluck('Nama', 'Kode_Barang');
            $refMesin = \Illuminate\Support\Facades\DB::table('EMI_Master_Mesin')->whereIn('Id_Master_Mesin', $allIdMesin)->pluck('Nama_Mesin', 'Id_Master_Mesin');
            
            // Ambil File Path gambar berdasarkan No Faktur
            $berkasMap = \Illuminate\Support\Facades\DB::table('N_EMI_LIMS_Berkas_Uji_Lab')
                ->whereIn('No_Faktur', $allNoFaktur)
                ->pluck('File_Path', 'No_Faktur');

            $groupedData = [];

            foreach ($rawData as $item) {
                $key = $item->No_Split_Po . '|' . $item->Tanggal_Pengujian . '|' . $item->Id_Mesin;

                if (!isset($groupedData[$key])) {
                    $namaBarang = $refBarang[$item->Kode_Barang] ?? 'N/A';
                    $namaMesin = $refMesin[$item->Id_Mesin] ?? 'N/A';
                    
                    $groupedData[$key] = [
                        'No' => 0,
                        'Nama_Sampel' => $namaBarang . '-' . $item->No_Split_Po . '-' . $namaMesin,
                        'Tanggal_Produksi' => \Carbon\Carbon::parse($item->Tanggal_Pengujian)->isoFormat('D MMMM YYYY'),
                        'Raw_Analisa' => [] 
                    ];
                }

                $idAnalisa = $item->Id_Jenis_Analisa;
                $isPerhitungan = ($flagMap[$idAnalisa] ?? null) === 'Y';
                $headerInfo = collect($analisaHeaders)->firstWhere('id', $idAnalisa);
                $isFoto = $headerInfo ? $headerInfo['flag_foto'] === 'Y' : false;

                // Terjemahkan Angka jadi Teks (cth: "Coklat Sempurna")
                if (!$isPerhitungan) {
                    $lookupKey = (string)((float)$item->Hasil);
                    if (isset($standarRentangMap[$idAnalisa][$lookupKey])) {
                        $textValue = $standarRentangMap[$idAnalisa][$lookupKey];
                    } else {
                        $textValue = $item->Hasil;
                    }
                } else {
                    $textValue = number_format((float)$item->Hasil, $item->Pembulatan, '.', '');
                }

                // Jika butuh Foto, kita Convert dari GCS ke Base64
                if ($isFoto) {
                    $filePath = $berkasMap[$item->No_Faktur] ?? null;
                    $fotoBase64 = null;

                    if ($filePath) {
                        try {
                            $fileContent = \Illuminate\Support\Facades\Storage::disk('gcs')->get($filePath);
                            if ($fileContent) {
                                $fotoBase64 = 'data:image/png;base64,' . base64_encode($fileContent);
                            }
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error('Gagal Tarik Foto PDF: ' . $e->getMessage());
                        }
                    }
                    
                    $groupedData[$key]['Raw_Analisa'][$idAnalisa] = [
                        'text' => $textValue,
                        'foto' => $fotoBase64
                    ];
                } else {
                    $groupedData[$key]['Raw_Analisa'][$idAnalisa] = $textValue;
                }
            }

            $finalCollection = [];
            $no = 1;
            
            foreach ($groupedData as $row) {
                $row['No'] = $no++;
                $analisaCells = [];

                foreach ($analisaHeaders as $idx => $header) {
                    $id = $header['id'];
                    $val = $row['Raw_Analisa'][$id] ?? null;
                    $isFoto = $header['flag_foto'] === 'Y';

                    $displayValue = null;
                    $fotoBase64 = null;

                    if ($val !== null) {
                        if ($isFoto && is_array($val)) {
                            $displayValue = $val['text'];
                            $fotoBase64 = $val['foto'];
                        } else {
                            $displayValue = $val;
                        }
                    }

                    if (!$isFoto && $displayValue !== null && is_numeric($displayValue)) {
                        $globalTotalNilai[$idx] += (float)$displayValue;
                        $globalJumlahDataValid[$idx]++;
                    }
                    
                    if ($displayValue === null) {
                        $displayValue = 'Tidak Ada Data';
                    } elseif (!$isFoto && is_numeric($displayValue) && (float)$displayValue == 0 && $header['kode'] === 'MBLG-STR') {
                        $displayValue = '-';
                    }
                    
                    $analisaCells[] = [
                        'nama' => $header['nama'],
                        'kode' => $header['kode'],
                        'nilai' => $displayValue,
                        'is_foto' => $isFoto,
                        'foto_base64' => $fotoBase64
                    ];
                }
                unset($row['Raw_Analisa']);
                $row['Analisa'] = $analisaCells;
                $finalCollection[] = $row;
            }

            $globalRataRataValues = [];
            foreach ($analisaHeaders as $idx => $header) {
                if ($header['flag_foto'] === 'Y' || $header['kode'] === 'MBLG-STR' || $globalJumlahDataValid[$idx] === 0) {
                    $globalRataRataValues[] = '-';
                } else {
                    $globalRataRataValues[] = number_format($globalTotalNilai[$idx] / $globalJumlahDataValid[$idx], 2, '.', '');
                }
            }

            $limitPerFile = 1000; 
            $chunks = array_chunk($finalCollection, $limitPerFile);
            $totalChunks = count($chunks);

            if ($totalChunks == 1) {
                return $this->generatePdfFromChunk(
                    $chunks[0], 
                    $analisaHeaders, 
                    $globalRataRataValues, 
                    'Laporan_Hasil_Analisa_' . now()->format('Ymd_His') . '.pdf', 
                    false
                );
            }

            $zipFileName = 'Laporan_Full_' . now()->format('Ymd_His') . '.zip';
            $zipFilePath = public_path('temp_pdf/' . $zipFileName);
            
            if (!\Illuminate\Support\Facades\File::exists(public_path('temp_pdf'))) {
                \Illuminate\Support\Facades\File::makeDirectory(public_path('temp_pdf'), 0755, true);
            }

            $zip = new \ZipArchive;
            if ($zip->open($zipFilePath, \ZipArchive::CREATE) === TRUE) {
                
                foreach ($chunks as $index => $chunk) {
                    $partNumber = $index + 1;
                    $pdfFileName = 'Laporan_Part_' . $partNumber . '.pdf';
                    $rataRataUntukFileIni = ($index === $totalChunks - 1) ? $globalRataRataValues : [];
                    $savedPdfPath = $this->generatePdfFromChunk(
                        $chunk, 
                        $analisaHeaders, 
                        $rataRataUntukFileIni, 
                        $pdfFileName, 
                        true
                    );
                    if ($savedPdfPath && file_exists($savedPdfPath)) {
                        $zip->addFile($savedPdfPath, $pdfFileName);
                    }
                }
                $zip->close();
            }
            $files = \Illuminate\Support\Facades\File::files(public_path('temp_pdf'));
            foreach ($files as $file) {
                if ($file->getExtension() == 'pdf') \Illuminate\Support\Facades\File::delete($file);
            }
            return response()->download($zipFilePath)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::channel('UjiSampelController')->error('Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => "Error: " . $e->getMessage()], 500);
        }
    }

    private function generatePdfFromChunk($collectionChunk, $headers, $rataRata, $filename, $saveToDisk = false)
    {
        $data = [
            'collection' => $collectionChunk,
            'headers' => $headers,
            'rataRata' => $rataRata, 
            'logoPath' => public_path('assets/images/thumb-excel.png'),
        ];

        $pdf = PDF::loadView('pdf.rekap-sampel-laporan', $data)
            ->setPaper('a4', 'landscape')
            ->setOption('margin-bottom', 10)
            ->setOption('footer-center', 'Halaman [page] dari [toPage]')
            ->setOption('enable-local-file-access', true);

        if ($saveToDisk) {
            $path = public_path('temp_pdf/' . $filename);
            $pdf->save($path);
            return $path;
        } else {
            return $pdf->download($filename);
        }
    }

    public function downloadRekapSampelByExcellV2(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'analysis'         => 'required|array',
            'analysis.*'       => 'required|string',
            'Flag_Perhitungan' => 'required|array',
            'startDate'        => 'required|date',
            'endDate'          => 'required|date|after_or_equal:startDate',
            'Id_Master_Mesin'  => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Input tidak valid', 'errors' => $validator->errors()], 400);
        }

        try {
            $start = $request->startDate;
            $end = $request->endDate;
            $checkedIdMaster = $request->Id_Master_Mesin;

            if ($checkedIdMaster && $checkedIdMaster !== "all") {
                $decoded = \Vinkla\Hashids\Facades\Hashids::connection('custom')->decode($checkedIdMaster);
                $checkedIdMaster = $decoded[0] ?? null;
            }

            $decodedIds = [];
            $flagMap = [];

            foreach ($request->analysis as $index => $encoded) {
                $id = \Vinkla\Hashids\Facades\Hashids::connection('custom')->decode($encoded)[0] ?? null;
                if ($id) {
                    $decodedIds[] = $id;
                    $flagMap[$id] = $request->Flag_Perhitungan[$index] ?? null;
                }
            }

            if (empty($decodedIds)) {
                return response()->json(['success' => false, 'status' => 404, 'message' => "Tidak ada analisa yang dipilih"], 404);
            }

            $jenisAnalisaAll = \Illuminate\Support\Facades\DB::table('N_EMI_LAB_Jenis_Analisa')
                ->where('Kode_Role', 'FLM')
                ->whereIn('id', $decodedIds)
                ->get()
                ->keyBy('id');

            $analisaHeaders = [];
            foreach ($decodedIds as $id) {
                if (isset($jenisAnalisaAll[$id])) {
                    $analisaHeaders[] = [
                        'id'        => $id,
                        'nama'      => $jenisAnalisaAll[$id]->Jenis_Analisa,
                        'kode'      => $jenisAnalisaAll[$id]->Kode_Analisa,
                        'flag_foto' => $jenisAnalisaAll[$id]->Flag_Foto ?? 'T'
                    ];
                }
            }

            $standarRentangRaw = \Illuminate\Support\Facades\DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->whereIn('Id_Jenis_Analisa', $decodedIds)
                ->where('Kode_Role', 'FLM')
                ->where('Flag_Aktif', 'Y')
                ->get();

            $standarRentangMap = [];
            foreach ($standarRentangRaw as $row) {
                $valKey = (string)((float)$row->Nilai_Kriteria); 
                $standarRentangMap[$row->Id_Jenis_Analisa][$valKey] = $row->Keterangan_Kriteria;
            }

            $query = \Illuminate\Support\Facades\DB::table('N_EMI_LIMS_Uji_Sampel as us')
                ->join('N_LIMS_PO_Sampel as ps', 'us.No_Po_Sampel', '=', 'ps.No_Sampel')
                ->leftJoin('N_EMI_LAB_Perhitungan as p', function ($join) {
                    $join->on('p.id', '=', 'us.Id_Perhitungan')
                        ->where('p.Kode_Role', 'FLM');
                })
                ->select(
                    'us.Id_Jenis_Analisa',
                    'us.No_Faktur',
                    'ps.No_Po', 'ps.No_Split_Po', 'ps.Kode_Barang', 'us.Id_Mesin',
                    'us.Tanggal as Tanggal_Pengujian',
                    'us.Hasil',
                    \Illuminate\Support\Facades\DB::raw("ISNULL(p.Hasil_Perhitungan, 2) as Pembulatan")
                )
                ->whereIn('us.Id_Jenis_Analisa', $decodedIds)
                ->whereBetween('us.Tanggal', [$start, $end])
                ->where('us.Flag_Selesai', 'Y')
                ->whereNull('us.Status');

            if ($checkedIdMaster !== "all" && $checkedIdMaster) {
                $query->where('us.Id_Mesin', $checkedIdMaster);
            }

            $query->orderBy('us.Tanggal', 'asc')->orderBy('ps.No_Split_Po', 'asc');

            $rawData = $query->get();

            if ($rawData->isEmpty()) {
                return response()->json(['success' => false, 'status' => 404, 'message' => "Data tidak ditemukan"], 404);
            }

            $allKodeBarang = $rawData->pluck('Kode_Barang')->unique()->values();
            $allIdMesin    = $rawData->pluck('Id_Mesin')->unique()->values();
            $allNoFaktur   = $rawData->pluck('No_Faktur')->filter()->unique()->values();

            $refBarang = \Illuminate\Support\Facades\DB::table('N_EMI_View_Barang')
                ->whereIn('Kode_Barang', $allKodeBarang)
                ->pluck('Nama', 'Kode_Barang');

            $refMesin = \Illuminate\Support\Facades\DB::table('EMI_Master_Mesin')
                ->whereIn('Id_Master_Mesin', $allIdMesin)
                ->pluck('Nama_Mesin', 'Id_Master_Mesin');

            $berkasMap = \Illuminate\Support\Facades\DB::table('N_EMI_LIMS_Berkas_Uji_Lab')
                ->whereIn('No_Faktur', $allNoFaktur)
                ->pluck('File_Path', 'No_Faktur');

            $groupedData = [];

            foreach ($rawData as $item) {
                $key = $item->No_Split_Po . '|' . $item->Tanggal_Pengujian . '|' . $item->Id_Mesin;
                
                if (!isset($groupedData[$key])) {
                    $namaBarang = $refBarang[$item->Kode_Barang] ?? 'N/A';
                    $namaMesin  = $refMesin[$item->Id_Mesin] ?? 'N/A';
                    
                    $groupedData[$key] = [
                        'No'          => 0, 
                        'Nama_Sampel' => $namaBarang . '-' . $item->No_Split_Po . '-' . $namaMesin,
                        'Tanggal'     => \Carbon\Carbon::parse($item->Tanggal_Pengujian)->isoFormat('D MMMM YYYY'),
                        'Raw_Analisa' => []
                    ];
                }

                $idAnalisa = $item->Id_Jenis_Analisa;
                $isPerhitungan = ($flagMap[$idAnalisa] ?? null) === 'Y';
                $headerInfo = collect($analisaHeaders)->firstWhere('id', $idAnalisa);
                $isFoto = $headerInfo ? $headerInfo['flag_foto'] === 'Y' : false;

                if (!$isPerhitungan) {
                    $lookupKey = (string)((float)$item->Hasil);
                    if (isset($standarRentangMap[$idAnalisa][$lookupKey])) {
                        $textValue = $standarRentangMap[$idAnalisa][$lookupKey];
                    } else {
                        $textValue = $item->Hasil;
                    }
                } else {
                    $textValue = number_format((float)$item->Hasil, $item->Pembulatan, '.', '');
                }

                if ($isFoto) {
                    $filePath = $berkasMap[$item->No_Faktur] ?? null;
                    $groupedData[$key]['Raw_Analisa'][$idAnalisa] = [
                        'text' => $textValue,
                        'path' => $filePath
                    ];
                } else {
                    $groupedData[$key]['Raw_Analisa'][$idAnalisa] = $textValue;
                }
            }

            $finalCollection = [];
            $imagesData = [];
            $no = 1;

            $jumlahKolomAnalisa = count($analisaHeaders);
            $totalNilai      = array_fill(0, $jumlahKolomAnalisa, 0);
            $jumlahDataValid = array_fill(0, $jumlahKolomAnalisa, 0);

            foreach ($groupedData as $row) {
                $row['No'] = $no;
                $analisaCells = [];

                foreach ($analisaHeaders as $idx => $header) {
                    $id = $header['id'];
                    $val = $row['Raw_Analisa'][$id] ?? null;
                    $isFoto = $header['flag_foto'] === 'Y';

                    $displayValue = null;
                    $path = null;

                    if ($val !== null) {
                        if ($isFoto && is_array($val)) {
                            $displayValue = $val['text'];
                            $path = $val['path'];
                        } else {
                            $displayValue = $val;
                        }
                    }

                    if ($displayValue === null) {
                        $displayValue = 'Tidak Ada Data';
                    }

                    if (!$isFoto && $displayValue !== 'Tidak Ada Data' && is_numeric($displayValue)) {
                        $totalNilai[$idx] += (float)$displayValue;
                        $jumlahDataValid[$idx]++;
                    } elseif (!$isFoto && is_numeric($displayValue) && (float)$displayValue == 0 && $header['kode'] === 'MBLG-STR') {
                        $displayValue = '-';
                    }

                    $analisaCells[] = $displayValue;

                    if ($isFoto && $path) {
                        $currentRow = 4 + $row['No']; 
                        // Disesuaikan dari 5 ke 4 karena kolom bergeser 1 ke kiri
                        $currentCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(4 + $idx);
                        
                        $imagesData[] = [
                            'row'  => $currentRow,
                            'col'  => $currentCol,
                            'path' => $path
                        ];
                    }
                }
                
                unset($row['Raw_Analisa']); 
                $flatRow = array_merge(array_values($row), $analisaCells);
                $finalCollection[] = $flatRow;
                $no++;
            }

            $rataRata = [];
            for ($i = 0; $i < $jumlahKolomAnalisa; $i++) {
                if ($analisaHeaders[$i]['flag_foto'] === 'Y' || $analisaHeaders[$i]['kode'] === 'MBLG-STR' || $jumlahDataValid[$i] == 0) {
                    $rataRata[$i] = '-';
                } else {
                    $rataRata[$i] = number_format($totalNilai[$i] / $jumlahDataValid[$i], 2, '.', '');
                }
            }

            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\RekapSampelExcelExportV2(
                    collect($finalCollection),
                    $analisaHeaders,
                    $rataRata,
                    $imagesData
                ),
                'Laporan_Hasil_Analisa_' . now()->format('Ymd_His') . '.xlsx'
            );
        } catch(\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan Internal Server."
            ], 500); 
        }
    }

    public function downloadRekapSampelByExcellV2Prafinal(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'No_Po_Sampel' => 'required|string',
            'format'       => 'required|in:excel,pdf',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Input tidak valid', 'errors' => $validator->errors()], 400);
        }

        try {
            $noPoSampel = $request->No_Po_Sampel;

            $rawData = \Illuminate\Support\Facades\DB::table('N_EMI_LIMS_Uji_Sampel as us')
                ->join('N_LIMS_PO_Sampel as ps', 'us.No_Po_Sampel', '=', 'ps.No_Sampel')
                ->leftJoin('N_EMI_LAB_Perhitungan as p', 'p.id', '=', 'us.Id_Perhitungan')
                ->select(
                    'us.No_Faktur', 
                    'us.Id_Jenis_Analisa',
                    'ps.No_Po', 'ps.No_Split_Po', 'ps.Kode_Barang', 'us.Id_Mesin',
                    'us.Tanggal as Tanggal_Pengujian',
                    'us.Hasil',
                    \Illuminate\Support\Facades\DB::raw("ISNULL(p.Hasil_Perhitungan, 2) as Pembulatan")
                )
                ->where('us.No_Po_Sampel', $noPoSampel)
                ->where('us.Flag_Selesai', 'Y')
                ->whereNull('us.Status')
                ->get();

            if ($rawData->isEmpty()) {
                return response()->json(['success' => false, 'status' => 404, 'message' => "Data analisa tidak ditemukan untuk sampel ini"], 404);
            }

            $decodedIds = $rawData->pluck('Id_Jenis_Analisa')->unique()->values()->toArray();

            $jenisAnalisaAll = \Illuminate\Support\Facades\DB::table('N_EMI_LAB_Jenis_Analisa')
                ->whereIn('id', $decodedIds)
                ->get()
                ->keyBy('id');

            $analisaHeaders = [];
            foreach ($decodedIds as $id) {
                if (isset($jenisAnalisaAll[$id])) {
                    $analisaHeaders[] = [
                        'id'        => $id,
                        'nama'      => $jenisAnalisaAll[$id]->Jenis_Analisa,
                        'kode'      => $jenisAnalisaAll[$id]->Kode_Analisa,
                        'flag_foto' => $jenisAnalisaAll[$id]->Flag_Foto ?? 'T',
                        'is_hitung' => $jenisAnalisaAll[$id]->Flag_Perhitungan == 'Y'
                    ];
                }
            }

            $berkasMap = \Illuminate\Support\Facades\DB::table('N_EMI_LIMS_Berkas_Uji_Lab')
                ->where('No_Sampel', $noPoSampel)
                ->pluck('File_Path', 'No_Faktur');

            $standarRentangRaw = \Illuminate\Support\Facades\DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->whereIn('Id_Jenis_Analisa', $decodedIds)
                ->where('Flag_Aktif', 'Y')
                ->get();

            $standarRentangMap = [];
            foreach ($standarRentangRaw as $row) {
                $valKey = (string)((float)$row->Nilai_Kriteria); 
                $standarRentangMap[$row->Id_Jenis_Analisa][$valKey] = $row->Keterangan_Kriteria;
            }

            $refBarang = \Illuminate\Support\Facades\DB::table('N_EMI_View_Barang')
                ->whereIn('Kode_Barang', $rawData->pluck('Kode_Barang')->unique())
                ->pluck('Nama', 'Kode_Barang');

            $refMesin = \Illuminate\Support\Facades\DB::table('EMI_Master_Mesin')
                ->whereIn('Id_Master_Mesin', $rawData->pluck('Id_Mesin')->unique())
                ->pluck('Nama_Mesin', 'Id_Master_Mesin');

            $groupedData = [];
            foreach ($rawData as $item) {
                $key = $item->No_Split_Po . '|' . $item->Tanggal_Pengujian . '|' . $item->Id_Mesin;
                
                if (!isset($groupedData[$key])) {
                    $groupedData[$key] = [
                        'No'               => 0, 
                        'Nama_Sampel'      => ($refBarang[$item->Kode_Barang] ?? 'N/A') . '-' . $item->No_Split_Po . '-' . ($refMesin[$item->Id_Mesin] ?? 'N/A'),
                        'Tanggal'          => \Carbon\Carbon::parse($item->Tanggal_Pengujian)->isoFormat('D MMMM YYYY'),
                        'Raw_Analisa'      => []
                    ];
                }

                $idAnalisa = $item->Id_Jenis_Analisa;
                $headerInfo = collect($analisaHeaders)->firstWhere('id', $idAnalisa);
                
                $isPerhitungan = $headerInfo ? $headerInfo['is_hitung'] : false;
                if (!$isPerhitungan) {
                    $lookupKey = (string)((float)$item->Hasil);
                    $textValue = $standarRentangMap[$idAnalisa][$lookupKey] ?? $item->Hasil;
                } else {
                    $textValue = number_format((float)$item->Hasil, $item->Pembulatan, '.', '');
                }

                if ($headerInfo && $headerInfo['flag_foto'] === 'Y') {
                    $filePath = $berkasMap[$item->No_Faktur] ?? null;
                    $finalValue = [
                        'text' => $textValue,
                        'path' => $filePath
                    ];
                } else {
                    $finalValue = $textValue;
                }

                $groupedData[$key]['Raw_Analisa'][$idAnalisa] = $finalValue;
            }

            $finalCollection = [];
            $imagesData = [];
            $no = 1;

            $jumlahKolomAnalisa = count($analisaHeaders);
            $totalNilai      = array_fill(0, $jumlahKolomAnalisa, 0);
            $jumlahDataValid = array_fill(0, $jumlahKolomAnalisa, 0);

            foreach ($groupedData as $row) {
                $row['No'] = $no;
                $analisaCells = [];

                foreach ($analisaHeaders as $idx => $header) {
                    $id = $header['id'];
                    $val = $row['Raw_Analisa'][$id] ?? '-';

                    if ($header['flag_foto'] === 'Y') {
                        $textResult = is_array($val) ? $val['text'] : $val;
                        $filePath = is_array($val) ? $val['path'] : null;

                        $analisaCells[] = $textResult; 

                        if ($filePath) {
                            $currentRow = 9 + $row['No']; 
                            $currentCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(4 + $idx);
                            
                            $imagesData[] = [
                                'row'  => $currentRow,
                                'col'  => $currentCol,
                                'path' => $filePath 
                            ];
                        }
                    } else {
                        $analisaCells[] = $val;
                        if (is_numeric($val) && $val !== '-') {
                            $totalNilai[$idx] += (float)$val;
                            $jumlahDataValid[$idx]++;
                        }
                    }
                }
                
                unset($row['Raw_Analisa']); 
                $flatRow = array_merge(array_values($row), $analisaCells);
                $finalCollection[] = $flatRow;
                $no++;
            }

            $rataRata = [];
            foreach ($analisaHeaders as $idx => $header) {
                if ($header['flag_foto'] === 'Y') {
                    $rataRata[$idx] = '-';
                } else {
                    $rataRata[$idx] = ($jumlahDataValid[$idx] > 0) ? number_format($totalNilai[$idx] / $jumlahDataValid[$idx], 2, '.', '') : '-';
                }
            }

            $tanggalCetak = \Carbon\Carbon::now()->isoFormat('D MMMM YYYY HH:mm');

            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\ReportSampelExcellExportPrafinalisaasi(
                    collect($finalCollection),
                    $analisaHeaders,
                    $rataRata,
                    $imagesData,
                    $tanggalCetak
                ),
                'Rekap_Prafinalisasi_Sampel_' . $noPoSampel . '_' . now()->format('Ymd_His') . '.xlsx'
            );

        } catch(\Exception $e){
            \Illuminate\Support\Facades\Log::error('Error Export Rekap Sampel: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan Internal Server."
            ], 500); 
        }
    }

    public function resampelingAnalisa(Request $request)
    {
         try {
            $id_jenis_analisa = Hashids::connection('custom')->decode($request->Id_Jenis_Analisa)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Format Kunci tidak valid.'
            ], 400);
        }

        $pengguna = Auth::user();

        DB::beginTransaction();

        $getTahapan = DB::table("N_EMI_LIMS_Uji_Sampel")
                    ->where('No_Po_Sampel', $request->No_Po_Sampel)
                    ->where('No_Fak_Sub_Po', $request->No_Sampel_Resampling_Origin)
                    ->first();

        $tahapKe = $getTahapan->Tahapan_Ke ?? 1;

        try {
            $payloadResampling = [
                'No_Po_Sampel' => $request->No_Po_Sampel,
                'No_Sampel_Resampling_Origin' => $request->No_Sampel_Resampling_Origin,
                'No_Sampel_Resampling' => $request->No_Sampel_Resampling,
                'Tahapan_Ke' => $tahapKe + 1,
                'Tanggal' => date('Y-m-d'),
                'Jam' => date('H:i:s'),
                'Id_Jenis_Analisa' => $id_jenis_analisa,
                'Id_User' => $pengguna->UserId,
                'Keterangan' => 'Nomor Sampel ' . $request->No_Sampel_Resampling_Origin . ' melakukan reanalisa dengan sampel ' . $request->No_Sampel_Resampling,
            ];

            
            DB::table('N_EMI_LIMS_Uji_Sampel_Resampling_Log')->insert($payloadResampling);
            
            DB::table("N_EMI_LIMS_Uji_Sampel")
                    ->where('No_Po_Sampel', $request->No_Po_Sampel)
                    ->where('No_Fak_Sub_Po', $request->No_Sampel_Resampling_Origin)
                    ->where('Id_Jenis_Analisa', $id_jenis_analisa)
                    ->update([
                        'Status_Keputusan_Sampel' => 'tolak',
                        'Flag_Resampling' => 'Y'
                    ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Data resampling berhasil disimpan.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel("FormulatorTrialSampelController")->error($e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => [
                    'error' => $e->getMessage()
                ]
            ], 500);
        }
    }
    
    public function resampelingAnalisaSingle(Request $request)
    {
        try {
            $id_jenis_analisa = Hashids::connection('custom')->decode($request->Id_Jenis_Analisa)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Format ID tidak valid'
            ], 400);
        }

        $user = Auth::user();

        DB::beginTransaction();

        try {
            $data = DB::table('N_EMI_LIMS_Uji_Sampel')
                ->where('No_Po_Sampel', $request->No_Po_Sampel)
                ->where('No_Fak_Sub_Po', $request->No_Fak_Sub_Po)
                ->where('Id_Jenis_Analisa', $id_jenis_analisa)
                ->whereNull('Status')
                ->whereNull('Flag_Final')
                ->whereNull('Flag_Selesai')
                ->first();

            if (!$data) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Data sampel tidak ditemukan atau sudah selesai.'
                ], 404);
            }

            $lastLogStage = DB::table('N_EMI_LIMS_Uji_Sampel_Resampling_Log')
                ->where('No_Po_Sampel', $request->No_Po_Sampel)
                ->where('Id_Jenis_Analisa', $id_jenis_analisa)
                ->max('Tahapan_Ke');

            $currentMasterStage = $data->Tahapan_Ke;
            $maxExistingStage = $lastLogStage ? max($currentMasterStage, $lastLogStage) : $currentMasterStage;
            $nextStage = $maxExistingStage + 1;

            DB::table('N_EMI_LIMS_Uji_Sampel')
                ->where('No_Po_Sampel', $request->No_Po_Sampel)
                ->where('No_Fak_Sub_Po', $request->No_Fak_Sub_Po)
                ->where('Id_Jenis_Analisa', $id_jenis_analisa)
                ->whereNull('Status')
                ->whereNull('Flag_Final')
                ->whereNull('Flag_Selesai')
                ->update([
                    'Flag_Resampling' => 'Y',
                    'Status_Keputusan_Sampel' => 'tolak'
                ]);

            DB::table('N_EMI_LIMS_Uji_Sampel_Resampling_Log')->insert([
                'No_Po_Sampel' => $request->No_Po_Sampel,
                'Tahapan_Ke' => $nextStage,
                'No_Sampel_Resampling_Origin' => $request->No_Sampel,
                'No_Sampel_Resampling' => $request->No_Sampel,
                'Tanggal' => now()->toDateString(),
                'Jam' => now()->toTimeString(),
                'Id_User' => $user->UserId,
                'Id_Jenis_Analisa' => $id_jenis_analisa,
                'Keterangan' => 'Reanalisa tanpa multi QR (sampel sama)'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Reanalisa single berhasil'
            ]);

        } catch(\Exception $e){
            DB::rollBack();
            Log::channel("UjiSampelController")->error("ERROR FUNCTION resampelingAnalisaSingle: ". $e->getMessage());
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => "Terjadi Kesalahan. Hubungi Admin"
            ], 500);
        }
    }

    public function viewResamplingHomes()
    {
        return inertia("vue/dashboard/lab/formulator/resampling-analisa/HomeResamplingAnalisa");
    }

    public function ViewUjiResampling($no_sampel, $no_fak_sub_sampel, $id_jenis_analisa, $no_resampling)
    {
        return inertia("vue/dashboard/lab/formulator/resampling-analisa/UjiResampling", [
            'No_Sampel' => $no_sampel,
            'No_Fak_Sub_Sampel' => $no_fak_sub_sampel,
            'Id_Jenis_Analisa' => $id_jenis_analisa,
            'No_Resampling' => $no_resampling
        ]);
    }

    public function getDataResamplingCurrent(Request $request)
    {
        try {
            $limit = (int) $request->query('limit', 10);
            $page = (int) $request->query('page', 1);
            $offset = ($page - 1) * $limit;

            $search = $request->query('search');
            $status = $request->query('status'); 
            $tanggalAwal = $request->query('tanggal_awal');
            $tanggalAkhir = $request->query('tanggal_akhir');
            $jenisAnalisa = $request->query('jenis_analisa'); // id jenis analisa

            $query = DB::table("N_EMI_LIMS_Uji_Sampel_Resampling_Log as resampling")
                ->join('N_LIMS_PO_Sampel as po', 'resampling.No_Po_Sampel', '=', 'po.No_Sampel')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'resampling.Id_Jenis_Analisa', '=', 'ja.id')
                ->select(
                    'resampling.*',
                    'ja.Jenis_Analisa',
                    'po.No_Po',
                    'po.No_Split_Po',
                    'po.No_Batch'
                );

            // 🔍 Search filter
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('po.No_Po', 'LIKE', "%$search%")
                    ->orWhere('po.No_Split_Po', 'LIKE', "%$search%")
                    ->orWhere('resampling.No_Sampel_Resampling_Origin', 'LIKE', "%$search%")
                    ->orWhere('resampling.No_Sampel_Resampling', 'LIKE', "%$search%")
                    ->orWhere('ja.Jenis_Analisa', 'LIKE', "%$search%");
                });
            }

            // 🎯 Filter status (selesai / belum)
            if ($status === 'Y') {
                $query->where('resampling.Flag_Selesai_Resampling', '=', 'Y');
            } elseif ($status === 'N') {
                $query->whereNull('resampling.Flag_Selesai_Resampling');
            }

            // 📅 Filter tanggal range
            if (!empty($tanggalAwal) && !empty($tanggalAkhir)) {
                $query->whereBetween('resampling.Tanggal', [$tanggalAwal, $tanggalAkhir]);
            }

            // 🧪 Filter Jenis Analisa
            if (!empty($jenisAnalisa)) {
                $query->where('resampling.Id_Jenis_Analisa', $jenisAnalisa);
            }

            // Hitung total
            $total = $query->count();

            // Urutkan: null Flag dulu, lalu terbaru berdasarkan tanggal+jam
            $getData = $query
                ->orderByRaw("CASE WHEN resampling.Flag_Selesai_Resampling IS NULL THEN 0 ELSE 1 END")
                ->orderByRaw("CONCAT(resampling.Tanggal, ' ', resampling.Jam) DESC")
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

            $encodedData = $getData->map(function ($item) {
                $item->Id_Resampling = Hashids::connection('custom')->encode($item->Id_Resampling);
                $item->Id_Jenis_Analisa = Hashids::connection('custom')->encode($item->Id_Jenis_Analisa);
                return $item;
            });

            return response()->json([
                'success' => true,
                'status' => 200,
                'result' => $encodedData,
                'page' => $page,
                'total_page' => ceil($total / $limit),
                'total_data' => $total
            ], 200);

        } catch (\Exception $e) {
            Log::channel('ResamplingController')->error('Error: ' . $e->getMessage());
            return response()->json([
                    'success' => true,
                    'status' => 500,
                    'message' => "Terjadi Kesalahan",
            ], 500); 
        }
    }

    public function getDetailSampelResampling($No_Sampel_Resampling_Origin, $No_Sampel_Resampling, $Id_Jenis_Analisa) 
    {
        try {
            $Id_Jenis_Analisa = Hashids::connection('custom')->decode($Id_Jenis_Analisa)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Jenis Analisa tidak valid.'
            ], 400);
        }

        $resampling = DB::table('N_EMI_LIMS_Uji_Sampel_Resampling_Log')
        ->select('N_EMI_LIMS_Uji_Sampel_Resampling_Log.*', 'N_LIMS_PO_Sampel.Id_Mesin', 'N_LIMS_PO_Sampel.Kode_Barang', 'N_LIMS_PO_Sampel.No_Po', 'N_LIMS_PO_Sampel.No_Split_Po', 'N_LIMS_PO_Sampel.No_Batch', 'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa')
        ->join('N_LIMS_PO_Sampel', 'N_EMI_LIMS_Uji_Sampel_Resampling_Log.No_Po_Sampel', '=', 'N_LIMS_PO_Sampel.No_Sampel')
        ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LIMS_Uji_Sampel_Resampling_Log.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
        ->where('No_Sampel_Resampling_Origin', $No_Sampel_Resampling_Origin)
        ->where('No_Sampel_Resampling', $No_Sampel_Resampling)
        ->where('Id_Jenis_Analisa', $Id_Jenis_Analisa)
        ->first();

         if ($resampling) {
            // Ubah ke array agar bisa dimodifikasi
            $resampling = (array) $resampling;

            // Encode ID ke hash sebelum dikembalikan
            if (isset($resampling['Id_Jenis_Analisa'])) {
                $resampling['Id_Jenis_Analisa'] = Hashids::connection('custom')->encode($resampling['Id_Jenis_Analisa']);
            }

            if (isset($resampling['Id_Resampling'])) {
                $resampling['Id_Resampling'] = Hashids::connection('custom')->encode($resampling['Id_Resampling']);
            }
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Data Ditemukan !',
            'result' => $resampling
        ]);
    }
}
