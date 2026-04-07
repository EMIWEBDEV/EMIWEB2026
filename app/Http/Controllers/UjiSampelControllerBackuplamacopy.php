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
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator; 
use Illuminate\Support\Facades\File;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use ZipArchive;

class UjiSampelController extends Controller
{
    public function tester()
    {
        $kodeBarang     = '9999.9997'; 
        $idJenisAnalisa = 3;
        $idMesin        = 4;
        $rangeAwal      = 50;
        $rangeAkhir     = 60;

        // Ambil data yang sesuai join & filter
        $data = DB::table('N_EMI_LAB_Uji_Sampel AS uji')
            ->join('N_EMI_LAB_PO_Sampel AS po', 'uji.No_Po_Sampel', '=', 'po.No_Sampel')
            ->where([
                ['uji.Id_Jenis_Analisa', '=', $idJenisAnalisa],
                ['po.Id_Mesin', '=', $idMesin],
                ['po.Kode_Barang', '=', $kodeBarang],
            ])
            ->select('uji.No_Po_Sampel', 'uji.Hasil', 'uji.No_Fak_Sub_Po')
            ->get();

        foreach ($data as $row) {
            // Bandingkan Hasil dengan Range Awal
            $flagLayak = $row->Hasil > $rangeAwal ? 'Y' : null;

            // Update di tabel uji sampel
            DB::table('N_EMI_LAB_Uji_Sampel')
                ->where('No_Po_Sampel', $row->No_Po_Sampel)
                ->update([
                    'Flag_Layak'  => $flagLayak,
                    'Range_Awal'  => $rangeAwal,
                    'Range_Akhir' => $rangeAkhir,
                    'Id_Mesin' => $idMesin
                ]);

            // Insert ke tabel hasil validasi
            // DB::table('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final')->insert([
            //     'No_Sampel'        => $row->No_Po_Sampel,
            //     'No_Sub_Sampel'    => $row->No_Fak_Sub_Po,
            //     'Id_Jenis_Analisa' => $idJenisAnalisa,
            //     'Tahapan_Ke'       => 1,
            //     'Tanggal'          => date("Y-m-d"),
            //     'Jam'              => date('H:i:s'),
            //     'Flag_Layak'       => $flagLayak,
            //     'Id_User'          => 'RUDI'
            // ]);
        }

        return response()->json([
            'message' => 'Update berhasil',
            'total_diproses' => count($data)
        ]);
    }

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
            // Log error untuk kemudahan debugging di masa depan
            // \Log::error('Kesalahan kalkulasi rumus di server: ' . $e->getMessage(), ['formula' => $formula, 'trace' => $e->getTraceAsString()]);
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
    public function viewInformasiMultiQr($no_sub_sampel)
    {
        return inertia('vue/dashboard/lab/page-confirmedv2/confirmedv2pcs', [
            'No_Sub_Sampel' => $no_sub_sampel
        ]);
    }
    public function viewInformasiJenisAnalisaMultiQr($no_sampel, $no_fak_sub_sampel)
    {
        return inertia('vue/dashboard/lab/page-confirmedv2/confirmedv2pcs-jenis-analisa', [
            'No_Sampel' => $no_sampel,
            'No_Fak_Sub_Sampel' => $no_fak_sub_sampel,
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
        return inertia('vue/dashboard/lab/page-confirmedv2/verfikasiv2pcs', [
            'No_Sampel' => $no_sampel,
            'No_Fak_Sub_Sampel' => $no_fak_sub_sampel,
            'Id_Jenis_Analisa' => $id_jenis_analisa
        ]);
    }
    public function viewDataHasilAnalisaValidasiSingleQrCode($no_sampel, $id_jenis_analisa)
    {
        return inertia('vue/dashboard/lab/page-confirmedv2/verfikasiv2nopcs', [
            'No_Sampel' => $no_sampel,
            'Id_Jenis_Analisa' => $id_jenis_analisa
        ]);
    }
    public function viewHasilAnalisa()
    {
        return inertia('vue/dashboard/lab/hasil-analisis/HasilAnalisa');
    }
    public function viewSubHasilAnalisa($id_jenis_analisa)
    {
        return inertia('vue/dashboard/lab/hasil-analisis/SubHasilAnalisa', [
            'id_jenis_analisa' => $id_jenis_analisa
        ]);
    }
    public function viewNestedSubHasilAnalisa($id_jenis_analisa, $no_po_sampel, $flag_multi)
    {
        if($flag_multi === 'Y'){
            return inertia('vue/dashboard/lab/hasil-analisis/NestedSubHasilAnalisa', [
                'id_jenis_analisa' => $id_jenis_analisa,
                'no_po_sampel' => $no_po_sampel,
                'flag_multi' => $flag_multi,
            ]);
        }else {
            return inertia('vue/dashboard/lab/hasil-analisis/DetailHasilAnalisa', [
                'id_jenis_analisa' => $id_jenis_analisa,
                'no_po_sampel' => $no_po_sampel,
                'flag_multi' => $flag_multi,
            ]);
        }
    }
    public function viewDetaiHasilMulti($id_jenis_analisa, $no_po_sampel, $flag_multi, $no_sub)
    {
        if($flag_multi === 'Y'){
            return inertia('vue/dashboard/lab/hasil-analisis/DetailHasilAnalisaMulti', [
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
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan: " . $e->getMessage()
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

        // dd($request->all());

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
                // dd($existing);

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
                // dd("lol");

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
                                                dd($result);
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
                                                    'Jam' => $jamSqlSever,
                                                    'Id_User' => $username,
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
            Log::error($e);
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Terjadi Kesalahan Server: ' . $e->getMessage(),
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
        
        // dd($request->all());

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
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan Server: " . $e->getMessage(),
                'trace' => $e->getTraceAsString() 
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
                            "Id_Mesin" => $analysisData['id_mesin']
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
                        "Id_Mesin" => $analysisData['id_mesin']
                    ];
                }

                $lastNumber++;
                $newNumber = $prefix . '-' . str_pad($lastNumber, 4, '0', STR_PAD_LEFT);
                $formulas = DB::table('N_EMI_LAB_Perhitungan')->where('Id_Jenis_Analisa', $sumberData->Id_Jenis_Analisa)->get();
                $getKodeBarang = DB::table('N_EMI_LAB_PO_Sampel')->where('No_Sampel', $sumberData->No_Po_Sampel)->first();
               

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
                // dd($sumberData);

                foreach ($calculatedResults as $result) {
                    $RentangAwal = (float) $result['Range_Awal'];
                    $hasilFloat = $this->safeFloat($result['Hasil_Perhitungan']);
                    $Flag_Layak = null;

                    if ($hasilFloat < $RentangAwal) {
                        $Flag_Layak = 'T';
                    } else {
                        $Flag_Layak = 'Y';
                    }

                    $getDataMesin = DB::table('EMI_Master_Mesin')
                                    ->where('Id_Master_Mesin', $sumberData->Id_Mesin)
                                    ->where('Flag_FG', 'Y')
                                    ->first();

                    if($getDataMesin){
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
        // dd($request->all());
      
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
                            "Id_Mesin" => $analysisData['id_mesin'],
                            "Tahapan_Ke" => $analysisData['Tahapan_Ke'],
                            "Id_Resampling" => $analysisData['Id_Resampling']
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
                        "Id_Mesin" => $analysisData['id_mesin'],
                        "Tahapan_Ke" => $analysisData['Tahapan_Ke'],
                        "Id_Resampling" => $analysisData['Id_Resampling']
                    ];
                }


                $lastNumber++;
                $newNumber = $prefix . '-' . str_pad($lastNumber, 4, '0', STR_PAD_LEFT);
                $formulas = DB::table('N_EMI_LAB_Perhitungan')->where('Id_Jenis_Analisa', $sumberData->Id_Jenis_Analisa)->get();
                $parameterValues = collect($sumberData->parameters)->pluck('Value_Parameter', 'Id_Quality_Control');

                try {
                    $decoded = Hashids::connection('custom')->decode($sumberData->Id_Resampling);
                    if (empty($decoded)) {
                        throw new \Exception('Invalid ID');
                    }
                    $id_resampling = $decoded[0];
                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'status' => 400,
                        'message' => 'Format tidak valid.'
                    ], 400);
                }

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
                    $Flag_Layak = null;

    

                    if ($hasilFloat < $RentangAwal) {
                        $Flag_Layak = 'T';
                    } else {
                        $Flag_Layak = 'Y';
                    }

                    $getDataMesin = DB::table('EMI_Master_Mesin')
                                    ->where('Id_Master_Mesin', $sumberData->Id_Mesin)
                                    ->where('Flag_FG', 'Y')
                                    ->first();

                    if($getDataMesin){
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
                            'Tahapan_Ke' => $sumberData->Tahapan_Ke,
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
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan Server: " . $e->getMessage(),
                'trace' => $e->getTraceAsString() 
            ], 500);
        }
    }

    public function storeMultiQrCodeNotRumus(Request $request)
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
            // Ganti nama variabel untuk menyimpan ID
            $jenisAnalisaId = isset($idDecoded[0]) ? $idDecoded[0] : null;

            $payloadActivityUjiSampel = [
                'Kode_Perusahaan' => '001',
                'No_Po_Sampel' => $firstAnalysis['No_Po_Sampel'],
                'No_Fak_Sub_Po' => $firstAnalysis['No_Po_Multi_Sampel'],
                'Jenis_Aktivitas' => 'save_submit',
                'Keterangan' => $pengguna->Nama . ' Berhasil Mengirimkan Data Analisa',
                'Id_User' => $pengguna->UserId,
                'Tanggal' => $tanggalSqlServer,
                'Jam' => $jamSqlServer,
                'Id_Jenis_Analisa' => $jenisAnalisaId // Gunakan ID
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
                        // Gunakan variabel ID yang konsisten
                        $isAllowed = DB::table('N_EMI_LAB_Barang_Analisa')
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

                $analysisData['parameters'] = collect($analysisData['parameters'])->map(function ($param) {
                    $decoded = Hashids::connection('custom')->decode($param['Id_Quality_Control']);
                    $decodedId = isset($decoded[0]) ? (string) $decoded[0] : null;
                    $valueParameter = $param['Value_Parameter']; 

                    if ($valueParameter === '-') {
                        $valueParameter = -999999; 
                    } elseif ($valueParameter === '+') {
                        $valueParameter = -88888888; 
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
                            'Id_Jenis_Analisa' => $jenisAnalisaId, // Gunakan ID
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
                        'Id_Jenis_Analisa' => $jenisAnalisaId, // Gunakan ID
                        'No_Po_Multi_Sampel' => $analysisData['No_Po_Multi_Sampel'],
                        'is_multi_print' => $analysisData['is_multi_print'] ?? 'N',
                        'parameters' => $analysisData['parameters'],
                        'formulas' => $analysisData['formulas'] ?? [],
                        "Id_Mesin" => $analysisData['id_mesin']
                    ];
                }

                $lastNumber++;
                $newNumber = $prefix . '-' . str_pad($lastNumber, 4, '0', STR_PAD_LEFT);
                
                // Gunakan nama variabel baru untuk hasil query objek
                $jenisAnalisaRecord = DB::table('N_EMI_LAB_Jenis_Analisa')
                    ->select('Kode_Analisa')
                    ->where('id', $sumberData->Id_Jenis_Analisa)
                    ->first();

                $payloadUjiSampleData = [];
                $payloadActivityUjiSampelHasil = [];
                $payloadUjiSampleDetailData = [];
                $payloadActiviyUjiSampelDetail = [];
                foreach ($sumberData->parameters as $parameter) {
                    $paramValueFloat = $this->safeFloat($parameter['Value_Parameter']);

                    $flagString = null;
                    $nilaiHasilString = null;
                    
                    // Gunakan variabel baru untuk pengecekan
                    if ($jenisAnalisaRecord && $jenisAnalisaRecord->Kode_Analisa === 'MBLG-STR') {
                        if ($paramValueFloat == -999999) {
                            $flagString = 'Y';
                            $nilaiHasilString = '-';
                        } elseif ($paramValueFloat == -88888888) {
                            $flagString = 'Y';
                            $nilaiHasilString = '+';
                        }
                    }

                    $payloadUjiSampleData[] = [
                        "No_Faktur" => $newNumber,
                        "Kode_Perusahaan" => "001",
                        "Id_Jenis_Analisa" => $sumberData->Id_Jenis_Analisa,
                        "Hasil" => $paramValueFloat,
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
                        "Nilai_Hasil_String" => $nilaiHasilString
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
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan Server: " . $e->getMessage(),
                'trace' => $e->getTraceAsString() 
            ], 500);
        }
    }

    // public function storeMultiQrCodeNotRumus(Request $request)
    // {

    //     $request->validate([
    //         'analyses' => 'required|array|min:1',
    //         'analyses.*.No_Po_Sampel' => 'required|string',
    //         'analyses.*.Id_Jenis_Analisa' => 'required|string',
    //         'analyses.*.No_Sementara' => 'nullable|string',
    //         'analyses.*.is_multi_print' => 'required|string',
    //         'analyses.*.No_Po_Multi_Sampel' => 'nullable|string',
    //         'analyses.*.parameters' => 'required|array|min:1',
    //         'analyses.*.parameters.*.Id_Quality_Control' => 'required',
    //         'analyses.*.parameters.*.Value_Parameter' => 'required',
    //     ], [
    //         'analyses.required' => 'Tidak ada data analisis yang dikirim.',
    //         'analyses.*.parameters.required' => 'Parameter tidak boleh kosong untuk setiap baris.',
    //     ]);
      
    //     DB::beginTransaction();
        
    //     // dd($request->all());

    //     try {
    //         $results = [];
          
    //         $pengguna = Auth::user();
    //         $userId = $pengguna->UserId;

    //         $userExists = DB::table('N_EMI_LAB_Users')->where('UserId', $userId)->exists();

    //         if (!$userExists) {
    //             return response()->json([
    //                 'success' => false,
    //                 'status' => 404,
    //                 'message' => "User dengan ID $userId tidak ditemukan di sistem."
    //             ], 404);
    //         }
            
    //         $tanggal = date('Y-m-d');
    //         $jam = date('H:i:s');

    //         $currentMonth = date('m');
    //         $currentYear = date('y');
    //         $prefix = 'FUS' . $currentMonth . $currentYear;
    //         $prefixLength = strlen($prefix);

    //         $lastNumber = DB::table('N_EMI_LAB_Uji_Sampel')
    //             ->where('No_Faktur', 'like', $prefix . '-%')
    //             ->lockForUpdate() 
    //             ->selectRaw("MAX(CAST(SUBSTRING(No_Faktur, ? + 2, 10) AS INT)) as max_number", [$prefixLength])
    //             ->value('max_number') ?? 0;

    //         $firstAnalysis = $request->analyses[0];
    //         $idDecoded = Hashids::connection('custom')->decode($firstAnalysis['Id_Jenis_Analisa']);
    //         $jenisAnalisa = isset($idDecoded[0]) ? $idDecoded[0] : null;

    //         $payloadActivityUjiSampel = [
    //             'Kode_Perusahaan' => '001',
    //             'No_Po_Sampel' => $firstAnalysis['No_Po_Sampel'],
    //             'No_Fak_Sub_Po' => $firstAnalysis['No_Po_Multi_Sampel'],
    //             'Jenis_Aktivitas' => 'save_submit',
    //             'Keterangan' => $pengguna->Nama . ' Berhasil Mengirimkan Data Analisa',
    //             'Id_User' => $pengguna->UserId,
    //             'Tanggal' => $tanggal,
    //             'Jam' => $jam,
    //             'Id_Jenis_Analisa' => $jenisAnalisa
    //         ];

    //         $idLogActivity = DB::table('N_EMI_LAB_Activity_Uji_Sampel')->insertGetId($payloadActivityUjiSampel, 'Id_Log_Activity');

    //         foreach ($request->analyses as $analysisData) {
    //             $noSementara = $analysisData['No_Sementara'] ?? null;
    //             $sumberData = (object) $analysisData;
    //             $idUserUntukInsert = $pengguna->UserId;  
    //             $isFromSementara = false;

    //             $isFlagKhusus = DB::table('N_EMI_LAB_PO_Sampel')
    //                     ->where('No_Sampel', $sumberData->No_Po_Sampel)
    //                     ->where('Flag_Khusus', 'Y')
    //                     ->exists();

    //             if (!$isFlagKhusus) {
    //                     $isAllowed = DB::table('N_EMI_LAB_Barang_Analisa')
    //                         ->where('Id_Jenis_Analisa', $jenisAnalisa)
    //                         ->where('Id_User', $userId)
    //                         ->exists();

    //                     if (!$isAllowed) {
    //                         return response()->json([
    //                             'success' => false,
    //                             'status' => 403,
    //                             'message' => "Anda tidak memiliki akses untuk Jenis Analisa ini"
    //                         ], 403);
    //                     }
    //             }

    //             $analysisData['parameters'] = collect($analysisData['parameters'])->map(function ($param) {
    //                 $decoded = Hashids::connection('custom')->decode($param['Id_Quality_Control']);
    //                 $decodedId = isset($decoded[0]) ? (string) $decoded[0] : null;
    //                 $valueParameter = $param['Value_Parameter']; 

    //                 if ($valueParameter === '-') {
    //                     $valueParameter = -999999; 
    //                 } elseif ($valueParameter === '+') {
    //                     $valueParameter = -88888888; 
    //                 }

    //                 return [
    //                     'Id_Quality_Control' => $decodedId,
    //                     'Value_Parameter' => $valueParameter,
    //                     'No_Urut' => $param['No_Urut'] ?? null,
    //                     'RV_INT' => $param['RV_INT'] ?? null
    //                 ];
    //             })->toArray();

    //             if ($noSementara) {
    //                 $dataSementara = DB::table('N_EMI_LAB_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->first();

    //                 if ($dataSementara) {
    //                     foreach ($analysisData['parameters'] as $paramFromRequest) {
    //                         $idDecoded = Hashids::connection('custom')->decode($paramFromRequest['No_Urut']);
    //                         $idDecodedRv = Hashids::connection('custom')->decode($paramFromRequest['RV_INT']);
    //                         $idNu = isset($idDecoded[0]) ? $idDecoded[0] : null;
    //                         $idRv = isset($idDecodedRv[0]) ? $idDecodedRv[0] : null;

    //                         if (empty($idNu) || empty($idRv)) {
    //                             return response()->json([
    //                                 'success' => false,
    //                                 'status' => 400,
    //                                 'message' => 'Data tidak lengkap untuk divalidasi. No_Urut atau RV_INT kosong pada data sementara.'
    //                             ], 400);
    //                         }

    //                         $dbParam = DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')
    //                             ->selectRaw('CAST(RV AS INT) AS RV_INT')
    //                             ->where('No_Sementara', $noSementara)
    //                             ->where('No_Urut', $idNu)
    //                             ->first();

    //                         if (!$dbParam) {
    //                             return response()->json([
    //                                 'success' => false,
    //                                 'status' => 404,
    //                                 'message' => 'Data parameter dengan No_Urut: ' . $paramFromRequest['No_Urut'] . ' tidak ditemukan.'
    //                             ], 404);
    //                         }

    //                         if ((int)$idRv !== (int)$dbParam->RV_INT) {
    //                             return response()->json([
    //                                 'success' => false,
    //                                 'status' => 409,
    //                                 'message' => 'Data sudah kedaluwarsa. Silakan refresh halaman.'
    //                             ], 409);
    //                         }
    //                     }

    //                     $isFromSementara = true;
    //                     $idUserUntukInsert = $dataSementara->Id_User;

    //                     $detailsSementara = DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')
    //                                         ->where('No_Sementara', $noSementara)
    //                                         ->get();

    //                     $requestParams = collect($analysisData['parameters'])->keyBy('Id_Quality_Control');

    //                     $mergedParameters = $detailsSementara->map(function ($dbParam) use ($requestParams) {
    //                         $qcId = (string) $dbParam->Id_Quality_Control;
    //                         $finalValue = $dbParam->Value_Parameter;

    //                         if ($requestParams->has($qcId) && is_null($dbParam->Value_Parameter)) {
    //                             $finalValue = $requestParams[$qcId]['Value_Parameter'];
    //                         }

    //                         return [
    //                             'Id_Quality_Control' => $qcId,
    //                             'Value_Parameter' => $finalValue
    //                         ];
    //                     })->toArray();

    //                     $sumberData = (object) [
    //                         'No_Po_Sampel' => $dataSementara->No_Po_Sampel,
    //                         'Id_Jenis_Analisa' => $jenisAnalisa,
    //                         'No_Po_Multi_Sampel' => $dataSementara->No_Fak_Sub_Po,
    //                         'is_multi_print' => $dataSementara->Flag_Multi_QrCode,
    //                         'parameters' => $mergedParameters,
    //                         'formulas' => $analysisData['formulas'] ?? [],
    //                         "Id_Mesin" => $analysisData['id_mesin']
    //                     ];
    //                 }
    //             } else {
    //                 // Pastikan jika bukan dari sementara, kita tetap pakai decoded ID
    //                 $sumberData = (object) [
    //                     'No_Po_Sampel' => $analysisData['No_Po_Sampel'],
    //                     'Id_Jenis_Analisa' => $jenisAnalisa,
    //                     'No_Po_Multi_Sampel' => $analysisData['No_Po_Multi_Sampel'],
    //                     'is_multi_print' => $analysisData['is_multi_print'] ?? 'N',
    //                     'parameters' => $analysisData['parameters'],
    //                     'formulas' => $analysisData['formulas'] ?? [],
    //                     "Id_Mesin" => $analysisData['id_mesin']
    //                 ];
    //             }

    //             $lastNumber++;
    //             $newNumber = $prefix . '-' . str_pad($lastNumber, 4, '0', STR_PAD_LEFT);
    //             $jenisAnalisa = DB::table('N_EMI_LAB_Jenis_Analisa')
    //             ->select('Kode_Analisa')
    //             ->where('id', $sumberData->Id_Jenis_Analisa)
    //             ->first();


    //             // Build Insert Payload
    //             $payloadUjiSampleData = [];
    //             $payloadActivityUjiSampelHasil = [];
    //             $payloadUjiSampleDetailData = [];
    //             $payloadActiviyUjiSampelDetail = [];
    //             foreach ($sumberData->parameters as $parameter) {
    //                 $paramValueFloat = $this->safeFloat($parameter['Value_Parameter']);

    //                 $flagString = null;
    //                 $nilaiHasilString = null;

    //                 if ($jenisAnalisa && $jenisAnalisa->Kode_Analisa === 'MBLG-STR') {
    //                     if ($paramValueFloat == -999999) {
    //                         $flagString = 'Y';
    //                         $nilaiHasilString = '-';
    //                     } elseif ($paramValueFloat == -88888888) {
    //                         $flagString = 'Y';
    //                         $nilaiHasilString = '+';
    //                     }
    //                 }


    //                 $payloadUjiSampleData[] = [
    //                     "No_Faktur" => $newNumber,
    //                     "Kode_Perusahaan" => "001",
    //                     "Id_Jenis_Analisa" => $sumberData->Id_Jenis_Analisa,
    //                     "Hasil" => $paramValueFloat,
    //                     "Flag_Perhitungan" => null,
    //                     "Flag_Multi_QrCode" => $sumberData->is_multi_print,
    //                     "No_Fak_Sub_Po" => $sumberData->No_Po_Multi_Sampel,
    //                     "Status" => null,
    //                     "Tanggal" => $tanggal,
    //                     "Jam" => $jam,
    //                     "Id_User" => $idUserUntukInsert,
    //                     "No_Po_Sampel" => $sumberData->No_Po_Sampel,
    //                     'Status_Keputusan_Sampel' => 'menunggu',
    //                     'Tahapan_Ke' => 1,
    //                     'Id_Mesin' => $sumberData->Id_Mesin,
    //                     "Flag_String" => $flagString,
    //                     "Nilai_Hasil_String" => $nilaiHasilString
    //                 ];

    //                 $payloadActivityUjiSampelHasil[] = [
    //                     "Kode_Perusahaan" => "001",
    //                     'Id_Log_Activity_Sampel' => $idLogActivity,
    //                     "No_Po_Sampel" => $sumberData->No_Po_Sampel,
    //                     "No_Fak_Sub_Po" => $sumberData->No_Po_Multi_Sampel,
    //                     "Id_Jenis_Analisa" => $sumberData->Id_Jenis_Analisa,
    //                     "Value_Baru" => $paramValueFloat,
    //                     "Value_Lama" => $paramValueFloat,
    //                     "Tanggal" => $tanggal,
    //                     "Jam" => $jam,
    //                     "Id_User" => $idUserUntukInsert,
    //                     "Status_Submit" => "Submited",
    //                 ];


    //                 $payloadUjiSampleDetailData[] = [
    //                     "Kode_Perusahaan" => "001",
    //                     "No_Faktur_Uji_Sample" => $newNumber,
    //                     "Id_Quality_Control" => $parameter['Id_Quality_Control'],
    //                     "Value_Parameter" => $paramValueFloat,
    //                     "Tanggal" => $tanggal,
    //                     "Jam" => $jam,
    //                     "Id_User" => $idUserUntukInsert,
    //                 ];

    //                 $payloadActiviyUjiSampelDetail[] = [
    //                     "Kode_Perusahaan" => "001",
    //                     'Id_Log_Activity_Sampel' => $idLogActivity,
    //                     "No_Po_Sampel" => $sumberData->No_Po_Sampel,
    //                     "No_Fak_Sub_Po" => $sumberData->No_Po_Multi_Sampel,
    //                     "Id_Jenis_Analisa" => $sumberData->Id_Jenis_Analisa,
    //                     "Id_Quality_Control" => $parameter['Id_Quality_Control'],
    //                     "Value_Baru" => $paramValueFloat,
    //                     "Value_Lama" => $paramValueFloat,
    //                     "Tanggal" => $tanggal,
    //                     "Jam" => $jam,
    //                     "Id_User" => $idUserUntukInsert,
    //                     "Status_Submit" => "Submited",
    //                 ];
    //             }

    //             // dd($payloadUjiSampleData);
    //             DB::table('N_EMI_LAB_Uji_Sampel')->insert($payloadUjiSampleData);
    //             DB::table('N_EMI_LAB_Uji_Sampel_Detail')->insert($payloadUjiSampleDetailData);
    //             DB::table('N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail')->insert($payloadActivityUjiSampelHasil);
    //             DB::table('N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail')->insert($payloadActiviyUjiSampelDetail);

    //             if ($isFromSementara) {
    //                 DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')->where('No_Sementara', $noSementara)->delete();
    //                 DB::table('N_EMI_LAB_Uji_Sampel_Sementara')->where('No_Sementara', $noSementara)->delete();
    //             }

    //             $results[] = [
    //                 'generated_no_faktur' => $newNumber,
    //                 'status' => $isFromSementara ? 'temporary_table' : 'request',
    //             ];
    //         }

    //         DB::commit();

    //         return response()->json([
    //             'success' => true,
    //             'status' => 201,
    //             'message' => "Data berhasil diproses dan disimpan.",
    //             'results' => $results 
    //         ], 201);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'success' => false,
    //             'status' => 500,
    //             'message' => "Terjadi Kesalahan Server: " . $e->getMessage(),
    //             'trace' => $e->getTraceAsString() 
    //         ], 500);
    //     }
    // }

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
        // dd($request->all());

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

                // Standarisasi ID_Quality_Control (decoded)
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
                    // Pastikan jika bukan dari sementara, kita tetap pakai decoded ID
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
                        "Flag_Perhitungan" => 'Y',
                        "Flag_Multi_QrCode" => null,
                        "Status" => null,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        'Tahapan_Ke' => 1,
                        'Status_Keputusan_Sampel' => 'menunggu',
                        "Id_User" => $idUserUntukInsert,
                        'Flag_Layak' => 'Y',
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
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan Server: " . $e->getMessage(),
                'trace' => $e->getTraceAsString() 
            ], 500);
        }
    }
    public function storeNoRumusNotMultiQrCode(Request $request)
    {
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

        // dd($request->all());

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
                    $decoded = !empty($param['Id_Quality_Control']) ? Hashids::connection('custom')->decode($param['Id_Quality_Control']) : []; 
                    $decodedId = isset($decoded[0]) ? (string) $decoded[0] : null;
                    $valueParameter = $param['Value_Parameter']; 

                    if ($valueParameter === '-') {
                        $valueParameter = -999999; 
                    } elseif ($valueParameter === '+') {
                        $valueParameter = -88888888; 
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
                            $idDecoded = !empty($paramFromRequest['No_Urut']) ? Hashids::connection('custom')->decode($paramFromRequest['No_Urut']) : [];
                            $idDecodedRv = !empty($paramFromRequest['No_Urut']) ? Hashids::connection('custom')->decode($paramFromRequest['RV_INT']) : [];
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
                // dd($request->all());
                $jenisAnalisa = DB::table('N_EMI_LAB_Jenis_Analisa')
                ->select('Kode_Analisa')
                ->where('id', $sumberData->Id_Jenis_Analisa)
                ->first();
                // dd($jenisAnalisa);

                $calculatedResults = [];
                foreach ($sumberData->formulas as $formula) {
                    $hasilPerhitungan = $formula['Value_Parameter'];

                    if ($hasilPerhitungan === '-') {
                        $hasilPerhitungan = -999999; 
                    } elseif ($hasilPerhitungan === '+') {
                        $hasilPerhitungan = -88888888; 
                    }
                    $calculatedResults[] = [
                        'Id_Jenis_Analisa' => $sumberData->Id_Jenis_Analisa,
                        'Hasil_Perhitungan' => $hasilPerhitungan
                    ];
                }

                $payloadUjiSampleData = [];
                $payloadActivityUjiSampelHasil = [];
                foreach ($calculatedResults as $result) {
                    $hasilFloat = $this->safeFloat($result['Hasil_Perhitungan']);

                    $flagString = null;
                    $nilaiHasilString = null;

                    if ($jenisAnalisa && $jenisAnalisa->Kode_Analisa === 'MBLG-STR') {
                        if ($hasilFloat == -999999) {
                            $flagString = 'Y';
                            $nilaiHasilString = '-';
                        } elseif ($hasilFloat == -88888888) {
                            $flagString = 'Y';
                            $nilaiHasilString = '+';
                        }
                    }

                    $payloadUjiSampleData[] = [
                        "No_Faktur" => $newNumber,
                        "Kode_Perusahaan" => "001",
                        "Id_Jenis_Analisa" => $result['Id_Jenis_Analisa'],
                        "Hasil" => $hasilFloat,
                        "Flag_Perhitungan" => null,
                        "Flag_Multi_QrCode" => null,
                        "Status" => null,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $idUserUntukInsert,
                        "No_Po_Sampel" => $sumberData->No_Po_Sampel,
                        "Id_Mesin" => $sumberData->Id_Mesin,
                        'Status_Keputusan_Sampel' => 'menunggu',
                        'Flag_Layak' => 'Y',
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
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan Server: " . $e->getMessage(),
                'trace' => $e->getTraceAsString() 
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
            Log::error($e);
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Terjadi Kesalahan Server: ' . $e->getMessage(),
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
                // dd($analysis);
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
            Log::error($e);
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Terjadi Kesalahan Server: ' . $e->getMessage(),
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
            Log::error($e);
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Terjadi Kesalahan Server: ' . $e->getMessage(),
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
                                    $hasilPerhitungan = $formula['Value_Parameter'];
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

                $calculatedResults = [];
                foreach ($analysis['formulas'] as $formula) {
                    $hasilPerhitungan = $formula['Value_Parameter'];
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
                        'Flag_Multi_QrCode' => null,
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
            Log::error($e);
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Terjadi Kesalahan Server: ' . $e->getMessage(),
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
                // Hapus referensi setelah loop selesai (praktik yang baik)
                unset($param);

                $analysis = (object) $analysisData;
                $idDecodedNu = Hashids::connection('custom')->decode($analysis->No_Urut);
                $idNu = isset($idDecodedNu[0]) ? $idDecodedNu[0] : null;
                $rvDecode = Hashids::connection('custom')->decode($analysis->RV_INT);
                $rvNew = isset($rvDecode[0]) ? $rvDecode[0] : null;

                // dd($analysis);

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
                    // dd($parameter);
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
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan Server: " . $e->getMessage(),
                'trace' => $e->getTraceAsString()
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

            $noPoSampel = $request->analyses[0]['No_Po_Sampel'];
            $idDecoded = Hashids::connection('custom')->decode($request->analyses[0]['Id_Jenis_Analisa']);
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
                $idNu = $idDecodedNu[0] ?? null;
                $rvDecode = Hashids::connection('custom')->decode($analysis->RV_INT);
                $rvNew = $rvDecode[0] ?? null;

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
                'message' => "Data dari semua baris berhasil disimpan.",
                'result' => $results
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan Server: " . $e->getMessage(),
                'trace' => $e->getTraceAsString()
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

            $analyses = collect($request->analyses);
            $first = $analyses->first();
            $noPoSampel = $first['No_Po_Sampel'];
            $jenisAnalisa = Hashids::connection('custom')->decode($first['Id_Jenis_Analisa'])[0] ?? null;

            $idLogActivity = DB::table('N_EMI_LAB_Activity_Uji_Sampel')->insertGetId([
                'Kode_Perusahaan' => '001',
                'No_Po_Sampel' => $noPoSampel,
                'Jenis_Aktivitas' => 'save_update',
                'Keterangan' => "$pengguna->Nama Berhasil Mengupdate Hasil Analisa",
                'Id_User' => $pengguna->UserId,
                'Tanggal' => $tanggalSqlServer,
                'Jam' => $jamSqlServer,
                'Id_Jenis_Analisa' => $jenisAnalisa
            ], 'Id_Log_Activity');

            // Decode & extract IDs
            $decoded = $analyses->map(function ($a) {
                return [
                    'raw' => (object)$a,
                    'No_Sementara' => $a['No_Sementara'],
                    'No_Urut' => Hashids::connection('custom')->decode($a['No_Urut'])[0] ?? null,
                    'RV_INT' => Hashids::connection('custom')->decode($a['RV_INT'])[0] ?? null,
                ];
            });

            $noSementaras = $decoded->pluck('No_Sementara')->unique()->values();
            $noUruts = $decoded->pluck('No_Urut')->unique()->values();

            // Preload data to avoid N+1
            $rvMap = DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')
                ->selectRaw('No_Sementara, No_Urut, CAST(RV AS INT) AS RV_INT')
                ->whereIn('No_Sementara', $noSementaras)
                ->whereIn('No_Urut', $noUruts)
                ->get()
                ->keyBy(fn($row) => $row->No_Sementara . '|' . $row->No_Urut);

            $hasilMap = DB::table('N_EMI_LAB_Uji_Sampel_Sementara')
                ->whereIn('No_Sementara', $noSementaras)
                ->pluck('Hasil', 'No_Sementara');

            $results = [];
            $updateParams = [];
            $paramLogs = [];
            $hasilUpdates = [];
            $hasilLogs = [];

            foreach ($decoded as $item) {
                $analysis = $item['raw'];
                $noSementara = $item['No_Sementara'];
                $idNu = $item['No_Urut'];
                $rvNew = $item['RV_INT'];
                $key = $noSementara . '|' . $idNu;
                $rvDb = $rvMap[$key] ?? null;

                if (!$rvDb || (int)$rvNew !== (int)$rvDb->RV_INT) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'status' => 400,
                        'message' => "Data tidak valid atau tidak ditemukan untuk No_Sementara: $noSementara.",
                    ], 400);
                }

                $parameterValues = [];
                $parameters = collect($analysis->parameters)->map(function ($p) {
                    $idQc = Hashids::connection('custom')->decode($p['Id_Quality_Control'])[0] ?? null;
                    return [
                        'idQc' => $idQc,
                        'value' => $p['Value_Parameter'],
                        'raw' => $p
                    ];
                })->filter(fn($p) => $p['idQc']);

                $paramIds = $parameters->pluck('idQc');
                $oldParams = DB::table('N_EMI_LAB_Uji_Sampel_Detail_Sementara')
                    ->where('No_Urut', $idNu)
                    ->whereIn('Id_Quality_Control', $paramIds)
                    ->pluck('Value_Parameter', 'Id_Quality_Control');

                foreach ($parameters as $p) {
                    $idQc = $p['idQc'];
                    $newValue = $this->safeFloat($p['value']);
                    $oldValue = $this->safeFloat($oldParams[$idQc] ?? null);
                    $parameterValues[$idQc] = $p['value'];

                    $paramLogs[] = [
                        "Kode_Perusahaan" => "001",
                        'Id_Log_Activity_Sampel' => $idLogActivity,
                        "No_Po_Sampel" => $analysis->No_Po_Sampel,
                        "Id_Jenis_Analisa" => $jenisAnalisa,
                        "Id_Quality_Control" => $idQc,
                        "Value_Baru" => $newValue,
                        "Value_Lama" => $oldValue,
                        "Tanggal" => $tanggalSqlServer,
                        "Jam" => $jamSqlServer,
                        "Id_User" => $pengguna->UserId,
                        "Status_Submit" => "Drafted",
                        'Alasan_Mengubah_Data' => $analysis->reason,
                    ];

                    $updateParams[] = [
                        'No_Urut' => $idNu,
                        'Id_Quality_Control' => $idQc,
                        'Value_Parameter' => $newValue
                    ];
                }

                $valueBaru = $this->safeFloat($analysis->formulas[0]['Value_Parameter'] ?? null);
                $valueLama = $this->safeFloat($hasilMap[$noSementara] ?? null);

                $hasilLogs[] = [
                    "Kode_Perusahaan" => "001",
                    'Id_Log_Activity_Sampel' => $idLogActivity,
                    "No_Po_Sampel" => $analysis->No_Po_Sampel,
                    "Id_Jenis_Analisa" => $jenisAnalisa,
                    "Value_Baru" => $valueBaru,
                    "Value_Lama" => $valueLama,
                    "Tanggal" => $tanggalSqlServer,
                    "Jam" => $jamSqlServer,
                    "Id_User" => $pengguna->UserId,
                    "Status_Submit" => "Drafted",
                ];

                $hasilUpdates[] = [
                    'No_Sementara' => $noSementara,
                    'Hasil' => $valueBaru,
                ];

                $results[] = [
                    'No_Sementara' => $noSementara,
                    'updated_parameters' => $parameterValues
                ];
            }

            // INSERT logs
            if (!empty($paramLogs)) {
                DB::table('N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail')->insert($paramLogs);
            }

            if (!empty($hasilLogs)) {
                DB::table('N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail')->insert($hasilLogs);
            }

            // UPDATE parameter
            foreach ($updateParams as $row) {
                DB::table("N_EMI_LAB_Uji_Sampel_Detail_Sementara")
                    ->where('No_Urut', $row['No_Urut'])
                    ->where('Id_Quality_Control', $row['Id_Quality_Control'])
                    ->update(['Value_Parameter' => $row['Value_Parameter']]);
            }

            // UPDATE hasil
            foreach ($hasilUpdates as $row) {
                DB::table("N_EMI_LAB_Uji_Sampel_Sementara")
                    ->where('No_Sementara', $row['No_Sementara'])
                    ->update(['Hasil' => $row['Hasil']]);
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
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan Server: " . $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan Server: " . $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
                    Log::error($e);
                    return response()->json([
                        'success' => false,
                        'message' => 'Terjadi kesalahan pada server saat memproses data.',
                        'error' => $e->getMessage(), // Opsional: hanya tampilkan saat mode debug
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
    
        foreach ($request->analyses as $analisis){
            $analysis = (object) $analisis;

            $checkFinishGood = DB::table('EMI_Master_Mesin')
                            ->where('Id_Master_Mesin', $analysis->Id_Mesin)
                            ->first();

            $checkedPerhitungan = DB::table('N_EMI_LAB_Jenis_Analisa')->where('id', $analysis->Id_Jenis_Analisa)->first();

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
                            'error' => $e->getMessage(), // Opsional: hanya tampilkan saat mode debug
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

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Terjadi kesalahan server',
                'error' => $e->getMessage() 
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
                        break; // cukup satu yang valid
                    }
                }

                if (!$isValid) {
                    $isLocked = true;
                }
            } else {
                // tidak ada pengajuan sama sekali
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
        ->select('r.*', 'ja.Jenis_Analisa')
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


    // versi awal sekali
    // public function getDetailSampelUji($no_sampel)
    // {
    //     $parts = explode('-', $no_sampel);
    //     $base_no_sampel = count($parts) > 2 ? implode('-', array_slice($parts, 0, -1)) : $no_sampel;

    //     $sampelRow = DB::table('N_EMI_LAB_PO_Sampel as q')
    //         ->leftJoin('EMI_Master_Mesin as m', 'q.Id_Mesin', '=', 'm.Id_Master_Mesin')
    //         ->leftJoin('N_EMI_View_Barang as b', 'q.Kode_Barang', '=', 'b.Kode_Barang')
    //         ->where('q.No_Sampel', '=', $base_no_sampel)
    //         ->select(
    //             'q.id as sampel_id', 'q.Berat_Sampel', 'q.Kode_Perusahaan', 'q.No_Sampel', 'q.No_Po', 'q.Kode_Barang',
    //             'q.Tanggal', 'q.Jam', 'q.No_Split_Po', 'q.No_Batch', 'q.Keterangan',
    //             'm.Nama_Mesin', 'm.Seri_Mesin', 'm.Flag_Multi_Qrcode', 'm.Jumlah_Print_QRCode',
    //             'b.Nama as Nama_Barang',
    //             'q.Id_Mesin'
    //         )
    //         ->first();

    //     if (!$sampelRow) {
    //         return response()->json(['success' => false, 'status' => 404, 'message' => 'Data tidak ditemukan'], 404);
    //     }

    //     $checkedSelesai = DB::table('N_EMI_LAB_PO_Sampel')
    //                     ->whereNull("Status")
    //                     ->where('No_Sampel', $base_no_sampel)
    //                     ->where('Flag_Selesai', 'Y')
    //                     ->first();
                        
    //     if($checkedSelesai){
    //         return response()->json([
    //             'success' => true,
    //             'status' => 200,
    //             'finished' => true,
    //             'message' => 'Untuk Nomor Sampel '. $base_no_sampel . ' Sudah Ditutup, Terimakasih Atas Kinerja Kerasnya, Tetap Semangat Dan Jaga Kondisi Ya ☺️'
    //         ], 200);
    //     }

    //     // --- Analisa Default ---
    //     $analisaList = DB::table('N_EMI_LAB_Barang_Analisa as ba')
    //         ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ba.Id_Jenis_Analisa', '=', 'ja.id')
    //         ->leftJoin('N_EMI_LAB_Mesin_Analisa as ma', 'ja.Id_Mesin', '=', 'ma.No_Urut')
    //         ->where('ba.Kode_Barang', $sampelRow->Kode_Barang)
    //         ->where('ba.Id_Master_Mesin', $sampelRow->Id_Mesin)
    //         ->where('ba.Id_User', Auth::user()->UserId)
    //         ->select('ja.id as analisa_id', 'ja.Kode_Analisa', 'ja.Jenis_Analisa', 'ma.Nama_Mesin as Nama_Mesin_Analisa')
    //         ->get();

    //     // --- Analisa Opsional ---
    //     $getAnalisa = DB::table('N_EMI_LAB_PO_Sampel')
    //         ->whereNull('Status')
    //         ->where('No_Sampel', $base_no_sampel)
    //         ->where('Flag_Khusus', 'Y')
    //         ->get();

    //     // 2. SESUAIKAN QUERY ANALISA OPSIONAL AGAR STRUKTURNYA SAMA DENGAN ANALISA DEFAULT
    //     // Menambahkan join dan select agar kolomnya (analisa_id, Nama_Mesin_Analisa, dll) identik.
    //     $getAnalsiaOpsional = DB::table('N_EMI_LAB_Jenis_Analisa as ja')
    //         ->leftJoin('N_EMI_LAB_Mesin_Analisa as ma', 'ja.Id_Mesin', '=', 'ma.No_Urut')
    //         ->whereIn('ja.id', $getAnalisa->pluck('Id_Jenis_Analisa_Khusus'))
    //         ->select('ja.id as analisa_id', 'ja.Kode_Analisa', 'ja.Jenis_Analisa', 'ma.Nama_Mesin as Nama_Mesin_Analisa')
    //         ->get();

    //     // 1. TENTUKAN LIST ANALISA YANG AKAN DIGUNAKAN
    //     // Jika analisa opsional ada isinya, gunakan itu. Jika tidak, gunakan analisa default.
    //     $listYangDigunakan = $getAnalsiaOpsional->isNotEmpty() ? $getAnalsiaOpsional : $analisaList;

    //     // 3. GUNAKAN LIST YANG SUDAH DIPILIH UNTUK PROSES MAPPING
    //     // Logika di dalam map() ini tidak perlu diubah sama sekali.
    //     $analisa = collect($listYangDigunakan)->map(function ($item) use ($base_no_sampel) {
    //         /** @var \stdClass $item */
    //         $analisaId = $item->analisa_id;

    //         $ujiSampelEntries = DB::table('N_EMI_LAB_Uji_Sampel')
    //             ->where('No_Po_Sampel', $base_no_sampel)
    //             ->where('Id_Jenis_Analisa', $analisaId)
    //             ->get();

    //         if ($ujiSampelEntries->isEmpty()) {
    //             return [
    //                 'id' => Hashids::connection('custom')->encode($analisaId),
    //                 'Kode_Analisa' => $item->Kode_Analisa,
    //                 'Jenis_Analisa' => $item->Jenis_Analisa,
    //                 'Nama_Mesin' => $item->Nama_Mesin_Analisa,
    //                 'is_done' => false,
    //             ];
    //         }

    //         $isDone = true;
    //         $isMultiQR = $ujiSampelEntries->contains(fn($entry) => $entry->Flag_Multi_QrCode === 'Y');

    //         if ($isMultiQR) {
    //             $expectedSubPoCount = DB::table('N_EMI_LAB_PO_Sampel_Multi_QrCode')
    //                 ->where('No_Po_Sampel', $base_no_sampel)
    //                 ->count();

    //             $doneSubPoCount = $ujiSampelEntries
    //                 ->where('Flag_Selesai', 'Y')
    //                 ->unique('No_Fak_Sub_Po')
    //                 ->count();

    //             $isDone = $doneSubPoCount >= $expectedSubPoCount;
    //         } else {
    //             $isDone = $ujiSampelEntries->every(fn($entry) => $entry->Flag_Selesai === 'Y');
    //         }

    //         return [
    //             'id' => Hashids::connection('custom')->encode($analisaId),
    //             'Kode_Analisa' => $item->Kode_Analisa,
    //             'Jenis_Analisa' => $item->Jenis_Analisa,
    //             'Nama_Mesin' => $item->Nama_Mesin_Analisa,
    //             'is_done' => $isDone,
    //         ];
    //     });

    //     return response()->json([
    //         'success' => true,
    //         'status' => 200,
    //         'message' => 'Data Ditemukan !',
    //         'result' => [
    //             'id' => Hashids::connection('custom')->encode($sampelRow->sampel_id),
    //             'nama_barang' => $sampelRow->Nama_Barang,
    //             'no_sampel' => $sampelRow->No_Sampel,
    //             'Berat_Sampel' => (float) $sampelRow->Berat_Sampel,
    //             'no_po' => $sampelRow->No_Po,
    //             'tanggal' => $sampelRow->Tanggal,
    //             'jam' => $sampelRow->Jam,
    //             'no_split_po' => $sampelRow->No_Split_Po,
    //             'no_batch' => $sampelRow->No_Batch,
    //             'nama_mesin' => $sampelRow->Nama_Mesin,
    //             'seri_mesin' => $sampelRow->Seri_Mesin,
    //             'keterangan' => $sampelRow->Keterangan,
    //             'kode_barang' => $sampelRow->Kode_Barang,
    //             'kode_perusahaan' => $sampelRow->Kode_Perusahaan,
    //             'is_multi_print' => $sampelRow->Flag_Multi_Qrcode,
    //             'jumlah_print' => $sampelRow->Jumlah_Print_QRCode,
    //             'analisa' => $analisa,
    //         ]
    //     ]);
    // }

    public function getParameterAndPerhitunganOld($id_analisa)
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
                    N_EMI_LAB_Jenis_Analisa.Kode_Analisa,
                    N_EMI_LAB_Perhitungan.Rumus as rumus, 
                    N_EMI_LAB_Perhitungan.Nama_Kolom as nama_kolom,
                    N_EMI_LAB_Perhitungan.Hasil_Perhitungan as digit
                FROM N_EMI_LAB_Perhitungan
                JOIN N_EMI_LAB_Jenis_Analisa ON N_EMI_LAB_Perhitungan.Id_Jenis_Analisa = N_EMI_LAB_Jenis_Analisa.id
                WHERE Id_Jenis_Analisa = ?
            ", [$id_analisa])
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
                'kode_analisa' => $rumus->Kode_Analisa, 
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
            $id_jenis_analisa = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Format ID Jenis Analisa tidak valid.'
            ], 400);
        }

         $baseSampel = implode('-', array_slice(explode('-', $no_sampel), 0, 2));
    $basePoMultiQr = implode('-', array_slice(explode('-', $no_PO_Multiqr), 0, 2));

    // Bandingkan apakah nomor PO Multi QR sesuai dengan induk nomor sampel
    if ($baseSampel !== $basePoMultiQr) {
        return response()->json([
            'success' => false,
            'status'  => 400,
            'message' => "Validasi Gagal: Nomor PO Multi QR '{$no_PO_Multiqr}' tidak sesuai dengan induk Nomor Sampel '{$baseSampel}'."
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
    // public function getPoSampelMultiQrDetailV2($no_PO_Multiqr, $id_jenis_analisa)
    // {
    //     try {
    //         $id_jenis_analisa = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'status'  => 400,
    //             'message' => 'Format ID Jenis Analisa tidak valid.'
    //         ], 400);
    //     }

    //     $getNoSampel = DB::table('N_EMI_LAB_PO_Sampel_Multi_QrCode')
    //         ->select(
    //             'N_EMI_LAB_PO_Sampel_Multi_QrCode.No_Po_Multi as no_ticket',
    //             'N_EMI_LAB_PO_Sampel_Multi_QrCode.No_Po_Sampel as sampel',
    //         )
    //         ->where('N_EMI_LAB_PO_Sampel_Multi_QrCode.No_Po_Multi', $no_PO_Multiqr)
    //         ->first();
        
    //     if(empty($getNoSampel)){
    //         return response()->json([
    //             'success' => false,
    //             'status' => 404,
    //             'message' => 'Data Dengan Nomor '. $no_PO_Multiqr.' Tidak Ditemukan'
    //         ], 404);
    //     }
            
    //     $isDone = DB::table('N_EMI_LAB_Uji_Sampel')
    //             ->where('No_Po_Sampel', $getNoSampel->sampel)
    //             ->where('No_Fak_Sub_Po', $no_PO_Multiqr)
    //             ->where('Id_Jenis_Analisa', $id_jenis_analisa)
    //             ->where('Flag_Selesai', 'Y')
    //             ->first(); 
        
    //     if ($isDone) {
    //         return response()->json([
    //             'success' => true,
    //             'status' => 200,
    //             'message' => "Selamat, nomor uji sampel {$no_PO_Multiqr} sudah diselesaikan",
    //             'result' => [
    //                 'is_done' => $isDone
    //             ]
    //         ]);
    //     }
    
    //     if (empty($getNoSampel)) {
    //         return response()->json([
    //             'success' => false,
    //             'status' => 404,
    //             'message' => "Data Tidak Ditemukan!"
    //         ], 404);
    //     }
    //     return response()->json([
    //         'success' => true,
    //         'status' => 200,
    //         'message' => 'Data Ditemukan!',
    //         'result' => [
    //             'no_ticket' => $getNoSampel->no_ticket,
    //             'sampel' => $getNoSampel->sampel,
    //         ]
    //     ], 200);
    // }
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
        // dd("saa");

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
        // dd("saa");

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
                    us.Id_Jenis_Analisa = ?
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
            Log::error($e); 
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => "Terjadi Kesalahan Server: " . $e->getMessage(),
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

    // public function getDataTrackingInformasi($no_po_sampel, $no_PO_Multiqr, $id_jenis_analisa)
    // {
    //      try {
    //         $id_jenis_analisa = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'status'  => 400,
    //             'message' => 'Format ID Jenis Analisa tidak valid.'
    //         ], 400);
    //     }
    //     $parts = explode('-', $no_po_sampel);
    //     $base_no_sampel = count($parts) > 2 ? implode('-', array_slice($parts, 0, -1)) : $no_po_sampel;
        
    //     $getNoSampelRaw = DB::selectOne("
    //         SELECT 
    //             psmq.No_Po_Multi AS no_ticket, 
    //             psmq.No_Po_Sampel AS sampel,
    //             us.Flag_Multi_QrCode
    //         FROM N_EMI_LAB_PO_Sampel_Multi_QrCode psmq
    //         LEFT JOIN N_EMI_LAB_Uji_Sampel us 
    //             ON psmq.No_Po_Sampel = us.No_Po_Sampel
    //         WHERE No_Po_Multi = ?
    //     ", [$no_PO_Multiqr]);

    //     if (empty($getNoSampelRaw)) {
    //         return response()->json([
    //             'success' => false,
    //             'status' => 404,
    //             'message' => 'Data Tidak Ditemukan!'
    //         ], 404);
    //     }

    //    $result = collect(
    //         DB::table('N_EMI_LAB_Activity_Uji_Sampel')
    //             ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LAB_Activity_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
    //             ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Activity_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
    //             ->select('N_EMI_LAB_Activity_Uji_Sampel.*', 'N_EMI_LAB_PO_Sampel.No_Po', 'N_EMI_LAB_PO_Sampel.No_Split_Po', 'N_EMI_LAB_PO_Sampel.No_Batch', 'N_EMI_LAB_Jenis_Analisa.Kode_Analisa', 'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa', 'N_EMI_LAB_Jenis_Analisa.Flag_Perhitungan')
    //             ->where('N_EMI_LAB_Activity_Uji_Sampel.No_Po_Sampel', $base_no_sampel)
    //             ->where('N_EMI_LAB_Activity_Uji_Sampel.No_Fak_Sub_Po', $no_PO_Multiqr)
    //             ->where('N_EMI_LAB_Activity_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa)
    //             ->orderBy('N_EMI_LAB_Activity_Uji_Sampel.Id_Log_Activity', 'desc') 
    //             ->get()
    //     );

    //     $getHasilAnalisa = collect(
    //         DB::table('N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail')
    //             ->select('No_Po_Sampel', 'Id_Log_Activity_Sampel' ,'No_Fak_Sub_Po',  'Value_Baru', 'Value_Lama', 'Tanggal', 'Jam', 'Id_User', 'Status_Submit')
    //             ->whereIn('No_Po_Sampel', $result->pluck('No_Po_Sampel'))
    //             ->whereIn('No_Fak_Sub_Po', $result->pluck('No_Fak_Sub_Po'))
    //             ->whereIn('Id_Log_Activity_Sampel', $result->pluck('Id_Log_Activity'))
    //             ->where('Id_Jenis_Analisa', $id_jenis_analisa)
    //             ->get()
    //     );

    //     $getParameterAnalisa = collect(
    //         DB::table('N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail')
    //           ->select('No_Po_Sampel', 'Id_Log_Activity_Sampel' ,'No_Fak_Sub_Po',  'Value_Baru', 'Value_Lama', 'Tanggal', 'Jam', 'Id_User', 'Status_Submit', 'Alasan_Mengubah_Data')
    //             ->whereIn('No_Po_Sampel', $result->pluck('No_Po_Sampel'))
    //             ->whereIn('No_Fak_Sub_Po', $result->pluck('No_Fak_Sub_Po'))
    //             ->whereIn('Id_Log_Activity_Sampel', $result->pluck('Id_Log_Activity'))
    //             ->where('Id_Jenis_Analisa', $id_jenis_analisa)
    //             ->get()
    //     );

    //     $roundingDigitRows = DB::table('N_EMI_LAB_Perhitungan')
    //     ->select('Hasil_Perhitungan')
    //     ->where('Id_Jenis_Analisa', $id_jenis_analisa)
    //     ->get()
    //     ->pluck('Hasil_Perhitungan')
    //     ->values();

     
    //     $finalResult = $result->map(function ($item) use ($getHasilAnalisa, $getParameterAnalisa, $roundingDigitRows) {
    //         /** @var object $item */

    //         $filteredHasil = $getHasilAnalisa->where('Id_Log_Activity_Sampel', $item->Id_Log_Activity)->values();
    //         $filteredParameter = $getParameterAnalisa->where('Id_Log_Activity_Sampel', $item->Id_Log_Activity)->values();
    //         /**
    //          * @param TValue $hasilItem
    //          */
            
     
    //         $encodedHasil = $filteredHasil->values()->map(function($hasilItem, $index) use ($roundingDigitRows) {
    //             $precision = $roundingDigitRows[$index] ?? 2; // fallback kalau tidak cukup
            
    //             $hasilItem->Value_Lama = is_numeric($hasilItem->Value_Lama)
    //                 ? number_format((float)$hasilItem->Value_Lama, $precision, '.', '')
    //                 : $hasilItem->Value_Lama;
            
    //             $hasilItem->Value_Baru = is_numeric($hasilItem->Value_Baru)
    //                 ? number_format((float)$hasilItem->Value_Baru, $precision, '.', '')
    //                 : $hasilItem->Value_Baru;
            
    //             return $hasilItem;
    //         });

    //          /**
    //          * @param TValue $paramItem
    //          */

    //         $encodedParameter = $filteredParameter->map(function($paramItem) {
    //                 $paramItem->Id_Log_Activity_Sampel = Hashids::connection('custom')->encode($paramItem->Id_Log_Activity_Sampel);

    //                 $paramItem->Value_Baru = $paramItem->Value_Baru !== null
    //                     ? round($paramItem->Value_Baru, 4)
    //                     : null;

    //                 $paramItem->Value_Lama = $paramItem->Value_Lama !== null
    //                     ? round($paramItem->Value_Lama, 4)
    //                     : null;

    //                 return $paramItem;
    //         });

    //         return [
    //             'Id_Log_Activity' => Hashids::connection('custom')->encode($item->Id_Log_Activity),
    //             'Id_Jenis_Analisa' => Hashids::connection('custom')->encode($item->Id_Jenis_Analisa),

    //             'Kode_Perusahaan' => $item->Kode_Perusahaan,
    //             'No_Po' => $item->No_Po,
    //             'No_Split_Po' => $item->No_Split_Po,
    //             'No_Batch' => $item->No_Batch,
    //             'No_Po_Sampel' => $item->No_Po_Sampel,
    //             'No_Fak_Sub_Po' => $item->No_Fak_Sub_Po,
    //             'Jenis_Aktivitas' => $item->Jenis_Aktivitas,
    //             'Keterangan' => $item->Keterangan,
    //             'Alasan' => $encodedParameter // Menggunakan parameter yang sudah di-encode
    //                 ->pluck('Alasan_Mengubah_Data')
    //                 ->filter()
    //                 ->unique()
    //                 ->values()
    //                 ->first() ?? null,
    //             'Id_User' => $item->Id_User,
    //             'Tanggal' => $item->Tanggal,
    //             'Jam' => $item->Jam,
    //             'Kode_Analisa' => $item->Kode_Analisa,
    //             'Jenis_Analisa' => $item->Jenis_Analisa,
    //             'Flag_Perhitungan' => $item->Flag_Perhitungan,
    //             'hasil' => $encodedHasil,
    //             'parameter' => $encodedParameter
    //         ];
    //     });

    //     return response()->json([
    //         'success' => true,
    //         'status' => 200,
    //         'message' => "Data Ditemukan",
    //         'result' => $finalResult
    //     ], 200);
    // }
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

        // dd($getParameterAnalisa);

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

    // public function getDataConfirmedSelesaiV2(Request $request)
    // {
    //     // --- Mengambil Parameter Filter ---
    //     $searchQuery = $request->input('q', '');
    //     $limit = $request->input('limit', 10);
    //     $filterTanggalMulai = $request->input('tanggal_mulai');
    //     $filterTanggalSelesai = $request->input('tanggal_selesai');
    //     $filterQrCode = $request->input('qrcode');

    //     $baseQuery = DB::table('N_EMI_LAB_Uji_Sampel')
    //         ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
    //         ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
    //         ->select(
    //             'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa',
    //             'N_EMI_LAB_Uji_Sampel.No_Po_Sampel',
    //             'N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode',
    //             'N_EMI_LAB_Uji_Sampel.Tanggal',
    //             'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa',
    //             'N_EMI_LAB_Jenis_Analisa.Kode_Analisa'
    //         )
    //         // Kondisi Awal
    //         ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
    //         ->whereNull('N_EMI_LAB_Uji_Sampel.Flag_Selesai')
    //         ->where('N_EMI_LAB_Uji_Sampel.Status_Keputusan_Sampel', 'menunggu')
    //         ->groupBy(
    //             'N_EMI_LAB_Uji_Sampel.No_Po_Sampel',
    //             'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa',
    //             'N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode',
    //             'N_EMI_LAB_Uji_Sampel.Tanggal',
    //             'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa',
    //             'N_EMI_LAB_Jenis_Analisa.Kode_Analisa'
    //         );
    

    //     // 1. Global Search
    //     if (!empty($searchQuery)) {
    //         $baseQuery->where(function ($query) use ($searchQuery) {
    //             $query->where('N_EMI_LAB_Uji_Sampel.No_Po_Sampel', 'like', '%' . $searchQuery . '%')
    //                   ->orWhere('N_EMI_LAB_PO_Sampel.No_Po', 'like', '%' . $searchQuery . '%')
    //                   ->orWhere('N_EMI_LAB_PO_Sampel.No_Split_Po', 'like', '%' . $searchQuery . '%')
    //                   ->orWhere('N_EMI_LAB_PO_Sampel.No_Batch', 'like', '%' . $searchQuery . '%');
    //         });
    //     }

    //     // 2. Filter Tanggal (berdasarkan tanggal Uji Sampel)
    //     if ($filterTanggalMulai && $filterTanggalSelesai) {
    //         $baseQuery->whereBetween('N_EMI_LAB_Uji_Sampel.Tanggal', [$filterTanggalMulai, $filterTanggalSelesai]);
    //     }
        
    //     // 3. Filter Tipe QRCode
    //     if ($filterQrCode) {
    //         if ($filterQrCode === 'multi') {
    //             $baseQuery->where('N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode', 'Y');
    //         } elseif ($filterQrCode === 'single') {
    //              $baseQuery->where(function ($query) {
    //                 $query->where('N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode', '!=', 'Y')
    //                       ->orWhereNull('N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode');
    //             });
    //         }
    //     }

    //     // Eksekusi query dengan pagination
    //     $paginatedData = $baseQuery->orderByDesc('N_EMI_LAB_Uji_Sampel.Tanggal')->paginate($limit);

    //     // Proses Hashids pada hasil paginasi
    //     $paginatedData->getCollection()->transform(function ($item) {
    //         $item->Id_Jenis_Analisa = Hashids::connection('custom')->encode($item->Id_Jenis_Analisa);
    //         return $item;
    //     });

    //     if ($paginatedData->total() === 0) {
    //         return response()->json([
    //             'success' => true,
    //             'status'  => 200,
    //             'message' => "Data tidak ditemukan sesuai kriteria pencarian Anda.", // Pesan yang lebih baik
    //             'result'  => [
    //                 'data' => [],
    //                 'pagination' => [
    //                     'page'      => 1,
    //                     'limit'     => (int)$limit,
    //                     'totalPage' => 0,
    //                     'totalData' => 0,
    //                 ]
    //             ]
    //         ], 200);
    //     }

    //     $getDataInformasi = DB::table('N_EMI_LAB_Uji_Sampel')
    //     ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
    //     ->join('EMI_Master_Mesin', 'N_EMI_LAB_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
    //     ->select(
    //         'N_EMI_LAB_PO_Sampel.No_Po',
    //         'N_EMI_LAB_PO_Sampel.Kode_Barang',
    //         'N_EMI_LAB_PO_Sampel.No_Split_Po',
    //         'N_EMI_LAB_PO_Sampel.No_Batch',
    //         'EMI_Master_Mesin.Nama_Mesin',
    //         'N_EMI_LAB_Uji_Sampel.Flag_Layak',
    //         'N_EMI_LAB_Uji_Sampel.Id_User',
    //         )
    //     ->where('No_Po_Sampel', $paginatedData)
    //     ->first();

    //     $getNamaBarang = DB::table('N_EMI_View_Barang')->where('Kode_Barang', $getDataInformasi->Kode_Barang)->first()
    //      return response()->json([
    //         'success' => true,
    //         'status'  => 200,
    //         'message' => "Data Ditemukan",
    //         'result'  => [
    //             // Kirim data yang sudah diproses di dalam key 'data'
    //             'data' => $paginatedData->getCollection(),
    //             // Buat objek pagination secara manual
    //             'pagination' => [
    //                 'page'      => $paginatedData->currentPage(),
    //                 'limit'     => $paginatedData->perPage(),
    //                 'totalPage' => $paginatedData->lastPage(),
    //                 'totalData' => $paginatedData->total(),
    //             ]
    //         ]
    //     ], 200);
    // }

    public function getDataConfirmedSelesaiV2(Request $request)
    {
        $searchQuery = $request->input('q', '');
        $limit = $request->input('limit', 10);
        $filterTanggalMulai = $request->input('tanggal_mulai');
        $filterTanggalSelesai = $request->input('tanggal_selesai');
        $filterQrCode = $request->input('qrcode');

        // === BASE QUERY ===
        $baseQuery = DB::table('N_EMI_LAB_Uji_Sampel')
            ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
            ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
            ->select(
                'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa',
                'N_EMI_LAB_Uji_Sampel.No_Po_Sampel',
                'N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode',
                'N_EMI_LAB_Uji_Sampel.Tanggal',
                'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa',
                'N_EMI_LAB_Jenis_Analisa.Kode_Analisa'
            )
            ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
            ->whereNull('N_EMI_LAB_Uji_Sampel.Flag_Selesai')
            ->where('N_EMI_LAB_Uji_Sampel.Status_Keputusan_Sampel', 'menunggu')
            ->groupBy(
                'N_EMI_LAB_Uji_Sampel.No_Po_Sampel',
                'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa',
                'N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode',
                'N_EMI_LAB_Uji_Sampel.Tanggal',
                'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa',
                'N_EMI_LAB_Jenis_Analisa.Kode_Analisa'
            );

        // === FILTER ===
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

        $paginatedData = $baseQuery->orderByDesc('N_EMI_LAB_Uji_Sampel.Tanggal')->paginate($limit);

        // === Ambil semua data tambahan secara batch ===
        $noPoSampelList = $paginatedData->pluck('No_Po_Sampel')->toArray();

        $infos = DB::table('N_EMI_LAB_Uji_Sampel')
            ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
            ->join('EMI_Master_Mesin', 'N_EMI_LAB_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
            ->select(
                'N_EMI_LAB_Uji_Sampel.No_Po_Sampel',
                'N_EMI_LAB_PO_Sampel.No_Po',
                'N_EMI_LAB_PO_Sampel.Tanggal as Tanggal_Registrasi',
                'N_EMI_LAB_PO_Sampel.Jam as Jam_Registrasi',
                'N_EMI_LAB_PO_Sampel.Kode_Barang',
                'N_EMI_LAB_PO_Sampel.No_Split_Po',
                'N_EMI_LAB_PO_Sampel.No_Batch',
                'N_EMI_LAB_Uji_Sampel.Jam',
                'EMI_Master_Mesin.Nama_Mesin',
                'N_EMI_LAB_Uji_Sampel.Flag_Layak',
                'N_EMI_LAB_Uji_Sampel.Id_User'
            )
            ->whereIn('N_EMI_LAB_Uji_Sampel.No_Po_Sampel', $noPoSampelList)
            ->get()
            ->keyBy('No_Po_Sampel');

        // Ambil semua kode barang unik
        $kodeBarangList = $infos->pluck('Kode_Barang')->unique()->filter()->toArray();
        $barangList = DB::table('N_EMI_View_Barang')
            ->whereIn('Kode_Barang', $kodeBarangList)
            ->pluck('Nama', 'Kode_Barang');

        // Ambil semua Flag_Layak untuk multi QR secara batch
        $flagLayakList = DB::table('N_EMI_LAB_Uji_Sampel')
            ->whereIn('No_Po_Sampel', $noPoSampelList)
            ->select('No_Po_Sampel', 'Flag_Layak')
            ->get()
            ->groupBy('No_Po_Sampel');

        // === Transformasi tanpa N+1 Query ===
        $paginatedData->getCollection()->transform(function ($item) use ($infos, $barangList, $flagLayakList) {
            $item->Id_Jenis_Analisa = Hashids::connection('custom')->encode($item->Id_Jenis_Analisa);

            $info = $infos->get($item->No_Po_Sampel);

            if ($info) {
                $item->Flag_Layak = $info->Flag_Layak;
                $item->Id_User    = $info->Id_User;
                $item->Jam        = $info->Jam;
                $item->Tanggal_Registrasi        = $info->Tanggal_Registrasi;
                $item->Jam_Registrasi        = $info->Jam_Registrasi;
                $item->Nama_Barang = $barangList[$info->Kode_Barang] ?? null;
                $item->po_info    = [
                    'No_Po'       => $info->No_Po,
                    'No_Split_Po' => $info->No_Split_Po,
                    'No_Batch'    => $info->No_Batch,
                    'Kode_Barang' => $info->Kode_Barang,
                    'Nama_Mesin'  => $info->Nama_Mesin,
                ];
            }

            // === Status Sampel ===
            if ($item->Flag_Multi_QrCode !== 'Y') {
                $item->Status_Sampel = "Lolos Uji";
            } else {
                $flags = $flagLayakList->get($item->No_Po_Sampel)->pluck('Flag_Layak')->toArray();
                if (!empty($flags) && count(array_unique($flags)) === 1 && $flags[0] === 'Y') {
                    $item->Status_Sampel = "Lolos Uji";
                } else {
                    $item->Status_Sampel = "Tidak Lolos Uji";
                }
            }

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

    public function getDataValidasiHasilAkhirDanCloseSampel()
    {
        $result = collect(
            DB::table('N_EMI_LAB_Uji_Sampel')
                ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
                ->select(
                    'N_EMI_LAB_Uji_Sampel.*',
                    'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa',
                    'N_EMI_LAB_Jenis_Analisa.Kode_Analisa'
                )
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
        })->unique('No_Po_Sampel') // 👈 tambahkan ini agar unik berdasarkan No_Po_Sampel
        ->values(); // reset index array-nya

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => "Data Ditemukan",
            'result' => $result
        ], 200);
    }
    

    public function validasiDataMultiQrCodeV2($No_Po_Sampel)
    {
        $result = collect(
            DB::table('N_EMI_LAB_Uji_Sampel')
                ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
                ->select(
                    'N_EMI_LAB_Uji_Sampel.*',
                    'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa',
                    'N_EMI_LAB_Jenis_Analisa.Kode_Analisa'
                )
                ->where('N_EMI_LAB_Uji_Sampel.No_Po_Sampel', $No_Po_Sampel)
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

    public function validasiDataJenisAnalisaMultiQrCodeV2($No_Po_Sampel, $No_Fak_Sub_Po) 
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
                ->where('No_Fak_Sub_Po', $No_Fak_Sub_Po)
                ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
                ->whereNull('N_EMI_LAB_Uji_Sampel.Flag_Selesai')
                ->where('N_EMI_LAB_Uji_Sampel.Status_Keputusan_Sampel', 'menunggu')
                ->orderByDesc('Tanggal')
                ->get()
        )
        // Step 1: Encode ID Jenis Analisa dulu
        ->map(function ($item) {
            /** @var \stdClass $item */
            $item->Id_Jenis_Analisa = Hashids::connection('custom')->encode($item->Id_Jenis_Analisa);
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
            DB::table('N_EMI_LAB_Uji_Sampel')
                ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
                ->select(
                'N_EMI_LAB_Uji_Sampel.*',
                'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa',
                'N_EMI_LAB_Jenis_Analisa.Kode_Analisa'
                )
                ->where('No_Po_Sampel', $No_Po_Sampel)
                ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
                ->whereNull('N_EMI_LAB_Uji_Sampel.Flag_Selesai')
                ->where('N_EMI_LAB_Uji_Sampel.Status_Keputusan_Sampel', 'menunggu')
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
        // $result = collect(
        //     DB::table('N_EMI_LAB_Uji_Sampel')
        //         ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
        //         ->select(
        //            'N_EMI_LAB_Uji_Sampel.*',
        //            'N_EMI_LAB_Jenis_Analisa.Jenis_Analisa',
        //            'N_EMI_LAB_Jenis_Analisa.Kode_Analisa'
        //         )
        //         ->where('No_Po_Sampel', $No_Po_Sampel)
        //         ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
        //         ->where('N_EMI_LAB_Uji_Sampel.Flag_Selesai', 'Y')
        //         ->where('N_EMI_LAB_Uji_Sampel.Status_Keputusan_Sampel', 'terima')
        //         ->orderByDesc('Tanggal')
        //         ->get()
        // )->map(function ($item) {
        //     /** @var object $item */
        //     $item->Id_Jenis_Analisa = Hashids::connection('custom')->encode($item->Id_Jenis_Analisa);
        //     return $item;
        // });

        // return response()->json([
        //     'success' => true,
        //     'status' => 200,
        //     'message' => "Data Ditemukan",
        //     'result' => $result
        // ], 200);
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
            ->where('N_EMI_LAB_Uji_Sampel.Status_Keputusan_Sampel', 'terima')
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

    public function getDataSubSampelCurrentV1($no_Sampel)
    {
        $getDataSubPo = DB::table('N_EMI_LAB_PO_Sampel_Multi_QrCode as multi')
            ->select('No_Po_Sampel','No_Po_Multi')
            ->where('multi.No_Po_Sampel', $no_Sampel)
            ->whereNotIn('multi.No_Po_Multi', function($query) use ($no_Sampel) {
                $query->select('No_Fak_Sub_Po')
                    ->from('N_EMI_LAB_Uji_Sampel')
                    ->where('No_Po_Sampel', $no_Sampel);
            })
            ->get();

        return response()->json([
            'success' => true,
            'status' => 200,
            'result' => $getDataSubPo
        ], 200);
    }

    public function getDataHasilAnalisaSelesai()
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

        // Urutkan dari data terbaru ke terlama dan lakukan pagination
        $ujiSampel = $baseQuery
            ->orderBy('N_EMI_LAB_Uji_Sampel.Tanggal', 'desc')
            ->orderBy('N_EMI_LAB_Uji_Sampel.Jam', 'desc')
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
            ->leftJoin('N_EMI_LAB_Standar_Rentang', function ($join) {
                $join->on('N_EMI_LAB_Standar_Rentang.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa')
                    ->on('N_EMI_LAB_Standar_Rentang.Id_Master_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
                    ->on('N_EMI_LAB_Standar_Rentang.Kode_Barang', '=', 'N_EMI_LAB_PO_Sampel.Kode_Barang');
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
                'N_EMI_LAB_PO_Sampel.No_Po',
                'N_EMI_LAB_PO_Sampel.No_Split_Po',
                DB::raw("ISNULL(N_EMI_LAB_Perhitungan.Hasil_Perhitungan, 0) AS Pembulatan"),
                DB::raw("CASE 
                            WHEN N_EMI_LAB_Standar_Rentang.Id_Standar_Rentang IS NOT NULL THEN CAST(1 AS BIT)
                            ELSE CAST(0 AS BIT)
                        END AS is_sop"),
                DB::raw("CASE 
                            WHEN N_EMI_LAB_Standar_Rentang.Id_Standar_Rentang IS NOT NULL THEN N_EMI_LAB_Standar_Rentang.Range_Awal
                            ELSE NULL
                        END AS Range_Awal"),
                DB::raw("CASE 
                            WHEN N_EMI_LAB_Standar_Rentang.Id_Standar_Rentang IS NOT NULL THEN N_EMI_LAB_Standar_Rentang.Range_Akhir
                            ELSE NULL
                        END AS Range_Akhir")
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

        $jenisAnalisa = DB::table('N_EMI_LAB_Jenis_Analisa')
                ->select('Kode_Analisa')
                ->where('id', $id_jenis_analisa)
                ->first();
        

        foreach ($ujiSampel as $item) {
            $item->is_sop = (bool) $item->is_sop;
            $item->Range_Awal = (float) $item->Range_Awal;
            $item->Range_Akhir = (float) $item->Range_Akhir;
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
            $transformedParameters = $parameters->map(function ($param) use ($jenisAnalisa, $sampel, $id_jenis_analisa) {
            
                // 1. Ambil nilai float dari parameter
                $hasilFloat = floatval($param->Hasil_Analisa);
                
                // 2. Siapkan variabel untuk menampung hasil akhir (default-nya adalah nilai numerik)
                $hasilTampil = round($hasilFloat, 4);

                // 3. Terapkan kondisi khusus sesuai permintaan
                // Cek jika Kode_Analisa adalah 'MBLG-STR' DAN Flag_String dari tabel induknya adalah 'Y'
                if ($jenisAnalisa && $jenisAnalisa->Kode_Analisa === 'MBLG-STR') {
                    if ($hasilFloat == -999999) {
                        // Jika nilai -999999, ganti hasil tampil menjadi '-'
                        $hasilTampil = '-';
                    } elseif ($hasilFloat == -88888888) {
                        // Jika nilai -88888888, ganti hasil tampil menjadi '+'
                        $hasilTampil = '+';
                    }
                }
                
                return [
                    'Id_Jenis_Analisa' => Hashids::connection('custom')->encode($id_jenis_analisa),
                    'Id_Uji_Sample_Detail' => Hashids::connection('custom')->encode($param->Id_Uji_Sample_Detail),
                    'No_Faktur_Uji_Sample' => $param->No_Faktur_Uji_Sample,
                    'Id_Quality_Control' => Hashids::connection('custom')->encode($param->Id_Quality_Control),
                    // 4. Gunakan variabel $hasilTampil yang sudah diproses
                    'Hasil_Analisa' => $hasilTampil,
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

    public function getVerifikasiHasilAnalisaPerhitunganByMultiV2($id_jenis_analisa, $no_po_sampel, $no_sub)
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
            ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
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
                'N_EMI_LAB_PO_Sampel.No_Po',
                'N_EMI_LAB_PO_Sampel.No_Split_Po',
                'EMI_Master_Mesin.Flag_FG',
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
            ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
            ->where('N_EMI_LAB_Uji_Sampel.No_Po_Sampel', $no_po_sampel)
            ->where('N_EMI_LAB_Uji_Sampel.No_Fak_Sub_po', $no_sub)
            ->where('N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa)
            ->where('N_EMI_LAB_Uji_Sampel.Status_Keputusan_Sampel', 'menunggu')
            ->get();

        if ($ujiSampel->isEmpty()) {
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Data Tidak Ditemukan',
            ], 200);
        }


        $jenisAnalisa = DB::table('N_EMI_LAB_Jenis_Analisa')
                ->select('Kode_Analisa')
                ->where('id', $id_jenis_analisa)
                ->first();
        

        foreach ($ujiSampel as $item) {
            
            $item->Range_Awal = (float) $item->Range_Awal;
            $item->Range_Akhir = (float) $item->Range_Akhir;
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
            $transformedParameters = $parameters->map(function ($param) use ($jenisAnalisa, $sampel, $id_jenis_analisa) {
            
                // 1. Ambil nilai float dari parameter
                $hasilFloat = floatval($param->Hasil_Analisa);
                
                // 2. Siapkan variabel untuk menampung hasil akhir (default-nya adalah nilai numerik)
                $hasilTampil = round($hasilFloat, 4);

                // 3. Terapkan kondisi khusus sesuai permintaan
                // Cek jika Kode_Analisa adalah 'MBLG-STR' DAN Flag_String dari tabel induknya adalah 'Y'
                if ($jenisAnalisa && $jenisAnalisa->Kode_Analisa === 'MBLG-STR') {
                    if ($hasilFloat == -999999) {
                        // Jika nilai -999999, ganti hasil tampil menjadi '-'
                        $hasilTampil = '-';
                    } elseif ($hasilFloat == -88888888) {
                        // Jika nilai -88888888, ganti hasil tampil menjadi '+'
                        $hasilTampil = '+';
                    }
                }
                
                return [
                    'Id_Jenis_Analisa' => Hashids::connection('custom')->encode($id_jenis_analisa),
                    'Id_Uji_Sample_Detail' => Hashids::connection('custom')->encode($param->Id_Uji_Sample_Detail),
                    'No_Faktur_Uji_Sample' => $param->No_Faktur_Uji_Sample,
                    'Id_Quality_Control' => Hashids::connection('custom')->encode($param->Id_Quality_Control),
                    // 4. Gunakan variabel $hasilTampil yang sudah diproses
                    'Hasil_Analisa' => $hasilTampil,
                    'Tanggal_Parameter_Analisa' => $param->Tanggal_Parameter_Analisa,
                    'Jam_Parameter_Analisa' => $param->Jam_Parameter_Analisa,
                ];
            });

        
            $item['parameter'] = $transformedParameters;
            $result[] = $item;
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Data Ditemukan',
            'result' => [
                'sampel' => $result
            ]
        ], 200);
    }

    public function getVerifikasiHasilAnalisaPerhitunganBySingleQrV2($id_jenis_analisa, $no_po_sampel)
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
            ->leftJoin('N_EMI_LAB_Standar_Rentang', function ($join) {
                $join->on('N_EMI_LAB_Standar_Rentang.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa')
                    ->on('N_EMI_LAB_Standar_Rentang.Id_Master_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
                    ->on('N_EMI_LAB_Standar_Rentang.Kode_Barang', '=', 'N_EMI_LAB_PO_Sampel.Kode_Barang');
            })
            ->select(
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
            ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
            ->where('N_EMI_LAB_Uji_Sampel.No_Po_Sampel', $no_po_sampel)
            ->where('N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa)
            ->where('N_EMI_LAB_Uji_Sampel.Status_Keputusan_Sampel', 'menunggu')
            ->get();

        if ($ujiSampel->isEmpty()) {
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Data Tidak Ditemukan',
            ], 200);
        }

        
        $jenisAnalisa = DB::table('N_EMI_LAB_Jenis_Analisa')
                ->select('Kode_Analisa')
                ->where('id', $id_jenis_analisa)
                ->first();

        foreach ($ujiSampel as $item) {
           
            $item->Range_Awal = (float) $item->Range_Awal;
            $item->Range_Akhir = (float) $item->Range_Akhir;
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
            // $transformedParameters = $parameters->map(function ($param) use ($id_jenis_analisa) {
            //     return [
            //         'Id_Jenis_Analisa' => Hashids::connection('custom')->encode($id_jenis_analisa),
            //         'Id_Uji_Sample_Detail' => Hashids::connection('custom')->encode($param->Id_Uji_Sample_Detail),
            //         'No_Faktur_Uji_Sample' => $param->No_Faktur_Uji_Sample,
            //         'Id_Quality_Control' => Hashids::connection('custom')->encode($param->Id_Quality_Control),
            //         'Hasil_Analisa' => round(floatval($param->Hasil_Analisa), 4),
            //         'Tanggal_Parameter_Analisa' => $param->Tanggal_Parameter_Analisa,
            //         'Jam_Parameter_Analisa' => $param->Jam_Parameter_Analisa,
            //     ];
            // });
              $transformedParameters = $parameters->map(function ($param) use ($jenisAnalisa, $sampel, $id_jenis_analisa) {
            
                // 1. Ambil nilai float dari parameter
                $hasilFloat = floatval($param->Hasil_Analisa);
                
                // 2. Siapkan variabel untuk menampung hasil akhir (default-nya adalah nilai numerik)
                $hasilTampil = round($hasilFloat, 4);

                // 3. Terapkan kondisi khusus sesuai permintaan
                // Cek jika Kode_Analisa adalah 'MBLG-STR' DAN Flag_String dari tabel induknya adalah 'Y'
                if ($jenisAnalisa && $jenisAnalisa->Kode_Analisa === 'MBLG-STR') {
                    if ($hasilFloat == -999999) {
                        // Jika nilai -999999, ganti hasil tampil menjadi '-'
                        $hasilTampil = '-';
                    } elseif ($hasilFloat == -88888888) {
                        // Jika nilai -88888888, ganti hasil tampil menjadi '+'
                        $hasilTampil = '+';
                    }
                }
                
                return [
                    'Id_Jenis_Analisa' => Hashids::connection('custom')->encode($id_jenis_analisa),
                    'Id_Uji_Sample_Detail' => Hashids::connection('custom')->encode($param->Id_Uji_Sample_Detail),
                    'No_Faktur_Uji_Sample' => $param->No_Faktur_Uji_Sample,
                    'Id_Quality_Control' => Hashids::connection('custom')->encode($param->Id_Quality_Control),
                    // 4. Gunakan variabel $hasilTampil yang sudah diproses
                    'Hasil_Analisa' => $hasilTampil,
                    'Tanggal_Parameter_Analisa' => $param->Tanggal_Parameter_Analisa,
                    'Jam_Parameter_Analisa' => $param->Jam_Parameter_Analisa,
                ];
            });
        
            $item['parameter'] = $transformedParameters;
            $result[] = $item;
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Data Ditemukan',
            'result' => [
                'sampel' => $result
            ]
        ], 200);
    }

    public function getVerifikasiHasilAnalisaFinalKeputusanV1($id_jenis_analisa, $no_po_sampel, $no_sub)
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
            ->leftJoin('N_EMI_LAB_Standar_Rentang', function ($join) {
                $join->on('N_EMI_LAB_Standar_Rentang.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa')
                    ->on('N_EMI_LAB_Standar_Rentang.Id_Master_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
                    ->on('N_EMI_LAB_Standar_Rentang.Kode_Barang', '=', 'N_EMI_LAB_PO_Sampel.Kode_Barang');
            })
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
                'N_EMI_LAB_PO_Sampel.No_Po',
                'N_EMI_LAB_PO_Sampel.No_Split_Po',
                'EMI_Master_Mesin.Flag_FG',
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
            ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
            ->where('N_EMI_LAB_Uji_Sampel.No_Po_Sampel', $no_po_sampel)
            ->where('N_EMI_LAB_Uji_Sampel.No_Fak_Sub_po', $no_sub)
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

        foreach ($ujiSampel as $item) {
            $item->Range_Awal = (float) $item->Range_Awal;
            $item->Range_Akhir = (float) $item->Range_Akhir;
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

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Data Ditemukan',
            'result' => [
                'sampel' => $result
            ]
        ], 200);
    }

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
                'N_EMI_LAB_PO_Sampel.No_Po',
                'N_EMI_LAB_PO_Sampel.No_Split_Po',
                'EMI_Master_Mesin.Flag_FG',
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

        foreach ($ujiSampel as $item) {
            $item->Range_Awal = (float) $item->Range_Awal;
            $item->Range_Akhir = (float) $item->Range_Akhir;
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

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Data Ditemukan',
            'result' => [
                'sampel' => $result
            ]
        ], 200);
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
    // public function getDataHasilAnalisaPerhitunganByNoMultiV2($id_jenis_analisa, $no_po_sampel)
    // {
    //     try {
    //         $id_jenis_analisa = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'status'  => 400,
    //             'message' => 'Format ID Jenis Analisa tidak valid.'
    //         ], 400);
    //     }

    //     $ujiSampel = DB::table('N_EMI_LAB_Uji_Sampel')
    //         ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
    //         ->join('EMI_Master_Mesin', 'N_EMI_LAB_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
    //         ->leftJoin('N_EMI_LAB_Perhitungan', function ($join) {
    //             $join->on('N_EMI_LAB_Perhitungan.id', '=', 'N_EMI_LAB_Uji_Sampel.Id_Perhitungan')
    //                 ->on('N_EMI_LAB_Perhitungan.Kode_Perusahaan', '=', 'N_EMI_LAB_Uji_Sampel.Kode_Perusahaan');
    //         })
    //         ->leftJoin('N_EMI_LAB_Standar_Rentang', function ($join) {
    //             $join->on('N_EMI_LAB_Standar_Rentang.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa')
    //                 ->on('N_EMI_LAB_Standar_Rentang.Id_Master_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
    //                 ->on('N_EMI_LAB_Standar_Rentang.Kode_Barang', '=', 'N_EMI_LAB_PO_Sampel.Kode_Barang');
    //         })
    //         ->select(
    //             'N_EMI_LAB_Uji_Sampel.No_Faktur',
    //             'N_EMI_LAB_Uji_Sampel.No_Po_Sampel',
    //             'N_EMI_LAB_Uji_Sampel.No_Fak_Sub_Po',
    //             'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa',
    //             'N_EMI_LAB_Uji_Sampel.Tanggal as Tanggal_Pengujian',
    //             'N_EMI_LAB_Uji_Sampel.Hasil as Hasil_Akhir_Analisa',
    //             'N_EMI_LAB_PO_Sampel.No_Po',
    //             'N_EMI_LAB_PO_Sampel.No_Split_Po',
    //             DB::raw("ISNULL(N_EMI_LAB_Perhitungan.Hasil_Perhitungan, 0) AS Pembulatan"),
    //             DB::raw("CASE 
    //                     WHEN N_EMI_LAB_Standar_Rentang.Id_Standar_Rentang IS NOT NULL THEN CAST(1 AS BIT)
    //                     ELSE CAST(0 AS BIT)
    //                 END AS is_sop"),
    //             DB::raw("CASE 
    //                         WHEN N_EMI_LAB_Standar_Rentang.Id_Standar_Rentang IS NOT NULL THEN N_EMI_LAB_Standar_Rentang.Range_Awal
    //                         ELSE NULL
    //                     END AS Range_Awal"),
    //             DB::raw("CASE 
    //                         WHEN N_EMI_LAB_Standar_Rentang.Id_Standar_Rentang IS NOT NULL THEN N_EMI_LAB_Standar_Rentang.Range_Akhir
    //                         ELSE NULL
    //                     END AS Range_Akhir")
    //         )
    //         ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
    //         ->where('N_EMI_LAB_Uji_Sampel.No_Po_Sampel', $no_po_sampel)
    //         ->where('N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa)
    //         ->whereNull('N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode')
    //         ->where('N_EMI_LAB_Uji_Sampel.Flag_Selesai', 'Y')
    //         ->get();


    //     if ($ujiSampel->isEmpty()) {
    //         return response()->json([
    //             'success' => true,
    //             'status' => 200,
    //             'message' => 'Data Tidak Ditemukan',
    //         ], 200);
    //     }

        
    //     $jenisAnalisa = DB::table('N_EMI_LAB_Jenis_Analisa')
    //             ->select('Kode_Analisa')
    //             ->where('id', $id_jenis_analisa)
    //             ->first();
        

    //     foreach ($ujiSampel as $item) {
    //         $item->is_sop = (bool) $item->is_sop;
    //         $item->Range_Awal = (float) $item->Range_Awal;
    //         $item->Range_Akhir = (float) $item->Range_Akhir;
    //         $item->Hasil_Akhir_Analisa = number_format((float)$item->Hasil_Akhir_Analisa, $item->Pembulatan, '.', '');
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

    //     foreach ($ujiSampel as $sampel) {
    //         $item = (array) $sampel;
    //         $parameters = $parameterRaw->get($sampel->No_Faktur)?->values() ?? [];
        
    //         // transformasi setiap parameter
    //         $transformedParameters = $parameters->map(function ($param) use ($id_jenis_analisa) {
    //             return [
    //                 'Id_Jenis_Analisa' => Hashids::connection('custom')->encode($id_jenis_analisa),
    //                 'Id_Uji_Sample_Detail' => Hashids::connection('custom')->encode($param->Id_Uji_Sample_Detail),
    //                 'No_Faktur_Uji_Sample' => $param->No_Faktur_Uji_Sample,
    //                 'Id_Quality_Control' => Hashids::connection('custom')->encode($param->Id_Quality_Control),
    //                 'Hasil_Analisa' => round(floatval($param->Hasil_Analisa), 4),
    //                 'Tanggal_Parameter_Analisa' => $param->Tanggal_Parameter_Analisa,
    //                 'Jam_Parameter_Analisa' => $param->Jam_Parameter_Analisa,
    //             ];
    //         });
        
    //         $item['parameter'] = $transformedParameters;
    //         $result[] = $item;
    //     }

    //     $informasi = DB::table('N_EMI_LAB_Uji_Sampel')
    //         ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
    //         ->join('EMI_Master_Mesin', 'N_EMI_LAB_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
    //         ->join('N_EMI_LAB_Jenis_Analisa', 'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', '=', 'N_EMI_LAB_Jenis_Analisa.id')
    //         ->select(
    //             'N_EMI_LAB_Uji_Sampel.No_Faktur',
    //             'N_EMI_LAB_Uji_Sampel.No_Po_Sampel',
    //             'N_EMI_LAB_Uji_Sampel.No_Fak_Sub_Po',
    //             'N_EMI_LAB_Uji_Sampel.Flag_Perhitungan',
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
    //         ->where('N_EMI_LAB_Uji_Sampel.No_Po_Sampel', $result[0]['No_Po_Sampel'])
    //         ->where('N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', $id_jenis_analisa)
    //         ->whereNull('N_EMI_LAB_Uji_Sampel.Flag_Multi_QrCode')
    //         ->where('N_EMI_LAB_Uji_Sampel.Flag_Selesai', 'Y')
    //         ->first();

    //     return response()->json([
    //         'success' => true,
    //         'status' => 200,
    //         'message' => 'Data Ditemukan',
    //         'result' => [
    //             'informasi' => $informasi,
    //             'sampel' => $result
    //         ]
    //     ], 200);
    // }

    // public function getDataHasilAnalisaPerhitunganByNoMultiV2($id_jenis_analisa, $no_po_sampel)
    // {
    //     try {
    //         // Dekode ID di awal
    //         $decoded_id_jenis_analisa = Hashids::connection('custom')->decode($id_jenis_analisa)[0];
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'status'  => 400,
    //             'message' => 'Format ID Jenis Analisa tidak valid.'
    //         ], 400);
    //     }

    //     $sampelUtama = DB::table('N_EMI_LAB_Uji_Sampel as uji')
    //         ->join('N_EMI_LAB_PO_Sampel as po', 'uji.No_Po_Sampel', '=', 'po.No_Sampel')
    //         ->join('EMI_Master_Mesin as mesin', 'po.Id_Mesin', '=', 'mesin.Id_Master_Mesin')
    //         ->join('N_EMI_LAB_Jenis_Analisa as jenis', 'uji.Id_Jenis_Analisa', '=', 'jenis.id')
    //         ->leftJoin('N_EMI_LAB_Perhitungan as hitung', function ($join) {
    //             $join->on('hitung.id', '=', 'uji.Id_Perhitungan')
    //                 ->on('hitung.Kode_Perusahaan', '=', 'uji.Kode_Perusahaan');
    //         })
    //         ->leftJoin('N_EMI_LAB_Standar_Rentang as standar', function ($join) use ($decoded_id_jenis_analisa) {
    //             $join->on('standar.Id_Jenis_Analisa', '=', 'uji.Id_Jenis_Analisa')
    //                 ->on('standar.Id_Master_Mesin', '=', 'mesin.Id_Master_Mesin')
    //                 ->on('standar.Kode_Barang', '=', 'po.Kode_Barang');
    //         })
    //         ->select(
    //             // Informasi
    //             'uji.No_Faktur', 'uji.No_Po_Sampel', 'uji.No_Fak_Sub_Po', 'uji.Flag_Perhitungan',
    //             'uji.Tanggal as Tanggal_Pengujian', 'uji.Jam as Jam_Pengujian',
    //             'po.Tanggal as Tanggal_Pengajuan', 'po.Jam as Jam_Pengajuan', 'po.No_Po',
    //             'po.Keterangan as Catatan', 'po.No_Split_Po', 'po.No_Batch', 'po.Kode_Barang',
    //             'mesin.Seri_Mesin', 'mesin.Nama_Mesin', 'jenis.Kode_Analisa', 'jenis.Jenis_Analisa',
    //             // Hasil & SOP
    //             'uji.Hasil as Hasil_Akhir_Analisa',
    //             DB::raw("ISNULL(hitung.Hasil_Perhitungan, 0) AS Pembulatan"),
    //             DB::raw("CAST(CASE WHEN standar.Id_Standar_Rentang IS NOT NULL THEN 1 ELSE 0 END AS BIT) as is_sop"),
    //             'standar.Range_Awal', 'standar.Range_Akhir'
    //         )
    //         ->whereNull('uji.Status')
    //         ->where('uji.No_Po_Sampel', $no_po_sampel)
    //         ->where('uji.Id_Jenis_Analisa', $decoded_id_jenis_analisa)
    //         ->whereNull('uji.Flag_Multi_QrCode')
    //         ->where('uji.Flag_Selesai', 'Y')
    //         ->get(); // Menggunakan first() untuk mendapatkan satu record

    //     // Jika data sampel tidak ditemukan sama sekali
    //     if ($daftarSampel->isEmpty()) {
    //         return response()->json([
    //             'success' => true, 'status' => 200, 'message' => 'Data Tidak Ditemukan'
    //         ], 200);
    //     }


    //     // 2. Ambil SEMUA parameter detail yang terkait dengan sampel utama
    //     $parameterDetails = DB::table('N_EMI_LAB_Uji_Sampel_Detail')
    //         ->select(
    //             'Id_Uji_Sample_Detail', 'No_Faktur_Uji_Sample', 'Id_Quality_Control',
    //             'Value_Parameter as Hasil_Analisa',
    //             'Tanggal as Tanggal_Parameter_Analisa', 'Jam as Jam_Parameter_Analisa'
    //         )
    //         ->where('No_Faktur_Uji_Sample', $sampelUtama->No_Faktur)
    //         ->get();

    //     // 3. Proses dan format parameter detail
    //     $transformedParameters = $parameterDetails->map(function ($param) use ($sampelUtama, $id_jenis_analisa) {
    //         $hasilFloat = floatval($param->Hasil_Analisa);
    //         $hasilTampil = round($hasilFloat, 4); // Default value

    //         // Kondisi khusus untuk 'MBLG-STR'
    //         if ($sampelUtama->Kode_Analisa === 'MBLG-STR') {
    //             if ($hasilFloat == -999999) $hasilTampil = '-';
    //             if ($hasilFloat == -88888888) $hasilTampil = '+';
    //         }

    //         return [
    //             'Id_Jenis_Analisa' => $id_jenis_analisa, // Kembalikan ID yang di-hash
    //             'Id_Uji_Sample_Detail' => Hashids::connection('custom')->encode($param->Id_Uji_Sample_Detail),
    //             'No_Faktur_Uji_Sample' => $param->No_Faktur_Uji_Sample,
    //             'Id_Quality_Control' => Hashids::connection('custom')->encode($param->Id_Quality_Control),
    //             'Hasil_Analisa' => $hasilTampil,
    //             'Tanggal_Parameter_Analisa' => $param->Tanggal_Parameter_Analisa,
    //             'Jam_Parameter_Analisa' => $param->Jam_Parameter_Analisa,
    //         ];
    //     });

    //     // 4. Format data utama dan gabungkan dengan parameter
    //     $sampelUtama->is_sop = (bool) $sampelUtama->is_sop;
    //     $sampelUtama->Range_Awal = (float) $sampelUtama->Range_Awal;
    //     $sampelUtama->Range_Akhir = (float) $sampelUtama->Range_Akhir;
    //     $sampelUtama->Hasil_Akhir_Analisa = number_format((float)$sampelUtama->Hasil_Akhir_Analisa, $sampelUtama->Pembulatan, '.', '');

    //     // Buat objek `informasi` dari data utama
    //     $informasi = [
    //         'No_Faktur' => $sampelUtama->No_Faktur,
    //         'No_Po_Sampel' => $sampelUtama->No_Po_Sampel,
    //         // ... tambahkan field lain yang dibutuhkan untuk 'informasi'
    //         'Flag_Perhitungan' => $sampelUtama->Flag_Perhitungan,
    //         'Tanggal_Pengujian' => $sampelUtama->Tanggal_Pengujian,
    //         'Jam_Pengujian' => $sampelUtama->Jam_Pengujian,
    //         'Tanggal_Pengajuan' => $sampelUtama->Tanggal_Pengajuan,
    //         'Jam_Pengajuan' => $sampelUtama->Jam_Pengajuan,
    //         'No_Po' => $sampelUtama->No_Po,
    //         'Catatan' => $sampelUtama->Catatan,
    //         'No_Split_Po' => $sampelUtama->No_Split_Po,
    //         'No_Batch' => $sampelUtama->No_Batch,
    //         'Kode_Barang' => $sampelUtama->Kode_Barang,
    //         'Seri_Mesin' => $sampelUtama->Seri_Mesin,
    //         'Nama_Mesin' => $sampelUtama->Nama_Mesin,
    //         'Kode_Analisa' => $sampelUtama->Kode_Analisa,
    //         'Jenis_Analisa' => $sampelUtama->Jenis_Analisa,
    //     ];

    //     // Buat objek `sampel` dan tambahkan parameter
    //     $sampelData = (array) $sampelUtama;
    //     $sampelData['parameter'] = $transformedParameters;
        
    //     // 5. Kembalikan respons dalam struktur yang diharapkan frontend
    //     return response()->json([
    //         'success' => true,
    //         'status' => 200,
    //         'message' => 'Data Ditemukan',
    //         'result' => [
    //             'informasi' => $informasi,
    //             'sampel' => [$sampelData] // Kirim sebagai array dengan SATU elemen
    //         ]
    //     ], 200);
    // }

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
        ->select(
            // Informasi
            'uji.No_Faktur', 'uji.No_Po_Sampel', 'uji.No_Fak_Sub_Po', 'uji.Flag_Perhitungan',
            'uji.Tanggal as Tanggal_Pengujian', 'uji.Jam as Jam_Pengujian',
            'po.Tanggal as Tanggal_Pengajuan', 'po.Jam as Jam_Pengajuan', 'po.No_Po',
            'po.Keterangan as Catatan', 'po.No_Split_Po', 'po.No_Batch', 'po.Kode_Barang',
            'mesin.Seri_Mesin', 'mesin.Nama_Mesin', 'jenis.Kode_Analisa', 'jenis.Jenis_Analisa',
            // Hasil & SOP
            'uji.Hasil as Hasil_Akhir_Analisa',
            DB::raw("ISNULL(hitung.Hasil_Perhitungan, 0) AS Pembulatan"),
            DB::raw("CAST(CASE WHEN standar.Id_Standar_Rentang IS NOT NULL THEN 1 ELSE 0 END AS BIT) as is_sop"),
            'standar.Range_Awal', 'standar.Range_Akhir'
        )
        ->whereNull('uji.Status')
        ->where('uji.No_Po_Sampel', $no_po_sampel)
        ->where('uji.Id_Jenis_Analisa', $decoded_id_jenis_analisa)
        ->whereNull('uji.Flag_Multi_QrCode')
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

    public function downloadRekapSampel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'analysis' => 'required|array',
            'analysis.*' => 'required|string',
            'Flag_Perhitungan' => 'required|array',
            'Flag_Perhitungan.*' => 'required|string',
            'startDate' => 'required|date',
            'endDate' => 'required|date|after_or_equal:startDate',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Input tidak valid.',
                'errors'  => $validator->errors()
            ], 400);
        }

        // 1. Buat folder sementara yang unik untuk menyimpan file-file Excel.
        //    'uniqid()' memastikan setiap request memiliki folder terpisah.
        $tempPath = storage_path('app/temp_exports/' . uniqid());
        File::makeDirectory($tempPath, 0775, true, true);
        
        $filePaths = []; // Untuk menyimpan path dari setiap file Excel yang berhasil dibuat.

        $checkedIdMaster = $request->Id_Master_Mesin;

        if ($checkedIdMaster !== "all") {
            $decoded = Hashids::connection('custom')->decode($checkedIdMaster);            
            $checkedIdMaster = $decoded[0] ?? null;
        }

        foreach ($request->analysis as $index => $hashedIdAnalisa) {
            try {
                $id_analisa = Hashids::connection('custom')->decode($hashedIdAnalisa)[0];
            } catch (\Exception $e) {
                // Jika ID tidak valid, lewati ke iterasi berikutnya.
                continue;
            }
            
            // Ambil Flag_Perhitungan yang sesuai dengan index analisa.
            $flagPerhitungan = $request->Flag_Perhitungan[$index] ?? 'N';

            // --- Mulai Logika Pengambilan & Pemrosesan Data untuk Satu Analisa ---
            
            $getNamaJenisAnalisa = DB::table('N_EMI_LAB_Jenis_Analisa')->where('id', $id_analisa)->first();
            if (!$getNamaJenisAnalisa) {
                continue; // Lewati jika jenis analisa tidak ditemukan.
            }

            $parameterQuery = "
                SELECT
                    b.id, b.Id_Quality_Control as id_qc, b.Id_Jenis_Analisa,
                    q.Keterangan as nama_parameter, kk.Keterangan AS type_inputan,
                    q.Satuan as satuan, q.Kode_Uji as kode_uji, ja.Kode_Analisa as kode_analisa,
                    ja.Jenis_Analisa as jenis_analisa, ja.Flag_Perhitungan as flag_perhitungan
                FROM N_EMI_LAB_Binding_jenis_analisa b
                JOIN EMI_Quality_Control q ON q.Id_QC_Formula = b.Id_Quality_Control
                JOIN N_EMI_LAB_Jenis_Analisa ja ON ja.id = b.Id_Jenis_Analisa
                LEFT JOIN EMI_Kategori_Komponen kk ON kk.Id_Kategori_Komponen = q.Id_Kategori_Komponen
                WHERE b.Id_Jenis_Analisa = ?
            ";
            $getParameter = DB::select($parameterQuery, [$id_analisa]);

            if (empty($getParameter)) {
                continue; // Lewati jika tidak ada parameter.
            }

            $isPerhitungan = $flagPerhitungan === 'Y';
            $getDataRumus = $isPerhitungan
                ? DB::select("
                    SELECT Id, Id_Jenis_Analisa, Rumus as rumus, Nama_Kolom as nama_kolom, Hasil_Perhitungan as digit
                    FROM N_EMI_LAB_Perhitungan WHERE Id_Jenis_Analisa = ?
                ", [$id_analisa])
                : null;
            
            // Hashing parameters (tidak ada perubahan)
            $hashedParameters = array_map(function ($param) {
                return [
                    'id' => Hashids::connection('custom')->encode($param->id),
                    'id_qc' => Hashids::connection('custom')->encode($param->id_qc),
                    'id_jenis_analisa' => Hashids::connection('custom')->encode($param->Id_Jenis_Analisa),
                    'nama_parameter' => $param->nama_parameter, 'type_inputan' => $param->type_inputan, 'satuan' => $param->satuan,
                    'kode_uji' => $param->kode_uji, 'kode_analisa' => $param->kode_analisa, 'jenis_analisa' => $param->jenis_analisa,
                    'flag_perhitungan' => $param->flag_perhitungan,
                ];
            }, $getParameter);

            // Hashing formulas (tidak ada perubahan)
            $hashedFormula = $getDataRumus ? array_map(function ($rumus) {
                $processedRumus = preg_replace_callback('/\[(\d+)\]/', function ($matches) {
                    return '[' . Hashids::connection('custom')->encode($matches[1]) . ']';
                }, $rumus->rumus);
                return [
                    'id' => Hashids::connection('custom')->encode($rumus->Id),
                    'id_jenis_analisa' => Hashids::connection('custom')->encode($rumus->Id_Jenis_Analisa),
                    'rumus' => $processedRumus, 'nama_kolom' => $rumus->nama_kolom, 'digit' => $rumus->digit,
                ];
            }, $getDataRumus) : null;

            $ujiSampel = DB::table('N_EMI_LAB_Uji_Sampel')
                ->join('N_EMI_LAB_PO_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel', '=', 'N_EMI_LAB_PO_Sampel.No_Sampel')
                ->join('EMI_Master_Mesin', 'N_EMI_LAB_PO_Sampel.Id_Mesin', '=', 'EMI_Master_Mesin.Id_Master_Mesin')
                ->leftJoin('N_EMI_LAB_Perhitungan', function ($join) {
                    $join->on('N_EMI_LAB_Perhitungan.id', '=', 'N_EMI_LAB_Uji_Sampel.Id_Perhitungan')
                         ->on('N_EMI_LAB_Perhitungan.Kode_Perusahaan', '=', 'N_EMI_LAB_Uji_Sampel.Kode_Perusahaan');
                })
                ->select(
                    'N_EMI_LAB_Uji_Sampel.No_Faktur', 'N_EMI_LAB_Uji_Sampel.No_Po_Sampel', 'N_EMI_LAB_Uji_Sampel.No_Fak_Sub_Po',
                    'N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', 
                    'N_EMI_LAB_Uji_Sampel.Id_Mesin', 
                    'N_EMI_LAB_Uji_Sampel.Tanggal as Tanggal_Pengujian',
                    'N_EMI_LAB_Uji_Sampel.Hasil as Hasil_Akhir_Analisa', 'N_EMI_LAB_PO_Sampel.No_Po', 'N_EMI_LAB_PO_Sampel.No_Split_Po',
                    'N_EMI_LAB_PO_Sampel.Flag_Selesai', DB::raw("ISNULL(N_EMI_LAB_Perhitungan.Hasil_Perhitungan, 0) AS Pembulatan")
                )
                ->whereNull('N_EMI_LAB_Uji_Sampel.Status')
                ->where('N_EMI_LAB_Uji_Sampel.Id_Jenis_Analisa', $id_analisa)
                ->where('N_EMI_LAB_Uji_Sampel.Flag_Selesai', 'Y')
                ->whereBetween('N_EMI_LAB_Uji_Sampel.Tanggal', [$request->startDate, $request->endDate]);

                if ($checkedIdMaster !== "all") {
                    $ujiSampel->where('N_EMI_LAB_Uji_Sampel.Id_Mesin', $checkedIdMaster);
                }

            $ujiSampel = $ujiSampel->get();

            if ($ujiSampel->isEmpty()) {
                continue; // Lewati jika tidak ada data uji sampel.
            }

            foreach ($ujiSampel as $item) {
                $item->Hasil_Akhir_Analisa = number_format((float)$item->Hasil_Akhir_Analisa, $item->Pembulatan, '.', '');
            }

            $parameterRaw = DB::table('N_EMI_LAB_Uji_Sampel_Detail')
                ->select('Id_Uji_Sample_Detail', 'No_Faktur_Uji_Sample', 'Id_Quality_Control', 'Value_Parameter as Hasil_Analisa', 'Tanggal as Tanggal_Parameter_Analisa', 'Jam as Jam_Parameter_Analisa')
                ->whereIn('No_Faktur_Uji_Sample', $ujiSampel->pluck('No_Faktur'))
                ->get()->groupBy('No_Faktur_Uji_Sample');
            
            $result = [];
            foreach ($ujiSampel as $sampel) {
                $item = (array) $sampel;
                $parameters = $parameterRaw->get($sampel->No_Faktur)?->values() ?? [];
                $transformedParameters = $parameters->map(function ($param) use ($id_analisa) {
                    return [
                        'Id_Jenis_Analisa' => Hashids::connection('custom')->encode($id_analisa),
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
            // --- Akhir dari Logika Pengambilan Data ---

            // Membuat nama file yang aman untuk sistem operasi.
            $start = date('d-m-Y', strtotime($request->startDate));
            $end = date('d-m-Y', strtotime($request->endDate));
            $periode = $start . '_sampai_' . $end;
            $namaAnalisa = ucwords(strtolower($getNamaJenisAnalisa->Jenis_Analisa));
            // Mengganti karakter yang tidak diizinkan dengan underscore.
            $safeNamaAnalisa = preg_replace('/[^A-Za-z0-9\-]/', '_', $namaAnalisa);
            $excelFileName = 'Rekap ' . $safeNamaAnalisa . ' Periode ' . $periode . '.xlsx';
            
            // Simpan file Excel ke folder sementara.
            Excel::store(
                new RekapSampelExport($result, $hashedParameters, $hashedFormula ?? [], $namaAnalisa),
                'temp_exports/' . basename($tempPath) . '/' . $excelFileName
            );

            // Tambahkan path file yang baru dibuat ke dalam array untuk di-zip.
            $filePaths[] = $tempPath . DIRECTORY_SEPARATOR . $excelFileName;

        } // Akhir dari loop foreach.

        // Jika tidak ada satupun file yang berhasil dibuat, kirim pesan error.
        if (empty($filePaths)) {
             return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Tidak ada data yang dapat diproses untuk kriteria yang dipilih.',
            ], 404);
        }

        // 3. Membuat file ZIP dari semua file Excel yang telah dibuat.
        $zip = new ZipArchive;
        // FIX: Mengganti ':' dengan '-' pada nama file agar valid di Windows.
        $zipFileName = 'Rekap Sampel ' . date('d-m-Y_H-i-s') . '.zip';
        $zipPath = $tempPath . DIRECTORY_SEPARATOR . $zipFileName;

        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            foreach ($filePaths as $file) {
                // Pastikan file benar-benar ada sebelum ditambahkan ke ZIP.
                if (File::exists($file)) {
                    $zip->addFile($file, basename($file));
                }
            }
            $zip->close();
        } else {
            // Jika gagal membuat zip, hapus folder sementara dan beri pesan error.
            File::deleteDirectory($tempPath);
            return response()->json(['success' => false, 'status' => 500, 'message' => 'Gagal membuat file arsip ZIP.'], 500);
        }

        // 4. Kirim file ZIP untuk diunduh.
        // `deleteFileAfterSend(true)` akan menghapus file ZIP dari server setelah diunduh.
        // Folder sementara dan file-file Excel di dalamnya akan dihapus setelah respons terkirim.
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

        $pdfFilesData = [];
        $checkedIdMaster = $request->Id_Master_Mesin;

        if ($checkedIdMaster && $checkedIdMaster !== "all") {
            $decoded = Hashids::connection('custom')->decode($checkedIdMaster);
            $checkedIdMaster = $decoded[0] ?? null;
        }
        
        // OPTIMASI 1: Baca file logo sekali saja di luar loop
        $logoBase64 = 'data:image/png;base64,' . base64_encode(File::get(public_path('assets/images/thumb-excel.png')));

        // 2. LOOPING SETIAP ANALISA
        foreach ($request->analysis as $key => $hashedId) {
            if (empty($hashedId)) continue;

            $flagPerhitungan = $request->Flag_Perhitungan[$key] ?? null;

            $pdfResult = $this->generatePdfDataForAnalysis(
                $hashedId,
                $flagPerhitungan,
                $request->startDate,
                $request->endDate,
                $checkedIdMaster,
                $logoBase64 // Kirim data logo sebagai argumen
            );

            if ($pdfResult) {
                $pdfFilesData[] = $pdfResult;
            }
        }

        // 3. JIKA TIDAK ADA DATA
        if (empty($pdfFilesData)) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Data tidak ditemukan untuk semua analisa yang dipilih pada rentang tanggal tersebut.'
            ], 404);
        }

        // 4. BUAT DAN UNDUH FILE ZIP
        $zip = new ZipArchive();
        $zipFileName = 'Rekap_Sampel_' . now()->format('Ymd_His') . '.zip';
        $zipPath = storage_path('app/' . $zipFileName);

        if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
            return response()->json(['message' => 'Gagal membuat file zip.'], 500);
        }

        foreach ($pdfFilesData as $pdfData) {
            $pdf = PDF::loadView('pdf.rekap-sampel', $pdfData['viewData'])->setPaper('a4', 'landscape');
            $zip->addFromString($pdfData['fileName'], $pdf->output());
        }

        $zip->close();

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    private function generatePdfDataForAnalysis(string $hashedId, ?string $flagPerhitungan, string $startDate, string $endDate, $checkedIdMaster, string $logoBase64): ?array
    {
        $decodedId = Hashids::connection('custom')->decode($hashedId);
        if (empty($decodedId)) return null;
        $id_analisa = $decodedId[0];

        $isPerhitungan = $flagPerhitungan === 'Y';

        $getNamaJenisAnalisa = DB::table('N_EMI_LAB_Jenis_Analisa')->where('id', $id_analisa)->first();
        if (!$getNamaJenisAnalisa) return null;

        // Query kueri metadata sekali di awal
        $getParameter = DB::select("
            SELECT q.Keterangan as nama_parameter 
            FROM N_EMI_LAB_Binding_jenis_analisa b 
            JOIN EMI_Quality_Control q ON q.Id_QC_Formula = b.Id_Quality_Control 
            WHERE b.Id_Jenis_Analisa = ?", [$id_analisa]);
        
        $getDataRumus = $isPerhitungan 
            ? DB::select("SELECT Nama_Kolom as nama_kolom FROM N_EMI_LAB_Perhitungan WHERE Id_Jenis_Analisa = ?", [$id_analisa])
            : [];

        // Ambil data uji sampel utama
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

        // --- INTI OPTIMASI: MENGHILANGKAN N+1 QUERY ---
        // 1. Kumpulkan semua ID yang dibutuhkan
        $kodeBarangIds = $ujiSampel->pluck('Kode_Barang')->unique()->filter();
        $idMesinIds    = $ujiSampel->pluck('Id_Mesin')->unique()->filter();

        // 2. Ambil semua data yang relevan dalam satu query per tabel
        $namaBarangMap = DB::table('N_EMI_View_Barang')
            ->whereIn('Kode_Barang', $kodeBarangIds)
            ->pluck('Nama', 'Kode_Barang');

        $namaMesinMap = DB::table('EMI_Master_Mesin')
            ->whereIn('Id_Master_Mesin', $idMesinIds)
            ->pluck('Nama_Mesin', 'Id_Master_Mesin');

        // 3. Proses data sampel dengan lookup ke data yang sudah diambil (tanpa query lagi)
        $ujiSampel->each(function ($row) use ($namaBarangMap, $namaMesinMap) {
            $namaBarang = $namaBarangMap[$row->Kode_Barang] ?? '-';
            $namaMesin  = $namaMesinMap[$row->Id_Mesin] ?? '-';
            $row->Nama_Sampel_Format = "{$namaBarang}-{$row->No_Po_Sampel}-{$namaMesin}";
        });
        // --- AKHIR OPTIMASI N+1 ---

        // Ambil detail parameter (sudah efisien menggunakan whereIn)
         $parameterRaw = DB::table('N_EMI_LAB_Uji_Sampel_Detail as usd')
                ->join('EMI_Quality_Control as qc', 'qc.Id_QC_Formula', '=', 'usd.Id_Quality_Control')
                ->whereIn('usd.No_Faktur_Uji_Sample', $ujiSampel->pluck('No_Faktur'))
                ->get([
                    'usd.No_Faktur_Uji_Sample',
                    'usd.Value_Parameter as Hasil_Analisa',
                    'qc.Keterangan as nama_parameter'
                ])
                ->groupBy('No_Faktur_Uji_Sample');
        
        // Grouping dan proses data (logika tetap sama)
        $dataTerproses = $ujiSampel->groupBy('No_Faktur')->map(function ($grup) use ($parameterRaw, $isPerhitungan, $getNamaJenisAnalisa) {
        $itemPertama = $grup->first();
        $faktur = $itemPertama->No_Faktur;

        $hasilParameter = collect($parameterRaw->get($faktur) ?? [])
            ->map(function ($p) use ($getNamaJenisAnalisa) {
                $value = $p->Hasil_Analisa;
                
                if ($getNamaJenisAnalisa->Kode_Analisa === 'MBLG-STR' && strtoupper(trim($p->nama_parameter ?? '')) === 'SALMONELLA') {
                    if ($value == -999999) {
                        return '-';
                    } elseif ($value == -88888888) {
                        return '+';
                    }
                }

                return round((float)$value, 4);
            })
            ->all();

        $hasilAkhir = $isPerhitungan
                ? $grup->map(fn($item) => number_format((float)$item->Hasil_Akhir_Analisa, $item->Pembulatan, '.', ''))->all()
                : [];

            return [
                'item'            => $itemPertama,
                'parameters'      => $hasilParameter,
                'results'         => $hasilAkhir,
                '_original_group' => $grup
            ];
        })->values();
        
        // Hitung rata-rata
        $rataRata = [];
        if ($isPerhitungan && $dataTerproses->isNotEmpty()) {
            $jumlahKolomRumus = count($getDataRumus);
            for ($i = 0; $i < $jumlahKolomRumus; $i++) {
                $kolomData = $dataTerproses->pluck('results.' . $i)->filter(fn($val) => is_numeric($val));
                $pembulatan = $dataTerproses->first()['_original_group'][$i]->Pembulatan ?? 2;
                
                if ($kolomData->isNotEmpty()) {
                    $rataRata[] = number_format($kolomData->avg(), $pembulatan, '.', '');
                } else {
                    $rataRata[] = '-';
                }
            }
        }
        
        // Data untuk view
        $headings = ['NO', 'TANGGAL ANALISA', 'NO PO', 'NO SPLIT PO', 'NO BATCH', 'NAMA SAMPEL'];
        $headings = array_merge($headings, array_map('strtoupper', array_column($getParameter, 'nama_parameter')));
        if ($isPerhitungan) {
            $headings = array_merge($headings, array_map('strtoupper', array_column($getDataRumus, 'nama_kolom')));
        }
        
        $collection = $dataTerproses->map(function ($data, $key) {
            $item = $data['item'];
            $baris = [
                'no'          => $key + 1,
                'tanggal'     => Carbon::parse($item->Tanggal_Pengujian)->isoFormat('DD-MMMM-YYYY'), // OPTIMASI 2
                'no_po'       => $item->No_Po,
                'no_split_po' => $item->No_Split_Po,
                'no_batch'    => $item->No_Batch,
                'nama_sampel' => $item->Nama_Sampel_Format,
            ];
            return array_merge($baris, $data['parameters'], $data['results']);
        });

        $namaAnalisaClean = preg_replace('/[^A-Za-z0-9\-]/', '_', $getNamaJenisAnalisa->Jenis_Analisa);
        $fileName = 'Rekap_Sampel_' . $namaAnalisaClean . '_' . Carbon::parse($startDate)->format('Ymd') . '-' . Carbon::parse($endDate)->format('Ymd') . '.pdf';

        return [
            'viewData' => [
                'namaAnalisa'       => ucwords(strtolower($getNamaJenisAnalisa->Jenis_Analisa)),
                'periode'           => Carbon::parse($startDate)->format('d M Y') . ' s/d ' . Carbon::parse($endDate)->format('d M Y'),
                'logoBase64'        => $logoBase64,
                'headings'          => $headings,
                'collection'        => $collection,
                'apakahPerhitungan' => $isPerhitungan,
                'rataRata'          => $rataRata,
                'rumusCount'        => count($getDataRumus),
            ],
            'fileName' => $fileName,
        ];
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
            'Flag_Perhitungan' => 'required|array',
            'startDate' => 'required|date',
            'endDate' => 'required|date|after_or_equal:startDate',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Input tidak valid', 'errors' => $validator->errors()], 400);
        }

        $analysisIds = $request->analysis;
        $start = $request->startDate;
        $end = $request->endDate;
        $checkedIdMaster = $request->Id_Master_Mesin;

        if ($checkedIdMaster !== "all") {
            $decoded = Hashids::connection('custom')->decode($checkedIdMaster);
            $checkedIdMaster = $decoded[0] ?? null;
        }

        $results = [];

        $decodedIds = collect($analysisIds)->map(function ($encoded) {
            return Hashids::connection('custom')->decode($encoded)[0] ?? null;
        })->filter()->values()->all();

        // MODIFIKASI 1: Ambil data analisa lebih lengkap (termasuk Kode_Analisa)
        $jenisAnalisaAll = DB::table('N_EMI_LAB_Jenis_Analisa')
            ->whereIn('id', $decodedIds)
            ->get()
            ->keyBy('id'); // Gunakan keyBy untuk memudahkan pencarian berdasarkan ID

        $analisaHeaders = [];
        foreach ($decodedIds as $id) {
            if (isset($jenisAnalisaAll[$id])) {
                $currentAnalisa = $jenisAnalisaAll[$id];
                $analisaHeaders[] = [
                    'nama' => $currentAnalisa->Jenis_Analisa,
                    'kode' => $currentAnalisa->Kode_Analisa
                ];
            }
        }

        foreach ($analysisIds as $index => $encodedId) {
            $id = Hashids::connection('custom')->decode($encodedId)[0] ?? null;
            if (!$id) continue;

            // Ambil info lengkap dari analisa saat ini
            $currentAnalisa = $jenisAnalisaAll->get($id);
            if (!$currentAnalisa) continue;

            $namaKolom = $currentAnalisa->Jenis_Analisa;
            
            // --- LOGIKA PERCABANGAN BERDASARKAN KODE ANALISA ---
            if ($currentAnalisa->Kode_Analisa === 'MBLG-STR') {
                // --- BLOK KHUSUS UNTUK ANALISA MBLG-STR ---
                $query = DB::table('N_EMI_LAB_Uji_Sampel as us')
                    ->join('N_EMI_LAB_PO_Sampel as ps', 'us.No_Po_Sampel', '=', 'ps.No_Sampel')
                    ->where('us.Id_Jenis_Analisa', $id)
                    ->whereBetween('us.Tanggal', [$start, $end])
                    ->where('us.Flag_Selesai', 'Y')
                    ->whereNull('us.Status')
                    // Tambahan WHERE clause sesuai permintaan
                    ->where('us.Flag_Final', 'Y')
                    ->where('us.Status_Keputusan_Sampel', 'terima');

                if ($checkedIdMaster !== "all") {
                    $query->where('us.Id_Mesin', $checkedIdMaster);
                }
                
                // Hitung jumlah Flag_Layak = 'T'
                $data = $query->select(
                        'ps.No_Po',
                        'ps.No_Split_Po',
                        'ps.Kode_Barang',
                        'us.Id_Mesin',
                        'us.Tanggal as Tanggal_Pengujian',
                        DB::raw("SUM(CASE WHEN us.Flag_Layak = 'T' THEN 1 ELSE 0 END) as jumlah_tidak_layak")
                    )
                    ->groupBy('ps.No_Po', 'ps.No_Split_Po', 'ps.Kode_Barang', 'us.Id_Mesin', 'us.Tanggal')
                    ->get();

                foreach ($data as $item) {
                    $key = $item->No_Split_Po . '|' . $item->Tanggal_Pengujian . '|' . $item->Id_Mesin;

                    if (!isset($results[$key])) {
                        // Logika untuk mengisi data master (Nama Sampel, Tanggal, dll)
                        // (Ini sama seperti blok di bawah, hanya dicopy untuk menjaga struktur)
                        $barang = DB::table('N_EMI_View_Barang')->where('Kode_Barang', $item->Kode_Barang)->first();
                        $namaBarang = $barang->Nama ?? 'NAMA_BARANG_TIDAK_DITEMUKAN';
                        $mesin = DB::table('EMI_Master_Mesin')->where('Id_Master_Mesin', $item->Id_Mesin)->first();
                        $namaMesin = $mesin->Nama_Mesin ?? 'NAMA_MESIN_TIDAK_DIKENAL';
                        $namaSampel = $namaBarang . '-' . $item->No_Split_Po . '-' . $namaMesin;
                        $orderProduksi = DB::table('N_EMI_View_Order_Produksi')->where('No_Faktur', $item->No_Po)->first();
                        $tanggalProduksiFormatted = $orderProduksi ? $this->formatTanggalIndoLengkap($orderProduksi->Tanggal) : 'Tidak Ada';

                        $results[$key] = [
                            'No' => count($results) + 1,
                            'Nama_Sampel' => $namaSampel,
                            'Tanggal_Produksi' => $tanggalProduksiFormatted,
                            'Tanggal' => $this->formatTanggalIndoLengkap($item->Tanggal_Pengujian),
                            'Analisa' => [],
                        ];
                    }

                    $nilaiUji = ($item->jumlah_tidak_layak > 0) ? 'Tidak lolos uji' : 'Lolos Uji';

                    $results[$key]['Analisa'][] = [
                        'nama' => $namaKolom,
                        'nilai' => $nilaiUji,
                    ];
                }
            } else {
                $query = DB::table('N_EMI_LAB_Uji_Sampel as us')
                    ->join('N_EMI_LAB_PO_Sampel as ps', 'us.No_Po_Sampel', '=', 'ps.No_Sampel')
                    ->leftJoin('N_EMI_LAB_Perhitungan as p', 'p.id', '=', 'us.Id_Perhitungan')
                    ->where('us.Id_Jenis_Analisa', $id)
                    ->whereBetween('us.Tanggal', [$start, $end])
                    ->where('us.Flag_Selesai', 'Y')
                    ->whereNull('us.Status');

                if ($checkedIdMaster !== "all") {
                    $query->where('us.Id_Mesin', $checkedIdMaster);
                }

                $data = $query->select(
                        'ps.No_Po',
                        'ps.No_Split_Po',
                        'ps.Kode_Barang',
                        'us.Id_Mesin',
                        'us.Tanggal as Tanggal_Pengujian',
                        'us.Flag_Perhitungan',
                        DB::raw('AVG(us.Hasil) as Nilai_Rata_Rata'),
                        DB::raw('MAX(p.Hasil_Perhitungan) as Digit_Belakang_Koma')
                    )
                    ->groupBy('ps.No_Po', 'ps.No_Split_Po', 'ps.Kode_Barang', 'us.Id_Mesin', 'us.Tanggal', 'us.Flag_Perhitungan')
                    ->get();

                foreach ($data as $item) {
                    $key = $item->No_Split_Po . '|' . $item->Tanggal_Pengujian . '|' . $item->Id_Mesin;

                    if (!isset($results[$key])) {
                        $barang = DB::table('N_EMI_View_Barang')->where('Kode_Barang', $item->Kode_Barang)->first();
                        $namaBarang = $barang->Nama ?? 'NAMA_BARANG_TIDAK_DITEMUKAN';
                        $mesin = DB::table('EMI_Master_Mesin')->where('Id_Master_Mesin', $item->Id_Mesin)->first();
                        $namaMesin = $mesin->Nama_Mesin ?? 'NAMA_MESIN_TIDAK_DIKENAL';
                        $namaSampel = $namaBarang . '-' . $item->No_Split_Po . '-' . $namaMesin;
                        $orderProduksi = DB::table('N_EMI_View_Order_Produksi')->where('No_Faktur', $item->No_Po)->first();
                        $tanggalProduksiFormatted = $orderProduksi ? $this->formatTanggalIndoLengkap($orderProduksi->Tanggal) : 'Tidak Ada';

                        $results[$key] = [
                            'No' => count($results) + 1,
                            'Nama_Sampel' => $namaSampel,
                            'Tanggal_Produksi' => $tanggalProduksiFormatted,
                            'Tanggal' => $this->formatTanggalIndoLengkap($item->Tanggal_Pengujian),
                            'Analisa' => [],
                        ];
                    }

                    $results[$key]['Analisa'][] = [
                        'nama' => $namaKolom,
                        'nilai' => number_format((float)$item->Nilai_Rata_Rata, 2, '.', ''),
                    ];
                }
            }
        }

        foreach ($results as &$row) {
            $analisaMap = collect($row['Analisa'])->pluck('nilai', 'nama');
            $filledAnalisa = [];
            // MODIFIKASI 2: Gunakan $analisaHeaders untuk mengisi data
            foreach ($analisaHeaders as $header) {
                $filledAnalisa[] = [
                    'nama' => $header['nama'],
                    'nilai' => $analisaMap[$header['nama']] ?? 'Tidak Ada Data',
                ];
            }
            $row['Analisa'] = $filledAnalisa;
        }
        unset($row);

        if (empty($results)) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => "Data tidak ditemukan"
            ], 404);
        }
        
        $data = [
            'collection' => array_values($results),
            'headers' => $analisaHeaders,
            'logoBase64' => 'data:image/png;base64,' . base64_encode(File::get(public_path('assets/images/thumb-excel.png'))),
        ];

        $pdf = PDF::loadView('pdf.rekap-sampel-laporan', $data)->setPaper('a4', 'landscape');
        return $pdf->stream('Laporan_Hasil_Analisa_' . now()->format('Ymd_His') . '.pdf');
    }

    public function downloadRekapSampelByExcellV2(Request $request)
    {
        // 1. Validasi
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'analysis' => 'required|array',
            'Flag_Perhitungan' => 'required|array',
            'startDate' => 'required|date',
            'endDate' => 'required|date|after_or_equal:startDate',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Input tidak valid', 'errors' => $validator->errors()], 400);
        }

        // --- [MULAI KODE PENGAMBILAN DATA LENGKAP] ---
        $analysisIds = $request->analysis;
        $start = $request->startDate;
        $end = $request->endDate;
        $checkedIdMaster = $request->Id_Master_Mesin;

        if ($checkedIdMaster !== "all") {
            $decoded = \Vinkla\Hashids\Facades\Hashids::connection('custom')->decode($checkedIdMaster);
            $checkedIdMaster = $decoded[0] ?? null;
        }

        $results = [];

        $decodedIds = collect($analysisIds)->map(function ($encoded) {
            return \Vinkla\Hashids\Facades\Hashids::connection('custom')->decode($encoded)[0] ?? null;
        })->filter()->values()->all();

        $jenisAnalisaAll = \Illuminate\Support\Facades\DB::table('N_EMI_LAB_Jenis_Analisa')
            ->whereIn('id', $decodedIds)
            ->get()
            ->keyBy('id');

        $analisaHeaders = [];
        foreach ($decodedIds as $id) {
            if (isset($jenisAnalisaAll[$id])) {
                $currentAnalisa = $jenisAnalisaAll[$id];
                $analisaHeaders[] = [
                    'nama' => $currentAnalisa->Jenis_Analisa,
                    'kode' => $currentAnalisa->Kode_Analisa
                ];
            }
        }

        foreach ($analysisIds as $encodedId) {
            $id = \Vinkla\Hashids\Facades\Hashids::connection('custom')->decode($encodedId)[0] ?? null;
            if (!$id) continue;

            $currentAnalisa = $jenisAnalisaAll->get($id);
            if (!$currentAnalisa) continue;

            $namaKolom = $currentAnalisa->Jenis_Analisa;

            if ($currentAnalisa->Kode_Analisa === 'MBLG-STR') {
                $query = \Illuminate\Support\Facades\DB::table('N_EMI_LAB_Uji_Sampel as us')
                    ->join('N_EMI_LAB_PO_Sampel as ps', 'us.No_Po_Sampel', '=', 'ps.No_Sampel')
                    ->where('us.Id_Jenis_Analisa', $id)
                    ->whereBetween('us.Tanggal', [$start, $end])
                    ->where('us.Flag_Selesai', 'Y')->whereNull('us.Status')
                    ->where('us.Flag_Final', 'Y')->where('us.Status_Keputusan_Sampel', 'terima');

                if ($checkedIdMaster !== "all") {
                    $query->where('us.Id_Mesin', $checkedIdMaster);
                }

                $data = $query->select(
                        'ps.No_Po', 'ps.No_Split_Po', 'ps.Kode_Barang', 'us.Id_Mesin',
                        'us.Tanggal as Tanggal_Pengujian',
                        \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN us.Flag_Layak = 'T' THEN 1 ELSE 0 END) as jumlah_tidak_layak")
                    )
                    ->groupBy('ps.No_Po', 'ps.No_Split_Po', 'ps.Kode_Barang', 'us.Id_Mesin', 'us.Tanggal')
                    ->get();

                foreach ($data as $item) {
                    $key = $item->No_Split_Po . '|' . $item->Tanggal_Pengujian . '|' . $item->Id_Mesin;

                    if (!isset($results[$key])) {
                        $barang = \Illuminate\Support\Facades\DB::table('N_EMI_View_Barang')->where('Kode_Barang', $item->Kode_Barang)->first();
                        $namaBarang = $barang->Nama ?? 'N/A';
                        $mesin = \Illuminate\Support\Facades\DB::table('EMI_Master_Mesin')->where('Id_Master_Mesin', $item->Id_Mesin)->first();
                        $namaMesin = $mesin->Nama_Mesin ?? 'N/A';
                        $namaSampel = $namaBarang . '-' . $item->No_Split_Po . '-' . $namaMesin;
                        $orderProduksi = \Illuminate\Support\Facades\DB::table('N_EMI_View_Order_Produksi')->where('No_Faktur', $item->No_Po)->first();
                        $tanggalProduksiFormatted = $orderProduksi ? $this->formatTanggalIndoLengkap($orderProduksi->Tanggal) : 'Tidak Ada';

                        $results[$key] = [
                            'No' => count($results) + 1, 'Nama_Sampel' => $namaSampel,
                            'Tanggal_Produksi' => $tanggalProduksiFormatted,
                            'Tanggal' => $this->formatTanggalIndoLengkap($item->Tanggal_Pengujian), 'Analisa' => [],
                        ];
                    }
                    $nilaiUji = ($item->jumlah_tidak_layak > 0) ? 'Tidak lolos uji' : 'Lolos Uji';
                    $results[$key]['Analisa'][] = ['nama' => $namaKolom, 'nilai' => $nilaiUji];
                }

            } else {
                $query = \Illuminate\Support\Facades\DB::table('N_EMI_LAB_Uji_Sampel as us')
                    ->join('N_EMI_LAB_PO_Sampel as ps', 'us.No_Po_Sampel', '=', 'ps.No_Sampel')
                    ->leftJoin('N_EMI_LAB_Perhitungan as p', 'p.id', '=', 'us.Id_Perhitungan')
                    ->where('us.Id_Jenis_Analisa', $id)->whereBetween('us.Tanggal', [$start, $end])
                    ->where('us.Flag_Selesai', 'Y')->whereNull('us.Status');

                if ($checkedIdMaster !== "all") {
                    $query->where('us.Id_Mesin', $checkedIdMaster);
                }

                $data = $query->select(
                        'ps.No_Po', 'ps.No_Split_Po', 'ps.Kode_Barang', 'us.Id_Mesin',
                        'us.Tanggal as Tanggal_Pengujian', 'us.Flag_Perhitungan',
                        \Illuminate\Support\Facades\DB::raw('AVG(us.Hasil) as Nilai_Rata_Rata'),
                        \Illuminate\Support\Facades\DB::raw('MAX(p.Hasil_Perhitungan) as Digit_Belakang_Koma')
                    )
                    ->groupBy('ps.No_Po', 'ps.No_Split_Po', 'ps.Kode_Barang', 'us.Id_Mesin', 'us.Tanggal', 'us.Flag_Perhitungan')
                    ->get();

                foreach ($data as $item) {
                    $key = $item->No_Split_Po . '|' . $item->Tanggal_Pengujian . '|' . $item->Id_Mesin;

                    if (!isset($results[$key])) {
                        $barang = \Illuminate\Support\Facades\DB::table('N_EMI_View_Barang')->where('Kode_Barang', $item->Kode_Barang)->first();
                        $namaBarang = $barang->Nama ?? 'N/A';
                        $mesin = \Illuminate\Support\Facades\DB::table('EMI_Master_Mesin')->where('Id_Master_Mesin', $item->Id_Mesin)->first();
                        $namaMesin = $mesin->Nama_Mesin ?? 'N/A';
                        $namaSampel = $namaBarang . '-' . $item->No_Split_Po . '-' . $namaMesin;
                        $orderProduksi = \Illuminate\Support\Facades\DB::table('N_EMI_View_Order_Produksi')->where('No_Faktur', $item->No_Po)->first();
                        $tanggalProduksiFormatted = $orderProduksi ? $this->formatTanggalIndoLengkap($orderProduksi->Tanggal) : 'Tidak Ada';

                        $results[$key] = [
                            'No' => count($results) + 1, 'Nama_Sampel' => $namaSampel,
                            'Tanggal_Produksi' => $tanggalProduksiFormatted,
                            'Tanggal' => $this->formatTanggalIndoLengkap($item->Tanggal_Pengujian), 'Analisa' => [],
                        ];
                    }
                    $results[$key]['Analisa'][] = [
                        'nama' => $namaKolom,
                        'nilai' => number_format((float)$item->Nilai_Rata_Rata, 2, '.', '')
                    ];
                }
            }
        }

        foreach ($results as &$row) {
            $analisaMap = collect($row['Analisa'])->pluck('nilai', 'nama');
            $filledAnalisa = [];
            foreach ($analisaHeaders as $header) {
                $filledAnalisa[] = [
                    'nama' => $header['nama'],
                    'nilai' => $analisaMap[$header['nama']] ?? 'Tidak Ada Data',
                ];
            }
            $row['Analisa'] = $filledAnalisa;
        }
        unset($row);

        if (empty($results)) {
            return response()->json([ 'success' => false, 'status' => 404, 'message' => "Data tidak ditemukan"], 404);
        }

        $collectionData = collect(array_values($results));

        // Hitung rata-rata
        $jumlahKolomAnalisa = count($analisaHeaders);
        $totalNilai = array_fill(0, $jumlahKolomAnalisa, 0);
        $jumlahDataValid = array_fill(0, $jumlahKolomAnalisa, 0);

        foreach ($collectionData as $row) {
            foreach ($row['Analisa'] as $index => $analisa) {
                if (is_numeric($analisa['nilai'])) {
                    $totalNilai[$index] += (float) $analisa['nilai'];
                    $jumlahDataValid[$index]++;
                }
            }
        }

        $rataRata = [];
        for ($i = 0; $i < $jumlahKolomAnalisa; $i++) {
            $kodeAnalisa = $analisaHeaders[$i]['kode'];
            if ($kodeAnalisa === 'MBLG-STR' || $jumlahDataValid[$i] === 0) {
                $rataRata[$i] = '-';
            } else {
                $rataRata[$i] = number_format($totalNilai[$i] / $jumlahDataValid[$i], 2, '.', '');
            }
        }

        // Ubah collection menjadi array datar untuk FromCollection
        $exportCollection = $collectionData->map(function ($row) {
            $flatRow = [
                $row['No'],
                $row['Nama_Sampel'],
                $row['Tanggal_Produksi'],
                $row['Tanggal'],
            ];
            $analisaValues = array_column($row['Analisa'], 'nilai');
            return array_merge($flatRow, $analisaValues);
        });

        // Format tanggal untuk judul
        $formattedStartDate = \Carbon\Carbon::parse($start)->isoFormat('D MMMM YYYY');
        $formattedEndDate = \Carbon\Carbon::parse($end)->isoFormat('D MMMM YYYY');

        // Panggil Export Class dengan semua data yang dibutuhkan
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\RekapSampelExcelExportV2(
                $exportCollection,
                $analisaHeaders,
                $rataRata,
                $formattedStartDate,
                $formattedEndDate
            ),
            'Laporan_Hasil_Analisa_' . now()->format('Ymd_His') . '.xlsx'
        );
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

}