<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use MathParser\StdMathParser;
use MathParser\Interpreting\Evaluator;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Exports\RekapSampelExport;
use App\Exports\ParticleSizeExport;
use App\Exports\DaftarAnalisaKurangExport;
use App\Helpers\ResponseHelper;
use App\Jobs\ExportRekapSampelJob;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator; 
use Illuminate\Support\Facades\File;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use ZipArchive;


class UjiSampelController extends Controller
{
    protected function calculateFormulaServerSide($formula, $parameterValues, $decimalPlaces = 2)
    {
        try {
            $processedFormula = $formula;
            $parameterValues = collect($parameterValues);

            // --- Langkah 1 & 2: Hitung dan ganti semua fungsi kustom ---
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

            // --- Langkah 3: Ganti placeholder parameter yang tersisa ---
            preg_match_all('/\[([^\]]+)\]/', $processedFormula, $paramMatches);
            foreach ($paramMatches[1] ?? [] as $id) {
                $value = $parameterValues->get($id, 0);
                $processedFormula = str_replace("[$id]", (string)$value, $processedFormula);
            }

            // --- Langkah 4: Evaluasi ekspresi matematika akhir (INI BAGIAN YANG DIPERBAIKI) ---
            $parser = new StdMathParser();
            $evaluator = new Evaluator();

            // Menggunakan cara yang benar sesuai library Anda
            $AST = $parser->parse($processedFormula);
            $finalResult = $AST->accept($evaluator);

            // Format hasil akhir
            return number_format((float)$finalResult, $decimalPlaces, '.', '');

        } catch (\Throwable $e) {
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
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
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return number_format(0, $decimalPlaces, '.', '');
        }
    }
    private function safeFloat($value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
    public function index()
    {
        return inertia('vue/lab/HomeLab')->withViewData([
             'layout' => 'layouts.master2',
         ]) ;
    }
    public function viewConfirmedAnalisis()
    {
        return inertia('vue/dashboard/lab/ConfirmedUjiAnalisav2');
    }

    public function exportDaftarAnalisaKurang(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');
        $type      = $request->input('type'); // 'produksi' | 'trial' | null = keduanya

        if (!$startDate || !$endDate) {
            return response()->json([
                'success' => false,
                'status'  => 422,
                'message' => 'Parameter start_date dan end_date wajib diisi.'
            ], 422);
        }

        $typeLabel = match ($type) {
            'trial'    => 'Trial_Produksi',
            'produksi' => 'Produksi',
            default    => 'Semua',
        };

        $fileName = 'Laporan_Analisa_Kurang_' . $typeLabel . '_' . $startDate . '_sd_' . $endDate . '.xlsx';

        return Excel::download(
            new DaftarAnalisaKurangExport($startDate, $endDate, $type),
            $fileName
        );
    }
    public function viewInformasiMultiQr($no_sub_sampel, $id_jenis_analisa)
    {
        return inertia('vue/dashboard/lab/page-confirmedv2/confirmedv2pcs', [
            'No_Sub_Sampel' => $no_sub_sampel,
            'id_jenis_analisa' => $id_jenis_analisa
        ]);
    }
    public function viewInformasiJenisAnalisaMultiQr($no_sampel, $no_fak_sub_sampel, $id_jenis_analisa)
    {
        return inertia('vue/dashboard/lab/page-confirmedv2/confirmedv2pcs-jenis-analisa', [
            'No_Sampel' => $no_sampel,
            'No_Fak_Sub_Sampel' => $no_fak_sub_sampel,
            'id_jenis_analisa' => $id_jenis_analisa,
        ]);
    }
    public function viewInformasiJenisAnalisaSingleQr($no_sampel)
    {
        return inertia('vue/dashboard/lab/page-confirmedv2/confirmedv2no-pcs-jenis-analisa', [
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
            ->select('Flag_Perhitungan', 'Jenis_Analisa') 
            ->first();

        if (!$getInformasiAnalisa) {
            abort(404, 'Data Jenis Analisa tidak ditemukan');
        }

        $getInformasi = DB::table('N_EMI_LAB_PO_Sampel')
            ->where('No_Sampel', $no_sampel)
            ->select('Id_Mesin', 'Kode_Barang')
            ->first();

        $Id_Master_Mesin = $getInformasi->Id_Mesin;
        $Kode_Barang     = $getInformasi->Kode_Barang;
        $Flag_Perhitungan = $getInformasiAnalisa->Flag_Perhitungan;
        $hasStandardConfiguration = false;

        if ($Flag_Perhitungan === 'Y') {
            $hasStandardConfiguration = DB::table('N_EMI_LAB_Standar_Rentang')
                ->where('Id_Jenis_Analisa', $Id_Jenis_Analisa)
                ->where('Kode_Barang', $Kode_Barang)
                ->where('Id_Master_Mesin', $Id_Master_Mesin)
                ->exists(); 

        } else {
            $hasStandardConfiguration = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->where('Id_Jenis_Analisa', $Id_Jenis_Analisa)
                ->exists();
        }

        return inertia('vue/dashboard/lab/page-confirmedv2/verfikasiv2pcs', [
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
            ->where('id', $Id_Jenis_Analisa)
            ->select('Flag_Perhitungan', 'Jenis_Analisa') 
            ->first();

        if (!$getInformasiAnalisa) {
            abort(404, 'Data Jenis Analisa tidak ditemukan');
        }

        $getInformasi = DB::table('N_EMI_LAB_PO_Sampel')
            ->where('No_Sampel', $no_sampel)
            ->select('Id_Mesin', 'Kode_Barang')
            ->first();

        $Id_Master_Mesin = $getInformasi->Id_Mesin;
        $Kode_Barang     = $getInformasi->Kode_Barang;
        $Flag_Perhitungan = $getInformasiAnalisa->Flag_Perhitungan;
        $hasStandardConfiguration = false;

        if ($Flag_Perhitungan === 'Y') {
            $hasStandardConfiguration = DB::table('N_EMI_LAB_Standar_Rentang')
                ->where('Id_Jenis_Analisa', $Id_Jenis_Analisa)
                ->where('Kode_Barang', $Kode_Barang)
                ->where('Id_Master_Mesin', $Id_Master_Mesin)
                ->exists(); 

        } else {
            $hasStandardConfiguration = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->where('Id_Jenis_Analisa', $Id_Jenis_Analisa)
                ->exists();
        }

        return inertia('vue/dashboard/lab/page-confirmedv2/verfikasiv2nopcs', [
            'No_Sampel' => $no_sampel,
            'Id_Jenis_Analisa' => $id_jenis_analisa,
            'Has_Standard_Configuration' => $hasStandardConfiguration,
        ]);
    }

    public function viewHasilAnalisa()
    {
        return inertia('vue/dashboard/lab/hasil-analisis/HasilAnalisa');
    }

    public function viewSubHasilAnalisa($id_jenis_analisa)
    {
        return inertia('vue/dashboard/lab/hasil-analisis/HasilAnalisa', [
            'selected_id' => $id_jenis_analisa
        ]);
    }

    public function viewNestedSubHasilAnalisa($id_jenis_analisa, $no_po_sampel, $flag_multi)
    {
        // Consolidated: render main HasilAnalisa SPA with deep-link props
        return inertia('vue/dashboard/lab/hasil-analisis/HasilAnalisa', [
            'selected_id'          => $id_jenis_analisa,
            'initial_no_po_sampel' => $no_po_sampel,
            'initial_flag_multi'   => $flag_multi,
        ]);
    }

    public function viewDetaiHasilMulti($id_jenis_analisa, $no_po_sampel, $flag_multi, $no_sub)
    {
        // Consolidated: render main HasilAnalisa SPA with deep-link props including sub-sample
        return inertia('vue/dashboard/lab/hasil-analisis/HasilAnalisa', [
            'selected_id'          => $id_jenis_analisa,
            'initial_no_po_sampel' => $no_po_sampel,
            'initial_flag_multi'   => $flag_multi,
            'initial_no_sub'       => $no_sub,
        ]);
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
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
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
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
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
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan"
            ], 500);
        }
    }


    public function storeMultiRumusV2(Request $request)
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

            $lastNumber = DB::table('N_EMI_LAB_Uji_Sampel')
                ->where('No_Faktur', 'like', $prefix . '-%')
                ->lockForUpdate() 
                ->selectRaw("MAX(CAST(SUBSTRING(No_Faktur, ? + 2, 10) AS INT)) as max_number", [$prefixLength])
                ->value('max_number') ?? 0;

            $firstAnalysis = $request->analyses[0];
            $idDecoded = Hashids::connection('custom')->decode($firstAnalysis['Id_Jenis_Analisa']);
            $jenisAnalisa = isset($idDecoded[0]) ? $idDecoded[0] : null;

            // PLT: cek apakah analisa ini PLT dan ambil Id_Session
            $isPlt = DB::table('N_EMI_LAB_Jenis_Analisa')
                ->where('id', $jenisAnalisa)
                ->value('Kode_Aktivitas_Lab') === 'PLT';
            $idSessionForPlt = null;
            if ($isPlt) {
                $pltSessionIdRaw = $firstAnalysis['plt_session_id'] ?? null;
                if ($pltSessionIdRaw) {
                    $decodedSess = Hashids::connection('custom')->decode($pltSessionIdRaw);
                    $idSessionForPlt = $decodedSess[0] ?? null;
                }
                if (!$idSessionForPlt) {
                    $idSessionForPlt = DB::table('N_EMI_LAB_Palatabilitas_Session')
                        ->where('No_Po_Sampel', $firstAnalysis['No_Po_Sampel'])
                        ->value('Id_Session');
                }
                if ($idSessionForPlt) {
                    DB::table('N_EMI_LAB_Palatabilitas_Session')
                        ->where('Id_Session', $idSessionForPlt)
                        ->whereNull('No_Po_Sampel')
                        ->update(['No_Po_Sampel' => $firstAnalysis['No_Po_Sampel']]);
                }
            }

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

                $idPembandingForRow = null;
                if ($isPlt && !empty($analysisData['Id_Pembanding'])) {
                    $decoded = Hashids::connection('custom')->decode($analysisData['Id_Pembanding']);
                    $idPembandingForRow = $decoded[0] ?? null;
                }

                $isFlagKhusus = DB::table('N_EMI_LAB_PO_Sampel')
                        ->where('No_Sampel', $sumberData->No_Po_Sampel)
                        ->where('Flag_Khusus', 'Y')
                        ->exists();

                if (!$isFlagKhusus) {
                        $isAllowed = DB::table('N_EMI_LAB_Barang_Analisa')
                            ->where('Id_Jenis_Analisa', $jenisAnalisa)
                            ->where('Kode_Role', 'LAB')
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
                    ->where('Kode_Role', 'LAB')
                    ->get();
                $getKodeBarang = DB::table('N_EMI_LAB_PO_Sampel')->where('No_Sampel', $sumberData->No_Po_Sampel)->first();
                

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
                                    ->where('Kode_Role', 'LAB')
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
                        ->where('Kode_Role', 'LAB')
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

                    $getDataMesin = DB::table('EMI_Master_Mesin')
                                    ->where('Id_Master_Mesin', $sumberData->Id_Mesin)
                                    ->where('Flag_FG', 'Y')
                                    ->first();

                    $basePltPayload = $isPlt ? ['Id_Session' => $idSessionForPlt, 'Id_Pembanding' => $idPembandingForRow] : [];

                    if ($getDataMesin) {
                        $payloadUjiSampleData[] = array_merge([
                            "No_Faktur" => $newNumber,
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
                            'Id_Mesin' => $sumberData->Id_Mesin,
                        ], $basePltPayload);
                    } else {
                        $payloadUjiSampleData[] = array_merge([
                            "No_Faktur" => $newNumber,
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
                            'Id_Mesin' => $sumberData->Id_Mesin,
                        ], $basePltPayload);
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
            Log::error('Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan Server: " . $e->getMessage(),
                'trace' => $e->getTraceAsString() 
            ], 500);
        }
    }

    public function storeMultiRumusResamplingV2(Request $request)
    {
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

            $lastNumber = DB::table('N_EMI_LAB_Uji_Sampel')
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

            $idLogActivity = DB::table('N_EMI_LAB_Activity_Uji_Sampel')->insertGetId($payloadActivityUjiSampel, 'Id_Log_Activity');

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

                $resamplingLog = DB::table('N_EMI_LAB_Uji_Sampel_Resampling_Log')
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
                    DB::table('N_EMI_LAB_Activity_Uji_Sampel')
                        ->where('Id_Log_Activity', $idLogActivity)
                        ->update(['No_Fak_Sub_Po' => $noFakSubPoYangBenar]);
                }

                $isFlagKhusus = DB::table('N_EMI_LAB_PO_Sampel')
                    ->where('No_Sampel', $analysisData['No_Po_Sampel'])
                    ->where('Flag_Khusus', 'Y')
                    ->exists();

                if (!$isFlagKhusus) {
                    $isAllowed = DB::table('N_EMI_LAB_Barang_Analisa')
                        ->where('Id_Jenis_Analisa', $jenisAnalisa)
                        ->where('Id_User', $userId)
                        ->where('Kode_Role', 'LAB')
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
                    $dataSementara = DB::table('N_EMI_LAB_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->first();

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
                    ->where('Id_Jenis_Analisa', $sumberData->Id_Jenis_Analisa)
                    ->where('Kode_Role', 'LAB')
                    ->get();
                $parameterValues = collect($sumberData->parameters)->pluck('Value_Parameter', 'Id_Quality_Control');
                $getKodeBarang = DB::table('N_EMI_LAB_PO_Sampel')->where('No_Sampel', $sumberData->No_Po_Sampel)->first();


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
                                    ->where('Kode_Role', 'LAB')
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

                DB::table('N_EMI_LAB_Uji_Sampel')->insert($payloadUjiSampleData);
                DB::table('N_EMI_LAB_Uji_Sampel_Detail')->insert($payloadUjiSampleDetailData);

                DB::table('N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail')->insert($payloadActivityUjiSampelHasil);
                DB::table('N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail')->insert($payloadActiviyUjiSampelDetail);

                if ($isFromSementara) {
                    DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')->where('No_Sementara', $noSementara)->delete();
                    DB::table('N_EMI_LAB_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->delete();
                }

                DB::table("N_EMI_LAB_Uji_Sampel_Resampling_Log")
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

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => "Data resampling berhasil diproses dan disimpan.",
                'results' => $results
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
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

            $lastNumber = DB::table('N_EMI_LAB_Uji_Sampel')
                ->where('No_Faktur', 'like', $prefix . '-%')
                ->lockForUpdate() 
                ->selectRaw("MAX(CAST(SUBSTRING(No_Faktur, ? + 2, 10) AS INT)) as max_number", [$prefixLength])
                ->value('max_number') ?? 0;

            $firstAnalysis = $request->analyses[0];
            $idDecodedFirst = Hashids::connection('custom')->decode($firstAnalysis['Id_Jenis_Analisa']);
            $jenisAnalisaIdFirst = isset($idDecodedFirst[0]) ? $idDecodedFirst[0] : null;

            // PLT: cek apakah analisa ini PLT dan ambil Id_Session
            $isPlt = DB::table('N_EMI_LAB_Jenis_Analisa')
                ->where('id', $jenisAnalisaIdFirst)
                ->value('Kode_Aktivitas_Lab') === 'PLT';
            $idSessionForPlt = null;
            if ($isPlt) {
                $pltSessionIdRaw = $firstAnalysis['plt_session_id'] ?? null;
                if ($pltSessionIdRaw) {
                    $decodedSess = Hashids::connection('custom')->decode($pltSessionIdRaw);
                    $idSessionForPlt = $decodedSess[0] ?? null;
                }
                if (!$idSessionForPlt) {
                    $idSessionForPlt = DB::table('N_EMI_LAB_Palatabilitas_Session')
                        ->where('No_Po_Sampel', $firstAnalysis['No_Po_Sampel'])
                        ->value('Id_Session');
                }
                if ($idSessionForPlt) {
                    DB::table('N_EMI_LAB_Palatabilitas_Session')
                        ->where('Id_Session', $idSessionForPlt)
                        ->whereNull('No_Po_Sampel')
                        ->update(['No_Po_Sampel' => $firstAnalysis['No_Po_Sampel']]);
                }
            }

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

            $idLogActivity = DB::table('N_EMI_LAB_Activity_Uji_Sampel')->insertGetId($payloadActivityUjiSampel, 'Id_Log_Activity');

            $uploadedFilesData = [];
            if ($request->hasFile('photos') && $request->flag_foto === 'Y') {
                foreach ($request->file('photos') as $index => $file) {
                    $extension = $file->getClientOriginalExtension() ?: 'png';
                    $receivedSizeMB = number_format($file->getSize() / 1048576, 2);
                    Log::channel('UjiSampelController')->info("📥 [UPLOAD FOTO MULTIPLE] Menerima file indeks {$index} murni dari Frontend. Ukuran: {$receivedSizeMB} MB");

                    $fileName = 'lab' . Str::random(5) . '_' . time() . '_' . $index . '.' . $extension;
                    $gcsFilePath = 'berkas/lab/' . $fileName;
                    
                    Storage::disk('gcs')->put($gcsFilePath, file_get_contents($file));

                    $gcsFileSize = Storage::disk('gcs')->size($gcsFilePath);
                    $gcsSizeMB = number_format($gcsFileSize / 1048576, 2);
                    Log::channel('UjiSampelController')->info("☁️ [GCS UPLOAD MULTIPLE] Berhasil disimpan ke Cloud (Indeks {$index}). Ukuran final: {$gcsSizeMB} MB | Path: {$gcsFilePath}");

                    $note = $request->input("notes.$index") ?? '';

                    $uploadedFilesData[] = [
                        'File_Path' => $gcsFilePath,
                        'Keterangan' => $note
                    ];
                }
            }
            
            // dd($request->analyses);

            foreach ($request->analyses as $analysisData) {
                $idDecoded = Hashids::connection('custom')->decode($analysisData['Id_Jenis_Analisa']);
                $jenisAnalisaId = isset($idDecoded[0]) ? $idDecoded[0] : null;

                $idPembandingForRow = null;
                if ($isPlt && !empty($analysisData['Id_Pembanding'])) {
                    $decoded = Hashids::connection('custom')->decode($analysisData['Id_Pembanding']);
                    $idPembandingForRow = $decoded[0] ?? null;
                }

                $kodeAktivitasLab = DB::table('N_EMI_LAB_Jenis_Analisa')
                    ->where('id', $jenisAnalisaId)
                    ->value('Kode_Aktivitas_Lab');

                $rulesNonHitung = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                    ->select('Nilai_Kriteria', 'Keterangan_Kriteria', 'Flag_Layak')
                    ->where('Id_Jenis_Analisa', $jenisAnalisaId)
                    ->where('Flag_Aktif', 'Y')
                    ->where('Kode_Role', 'LAB')
                    ->get();

                $inputMap = []; 
                $ruleDetailsMap = []; 
                
                $standardRule = $rulesNonHitung->firstWhere('Flag_Layak', 'Y');
                $standardRangeValue = $standardRule ? (float)$standardRule->Nilai_Kriteria : null;

                

                foreach ($rulesNonHitung as $rule) {
                    $valFloat = (float)$rule->Nilai_Kriteria;
                    $inputMap[$rule->Keterangan_Kriteria] = $valFloat;
                    
                    $ruleDetailsMap[(string)$valFloat] = [
                        'label' => $rule->Keterangan_Kriteria,
                        'layak' => $rule->Flag_Layak
                    ];
                }

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
                            ->where('Id_Jenis_Analisa', $jenisAnalisaId)
                            ->where('Id_User', $userId)
                            ->where('Kode_Role', 'LAB')
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
                    $dataSementara = DB::table('N_EMI_LAB_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->first();

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
                    $flagLayak = 'T';

                    
                    $keyCheck = (string)$paramValueFloat;
                    if (isset($ruleDetailsMap[$keyCheck])) {
                        $ruleDetail = $ruleDetailsMap[$keyCheck];
                        
                        $flagString = 'Y';
                        $nilaiHasilString = $ruleDetail['label'];
                        $flagLayak = $ruleDetail['layak']; 
                    }
                    

                    if ($kodeAktivitasLab === 'PLT') {
                        $flagLayak = 'Y';
                    }

                    $rangeAwal = $standardRangeValue;
                    $rangeAkhir = $standardRangeValue;

                    $payloadUjiSampleData[] = array_merge([
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
                        "Flag_Layak" => $flagLayak,
                        "Range_Awal" => $rangeAwal,
                        "Range_Akhir" => $rangeAkhir,
                    ], $isPlt ? ['Id_Session' => $idSessionForPlt, 'Id_Pembanding' => $idPembandingForRow] : []);

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

                DB::table('N_EMI_LAB_Uji_Sampel')->insert($payloadUjiSampleData);
                DB::table('N_EMI_LAB_Uji_Sampel_Detail')->insert($payloadUjiSampleDetailData);
                DB::table('N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail')->insert($payloadActivityUjiSampelHasil);
                DB::table('N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail')->insert($payloadActiviyUjiSampelDetail);

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
                    DB::table('N_EMI_LAB_Berkas_Uji_Lab')->insert($berkasPayload);
                }

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
        } catch (\Illuminate\Database\QueryException $e) {
            // 1. Tangkap khusus error database (QueryException)
            DB::rollBack();
            
            // Rollback file di GCS jika ada
            if (!empty($uploadedFilesData)) {
                foreach ($uploadedFilesData as $fileData) {
                    if (Storage::disk('gcs')->exists($fileData['File_Path'])) {
                        Storage::disk('gcs')->delete($fileData['File_Path']);
                        Log::channel('UjiSampelController')->info("🗑️ [GCS ROLLBACK] File dihapus karena proses DB gagal: {$fileData['File_Path']}");
                    }
                }
            }
            
            // Log error asli (lengkap dengan query-nya) HANYA di file log server untuk tim IT
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);

            // 2. Filter pesan untuk pengguna
            $errorCode = $e->errorInfo[1] ?? 0;
            $sqlState = $e->errorInfo[0] ?? '';
            
            $userMessage = "Terjadi kesalahan pada saat menyimpan data ke database.";

            // Mapping Kode Error SQL Server ke bahasa manusia yang aman
            if ($errorCode == 8152 || $errorCode == 2628 || $sqlState === '22001') {
                $userMessage = "Gagal menyimpan: Ada isian teks/data yang terlalu panjang melebihi kapasitas kolom.";
            } elseif ($errorCode == 1205 || $sqlState === '40001') {
                $userMessage = "Sistem sedang sibuk memproses data lain (Deadlock). Silakan coba simpan lagi beberapa saat.";
            } elseif ($errorCode == 2627 || $errorCode == 2601) {
                $userMessage = "Gagal menyimpan: Ditemukan data duplikat yang sudah pernah diinput sebelumnya.";
            } elseif ($errorCode == 547) {
                $userMessage = "Gagal menyimpan: Data yang direferensikan tidak valid atau tidak ditemukan.";
            } elseif ($errorCode == 208) {
                $userMessage = "Kesalahan sistem: Tabel atau relasi data tidak ditemukan saat memproses permintaan.";
            }

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => $userMessage
            ], 500);

        } catch (\Exception $e) {
            // 3. Tangkap error umum lainnya (Logic PHP, Network, dll)
            DB::rollBack();
            
            if (!empty($uploadedFilesData)) {
                foreach ($uploadedFilesData as $fileData) {
                    if (Storage::disk('gcs')->exists($fileData['File_Path'])) {
                        Storage::disk('gcs')->delete($fileData['File_Path']);
                        Log::channel('UjiSampelController')->info("🗑️ [GCS ROLLBACK] File dihapus karena logic PHP gagal: {$fileData['File_Path']}");
                    }
                }
            }
            
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);

            // Cek apakah pesan exception murni dari PHP/Logic (aman) atau memuat kata kunci database
            $rawMessage = $e->getMessage();
            $safeMessage = "Terjadi Kesalahan Sistem.";
            
            // Jika pesannya bukan tentang syntax SQL atau hal teknis, kita bisa tampilkan
            if (!str_contains(strtolower($rawMessage), 'sql') && !str_contains(strtolower($rawMessage), 'syntax')) {
                // Opsional: Anda bisa membatasi panjang pesan agar tidak kepanjangan
                $safeMessage = "Kesalahan Sistem: " . substr($rawMessage, 0, 150); 
            }

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => $safeMessage
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

            $lastNumber = DB::table('N_EMI_LAB_Uji_Sampel')
                ->where('No_Faktur', 'like', $prefix . '-%')
                ->lockForUpdate() 
                ->selectRaw("MAX(CAST(SUBSTRING(No_Faktur, ? + 2, 10) AS INT)) as max_number", [$prefixLength])
                ->value('max_number') ?? 0;

            $firstAnalysis = $request->analyses[0];
            $idDecoded = Hashids::connection('custom')->decode($firstAnalysis['Id_Jenis_Analisa']);
            $jenisAnalisaId = isset($idDecoded[0]) ? $idDecoded[0] : null;
            $noPoSampel = $firstAnalysis['No_Po_Sampel'];

            // PLT: cek apakah analisa ini PLT dan ambil Id_Session
            $isPlt = DB::table('N_EMI_LAB_Jenis_Analisa')
                ->where('id', $jenisAnalisaId)
                ->value('Kode_Aktivitas_Lab') === 'PLT';

            $idSessionForPlt = null;
            if ($isPlt) {
                $pltSessionIdRaw = $firstAnalysis['plt_session_id'] ?? null;
                if ($pltSessionIdRaw) {
                    $decodedSess = Hashids::connection('custom')->decode($pltSessionIdRaw);
                    $idSessionForPlt = $decodedSess[0] ?? null;
                }
                if (!$idSessionForPlt) {
                    $idSessionForPlt = DB::table('N_EMI_LAB_Palatabilitas_Session')
                        ->where('No_Po_Sampel', $firstAnalysis['No_Po_Sampel'])
                        ->value('Id_Session');
                }
                if ($idSessionForPlt) {
                    DB::table('N_EMI_LAB_Palatabilitas_Session')
                        ->where('Id_Session', $idSessionForPlt)
                        ->whereNull('No_Po_Sampel')
                        ->update(['No_Po_Sampel' => $firstAnalysis['No_Po_Sampel']]);
                }
            }

            $dataResampling = DB::table('N_EMI_LAB_Uji_Sampel_Resampling_Log')
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

            $oldFakturRecords = DB::table('N_EMI_LAB_Uji_Sampel')
                ->where('No_Po_Sampel', $noPoSampel)
                ->where('Id_Jenis_Analisa', $jenisAnalisaId)
                ->where('Flag_Resampling', 'Y')
                ->where('Status_Keputusan_Sampel', 'tolak')
                ->pluck('No_Faktur')
                ->toArray();

            $noSementaraList = collect($request->analyses)->pluck('No_Sementara')->filter()->toArray();

            $allFakturToDelete = array_unique(array_merge($oldFakturRecords, $noSementaraList));
            
            if (!empty($allFakturToDelete)) {
                $oldBerkasRecords = DB::table('N_EMI_LAB_Berkas_Uji_Lab')
                    ->whereIn('No_Faktur', $allFakturToDelete)
                    ->get();

                foreach ($oldBerkasRecords as $berkas) {
                    if (!empty($berkas->File_Path)) {
                        $oldFilesToDeleteGcs[] = $berkas->File_Path;
                    }
                }

                if ($oldBerkasRecords->count() > 0) {
                    DB::table('N_EMI_LAB_Berkas_Uji_Lab')
                        ->whereIn('No_Faktur', $allFakturToDelete)
                        ->delete();
                }
            }
            
            if ($request->hasFile('photos') && $request->flag_foto === 'Y') {
                $photos = $request->file('photos');
                $notes = $request->input('notes', []);

                foreach ($photos as $index => $file) {
                    $extension = $file->getClientOriginalExtension() ?: 'png';
                    $fileName = 'lab_' . Str::random(5) . '_' . time() . '_' . $index . '.' . $extension;
                    $gcsFilePath = 'berkas/lab/' . $fileName;
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

            $idLogActivity = DB::table('N_EMI_LAB_Activity_Uji_Sampel')->insertGetId($payloadActivityUjiSampel, 'Id_Log_Activity');

            $jenisAnalisaRecord = DB::table('N_EMI_LAB_Jenis_Analisa')
                ->select('Kode_Analisa', 'Flag_Perhitungan')
                ->where('id', $jenisAnalisaId)
                ->where('Kode_Role', 'LAB')
                ->first();

            $isPerhitungan = $jenisAnalisaRecord && $jenisAnalisaRecord->Flag_Perhitungan === 'Y';
            $flagPerhitunganVal = $isPerhitungan ? 'Y' : null;

            foreach ($request->analyses as $analysisData) {
                $noSementara = $analysisData['No_Sementara'] ?? null;
                $sumberData = (object) $analysisData;
                $isFromSementara = false;

                $idPembandingForRow = null;
                if ($isPlt && !empty($analysisData['Id_Pembanding'])) {
                    $decodedPb = Hashids::connection('custom')->decode($analysisData['Id_Pembanding']);
                    $idPembandingForRow = $decodedPb[0] ?? null;
                }

                $poData = DB::table('N_EMI_LAB_PO_Sampel')
                    ->where('No_Sampel', $sumberData->No_Po_Sampel)
                    ->select('Kode_Barang', 'Flag_Khusus')
                    ->first();
                
                $kodeBarang = $poData->Kode_Barang ?? '';
                $isFlagKhusus = ($poData->Flag_Khusus ?? null) === 'Y';

                if (!$isFlagKhusus) {
                        $isAllowed = DB::table('N_EMI_LAB_Barang_Analisa')
                            ->where('Id_Jenis_Analisa', $jenisAnalisaId)
                            ->where('Id_User', $userId)
                            ->where('Kode_Role', 'LAB')
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
                            ->where('Kode_Perusahaan', '001')
                            ->where('Id_Jenis_Analisa', $jenisAnalisaId)
                            ->where('Kode_Barang', $kodeBarang)
                            ->where('Id_Master_Mesin', $analysisData['id_mesin'])
                            ->where('Kode_Role', 'LAB')
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
                            ->where('Id_Jenis_Analisa', $jenisAnalisaId)
                            ->where('Nilai_Kriteria', $paramValueFloat)
                            ->where('Flag_Aktif', 'Y')
                            ->where('Kode_Role', 'LAB')
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

                    $basePltPayload = $isPlt ? ['Id_Session' => $idSessionForPlt, 'Id_Pembanding' => $idPembandingForRow] : [];

                    $payloadUjiSampleData[] = array_merge([
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
                    ], $basePltPayload);

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

                DB::table('N_EMI_LAB_Uji_Sampel')->insert($payloadUjiSampleData);
                DB::table('N_EMI_LAB_Uji_Sampel_Detail')->insert($payloadUjiSampleDetailData);
                DB::table('N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail')->insert($payloadActivityUjiSampelHasil);
                DB::table('N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail')->insert($payloadActiviyUjiSampelDetail);

                if (!empty($berkasInsertsTemplate)) {
                    $berkasToInsert = array_map(function($item) use ($newNumber) {
                        $item['No_Faktur'] = $newNumber;
                        $item['Berkas_Key'] = Str::random(32); 
                        return $item;
                    }, $berkasInsertsTemplate);

                    DB::table('N_EMI_LAB_Berkas_Uji_Lab')->insert($berkasToInsert);
                }

                if ($isFromSementara) {
                    DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')->where('No_Sementara', $noSementara)->delete();
                    DB::table('N_EMI_LAB_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->delete();
                }

                DB::table("N_EMI_LAB_Uji_Sampel_Resampling_Log")
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
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan"
            ], 500);
        }
    }
  
    public function storeMultiRumusNotMultiQrCode(Request $request)
    {
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

            $lastNumber = DB::table('N_EMI_LAB_Uji_Sampel')
                ->where('No_Faktur', 'like', $prefix . '-%')
                ->lockForUpdate() 
                ->selectRaw("MAX(CAST(SUBSTRING(No_Faktur, ? + 2, 10) AS INT)) as max_number", [$prefixLength])
                ->value('max_number') ?? 0;

            $firstAnalysis = $request->analyses[0];
            $idDecoded = Hashids::connection('custom')->decode($firstAnalysis['Id_Jenis_Analisa']);
            $jenisAnalisa = isset($idDecoded[0]) ? $idDecoded[0] : null;

            // PLT: cek apakah analisa ini PLT dan ambil Id_Session
            $isPlt = DB::table('N_EMI_LAB_Jenis_Analisa')
                ->where('id', $jenisAnalisa)
                ->value('Kode_Aktivitas_Lab') === 'PLT';
            $idSessionForPlt = null;
            if ($isPlt) {
                $pltSessionIdRaw = $firstAnalysis['plt_session_id'] ?? null;
                if ($pltSessionIdRaw) {
                    $decodedSess = Hashids::connection('custom')->decode($pltSessionIdRaw);
                    $idSessionForPlt = $decodedSess[0] ?? null;
                }
                if (!$idSessionForPlt) {
                    $idSessionForPlt = DB::table('N_EMI_LAB_Palatabilitas_Session')
                        ->where('No_Po_Sampel', $firstAnalysis['No_Po_Sampel'])
                        ->value('Id_Session');
                }
                if ($idSessionForPlt) {
                    DB::table('N_EMI_LAB_Palatabilitas_Session')
                        ->where('Id_Session', $idSessionForPlt)
                        ->whereNull('No_Po_Sampel')
                        ->update(['No_Po_Sampel' => $firstAnalysis['No_Po_Sampel']]);
                }
            }

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

            $idLogActivity = DB::table('N_EMI_LAB_Activity_Uji_Sampel')->insertGetId($payloadActivityUjiSampel, 'Id_Log_Activity');

            foreach ($request->analyses as $analysisData) {
                $noSementara = $analysisData['No_Sementara'] ?? null;
                $sumberData = (object) $analysisData;
                $idUserUntukInsert = $pengguna->UserId;
                $isFromSementara = false;

                $idPembandingForRow = null;
                if ($isPlt && !empty($analysisData['Id_Pembanding'])) {
                    $decoded = Hashids::connection('custom')->decode($analysisData['Id_Pembanding']);
                    $idPembandingForRow = $decoded[0] ?? null;
                }

                $isFlagKhusus = DB::table('N_EMI_LAB_PO_Sampel')
                        ->where('No_Sampel', $sumberData->No_Po_Sampel)
                        ->where('Flag_Khusus', 'Y')
                        ->exists();

                if (!$isFlagKhusus) {
                        $isAllowed = DB::table('N_EMI_LAB_Barang_Analisa')
                            ->where('Id_Jenis_Analisa', $jenisAnalisa)
                            ->where('Kode_Role', 'LAB')
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
                    ->where('Kode_Role', 'LAB')
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
                        ->where('Kode_Role', 'LAB')
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
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan"
            ], 500);
        }
    }

    public function storeMultiRumusNotMultiQrCodeResampling(Request $request)
    {
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

            $lastNumber = DB::table('N_EMI_LAB_Uji_Sampel')
                ->where('No_Faktur', 'like', $prefix . '-%')
                ->lockForUpdate() 
                ->selectRaw("MAX(CAST(SUBSTRING(No_Faktur, ? + 2, 10) AS INT)) as max_number", [$prefixLength])
                ->value('max_number') ?? 0;

            $firstAnalysis = $request->analyses[0];
            $idDecoded = Hashids::connection('custom')->decode($firstAnalysis['Id_Jenis_Analisa']);
            $jenisAnalisa = isset($idDecoded[0]) ? $idDecoded[0] : null;

            // PLT: cek apakah analisa ini PLT dan ambil Id_Session
            $isPlt = DB::table('N_EMI_LAB_Jenis_Analisa')
                ->where('id', $jenisAnalisa)
                ->value('Kode_Aktivitas_Lab') === 'PLT';

            $idSessionForPlt = null;
            if ($isPlt) {
                $pltSessionIdRaw = $firstAnalysis['plt_session_id'] ?? null;
                if ($pltSessionIdRaw) {
                    $decodedSess = Hashids::connection('custom')->decode($pltSessionIdRaw);
                    $idSessionForPlt = $decodedSess[0] ?? null;
                }
                if (!$idSessionForPlt) {
                    $idSessionForPlt = DB::table('N_EMI_LAB_Palatabilitas_Session')
                        ->where('No_Po_Sampel', $firstAnalysis['No_Po_Sampel'])
                        ->value('Id_Session');
                }
                if ($idSessionForPlt) {
                    DB::table('N_EMI_LAB_Palatabilitas_Session')
                        ->where('Id_Session', $idSessionForPlt)
                        ->whereNull('No_Po_Sampel')
                        ->update(['No_Po_Sampel' => $firstAnalysis['No_Po_Sampel']]);
                }
            }

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

            $idLogActivity = DB::table('N_EMI_LAB_Activity_Uji_Sampel')->insertGetId($payloadActivityUjiSampel, 'Id_Log_Activity');

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

                $resamplingLog = DB::table('N_EMI_LAB_Uji_Sampel_Resampling_Log')
                    ->where('Id_Resampling', $id_resampling)
                    ->first();

                $tahapanKe = $resamplingLog->Tahapan_Ke ?? 1;

                $idPembandingForRow = null;
                if ($isPlt && !empty($analysisData['Id_Pembanding'])) {
                    $decodedPb = Hashids::connection('custom')->decode($analysisData['Id_Pembanding']);
                    $idPembandingForRow = $decodedPb[0] ?? null;
                }

                $isFlagKhusus = DB::table('N_EMI_LAB_PO_Sampel')
                        ->where('No_Sampel', $sumberData->No_Po_Sampel)
                        ->where('Flag_Khusus', 'Y')
                        ->exists();

                if (!$isFlagKhusus) {
                        $isAllowed = DB::table('N_EMI_LAB_Barang_Analisa')
                            ->where('Id_Jenis_Analisa', $jenisAnalisa)
                            ->where('Id_User', $userId)
                            ->where('Kode_Role', 'LAB')
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
                $formulas = DB::table('N_EMI_LAB_Perhitungan')->where('Id_Jenis_Analisa', $sumberData->Id_Jenis_Analisa)->get();

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
                        ->where('Flag_Aktif', 'Y')
                        ->where('Kode_Role', 'LAB')
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

                    $basePltPayload = $isPlt ? ['Id_Session' => $idSessionForPlt, 'Id_Pembanding' => $idPembandingForRow] : [];

                    $payloadUjiSampleData[] = array_merge([
                        "No_Faktur" => $newNumber,
                        "Kode_Perusahaan" => "001",
                        "Id_Jenis_Analisa" => $result['Id_Jenis_Analisa'],
                        "Id_Perhitungan" => $result['Id_Perhitungan'],
                        "Hasil" => $hasilFloat,
                        "Flag_Perhitungan" => 'Y',
                        "Flag_Multi_QrCode" => null,
                        "Status" => null,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        'Tahapan_Ke' => $tahapanKe,
                        'Status_Keputusan_Sampel' => 'menunggu',
                        "Id_User" => $idUserUntukInsert,
                        'Flag_Layak' => $Flag_Layak,
                        "No_Po_Sampel" => $sumberData->No_Po_Sampel,
                        "Id_Mesin" => $sumberData->Id_Mesin,
                        "Range_Awal" => $result['Range_Awal'],
                        "Range_Akhir" => $result['Range_Akhir'],
                    ], $basePltPayload);

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

                DB::table('N_EMI_LAB_Uji_Sampel')->insert($payloadUjiSampleData);
                DB::table('N_EMI_LAB_Uji_Sampel_Detail')->insert($payloadUjiSampleDetailData);
                DB::table('N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail')->insert($payloadActivityUjiSampelHasil);
                DB::table('N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail')->insert($payloadActiviyUjiSampelDetail);

                if ($isFromSementara) {
                    DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')->where('No_Sementara', $noSementara)->delete();
                    DB::table('N_EMI_LAB_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->delete();
                }

                DB::table("N_EMI_LAB_Uji_Sampel_Resampling_Log")
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
            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => "Data berhasil diproses dan disimpan.",
                'results' => $results 
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan"
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
                ->select('id', 'Kode_Analisa', 'Flag_Perhitungan')
                ->where('Kode_Role', 'LAB')
                ->get()
                ->keyBy('id');

            // Ambil Standar Range
            $standarNonHitung = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->whereIn('Id_Jenis_Analisa', $decodedIds)
                ->where('Flag_Aktif', 'Y')
                ->where('Kode_Role', 'LAB')
                ->get()
                ->groupBy('Id_Jenis_Analisa');

            $allowedAnalyses = DB::table('N_EMI_LAB_Barang_Analisa')
                ->where('Id_User', $userId)
                ->whereIn('Id_Jenis_Analisa', $decodedIds)
                ->where('Kode_Role', 'LAB')
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

            $idLogActivity = DB::table('N_EMI_LAB_Activity_Uji_Sampel')->insertGetId($payloadActivityUjiSampel, 'Id_Log_Activity');

            $results = [];

            $currentMonth = date('m');
            $currentYear = date('y');
            $prefix = 'FUS' . $currentMonth . $currentYear;
            $prefixLength = strlen($prefix);

            $lastNumber = DB::table('N_EMI_LAB_Uji_Sampel')
                ->where('No_Faktur', 'like', $prefix . '-%')
                ->lockForUpdate()
                ->selectRaw("MAX(CAST(SUBSTRING(No_Faktur, ? + 2, 10) AS INT)) as max_number", [$prefixLength])
                ->value('max_number') ?? 0;


            $uploadedFilesData = [];
            if ($request->hasFile('photos') && $request->flag_foto === 'Y') {
                foreach ($request->file('photos') as $index => $file) {
                    $extension = $file->getClientOriginalExtension() ?: 'png';
                    $receivedSizeMB = number_format($file->getSize() / 1048576, 2);
                    Log::channel('UjiSampelController')->info("📥 [UPLOAD FOTO MULTIPLE] Menerima file indeks {$index} murni dari Frontend. Ukuran: {$receivedSizeMB} MB");

                    $fileName = 'lab' . Str::random(5) . '_' . time() . '_' . $index . '.' . $extension;
                    $gcsFilePath = 'berkas/lab/' . $fileName;
                    
                    Storage::disk('gcs')->put($gcsFilePath, file_get_contents($file));

                    $gcsFileSize = Storage::disk('gcs')->size($gcsFilePath);
                    $gcsSizeMB = number_format($gcsFileSize / 1048576, 2);
                    Log::channel('UjiSampelController')->info("☁️ [GCS UPLOAD MULTIPLE] Berhasil disimpan ke Cloud (Indeks {$index}). Ukuran final: {$gcsSizeMB} MB | Path: {$gcsFilePath}");

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

                $isFlagKhusus = DB::table('N_EMI_LAB_PO_Sampel')
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

                $kodeAktivitasLab = DB::table('N_EMI_LAB_Jenis_Analisa')
                    ->where('id', $idJenisAnalisa)
                    ->value('Kode_Aktivitas_Lab');

                // PLT: lookup session dan pembanding sekali per analisa (di luar inner loop)
                $idSessionForPlt = null;
                $idPembandingForRow = null;
                if ($kodeAktivitasLab === 'PLT') {
                    $pltSessionIdRaw = $analysisData['plt_session_id'] ?? null;
                    if ($pltSessionIdRaw) {
                        $decodedSess = Hashids::connection('custom')->decode($pltSessionIdRaw);
                        $idSessionForPlt = $decodedSess[0] ?? null;
                    }
                    if (!$idSessionForPlt) {
                        $idSessionForPlt = DB::table('N_EMI_LAB_Palatabilitas_Session')
                            ->where('No_Po_Sampel', $analysisData['No_Po_Sampel'])
                            ->value('Id_Session');
                    }
                    if ($idSessionForPlt) {
                        DB::table('N_EMI_LAB_Palatabilitas_Session')
                            ->where('Id_Session', $idSessionForPlt)
                            ->whereNull('No_Po_Sampel')
                            ->update(['No_Po_Sampel' => $analysisData['No_Po_Sampel']]);
                    }

                    if (!empty($analysisData['Id_Pembanding'])) {
                        $decodedPb = Hashids::connection('custom')->decode($analysisData['Id_Pembanding']);
                        $idPembandingForRow = $decodedPb[0] ?? null;
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
                    $dataSementara = DB::table('N_EMI_LAB_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->first();

                    if ($dataSementara) {
                        foreach ($parameters as $paramFromRequest) {
                            $idNu = !empty($paramFromRequest['No_Urut']) ? Hashids::connection('custom')->decode($paramFromRequest['No_Urut'])[0] ?? null : null;
                            if ($idNu) {
                                $dbParam = DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')
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
                        $detailsSementara = DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')->where('No_Sementara', $noSementara)->get();
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

                    if ($kodeAktivitasLab === 'PLT') {
                        $flagLayak = 'Y';
                    }

                    $basePltPayload = $kodeAktivitasLab === 'PLT' ? ['Id_Session' => $idSessionForPlt, 'Id_Pembanding' => $idPembandingForRow] : [];

                    $payloadUjiSampleData[] = array_merge([
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
                    ], $basePltPayload);

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

                if (!empty($payloadUjiSampleData)) {
                    DB::table('N_EMI_LAB_Uji_Sampel')->insert($payloadUjiSampleData);
                    DB::table('N_EMI_LAB_Uji_Sampel_Detail')->insert($payloadUjiSampleDetailData);
                    DB::table('N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail')->insert($payloadActivityUjiSampelHasil);
                    DB::table('N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail')->insert($payloadActiviyUjiSampelDetail);
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
                    DB::table('N_EMI_LAB_Berkas_Uji_Lab')->insert($berkasPayload);
                }

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
            if (!empty($uploadedFilesData)) {
                foreach ($uploadedFilesData as $fileData) {
                    if (Storage::disk('gcs')->exists($fileData['File_Path'])) {
                        Storage::disk('gcs')->delete($fileData['File_Path']);
                        Log::channel('UjiSampelController')->info("🗑️ [GCS ROLLBACK] File dihapus karena proses DB gagal: {$fileData['File_Path']}");
                    }
                }
            }
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
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

        $berkasInsertsTemplate = []; 
        $oldFilesToDeleteGcs = [];   


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
                ->where('Kode_Role', 'LAB')
                ->select('id', 'Kode_Analisa', 'Flag_Perhitungan')
                ->get()
                ->keyBy('id');

            $standarNonHitung = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->whereIn('Id_Jenis_Analisa', $decodedIds)
                ->where('Kode_Perusahaan', '001')
                ->where('Flag_Aktif', 'Y')
                ->where('Kode_Role', 'LAB')
                ->get()
                ->groupBy('Id_Jenis_Analisa');

            $firstAnalysisHash = $request->analyses[0]['Id_Jenis_Analisa'];
            $firstIdDecoded = Hashids::connection('custom')->decode($firstAnalysisHash)[0] ?? null;

            // PLT: cek apakah analisa ini PLT dan ambil Id_Session
            $isPlt = DB::table('N_EMI_LAB_Jenis_Analisa')
                ->where('id', $firstIdDecoded)
                ->value('Kode_Aktivitas_Lab') === 'PLT';

            $idSessionForPlt = null;
            if ($isPlt) {
                $pltSessionIdRaw = $request->analyses[0]['plt_session_id'] ?? null;
                if ($pltSessionIdRaw) {
                    $decodedSess = Hashids::connection('custom')->decode($pltSessionIdRaw);
                    $idSessionForPlt = $decodedSess[0] ?? null;
                }
                if (!$idSessionForPlt) {
                    $idSessionForPlt = DB::table('N_EMI_LAB_Palatabilitas_Session')
                        ->where('No_Po_Sampel', $request->analyses[0]['No_Po_Sampel'])
                        ->value('Id_Session');
                }
                if ($idSessionForPlt) {
                    DB::table('N_EMI_LAB_Palatabilitas_Session')
                        ->where('Id_Session', $idSessionForPlt)
                        ->whereNull('No_Po_Sampel')
                        ->update(['No_Po_Sampel' => $request->analyses[0]['No_Po_Sampel']]);
                }
            }

            $dataResamplingFirst = DB::table('N_EMI_LAB_Uji_Sampel_Resampling_Log')
                ->where('No_Po_Sampel', $request->analyses[0]['No_Po_Sampel'])
                ->where('Id_Jenis_Analisa', $firstIdDecoded)
                ->whereNull('Flag_Selesai_Resampling')
                ->orderByDesc('Tanggal')
                ->orderByDesc('Jam')
                ->orderByDesc('Id_Resampling')
                ->first();


            if (!$dataResamplingFirst) {
                throw new \Exception("Data Resampling Aktif tidak ditemukan.");
            }


            $oldFakturRecords = DB::table('N_EMI_LAB_Uji_Sampel')
                ->where('No_Po_Sampel', $request->analyses[0]['No_Po_Sampel'])
                ->where('Id_Jenis_Analisa', $firstIdDecoded)
                ->where('Flag_Resampling', 'Y')
                ->where('Status_Keputusan_Sampel', 'tolak')
                ->pluck('No_Faktur')
                ->toArray();

            $noSementaraList = collect($request->analyses)->pluck('No_Sementara')->filter()->toArray();

            $allFakturToDelete = array_unique(array_merge($oldFakturRecords, $noSementaraList));
            
            if (!empty($allFakturToDelete)) {
                $oldBerkasRecords = DB::table('N_EMI_LAB_Berkas_Uji_Lab')
                    ->whereIn('No_Faktur', $allFakturToDelete)
                    ->get();

                foreach ($oldBerkasRecords as $berkas) {
                    if (!empty($berkas->File_Path)) {
                        $oldFilesToDeleteGcs[] = $berkas->File_Path;
                    }
                }

                if ($oldBerkasRecords->count() > 0) {
                    DB::table('N_EMI_LAB_Berkas_Uji_Lab')
                        ->whereIn('No_Faktur', $allFakturToDelete)
                        ->delete();
                }
            }
            
            if ($request->hasFile('photos') && $request->flag_foto === 'Y') {
                $photos = $request->file('photos');
                $notes = $request->input('notes', []);

                foreach ($photos as $index => $file) {
                    $extension = $file->getClientOriginalExtension() ?: 'png';
                    $fileName = 'lab_' . Str::random(5) . '_' . time() . '_' . $index . '.' . $extension;
                    $gcsFilePath = 'berkas/lab/' . $fileName;
                    Storage::disk('gcs')->put($gcsFilePath, file_get_contents($file));
                    $note = isset($notes[$index]) && !empty($notes[$index]) ? $notes[$index] : '-';
                    $berkasInsertsTemplate[] = [
                        'No_Sampel' => $request->analyses[0]['No_Po_Sampel'],
                        'Berkas_Key' => Str::random(32),
                        'File_Path' => $gcsFilePath,
                        'Keterangan' => $note
                    ];
                }
            }


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

            $idLogActivity = DB::table('N_EMI_LAB_Activity_Uji_Sampel')->insertGetId($payloadActivityUjiSampel, 'Id_Log_Activity');

            $results = [];

            $currentMonth = date('m');
            $currentYear = date('y');
            $prefix = 'FUS' . $currentMonth . $currentYear;
            $prefixLength = strlen($prefix);

            $lastNumber = DB::table('N_EMI_LAB_Uji_Sampel')
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

           
                $isFlagKhusus = DB::table('N_EMI_LAB_PO_Sampel')
                    ->where('No_Sampel', $analysisData['No_Po_Sampel'])
                    ->where('Flag_Khusus', 'Y')
                    ->exists();

                if (!$isFlagKhusus) {
                    $allowed = DB::table('N_EMI_LAB_Barang_Analisa')
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

                $idPembandingForRow = null;
                if ($isPlt && !empty($analysisData['Id_Pembanding'])) {
                    $decodedPb = Hashids::connection('custom')->decode($analysisData['Id_Pembanding']);
                    $idPembandingForRow = $decodedPb[0] ?? null;
                }

                $sumberData = (object) [
                    'No_Po_Sampel' => $analysisData['No_Po_Sampel'],
                    'Id_Jenis_Analisa' => $idJenisAnalisa,
                    'Id_Mesin' => $analysisData['id_mesin'] ?? null,
                    'formulas' => $analysisData['formulas'] ?? [],
                    'parameters' => $parameters
                ];

                if ($noSementara) {
                    $dataSementara = DB::table('N_EMI_LAB_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->first();

                    if ($dataSementara) {
                        foreach ($parameters as $paramFromRequest) {
                            $idNu = !empty($paramFromRequest['No_Urut']) ? Hashids::connection('custom')->decode($paramFromRequest['No_Urut'])[0] ?? null : null;
                            if ($idNu) {
                                $dbParam = DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')
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
                        $detailsSementara = DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')->where('No_Sementara', $noSementara)->get();
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

                    $basePltPayload = $isPlt ? ['Id_Session' => $idSessionForPlt, 'Id_Pembanding' => $idPembandingForRow] : [];

                    $payloadUjiSampleData[] = array_merge([
                        "No_Faktur" => $newNumber,
                        "Kode_Perusahaan" => "001",
                        "Flag_Foto" => $request->flag_foto,
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
                    ], $basePltPayload);

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
                    DB::table('N_EMI_LAB_Uji_Sampel')->insert($payloadUjiSampleData);
                    DB::table('N_EMI_LAB_Uji_Sampel_Detail')->insert($payloadUjiSampleDetailData);
                    DB::table('N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail')->insert($payloadActivityUjiSampelHasil);
                    DB::table('N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail')->insert($payloadActiviyUjiSampelDetail);
                }

                if (!empty($berkasInsertsTemplate)) {
                    $berkasToInsert = array_map(function($item) use ($newNumber) {
                        $item['No_Faktur'] = $newNumber;
                        $item['Berkas_Key'] = Str::random(32); 
                        return $item;
                    }, $berkasInsertsTemplate);

                    DB::table('N_EMI_LAB_Berkas_Uji_Lab')->insert($berkasToInsert);
                }
                

                if ($isFromSementara) {
                    DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')->where('No_Sementara', $noSementara)->delete();
                    DB::table('N_EMI_LAB_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->delete();
                }

                $results[] = [
                    'generated_no_faktur' => $newNumber,
                    'status' => $isFromSementara ? 'temporary_table' : 'request',
                ];
            }

              DB::table("N_EMI_LAB_Uji_Sampel_Resampling_Log")
                    ->where('Id_Resampling', $dataResampling->Id_Resampling) 
                    ->update([
                        'Flag_Selesai_Resampling' => 'Y'
                    ]);

            DB::commit();

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
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan"
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
            $tanggal = date('Y-m-d');
            $jam = date('H:i:s');
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
                $idDecoded = Hashids::connection('custom')->decode($analysis['Id_Jenis_Analisa']);
                $jenisAnalisa = isset($idDecoded[0]) ? $idDecoded[0] : null;

                $isFlagKhusus = DB::table('N_EMI_LAB_PO_Sampel')
                        ->where('No_Sampel', $noPoSampel)
                        ->where('Flag_Khusus', 'Y')
                        ->exists();

                if (!$isFlagKhusus) {
                        $isAllowed = DB::table('N_EMI_LAB_Barang_Analisa')
                            ->where('Kode_Role', 'LAB')
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
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
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
                            ->where('Kode_Role', 'LAB')
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
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
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
                            ->where('Kode_Role', 'LAB')
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
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
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
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
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
            $lastNumberRecord = DB::table('N_EMI_LAB_Uji_Sampel_Sementara')
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

            $idLogActivity = DB::table('N_EMI_LAB_Activity_Uji_Sampel')->insertGetId($payloadActivityUjiSampel, 'Id_Log_Activity');

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

                // Reset nilai header setiap loop baris baru
                $capturedResultValue = null; 

                foreach ($analysis['parameters'] as $param) {
                    $idDecodedQc = Hashids::connection('custom')->decode($param['Id_Quality_Control']);
                    $idQc = $idDecodedQc[0] ?? null;
                    
                    $cleanValue = $this->safeFloat($param['Value_Parameter']);
                    
                    // Logic Penting: Ambil nilai pertama yang valid sebagai Header Result
                    // Jika capturedResultValue masih null DAN cleanValue ada isinya, maka set.
                    // Ini mencegah nilai valid tertimpa oleh null di iterasi parameter berikutnya.
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

                // Fallback jika semua param null, header jadi 0 atau null (sesuai kebutuhan DB)
                $finalHeaderValue = $capturedResultValue !== null ? $capturedResultValue : 0;

                $batchUjiSementara[] = [
                    'Kode_Perusahaan' => '001',
                    'No_Sementara' => $newNumber,
                    'No_Po_Sampel' => $analysis['No_Po_Sampel'],
                    'Id_Jenis_Analisa' => $jenisAnalisa,
                    'Hasil' => $finalHeaderValue, // Gunakan nilai yang sudah diamankan
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
                    DB::table('N_EMI_LAB_Uji_Sampel_Sementara')->insert($chunk);
                }
            }
            
            if (!empty($batchDetailSementara)) {
                foreach (array_chunk($batchDetailSementara, 500) as $chunk) {
                    DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')->insert($chunk);
                }
            }

            if (!empty($batchLogHasil)) {
                foreach (array_chunk($batchLogHasil, 500) as $chunk) {
                    DB::table('N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail')->insert($chunk);
                }
            }

            if (!empty($batchLogParameter)) {
                foreach (array_chunk($batchLogParameter, 500) as $chunk) {
                    DB::table('N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail')->insert($chunk);
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
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
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

            $idLogActivity = DB::table('N_EMI_LAB_Activity_Uji_Sampel')->insertGetId(
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

                $getDataRvInteger = DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')
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

                    $getOldValue = DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')
                        ->where('No_Urut', $idNu)
                        ->where('Id_Quality_Control', $idQc)
                        ->value('Value_Parameter');

                    $parameterValues[$idQc] = $parameter['Value_Parameter'];

                    DB::table('N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail')->insert([
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
                
                    DB::table("N_EMI_LAB_Uji_Sampel_Detail_Sementara")
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
                    $getValueLama = DB::table('N_EMI_LAB_Uji_Sampel_Sementara')
                        ->where("No_Sementara", $analysis->No_Sementara)
                        ->value('Hasil');

                    DB::table('N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail')->insert([
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

                    DB::table("N_EMI_LAB_Uji_Sampel_Sementara")
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
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
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

            $idLogActivity = DB::table('N_EMI_LAB_Activity_Uji_Sampel')->insertGetId($payloadActivityUjiSampel, 'Id_Log_Activity');

            $listNoSementara = array_column($request->analyses, 'No_Sementara');
            
            $existingDetails = DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')
                ->whereIn('No_Sementara', $listNoSementara)
                ->select('No_Sementara', 'Id_Quality_Control', 'Value_Parameter', 'No_Urut')
                ->get()
                ->groupBy('No_Sementara');
            
            $existingHeaders = DB::table('N_EMI_LAB_Uji_Sampel_Sementara')
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

                    DB::table('N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail')->insert([
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

                    $queryUpdate = DB::table("N_EMI_LAB_Uji_Sampel_Detail_Sementara");
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

                DB::table('N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail')->insert([
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

                DB::table("N_EMI_LAB_Uji_Sampel_Sementara")
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
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
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

            $idLogActivity = DB::table('N_EMI_LAB_Activity_Uji_Sampel')->insertGetId([
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

            $existingDetails = DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')
                ->whereIn('No_Urut', $listNoUrut)
                ->get()
                ->keyBy('No_Urut');

            $existingHeaders = DB::table('N_EMI_LAB_Uji_Sampel_Sementara')
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
                DB::table('N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail')->insert($batchLogParameter);
            }
            if (!empty($batchLogHasil)) {
                DB::table('N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail')->insert($batchLogHasil);
            }

            foreach ($updateListDetail as $item) {
                DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')
                    ->where($item['conditions'])
                    ->update($item['values']);
            }

            foreach ($updateListHeader as $item) {
                DB::table('N_EMI_LAB_Uji_Sampel_Sementara')
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
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
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

            foreach ($request->analyses as $analysisData) {
                $analysis = (object) $analysisData;

                $noPoSampel = $analysis->No_Po_Sampel;
                $noFakSubPo = $analysis->No_Po_Multi_Sampel ?? null;
                $reason = $analysis->reason ?? '-';

                $idDecoded = Hashids::connection('custom')->decode($analysis->Id_Jenis_Analisa);
                $jenisAnalisa = $idDecoded[0] ?? null;

                $idLogActivity = DB::table('N_EMI_LAB_Activity_Uji_Sampel')->insertGetId([
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

                $parameterDb = DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')
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

                DB::table('N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail')->insert($paramLogs);

                foreach ($analysis->formulas as $formula) {
                    $hasilLama = DB::table('N_EMI_LAB_Uji_Sampel_Sementara')
                                ->where('No_Sementara', $no_sementara)
                                ->value('Hasil');

                    DB::table('N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail')->insert([
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

            DB::table('N_EMI_LAB_Uji_Sampel_Sementara')->where('No_Sementara', $no_sementara)->delete();
            DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')->where('No_Sementara', $no_sementara)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Data Berhasil Dihapus dan Aktivitas Dicatat.",
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
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

                $idLogActivity = DB::table('N_EMI_LAB_Activity_Uji_Sampel')->insertGetId([
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
                $parameterDb = DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')
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

                DB::table('N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail')->insert($paramLogs);

                foreach ($analysis->formulas as $formula) {
                    $hasilLama = DB::table('N_EMI_LAB_Uji_Sampel_Sementara')
                        ->where('No_Sementara', $noSementara)
                        ->value('Hasil');

                    DB::table('N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail')->insert([
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

                DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')->where('No_Sementara', $noSementara)->delete();
                DB::table('N_EMI_LAB_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->delete();
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Data berhasil dihapus dan semua aktivitas dicatat.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
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
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
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

        foreach ($request->analyses as $analisis){
            $analysis = (object) $analisis;

            $poInfoLog = DB::table('N_EMI_LAB_PO_Sampel')
                ->where('No_Sampel', $analysis->No_Po_Sampel)
                ->select('No_Po', 'No_Split_Po', 'Kode_Barang', 'Flag_Trial_Produksi')
                ->first();

            $jenisAksiLog = ($poInfoLog->Flag_Trial_Produksi ?? null) === 'Y'
                ? 'VALIDASI_TRIAL_PRODUKSI'
                : 'VALIDASI_PRODUKSI';

            if($analysis->Flag_Multi_QrCode === 'Y'){
                DB::beginTransaction();

                try {
                    DB::table('N_EMI_LAB_Uji_Sampel')
                            ->where('No_Po_Sampel', $analysis->No_Po_Sampel)
                            ->where('No_Fak_Sub_Po', $analysis->No_Fak_Sub_Po)
                            ->where('Id_Jenis_Analisa', $analysis->Id_Jenis_Analisa)
                            ->whereNull('Flag_Selesai')
                            ->update(['Flag_Selesai' => 'Y']);

                    $existingHeader = DB::table('N_EMI_LAB_Log_Aksi')
                        ->where('No_Sampel', $analysis->No_Po_Sampel)
                        ->where('Jenis_Aksi', $jenisAksiLog)
                        ->where('Sub_Aksi', 'SETUJU')
                        ->first();
                    if ($existingHeader) {
                        $logId = $existingHeader->Id_Log_Aksi;
                    } else {
                        $logId = DB::table('N_EMI_LAB_Log_Aksi')->insertGetId([
                            'No_Sampel'   => $analysis->No_Po_Sampel,
                            'No_Po'       => $poInfoLog->No_Po       ?? '-',
                            'No_Split_Po' => $poInfoLog->No_Split_Po ?? '-',
                            'Kode_Barang' => $poInfoLog->Kode_Barang ?? null,
                            'Flag_Trial'  => $poInfoLog->Flag_Trial_Produksi ?? null,
                            'Jenis_Aksi'  => $jenisAksiLog,
                            'Sub_Aksi'    => 'SETUJU',
                            'Id_User'     => $userId,
                            'Tanggal'     => $tanggalSqlServer,
                            'Jam'         => $jamSqlServer,
                        ]);
                    }

                    $jaName = DB::table('N_EMI_LAB_Jenis_Analisa')->where('id', $analysis->Id_Jenis_Analisa)->value('Jenis_Analisa');
                    DB::table('N_EMI_LAB_Log_Aksi_Detail')->insert([
                        'Id_Log_Aksi'        => $logId,
                        'Id_Jenis_Analisa'   => $analysis->Id_Jenis_Analisa,
                        'Nama_Jenis_Analisa' => $jaName,
                        'Flag_Layak'         => null,
                        'Tanggal'            => $tanggalSqlServer,
                        'Jam'                => $jamSqlServer,
                        'Id_User'            => $userId,
                    ]);

                    DB::commit();

                    return response()->json([
                        'success' => true,
                        'message' => 'Data berhasil diupdate dan status penyelesaian telah diperiksa.'
                    ], 200);

                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
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

                    $existingHeader = DB::table('N_EMI_LAB_Log_Aksi')
                        ->where('No_Sampel', $analysis->No_Po_Sampel)
                        ->where('Jenis_Aksi', $jenisAksiLog)
                        ->where('Sub_Aksi', 'SETUJU')
                        ->first();
                    if ($existingHeader) {
                        $logId = $existingHeader->Id_Log_Aksi;
                    } else {
                        $logId = DB::table('N_EMI_LAB_Log_Aksi')->insertGetId([
                            'No_Sampel'   => $analysis->No_Po_Sampel,
                            'No_Po'       => $poInfoLog->No_Po       ?? '-',
                            'No_Split_Po' => $poInfoLog->No_Split_Po ?? '-',
                            'Kode_Barang' => $poInfoLog->Kode_Barang ?? null,
                            'Flag_Trial'  => $poInfoLog->Flag_Trial_Produksi ?? null,
                            'Jenis_Aksi'  => $jenisAksiLog,
                            'Sub_Aksi'    => 'SETUJU',
                            'Id_User'     => $userId,
                            'Tanggal'     => $tanggalSqlServer,
                            'Jam'         => $jamSqlServer,
                        ]);
                    }

                    $jaName = DB::table('N_EMI_LAB_Jenis_Analisa')->where('id', $analysis->Id_Jenis_Analisa)->value('Jenis_Analisa');
                    DB::table('N_EMI_LAB_Log_Aksi_Detail')->insert([
                        'Id_Log_Aksi'        => $logId,
                        'Id_Jenis_Analisa'   => $analysis->Id_Jenis_Analisa,
                        'Nama_Jenis_Analisa' => $jaName,
                        'Flag_Layak'         => null,
                        'Tanggal'            => $tanggalSqlServer,
                        'Jam'                => $jamSqlServer,
                        'Id_User'            => $userId,
                    ]);

                    DB::commit();
                    return response()->json([
                        'success' => true,
                        'message' => 'Data berhasil diupdate dan status penyelesaian telah diperiksa.'
                    ], 200);

                }catch(\Exception $e){
                    DB::rollBack();
                    Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
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

            $checkFinishGood = DB::table('EMI_Master_Mesin')
                ->where('Id_Master_Mesin', $analysis->Id_Mesin)
                ->first();

            $checkedPerhitungan = DB::table('N_EMI_LAB_Jenis_Analisa')->where('id', $analysis->Id_Jenis_Analisa)->first();

            $poInfoLog = DB::table('N_EMI_LAB_PO_Sampel')
                ->where('No_Sampel', $analysis->No_Po_Sampel)
                ->select('No_Po', 'No_Split_Po', 'Kode_Barang', 'Flag_Trial_Produksi')
                ->first();

            $jenisAksiLog = ($poInfoLog->Flag_Trial_Produksi ?? null) === 'Y'
                ? 'VALIDASI_TRIAL_PRODUKSI'
                : 'VALIDASI_PRODUKSI';

            if($checkFinishGood && $checkFinishGood->Flag_FG === 'Y'){
                
                if($checkedPerhitungan->Flag_Perhitungan === 'Y'){
                        $adaTidakLayak = DB::table('N_EMI_LAB_Uji_Sampel')
                        ->where('No_Po_Sampel', $analysis->No_Po_Sampel)
                        ->where('No_Fak_Sub_Po', $analysis->No_Fak_Sub_Po)
                        ->where('Id_Jenis_Analisa', $analysis->Id_Jenis_Analisa)
                        ->where('Flag_Layak', 'T')
                        ->exists();

                    $statusKelayakan = $adaTidakLayak ? 'T' : 'Y';

                    DB::beginTransaction();

                    try {
                            DB::table('N_EMI_LAB_Uji_Sampel')
                                    ->where('No_Po_Sampel', $analysis->No_Po_Sampel)
                                    ->where('No_Fak_Sub_Po', $analysis->No_Fak_Sub_Po)
                                    ->where('Id_Jenis_Analisa', $analysis->Id_Jenis_Analisa) 
                                    ->whereNull('Flag_Selesai')
                                    ->update([
                                        'Status_Keputusan_Sampel' => 'terima',
                                        'Flag_Selesai' => 'Y'
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
                            DB::table('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final')->insert($payloadUjiFinalDetail);

                            $existingHeader = DB::table('N_EMI_LAB_Log_Aksi')
                                ->where('No_Sampel', $analysis->No_Po_Sampel)
                                ->where('Jenis_Aksi', $jenisAksiLog)
                                ->where('Sub_Aksi', 'SETUJU')
                                ->first();
                            if ($existingHeader) {
                                $logId = $existingHeader->Id_Log_Aksi;
                            } else {
                                $logId = DB::table('N_EMI_LAB_Log_Aksi')->insertGetId([
                                    'No_Sampel'   => $analysis->No_Po_Sampel,
                                    'No_Po'       => $poInfoLog->No_Po       ?? '-',
                                    'No_Split_Po' => $poInfoLog->No_Split_Po ?? '-',
                                    'Kode_Barang' => $poInfoLog->Kode_Barang ?? null,
                                    'Flag_Trial'  => $poInfoLog->Flag_Trial_Produksi ?? null,
                                    'Jenis_Aksi'  => $jenisAksiLog,
                                    'Sub_Aksi'    => 'SETUJU',
                                    'Id_User'     => $userId,
                                    'Tanggal'     => $tanggalSqlServer,
                                    'Jam'         => $jamSqlServer,
                                ]);
                            }

                            DB::table('N_EMI_LAB_Log_Aksi_Detail')->insert([
                                'Id_Log_Aksi'        => $logId,
                                'Id_Jenis_Analisa'   => $analysis->Id_Jenis_Analisa,
                                'Nama_Jenis_Analisa' => $checkedPerhitungan->Jenis_Analisa ?? null,
                                'Flag_Layak'         => $statusKelayakan,
                                'Tanggal'            => $tanggalSqlServer,
                                'Jam'                => $jamSqlServer,
                                'Id_User'            => $userId,
                            ]);

                            DB::commit();

                            return response()->json([
                                'success' => true,
                                'message' => 'Data berhasil diupdate dan status penyelesaian telah diperiksa.'
                            ], 200);

                    } catch (\Exception $e) {
                            DB::rollBack();
                            Log::error($e);
                            return response()->json([
                                'success' => false,
                                'message' => 'Terjadi kesalahan pada server saat memproses data.',
                                'error' => $e->getMessage(),
                            ], 500);
                    }
                }else {
                    DB::beginTransaction();

                    try {

                        DB::table('N_EMI_LAB_Uji_Sampel')
                                    ->where('No_Po_Sampel', $analysis->No_Po_Sampel)
                                    ->where('No_Fak_Sub_Po', $analysis->No_Fak_Sub_Po)
                                    ->where('Id_Jenis_Analisa', $analysis->Id_Jenis_Analisa)
                                    ->whereNull('Flag_Selesai')
                                    ->update([
                                        'Status_Keputusan_Sampel' => 'terima',
                                        'Flag_Selesai' => 'Y',
                                        'Flag_Layak' => 'Y',
                                        'Flag_Final' => 'Y'
                                    ]);

                            $existingHeader = DB::table('N_EMI_LAB_Log_Aksi')
                                ->where('No_Sampel', $analysis->No_Po_Sampel)
                                ->where('Jenis_Aksi', $jenisAksiLog)
                                ->where('Sub_Aksi', 'SETUJU')
                                ->first();
                            if ($existingHeader) {
                                $logId = $existingHeader->Id_Log_Aksi;
                            } else {
                                $logId = DB::table('N_EMI_LAB_Log_Aksi')->insertGetId([
                                    'No_Sampel'   => $analysis->No_Po_Sampel,
                                    'No_Po'       => $poInfoLog->No_Po       ?? '-',
                                    'No_Split_Po' => $poInfoLog->No_Split_Po ?? '-',
                                    'Kode_Barang' => $poInfoLog->Kode_Barang ?? null,
                                    'Flag_Trial'  => $poInfoLog->Flag_Trial_Produksi ?? null,
                                    'Jenis_Aksi'  => $jenisAksiLog,
                                    'Sub_Aksi'    => 'SETUJU',
                                    'Id_User'     => $userId,
                                    'Tanggal'     => $tanggalSqlServer,
                                    'Jam'         => $jamSqlServer,
                                ]);
                            }

                            DB::table('N_EMI_LAB_Log_Aksi_Detail')->insert([
                                'Id_Log_Aksi'        => $logId,
                                'Id_Jenis_Analisa'   => $analysis->Id_Jenis_Analisa,
                                'Nama_Jenis_Analisa' => $checkedPerhitungan->Jenis_Analisa ?? null,
                                'Flag_Layak'         => 'Y',
                                'Tanggal'            => $tanggalSqlServer,
                                'Jam'                => $jamSqlServer,
                                'Id_User'            => $userId,
                            ]);

                            DB::commit();

                            return response()->json([
                                'success' => true,
                                'message' => 'Data berhasil diupdate dan status penyelesaian telah diperiksa.'
                            ], 200);

                    } catch (\Exception $e) {
                            DB::rollBack();
                            Log::error($e);
                            return response()->json([
                                'success' => false,
                                'message' => 'Terjadi kesalahan pada server saat memproses data.',
                                'error' => $e->getMessage(),
                            ], 500);
                    }
                }
            }else {
                if($analysis->Flag_Multi_QrCode === 'Y'){
                    DB::beginTransaction();

                    try {
                        DB::table('N_EMI_LAB_Uji_Sampel')
                                ->where('No_Po_Sampel', $analysis->No_Po_Sampel)
                                ->where('No_Fak_Sub_Po', $analysis->No_Fak_Sub_Po)
                                ->where('Id_Jenis_Analisa', $analysis->Id_Jenis_Analisa)
                                ->whereNull('Flag_Selesai')
                                ->update([
                                    'Flag_Selesai' => 'Y',
                                    'Status_Keputusan_Sampel' => 'terima',
                                    'Flag_Layak' => 'Y',
                                    'Flag_Final' => 'Y'
                                ]);

                        $existingHeader = DB::table('N_EMI_LAB_Log_Aksi')
                            ->where('No_Sampel', $analysis->No_Po_Sampel)
                            ->where('Jenis_Aksi', $jenisAksiLog)
                            ->where('Sub_Aksi', 'SETUJU')
                            ->first();
                        if ($existingHeader) {
                            $logId = $existingHeader->Id_Log_Aksi;
                        } else {
                            $logId = DB::table('N_EMI_LAB_Log_Aksi')->insertGetId([
                                'No_Sampel'   => $analysis->No_Po_Sampel,
                                'No_Po'       => $poInfoLog->No_Po       ?? '-',
                                'No_Split_Po' => $poInfoLog->No_Split_Po ?? '-',
                                'Kode_Barang' => $poInfoLog->Kode_Barang ?? null,
                                'Flag_Trial'  => $poInfoLog->Flag_Trial_Produksi ?? null,
                                'Jenis_Aksi'  => $jenisAksiLog,
                                'Sub_Aksi'    => 'SETUJU',
                                'Id_User'     => $userId,
                                'Tanggal'     => $tanggalSqlServer,
                                'Jam'         => $jamSqlServer,
                            ]);
                        }

                        DB::table('N_EMI_LAB_Log_Aksi_Detail')->insert([
                            'Id_Log_Aksi'        => $logId,
                            'Id_Jenis_Analisa'   => $analysis->Id_Jenis_Analisa,
                            'Nama_Jenis_Analisa' => $checkedPerhitungan->Jenis_Analisa ?? null,
                            'Flag_Layak'         => 'Y',
                            'Tanggal'            => $tanggalSqlServer,
                            'Jam'                => $jamSqlServer,
                            'Id_User'            => $userId,
                        ]);

                        DB::commit();

                        return response()->json([
                            'success' => true,
                            'message' => 'Data berhasil diupdate dan status penyelesaian telah diperiksa.'
                        ], 200);

                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error($e);
                        return response()->json([
                            'success' => false,
                            'message' => 'Terjadi kesalahan pada server saat memproses data.',
                            'error' => $e->getMessage(),
                        ], 500);
                    }
                }else {
                    DB::beginTransaction();

                    try {
                        DB::table('N_EMI_LAB_Uji_Sampel')
                                ->where('No_Po_Sampel', $analysis->No_Po_Sampel)
                                ->where('Id_Jenis_Analisa', $analysis->Id_Jenis_Analisa)
                                ->whereNull('Flag_Selesai')
                                ->update([
                                    'Flag_Selesai' => 'Y',
                                    'Status_Keputusan_Sampel' => 'terima',
                                    'Flag_Layak' => 'Y',
                                    'Flag_Final' => 'Y'
                                ]);

                        $existingHeader = DB::table('N_EMI_LAB_Log_Aksi')
                            ->where('No_Sampel', $analysis->No_Po_Sampel)
                            ->where('Jenis_Aksi', $jenisAksiLog)
                            ->where('Sub_Aksi', 'SETUJU')
                            ->first();
                        if ($existingHeader) {
                            $logId = $existingHeader->Id_Log_Aksi;
                        } else {
                            $logId = DB::table('N_EMI_LAB_Log_Aksi')->insertGetId([
                                'No_Sampel'   => $analysis->No_Po_Sampel,
                                'No_Po'       => $poInfoLog->No_Po       ?? '-',
                                'No_Split_Po' => $poInfoLog->No_Split_Po ?? '-',
                                'Kode_Barang' => $poInfoLog->Kode_Barang ?? null,
                                'Flag_Trial'  => $poInfoLog->Flag_Trial_Produksi ?? null,
                                'Jenis_Aksi'  => $jenisAksiLog,
                                'Sub_Aksi'    => 'SETUJU',
                                'Id_User'     => $userId,
                                'Tanggal'     => $tanggalSqlServer,
                                'Jam'         => $jamSqlServer,
                            ]);
                        }

                        DB::table('N_EMI_LAB_Log_Aksi_Detail')->insert([
                            'Id_Log_Aksi'        => $logId,
                            'Id_Jenis_Analisa'   => $analysis->Id_Jenis_Analisa,
                            'Nama_Jenis_Analisa' => $checkedPerhitungan->Jenis_Analisa ?? null,
                            'Flag_Layak'         => 'Y',
                            'Tanggal'            => $tanggalSqlServer,
                            'Jam'                => $jamSqlServer,
                            'Id_User'            => $userId,
                        ]);

                        DB::commit();
                        return response()->json([
                            'success' => true,
                            'message' => 'Data berhasil diupdate dan status penyelesaian telah diperiksa.'
                        ], 200);

                    }catch(\Exception $e){
                        DB::rollBack();
                        Log::error($e);
                        return response()->json([
                            'success' => false,
                            'message' => 'Terjadi kesalahan pada server saat memproses data.',
                            'error' => $e->getMessage()
                        ], 500);
                    }
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
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
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
            ->where('ba.Kode_Role', 'LAB')
            ->where('ja.Kode_Role', 'LAB')
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

            $sampelRow = DB::table('N_EMI_LAB_PO_Sampel as q')
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

            $sampelWaktu = Carbon::parse($sampelRow->Tanggal . ' ' . $sampelRow->Jam);

            $hariKe4 = $sampelWaktu->copy()->addDays(3);

            if ($hariKe4->dayOfWeek === Carbon::SUNDAY) {
                $batasAkhirInput = $hariKe4->copy()->addDay()->endOfDay();
            } else {
                $batasAkhirInput = $hariKe4->endOfDay(); 
            }

            $now = Carbon::now();
            $isLocked = false;

            if ($now->greaterThan($batasAkhirInput)) {
                $pengajuans = DB::table('N_EMI_LAB_Pengajuan_Buka_Ulang_Uji_Sampel')
                    ->where('No_Sampel', '=', $base_no_sampel)
                    ->orderByDesc('Waktu_Akhir')
                    ->get();

                if ($pengajuans->isNotEmpty()) {
                    $isValid = false;

                    foreach ($pengajuans as $pengajuan) {
                        $waktuMulai = Carbon::parse($pengajuan->Waktu_Mulai);
                        $waktuAkhir = Carbon::parse($pengajuan->Waktu_Akhir);

                        if ($now->between($waktuMulai, $waktuAkhir)) {
                            $isValid = true;
                            break; 
                        }
                    }

                    if (!$isValid) {
                        $isLocked = true;
                    }
                } else {
                    $isLocked = true;
                }

                if ($isLocked) {
                    return response()->json([
                        'success' => true,
                        'status' => 200,
                        'locked' => true,
                        'message' => 'Sampel dengan No ' . $base_no_sampel . 
                            ' sudah melewati batas input dan tidak ada/melampaui waktu buka ulang. Tidak bisa dilakukan input data lagi.'
                    ], 200);
                }
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

            $analisaList = DB::table('N_EMI_LAB_Barang_Analisa as ba')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ba.Id_Jenis_Analisa', '=', 'ja.id')
                ->leftJoin('N_EMI_LAB_Mesin_Analisa as ma', 'ja.Id_Mesin', '=', 'ma.No_Urut')
                ->where('ba.Kode_Barang', $sampelRow->Kode_Barang)
                ->where('ba.Id_Master_Mesin', $sampelRow->Id_Mesin)
                ->where('ba.Id_User', Auth::user()->UserId)
                ->where('ba.Flag_Aktif', 'Y')
                ->where('ba.Kode_Role', 'LAB')
                // ja.Kode_Role intentionally not filtered — ba.Kode_Role is the authoritative
                // access-control field. Filtering on ja.Kode_Role too caused analisa to silently
                // disappear when the Jenis_Analisa master row has a different Kode_Role than
                // the Barang_Analisa assignment (e.g. ja=FLM but ba=LAB).
                ->select(
                    'ja.id as analisa_id',
                    'ja.Kode_Analisa',
                    'ja.Jenis_Analisa',
                    'ma.Nama_Mesin as Nama_Mesin_Analisa'
                )
                ->get();


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

            $isResampling = $sampelRow->Flag_FG === 'Y';

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
                    'is_resampling' => $isResampling,
                    'analisa' => $analisa,
                ]
            ]);

        } catch (\Exception $e) {
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), [
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

        $sampelRow = DB::table('N_EMI_LAB_PO_Sampel as q')
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

        $checkedResempling = DB::table('N_EMI_LAB_Uji_Sampel_Resampling_Log as r')
            ->join('N_EMI_LAB_Jenis_Analisa as ja', 'r.Id_Jenis_Analisa', '=', 'ja.id')
            ->leftJoin('N_EMI_LAB_Palatabilitas_Pembanding as pb', 'r.Id_Pembanding', '=', 'pb.Id_Pembanding')
            ->select(
                'r.*',
                'ja.Jenis_Analisa',
                'ja.Kode_Aktivitas_Lab',
                'pb.Nama_Pembanding',
                'pb.Kode_Barang_Pembanding'
            )
            ->where('r.No_Po_Sampel', $sampelRow->No_Sampel)
            ->where('r.No_Sampel_Resampling_Origin', $no_sub_sampel)
            ->where('r.No_Sampel_Resampling', $no_resampling)
            ->where('r.Id_Jenis_Analisa', $Id_Jenis_Analisa)
            ->first();


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
            $ujiSampelEntries = DB::table('N_EMI_LAB_Uji_Sampel')
                ->where('No_Po_Sampel', $base_no_sampel)
                ->where('Id_Jenis_Analisa', $Id_Jenis_Analisa)
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

            $analisa->push([
                'id' => Hashids::connection('custom')->encode($analisaData->analisa_id),
                'Kode_Analisa' => $analisaData->Kode_Analisa ?? null,
                'Jenis_Analisa' => $analisaData->Jenis_Analisa,
                'Nama_Mesin' => $analisaData->Nama_Mesin_Analisa ?? null,
                'is_done' => $isDone,
            ]);
        }

        $isResampling = $sampelRow->Flag_FG === 'Y';

        // ── PLT: ambil seluruh daftar pembanding dalam session (jika PLT) ──
        $pltPembandingList = [];
        $pltSessionId      = null;

        if ($checkedResempling && ($checkedResempling->Kode_Aktivitas_Lab ?? null) === 'PLT') {
            // Primary: gunakan Id_Session dari resampling log
            $actualSessionId = $checkedResempling->Id_Session ?? null;

            // Fallback: cari dari N_EMI_LAB_Palatabilitas_Session jika log tidak punya Id_Session
            // (terjadi pada data lama sebelum fix PLT diterapkan)
            if (empty($actualSessionId)) {
                $actualSessionId = DB::table('N_EMI_LAB_Palatabilitas_Session')
                    ->where('No_Po_Sampel', $sampelRow->No_Sampel)
                    ->where('Kode_Aktivitas_Lab', 'PLT')
                    ->value('Id_Session');
            }

            if (!empty($actualSessionId)) {
                $pltSessionId = Hashids::connection('custom')->encode($actualSessionId);

                $pltPembandingList = DB::table('N_EMI_LAB_Palatabilitas_Pembanding')
                    ->where('Id_Session', $actualSessionId)
                    ->where('Flag_Aktif', 'Y')
                    ->select('Id_Pembanding', 'Urutan', 'Nama_Pembanding', 'Kode_Barang_Pembanding')
                    ->orderBy('Urutan')
                    ->orderBy('Id_Pembanding')
                    ->get()
                    ->map(fn($p) => [
                        'id_pembanding'          => Hashids::connection('custom')->encode($p->Id_Pembanding),
                        'urutan'                 => $p->Urutan,
                        'nama_pembanding'        => $p->Nama_Pembanding,
                        'kode_barang_pembanding' => $p->Kode_Barang_Pembanding,
                    ])
                    ->values()
                    ->toArray();
            }
        }

        $resamplingInfo = $checkedResempling ? [
            'Id_Resampling'              => Hashids::connection('custom')->encode($checkedResempling->Id_Resampling),
            'No_Po_Sampel'               => $checkedResempling->No_Po_Sampel,
            'Tahapan_Ke'                 => $checkedResempling->Tahapan_Ke,
            'No_Sampel_Resampling_Origin'=> $checkedResempling->No_Sampel_Resampling_Origin,
            'No_Sampel_Resampling'       => $checkedResempling->No_Sampel_Resampling,
            'Keterangan_Resempling'      => $checkedResempling->Keterangan,
            'Tanggal'                    => $checkedResempling->Tanggal,
            'Jam'                        => $checkedResempling->Jam,
            'Id_User'                    => $checkedResempling->Id_User,
            'Id_Jenis_Analisa'           => Hashids::connection('custom')->encode($checkedResempling->Id_Jenis_Analisa),
            'Flag_Selesai_Resampling'    => $checkedResempling->Flag_Selesai_Resampling,
            'Jenis_Analisa'              => $checkedResempling->Jenis_Analisa,
            // ── PLT fields ──
            'is_plt'                     => ($checkedResempling->Kode_Aktivitas_Lab ?? null) === 'PLT',
            'Id_Pembanding'              => $checkedResempling->Id_Pembanding
                                              ? Hashids::connection('custom')->encode($checkedResempling->Id_Pembanding)
                                              : null,
            'plt_session_id'             => $pltSessionId,
            'Nama_Pembanding'            => $checkedResempling->Nama_Pembanding ?? null,
            'Kode_Barang_Pembanding'     => $checkedResempling->Kode_Barang_Pembanding ?? null,
            'plt_pembanding_list'        => $pltPembandingList,
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
            $decodedId = Hashids::connection('custom')->decode($id_analisa);

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

            $switchIds = $parameters->where('type_inputan', 'Switch')
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

            $hashedParameters = $parameters->map(function ($param) use ($switchOptions) {
                $options = null;

                if ($param->type_inputan === 'Switch' && isset($switchOptions[$param->id_qc])) {
                    $options = $switchOptions[$param->id_qc]->map(function ($opt) {
                        return [
                            'value' => $opt->Keterangan,
                            'label' => $opt->Label_Keterangan ?? $opt->Keterangan
                        ];
                    })->values()->toArray();
                }

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
                    'sesi_foto' => $param->sesi_foto,
                    'option' => $options
                ];
            });

            $hashedFormula = null;
            $isPerhitungan = $parameters->first()->flag_perhitungan === 'Y';

            if ($isPerhitungan) {
                $formulas = DB::table('N_EMI_LAB_Perhitungan as p')
                    ->join('N_EMI_LAB_Jenis_Analisa as ja', 'p.Id_Jenis_Analisa', '=', 'ja.id')
                    ->where('p.Id_Jenis_Analisa', $realId)
                    ->select(
                        'p.Id',
                        'p.Id_Jenis_Analisa',
                        'ja.Kode_Analisa',
                        'p.Rumus',
                        'p.Nama_Kolom',
                        'p.Hasil_Perhitungan'
                    )
                    ->get();

                $hashedFormula = $formulas->map(function ($rumus) {
                    $processedRumus = preg_replace_callback(
                        '/\[(\d+)\]/',
                        function ($matches) {
                            return '[' . Hashids::connection('custom')->encode($matches[1]) . ']';
                        },
                        $rumus->Rumus
                    );

                    return [
                        'id' => Hashids::connection('custom')->encode($rumus->Id),
                        'id_jenis_analisa' => Hashids::connection('custom')->encode($rumus->Id_Jenis_Analisa),
                        'rumus' => $processedRumus,
                        'kode_analisa' => $rumus->Kode_Analisa,
                        'nama_kolom' => $rumus->Nama_Kolom,
                        'digit' => $rumus->Hasil_Perhitungan,
                    ];
                });
            }
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
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), [
                'id_analisa' => $id_analisa,
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'success' => false,
                'status' => 500,
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

        $getNoSampel = DB::table('N_EMI_LAB_PO_Sampel_Multi_QrCode')
            ->select(
                'N_EMI_LAB_PO_Sampel_Multi_QrCode.No_Po_Multi as no_ticket',
                'N_EMI_LAB_PO_Sampel_Multi_QrCode.No_Po_Sampel as sampel',
            )
            ->where('N_EMI_LAB_PO_Sampel_Multi_QrCode.No_Po_Multi', $no_PO_Multiqr)
            ->first();
        
        if(empty($getNoSampel)){
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Data Dengan Nomor '. $no_PO_Multiqr.' Tidak Ditemukan'
            ], 404);
        }
            
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

            $getNoSampel = DB::table('N_EMI_LAB_PO_Sampel_Multi_QrCode')
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

            $resamplingCheck = DB::table('N_EMI_LAB_Uji_Sampel_Resampling_Log')
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

            $isDone = DB::table('N_EMI_LAB_Uji_Sampel')
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
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), [
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

    public function getPoSampelMultiQrDetailForRumus($no_PO_Multiqr, $id_jenis_analisa)
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

        $jenisAnalisa = DB::table('N_EMI_LAB_Jenis_Analisa')
                        ->where('id', $decoded_id_jenis_analisa)
                        ->first();

        if (!$jenisAnalisa) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Jenis Analisa tidak ditemukan.'
            ], 404);
        }

        $kodeAnalisa = $jenisAnalisa->Kode_Analisa;

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
                        // Jika bukan -888888 atau -999999, format seperti biasa
                        $valueParameter = round((float)$itemArray['Value_Parameter'], 4);
                    }
                } else {
                    // Jika Kode_Analisa bukan MBLG-STR, format seperti biasa
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
                FROM N_EMI_LAB_Uji_Sampel us
                INNER JOIN N_EMI_LAB_Jenis_Analisa ja ON us.Id_Jenis_Analisa = ja.id
                INNER JOIN N_EMI_LAB_PO_Sampel po ON us.No_Po_Sampel = po.No_Sampel
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
            FROM N_EMI_LAB_Uji_Sampel_Sementara
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
                FROM N_EMI_LAB_Uji_Sampel_Detail_Sementara
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
                    'is_done' => $isDone->Flag_Selesai === 'Y' ? true : false,
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
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
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
            FROM N_EMI_LAB_PO_Sampel_Multi_QrCode psmq
            LEFT JOIN N_EMI_LAB_Uji_Sampel us 
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
            FROM N_EMI_LAB_Uji_Sampel us
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
                FROM N_EMI_LAB_Uji_Sampel us
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
                    FROM N_EMI_LAB_Uji_Sampel_Detail
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
            FROM N_EMI_LAB_Uji_Sampel us
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
                FROM N_EMI_LAB_Uji_Sampel us
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
                    FROM N_EMI_LAB_Uji_Sampel_Detail
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
            $id_jenis_analisa = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Jenis Analisa tidak valid.'
            ], 400);
        }

        $parts = explode('-', $no_po_sampel);
        $base_no_sampel = count($parts) > 2 ? implode('-', array_slice($parts, 0, -1)) : $no_po_sampel;

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
                'status' => 404,
                'message' => 'Data Tidak Ditemukan!'
            ], 404);
        }

        // Ambil aktivitas uji sampel
        $result = collect(
            DB::table('N_EMI_LAB_Activity_Uji_Sampel AS aus')
                ->join('N_EMI_LAB_Jenis_Analisa AS ja', 'aus.Id_Jenis_Analisa', '=', 'ja.id')
                ->join('N_EMI_LAB_PO_Sampel AS ps', 'aus.No_Po_Sampel', '=', 'ps.No_Sampel')
                ->select(
                    'aus.*',
                    'ps.No_Po',
                    'ps.No_Split_Po',
                    'ps.No_Batch',
                    'ja.Kode_Analisa',
                    'ja.Jenis_Analisa',
                    'ja.Flag_Perhitungan'
                )
                ->where('aus.No_Po_Sampel', $base_no_sampel)
                ->where('aus.No_Fak_Sub_Po', $no_PO_Multiqr)
                ->where('aus.Id_Jenis_Analisa', $id_jenis_analisa)
                ->orderBy('aus.Id_Log_Activity', 'desc')
                ->get()
        );

        // Ambil data hasil uji dan join ke perhitungan untuk dapatkan digit
        $getHasilAnalisa = collect(
            DB::table('N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail AS ahd')
                ->leftJoin('N_EMI_LAB_Activity_Uji_Sampel AS aus', 'aus.Id_Log_Activity', '=', 'ahd.Id_Log_Activity_Sampel')
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
                ->whereIn('ahd.No_Po_Sampel', $result->pluck('No_Po_Sampel'))
                ->whereIn('ahd.No_Fak_Sub_Po', $result->pluck('No_Fak_Sub_Po'))
                ->whereIn('ahd.Id_Log_Activity_Sampel', $result->pluck('Id_Log_Activity'))
                ->where('ahd.Id_Jenis_Analisa', $id_jenis_analisa)
                ->get()
        );

        // Ambil parameter analisa
        $getParameterAnalisa = collect(
            DB::table('N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail')
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
                ->whereIn('No_Po_Sampel', $result->pluck('No_Po_Sampel'))
                ->whereIn('No_Fak_Sub_Po', $result->pluck('No_Fak_Sub_Po'))
                ->whereIn('Id_Log_Activity_Sampel', $result->pluck('Id_Log_Activity'))
                ->where('Id_Jenis_Analisa', $id_jenis_analisa)
                ->get()
        );

        // Map hasil akhir
        $finalResult = $result->map(function ($item) use ($getHasilAnalisa, $getParameterAnalisa) {
            /**
             * @var TValue $item
             */
    
            $filteredHasil = $getHasilAnalisa->where('Id_Log_Activity_Sampel', $item->Id_Log_Activity)->values();
            $filteredParameter = $getParameterAnalisa->where('Id_Log_Activity_Sampel', $item->Id_Log_Activity)->values();

            $encodedHasil = $filteredHasil->map(function ($hasilItem) {
                /**
                 * @var TValue $hasilItem
                 */
                $digit = is_numeric($hasilItem->Pembulatan_Digit) ? (int) $hasilItem->Pembulatan_Digit : 2;

                $hasilItem->Value_Lama = is_numeric($hasilItem->Value_Lama)
                    ? number_format((float)$hasilItem->Value_Lama, $digit, '.', '')
                    : $hasilItem->Value_Lama;

                $hasilItem->Value_Baru = is_numeric($hasilItem->Value_Baru)
                    ? number_format((float)$hasilItem->Value_Baru, $digit, '.', '')
                    : $hasilItem->Value_Baru;

                return $hasilItem;
            });

            // Format parameter
            $encodedParameter = $filteredParameter->map(function ($paramItem) {
                /**
                 * @var TValue $paramItem
                 */
                $paramItem->Id_Log_Activity_Sampel = Hashids::connection('custom')->encode($paramItem->Id_Log_Activity_Sampel);

                $paramItem->Value_Baru = $paramItem->Value_Baru !== null
                    ? round($paramItem->Value_Baru, 4)
                    : null;

                $paramItem->Value_Lama = $paramItem->Value_Lama !== null
                    ? round($paramItem->Value_Lama, 4)
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
                'Alasan' => $encodedParameter
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

    public function getDataTrackingInformasiNotMultiQrCode($no_po_sampel,$id_jenis_analisa)
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
            DB::table('N_EMI_LAB_Activity_Uji_Sampel')
                ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LAB_Activity_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
                ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Activity_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
                ->select('N_EMI_LAB_Activity_Uji_Sampel.*', 'N_EMI_LAB_PO_Sampel.No_Po', 'N_EMI_LAB_PO_Sampel.No_Split_Po', 'N_EMI_LAB_PO_Sampel.No_Batch', 'N_EMI_LAB_Jenis_Analisa.Kode_Analisa', 'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa', 'N_EMI_LAB_Jenis_Analisa.Flag_Perhitungan')
                ->where('N_EMI_LAB_Activity_Uji_Sampel.No_Po_Sampel', $no_po_sampel)
                ->where('N_EMI_LAB_Activity_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa)
                ->orderBy('N_EMI_LAB_Activity_Uji_Sampel.Id_Log_Activity', 'desc') 
                ->get()
        );

        $getHasilAnalisa = collect(
            DB::table('N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail')
                ->select('No_Po_Sampel', 'Id_Log_Activity_Sampel' ,'No_Fak_Sub_Po',  'Value_Baru', 'Value_Lama', 'Tanggal', 'Jam', 'Id_User', 'Status_Submit')
                ->whereIn('No_Po_Sampel', $result->pluck('No_Po_Sampel'))
                ->whereIn('Id_Log_Activity_Sampel', $result->pluck('Id_Log_Activity'))
                ->where('Id_Jenis_Analisa', $id_jenis_analisa)
                ->get()
        );

        $getParameterAnalisa = collect(
            DB::table('N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail')
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
        $checkedAkses = Session::get("user_permissions");
        $permissionKonten = $checkedAkses['permission_konten'] ?? [];

        $allowedAnalisaIds = [];
            if (isset($permissionKonten['Validasi Hasil Analisa']) && is_array($permissionKonten['Validasi Hasil Analisa'])) {
                foreach ($permissionKonten['Validasi Hasil Analisa'] as $akses) {
                    if (isset($akses['flag']) && $akses['flag'] === 'Y' && isset($akses['id_jenis_analisa'])) {
                        $allowedAnalisaIds[] = $akses['id_jenis_analisa'];
                    }
                }
            }

            $searchQuery = $request->input('q', '');
            $limit = $request->input('limit', 10);

            if (empty($allowedAnalisaIds)) {
                return response()->json([
                    'success' => true,
                    'status'  => 200,
                    'message' => "Data tidak ditemukan (Tidak ada akses jenis analisa yang valid).",
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
        $filterTanggalMulai = $request->input('tanggal_mulai');
        $filterTanggalSelesai = $request->input('tanggal_selesai');
        $filterQrCode = $request->input('qrcode');
        $filterStatus = $request->input('status');

        $baseQuery = DB::table('N_EMI_LAB_Uji_Sampel')
            ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
            ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
            ->select(
                'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa',
                'N_EMI_LAB_Uji_Sampel.No_Po_Sampel',
                'N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode',
                'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa',
                'N_EMI_LAB_Jenis_Analisa.Kode_Analisa',
                DB::raw('MAX(N_EMI_LAB_Uji_Sampel.Tanggal) as Tanggal')
            )
            ->whereIn('N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', $allowedAnalisaIds)
            ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
            ->whereNull('N_EMI_LAB_Uji_Sampel.Flag_Selesai')
            ->where('N_EMI_LAB_Uji_Sampel.Status_Keputusan_Sampel', 'menunggu')
            ->whereNull('N_EMI_LAB_PO_Sampel.Flag_Trial_Produksi')
            ->groupBy(
                'N_EMI_LAB_Uji_Sampel.No_Po_Sampel',
                'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa',
                'N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode',
                'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa',
                'N_EMI_LAB_Jenis_Analisa.Kode_Analisa'
            );

        if (!empty($searchQuery)) {
            $baseQuery->where(function ($query) use ($searchQuery) {
                $query->where('N_EMI_LAB_Uji_Sampel.No_Po_Sampel', 'like', "%$searchQuery%")
                    ->orWhere('N_EMI_LAB_PO_Sampel.No_Po', 'like', "%$searchQuery%")
                    ->orWhere('N_EMI_LAB_PO_Sampel.No_Split_Po', 'like', "%$searchQuery%")
                    ->orWhere('N_EMI_LAB_PO_Sampel.No_Batch', 'like', "%$searchQuery%");
            });
        }

        if ($filterTanggalMulai && $filterTanggalSelesai) {
            $baseQuery->whereBetween('N_EMI_LAB_Uji_Sampel.Tanggal', [$filterTanggalMulai, $filterTanggalSelesai]);
        }

        if ($filterQrCode) {
            if ($filterQrCode === 'multi') {
                $baseQuery->where('N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode', 'Y');
            } elseif ($filterQrCode === 'single') {
                $baseQuery->where(function ($query) {
                    $query->where('N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode', '!=', 'Y')
                        ->orWhereNull('N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode');
                });
            }
        }

        if ($filterStatus === 'lolos') {
            $baseQuery->whereNotExists(function ($subQ) {
                $subQ->select(DB::raw(1))
                     ->from('N_EMI_LAB_Uji_Sampel as chk')
                     ->whereColumn('chk.No_Po_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel')
                     ->whereColumn('chk.Id_Jenis_Analisa', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa')
                     ->whereNull('chk.Status')
                     ->whereNull('chk.Flag_Selesai')
                     ->where(function ($q2) {
                         $q2->whereNull('chk.Flag_Layak')->orWhere('chk.Flag_Layak', '!=', 'Y');
                     })
                     ->whereRaw('chk.Tahapan_Ke = (SELECT MAX(mx.Tahapan_Ke) FROM N_EMI_LAB_Uji_Sampel mx WHERE mx.No_Po_Sampel = chk.No_Po_Sampel AND mx.Id_Jenis_Analisa = chk.Id_Jenis_Analisa AND mx.Status IS NULL AND mx.Flag_Selesai IS NULL)');
            });
        } elseif ($filterStatus === 'tidak_lolos') {
            $baseQuery->whereExists(function ($subQ) {
                $subQ->select(DB::raw(1))
                     ->from('N_EMI_LAB_Uji_Sampel as chk')
                     ->whereColumn('chk.No_Po_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel')
                     ->whereColumn('chk.Id_Jenis_Analisa', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa')
                     ->whereNull('chk.Status')
                     ->whereNull('chk.Flag_Selesai')
                     ->where(function ($q2) {
                         $q2->whereNull('chk.Flag_Layak')->orWhere('chk.Flag_Layak', '!=', 'Y');
                     })
                     ->whereRaw('chk.Tahapan_Ke = (SELECT MAX(mx.Tahapan_Ke) FROM N_EMI_LAB_Uji_Sampel mx WHERE mx.No_Po_Sampel = chk.No_Po_Sampel AND mx.Id_Jenis_Analisa = chk.Id_Jenis_Analisa AND mx.Status IS NULL AND mx.Flag_Selesai IS NULL)');
            });
        }

        // Urutkan menggunakan alias `Tanggal` yang didapat dari agregasi MAX()
        $paginatedData = $baseQuery->orderByDesc('Tanggal')->paginate($limit);
        $noPoSampelList = $paginatedData->pluck('No_Po_Sampel')->toArray();

        // 2. Fetch ALL history details for these samples
        $allInfoRows = DB::table('N_EMI_LAB_Uji_Sampel')
            ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
            ->join('EMI_Master_Mesin', 'N_EMI_LAB_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
            ->select(
                'N_EMI_LAB_Uji_Sampel.No_Po_Sampel',
                'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa',
                'N_EMI_LAB_PO_Sampel.No_Po',
                'N_EMI_LAB_PO_Sampel.Tanggal as Tanggal_Registrasi',
                'N_EMI_LAB_PO_Sampel.Jam as Jam_Registrasi',
                'N_EMI_LAB_PO_Sampel.Kode_Barang',
                'N_EMI_LAB_PO_Sampel.No_Split_Po',
                'N_EMI_LAB_PO_Sampel.No_Batch',
                'N_EMI_LAB_PO_Sampel.Flag_Trial_Produksi', // Tambahkan field ini
                'N_EMI_LAB_Uji_Sampel.Jam',
                'N_EMI_LAB_Uji_Sampel.Tanggal',
                'EMI_Master_Mesin.Nama_Mesin',
                'N_EMI_LAB_Uji_Sampel.Flag_Layak',
                'N_EMI_LAB_Uji_Sampel.Id_User',
                'N_EMI_LAB_Uji_Sampel.Tahapan_Ke'
            )
            ->whereIn('N_EMI_LAB_Uji_Sampel.No_Po_Sampel', $noPoSampelList)
            ->orderByDesc('N_EMI_LAB_Uji_Sampel.Tahapan_Ke')
            ->orderByDesc('N_EMI_LAB_Uji_Sampel.Tanggal')
            ->orderByDesc('N_EMI_LAB_Uji_Sampel.Jam')
            ->get();

        $groupedInfos = $allInfoRows->groupBy('No_Po_Sampel');

        $kodeBarangList = $allInfoRows->pluck('Kode_Barang')->unique()->filter()->toArray();
        $barangList = DB::table('N_EMI_View_Barang')
            ->whereIn('Kode_Barang', $kodeBarangList)
            ->pluck('Nama', 'Kode_Barang');

        $paginatedData->getCollection()->transform(function ($item) use ($groupedInfos, $barangList) {
            $rawIdJenisAnalisa = $item->Id_Jenis_Analisa;
            
            $item->Id_Jenis_Analisa = Hashids::connection('custom')->encode($item->Id_Jenis_Analisa);

            $sampleHistory = $groupedInfos->get($item->No_Po_Sampel);
            
            $specificInfo = null;
            if ($sampleHistory) {
                $specificInfo = $sampleHistory->where('Id_Jenis_Analisa', $rawIdJenisAnalisa)->first();
            }

            if ($specificInfo) {
                $item->Id_User            = $specificInfo->Id_User;
                $item->Jam                = $specificInfo->Jam;
                $item->Flag_Layak         = $specificInfo->Flag_Layak;
                
                $item->Tanggal_Registrasi = $specificInfo->Tanggal_Registrasi;
                $item->Jam_Registrasi     = $specificInfo->Jam_Registrasi;
                $item->Nama_Barang        = $barangList[$specificInfo->Kode_Barang] ?? null;
                $item->po_info = [
                    'No_Po'               => $specificInfo->No_Po,
                    'No_Split_Po'         => $specificInfo->No_Split_Po,
                    'No_Batch'            => $specificInfo->No_Batch,
                    'Kode_Barang'         => $specificInfo->Kode_Barang,
                    'Nama_Mesin'          => $specificInfo->Nama_Mesin,
                    'Flag_Trial_Produksi' => $specificInfo->Flag_Trial_Produksi, // Map ke po_info
                ];
            } else {
                $item->Id_User = null;
                $item->Jam = null;
                $item->Flag_Layak = null;
                $item->Tanggal_Registrasi = null;
                $item->Jam_Registrasi = null;
                $item->Nama_Barang = null;
                $item->po_info = null;
            }

            $autoLolosKodes = ['PSZ'];
            $kodeAnalisa = trim($item->Kode_Analisa);

            if (in_array($kodeAnalisa, $autoLolosKodes)) {
                $item->Status_Sampel = "Lolos Uji";
            } else {
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
        $checkedAkses = Session::get("user_permissions");
        $permissionKonten = $checkedAkses['permission_konten'] ?? [];

        $allowedAnalisaIds = [];
        if (isset($permissionKonten['Finalisasi Sampel']) && is_array($permissionKonten['Validasi Hasil Analisa'])) {
            foreach ($permissionKonten['Finalisasi Sampel'] as $akses) {
                if (isset($akses['flag']) && $akses['flag'] === 'Y' && isset($akses['id_jenis_analisa'])) {
                    $allowedAnalisaIds[] = $akses['id_jenis_analisa'];
                }
            }
        }

        $perPage = $request->input('limit', 10);
        $page = $request->input('page', 1);
        
        if (empty($allowedAnalisaIds)) {
            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => "Data tidak ditemukan (Tidak ada akses jenis analisa yang valid).",
                'result'  => [
                    'data' => [],
                    'pagination' => [
                        'page'      => 1,
                        'limit'     => (int)$perPage,
                        'totalPage' => 0,
                        'totalData' => 0,
                    ]
                ]
            ], 200);
        }
        
        $search = $request->input('search');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $qrType = $request->input('qr_type'); // Filter QR
        $totalAnalisa = $request->input('total_analisa'); // Menerima filter total analisa

        $query = DB::table('N_EMI_LAB_Uji_Sampel as uji')
            ->join('N_EMI_LAB_PO_Sampel as po', 'uji.No_Po_Sampel', '=', 'po.No_Sampel')
            ->join('N_EMI_View_Barang as brg', 'po.Kode_Barang', '=', 'brg.Kode_Barang')
            ->select(
                'uji.No_Po_Sampel',
                DB::raw('MAX(uji.Tanggal) as Tanggal'),
                DB::raw('MAX(uji.Jam) as Jam'),
                'uji.Flag_Multi_QrCode',
                'po.No_Po',
                'po.No_Split_Po',
                'po.Kode_Barang',
                'brg.Nama as Nama_Barang',
                'po.Flag_Trial_Produksi' 
            )
            ->whereIn('uji.Id_Jenis_Analisa', $allowedAnalisaIds)
            ->whereNull('po.Flag_Trial_Produksi')
            // Tambahan kondisi untuk tabel N_EMI_LAB_PO_Sampel (alias: po)
            ->whereNull('po.Flag_Selesai')
            ->whereNull('po.Status')
            // Kondisi tabel uji tetap dipertahankan sesuai aslinya
            ->whereNull('uji.Status')
            ->where('uji.Flag_Selesai', 'Y')
            ->whereNull('uji.Flag_Final')
            ->where('uji.Status_Keputusan_Sampel', 'terima')
            ->where(function($q) {
                $q->where('uji.Flag_Resampling', '!=', 'Y')
                ->orWhereNull('uji.Flag_Resampling');
            });

        if (!empty($startDate) && !empty($endDate)) {
            $query->whereBetween('uji.Tanggal', [$startDate, $endDate]);
        }

        // Filter QR Code Type
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

        // Searching
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
            'brg.Nama',
            'po.Flag_Trial_Produksi'
        );

        // FILTER TOTAL ANALISA (Gunakan HAVING setelah GROUP BY)
        if (!empty($totalAnalisa)) {
            $query->having(DB::raw('COUNT(DISTINCT uji.Id_Jenis_Analisa)'), '=', (int)$totalAnalisa);
        }

        $query->orderByDesc(DB::raw('MAX(uji.Tanggal)'))
            ->orderByDesc(DB::raw('MAX(uji.Jam)'));

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);
        $items = $paginated->items();

        // 1. Ambil list No_Po_Sampel dari data yang sedang di-paginate
        $poSampelIds = collect($items)->pluck('No_Po_Sampel')->toArray();

        if (!empty($poSampelIds)) {
            // 2. Lakukan Query Join ke tabel Jenis Analisa khusus untuk data di halaman ini
            $analisaDetails = DB::table('N_EMI_LAB_Uji_Sampel as uji')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'uji.Id_Jenis_Analisa', '=', 'ja.id')
                ->whereIn('uji.No_Po_Sampel', $poSampelIds)
                ->whereNull('uji.Status')
                ->where('uji.Flag_Selesai', 'Y')
                ->where('uji.Status_Keputusan_Sampel', 'terima')
                ->where(function($q) {
                    $q->where('uji.Flag_Resampling', '!=', 'Y')
                    ->orWhereNull('uji.Flag_Resampling');
                })
                ->select(
                    'uji.No_Po_Sampel',
                    'ja.id',
                    'ja.Kode_Analisa',
                    'ja.Jenis_Analisa',
                    'ja.Kode_Aktivitas_Lab'
                )
                ->distinct()
                ->get()
                ->groupBy('No_Po_Sampel');

            // PLT session context per No_Po_Sampel
            $pltSessions = DB::table('N_EMI_LAB_Palatabilitas_Session as ps')
                ->leftJoin('N_EMI_LAB_Palatabilitas_Pembanding as pp', function ($j) {
                    $j->on('pp.Id_Session', '=', 'ps.Id_Session')->where('pp.Flag_Aktif', '=', 'Y');
                })
                ->whereIn('ps.No_Po_Sampel', $poSampelIds)
                ->where('ps.Kode_Aktivitas_Lab', 'PLT')
                ->select(
                    'ps.No_Po_Sampel',
                    'ps.Id_Session',
                    'ps.Status_Session',
                    DB::raw('COUNT(pp.Id_Pembanding) as jumlah_pembanding'),
                    DB::raw("STRING_AGG(pp.Nama_Pembanding, ', ') as nama_pembanding_list")
                )
                ->groupBy('ps.No_Po_Sampel', 'ps.Id_Session', 'ps.Status_Session')
                ->get()
                ->keyBy('No_Po_Sampel');

            // 3. Sisipkan Total dan Array Detail Jenis Analisa + PLT context ke dalam hasil response
            $items = collect($items)->map(function ($item) use ($analisaDetails, $pltSessions) {
                $analisa = $analisaDetails->get($item->No_Po_Sampel, collect());

                $item->Total_Jenis_Analisa = $analisa->count();
                $item->Detail_Jenis_Analisa = $analisa->values()->toArray();

                $pltSession = $pltSessions->get($item->No_Po_Sampel);
                if ($pltSession && (int)$pltSession->jumlah_pembanding > 0) {
                    $item->plt_context = [
                        'ada_plt'            => true,
                        'jumlah_pembanding'  => (int)$pltSession->jumlah_pembanding,
                        'nama_pembanding'    => $pltSession->nama_pembanding_list,
                        'status_session'     => $pltSession->Status_Session,
                        'session_final'      => $pltSession->Status_Session === 'F',
                    ];
                } else {
                    $adaPlt = $analisa->contains('Kode_Aktivitas_Lab', 'PLT');
                    $item->plt_context = $adaPlt ? ['ada_plt' => true, 'jumlah_pembanding' => 0] : null;
                }

                return $item;
            })->toArray();
        }

        return ResponseHelper::successWithPaginationV2(
            $items,
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
            DB::table('N_EMI_LAB_Uji_Sampel')
                ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
                ->select(
                    'N_EMI_LAB_Uji_Sampel.*',
                    'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa',
                    'N_EMI_LAB_Jenis_Analisa.Kode_Analisa'
                )
                ->where('N_EMI_LAB_Uji_Sampel.No_Po_Sampel', $No_Po_Sampel)
                ->where('N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa)
                ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
                ->whereNull('N_EMI_LAB_Uji_Sampel.Flag_Selesai')
                ->where('N_EMI_LAB_Uji_Sampel.Status_Keputusan_Sampel', 'menunggu')
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
            DB::table('N_EMI_LAB_Uji_Sampel as us')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'us.Id_Jenis_Analisa', '=', 'ja.id')
                ->leftJoin('N_EMI_LAB_Palatabilitas_Pembanding as pb', 'us.Id_Pembanding', '=', 'pb.Id_Pembanding')
                ->select(
                'us.*',
                'ja.Jenis_Analisa',
                'ja.Kode_Analisa',
                'ja.Kode_Aktivitas_Lab',
                'pb.Nama_Pembanding',
                'pb.Kode_Barang_Pembanding'
                )
                ->where('us.No_Po_Sampel', $No_Po_Sampel)
                ->where('us.No_Fak_Sub_Po', $No_Fak_Sub_Po)
                ->where('us.Id_Jenis_Analisa', $id_jenis_analisa)
                ->whereNull('us.Status')
                ->whereNull('us.Flag_Selesai')
                ->where('us.Status_Keputusan_Sampel', 'menunggu')
                ->orderByDesc('us.Tanggal')
                ->get()
        )
        // Step 1: Encode ID Jenis Analisa dulu
        ->map(function ($item) {
            /** @var \stdClass $item */
            $item->Id_Jenis_Analisa = Hashids::connection('custom')->encode($item->Id_Jenis_Analisa);
            $item->is_plt = ($item->Kode_Aktivitas_Lab ?? null) === 'PLT';
            return $item;
        })
        // Step 2: Uniquekan berdasarkan ID yang sudah di-encode
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
            DB::table('N_EMI_LAB_Uji_Sampel as us')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'us.Id_Jenis_Analisa', '=', 'ja.id')
                ->leftJoin('N_EMI_LAB_Palatabilitas_Pembanding as pb', 'us.Id_Pembanding', '=', 'pb.Id_Pembanding')
                ->select(
                'us.*',
                'ja.Jenis_Analisa',
                'ja.Kode_Analisa',
                'ja.Kode_Aktivitas_Lab',
                'pb.Nama_Pembanding',
                'pb.Kode_Barang_Pembanding'
                )
                ->where('us.No_Po_Sampel', $No_Po_Sampel)
                ->whereNull('us.Status')
                ->whereNull('us.Flag_Selesai')
                ->where('us.Status_Keputusan_Sampel', 'menunggu')
                ->orderByDesc('us.Tanggal')
                ->get()
        )
        // Step 1: Encode ID Jenis Analisa dulu
        ->map(function ($item) {
            /** @var \stdClass $item */
            $item->Id_Jenis_Analisa = Hashids::connection('custom')->encode($item->Id_Jenis_Analisa);
            $item->is_plt = ($item->Kode_Aktivitas_Lab ?? null) === 'PLT';
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
            DB::table('N_EMI_LAB_Uji_Sampel as us')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'us.Id_Jenis_Analisa', '=', 'ja.id')
                ->join('N_EMI_LAB_PO_Sampel as po', 'us.No_Po_Sampel', '=', 'po.No_Sampel')
                ->leftJoin('N_EMI_LAB_Palatabilitas_Pembanding as pb', 'us.Id_Pembanding', '=', 'pb.Id_Pembanding')
                ->select(
                    'us.*',
                    'ja.Jenis_Analisa',
                    'ja.Kode_Analisa',
                    'ja.Kode_Aktivitas_Lab',
                    'ja.Flag_Perhitungan',
                    'pb.Nama_Pembanding',
                    'pb.Kode_Barang_Pembanding'
                )
                ->where('us.No_Po_Sampel', $No_Po_Sampel)
                ->whereNull('us.Status')
                ->whereNull('po.Flag_Trial_Produksi')
                ->where(function ($q) {
                    // terima atau sudah flag_final (non-perhitungan yang otomatis Y)
                    $q->where(function ($inner) {
                        $inner->where('us.Flag_Selesai', 'Y')
                              ->where('us.Status_Keputusan_Sampel', 'terima');
                    })->orWhere('us.Flag_Final', 'Y');
                })
                ->where(function ($q) {
                    $q->where('us.Flag_Resampling', '!=', 'Y')
                      ->orWhereNull('us.Flag_Resampling');
                })
                ->orderByDesc('us.Tanggal')
                ->get()
        )->map(function ($item) {
            /** @var object $item */
            $item->Id_Jenis_Analisa = Hashids::connection('custom')->encode($item->Id_Jenis_Analisa);
            $item->is_plt = ($item->Kode_Aktivitas_Lab ?? null) === 'PLT';
            return $item;
        })->unique(function ($item) {
            // PLT analisa: group all pembanding under one entry (unique by sampel + analisa only).
            // The detail table groups rows by No_Faktur, so each pembanding appears as its own
            // row with a Pembanding column — matching how monitoring displays PLT.
            return $item->No_Po_Sampel . '-' . $item->Id_Jenis_Analisa;
        })->values();

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
            $getDataSubPo = DB::table('N_EMI_LAB_PO_Sampel_Multi_QrCode as multi')
                ->select('No_Po_Sampel','No_Po_Multi')
                ->where('multi.No_Po_Sampel', $no_Sampel)
                ->whereNotExists(function ($query) use ($no_Sampel, $id_jenis_analisa) {
                    $query->select(DB::raw(1))
                        ->from('N_EMI_LAB_Uji_Sampel as uji')
                        ->whereColumn('uji.No_Fak_Sub_Po', 'multi.No_Po_Multi')
                        ->where('uji.No_Po_Sampel', $no_Sampel)
                        ->where('uji.Id_Jenis_Analisa', $id_jenis_analisa);
                })
                ->get();

            return response()->json([
                'success' => true,
                'status' => 200,
                'result' => $getDataSubPo
            ], 200);
        }catch(\Exception $e){
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan"
            ], 500);
        }
    }

    /**
     * GET /api/v1/lab/hasil-analisa/per-produk/semua
     * Mengembalikan semua No_Po_Sampel yang sudah selesai analisa (untuk view Per Produk).
     */
    public function getDataHasilAnalisaSelesaiPerProduk(Request $request)
    {
        $page       = (int) $request->input('page', 1);
        $limit      = (int) $request->input('limit', 20);
        $search     = $request->input('q');
        $qrcode     = $request->input('qrcode');
        $status     = $request->input('status', 'terima');
        $tipe       = $request->input('tipe_produksi');
        $dateFrom   = $request->input('tanggal_mulai');
        $dateTo     = $request->input('tanggal_selesai');

        $query = DB::table('N_EMI_LAB_Uji_Sampel as uji')
            ->join('N_EMI_LAB_PO_Sampel as po', 'uji.No_Po_Sampel', '=', 'po.No_Sampel')
            ->join('N_EMI_View_Barang as brg', 'po.Kode_Barang', '=', 'brg.Kode_Barang')
            ->join('EMI_Master_Mesin as mesin', 'po.Id_Mesin', '=', 'mesin.Id_Master_Mesin')
            ->select(
                'uji.No_Po_Sampel',
                'po.No_Po',
                'po.No_Split_Po',
                'po.No_Batch',
                'po.Kode_Barang',
                'brg.Nama as Nama_Barang',
                'mesin.Nama_Mesin',
                'uji.Flag_Multi_QrCode',
                DB::raw("CASE WHEN po.Flag_Trial_Produksi = 'Y' THEN 'Trial Produksi' ELSE 'Produksi' END as Tipe_Produksi"),
                DB::raw('MAX(uji.Tanggal) as Tanggal_Uji'),
                DB::raw('MAX(uji.Jam) as Jam_Uji'),
                DB::raw('COUNT(DISTINCT uji.Id_Jenis_Analisa) as Total_Analisa')
            )
            ->whereNull('uji.Status')
            ->where('uji.Flag_Selesai', 'Y')
            ->where(function ($q) {
                $q->where('uji.Flag_Resampling', '!=', 'Y')->orWhereNull('uji.Flag_Resampling');
            });

        if (!empty($status))   $query->where('uji.Status_Keputusan_Sampel', $status);
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('uji.No_Po_Sampel', 'LIKE', "%{$search}%")
                  ->orWhere('po.No_Po', 'LIKE', "%{$search}%")
                  ->orWhere('brg.Nama', 'LIKE', "%{$search}%")
                  ->orWhere('po.No_Batch', 'LIKE', "%{$search}%")
                  ->orWhere('mesin.Nama_Mesin', 'LIKE', "%{$search}%");
            });
        }
        if ($qrcode === 'multi') {
            $query->where('uji.Flag_Multi_QrCode', 'Y');
        } elseif ($qrcode === 'single') {
            $query->where(function ($q) { $q->where('uji.Flag_Multi_QrCode', '!=', 'Y')->orWhereNull('uji.Flag_Multi_QrCode'); });
        }
        if ($tipe === 'trial')    $query->where('po.Flag_Trial_Produksi', 'Y');
        if ($tipe === 'produksi') $query->whereNull('po.Flag_Trial_Produksi');
        if (!empty($dateFrom) && !empty($dateTo)) $query->whereBetween('uji.Tanggal', [$dateFrom, $dateTo]);

        $query->groupBy(
            'uji.No_Po_Sampel', 'po.No_Po', 'po.No_Split_Po', 'po.No_Batch',
            'po.Kode_Barang', 'brg.Nama', 'mesin.Nama_Mesin',
            'uji.Flag_Multi_QrCode', 'po.Flag_Trial_Produksi'
        )->orderByDesc(DB::raw('MAX(uji.Tanggal)'))->orderByDesc(DB::raw('MAX(uji.Jam)'));

        $paginated = $query->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Data Ditemukan',
            'result'  => [
                'data' => $paginated->items(),
                'pagination' => [
                    'page'      => $paginated->currentPage(),
                    'limit'     => $paginated->perPage(),
                    'totalPage' => $paginated->lastPage(),
                    'totalData' => $paginated->total(),
                ],
            ],
        ], 200);
    }

    /**
     * GET /api/v1/lab/hasil-analisa/per-produk/detail-jenis/{no_po_sampel}
     * Mengembalikan daftar jenis analisa yang dimiliki sebuah No_Po_Sampel.
     */
    public function getDataJenisAnalisaBySampel($no_po_sampel)
    {
        $result = DB::table('N_EMI_LAB_Uji_Sampel as us')
            ->join('N_EMI_LAB_Jenis_Analisa as ja', 'us.Id_Jenis_Analisa', '=', 'ja.id')
            ->leftJoin('N_EMI_LAB_Palatabilitas_Pembanding as pb', 'us.Id_Pembanding', '=', 'pb.Id_Pembanding')
            ->select(
                'ja.id as Jenis_Analisa_Id_Raw',
                'ja.Jenis_Analisa',
                'ja.Kode_Analisa',
                'ja.Kode_Aktivitas_Lab',
                'ja.Flag_Perhitungan',
                'us.Flag_Layak',
                'pb.Nama_Pembanding',
                'pb.Kode_Barang_Pembanding'
            )
            ->where('us.No_Po_Sampel', $no_po_sampel)
            ->whereNull('us.Status')
            ->where('us.Flag_Selesai', 'Y')
            ->where(function ($q) {
                $q->where('us.Status_Keputusan_Sampel', 'terima')->orWhere('us.Flag_Final', 'Y');
            })
            ->where(function ($q) {
                $q->where('us.Flag_Resampling', '!=', 'Y')->orWhereNull('us.Flag_Resampling');
            })
            ->orderByDesc('us.Tanggal')
            ->get()
            ->map(function ($item) {
                $item->Id_Jenis_Analisa = Hashids::connection('custom')->encode($item->Jenis_Analisa_Id_Raw);
                $item->is_plt = ($item->Kode_Aktivitas_Lab ?? null) === 'PLT';
                unset($item->Jenis_Analisa_Id_Raw);
                return $item;
            })
            ->unique(function ($item) {
                return $item->Id_Jenis_Analisa . '-' . ($item->Nama_Pembanding ?? '');
            })
            ->values();

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Data Ditemukan',
            'result'  => $result,
        ], 200);
    }

    public function getDataHasilAnalisaSelesai()
    {
        $checkedAkses = Session::get("user_permissions");
        $permissionKonten = $checkedAkses['permission_konten'] ?? [];

        $allowedAnalisaIds = [];
        if (isset($permissionKonten['Hasil Analisa']) && is_array($permissionKonten['Hasil Analisa'])) {
                foreach ($permissionKonten['Hasil Analisa'] as $akses) {
                    if (isset($akses['flag']) && $akses['flag'] === 'Y' && isset($akses['id_jenis_analisa'])) {
                        $allowedAnalisaIds[] = $akses['id_jenis_analisa'];
                    }
                }
        }

        $result = collect(
            DB::table('N_EMI_LAB_Uji_Sampel')
                ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
                ->select(
                    DB::raw('MAX(N_EMI_LAB_Uji_Sampel.Tanggal) as Tanggal'),
                    DB::raw('MAX(N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa) as Id_Jenis_Analisa'),
                    DB::raw('MAX(N_EMI_LAB_Jenis_Analisa.Jenis_Analisa) as Jenis_Analisa'),
                    DB::raw('MAX(N_EMI_LAB_Jenis_Analisa.Kode_Analisa) as Kode_Analisa'),
                    DB::raw('MAX(N_EMI_LAB_Jenis_Analisa.Kode_Aktivitas_Lab) as Kode_Aktivitas_Lab'),

                )
                ->whereIn('N_EMI_LAB_Jenis_Analisa.id', $allowedAnalisaIds)
                ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
                ->where('N_EMI_LAB_Uji_Sampel.Flag_Selesai', 'Y')
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
        $filterTipeProduksi = $request->input('tipe_produksi');
 

        $baseQuery = DB::table('N_EMI_LAB_Uji_Sampel')
            ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
            ->join('EMI_Master_Mesin', 'N_EMI_LAB_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
            ->select(
                'N_EMI_LAB_Uji_Sampel.No_Faktur',
                'N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode',
                'N_EMI_LAB_Uji_Sampel.Flag_Perhitungan',
                'N_EMI_LAB_Uji_Sampel.No_Po_Sampel',
                'N_EMI_LAB_Uji_Sampel.Status',
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
                'N_EMI_LAB_Uji_Sampel.Status_Keputusan_Sampel',
                'N_EMI_LAB_PO_Sampel.Flag_Selesai as is_selesai',
                'N_EMI_LAB_PO_Sampel.Flag_Trial_Produksi',
                'EMI_Master_Mesin.Seri_Mesin',
                'EMI_Master_Mesin.Nama_Mesin',
                DB::raw("ISNULL((SELECT x.Hasil_Perhitungan FROM N_EMI_LAB_Perhitungan x 
                        WHERE x.id = N_EMI_LAB_Uji_Sampel.Id_Perhitungan 
                        AND x.Kode_Perusahaan = N_EMI_LAB_Uji_Sampel.Kode_Perusahaan), 0) AS Pembulatan")
            )
            ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
            ->where('N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', $id)
            ->where('N_EMI_LAB_Uji_Sampel.Flag_Selesai', 'Y')
            ->where('N_EMI_LAB_Uji_Sampel.Flag_Final', 'Y');

        if (!empty($searchQuery)) {
            $baseQuery->where(function ($query) use ($searchQuery) {
                $query->where('N_EMI_LAB_Uji_Sampel.No_Po_Sampel', 'like', '%' . $searchQuery . '%')
                      ->orWhere('N_EMI_LAB_PO_Sampel.No_Po', 'like', '%' . $searchQuery . '%')
                      ->orWhere('N_EMI_LAB_PO_Sampel.No_Split_Po', 'like', '%' . $searchQuery . '%')
                      ->orWhere('N_EMI_LAB_PO_Sampel.No_Batch', 'like', '%' . $searchQuery . '%')
                      ->orWhere('EMI_Master_Mesin.Nama_Mesin', 'like', '%' . $searchQuery . '%');
            });
        }

        // 2. Filter Tanggal Pengujian
        if ($filterTanggalMulai && $filterTanggalSelesai) {
            $baseQuery->whereBetween('N_EMI_LAB_Uji_Sampel.Tanggal', [$filterTanggalMulai, $filterTanggalSelesai]);
        }

        // 3. Filter Mesin
        if ($filterMesin) {
            $baseQuery->where('N_EMI_LAB_PO_Sampel.Id_Mesin', $filterMesin);
        }

        // 4. Filter Tipe QRCode
        if ($filterQrCode) {
            if ($filterQrCode === 'multi') {
                $baseQuery->where('N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode', 'Y');
            } elseif ($filterQrCode === 'single') {
                $baseQuery->where(function ($query) {
                    $query->where('N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode', '!=', 'Y')
                          ->orWhereNull('N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode');
                });
            }
        }

        // 5. Filter Status Keputusan
        if ($filterStatus) {
            if ($filterStatus === 'dibatalkan') {
                $baseQuery->where('N_EMI_LAB_Uji_Sampel.Status', 'Y');
            } else {
                // Untuk 'terima' atau 'tolak', pastikan bukan yang dibatalkan
                $baseQuery->where(function ($query) {
                    $query->where('N_EMI_LAB_Uji_Sampel.Status', '!=', 'Y')
                          ->orWhereNull('N_EMI_LAB_Uji_Sampel.Status');
                })->where('N_EMI_LAB_Uji_Sampel.Status_Keputusan_Sampel', $filterStatus);
            }
        }

        // 6. Filter Tipe Produksi
        if ($filterTipeProduksi === 'trial') {
            $baseQuery->where('N_EMI_LAB_PO_Sampel.Flag_Trial_Produksi', 'Y');
        } elseif ($filterTipeProduksi === 'produksi') {
            $baseQuery->where(function ($query) {
                $query->where('N_EMI_LAB_PO_Sampel.Flag_Trial_Produksi', '!=', 'Y')
                      ->orWhereNull('N_EMI_LAB_PO_Sampel.Flag_Trial_Produksi');
            });
        }

        $page = (int) $request->input('page', 1);

        // Hitung total No_Po_Sampel unik (bukan baris mentah)
        $totalDistinct = (clone $baseQuery)
            ->count(DB::raw('DISTINCT N_EMI_LAB_Uji_Sampel.No_Po_Sampel'));

        $totalPage = $limit > 0 ? (int) ceil($totalDistinct / $limit) : 1;

        // Ambil daftar No_Po_Sampel untuk halaman saat ini, urut dari terbaru
        $pagedPoSampel = (clone $baseQuery)
            ->select(
                'N_EMI_LAB_Uji_Sampel.No_Po_Sampel as NoPo',
                DB::raw('MAX(N_EMI_LAB_Uji_Sampel.Tanggal) as MaxTgl'),
                DB::raw('MAX(N_EMI_LAB_Uji_Sampel.Jam) as MaxJam')
            )
            ->groupBy('N_EMI_LAB_Uji_Sampel.No_Po_Sampel')
            ->orderByDesc('MaxTgl')
            ->orderByDesc('MaxJam')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->pluck('NoPo')
            ->toArray();

        if (empty($pagedPoSampel)) {
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

        // Ambil semua baris untuk No_Po_Sampel di halaman ini
        $ujiSampel = $baseQuery
            ->whereIn('N_EMI_LAB_Uji_Sampel.No_Po_Sampel', $pagedPoSampel)
            ->orderBy('N_EMI_LAB_Uji_Sampel.Tanggal', 'desc')
            ->orderBy('N_EMI_LAB_Uji_Sampel.Jam', 'desc')
            ->get();

        // Proses nilai hasil
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
                    'tipe_produksi'     => $item->Flag_Trial_Produksi === 'Y' ? 'Trial Produksi' : 'Produksi',
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
                    'page'      => $page,
                    'limit'     => $limit,
                    'totalPage' => $totalPage,
                    'totalData' => $totalDistinct,
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
        $ujiSampel = DB::table('N_EMI_LAB_Uji_Sampel')
                ->select('No_Po_Sampel', 'No_Fak_Sub_Po')
                ->whereNull('Status')
                ->where('No_Po_Sampel', $no_po_sampel)
                ->where('Id_Jenis_Analisa', $id_jenis_analisa)
                ->where('Flag_Multi_QrCode', 'Y')
                ->where('Flag_Selesai', 'Y')
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
                    'N_EMI_LAB_Uji_Sampel.Flag_Layak',
                    'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa',
                    'N_EMI_LAB_Uji_Sampel.Tanggal as Tanggal_Pengujian',
                    'N_EMI_LAB_Uji_Sampel.Hasil as Hasil_Akhir_Analisa',
                    'N_EMI_LAB_Uji_Sampel.Flag_Perhitungan',
                    'N_EMI_LAB_Uji_Sampel.Range_Awal',
                    'N_EMI_LAB_Uji_Sampel.Range_Akhir',
                    'N_EMI_LAB_PO_Sampel.No_Po',
                    'N_EMI_LAB_PO_Sampel.No_Split_Po',
                    'N_EMI_LAB_Uji_Sampel.Flag_Foto', // PERBAIKAN: Gunakan N_EMI_LAB_Uji_Sampel, bukan LIMS
                    DB::raw("ISNULL(N_EMI_LAB_Perhitungan.Hasil_Perhitungan, 0) AS Pembulatan"),
                    DB::raw("CASE WHEN N_EMI_LAB_Uji_Sampel.Range_Awal IS NOT NULL THEN CAST(1 AS BIT) ELSE CAST(0 AS BIT) END AS is_sop")
                )
                ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
                ->where('N_EMI_LAB_Uji_Sampel.No_Po_Sampel', $no_po_sampel)
                ->where('N_EMI_LAB_Uji_Sampel.No_Fak_Sub_po', $no_sub)
                ->where('N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa_decoded)
                ->where('N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode', $flag_multi)
                ->where('N_EMI_LAB_Uji_Sampel.Flag_Selesai', 'Y')
                ->get();

            if ($ujiSampel->isEmpty()) {
                return response()->json(['success' => true, 'status' => 200, 'message' => 'Data Tidak Ditemukan'], 200);
            }

            // Deklarasi Sesi Foto dan Faktur List
            $hasSesiFoto = $ujiSampel->contains('Flag_Foto', 'Y') ? 'Y' : 'T';
            $fakturList = $ujiSampel->pluck('No_Faktur')->unique()->toArray();

            // Pengambilan data Berkas / Foto
            $berkasRaw = DB::table('N_EMI_LIMS_Berkas_Uji_Lab')
                ->select('Id_Berkas_Uji_Lab', 'No_Faktur', 'Berkas_Key', 'Keterangan')
                ->whereIn('No_Faktur', $fakturList)
                ->get()
                ->groupBy('No_Faktur');

            $rawRules = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->select('Nilai_Kriteria', 'Keterangan_Kriteria')
                ->where('Id_Jenis_Analisa', $id_jenis_analisa_decoded)
                ->where('Flag_Aktif', 'Y')
                ->where('Kode_Role', 'LAB')
                ->get();

            $rulesMap = [];
            foreach ($rawRules as $rule) {
                $key = (string)((float)$rule->Nilai_Kriteria);
                $rulesMap[$key] = $rule->Keterangan_Kriteria;
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
                ->whereIn('No_Faktur_Uji_Sample', $fakturList) // OPTIMALISASI: Gunakan $fakturList
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
                    'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa',
                    'N_EMI_LAB_Jenis_Analisa.Kode_Aktivitas_Lab'
                )
                ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
                ->where('N_EMI_LAB_Uji_Sampel.No_Po_Sampel', $no_po_sampel)
                ->where('N_EMI_LAB_Uji_Sampel.No_Fak_Sub_po', $no_sub)
                ->where('N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa_decoded)
                ->where('N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode', $flag_multi)
                ->where('N_EMI_LAB_Uji_Sampel.Flag_Selesai', 'Y')
                ->first();

            // ── PLT: ambil daftar pembanding dari session ──
            $isPltMultiHasil = $informasi && ($informasi->Kode_Aktivitas_Lab ?? null) === 'PLT';
            $pltPembandingMultiHasil = [];
            if ($isPltMultiHasil) {
                $pltPembandingMultiHasil = DB::table('N_EMI_LAB_Palatabilitas_Pembanding as pb')
                    ->join('N_EMI_LAB_Palatabilitas_Session as ps', 'pb.Id_Session', '=', 'ps.Id_Session')
                    ->where('ps.No_Po_Sampel', $no_po_sampel)
                    ->where('ps.Kode_Aktivitas_Lab', 'PLT')
                    ->where('pb.Flag_Aktif', 'Y')
                    ->select('pb.Urutan', 'pb.Nama_Pembanding', 'pb.Kode_Barang_Pembanding')
                    ->orderBy('pb.Urutan')
                    ->orderBy('pb.Id_Pembanding')
                    ->get()
                    ->map(fn($r) => ['nama' => $r->Nama_Pembanding, 'kode' => $r->Kode_Barang_Pembanding])
                    ->values()
                    ->toArray();
            }

            if ($informasi) {
                $informasi->sesi_foto            = $hasSesiFoto;
                $informasi->is_plt               = $isPltMultiHasil;
                $informasi->plt_pembanding        = $pltPembandingMultiHasil;
                $informasi->plt_pembanding_nama   = $isPltMultiHasil && !empty($pltPembandingMultiHasil)
                                                        ? implode(', ', array_column($pltPembandingMultiHasil, 'nama'))
                                                        : null;
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
            return response()->json([
                'success' => false, 
                'status' => 500, 
                'message' => 'Terjadi kesalahan pada server. Silahkan hubungi administrator.'
            ], 500);
        }
    }

    // public function getDataHasilAnalisaPerhitunganByMultiV2($id_jenis_analisa, $no_po_sampel, $flag_multi, $no_sub)
    // {
    //     try {
    //         $id_jenis_analisa_decoded = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
    //     } catch (\Exception $e) {
    //         return response()->json(['success' => false, 'status' => 400, 'message' => 'Format ID Jenis Analisa tidak valid.'], 400);
    //     }

    //     $ujiSampel = DB::table('N_EMI_LAB_Uji_Sampel') 
    //         ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
    //         ->join('EMI_Master_Mesin', 'N_EMI_LAB_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
    //         ->leftJoin('N_EMI_LAB_Perhitungan', function ($join) {
    //             $join->on('N_EMI_LAB_Perhitungan.id', '=', 'N_EMI_LAB_Uji_Sampel.Id_Perhitungan')
    //                 ->on('N_EMI_LAB_Perhitungan.Kode_Perusahaan', '=', 'N_EMI_LAB_Uji_Sampel.Kode_Perusahaan');
    //         })
    //         ->select(
    //             'N_EMI_LAB_PO_Sampel.Kode_Barang',
    //             'N_EMI_LAB_Uji_Sampel.No_Faktur',
    //             'N_EMI_LAB_Uji_Sampel.No_Po_Sampel',
    //             'N_EMI_LAB_Uji_Sampel.No_Fak_Sub_Po',
    //             'N_EMI_LAB_Uji_Sampel.Flag_Layak',
    //             'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa',
    //             'N_EMI_LAB_Uji_Sampel.Tanggal as Tanggal_Pengujian',
    //             'N_EMI_LAB_Uji_Sampel.Hasil as Hasil_Akhir_Analisa',
    //             'N_EMI_LAB_Uji_Sampel.Flag_Perhitungan',
    //             'N_EMI_LAB_Uji_Sampel.Range_Awal',
    //             'N_EMI_LAB_Uji_Sampel.Range_Akhir',
    //             'N_EMI_LAB_PO_Sampel.No_Po',
    //             'N_EMI_LAB_PO_Sampel.No_Split_Po',
    //             'N_EMI_LIMS_Uji_Sampel.Flag_Foto',
    //             DB::raw("ISNULL(N_EMI_LAB_Perhitungan.Hasil_Perhitungan, 0) AS Pembulatan"),
    //             DB::raw("CASE WHEN N_EMI_LAB_Uji_Sampel.Range_Awal IS NOT NULL THEN CAST(1 AS BIT) ELSE CAST(0 AS BIT) END AS is_sop")
    //         )
    //         ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
    //         ->where('N_EMI_LAB_Uji_Sampel.No_Po_Sampel', $no_po_sampel)
    //         ->where('N_EMI_LAB_Uji_Sampel.No_Fak_Sub_po', $no_sub)
    //         ->where('N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa_decoded)
    //         ->where('N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode', $flag_multi)
    //         ->where('N_EMI_LAB_Uji_Sampel.Flag_Selesai', 'Y')
    //         ->get();

    //     if ($ujiSampel->isEmpty()) {
    //         return response()->json(['success' => true, 'status' => 200, 'message' => 'Data Tidak Ditemukan'], 200);
    //     }

    //     $hasSesiFoto = $ujiSampel->contains('Flag_Foto', 'Y') ? 'Y' : 'T';
    //         $fakturList = $ujiSampel->pluck('No_Faktur')->unique()->toArray();

    //         // Pengambilan data Berkas / Foto
    //         // PERUBAHAN: Menambahkan 'Keterangan' pada select
    //         $berkasRaw = DB::table('N_EMI_LIMS_Berkas_Uji_Lab')
    //             ->select('Id_Berkas_Uji_Lab', 'No_Faktur', 'Berkas_Key', 'Keterangan')
    //             ->whereIn('No_Faktur', $fakturList)
    //             ->get()
    //             ->groupBy('No_Faktur');

    //     $rawRules = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
    //         ->select('Nilai_Kriteria', 'Keterangan_Kriteria')
    //         ->where('Id_Jenis_Analisa', $id_jenis_analisa_decoded)
    //         ->where('Flag_Aktif', 'Y')
    //         ->where('Kode_Role', 'LAB')
    //         ->get();

    //     $rulesMap = [];
    //     foreach ($rawRules as $rule) {
    //         $key = (string)((float)$rule->Nilai_Kriteria);
    //         $rulesMap[$key] = $rule->Keterangan_Kriteria;
    //     }

    //     $parameterRaw = DB::table('N_EMI_LAB_Uji_Sampel_Detail')
    //         ->select(
    //             'Id_Uji_Sample_Detail',
    //             'No_Faktur_Uji_Sample',
    //             'Id_Quality_Control',
    //             'Value_Parameter as Hasil_Analisa',
    //             'Tanggal as Tanggal_Parameter_Analisa',
    //             'Jam as Jam_Parameter_Analisa'
    //         )
    //         ->whereIn('No_Faktur_Uji_Sample', $ujiSampel->pluck('No_Faktur'))
    //         ->get()
    //         ->groupBy('No_Faktur_Uji_Sample');

    //     $result = [];

    //     foreach ($ujiSampel as $item) {
    //         $item->is_sop = (bool) $item->is_sop;
    //         $item->Range_Awal = (float) $item->Range_Awal;
    //         $item->Range_Akhir = (float) $item->Range_Akhir;

    //         $valHeader = (float)$item->Hasil_Akhir_Analisa;
    //         $keyHeader = (string)$valHeader;

    //         if (is_null($item->Flag_Perhitungan) && isset($rulesMap[$keyHeader])) {
    //             $item->Hasil_Akhir_Analisa = $rulesMap[$keyHeader];
    //         } else {
    //             $item->Hasil_Akhir_Analisa = number_format($valHeader, $item->Pembulatan, '.', '');
    //         }

    //         $params = $parameterRaw->get($item->No_Faktur);
    //         $transformedParameters = [];

    //         if ($params) {
    //             foreach ($params as $param) {
    //                 $valParam = (float)$param->Hasil_Analisa;
    //                 $keyParam = (string)$valParam;
                    
    //                 if (is_null($item->Flag_Perhitungan) && isset($rulesMap[$keyParam])) {
    //                     $hasilTampil = $rulesMap[$keyParam];
    //                 } else {
    //                     $hasilTampil = round($valParam, 4);
    //                 }

    //                 $transformedParameters[] = [
    //                     'Id_Jenis_Analisa' => Hashids::connection('custom')->encode($id_jenis_analisa_decoded),
    //                     'Id_Uji_Sample_Detail' => Hashids::connection('custom')->encode($param->Id_Uji_Sample_Detail),
    //                     'No_Faktur_Uji_Sample' => $param->No_Faktur_Uji_Sample,
    //                     'Id_Quality_Control' => Hashids::connection('custom')->encode($param->Id_Quality_Control),
    //                     'Hasil_Analisa' => $hasilTampil,
    //                     'Tanggal_Parameter_Analisa' => $param->Tanggal_Parameter_Analisa,
    //                     'Jam_Parameter_Analisa' => $param->Jam_Parameter_Analisa,
    //                 ];
    //             }
    //         }

    //         // Proses mapping berkas foto untuk item ini
    //             $berkas = $berkasRaw->get($item->No_Faktur);
    //             $fotoList = [];

    //             if ($berkas) {
    //                 foreach ($berkas as $file) {
    //                     // PERUBAHAN: Menambahkan 'Keterangan' ke array respons
    //                     $fotoList[] = [
    //                         'Berkas_Key' => $file->Berkas_Key,
    //                         'Keterangan' => $file->Keterangan,
    //                     ];
    //                 }
    //             }

    //             $item->foto_analisa = $fotoList;
    //             unset($item->Flag_Foto); // Hapus Flag_Foto dari response JSON final

    //             $item->parameter = $transformedParameters;
    //             $result[] = $item;
    //     }

    //     $informasi = DB::table('N_EMI_LAB_Uji_Sampel')
    //         ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
    //         ->join('EMI_Master_Mesin', 'N_EMI_LAB_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
    //         ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
    //         ->select(
    //             'N_EMI_LAB_Uji_Sampel.No_Faktur',
    //             'N_EMI_LAB_Uji_Sampel.No_Po_Sampel',
    //             'N_EMI_LAB_Uji_Sampel.No_Fak_Sub_Po',
    //             'N_EMI_LAB_Uji_Sampel.Tanggal as Tanggal_Pengujian',
    //             'N_EMI_LAB_Uji_Sampel.Jam as Jam_Pengujian',
    //             'N_EMI_LAB_PO_Sampel.Tanggal as Tanggal_Pengajuan',
    //             'N_EMI_LAB_PO_Sampel.Jam as Jam_Pengajuan',
    //             'N_EMI_LAB_PO_Sampel.No_Po',
    //             'N_EMI_LAB_PO_Sampel.Keterangan as Catatan',
    //             'N_EMI_LAB_PO_Sampel.No_Split_Po',
    //             'N_EMI_LAB_PO_Sampel.No_Batch',
    //             'N_EMI_LAB_PO_Sampel.Kode_Barang',
    //             'EMI_Master_Mesin.Seri_Mesin',
    //             'EMI_Master_Mesin.Nama_Mesin', 
    //             'N_EMI_LAB_Jenis_Analisa.Kode_Analisa',
    //             'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa'
    //         )
    //         ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
    //         ->where('N_EMI_LAB_Uji_Sampel.No_Po_Sampel', $no_po_sampel)
    //         ->where('N_EMI_LAB_Uji_Sampel.No_Fak_Sub_po', $no_sub)
    //         ->where('N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa_decoded)
    //         ->where('N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode', $flag_multi)
    //         ->where('N_EMI_LAB_Uji_Sampel.Flag_Selesai', 'Y')
    //         ->first();

    //     return response()->json([
    //             'success' => true,
    //             'status' => 200,
    //             'message' => 'Data Ditemukan',
    //             'result' => [
    //                 'informasi' => $informasi,
    //                 'sampel' => $result
    //             ]
    //         ], 200);
    // }

    public function getVerifikasiHasilAnalisaPerhitunganByMultiV2($id_jenis_analisa, $no_po_sampel, $no_sub)
    {
        try {
            $id_jenis_analisa_decoded = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'status' => 400, 'message' => 'Format ID Jenis Analisa tidak valid.'], 400);
        }

        $ujiSampel = DB::table('N_EMI_LAB_Uji_Sampel')
            ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
            ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
            ->join('EMI_Master_Mesin', 'N_EMI_LAB_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
            ->leftJoin('N_EMI_LAB_Perhitungan', function ($join) {
                $join->on('N_EMI_LAB_Perhitungan.id', '=', 'N_EMI_LAB_Uji_Sampel.Id_Perhitungan')
                    ->on('N_EMI_LAB_Perhitungan.Kode_Perusahaan', '=', 'N_EMI_LAB_Uji_Sampel.Kode_Perusahaan');
            })
            ->leftJoin('N_EMI_LAB_Palatabilitas_Pembanding as pb_vld', 'N_EMI_LAB_Uji_Sampel.Id_Pembanding', '=', 'pb_vld.Id_Pembanding')
            ->select(
                'N_EMI_LAB_PO_Sampel.Kode_Barang',
                'N_EMI_LAB_PO_Sampel.tanggal as Tanggal_Registrasi',
                'N_EMI_LAB_PO_Sampel.jam as Jam_Registrasi',
                'N_EMI_LAB_Jenis_Analisa.Kode_Analisa',
                'N_EMI_LAB_Uji_Sampel.No_Faktur',
                'N_EMI_LAB_Uji_Sampel.Flag_String',
                'N_EMI_LAB_Uji_Sampel.Nilai_Hasil_String',
                'N_EMI_LAB_Uji_Sampel.No_Po_Sampel',
                'N_EMI_LAB_Uji_Sampel.No_Fak_Sub_Po', 
                'N_EMI_LAB_PO_Sampel.No_Batch', 
                'N_EMI_LAB_Uji_Sampel.Tahapan_Ke', 
                'N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode', 
                'N_EMI_LAB_Uji_Sampel.Flag_Resampling', 
                'N_EMI_LAB_Uji_Sampel.Status_Keputusan_Sampel', 
                'N_EMI_LAB_Uji_Sampel.Flag_Layak', 
                'N_EMI_LAB_Uji_Sampel.Flag_Final', 
                'N_EMI_LAB_Uji_Sampel.Id_Mesin', 
                'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa',
                'N_EMI_LAB_Uji_Sampel.Tanggal as Tanggal_Pengujian',
                'N_EMI_LAB_Uji_Sampel.Hasil as Hasil_Akhir_Analisa',
                'N_EMI_LAB_Uji_Sampel.Flag_Perhitungan',
                'N_EMI_LAB_Uji_Sampel.Range_Awal',
                'N_EMI_LAB_Uji_Sampel.Range_Akhir',
                'N_EMI_LAB_PO_Sampel.No_Po',
                'N_EMI_LAB_PO_Sampel.No_Split_Po',
                'EMI_Master_Mesin.Flag_FG',
                'N_EMI_LAB_Uji_Sampel.Flag_Foto', // Flag Foto terambil di sini
                'N_EMI_LAB_Jenis_Analisa.Kode_Aktivitas_Lab',
                DB::raw("ISNULL(N_EMI_LAB_Perhitungan.Hasil_Perhitungan, 0) AS Pembulatan"),
                'pb_vld.Nama_Pembanding'
            )
            ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
            ->where('N_EMI_LAB_Uji_Sampel.No_Po_Sampel', $no_po_sampel)
            ->where('N_EMI_LAB_Uji_Sampel.No_Fak_Sub_po', $no_sub)
            ->where('N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa_decoded)
            ->where('N_EMI_LAB_Uji_Sampel.Status_Keputusan_Sampel', 'menunggu')
            ->get();

        if ($ujiSampel->isEmpty()) {
            return response()->json(['success' => true, 'status' => 200, 'message' => 'Data Tidak Ditemukan'], 200);
        }

        // --- MULAI PENAMBAHAN FOTO ---
        $hasSesiFoto = $ujiSampel->contains('Flag_Foto', 'Y') ? 'Y' : 'T';
        $fakturList = $ujiSampel->pluck('No_Faktur')->unique()->toArray();

        // Catatan: Pastikan nama tabelnya N_EMI_LAB_Berkas_Uji_Lab. 
        // Jika di database nama tabelnya tetap N_EMI_LIMS_Berkas_Uji_Lab, silakan ubah string tabel di bawah ini.
        $berkasRaw = DB::table('N_EMI_LAB_Berkas_Uji_Lab')
            ->select('Id_Berkas_Lab', 'No_Faktur', 'Berkas_Key', 'Keterangan')
            ->whereIn('No_Faktur', $fakturList)
            ->get()
            ->groupBy('No_Faktur');
        // --- SELESAI PENAMBAHAN FOTO ---

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

        $parameterRaw = DB::table('N_EMI_LAB_Uji_Sampel_Detail')
            ->select(
                'Id_Uji_Sample_Detail',
                'No_Faktur_Uji_Sample',
                'Id_Quality_Control',
                'Value_Parameter as Hasil_Analisa',
                'Tanggal as Tanggal_Parameter_Analisa',
                'Jam as Jam_Parameter_Analisa'
            )
            ->whereIn('No_Faktur_Uji_Sample', $fakturList) // Menggunakan $fakturList agar lebih efisien
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

            // --- MULAI PENAMBAHAN LOOP FOTO ---
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
            // --- SELESAI PENAMBAHAN LOOP FOTO ---

            $item->parameter = $transformedParameters;
            $result[] = $item;
        }

        // --- PENYESUAIAN RESPONSE JSON ---
        $firstUji = $ujiSampel->first();
        $flagPerhitunganCheck = $firstUji->Flag_Perhitungan ?? null;
        $idMesinCheck = $firstUji->Id_Mesin ?? null;
        $kodeBarangCheck = $firstUji->Kode_Barang ?? null;

        if ($flagPerhitunganCheck === 'Y') {
            $hasStdConfig = DB::table('N_EMI_LAB_Standar_Rentang')
                ->where('Id_Jenis_Analisa', $id_jenis_analisa_decoded)
                ->where('Kode_Barang', $kodeBarangCheck)
                ->where('Id_Master_Mesin', $idMesinCheck)
                ->exists();
        } else {
            $hasStdConfig = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->where('Id_Jenis_Analisa', $id_jenis_analisa_decoded)
                ->exists();
        }

        // ── PLT: ambil daftar produk pembanding dalam session ──
        $isPltMulti       = ($firstUji->Kode_Aktivitas_Lab ?? null) === 'PLT';
        $pltPembandingMulti = [];
        if ($isPltMulti) {
            $pltPembandingMulti = DB::table('N_EMI_LAB_Palatabilitas_Pembanding as pb')
                ->join('N_EMI_LAB_Palatabilitas_Session as ps', 'pb.Id_Session', '=', 'ps.Id_Session')
                ->where('ps.No_Po_Sampel', $no_po_sampel)
                ->where('ps.Kode_Aktivitas_Lab', 'PLT')
                ->where('pb.Flag_Aktif', 'Y')
                ->select('pb.Urutan', 'pb.Nama_Pembanding', 'pb.Kode_Barang_Pembanding')
                ->orderBy('pb.Urutan')
                ->orderBy('pb.Id_Pembanding')
                ->get()
                ->map(fn($r) => [
                    'nama' => $r->Nama_Pembanding,
                    'kode' => $r->Kode_Barang_Pembanding,
                ])
                ->values()
                ->toArray();
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Data Ditemukan',
            'result' => [
                'informasi' => [
                    'sesi_foto'                  => $hasSesiFoto,
                    'has_standard_configuration' => $hasStdConfig,
                    'is_plt'                     => $isPltMulti,
                    'plt_pembanding'             => $pltPembandingMulti,
                    'plt_pembanding_nama'        => $isPltMulti && !empty($pltPembandingMulti)
                                                        ? implode(', ', array_column($pltPembandingMulti, 'nama'))
                                                        : null,
                ],
                'sampel' => $result
            ]
        ], 200);
    }

    public function getVerifikasiHasilAnalisaPerhitunganBySingleQrV2($id_jenis_analisa, $no_po_sampel)
    {
        try {
            $decodedId = Hashids::connection('custom')->decode($id_jenis_analisa);
            
            if (empty($decodedId)) {
                throw new \Exception('Format ID Jenis Analisa tidak valid.');
            }
            
            $id_jenis_analisa_int = $decodedId[0];

            $ujiSampel = DB::table('N_EMI_LAB_Uji_Sampel') 
                ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
                ->join('EMI_Master_Mesin', 'N_EMI_LAB_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
                ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
                ->leftJoin('N_EMI_LAB_Perhitungan', function ($join) {
                    $join->on('N_EMI_LAB_Perhitungan.id', '=', 'N_EMI_LAB_Uji_Sampel.Id_Perhitungan')
                        ->on('N_EMI_LAB_Perhitungan.Kode_Perusahaan', '=', 'N_EMI_LAB_Uji_Sampel.Kode_Perusahaan');
                })
                ->leftJoin('N_EMI_LAB_Standar_Rentang', function ($join) {
                    $join->on('N_EMI_LAB_Standar_Rentang.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa')
                        ->on('N_EMI_LAB_Standar_Rentang.Id_Master_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
                        ->on('N_EMI_LAB_Standar_Rentang.Kode_Barang', '=', 'N_EMI_LAB_PO_Sampel.Kode_Barang');
                })
                ->leftJoin('N_EMI_LAB_Palatabilitas_Pembanding as pb_vld', 'N_EMI_LAB_Uji_Sampel.Id_Pembanding', '=', 'pb_vld.Id_Pembanding')
                ->select(
                    'N_EMI_LAB_Uji_Sampel.Kode_Perusahaan',
                    'N_EMI_LAB_PO_Sampel.Kode_Barang',
                    'N_EMI_LAB_PO_Sampel.Tanggal as Tanggal_Registrasi',
                    'N_EMI_LAB_PO_Sampel.Jam as Jam_Registrasi',
                    'N_EMI_LAB_Uji_Sampel.No_Faktur',
                    'N_EMI_LAB_Uji_Sampel.No_Po_Sampel',
                    'N_EMI_LAB_Uji_Sampel.No_Fak_Sub_Po', 
                    'N_EMI_LAB_PO_Sampel.No_Batch', 
                    'N_EMI_LAB_Uji_Sampel.Tahapan_Ke', 
                    'N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode', 
                    'N_EMI_LAB_Uji_Sampel.Flag_Resampling', 
                    'N_EMI_LAB_Uji_Sampel.Status_Keputusan_Sampel', 
                    'N_EMI_LAB_Uji_Sampel.Flag_Layak', 
                    'N_EMI_LAB_Uji_Sampel.Flag_Final', 
                    'N_EMI_LAB_Uji_Sampel.Id_Mesin', 
                    'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa',
                    'N_EMI_LAB_Uji_Sampel.Tanggal as Tanggal_Pengujian',
                    'N_EMI_LAB_Uji_Sampel.Hasil as Hasil_Akhir_Analisa',
                    'N_EMI_LAB_PO_Sampel.No_Po',
                    'N_EMI_LAB_PO_Sampel.No_Split_Po',
                    'EMI_Master_Mesin.Flag_FG',
                    'N_EMI_LAB_Uji_Sampel.Flag_Foto', 
                    'N_EMI_LAB_Jenis_Analisa.Flag_Perhitungan', 
                    DB::raw("ISNULL(N_EMI_LAB_Perhitungan.Hasil_Perhitungan, 0) AS Pembulatan"),
                    DB::raw("CASE WHEN N_EMI_LAB_Standar_Rentang.Id_Standar_Rentang IS NOT NULL THEN N_EMI_LAB_Standar_Rentang.Range_Awal ELSE NULL END AS Range_Awal"),
                    DB::raw("CASE WHEN N_EMI_LAB_Standar_Rentang.Id_Standar_Rentang IS NOT NULL THEN N_EMI_LAB_Standar_Rentang.Range_Akhir ELSE NULL END AS Range_Akhir"),
                    'N_EMI_LAB_Jenis_Analisa.Kode_Aktivitas_Lab',
                    'pb_vld.Nama_Pembanding'
                )
                ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
                ->where('N_EMI_LAB_Uji_Sampel.No_Po_Sampel', $no_po_sampel)
                ->where('N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa_int)
                ->where('N_EMI_LAB_Uji_Sampel.Status_Keputusan_Sampel', 'menunggu')
                ->get();

            if ($ujiSampel->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'status' => 200,
                    'message' => 'Data Tidak Ditemukan',
                ], 200);
            }

            // --- MULAI PENAMBAHAN FOTO ---
            $hasSesiFoto = $ujiSampel->contains('Flag_Foto', 'Y') ? 'Y' : 'T';
            $fakturList = $ujiSampel->pluck('No_Faktur')->unique()->toArray();

            $berkasRaw = DB::table('N_EMI_LAB_Berkas_Uji_Lab')
                ->select('Id_Berkas_Lab', 'No_Faktur', 'Berkas_Key', 'Keterangan')
                ->whereIn('No_Faktur', $fakturList)
                ->get()
                ->groupBy('No_Faktur');
            // --- SELESAI PENAMBAHAN FOTO ---

            $kodePerusahaan = $ujiSampel->first()->Kode_Perusahaan;

            $referensiNonHitung = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->select('Nilai_Kriteria', 'Keterangan_Kriteria')
                ->where('Id_Jenis_Analisa', $id_jenis_analisa_int)
                ->where('Kode_Perusahaan', $kodePerusahaan)
                ->where('Flag_Aktif', 'Y')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [(string)floatval($item->Nilai_Kriteria) => $item->Keterangan_Kriteria];
                });

            $parameterRaw = DB::table('N_EMI_LAB_Uji_Sampel_Detail')
                ->select(
                    'Id_Uji_Sample_Detail',
                    'No_Faktur_Uji_Sample',
                    'Id_Quality_Control',
                    'Value_Parameter as Hasil_Analisa',
                    'Tanggal as Tanggal_Parameter_Analisa',
                    'Jam as Jam_Parameter_Analisa'
                )
                ->whereIn('No_Faktur_Uji_Sample', $fakturList) // Optimasi query memakai $fakturList
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
            
                $item['parameter'] = $transformedParameters;

                // --- MULAI PENAMBAHAN LOOP FOTO ---
                $berkas = $berkasRaw->get($sampel->No_Faktur);
                $fotoList = [];

                if ($berkas) {
                    foreach ($berkas as $file) {
                        $fotoList[] = [
                            'Berkas_Key' => $file->Berkas_Key,
                            'Keterangan' => $file->Keterangan ?? 'Tidak Ada Keterangan'
                        ];
                    }
                }

                $item['foto_analisa'] = $fotoList;
                unset($item['Flag_Foto']); // Hapus Flag_Foto dari response array seperti di contoh sebelumnya
                // --- SELESAI PENAMBAHAN LOOP FOTO ---

                $result[] = $item;
            }

            // --- PENYESUAIAN RESPONSE JSON ---
            $firstSampel = $ujiSampel->first();
            $flagPerhitunganSingle = $firstSampel->Flag_Perhitungan ?? null;
            $idMesinSingle = $firstSampel->Id_Mesin ?? null;
            $kodeBarangSingle = $firstSampel->Kode_Barang ?? null;

            if ($flagPerhitunganSingle === 'Y') {
                $hasStdConfigSingle = DB::table('N_EMI_LAB_Standar_Rentang')
                    ->where('Id_Jenis_Analisa', $id_jenis_analisa_int)
                    ->where('Kode_Barang', $kodeBarangSingle)
                    ->where('Id_Master_Mesin', $idMesinSingle)
                    ->exists();
            } else {
                $hasStdConfigSingle = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                    ->where('Id_Jenis_Analisa', $id_jenis_analisa_int)
                    ->exists();
            }

            $isPltSingle = ($firstSampel->Kode_Aktivitas_Lab ?? null) === 'PLT';
            $pltPembandingSingle = [];
            if ($isPltSingle) {
                $pltPembandingSingle = DB::table('N_EMI_LAB_Palatabilitas_Pembanding as pb')
                    ->join('N_EMI_LAB_Palatabilitas_Session as ps', 'pb.Id_Session', '=', 'ps.Id_Session')
                    ->where('ps.No_Po_Sampel', $firstSampel->No_Po_Sampel)
                    ->where('ps.Kode_Aktivitas_Lab', 'PLT')
                    ->where('pb.Flag_Aktif', 'Y')
                    ->select('pb.Urutan', 'pb.Nama_Pembanding', 'pb.Kode_Barang_Pembanding')
                    ->orderBy('pb.Urutan')
                    ->orderBy('pb.Id_Pembanding')
                    ->get()
                    ->map(fn($r) => [
                        'nama' => $r->Nama_Pembanding,
                        'kode' => $r->Kode_Barang_Pembanding,
                    ])
                    ->values()
                    ->toArray();
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Data Ditemukan',
                'result' => [
                    'informasi' => [
                        'sesi_foto'                => $hasSesiFoto,
                        'has_standard_configuration' => $hasStdConfigSingle,
                        'is_plt'                   => $isPltSingle,
                        'plt_pembanding'           => $pltPembandingSingle,
                        'plt_pembanding_nama'      => $isPltSingle && !empty($pltPembandingSingle)
                                                          ? implode(', ', array_column($pltPembandingSingle, 'nama'))
                                                          : null,
                    ],
                    'sampel' => $result
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'params' => [
                    'id_jenis_analisa' => $id_jenis_analisa,
                    'no_po_sampel' => $no_po_sampel
                ],
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Terjadi kesalahan pada server. Silahkan hubungi administrator.'
            ], 500);
        }
    }

    public function getVerifikasiHasilAnalisaFinalKeputusanV1($id_jenis_analisa, $no_po_sampel, $no_sub)
    {
        // Try Catch Pertama: Decode ID Jenis Analisa
        try {
            $id_jenis_analisa_decoded = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'status' => 400, 'message' => 'Format ID Jenis Analisa tidak valid.'], 400);
        }

        // Try Catch Kedua: Logika Utama Data & Database
        try {
            $ujiSampel = DB::table('N_EMI_LAB_Uji_Sampel') 
                ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
                ->join('EMI_Master_Mesin', 'N_EMI_LAB_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
                ->leftJoin('N_EMI_LAB_Perhitungan', function ($join) {
                    $join->on('N_EMI_LAB_Perhitungan.id', '=', 'N_EMI_LAB_Uji_Sampel.Id_Perhitungan')
                        ->on('N_EMI_LAB_Perhitungan.Kode_Perusahaan', '=', 'N_EMI_LAB_Uji_Sampel.Kode_Perusahaan');
                })
                ->leftJoin('N_EMI_LAB_Palatabilitas_Pembanding as pb_vld', 'N_EMI_LAB_Uji_Sampel.Id_Pembanding', '=', 'pb_vld.Id_Pembanding')
                ->select(
                    'N_EMI_LAB_PO_Sampel.Kode_Barang',
                    'N_EMI_LAB_Uji_Sampel.No_Faktur',
                    'N_EMI_LAB_Uji_Sampel.No_Po_Sampel',
                    'N_EMI_LAB_Uji_Sampel.No_Fak_Sub_Po',
                    'N_EMI_LAB_PO_Sampel.No_Batch',
                    'N_EMI_LAB_Uji_Sampel.Tahapan_Ke',
                    'N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode',
                    'N_EMI_LAB_Uji_Sampel.Flag_Resampling',
                    'N_EMI_LAB_Uji_Sampel.Status_Keputusan_Sampel',
                    'N_EMI_LAB_Uji_Sampel.Flag_Layak',
                    'N_EMI_LAB_Uji_Sampel.Flag_Final',
                    'N_EMI_LAB_Uji_Sampel.Id_Mesin',
                    'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa',
                    'N_EMI_LAB_Uji_Sampel.Tanggal as Tanggal_Pengujian',
                    'N_EMI_LAB_Uji_Sampel.Hasil as Hasil_Akhir_Analisa',
                    'N_EMI_LAB_Uji_Sampel.Flag_Perhitungan',
                    'N_EMI_LAB_Uji_Sampel.Range_Awal',
                    'N_EMI_LAB_Uji_Sampel.Range_Akhir',
                    'N_EMI_LAB_PO_Sampel.No_Po',
                    'N_EMI_LAB_PO_Sampel.No_Split_Po',
                    'EMI_Master_Mesin.Flag_FG',
                    'N_EMI_LAB_Uji_Sampel.Flag_Foto', // Penambahan Field Flag_Foto (disesuaikan dengan nama tabel utama LAB)
                    DB::raw("ISNULL(N_EMI_LAB_Perhitungan.Hasil_Perhitungan, 0) AS Pembulatan"),
                    'pb_vld.Nama_Pembanding'
                )
                ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
                ->where('N_EMI_LAB_Uji_Sampel.No_Po_Sampel', $no_po_sampel)
                ->where('N_EMI_LAB_Uji_Sampel.No_Fak_Sub_po', $no_sub)
                ->where('N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa_decoded)
                ->where('N_EMI_LAB_Uji_Sampel.Status_Keputusan_Sampel', 'terima')
                ->get();

            if ($ujiSampel->isEmpty()) {
                return response()->json(['success' => true, 'status' => 200, 'message' => 'Data Tidak Ditemukan'], 200);
            }

            // --- AWAL PENAMBAHAN FLAG FOTO ---
            // Deklarasi Sesi Foto dan Faktur List
            $hasSesiFoto = $ujiSampel->contains('Flag_Foto', 'Y') ? 'Y' : 'T';
            $fakturList = $ujiSampel->pluck('No_Faktur')->unique()->toArray();

            // Ambil Data Berkas (Foto) dari Database (Disamakan dengan prefix tabel LAB kamu)
            $berkasRaw = DB::table('N_EMI_LAB_Berkas_Uji_Lab')
                ->select('Id_Berkas_Lab', 'No_Faktur', 'Berkas_Key')
                ->whereIn('No_Faktur', $fakturList)
                ->get()
                ->groupBy('No_Faktur');
            // --- AKHIR PENAMBAHAN FLAG FOTO ---

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

            $parameterRaw = DB::table('N_EMI_LAB_Uji_Sampel_Detail')
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

                // --- TRANSFORMASI DATA FOTO ---
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
                unset($item->Flag_Foto); // Dihapus karena informasinya diangkat ke node informasi
                // ------------------------------

                $item->parameter = $transformedParameters;
                $result[] = $item;
            }

            // ── PLT: cek apakah analisa ini PLT dan ambil pembanding ──
            $kodeAktivitasLab = DB::table('N_EMI_LAB_Jenis_Analisa')
                ->where('id', $id_jenis_analisa_decoded)
                ->value('Kode_Aktivitas_Lab');
            $isPltFinal = $kodeAktivitasLab === 'PLT';
            $pltPembandingFinal = [];
            if ($isPltFinal && !empty($no_po_sampel)) {
                $pltPembandingFinal = DB::table('N_EMI_LAB_Palatabilitas_Pembanding as pb')
                    ->join('N_EMI_LAB_Palatabilitas_Session as ps', 'pb.Id_Session', '=', 'ps.Id_Session')
                    ->where('ps.No_Po_Sampel', $no_po_sampel)
                    ->where('ps.Kode_Aktivitas_Lab', 'PLT')
                    ->where('pb.Flag_Aktif', 'Y')
                    ->select('pb.Urutan', 'pb.Nama_Pembanding', 'pb.Kode_Barang_Pembanding')
                    ->orderBy('pb.Urutan')
                    ->orderBy('pb.Id_Pembanding')
                    ->get()
                    ->map(fn($r) => ['nama' => $r->Nama_Pembanding, 'kode' => $r->Kode_Barang_Pembanding])
                    ->values()
                    ->toArray();
            }

            // Return Data Sesuai Pola Baru
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Data Ditemukan',
                'result' => [
                    'informasi' => [
                        'sesi_foto'          => $hasSesiFoto,
                        'is_plt'             => $isPltFinal,
                        'plt_pembanding'     => $pltPembandingFinal,
                        'plt_pembanding_nama'=> $isPltFinal && !empty($pltPembandingFinal)
                                                    ? implode(', ', array_column($pltPembandingFinal, 'nama'))
                                                    : null,
                    ],
                    'sampel' => $result
                ]
            ], 200);

        } catch (\Exception $e) {
            // Logging error (pastikan Log di-import di atas, e.g: use Illuminate\Support\Facades\Log;)
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Terjadi kesalahan pada server. Silahkan hubungi administrator.'
            ], 500);
        }
    }

    // public function getVerifikasiHasilAnalisaFinalKeputusanV1($id_jenis_analisa, $no_po_sampel, $no_sub)
    // {
    //     try {
    //         $id_jenis_analisa_decoded = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
    //     } catch (\Exception $e) {
    //         return response()->json(['success' => false, 'status' => 400, 'message' => 'Format ID Jenis Analisa tidak valid.'], 400);
    //     }

    //     $ujiSampel = DB::table('N_EMI_LAB_Uji_Sampel') 
    //         ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
    //         ->join('EMI_Master_Mesin', 'N_EMI_LAB_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
    //         ->leftJoin('N_EMI_LAB_Perhitungan', function ($join) {
    //             $join->on('N_EMI_LAB_Perhitungan.id', '=', 'N_EMI_LAB_Uji_Sampel.Id_Perhitungan')
    //                 ->on('N_EMI_LAB_Perhitungan.Kode_Perusahaan', '=', 'N_EMI_LAB_Uji_Sampel.Kode_Perusahaan');
    //         })
    //         ->select(
    //             'N_EMI_LAB_PO_Sampel.Kode_Barang',
    //             'N_EMI_LAB_Uji_Sampel.No_Faktur',
    //             'N_EMI_LAB_Uji_Sampel.No_Po_Sampel',
    //             'N_EMI_LAB_Uji_Sampel.No_Fak_Sub_Po', 
    //             'N_EMI_LAB_PO_Sampel.No_Batch', 
    //             'N_EMI_LAB_Uji_Sampel.Tahapan_Ke', 
    //             'N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode', 
    //             'N_EMI_LAB_Uji_Sampel.Flag_Resampling', 
    //             'N_EMI_LAB_Uji_Sampel.Status_Keputusan_Sampel', 
    //             'N_EMI_LAB_Uji_Sampel.Flag_Layak', 
    //             'N_EMI_LAB_Uji_Sampel.Flag_Final', 
    //             'N_EMI_LAB_Uji_Sampel.Id_Mesin', 
    //             'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa',
    //             'N_EMI_LAB_Uji_Sampel.Tanggal as Tanggal_Pengujian',
    //             'N_EMI_LAB_Uji_Sampel.Hasil as Hasil_Akhir_Analisa',
    //             'N_EMI_LAB_Uji_Sampel.Flag_Perhitungan',
    //             'N_EMI_LAB_Uji_Sampel.Range_Awal',
    //             'N_EMI_LAB_Uji_Sampel.Range_Akhir',
    //             'N_EMI_LAB_PO_Sampel.No_Po',
    //             'N_EMI_LAB_PO_Sampel.No_Split_Po',
    //             'EMI_Master_Mesin.Flag_FG',
    //             'N_EMI_LIMS_Uji_Sampel.Flag_Foto',
    //             DB::raw("ISNULL(N_EMI_LAB_Perhitungan.Hasil_Perhitungan, 0) AS Pembulatan")
    //         )
    //         ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
    //         ->where('N_EMI_LAB_Uji_Sampel.No_Po_Sampel', $no_po_sampel)
    //         ->where('N_EMI_LAB_Uji_Sampel.No_Fak_Sub_po', $no_sub)
    //         ->where('N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa_decoded)
    //         ->where('N_EMI_LAB_Uji_Sampel.Status_Keputusan_Sampel', 'terima')
    //         ->get();

    //     if ($ujiSampel->isEmpty()) {
    //         return response()->json(['success' => true, 'status' => 200, 'message' => 'Data Tidak Ditemukan'], 200);
    //     }

    //     $rawRules = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
    //         ->select('Nilai_Kriteria', 'Keterangan_Kriteria')
    //         ->where('Id_Jenis_Analisa', $id_jenis_analisa_decoded)
    //         ->where('Flag_Aktif', 'Y')
    //         ->get();

    //     $rulesMap = [];
    //     foreach ($rawRules as $rule) {
    //         $key = (string)((float)$rule->Nilai_Kriteria);
    //         $rulesMap[$key] = $rule->Keterangan_Kriteria;
    //     }

    //     $parameterRaw = DB::table('N_EMI_LAB_Uji_Sampel_Detail')
    //         ->select(
    //             'Id_Uji_Sample_Detail',
    //             'No_Faktur_Uji_Sample',
    //             'Id_Quality_Control',
    //             'Value_Parameter as Hasil_Analisa',
    //             'Tanggal as Tanggal_Parameter_Analisa',
    //             'Jam as Jam_Parameter_Analisa'
    //         )
    //         ->whereIn('No_Faktur_Uji_Sample', $ujiSampel->pluck('No_Faktur'))
    //         ->get()
    //         ->groupBy('No_Faktur_Uji_Sample');

    //     $result = [];

    //     foreach ($ujiSampel as $item) {
    //         $item->Range_Awal = (float) $item->Range_Awal;
    //         $item->Range_Akhir = (float) $item->Range_Akhir;
            
    //         $valHeader = (float)$item->Hasil_Akhir_Analisa;
    //         $keyHeader = (string)$valHeader;

    //         if (is_null($item->Flag_Perhitungan) && isset($rulesMap[$keyHeader])) {
    //             $item->Hasil_Akhir_Analisa = $rulesMap[$keyHeader];
    //         } else {
    //             $item->Hasil_Akhir_Analisa = number_format($valHeader, $item->Pembulatan, '.', '');
    //         }

    //         $params = $parameterRaw->get($item->No_Faktur);
    //         $transformedParameters = [];

    //         if ($params) {
    //             foreach ($params as $param) {
    //                 $valParam = (float)$param->Hasil_Analisa;
    //                 $keyParam = (string)$valParam;

    //                 if (is_null($item->Flag_Perhitungan) && isset($rulesMap[$keyParam])) {
    //                     $hasilTampil = $rulesMap[$keyParam];
    //                 } else {
    //                     $hasilTampil = round($valParam, 4);
    //                 }

    //                 $transformedParameters[] = [
    //                     'Id_Jenis_Analisa' => Hashids::connection('custom')->encode($id_jenis_analisa_decoded),
    //                     'Id_Uji_Sample_Detail' => Hashids::connection('custom')->encode($param->Id_Uji_Sample_Detail),
    //                     'No_Faktur_Uji_Sample' => $param->No_Faktur_Uji_Sample,
    //                     'Id_Quality_Control' => Hashids::connection('custom')->encode($param->Id_Quality_Control),
    //                     'Hasil_Analisa' => $hasilTampil,
    //                     'Tanggal_Parameter_Analisa' => $param->Tanggal_Parameter_Analisa,
    //                     'Jam_Parameter_Analisa' => $param->Jam_Parameter_Analisa,
    //                 ];
    //             }
    //         }

    //         $item->parameter = $transformedParameters;
    //         $result[] = $item;
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'status' => 200,
    //         'message' => 'Data Ditemukan',
    //         'result' => ['sampel' => $result]
    //     ], 200);
    // }

    public function getVerifikasiHasilAnalisaFinalKeputusanV1NoPcs($id_jenis_analisa, $no_po_sampel)
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

            $ujiSampel = DB::table('N_EMI_LAB_Uji_Sampel') 
                ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
                ->join('EMI_Master_Mesin', 'N_EMI_LAB_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
                ->leftJoin('N_EMI_LAB_Perhitungan', function ($join) {
                    $join->on('N_EMI_LAB_Perhitungan.id', '=', 'N_EMI_LAB_Uji_Sampel.Id_Perhitungan')
                        ->on('N_EMI_LAB_Perhitungan.Kode_Perusahaan', '=', 'N_EMI_LAB_Uji_Sampel.Kode_Perusahaan');
                })
                ->leftJoin('N_EMI_LAB_Standar_Rentang', function ($join) {
                    $join->on('N_EMI_LAB_Standar_Rentang.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa')
                        ->on('N_EMI_LAB_Standar_Rentang.Id_Master_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
                        ->on('N_EMI_LAB_Standar_Rentang.Kode_Barang', '=', 'N_EMI_LAB_PO_Sampel.Kode_Barang');
                })
                ->leftJoin('N_EMI_LAB_Palatabilitas_Pembanding as pb_vld', 'N_EMI_LAB_Uji_Sampel.Id_Pembanding', '=', 'pb_vld.Id_Pembanding')
                ->select(
                    'N_EMI_LAB_PO_Sampel.Kode_Barang',
                    'N_EMI_LAB_Uji_Sampel.No_Faktur',
                    'N_EMI_LAB_Uji_Sampel.No_Po_Sampel',
                    'N_EMI_LAB_Uji_Sampel.No_Fak_Sub_Po',
                    'N_EMI_LAB_PO_Sampel.No_Batch',
                    'N_EMI_LAB_Uji_Sampel.Tahapan_Ke',
                    'N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode',
                    'N_EMI_LAB_Uji_Sampel.Flag_Resampling',
                    'N_EMI_LAB_Uji_Sampel.Status_Keputusan_Sampel',
                    'N_EMI_LAB_Uji_Sampel.Flag_Layak',
                    'N_EMI_LAB_Uji_Sampel.Flag_Final',
                    'N_EMI_LAB_Uji_Sampel.Id_Mesin',
                    'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa',
                    'N_EMI_LAB_Uji_Sampel.Tanggal as Tanggal_Pengujian',
                    'N_EMI_LAB_Uji_Sampel.Hasil as Hasil_Akhir_Analisa',
                    'N_EMI_LAB_Uji_Sampel.Flag_Foto',
                    'N_EMI_LAB_PO_Sampel.No_Po',
                    'N_EMI_LAB_PO_Sampel.No_Split_Po',
                    'EMI_Master_Mesin.Flag_FG',
                    DB::raw("ISNULL(N_EMI_LAB_Perhitungan.Hasil_Perhitungan, 0) AS Pembulatan"),
                    'pb_vld.Nama_Pembanding',
                    DB::raw("
                        CASE
                            WHEN N_EMI_LAB_Standar_Rentang.Id_Standar_Rentang IS NOT NULL
                            THEN N_EMI_LAB_Standar_Rentang.Range_Awal
                            ELSE NULL
                        END AS Range_Awal
                    "),
                    DB::raw("
                        CASE
                            WHEN N_EMI_LAB_Standar_Rentang.Id_Standar_Rentang IS NOT NULL
                            THEN N_EMI_LAB_Standar_Rentang.Range_Akhir
                            ELSE NULL
                        END AS Range_Akhir
                    ")
                )
                ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
                ->where('N_EMI_LAB_Uji_Sampel.No_Po_Sampel', $no_po_sampel)
                ->where('N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa)
                ->where('N_EMI_LAB_Uji_Sampel.Status_Keputusan_Sampel', 'terima')
                ->get();

            if ($ujiSampel->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'status' => 200,
                    'message' => 'Data Tidak Ditemukan',
                ], 200);
            }

            // =========================
            // MAPPING STANDAR RENTANG NON PERHITUNGAN (KHUSUS LAB)
            // =========================
            $kriteriaRaw = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->where('Id_Jenis_Analisa', $id_jenis_analisa)
                ->where('Kode_Role', 'LAB')
                ->get(['Nilai_Kriteria', 'Keterangan_Kriteria']);

            $kriteriaNonPerhitunganList = [];
            foreach ($kriteriaRaw as $kriteria) {
                // Konversi float untuk otomatis menghilangkan ".0" dari database jika ada, lalu cast ke string
                $nilaiKey = is_numeric($kriteria->Nilai_Kriteria) 
                    ? (string) floatval($kriteria->Nilai_Kriteria) 
                    : trim((string) $kriteria->Nilai_Kriteria);
                    
                $kriteriaNonPerhitunganList[$nilaiKey] = $kriteria->Keterangan_Kriteria;
            }

            // =========================
            // FLAG FOTO
            // =========================
            $hasSesiFoto = $ujiSampel->contains('Flag_Foto', 'Y') ? 'Y' : 'T';

            $fakturList = $ujiSampel->pluck('No_Faktur')->unique()->toArray();

            $berkasRaw = DB::table('N_EMI_LAB_Berkas_Uji_Lab')
                ->select(
                    'Id_Berkas_Lab',
                    'No_Faktur',
                    'Berkas_Key',
                    'Keterangan'
                )
                ->whereIn('No_Faktur', $fakturList)
                ->get()
                ->groupBy('No_Faktur');

            // =========================
            // PARAMETER
            // =========================
            $parameterRaw = DB::table('N_EMI_LAB_Uji_Sampel_Detail')
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

            foreach ($ujiSampel as $sampel) {

                $sampel->Range_Awal = (float) $sampel->Range_Awal;
                $sampel->Range_Akhir = (float) $sampel->Range_Akhir;

                // =========================
                // PENGECEKAN HASIL ANALISA (INDUK)
                // =========================
                $hasilAsli = (string)$sampel->Hasil_Akhir_Analisa;

                // Format key pencarian untuk membuang '.0' jika ada, agar matching dengan array
                $keyCari = is_numeric($hasilAsli) 
                    ? (string) floatval($hasilAsli) 
                    : trim($hasilAsli);

                // Cek apakah nilai hasil ada di database master Non Perhitungan
                if (array_key_exists($keyCari, $kriteriaNonPerhitunganList)) {
                    // Tampilkan keterangannya jika cocok
                    $sampel->Hasil_Akhir_Analisa = $kriteriaNonPerhitunganList[$keyCari];
                } else {
                    // Jika tidak ada di db, tampilkan aslinya. (Format numerik jika ia berupa angka)
                    if (is_numeric($hasilAsli)) {
                        $sampel->Hasil_Akhir_Analisa = number_format(
                            (float)$hasilAsli,
                            $sampel->Pembulatan,
                            '.',
                            ''
                        );
                    } else {
                        $sampel->Hasil_Akhir_Analisa = $hasilAsli;
                    }
                }

                $parameters = $parameterRaw->get($sampel->No_Faktur)?->values() ?? collect([]);

                // Perhatikan penambahan "use ($kriteriaNonPerhitunganList)" di bawah ini
                $transformedParameters = $parameters->map(function ($param) use ($id_jenis_analisa, $kriteriaNonPerhitunganList) {
                    
                    // =========================
                    // PENGECEKAN HASIL ANALISA (PARAMETER DETAIL)
                    // =========================
                    $hasilParamAsli = (string)$param->Hasil_Analisa;
                    
                    $keyCariParam = is_numeric($hasilParamAsli) 
                        ? (string) floatval($hasilParamAsli) 
                        : trim($hasilParamAsli);

                    if (array_key_exists($keyCariParam, $kriteriaNonPerhitunganList)) {
                        $hasilParamFinal = $kriteriaNonPerhitunganList[$keyCariParam];
                    } else {
                        // Kembali ke format asal (dibulatkan ke 4 angka di belakang koma) jika bukan string keterangan
                        $hasilParamFinal = is_numeric($hasilParamAsli) 
                            ? round(floatval($hasilParamAsli), 4) 
                            : $hasilParamAsli;
                    }

                    return [
                        'Id_Jenis_Analisa' => Hashids::connection('custom')->encode($id_jenis_analisa),
                        'Id_Uji_Sample_Detail' => Hashids::connection('custom')->encode($param->Id_Uji_Sample_Detail),
                        'No_Faktur_Uji_Sample' => $param->No_Faktur_Uji_Sample,
                        'Id_Quality_Control' => Hashids::connection('custom')->encode($param->Id_Quality_Control),
                        'Hasil_Analisa' => $hasilParamFinal,
                        'Tanggal_Parameter_Analisa' => $param->Tanggal_Parameter_Analisa,
                        'Jam_Parameter_Analisa' => $param->Jam_Parameter_Analisa,
                    ];
                });

                // =========================
                // FOTO ANALISA
                // =========================
                $berkas = $berkasRaw->get($sampel->No_Faktur);

                $fotoList = [];

                if ($berkas) {
                    foreach ($berkas as $file) {
                        $fotoList[] = [
                            'Berkas_Key' => $file->Berkas_Key,
                            'Keterangan' => $file->Keterangan ?? '',
                        ];
                    }
                }

                $sampel->foto_analisa = $fotoList;

                unset($sampel->Flag_Foto);

                $sampel->parameter = $transformedParameters;

                $result[] = $sampel;
            }

            // ── PLT: cek apakah analisa ini PLT dan ambil pembanding ──
            $kodeAktivitasLabNoPcs = DB::table('N_EMI_LAB_Jenis_Analisa')
                ->where('id', $id_jenis_analisa)
                ->value('Kode_Aktivitas_Lab');
            $isPltFinalNoPcs = $kodeAktivitasLabNoPcs === 'PLT';
            $pltPembandingFinalNoPcs = [];
            if ($isPltFinalNoPcs && !empty($no_po_sampel)) {
                $pltPembandingFinalNoPcs = DB::table('N_EMI_LAB_Palatabilitas_Pembanding as pb')
                    ->join('N_EMI_LAB_Palatabilitas_Session as ps', 'pb.Id_Session', '=', 'ps.Id_Session')
                    ->where('ps.No_Po_Sampel', $no_po_sampel)
                    ->where('ps.Kode_Aktivitas_Lab', 'PLT')
                    ->where('pb.Flag_Aktif', 'Y')
                    ->select('pb.Urutan', 'pb.Nama_Pembanding', 'pb.Kode_Barang_Pembanding')
                    ->orderBy('pb.Urutan')
                    ->orderBy('pb.Id_Pembanding')
                    ->get()
                    ->map(fn($r) => ['nama' => $r->Nama_Pembanding, 'kode' => $r->Kode_Barang_Pembanding])
                    ->values()
                    ->toArray();
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Data Ditemukan',
                'result' => [
                    'informasi' => [
                        'sesi_foto'          => $hasSesiFoto,
                        'is_plt'             => $isPltFinalNoPcs,
                        'plt_pembanding'     => $pltPembandingFinalNoPcs,
                        'plt_pembanding_nama'=> $isPltFinalNoPcs && !empty($pltPembandingFinalNoPcs)
                                                    ? implode(', ', array_column($pltPembandingFinalNoPcs, 'nama'))
                                                    : null,
                    ],
                    'sampel' => $result
                ]
            ], 200);

        } catch (\Exception $e) {

            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Terjadi kesalahan pada server. Silahkan hubungi administrator.'
            ], 500);
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
            // Dekode ID di awal
            $decoded_id_jenis_analisa = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Jenis Analisa tidak valid.'
            ], 400);
        }

        // 1. Ambil SEMUA data sampel yang cocok menggunakan ->get()
        $daftarSampel = DB::table('N_EMI_LAB_Uji_Sampel as uji')
            ->join('N_EMI_LAB_PO_Sampel as po', 'uji.No_Po_Sampel', '=', 'po.No_Sampel')
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
            ->leftJoin('N_EMI_LAB_Palatabilitas_Pembanding as pb', 'uji.Id_Pembanding', '=', 'pb.Id_Pembanding')
            ->select(
                // Informasi
                'uji.No_Faktur', 'uji.No_Po_Sampel', 'uji.No_Fak_Sub_Po', 'uji.Flag_Perhitungan',
                'uji.Tanggal as Tanggal_Pengujian', 'uji.Jam as Jam_Pengujian',
                'po.Tanggal as Tanggal_Pengajuan', 'po.Jam as Jam_Pengajuan', 'po.No_Po',
                'po.Keterangan as Catatan', 'po.No_Split_Po', 'po.No_Batch', 'po.Kode_Barang',
                'mesin.Seri_Mesin', 'mesin.Nama_Mesin', 'jenis.Kode_Analisa', 'jenis.Jenis_Analisa',
                'jenis.Kode_Aktivitas_Lab',
                // Hasil & SOP
                'uji.Hasil as Hasil_Akhir_Analisa',
                DB::raw("ISNULL(hitung.Hasil_Perhitungan, 0) AS Pembulatan"),
                DB::raw("CAST(CASE WHEN standar.Id_Standar_Rentang IS NOT NULL THEN 1 ELSE 0 END AS BIT) as is_sop"),
                'standar.Range_Awal', 'standar.Range_Akhir',
                // PLT pembanding
                'uji.Id_Pembanding',
                'pb.Nama_Pembanding',
                'pb.Kode_Barang_Pembanding'
            )
            ->whereNull('uji.Status')
            ->where('uji.No_Po_Sampel', $no_po_sampel)
            ->where('uji.Id_Jenis_Analisa', $decoded_id_jenis_analisa)
            ->where('uji.Flag_Selesai', 'Y')
            ->orderBy('uji.No_Faktur') // Disarankan untuk menambah urutan agar data konsisten
            ->get(); // ✅ KUNCI PERUBAHAN: Mengambil semua data yang cocok

        // Jika tidak ada data sampel sama sekali, kembalikan pesan
        if ($daftarSampel->isEmpty()) {
            return response()->json([
                'success' => true, 'status' => 200, 'message' => 'Data Tidak Ditemukan'
            ], 200);
        }

        // 2. LAKUKAN LOOPING UNTUK SETIAP SAMPEL
        // Kita gunakan ->map() untuk mengubah setiap item di $daftarSampel
        $hasilProses = $daftarSampel->map(function ($sampel) use ($id_jenis_analisa) {
            
            // Ambil parameter detail yang terkait dengan sampel SAAT INI
            $parameterDetails = DB::table('N_EMI_LAB_Uji_Sampel_Detail')
                ->select(
                    'Id_Uji_Sample_Detail', 'No_Faktur_Uji_Sample', 'Id_Quality_Control',
                    'Value_Parameter as Hasil_Analisa',
                    'Tanggal as Tanggal_Parameter_Analisa', 'Jam as Jam_Parameter_Analisa'
                )
                // Menggunakan No_Faktur dari $sampel yang sedang di-loop
                ->where('No_Faktur_Uji_Sample', $sampel->No_Faktur) 
                ->get();

            // Proses dan format parameter detail untuk sampel ini
            $transformedParameters = $parameterDetails->map(function ($param) use ($sampel, $id_jenis_analisa) {
                $hasilFloat = floatval($param->Hasil_Analisa);
                $hasilTampil = round($hasilFloat, 4); 

                // Kondisi khusus untuk 'MBLG-STR'
                if ($sampel->Kode_Analisa === 'MBLG-STR') {
                    if ($hasilFloat == -999999) $hasilTampil = '-';
                    if ($hasilFloat == -88888888) $hasilTampil = '+';
                }

                return [
                    'Id_Jenis_Analisa'          => $id_jenis_analisa,
                    'Id_Uji_Sample_Detail'      => Hashids::connection('custom')->encode($param->Id_Uji_Sample_Detail),
                    'No_Faktur_Uji_Sample'      => $param->No_Faktur_Uji_Sample,
                    'Id_Quality_Control'        => Hashids::connection('custom')->encode($param->Id_Quality_Control),
                    'Hasil_Analisa'             => $hasilTampil,
                    'Tanggal_Parameter_Analisa' => $param->Tanggal_Parameter_Analisa,
                    'Jam_Parameter_Analisa'     => $param->Jam_Parameter_Analisa,
                ];
            });

            // Format data utama sampel ini
            $sampel->is_sop = (bool) $sampel->is_sop;
            $sampel->Range_Awal = (float) $sampel->Range_Awal;
            $sampel->Range_Akhir = (float) $sampel->Range_Akhir;
            $sampel->Hasil_Akhir_Analisa = number_format((float)$sampel->Hasil_Akhir_Analisa, $sampel->Pembulatan, '.', '');

            // Gabungkan data sampel yang sudah diformat dengan parameternya
            $sampelData = (array) $sampel;
            $sampelData['parameter'] = $transformedParameters;
            
            // Kembalikan data yang sudah lengkap untuk sampel ini
            return $sampelData;
        });

        // 3. Buat objek `informasi` dari data sampel PERTAMA
        // Karena informasi ini (No PO, Mesin, dll) seharusnya sama untuk semua baris
        $sampelPertama = $daftarSampel->first();
        $isPlt = ($sampelPertama->Kode_Aktivitas_Lab ?? null) === 'PLT';

        // Ambil daftar bahan pembanding via session PLT (dengan filter Flag_Aktif)
        $pltPembanding = [];
        if ($isPlt) {
            $pltPembanding = DB::table('N_EMI_LAB_Palatabilitas_Pembanding as pb')
                ->join('N_EMI_LAB_Palatabilitas_Session as ps', 'pb.Id_Session', '=', 'ps.Id_Session')
                ->where('ps.No_Po_Sampel', $sampelPertama->No_Po_Sampel)
                ->where('ps.Kode_Aktivitas_Lab', 'PLT')
                ->where('pb.Flag_Aktif', 'Y')
                ->select('pb.Urutan', 'pb.Nama_Pembanding', 'pb.Kode_Barang_Pembanding')
                ->orderBy('pb.Urutan')
                ->orderBy('pb.Id_Pembanding')
                ->get()
                ->map(fn($r) => [
                    'nama' => $r->Nama_Pembanding,
                    'kode' => $r->Kode_Barang_Pembanding,
                ])
                ->values()
                ->toArray();
        }

        $informasi = [
            'No_Faktur'            => $sampelPertama->No_Faktur,
            'No_Po_Sampel'         => $sampelPertama->No_Po_Sampel,
            'Flag_Perhitungan'     => $sampelPertama->Flag_Perhitungan,
            'Tanggal_Pengujian'    => $sampelPertama->Tanggal_Pengujian,
            'Jam_Pengujian'        => $sampelPertama->Jam_Pengujian,
            'Tanggal_Pengajuan'    => $sampelPertama->Tanggal_Pengajuan,
            'Jam_Pengajuan'        => $sampelPertama->Jam_Pengajuan,
            'No_Po'                => $sampelPertama->No_Po,
            'Catatan'              => $sampelPertama->Catatan,
            'No_Split_Po'          => $sampelPertama->No_Split_Po,
            'No_Batch'             => $sampelPertama->No_Batch,
            'Kode_Barang'          => $sampelPertama->Kode_Barang,
            'Seri_Mesin'           => $sampelPertama->Seri_Mesin,
            'Nama_Mesin'           => $sampelPertama->Nama_Mesin,
            'Kode_Analisa'         => $sampelPertama->Kode_Analisa,
            'Jenis_Analisa'        => $sampelPertama->Jenis_Analisa,
            'Kode_Aktivitas_Lab'   => $sampelPertama->Kode_Aktivitas_Lab,
            'is_plt'               => $isPlt,
            'plt_pembanding'       => $pltPembanding,
            'plt_pembanding_nama'  => $isPlt && !empty($pltPembanding)
                                         ? implode(', ', array_column($pltPembanding, 'nama'))
                                         : null,
        ];

        // 4. Kembalikan respons dalam struktur yang diharapkan frontend
        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Data Ditemukan',
            'result'  => [
                'informasi' => $informasi,
                'sampel'    => $hasilProses // ✅ Kirim sebagai array dengan BANYAK elemen
            ]
        ], 200);
    }

    // public function downloadRekapSampel(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'analysis' => 'required|array',
    //         'analysis.*' => 'required|string',
    //         'startDate' => 'required|date',
    //         'endDate' => 'required|date|after_or_equal:startDate',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'success' => false,
    //             'status'  => 400,
    //             'message' => 'Input tidak valid.',
    //             'errors'  => $validator->errors()
    //         ], 400);
    //     }

    //     $tempPath = storage_path('app/temp_exports/' . uniqid());
    //     File::makeDirectory($tempPath, 0775, true, true);
        
    //     $filePaths = [];

    //     $checkedIdMaster = $request->Id_Master_Mesin;
    //     if ($checkedIdMaster !== "all") {
    //         $decoded = Hashids::connection('custom')->decode($checkedIdMaster);            
    //         $checkedIdMaster = $decoded[0] ?? null;
    //     }

    //     $targetAnalyses = [];
    //     $analysisIds = [];

    //     foreach ($request->analysis as $index => $hashedIdAnalisa) {
    //         $decodedId = Hashids::connection('custom')->decode($hashedIdAnalisa)[0] ?? null;
    //         if ($decodedId) {
    //             $analysisIds[] = $decodedId;
    //             $targetAnalyses[] = [
    //                 'id' => $decodedId,
    //                 'index' => $index,
    //             ];
    //         }
    //     }

    //     if (empty($analysisIds)) {
    //         return response()->json(['success' => false, 'status' => 404, 'message' => 'Tidak ada analisa valid.'], 404);
    //     }

    //     $jenisAnalisaMap = DB::table('N_EMI_LAB_Jenis_Analisa')
    //         ->whereIn('id', $analysisIds)
    //         ->get()
    //         ->keyBy('id');

    //     $parametersMap = DB::table('N_EMI_LAB_Binding_jenis_analisa as b')
    //         ->join('EMI_Quality_Control as q', 'q.Id_QC_Formula', '=', 'b.Id_Quality_Control')
    //         ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ja.id', '=', 'b.Id_Jenis_Analisa')
    //         ->leftJoin('EMI_Kategori_Komponen as kk', 'kk.Id_Kategori_Komponen', '=', 'q.Id_Kategori_Komponen')
    //         ->whereIn('b.Id_Jenis_Analisa', $analysisIds)
    //         ->select(
    //             'b.id', 'b.Id_Quality_Control as id_qc', 'b.Id_Jenis_Analisa',
    //             'q.Keterangan as nama_parameter', 'kk.Keterangan AS type_inputan',
    //             'q.Satuan as satuan', 'q.Kode_Uji as kode_uji', 'ja.Kode_Analisa as kode_analisa',
    //             'ja.Jenis_Analisa as jenis_analisa', 'ja.Flag_Perhitungan as flag_perhitungan'
    //         )
    //         ->get()
    //         ->groupBy('Id_Jenis_Analisa');

    //     $rumusMap = DB::table('N_EMI_LAB_Perhitungan')
    //         ->whereIn('Id_Jenis_Analisa', $analysisIds)
    //         ->select('Id', 'Id_Jenis_Analisa', 'Rumus as rumus', 'Nama_Kolom as nama_kolom', 'Hasil_Perhitungan as digit')
    //         ->get()
    //         ->groupBy('Id_Jenis_Analisa');

    //     $nonCalcMap = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
    //         ->whereIn('Id_Jenis_Analisa', $analysisIds)
    //         ->where('Flag_Aktif', 'Y')
    //         ->select('Id_Jenis_Analisa', 'Nilai_Kriteria', 'Keterangan_Kriteria')
    //         ->get()
    //         ->groupBy('Id_Jenis_Analisa');

    //     $requestFlags = $request->Flag_Perhitungan ?? [];

    //     foreach ($targetAnalyses as $target) {
    //         $id_analisa = $target['id'];
    //         $index = $target['index'];

    //         $getNamaJenisAnalisa = $jenisAnalisaMap->get($id_analisa);
    //         if (!$getNamaJenisAnalisa) continue;

    //         $getParameter = $parametersMap->get($id_analisa);
    //         if (!$getParameter || $getParameter->isEmpty()) continue;

    //         $flagPerhitungan = $requestFlags[$index] ?? null;
    //         $isPerhitungan = $flagPerhitungan === 'Y';
            
    //         $getDataRumus = $isPerhitungan ? ($rumusMap->get($id_analisa) ?? collect([])) : null;

    //         $currentNonCalcLookup = [];
    //         if (!$isPerhitungan && isset($nonCalcMap[$id_analisa])) {
    //             foreach ($nonCalcMap[$id_analisa] as $item) {
    //                 // Normalisasi nilai kriteria ke string float agar match dengan data hasil
    //                 $cleanKey = (string)((float)$item->Nilai_Kriteria);
    //                 $currentNonCalcLookup[$cleanKey] = $item->Keterangan_Kriteria;
    //             }
    //         }

    //         $hashedParameters = $getParameter->map(function ($param) {
    //             return [
    //                 'id' => Hashids::connection('custom')->encode($param->id),
    //                 'id_qc' => Hashids::connection('custom')->encode($param->id_qc),
    //                 'id_jenis_analisa' => Hashids::connection('custom')->encode($param->Id_Jenis_Analisa),
    //                 'nama_parameter' => $param->nama_parameter,
    //                 'type_inputan' => $param->type_inputan,
    //                 'satuan' => $param->satuan,
    //                 'kode_uji' => $param->kode_uji,
    //                 'kode_analisa' => $param->kode_analisa,
    //                 'jenis_analisa' => $param->jenis_analisa,
    //                 'flag_perhitungan' => $param->flag_perhitungan,
    //             ];
    //         })->toArray();

    //         $hashedFormula = ($getDataRumus && $getDataRumus->isNotEmpty()) ? $getDataRumus->map(function ($rumus) {
    //             $processedRumus = preg_replace_callback('/\[(\d+)\]/', function ($matches) {
    //                 return '[' . Hashids::connection('custom')->encode($matches[1]) . ']';
    //             }, $rumus->rumus);
    //             return [
    //                 'id' => Hashids::connection('custom')->encode($rumus->Id),
    //                 'id_jenis_analisa' => Hashids::connection('custom')->encode($rumus->Id_Jenis_Analisa),
    //                 'rumus' => $processedRumus,
    //                 'nama_kolom' => $rumus->nama_kolom,
    //                 'digit' => $rumus->digit,
    //             ];
    //         })->toArray() : null;

    //         $ujiSampelQuery = DB::table('N_EMI_LAB_Uji_Sampel')
    //             ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
    //             ->join('EMI_Master_Mesin', 'N_EMI_LAB_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
    //             ->leftJoin('N_EMI_LAB_Perhitungan', function ($join) {
    //                 $join->on('N_EMI_LAB_Perhitungan.id', '=', 'N_EMI_LAB_Uji_Sampel.Id_Perhitungan')
    //                         ->on('N_EMI_LAB_Perhitungan.Kode_Perusahaan', '=', 'N_EMI_LAB_Uji_Sampel.Kode_Perusahaan');
    //             })
    //             ->select(
    //                 'N_EMI_LAB_Uji_Sampel.No_Faktur', 
    //                 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel', 
    //                 'N_EMI_LAB_Uji_Sampel.No_Fak_Sub_Po',
    //                 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', 
    //                 'N_EMI_LAB_Uji_Sampel.Id_Mesin', 
    //                 'N_EMI_LAB_Uji_Sampel.Tanggal as Tanggal_Pengujian',
    //                 'N_EMI_LAB_Uji_Sampel.Hasil as Hasil_Akhir_Analisa', 
    //                 'N_EMI_LAB_PO_Sampel.No_Po', 
    //                 'N_EMI_LAB_PO_Sampel.No_Split_Po',
    //                 'N_EMI_LAB_PO_Sampel.Flag_Selesai'
    //             )
    //             ->addSelect(DB::raw("ISNULL(N_EMI_LAB_Perhitungan.Hasil_Perhitungan, 0) AS Pembulatan"))
    //             ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
    //             ->where('N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', $id_analisa)
    //             ->where('N_EMI_LAB_Uji_Sampel.Flag_Selesai', 'Y')
    //             ->whereBetween('N_EMI_LAB_Uji_Sampel.Tanggal', [$request->startDate, $request->endDate]);

    //         if ($checkedIdMaster !== "all") {
    //             $ujiSampelQuery->where('N_EMI_LAB_Uji_Sampel.Id_Mesin', $checkedIdMaster);
    //         }

    //         $ujiSampel = $ujiSampelQuery->get();

    //         if ($ujiSampel->isEmpty()) continue;

    //         // 1. Logic Ganti Hasil Induk (Hasil_Akhir_Analisa)
    //         foreach ($ujiSampel as $item) {
    //             $cleanVal = (string)((float)$item->Hasil_Akhir_Analisa);
                
    //             if (!$isPerhitungan && isset($currentNonCalcLookup[$cleanVal])) {
    //                 $item->Hasil_Akhir_Analisa = $currentNonCalcLookup[$cleanVal];
    //             } else {
    //                 $item->Hasil_Akhir_Analisa = number_format((float)$item->Hasil_Akhir_Analisa, $item->Pembulatan, '.', '');
    //             }
    //         }

    //         $parameterRaw = DB::table('N_EMI_LAB_Uji_Sampel_Detail')
    //             ->select('Id_Uji_Sample_Detail', 'No_Faktur_Uji_Sample', 'Id_Quality_Control', 'Value_Parameter as Hasil_Analisa', 'Tanggal as Tanggal_Parameter_Analisa', 'Jam as Jam_Parameter_Analisa')
    //             ->whereIn('No_Faktur_Uji_Sample', $ujiSampel->pluck('No_Faktur'))
    //             ->get()
    //             ->groupBy('No_Faktur_Uji_Sample');
            
    //         $result = [];
    //         foreach ($ujiSampel as $sampel) {
    //             $item = (array) $sampel;
    //             $parameters = $parameterRaw->get($sampel->No_Faktur)?->values() ?? collect([]);
                
    //             // 2. Logic Ganti Hasil Detail Parameter (Hasil_Analisa)
    //             // Passing $isPerhitungan dan $currentNonCalcLookup ke dalam closure
    //             $transformedParameters = $parameters->map(function ($param) use ($id_analisa, $isPerhitungan, $currentNonCalcLookup) {
                    
    //                 $valParam = $param->Hasil_Analisa;
    //                 $cleanParamVal = (string)((float)$valParam);
                    
    //                 // Cek logic yang sama dengan Induk
    //                 if (!$isPerhitungan && isset($currentNonCalcLookup[$cleanParamVal])) {
    //                     $finalHasil = $currentNonCalcLookup[$cleanParamVal];
    //                 } else {
    //                     $finalHasil = round(floatval($valParam), 4);
    //                 }

    //                 return [
    //                     'Id_Jenis_Analisa' => Hashids::connection('custom')->encode($id_analisa),
    //                     'Id_Uji_Sample_Detail' => Hashids::connection('custom')->encode($param->Id_Uji_Sample_Detail),
    //                     'No_Faktur_Uji_Sample' => $param->No_Faktur_Uji_Sample,
    //                     'Id_Quality_Control' => Hashids::connection('custom')->encode($param->Id_Quality_Control),
    //                     'Hasil_Analisa' => $finalHasil, // Menggunakan hasil yang sudah diproses
    //                     'Tanggal_Parameter_Analisa' => $param->Tanggal_Parameter_Analisa,
    //                     'Jam_Parameter_Analisa' => $param->Jam_Parameter_Analisa,
    //                 ];
    //             });

    //             $item['parameter'] = $transformedParameters;
    //             $result[] = $item;
    //         }

    //         $start = date('d-m-Y', strtotime($request->startDate));
    //         $end = date('d-m-Y', strtotime($request->endDate));
    //         $periode = $start . '_sampai_' . $end;
    //         $namaAnalisa = ucwords(strtolower($getNamaJenisAnalisa->Jenis_Analisa));
    //         $safeNamaAnalisa = preg_replace('/[^A-Za-z0-9\-]/', '_', $namaAnalisa);
    //         $excelFileName = 'Rekap ' . $safeNamaAnalisa . ' Periode ' . $periode . '.xlsx';
            
    //         Excel::store(
    //             new RekapSampelExport($result, $hashedParameters, $hashedFormula ?? [], $namaAnalisa),
    //             'temp_exports/' . basename($tempPath) . '/' . $excelFileName
    //         );

    //         $filePaths[] = $tempPath . DIRECTORY_SEPARATOR . $excelFileName;
    //     }

    //     if (empty($filePaths)) {
    //         return response()->json([
    //             'success' => false,
    //             'status' => 404,
    //             'message' => 'Tidak ada data yang dapat diproses untuk kriteria yang dipilih.',
    //         ], 404);
    //     }

    //     if (count($filePaths) === 1) {
    //         return response()->download($filePaths[0])->deleteFileAfterSend(true);
    //     }

    //     $zip = new ZipArchive;
    //     $zipFileName = 'Rekap Sampel ' . date('d-m-Y_H-i-s') . '.zip';
    //     $zipPath = $tempPath . DIRECTORY_SEPARATOR . $zipFileName;

    //     if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
    //         foreach ($filePaths as $file) {
    //             if (File::exists($file)) {
    //                 $zip->addFile($file, basename($file));
    //             }
    //         }
    //         $zip->close();
    //     } else {
    //         File::deleteDirectory($tempPath);
    //         return response()->json(['success' => false, 'status' => 500, 'message' => 'Gagal membuat file arsip ZIP.'], 500);
    //     }

    //     return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
    // }

    public function dispatchExportJob(Request $request)
    {
        // Validasi basic
        $request->validate([
            'format' => 'required|in:pdf,excell',
            'jenis_print' => 'nullable|string', // 'psz' atau 'ringkas' atau null
            'analysis' => 'required',
            'startDate' => 'required|date',
            'endDate' => 'required|date',
        ]);

        $trackId  = (string) Str::uuid();
        $queueConn = config('queue.default');
        $handlerUrl = config('queue.connections.cloudtasks.handler', '-');
        $cloudTasksUri = config('cloud-tasks.uri', 'handle-task');

        Log::channel('export_job')->info('[DISPATCH] dispatchExportJob dipanggil', [
            'track_id'        => $trackId,
            'queue_conn'      => $queueConn,
            'handler_url'     => $handlerUrl,
            'cloud_tasks_uri' => $cloudTasksUri,
            'format'          => $request->format,
            'jenis_print'     => $request->jenis_print,
            'env'             => app()->environment(),
        ]);

        DB::table('N_EMI_LAB_Export_Tracking')->insert([
            'id'           => $trackId,
            'jenis_export' => $request->jenis_print === 'psz' ? 'psz_' . $request->format : 'rekap_' . $request->format,
            'status'       => 'pending',
            'progress'     => 0,
            'message'      => 'Menunggu antrean...',
            'created_at'   => Carbon::now(),
            'updated_at'   => Carbon::now(),
        ]);

        $payload = [
            'track_id'         => $trackId,
            'format'           => $request->format,
            'jenis_print'      => $request->jenis_print,
            'analysis'         => is_array($request->analysis) ? $request->analysis : [$request->analysis],
            'Flag_Perhitungan' => is_array($request->Flag_Perhitungan) ? $request->Flag_Perhitungan : [$request->Flag_Perhitungan],
            'startDate'        => $request->startDate,
            'endDate'          => $request->endDate,
            'Id_Master_Mesin'  => $request->Id_Master_Mesin,
        ];

        try {
            ExportRekapSampelJob::dispatch($payload);
            Log::channel('export_job')->info('[DISPATCH] Job berhasil di-dispatch', [
                'track_id'   => $trackId,
                'queue_conn' => $queueConn,
            ]);
        } catch (\Exception $e) {
            Log::channel('export_job')->error('[DISPATCH] Gagal dispatch job', [
                'track_id' => $trackId,
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
            ]);
            DB::table('N_EMI_LAB_Export_Tracking')->where('id', $trackId)->update([
                'status'        => 'failed',
                'message'       => 'Gagal mengirim ke antrian.',
                'error_message' => $e->getMessage(),
                'updated_at'    => Carbon::now(),
            ]);
            return response()->json(['success' => false, 'status' => 500, 'message' => 'Gagal mengirim job ke antrian: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'status'  => 202,
            'message' => 'Laporan sedang diproses di background.',
            'data'    => ['track_id' => $trackId],
        ], 202);
    }

    public function checkExportStatus($trackId)
    {
        $tracking = DB::table('N_EMI_LAB_Export_Tracking')->where('id', $trackId)->first();

        if (!$tracking) {
            return response()->json(['success' => false, 'message' => 'Tracking ID tidak ditemukan'], 404);
        }

        return response()->json([
            'success' => true,
            'status'   => $tracking->status,
            'progress' => $tracking->progress,
            'message'  => $tracking->message,
            'file_url' => $tracking->file_url,
        ]);
    }

    /**
     * Stream file ekspor dari GCS ke browser.
     * Route: GET /api/v1/export-download/{trackId}
     */
    public function downloadExport($trackId)
    {
        $tracking = DB::table('N_EMI_LAB_Export_Tracking')->where('id', $trackId)->first();

        if (!$tracking || !$tracking->file_path) {
            return response()->json(['success' => false, 'message' => 'File belum tersedia.'], 404);
        }

        if ($tracking->status !== 'completed') {
            return response()->json(['success' => false, 'message' => 'Ekspor belum selesai diproses.'], 409);
        }

        if (!Storage::disk('gcs')->exists($tracking->file_path)) {
            return response()->json(['success' => false, 'message' => 'File tidak ditemukan di storage.'], 404);
        }

        $fileName = basename($tracking->file_path);
        $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $mimeMap  = [
            'pdf'  => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'zip'  => 'application/zip',
        ];
        $mimeType = $mimeMap[$ext] ?? 'application/octet-stream';

        $stream = Storage::disk('gcs')->readStream($tracking->file_path);

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) fclose($stream);
        }, 200, [
            'Content-Type'        => $mimeType,
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control'       => 'no-store',
        ]);
    }

    /**
     * Hapus file ekspor dari GCS dan record tracking.
     * Route: DELETE /api/v1/export-file/{trackId}
     */
    public function deleteExportFile($trackId)
    {
        $tracking = DB::table('N_EMI_LAB_Export_Tracking')->where('id', $trackId)->first();

        if (!$tracking) {
            return response()->json(['success' => false, 'message' => 'Tracking tidak ditemukan.'], 404);
        }

        if ($tracking->file_path && Storage::disk('gcs')->exists($tracking->file_path)) {
            Storage::disk('gcs')->delete($tracking->file_path);
        }

        DB::table('N_EMI_LAB_Export_Tracking')->where('id', $trackId)->delete();

        return response()->json(['success' => true, 'message' => 'File berhasil dihapus.']);
    }

    public function downloadRekapSampel(Request $request)
    {
        // 1. Validasi Input
        $validator = Validator::make($request->all(), [
            'analysis' => 'required|array',
            'analysis.*' => 'required|string',
            'startDate' => 'required|date',
            'endDate' => 'required|date|after_or_equal:startDate',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'status' => 400, 'message' => 'Input tidak valid.', 'errors' => $validator->errors()], 400);
        }

        // 2. Setup Folder
        $tempPath = storage_path('app/temp_exports/' . uniqid());
        File::makeDirectory($tempPath, 0775, true, true);
        $filePaths = [];

        // 3. Decode Filter Mesin (Sekali saja)
        $checkedIdMaster = $request->Id_Master_Mesin;
        $filterMesinId = null;
        if ($checkedIdMaster !== "all") {
            $decoded = Hashids::connection('custom')->decode($checkedIdMaster);
            $filterMesinId = $decoded[0] ?? null;
        }

        // 4. Decode Analysis IDs (Sekali saja)
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

        // 5. PRE-FETCH DATA PENDUKUNG (Sekali Query untuk semua)
        // Gunakan toBase() agar ringan (array of objects, bukan Models)
        $jenisAnalisaMap = DB::table('N_EMI_LAB_Jenis_Analisa')->whereIn('id', $analysisIds)->get()->keyBy('id');
        
        $parametersMap = DB::table('N_EMI_LAB_Binding_jenis_analisa as b')
            ->join('EMI_Quality_Control as q', 'q.Id_QC_Formula', '=', 'b.Id_Quality_Control')
            ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ja.id', '=', 'b.Id_Jenis_Analisa')
            ->leftJoin('EMI_Kategori_Komponen as kk', 'kk.Id_Kategori_Komponen', '=', 'q.Id_Kategori_Komponen')
            ->whereIn('b.Id_Jenis_Analisa', $analysisIds)
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
            ->select('Id', 'Id_Jenis_Analisa', 'Rumus as rumus', 'Nama_Kolom as nama_kolom', 'Hasil_Perhitungan as digit')
            ->get()
            ->groupBy('Id_Jenis_Analisa');

        $nonCalcMap = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
            ->whereIn('Id_Jenis_Analisa', $analysisIds)
            ->where('Flag_Aktif', 'Y')
            ->select('Id_Jenis_Analisa', 'Nilai_Kriteria', 'Keterangan_Kriteria')
            ->get()
            ->groupBy('Id_Jenis_Analisa');

        $requestFlags = $request->Flag_Perhitungan ?? [];

        // --- MULAI LOOPING ANALISA ---
        foreach ($targetAnalyses as $target) {
            $id_analisa = $target['id'];
            $index = $target['index'];

            $getNamaJenisAnalisa = $jenisAnalisaMap->get($id_analisa);
            if (!$getNamaJenisAnalisa) continue;

            $getParameter = $parametersMap->get($id_analisa);
            if (!$getParameter || $getParameter->isEmpty()) continue;

            // Persiapan Logic
            $flagPerhitungan = $requestFlags[$index] ?? null;
            $isPerhitungan = $flagPerhitungan === 'Y';
            $getDataRumus = $isPerhitungan ? ($rumusMap->get($id_analisa) ?? collect([])) : null;

            // Buat Lookup Array yang efisien
            $currentNonCalcLookup = [];
            if (!$isPerhitungan && isset($nonCalcMap[$id_analisa])) {
                foreach ($nonCalcMap[$id_analisa] as $item) {
                    $cleanKey = (string)((float)$item->Nilai_Kriteria);
                    $currentNonCalcLookup[$cleanKey] = $item->Keterangan_Kriteria;
                }
            }

            // Cache Hash ID Jenis Analisa (Optimasi CPU)
            $hashedIdJenisAnalisa = Hashids::connection('custom')->encode($id_analisa);

            // --- QUERY HEADER (SAMPEL) ---
            $queryHeader = DB::table('N_EMI_LAB_Uji_Sampel as US')
                ->join('N_EMI_LAB_PO_Sampel as PO', 'US.No_Po_Sampel', '=', 'PO.No_Sampel')
                ->join('EMI_Master_Mesin as M', 'PO.Id_Mesin', '=', 'M.Id_Master_Mesin')
                ->leftJoin('N_EMI_LAB_Perhitungan as C', function ($join) {
                    $join->on('C.id', '=', 'US.Id_Perhitungan')
                        ->on('C.Kode_Perusahaan', '=', 'US.Kode_Perusahaan');
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

            $ujiSampel = $queryHeader->get(); // Collection of objects

            if ($ujiSampel->isEmpty()) continue;

            // --- QUERY DETAIL (PARAMETER) ---
            // OPTIMASI: Jangan pakai whereIn(pluck IDs) karena lambat & limit SQL.
            // Gunakan JOIN ke Header untuk filter yang sama.
            $parameterRaw = DB::table('N_EMI_LAB_Uji_Sampel_Detail as D')
                ->join('N_EMI_LAB_Uji_Sampel as US', 'D.No_Faktur_Uji_Sample', '=', 'US.No_Faktur')
                ->select(
                    'D.Id_Uji_Sample_Detail', 
                    'D.No_Faktur_Uji_Sample', 
                    'D.Id_Quality_Control', 
                    'D.Value_Parameter as Hasil_Analisa', 
                    'D.Tanggal as Tanggal_Parameter_Analisa', 
                    'D.Jam as Jam_Parameter_Analisa'
                )
                ->where('US.Id_Jenis_Analisa', $id_analisa)
                ->where('US.Flag_Selesai', 'Y')
                ->whereNull('US.Status')
                ->whereBetween('US.Tanggal', [$request->startDate, $request->endDate])
                ->when($filterMesinId, function($q) use ($filterMesinId) {
                    return $q->where('US.Id_Mesin', $filterMesinId);
                })
                ->get()
                ->groupBy('No_Faktur_Uji_Sample');

            // --- PROCESSING DATA ---
            $result = []; // INI ARRAY, BUKAN COLLECTION (Solusi Error TypeError)

            foreach ($ujiSampel as $itemObj) {
                $item = (array) $itemObj; // Cast object ke array

                // 1. Logic Hasil Header
                $cleanVal = (string)((float)$item['Hasil_Akhir_Analisa']);
                if (!$isPerhitungan && isset($currentNonCalcLookup[$cleanVal])) {
                    $item['Hasil_Akhir_Analisa'] = $currentNonCalcLookup[$cleanVal];
                } else {
                    $item['Hasil_Akhir_Analisa'] = number_format((float)$item['Hasil_Akhir_Analisa'], $item['Pembulatan'], '.', '');
                }

                // 2. Logic Parameter
                $rawParams = $parameterRaw->get($item['No_Faktur']);
                $processedParams = [];

                if ($rawParams) {
                    foreach ($rawParams as $param) {
                        $valParam = $param->Hasil_Analisa;
                        $cleanParamVal = (string)((float)$valParam);

                        if (!$isPerhitungan && isset($currentNonCalcLookup[$cleanParamVal])) {
                            $finalHasil = $currentNonCalcLookup[$cleanParamVal];
                        } else {
                            $finalHasil = round(floatval($valParam), 4);
                        }

                        $processedParams[] = [
                            'Id_Jenis_Analisa' => $hashedIdJenisAnalisa, // Pakai yang sudah dicache
                            'Id_Uji_Sample_Detail' => Hashids::connection('custom')->encode($param->Id_Uji_Sample_Detail),
                            'No_Faktur_Uji_Sample' => $param->No_Faktur_Uji_Sample,
                            'Id_Quality_Control' => Hashids::connection('custom')->encode($param->Id_Quality_Control),
                            'Hasil_Analisa' => $finalHasil,
                            'Tanggal_Parameter_Analisa' => $param->Tanggal_Parameter_Analisa,
                            'Jam_Parameter_Analisa' => $param->Jam_Parameter_Analisa,
                        ];
                    }
                }
                
                $item['parameter'] = $processedParams; // Array di dalam Array
                $result[] = $item; // Push ke array utama
            }

            // --- EXPORTING ---
            // Siapkan header parameter (sekali saja di luar loop)
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
            
            // PASTIKAN $result ADALAH ARRAY
            Excel::store(
                new RekapSampelExport($result, $hashedParameters, $hashedFormula, $namaAnalisa),
                'temp_exports/' . basename($tempPath) . '/' . $excelFileName
            );

            $filePaths[] = $tempPath . DIRECTORY_SEPARATOR . $excelFileName;
        }

        // --- ZIP & RESPONSE (Sama seperti sebelumnya) ---
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
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
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

        $getNamaJenisAnalisa = DB::table('N_EMI_LAB_Jenis_Analisa')->where('id', $id_analisa)->first();
        if (!$getNamaJenisAnalisa) return [];

        $getParameter = DB::table('N_EMI_LAB_Binding_jenis_analisa as b')
            ->join('EMI_Quality_Control as q', 'q.Id_QC_Formula', '=', 'b.Id_Quality_Control')
            ->where('b.Id_Jenis_Analisa', $id_analisa)
            ->select('q.Keterangan as nama_parameter')
            ->get();

        $getDataRumus = [];
        if ($isPerhitungan) {
            $getDataRumus = DB::table('N_EMI_LAB_Perhitungan')
                ->where('Id_Jenis_Analisa', $id_analisa)
                ->select('Nama_Kolom as nama_kolom')
                ->get();
        }

        $standarMap = [];
        if (!$isPerhitungan) {
            $rawStandar = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->where('Id_Jenis_Analisa', $id_analisa)
                ->where('Flag_Aktif', 'Y')
                ->get();

            foreach ($rawStandar as $std) {
                $cleanKey = (string)((float)$std->Nilai_Kriteria);
                $standarMap[$cleanKey] = $std->Keterangan_Kriteria;
            }
        }

        $ujiSampelQuery = DB::table('N_EMI_LAB_Uji_Sampel as us')
            ->join('N_EMI_LAB_PO_Sampel as ps', 'us.No_Po_Sampel', '=', 'ps.No_Sampel')
            ->leftJoin('N_EMI_LAB_Perhitungan as p', 'p.id', '=', 'us.Id_Perhitungan')
            ->select(
                'us.No_Faktur', 'us.No_Po_Sampel', 'ps.No_Po', 'ps.No_Batch', 'ps.No_Split_Po',
                'us.Id_Mesin', 'ps.Kode_Barang', 'us.Tanggal as Tanggal_Pengujian',
                'us.Hasil as Hasil_Akhir_Analisa', DB::raw("COALESCE(p.Hasil_Perhitungan, 2) AS Pembulatan")
            )
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
            $batchData = DB::table('N_EMI_LAB_Uji_Sampel_Detail as usd')
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
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
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

        $validator = Validator::make($request->all(), [
            'analysis' => 'required|array',
            'analysis.*' => 'required|string',
            'Flag_Perhitungan' => 'required|array',
            'startDate' => 'required|date',
            'endDate' => 'required|date|after_or_equal:startDate',
            'Id_Master_Mesin' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Input tidak valid', 'errors' => $validator->errors()], 400);
        }

        try {
            // --- 1. SETUP QUERY (SAMA) ---
            $start = $request->startDate;
            $end = $request->endDate;
            $checkedIdMaster = $request->Id_Master_Mesin;

            if ($checkedIdMaster && $checkedIdMaster !== "all") {
                $decoded = Hashids::connection('custom')->decode($checkedIdMaster);
                $checkedIdMaster = $decoded[0] ?? null;
            }

            $decodedIds = [];
            $flagMap = [];

            foreach ($request->analysis as $index => $encoded) {
                $id = Hashids::connection('custom')->decode($encoded)[0] ?? null;
                if ($id) {
                    $decodedIds[] = $id;
                    $flagMap[$id] = $request->Flag_Perhitungan[$index] ?? null;
                }
            }

            if (empty($decodedIds)) {
                return response()->json(['message' => 'Data analisa tidak valid'], 400);
            }

            $jenisAnalisaAll = DB::table('N_EMI_LAB_Jenis_Analisa')->whereIn('id', $decodedIds)->get()->keyBy('id');

            $analisaHeaders = [];
            foreach ($decodedIds as $id) {
                if (isset($jenisAnalisaAll[$id])) {
                    $analisaHeaders[] = [
                        'id' => $id,
                        'nama' => $jenisAnalisaAll[$id]->Jenis_Analisa,
                        'kode' => $jenisAnalisaAll[$id]->Kode_Analisa
                    ];
                }
            }

            $standarRentangRaw = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->whereIn('Id_Jenis_Analisa', $decodedIds)->where('Flag_Aktif', 'Y')->get();

            $standarRentangMap = [];
            foreach ($standarRentangRaw as $row) {
                $keyVal = (string)((float)$row->Nilai_Kriteria);
                $standarRentangMap[$row->Id_Jenis_Analisa][$keyVal] = $row->Keterangan_Kriteria;
            }

            $query = DB::table('N_EMI_LAB_Uji_Sampel as us')
                ->join('N_EMI_LAB_PO_Sampel as ps', 'us.No_Po_Sampel', '=', 'ps.No_Sampel')
                ->leftJoin('N_EMI_LAB_Perhitungan as p', 'p.id', '=', 'us.Id_Perhitungan')
                ->select(
                    'us.Id_Jenis_Analisa',
                    'ps.No_Po', 'ps.No_Split_Po', 'ps.Kode_Barang', 'us.Id_Mesin',
                    'us.Tanggal as Tanggal_Pengujian',
                    'us.Hasil',
                    DB::raw("COALESCE(p.Hasil_Perhitungan, 2) as Pembulatan")
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

            $refBarang = DB::table('N_EMI_View_Barang')->whereIn('Kode_Barang', $allKodeBarang)->pluck('Nama', 'Kode_Barang');
            $refMesin = DB::table('EMI_Master_Mesin')->whereIn('Id_Master_Mesin', $allIdMesin)->pluck('Nama_Mesin', 'Id_Master_Mesin');

            $groupedData = [];

            foreach ($rawData as $item) {
                $key = $item->No_Split_Po . '|' . $item->Tanggal_Pengujian . '|' . $item->Id_Mesin;

                if (!isset($groupedData[$key])) {
                    $namaBarang = $refBarang[$item->Kode_Barang] ?? 'N/A';
                    $namaMesin = $refMesin[$item->Id_Mesin] ?? 'N/A';
                    
                    $groupedData[$key] = [
                        'No' => 0,
                        'Nama_Sampel' => $namaBarang . '-' . $item->No_Split_Po . '-' . $namaMesin,
                        'Tanggal_Produksi' => $this->formatTanggalIndoLengkap($item->Tanggal_Pengujian),
                        'Raw_Analisa' => [] // Simpan raw untuk hitung rata-rata
                    ];
                }

                $idAnalisa = $item->Id_Jenis_Analisa;
                $isPerhitungan = ($flagMap[$idAnalisa] ?? null) === 'Y';
                $finalValue = $item->Hasil;

                if (!$isPerhitungan) {
                    $lookupKey = (string)((float)$item->Hasil);
                    if (isset($standarRentangMap[$idAnalisa][$lookupKey])) {
                        $finalValue = $standarRentangMap[$idAnalisa][$lookupKey];
                    }
                } else {
                    $finalValue = number_format((float)$item->Hasil, $item->Pembulatan, '.', '');
                }

                $groupedData[$key]['Raw_Analisa'][$idAnalisa] = $finalValue;
            }

            // --- 3. FORMAT DATA + HITUNG RATA-RATA GLOBAL ---
            $finalCollection = [];
            $no = 1;
            
            foreach ($groupedData as $row) {
                $row['No'] = $no++;
                $analisaCells = [];

                // Disini kita format cell, SEKALIGUS hitung akumulasi global
                foreach ($analisaHeaders as $idx => $header) {
                    $id = $header['id'];
                    $val = $row['Raw_Analisa'][$id] ?? null;

                    // Hitung Global Average (Akumulasi)
                    if ($val !== null && is_numeric($val)) {
                        $globalTotalNilai[$idx] += (float)$val;
                        $globalJumlahDataValid[$idx]++;
                    }

                    $displayValue = $val;
                    if ($val === null) {
                        $displayValue = 'Tidak Ada Data';
                    } elseif (is_numeric($val) && (float)$val == 0 && $header['kode'] === 'MBLG-STR') {
                        $displayValue = '-';
                    }

                    $analisaCells[] = [
                        'nama' => $header['nama'],
                        'kode' => $header['kode'],
                        'nilai' => $displayValue,
                        'is_foto' => false,    
                        'foto_base64' => null, 
                    ];
                }
                
                unset($row['Raw_Analisa']);
                $row['Analisa'] = $analisaCells;
                $finalCollection[] = $row;
            }

            // Hitung Nilai Akhir Rata-rata Global
            $globalRataRataValues = [];
            foreach ($analisaHeaders as $idx => $header) {
                if ($header['kode'] === 'MBLG-STR' || $globalJumlahDataValid[$idx] === 0) {
                    $globalRataRataValues[] = '-';
                } else {
                    $globalRataRataValues[] = number_format($globalTotalNilai[$idx] / $globalJumlahDataValid[$idx], 2, '.', '');
                }
            }

            // --- 4. CHUNKING LOGIC (Limit 1000) ---
            $limitPerFile = 1000; // Sesuai permintaan
            $chunks = array_chunk($finalCollection, $limitPerFile);
            $totalChunks = count($chunks);

            // Jika hanya 1 Chunk, langsung download (Rata-rata pasti muncul)
            if ($totalChunks == 1) {
                return $this->generatePdfFromChunk(
                    $chunks[0], 
                    $analisaHeaders, 
                    $globalRataRataValues, // Kirim Rata-rata
                    'Laporan_Hasil_Analisa_' . now()->format('Ymd_His') . '.pdf', 
                    false
                );
            }

            // Jika > 1 Chunk, buat ZIP
            $zipFileName = 'Laporan_Full_' . now()->format('Ymd_His') . '.zip';
            $zipFilePath = public_path('temp_pdf/' . $zipFileName);
            
            if (!File::exists(public_path('temp_pdf'))) {
                File::makeDirectory(public_path('temp_pdf'), 0755, true);
            }

            $zip = new ZipArchive;
            if ($zip->open($zipFilePath, ZipArchive::CREATE) === TRUE) {
                
                foreach ($chunks as $index => $chunk) {
                    $partNumber = $index + 1;
                    $pdfFileName = 'Laporan_Part_' . $partNumber . '.pdf';
                
                    
                    $rataRataUntukFileIni = ($index === $totalChunks - 1) ? $globalRataRataValues : [];

                    $savedPdfPath = $this->generatePdfFromChunk(
                        $chunk, 
                        $analisaHeaders, 
                        $rataRataUntukFileIni, // Variable dinamis
                        $pdfFileName, 
                        true
                    );
                    
                    if ($savedPdfPath && file_exists($savedPdfPath)) {
                        $zip->addFile($savedPdfPath, $pdfFileName);
                    }
                }
                $zip->close();
            }

            // Bersihkan temp
            $files = File::files(public_path('temp_pdf'));
            foreach ($files as $file) {
                if ($file->getExtension() == 'pdf') File::delete($file);
            }

            return response()->download($zipFilePath)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'status' => 500, 'message' => 'Terjadi kesalahan pada server.'], 500);
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
            $end   = $request->endDate;
            $checkedIdMaster = $request->Id_Master_Mesin;

            if ($checkedIdMaster && $checkedIdMaster !== "all") {
                $decoded = \Vinkla\Hashids\Facades\Hashids::connection('custom')->decode($checkedIdMaster);
                $checkedIdMaster = $decoded[0] ?? null;
            }

            $decodedMap = [];
            $decodedIds = [];
            $flagMap = [];

            foreach ($request->analysis as $index => $encoded) {
                $id = \Vinkla\Hashids\Facades\Hashids::connection('custom')->decode($encoded)[0] ?? null;
                if ($id) {
                    $decodedIds[] = $id;
                    $decodedMap[$id] = $encoded;
                    $flagMap[$id] = $request->Flag_Perhitungan[$index] ?? null;
                }
            }

            if (empty($decodedIds)) {
                return response()->json(['success' => false, 'status' => 404, 'message' => "Tidak ada analisa yang dipilih"], 404);
            }

            $jenisAnalisaAll = \Illuminate\Support\Facades\DB::table('N_EMI_LAB_Jenis_Analisa')
                ->whereIn('id', $decodedIds)
                ->get()
                ->keyBy('id');

            $analisaHeaders = [];
            foreach ($decodedIds as $id) {
                if (isset($jenisAnalisaAll[$id])) {
                    $analisaHeaders[] = [
                        'id'   => $id,
                        'nama' => $jenisAnalisaAll[$id]->Jenis_Analisa,
                        'kode' => $jenisAnalisaAll[$id]->Kode_Analisa
                    ];
                }
            }

            $standarRentangRaw = \Illuminate\Support\Facades\DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->whereIn('Id_Jenis_Analisa', $decodedIds)
                ->where('Flag_Aktif', 'Y')
                ->get();

            $standarRentangMap = [];
            foreach ($standarRentangRaw as $row) {
                $valKey = (string)((float)$row->Nilai_Kriteria); 
                $standarRentangMap[$row->Id_Jenis_Analisa][$valKey] = $row->Keterangan_Kriteria;
            }

            $query = \Illuminate\Support\Facades\DB::table('N_EMI_LAB_Uji_Sampel as us')
                ->join('N_EMI_LAB_PO_Sampel as ps', 'us.No_Po_Sampel', '=', 'ps.No_Sampel')
                ->leftJoin('N_EMI_LAB_Perhitungan as p', 'p.id', '=', 'us.Id_Perhitungan')
                ->select(
                    'us.Id_Jenis_Analisa',
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

            $rawData = $query->get();

            if ($rawData->isEmpty()) {
                return response()->json(['success' => false, 'status' => 404, 'message' => "Data tidak ditemukan"], 404);
            }

            $allKodeBarang = $rawData->pluck('Kode_Barang')->unique()->values();
            $allIdMesin    = $rawData->pluck('Id_Mesin')->unique()->values();
            $allNoPo       = $rawData->pluck('No_Po')->unique()->values();

            $refBarang = \Illuminate\Support\Facades\DB::table('N_EMI_View_Barang')
                ->whereIn('Kode_Barang', $allKodeBarang)
                ->pluck('Nama', 'Kode_Barang');

            $refMesin = \Illuminate\Support\Facades\DB::table('EMI_Master_Mesin')
                ->whereIn('Id_Master_Mesin', $allIdMesin)
                ->pluck('Nama_Mesin', 'Id_Master_Mesin');

            $refOrder = \Illuminate\Support\Facades\DB::table('N_EMI_View_Order_Produksi')
                ->whereIn('No_Faktur', $allNoPo)
                ->pluck('Tanggal', 'No_Faktur');

            $groupedData = [];

            foreach ($rawData as $item) {
                $key = $item->No_Split_Po . '|' . $item->Tanggal_Pengujian . '|' . $item->Id_Mesin;
                
                if (!isset($groupedData[$key])) {
                    $namaBarang = $refBarang[$item->Kode_Barang] ?? 'N/A';
                    $namaMesin  = $refMesin[$item->Id_Mesin] ?? 'N/A';
                    $tglProduksiRaw = $refOrder[$item->No_Po] ?? null;
                    
                    $groupedData[$key] = [
                        'No'               => 0, 
                        'Nama_Sampel'      => $namaBarang . '-' . $item->No_Split_Po . '-' . $namaMesin,
                        'Tanggal_Produksi' => $tglProduksiRaw ? $this->formatTanggalIndoLengkap($tglProduksiRaw) : 'Tidak Ada',
                        'Tanggal'          => $this->formatTanggalIndoLengkap($item->Tanggal_Pengujian),
                        'Raw_Analisa'      => []
                    ];
                }

                $idAnalisa = $item->Id_Jenis_Analisa;
                $flagHitungInput = $flagMap[$idAnalisa] ?? null;
                $isPerhitungan = ($flagHitungInput === 'Y');

                $finalValue = $item->Hasil;

                if (!$isPerhitungan) {
                    $lookupKey = (string)((float)$item->Hasil);
                    if (isset($standarRentangMap[$idAnalisa][$lookupKey])) {
                        $finalValue = $standarRentangMap[$idAnalisa][$lookupKey];
                    }
                } else {
                    $finalValue = number_format((float)$item->Hasil, $item->Pembulatan, '.', '');
                }

                $groupedData[$key]['Raw_Analisa'][$idAnalisa] = $finalValue;
            }

            $finalCollection = [];
            $no = 1;

            $jumlahKolomAnalisa = count($analisaHeaders);
            $totalNilai      = array_fill(0, $jumlahKolomAnalisa, 0);
            $jumlahDataValid = array_fill(0, $jumlahKolomAnalisa, 0);

            foreach ($groupedData as $row) {
                $row['No'] = $no++;
                $analisaCells = [];

                foreach ($analisaHeaders as $idx => $header) {
                    $id = $header['id'];
                    $val = $row['Raw_Analisa'][$id] ?? 'Tidak Ada Data';
                    $analisaCells[] = $val;

                    if (is_numeric($val)) {
                        $totalNilai[$idx] += (float)$val;
                        $jumlahDataValid[$idx]++;
                    }
                }
                
                unset($row['Raw_Analisa']); 
                $flatRow = array_merge(array_values($row), $analisaCells);
                $finalCollection[] = $flatRow;
            }

            $rataRata = [];
            for ($i = 0; $i < $jumlahKolomAnalisa; $i++) {
                if ($jumlahDataValid[$i] > 0) {
                    $rataRata[$i] = number_format($totalNilai[$i] / $jumlahDataValid[$i], 2, '.', '');
                } else {
                    $rataRata[$i] = '-';
                }
            }

            $formattedStartDate = \Carbon\Carbon::parse($start)->isoFormat('D MMMM YYYY');
            $formattedEndDate   = \Carbon\Carbon::parse($end)->isoFormat('D MMMM YYYY');

            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\RekapSampelLabProduksiExport(
                    collect($finalCollection),
                    $analisaHeaders,
                    $rataRata,
                    $formattedStartDate,
                    $formattedEndDate
                ),
                'Laporan_Hasil_Analisa_' . now()->format('Ymd_His') . '.xlsx'
            );
        }catch(\Exception $e){
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json([
                    'success' => true,
                    'status' => 500,
                    'message' => "Terjadi Kesalahan",
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

        $getTahapan = DB::table("N_EMI_LAB_Uji_Sampel")
                    ->where('No_Po_Sampel', $request->No_Po_Sampel)
                    ->where('No_Fak_Sub_Po', $request->No_Sampel_Resampling_Origin)
                    ->first();

        $tahapKe = $getTahapan->Tahapan_Ke ?? 1;

        // Carry PLT context jika analisa ini adalah PLT
        $idSessionPlt    = $getTahapan->Id_Session ?? null;
        $idPembandingPlt = $getTahapan->Id_Pembanding ?? null;

        // Fallback: jika parent tidak punya Id_Session, cari dari Palatabilitas_Session
        if (empty($idSessionPlt)) {
            $idSessionPlt = DB::table('N_EMI_LAB_Palatabilitas_Session')
                ->where('No_Po_Sampel', $request->No_Po_Sampel)
                ->where('Kode_Aktivitas_Lab', 'PLT')
                ->value('Id_Session');
        }

        // Fallback Id_Pembanding: cari dari row uji sampel sebelumnya jika NULL
        if (empty($idPembandingPlt) && !empty($idSessionPlt)) {
            $idPembandingPlt = DB::table('N_EMI_LAB_Uji_Sampel')
                ->where('No_Po_Sampel', $request->No_Po_Sampel)
                ->where('No_Fak_Sub_Po', $request->No_Sampel_Resampling_Origin)
                ->whereNotNull('Id_Pembanding')
                ->value('Id_Pembanding');
        }

        try {
            $payloadResampling = [
                'No_Po_Sampel'                => $request->No_Po_Sampel,
                'No_Sampel_Resampling_Origin' => $request->No_Sampel_Resampling_Origin,
                'No_Sampel_Resampling'        => $request->No_Sampel_Resampling,
                'Tahapan_Ke'                  => $tahapKe + 1,
                'Tanggal'                     => date('Y-m-d'),
                'Jam'                         => date('H:i:s'),
                'Id_Jenis_Analisa'            => $id_jenis_analisa,
                'Id_User'                     => $pengguna->UserId,
                'Keterangan'                  => 'Nomor Sampel ' . $request->No_Sampel_Resampling_Origin . ' melakukan reanalisa dengan sampel ' . $request->No_Sampel_Resampling,
                'Id_Session'                  => $idSessionPlt,
                'Id_Pembanding'               => $idPembandingPlt,
            ];


            DB::table('N_EMI_LAB_Uji_Sampel_Resampling_Log')->insert($payloadResampling);

            DB::table("N_EMI_LAB_Uji_Sampel")
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
            $data = DB::table('N_EMI_LAB_Uji_Sampel')
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

            $lastLogStage = DB::table('N_EMI_LAB_Uji_Sampel_Resampling_Log')
                ->where('No_Po_Sampel', $request->No_Po_Sampel)
                ->where('Id_Jenis_Analisa', $id_jenis_analisa)
                ->max('Tahapan_Ke');

            $currentMasterStage = $data->Tahapan_Ke;
            $maxExistingStage = $lastLogStage ? max($currentMasterStage, $lastLogStage) : $currentMasterStage;
            $nextStage = $maxExistingStage + 1;

            DB::table('N_EMI_LAB_Uji_Sampel')
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

            // Carry PLT context jika analisa ini adalah PLT
            $idSessionPlt    = $data->Id_Session ?? null;
            $idPembandingPlt = $data->Id_Pembanding ?? null;

            // Fallback: jika parent tidak punya Id_Session, cari dari Palatabilitas_Session
            if (empty($idSessionPlt)) {
                $idSessionPlt = DB::table('N_EMI_LAB_Palatabilitas_Session')
                    ->where('No_Po_Sampel', $request->No_Po_Sampel)
                    ->where('Kode_Aktivitas_Lab', 'PLT')
                    ->value('Id_Session');
            }

            // Fallback Id_Pembanding: cari dari row uji sampel sebelumnya jika NULL
            if (empty($idPembandingPlt) && !empty($idSessionPlt)) {
                $idPembandingPlt = DB::table('N_EMI_LAB_Uji_Sampel')
                    ->where('No_Po_Sampel', $request->No_Po_Sampel)
                    ->where('No_Fak_Sub_Po', $request->No_Fak_Sub_Po)
                    ->whereNotNull('Id_Pembanding')
                    ->value('Id_Pembanding');
            }

            DB::table('N_EMI_LAB_Uji_Sampel_Resampling_Log')->insert([
                'No_Po_Sampel'                => $request->No_Po_Sampel,
                'Tahapan_Ke'                  => $nextStage,
                'No_Sampel_Resampling_Origin' => $request->No_Sampel,
                'No_Sampel_Resampling'        => $request->No_Sampel,
                'Tanggal'                     => now()->toDateString(),
                'Jam'                         => now()->toTimeString(),
                'Id_User'                     => $user->UserId,
                'Id_Jenis_Analisa'            => $id_jenis_analisa,
                'Keterangan'                  => 'Reanalisa tanpa multi QR (sampel sama)',
                'Id_Session'                  => $idSessionPlt,
                'Id_Pembanding'               => $idPembandingPlt,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Reanalisa single berhasil'
            ]);

        } catch(\Exception $e){
            DB::rollBack();
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => "Terjadi Kesalahan. Hubungi Admin"
            ], 500);
        }
    }

    public function generateFotoToken(Request $request)
    {
        if (!auth()->check()) abort(401);

        $keys = $request->keys;

        $result = [];

        foreach ($keys as $key) {
            $token = Str::random(40);

            Cache::put(
                "lab_foto_token:$token",
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

        $payload = Cache::get("lab_foto_token:$token");

        if (!$payload) abort(401, "Token expired");

        Cache::forget("lab_foto_token:$token");

        if ($payload["key"] !== $key) abort(403);

        if ($payload["user"] !== auth()->id()) abort(403);


        $berkas = DB::table('N_EMI_LAB_Berkas_Uji_Lab')
            ->where('Berkas_Key', $key)
            ->first();

        if (!$berkas) abort(404);

        $ext = strtolower(pathinfo($berkas->File_Path, PATHINFO_EXTENSION));
        $mimeMap = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'heic' => 'image/heic',
            'heif' => 'image/heif',
        ];
        $mimeType = $mimeMap[$ext] ?? 'image/jpeg';

        $stream = Storage::disk('gcs')->readStream($berkas->File_Path);

        return response()->stream(function () use ($stream) {
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type'  => $mimeType,
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function storeBulkConfirmedUjiSampel(Request $request)
    {
        $request->validate([
            'analyses'                    => 'required|array|min:1',
            'analyses.*.No_Po_Sampel'     => 'required|string',
            'analyses.*.Id_Jenis_Analisa' => 'required|string',
            'analyses.*.Flag_Multi_QrCode'=> 'nullable|string',
            'analyses.*.No_Fak_Sub_Po'    => 'nullable|string',
        ]);

        $waktuServer     = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow");
        $dt              = $waktuServer[0]->DateTimeNow;
        $tanggalSqlServer = date('Y-m-d', strtotime($dt));
        $jamSqlServer     = date('H:i:s', strtotime($dt));

        $userId = Auth::user()->UserId;

        if (!DB::table('N_EMI_LAB_Users')->where('UserId', $userId)->exists()) {
            return response()->json(['success' => false, 'message' => "User tidak ditemukan."], 404);
        }

        $analyses = collect($request->analyses);

        // Decode hashed Id_Jenis_Analisa
        $decodedAnalyses = $analyses->map(function ($a) {
            $decoded = Hashids::connection('custom')->decode($a['Id_Jenis_Analisa']);
            if (empty($decoded)) {
                throw new \Exception("Id_Jenis_Analisa tidak valid: " . $a['Id_Jenis_Analisa']);
            }
            $a['_raw_id_jenis_analisa'] = $decoded[0];
            return $a;
        });

        // Batch lookups
        $noPoList = $decodedAnalyses->pluck('No_Po_Sampel')->unique()->values()->toArray();
        $rawJaIds = $decodedAnalyses->pluck('_raw_id_jenis_analisa')->unique()->values()->toArray();

        $poMap = DB::table('N_EMI_LAB_PO_Sampel')
            ->whereIn('No_Sampel', $noPoList)
            ->select('No_Sampel', 'Id_Mesin')
            ->get()->keyBy('No_Sampel');

        $mesinIds = $poMap->pluck('Id_Mesin')->unique()->filter()->values()->toArray();
        $mesinMap = DB::table('EMI_Master_Mesin')
            ->whereIn('Id_Master_Mesin', $mesinIds)
            ->select('Id_Master_Mesin', 'Flag_FG')
            ->get()->keyBy('Id_Master_Mesin');

        $jenisAnalisaMap = DB::table('N_EMI_LAB_Jenis_Analisa')
            ->whereIn('id', $rawJaIds)
            ->select('id', 'Flag_Perhitungan', 'Jenis_Analisa')
            ->get()->keyBy('id');

        $tahapanMap = DB::table('N_EMI_LAB_Uji_Sampel')
            ->whereIn('No_Po_Sampel', $noPoList)
            ->whereIn('Id_Jenis_Analisa', $rawJaIds)
            ->select('No_Po_Sampel', 'Id_Jenis_Analisa', DB::raw('MAX(Tahapan_Ke) as Tahapan_Ke'))
            ->groupBy('No_Po_Sampel', 'Id_Jenis_Analisa')
            ->get()->keyBy(fn($r) => $r->No_Po_Sampel . '|' . $r->Id_Jenis_Analisa);

        $results            = [];
        $finalDetailInserts = [];
        $logDetailByNoPo    = [];

        DB::beginTransaction();
        try {
            foreach ($decodedAnalyses as $analisis) {
                $noPo           = $analisis['No_Po_Sampel'];
                $rawJaId        = $analisis['_raw_id_jenis_analisa'];
                $isMultiQr      = ($analisis['Flag_Multi_QrCode'] ?? null) === 'Y';
                $noFakSubPo     = $analisis['No_Fak_Sub_Po'] ?? null;

                $po          = $poMap->get($noPo);
                $idMesin     = $po?->Id_Mesin;
                $mesin       = $idMesin ? $mesinMap->get($idMesin) : null;
                $jenisAnalisa = $jenisAnalisaMap->get($rawJaId);

                $isFG          = $mesin && $mesin->Flag_FG === 'Y';
                $isPerhitungan = $jenisAnalisa && $jenisAnalisa->Flag_Perhitungan === 'Y';

                $tahapanKey = $noPo . '|' . $rawJaId;
                $tahapanKe  = $tahapanMap->get($tahapanKey)?->Tahapan_Ke ?? 1;

                if ($isFG && $isPerhitungan) {
                    $adaTidakLayak = DB::table('N_EMI_LAB_Uji_Sampel')
                        ->where('No_Po_Sampel', $noPo)
                        ->where('No_Fak_Sub_Po', $noFakSubPo)
                        ->where('Id_Jenis_Analisa', $rawJaId)
                        ->where('Flag_Layak', 'T')
                        ->exists();
                    $statusKelayakan = $adaTidakLayak ? 'T' : 'Y';

                    DB::table('N_EMI_LAB_Uji_Sampel')
                        ->where('No_Po_Sampel', $noPo)
                        ->where('No_Fak_Sub_Po', $noFakSubPo)
                        ->where('Id_Jenis_Analisa', $rawJaId)
                        ->whereNull('Flag_Selesai')
                        ->update(['Status_Keputusan_Sampel' => 'terima', 'Flag_Selesai' => 'Y']);

                    $finalDetailInserts[] = [
                        'No_Sampel'       => $noPo,
                        'No_Sub_Sampel'   => $noFakSubPo ?? $noPo,
                        'Id_Jenis_Analisa'=> $rawJaId,
                        'Tahapan_Ke'      => $tahapanKe,
                        'Flag_Layak'      => $statusKelayakan,
                        'Tanggal'         => $tanggalSqlServer,
                        'Jam'             => $jamSqlServer,
                        'Id_User'         => $userId,
                    ];
                } elseif ($isFG && !$isPerhitungan) {
                    $q = DB::table('N_EMI_LAB_Uji_Sampel')
                        ->where('No_Po_Sampel', $noPo)
                        ->where('Id_Jenis_Analisa', $rawJaId)
                        ->whereNull('Flag_Selesai');
                    if ($isMultiQr && $noFakSubPo) {
                        $q->where('No_Fak_Sub_Po', $noFakSubPo);
                    }
                    $q->update(['Status_Keputusan_Sampel' => 'terima', 'Flag_Selesai' => 'Y', 'Flag_Layak' => 'Y', 'Flag_Final' => 'Y']);
                } else {
                    $q = DB::table('N_EMI_LAB_Uji_Sampel')
                        ->where('No_Po_Sampel', $noPo)
                        ->where('Id_Jenis_Analisa', $rawJaId)
                        ->whereNull('Flag_Selesai');
                    if ($isMultiQr && $noFakSubPo) {
                        $q->where('No_Fak_Sub_Po', $noFakSubPo);
                    }
                    $q->update(['Flag_Selesai' => 'Y', 'Status_Keputusan_Sampel' => 'terima', 'Flag_Layak' => 'Y', 'Flag_Final' => 'Y']);
                }

                // Kumpulkan detail analisa per sampel untuk log detail
                $logDetailByNoPo[$noPo][] = [
                    'Id_Jenis_Analisa'   => $rawJaId,
                    'Nama_Jenis_Analisa' => $jenisAnalisa?->Jenis_Analisa ?? null,
                    'Flag_Layak'         => ($isFG && $isPerhitungan) ? ($statusKelayakan ?? 'Y') : 'Y',
                    'Tanggal'            => $tanggalSqlServer,
                    'Jam'                => $jamSqlServer,
                    'Id_User'            => $userId,
                ];

                $results[] = ['No_Po_Sampel' => $noPo, 'success' => true];
            }

            if (!empty($finalDetailInserts)) {
                DB::table('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final')->insert($finalDetailInserts);
            }

            // Log validasi: satu header per unique sampel + detail per analisa
            $processedNoPosLog = collect($results)->pluck('No_Po_Sampel')->unique()->values()->toArray();
            if (!empty($processedNoPosLog)) {
                $poInfoForLog = DB::table('N_EMI_LAB_PO_Sampel')
                    ->whereIn('No_Sampel', $processedNoPosLog)
                    ->select('No_Sampel', 'No_Po', 'No_Split_Po', 'Kode_Barang', 'Flag_Trial_Produksi')
                    ->get()->keyBy('No_Sampel');

                foreach ($processedNoPosLog as $logNoPo) {
                    $poData = $poInfoForLog->get($logNoPo);
                    if ($poData) {
                        $jenisAksiLogBulk = $poData->Flag_Trial_Produksi === 'Y'
                            ? 'VALIDASI_TRIAL_PRODUKSI'
                            : 'VALIDASI_PRODUKSI';
                        $existingHeader = DB::table('N_EMI_LAB_Log_Aksi')
                            ->where('No_Sampel', $logNoPo)
                            ->where('Jenis_Aksi', $jenisAksiLogBulk)
                            ->where('Sub_Aksi', 'SETUJU')
                            ->first();
                        if ($existingHeader) {
                            $logId = $existingHeader->Id_Log_Aksi;
                        } else {
                            $logId = DB::table('N_EMI_LAB_Log_Aksi')->insertGetId([
                                'No_Sampel'  => $logNoPo,
                                'No_Po'      => $poData->No_Po       ?? '-',
                                'No_Split_Po'=> $poData->No_Split_Po ?? '-',
                                'Kode_Barang'=> $poData->Kode_Barang ?? null,
                                'Flag_Trial' => $poData->Flag_Trial_Produksi ?? null,
                                'Jenis_Aksi' => $jenisAksiLogBulk,
                                'Sub_Aksi'   => 'SETUJU',
                                'Id_User'    => $userId,
                                'Tanggal'    => $tanggalSqlServer,
                                'Jam'        => $jamSqlServer,
                            ]);
                        }

                        $detailRows = array_map(
                            fn($d) => array_merge($d, ['Id_Log_Aksi' => $logId]),
                            $logDetailByNoPo[$logNoPo] ?? []
                        );
                        if (!empty($detailRows)) {
                            DB::table('N_EMI_LAB_Log_Aksi_Detail')->insert($detailRows);
                        }
                    }
                }
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => count($results) . ' analisa berhasil divalidasi.',
                'data'    => $results,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getDaftarLabPaginasi(Request $request)
    {
        try {
            $userId  = Auth::user()->UserId;
            $days    = max(1, min(90, (int) $request->input('days', 7)));
            $page    = max(1, (int) $request->input('page', 1));
            $perPage = 20;
            $filter  = $request->input('filter', 'semua'); // semua|selesai|belum_selesai
            $search  = trim($request->input('search', ''));

            $dateFrom = now()->subDays($days - 1)->startOfDay()->toDateString();
            $dateTo   = now()->endOfDay()->toDateString();

            // Summary counts (single query — for filter tab badges)
            $summaryRow = DB::table('N_EMI_LAB_PO_Sampel')
                ->whereNull('Status')
                ->whereBetween(DB::raw('CAST(Tanggal AS DATE)'), [$dateFrom, $dateTo])
                ->when(!empty($search), function ($q) use ($search) {
                    $q->where(function ($inner) use ($search) {
                        $inner->where('No_Sampel', 'like', '%' . $search . '%')
                              ->orWhere('No_Po', 'like', '%' . $search . '%')
                              ->orWhere('Kode_Barang', 'like', '%' . $search . '%');
                    });
                })
                ->selectRaw("COUNT(*) as total_count, SUM(CASE WHEN Flag_Selesai = 'Y' THEN 1 ELSE 0 END) as selesai_count")
                ->first();

            $countAll     = (int) ($summaryRow->total_count ?? 0);
            $countSelesai = (int) ($summaryRow->selesai_count ?? 0);
            $summary = [
                'semua'         => $countAll,
                'selesai'       => $countSelesai,
                'belum_selesai' => $countAll - $countSelesai,
            ];

            if ($filter === 'selesai') {
                $total = $countSelesai;
            } elseif ($filter === 'belum_selesai') {
                $total = $countAll - $countSelesai;
            } else {
                $total = $countAll;
            }

            $lastPage = max(1, (int) ceil($total / $perPage));
            $page     = min($page, $lastPage);
            $offset   = ($page - 1) * $perPage;

            $samples = DB::table('N_EMI_LAB_PO_Sampel as po')
                ->leftJoin('EMI_Master_Mesin as m', 'po.Id_Mesin', '=', 'm.Id_Master_Mesin')
                ->whereNull('po.Status')
                ->whereBetween(DB::raw('CAST(po.Tanggal AS DATE)'), [$dateFrom, $dateTo])
                ->when($filter === 'selesai', fn($q) => $q->where('po.Flag_Selesai', 'Y'))
                ->when($filter === 'belum_selesai', fn($q) => $q->where(function ($inner) {
                    $inner->whereNull('po.Flag_Selesai')->orWhere('po.Flag_Selesai', '!=', 'Y');
                }))
                ->when(!empty($search), function ($q) use ($search) {
                    $q->where(function ($inner) use ($search) {
                        $inner->where('po.No_Sampel', 'like', '%' . $search . '%')
                              ->orWhere('po.No_Po', 'like', '%' . $search . '%')
                              ->orWhere('po.Kode_Barang', 'like', '%' . $search . '%');
                    });
                })
                ->select(
                    'po.id', 'po.No_Sampel', 'po.No_Po', 'po.No_Split_Po', 'po.No_Batch',
                    'po.Kode_Barang', 'po.Id_Mesin', 'po.Tanggal', 'po.Jam',
                    'po.Flag_Selesai', 'po.Flag_Trial_Produksi', 'po.Id_User',
                    'm.Nama_Mesin', 'm.Flag_Multi_Qrcode', 'm.Jumlah_Print_QRCode'
                )
                ->orderByDesc('po.Tanggal')
                ->orderByDesc('po.id')
                ->skip($offset)
                ->take($perPage)
                ->get();

            if ($samples->isEmpty()) {
                return response()->json([
                    'success'    => true,
                    'status'     => 200,
                    'message'    => 'Tidak ada sampel pada periode ini.',
                    'result'     => [],
                    'summary'    => $summary,
                    'pagination' => [
                        'total' => 0, 'per_page' => $perPage,
                        'current_page' => 1, 'total_pages' => 1,
                        'from' => 0, 'to' => 0,
                    ],
                ]);
            }

            $noSampelList   = $samples->pluck('No_Sampel')->toArray();
            $kodeBarangList = $samples->pluck('Kode_Barang')->unique()->filter()->toArray();
            $idMesinList    = $samples->pluck('Id_Mesin')->unique()->filter()->toArray();

            // Barang names
            $barangNameMap = collect([]);
            if (!empty($kodeBarangList)) {
                foreach (array_chunk($kodeBarangList, 1000) as $chunk) {
                    $barangNameMap = $barangNameMap->concat(
                        DB::table('N_EMI_View_Barang')
                            ->whereIn('Kode_Barang', $chunk)
                            ->select('Kode_Barang', DB::raw('MAX(Nama) as Nama'))
                            ->groupBy('Kode_Barang')
                            ->get()
                    );
                }
            }
            $barangNameMap = $barangNameMap->pluck('Nama', 'Kode_Barang');

            // Required analisa per barang-mesin-user (Kode_Role = LAB)
            $allAnalisaRaw = collect([]);
            if (!empty($kodeBarangList) && !empty($idMesinList)) {
                foreach (array_chunk($kodeBarangList, 1000) as $kodeChunk) {
                    $allAnalisaRaw = $allAnalisaRaw->concat(
                        DB::table('N_EMI_LAB_Barang_Analisa as ba')
                            ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ba.Id_Jenis_Analisa', '=', 'ja.id')
                            ->whereIn('ba.Kode_Barang', $kodeChunk)
                            ->whereIn('ba.Id_Master_Mesin', $idMesinList)
                            ->where('ba.Id_User', $userId)
                            ->where('ba.Flag_Aktif', 'Y')
                            ->where('ba.Kode_Role', 'LAB')
                            ->where('ja.Kode_Role', 'LAB')
                            ->select('ba.Kode_Barang', 'ba.Id_Master_Mesin', 'ja.id as analisa_id', 'ja.Kode_Analisa', 'ja.Jenis_Analisa', 'ja.Kode_Aktivitas_Lab')
                            ->get()
                    );
                }
            }
            $analisaByKey = $allAnalisaRaw->groupBy(fn($i) => $i->Kode_Barang . '|' . $i->Id_Master_Mesin);

            // Uji sampel status
            $allUjiRaw = collect([]);
            foreach (array_chunk($noSampelList, 2000) as $chunk) {
                $allUjiRaw = $allUjiRaw->concat(
                    DB::table('N_EMI_LAB_Uji_Sampel')
                        ->whereIn('No_Po_Sampel', $chunk)
                        ->select('No_Po_Sampel', 'Id_Jenis_Analisa', 'Flag_Selesai', 'Flag_Multi_QrCode', 'No_Fak_Sub_Po')
                        ->get()
                );
            }
            $ujiByNoSampel = $allUjiRaw->groupBy('No_Po_Sampel');

            // Multi-QR sub-sample list (natural order by numeric suffix)
            $multiQrData = DB::table('N_EMI_LAB_PO_Sampel_Multi_QrCode')
                ->whereIn('No_Po_Sampel', $noSampelList)
                ->select('No_Po_Sampel', 'No_Po_Multi')
                ->orderByRaw('LEN(No_Po_Multi) ASC, No_Po_Multi ASC')
                ->get()
                ->groupBy('No_Po_Sampel');

            // Active resampling
            $allResamplingRaw = collect([]);
            foreach (array_chunk($noSampelList, 2000) as $chunk) {
                $allResamplingRaw = $allResamplingRaw->concat(
                    DB::table('N_EMI_LAB_Uji_Sampel_Resampling_Log')
                        ->whereIn('No_Po_Sampel', $chunk)
                        ->whereNull('Flag_Selesai_Resampling')
                        ->select('No_Po_Sampel', 'Id_Jenis_Analisa', 'No_Sampel_Resampling_Origin')
                        ->get()
                );
            }
            $resamplingByNoSampel = $allResamplingRaw->groupBy('No_Po_Sampel');

            // PLT session context: cek apakah ada session palatabilitas per sample
            $pltSessionRaw = collect([]);
            foreach (array_chunk($noSampelList, 2000) as $chunk) {
                $pltSessionRaw = $pltSessionRaw->concat(
                    DB::table('N_EMI_LAB_Palatabilitas_Session as ps')
                        ->leftJoin('N_EMI_LAB_Palatabilitas_Pembanding as pp', function ($j) {
                            $j->on('pp.Id_Session', '=', 'ps.Id_Session')
                              ->where('pp.Flag_Aktif', '=', 'Y');
                        })
                        ->whereIn('ps.No_Po_Sampel', $chunk)
                        ->where('ps.Kode_Aktivitas_Lab', 'PLT')
                        ->select(
                            'ps.No_Po_Sampel',
                            'ps.Id_Session',
                            'ps.Status_Session',
                            DB::raw('COUNT(pp.Id_Pembanding) as jumlah_pembanding'),
                            DB::raw("STRING_AGG(pp.Nama_Pembanding, ', ') as nama_pembanding_list")
                        )
                        ->groupBy('ps.No_Po_Sampel', 'ps.Id_Session', 'ps.Status_Session')
                        ->get()
                );
            }
            $pltSessionByNoSampel = $pltSessionRaw->keyBy('No_Po_Sampel');

            // PLT analisa IDs: ambil mana saja jenis analisa yang Kode_Aktivitas_Lab = PLT
            $pltAnalisaIdSet = DB::table('N_EMI_LAB_Jenis_Analisa')
                ->where('Kode_Aktivitas_Lab', 'PLT')
                ->pluck('id')
                ->flip(); // flip → O(1) lookup

            // Expired detection: deadline = Tanggal+Jam + 3 days end-of-day (Sunday → +1 day)
            $now = Carbon::now();
            $sampleDeadlines = $samples->mapWithKeys(function ($po) {
                $waktu    = Carbon::parse($po->Tanggal . ' ' . $po->Jam);
                $hariKe4  = $waktu->copy()->addDays(3);
                $deadline = ($hariKe4->dayOfWeek === Carbon::SUNDAY)
                    ? $hariKe4->copy()->addDay()->endOfDay()
                    : $hariKe4->copy()->endOfDay();
                return [$po->No_Sampel => $deadline];
            });

            $candidateExpired = $sampleDeadlines
                ->filter(fn($dl) => $now->greaterThan($dl))
                ->keys()->toArray();

            $bukaUlangMap = collect([]);
            if (!empty($candidateExpired)) {
                $bukaUlangMap = DB::table('N_EMI_LAB_Pengajuan_Buka_Ulang_Uji_Sampel')
                    ->whereIn('No_Sampel', $candidateExpired)
                    ->select('No_Sampel', 'Waktu_Mulai', 'Waktu_Akhir')
                    ->get()
                    ->groupBy('No_Sampel');
            }

            $expiredSet = collect($candidateExpired)->filter(function ($noSampel) use ($bukaUlangMap, $now) {
                $pengajuans = $bukaUlangMap->get($noSampel, collect());
                if ($pengajuans->isEmpty()) return true;
                foreach ($pengajuans as $p) {
                    if ($now->between(Carbon::parse($p->Waktu_Mulai), Carbon::parse($p->Waktu_Akhir))) {
                        return false;
                    }
                }
                return true;
            })->flip();

            $result = $samples->map(function ($po) use ($analisaByKey, $ujiByNoSampel, $multiQrData, $resamplingByNoSampel, $barangNameMap, $sampleDeadlines, $expiredSet, $pltSessionByNoSampel, $pltAnalisaIdSet) {
                $key          = $po->Kode_Barang . '|' . $po->Id_Mesin;
                $analisaList  = $analisaByKey->get($key, collect());
                $ujiList      = $ujiByNoSampel->get($po->No_Sampel, collect());
                $ujiByAnalisa = $ujiList->groupBy('Id_Jenis_Analisa');
                $multiQrList  = $multiQrData->get($po->No_Sampel, collect());
                $multiQrCount = $multiQrList->count();

                $resamplingForSample  = $resamplingByNoSampel->get($po->No_Sampel, collect());
                $resamplingAnalisaIds = $resamplingForSample->pluck('Id_Jenis_Analisa')->unique()->toArray();
                $resamplingOrigins    = $resamplingForSample->pluck('No_Sampel_Resampling_Origin')->unique()->toArray();

                // Sub-samples done: has at least one selesai uji entry
                $doneSubSamples = $ujiList
                    ->where('Flag_Selesai', 'Y')
                    ->pluck('No_Fak_Sub_Po')
                    ->filter()
                    ->unique()
                    ->flip();

                $pltSession = $pltSessionByNoSampel->get($po->No_Sampel);

                $analisaWithStatus = $analisaList->map(function ($analisa) use ($ujiByAnalisa, $multiQrCount, $resamplingAnalisaIds, $pltAnalisaIdSet, $pltSession) {
                    $entries   = $ujiByAnalisa->get($analisa->analisa_id, collect());
                    $isPlt     = $pltAnalisaIdSet->has($analisa->analisa_id);
                    $isStarted = $entries->isNotEmpty();
                    $isDone    = false;

                    if ($isStarted) {
                        $isMultiQR = $entries->contains(fn($e) => $e->Flag_Multi_QrCode === 'Y');
                        if ($isMultiQR) {
                            $done   = $entries->where('Flag_Selesai', 'Y')->unique('No_Fak_Sub_Po')->count();
                            $isDone = $multiQrCount > 0 && $done >= $multiQrCount;
                        } else {
                            $isDone = $entries->every(fn($e) => $e->Flag_Selesai === 'Y');
                        }
                    }

                    // Untuk PLT: is_started = session ada + punya pembanding, walau belum masuk Uji_Sampel
                    if ($isPlt && !$isStarted && $pltSession && $pltSession->jumlah_pembanding > 0) {
                        $isStarted = true;
                    }

                    $kodeAktivitas = $analisa->Kode_Aktivitas_Lab ?? 'ANL';
                    $item = [
                        'id'                 => Hashids::connection('custom')->encode($analisa->analisa_id),
                        'Kode_Analisa'       => $analisa->Kode_Analisa,
                        'Jenis_Analisa'      => $analisa->Jenis_Analisa,
                        'Kode_Aktivitas_Lab' => $kodeAktivitas,
                        'is_expired_exempt'  => $kodeAktivitas !== 'ANL',
                        'is_started'         => $isStarted,
                        'is_done'            => $isDone,
                        'has_resampling'     => in_array($analisa->analisa_id, $resamplingAnalisaIds),
                        'is_plt'             => $isPlt,
                    ];

                    // Sertakan info PLT session jika ini analisa PLT
                    if ($isPlt && $pltSession) {
                        $item['plt_context'] = [
                            'id_session'       => Hashids::connection('custom')->encode($pltSession->Id_Session),
                            'status_session'   => $pltSession->Status_Session,
                            'jumlah_pembanding'=> (int) $pltSession->jumlah_pembanding,
                            'pembanding_list'  => $pltSession->nama_pembanding_list,
                            'session_final'    => $pltSession->Status_Session === 'F',
                        ];
                    } elseif ($isPlt) {
                        $item['plt_context'] = null;
                    }

                    return $item;
                });

                return [
                    'no_sampel'      => $po->No_Sampel,
                    'no_po'          => $po->No_Po,
                    'no_split_po'    => $po->No_Split_Po,
                    'no_batch'       => $po->No_Batch ?? '-',
                    'kode_barang'    => $po->Kode_Barang,
                    'nama_barang'    => $barangNameMap->get($po->Kode_Barang, $po->Kode_Barang),
                    'nama_mesin'     => $po->Nama_Mesin,
                    'Id_Mesin'       => $po->Id_Mesin,
                    'is_multi_print' => $po->Flag_Multi_Qrcode,
                    'jumlah_print'   => $po->Jumlah_Print_QRCode,
                    'tanggal'        => $po->Tanggal,
                    'jam'            => $po->Jam,
                    'is_trial_produksi' => $po->Flag_Trial_Produksi === 'Y',
                    'is_selesai'     => $po->Flag_Selesai === 'Y',
                    'is_expired'     => $expiredSet->has($po->No_Sampel),
                    'expired_at'     => $sampleDeadlines->has($po->No_Sampel)
                                            ? $sampleDeadlines->get($po->No_Sampel)->toDateTimeString()
                                            : null,
                    'registrar'      => $po->Id_User,
                    'multi_qr_list'  => $multiQrList->map(fn($qr) => [
                        'no_po_multi'          => $qr->No_Po_Multi,
                        'flag_selesai'         => $doneSubSamples->has($qr->No_Po_Multi) ? 'Y' : null,
                        'is_resampling_origin' => in_array($qr->No_Po_Multi, $resamplingOrigins),
                    ])->values(),
                    'analisa'        => $analisaWithStatus->values(),
                ];
            });

            $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
                $result->values()->all(), $total, $perPage, $page
            );
            $pg = $paginator->toArray();

            return response()->json([
                'success'    => true,
                'status'     => 200,
                'message'    => 'Data berhasil diambil.',
                'result'     => $pg['data'],
                'summary'    => $summary,
                'pagination' => [
                    'total'        => $pg['total'],
                    'per_page'     => $pg['per_page'],
                    'current_page' => $pg['current_page'],
                    'total_pages'  => $pg['last_page'],
                    'from'         => $pg['from'],
                    'to'           => $pg['to'],
                ],
            ]);

        } catch (\Exception $e) {
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'status' => 500, 'message' => 'Terjadi kesalahan pada server.'], 500);
        }
    }
}