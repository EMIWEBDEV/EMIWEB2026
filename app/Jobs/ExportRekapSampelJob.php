<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Vinkla\Hashids\Facades\Hashids;
use Carbon\Carbon;
use ZipArchive;

use App\Exports\RekapSampelExport;
use App\Exports\RekapSampelLabProduksiExport;
use App\Exports\ParticleSizeExport;

class ExportRekapSampelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;
    protected $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function handle()
    {
        $trackId = $this->payload['track_id'];

        Log::channel('export_job')->info('[EXPORT] handle() dipanggil', [
            'track_id'       => $trackId,
            'jenis_print'    => $this->payload['jenis_print'] ?? null,
            'format'         => $this->payload['format'] ?? null,
            'queue_conn'     => config('queue.default'),
            'env'            => app()->environment(),
            'php_version'    => PHP_VERSION,
        ]);

        try {
            $this->updateProgress($trackId, 5, 'processing', 'Menyiapkan parameter laporan...');

            // Pastikan direktori temp ada
            File::ensureDirectoryExists(storage_path('app/temp'));

            $format     = $this->payload['format'];
            $jenisPrint = $this->payload['jenis_print'];

            Log::channel('export_job')->info('[EXPORT] Mulai proses', [
                'track_id'    => $trackId,
                'jenis_print' => $jenisPrint,
                'format'      => $format,
            ]);

            if ($jenisPrint === 'psz') {
                $this->processParticleSize($trackId, $format);
            } elseif ($jenisPrint === 'ringkas') {
                $this->processRingkas($trackId, $format);
            } else {
                $this->processDefault($trackId, $format);
            }

            Log::channel('export_job')->info('[EXPORT] Proses selesai', ['track_id' => $trackId]);

        } catch (\Exception $e) {
            Log::channel('export_job')->error('[EXPORT] Exception di handle()', [
                'track_id' => $trackId,
                'message'  => $e->getMessage(),
                'file'     => $e->getFile(),
                'line'     => $e->getLine(),
                'trace'    => $e->getTraceAsString(),
            ]);
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $this->updateProgress($trackId, 0, 'failed', 'Gagal memproses laporan.', null, $e->getMessage());
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function updateProgress($trackId, $progress, $status, $message, $fileUrl = null, $errorMsg = null)
    {
        DB::table('N_EMI_LAB_Export_Tracking')->where('id', $trackId)->update([
            'progress'      => $progress,
            'status'        => $status,
            'message'       => $message,
            'file_url'      => $fileUrl,
            'error_message' => $errorMsg,
            'updated_at'    => Carbon::now(),
        ]);
    }

    /**
     * Upload file lokal ke GCS, hapus file lokal, simpan gcs_path ke tracking,
     * dan update progress ke completed dengan download URL.
     */
    private function finalizeWithGcs(string $trackId, string $localPath): void
    {
        $fileName = basename($localPath);
        $gcsPath  = 'exports/' . $fileName;

        Log::channel('export_job')->info('[EXPORT] Upload ke GCS', [
            'track_id'   => $trackId,
            'local_path' => $localPath,
            'gcs_path'   => $gcsPath,
            'file_size'  => file_exists($localPath) ? filesize($localPath) : 'FILE_NOT_FOUND',
        ]);

        if (!file_exists($localPath)) {
            throw new \Exception("File temp tidak ditemukan: {$localPath}");
        }

        Storage::disk('gcs')->put($gcsPath, file_get_contents($localPath));
        File::delete($localPath);

        Log::channel('export_job')->info('[EXPORT] Upload GCS berhasil', [
            'track_id' => $trackId,
            'gcs_path' => $gcsPath,
        ]);

        DB::table('N_EMI_LAB_Export_Tracking')->where('id', $trackId)->update([
            'file_path' => $gcsPath,
        ]);

        $downloadUrl = url('/api/v1/export-download/' . $trackId);
        $this->updateProgress($trackId, 100, 'completed', 'Selesai.', $downloadUrl);
    }

    private function getFilterMesinId()
    {
        $checkedIdMaster = $this->payload['Id_Master_Mesin'] ?? null;
        if ($checkedIdMaster && $checkedIdMaster !== 'all') {
            return Hashids::connection('custom')->decode($checkedIdMaster)[0] ?? null;
        }
        return null;
    }

    // =========================================================================
    // LOGIKA 1: PARTICLE SIZE (PSZ)
    // =========================================================================
    private function processParticleSize($trackId, $format)
    {
        $this->updateProgress($trackId, 20, 'processing', 'Mengambil data Particle Size...');

        $analysisIdRaw   = $this->payload['analysis'][0] ?? null;
        $flagPerhitungan = $this->payload['Flag_Perhitungan'][0] ?? null;
        $startDate       = $this->payload['startDate'];
        $endDate         = $this->payload['endDate'];

        $decodedId = Hashids::connection('custom')->decode($analysisIdRaw);
        if (empty($decodedId)) throw new \Exception('ID Analisa Tidak Valid');
        $id_analisa = $decodedId[0];

        $filterMesinId = $this->getFilterMesinId();

        $ujiSampelQuery = DB::table('N_EMI_LAB_Uji_Sampel as us')
            ->join('N_EMI_LAB_PO_Sampel as ps', 'us.No_Po_Sampel', '=', 'ps.No_Sampel')
            ->leftJoin('N_EMI_LAB_Perhitungan as p', 'p.id', '=', 'us.Id_Perhitungan')
            ->select(
                'us.No_Faktur', 'us.No_Po_Sampel', 'ps.No_Po', 'ps.No_Batch', 'ps.No_Split_Po',
                'us.Id_Mesin', 'ps.Kode_Barang', 'us.Tanggal as Tanggal_Pengujian',
                'us.Hasil as Hasil_Akhir_Analisa',
                DB::raw("ISNULL(p.Hasil_Perhitungan, 2) AS Pembulatan")
            )
            ->where('us.Id_Jenis_Analisa', $id_analisa)
            ->whereBetween('us.Tanggal', [$startDate, $endDate])
            ->where('us.Flag_Selesai', 'Y')
            ->whereNull('us.Status');

        if ($filterMesinId) $ujiSampelQuery->where('us.Id_Mesin', $filterMesinId);

        $ujiSampel = $ujiSampelQuery->get();
        if ($ujiSampel->isEmpty()) throw new \Exception('Data Particle Size tidak ditemukan.');

        $this->updateProgress($trackId, 40, 'processing', 'Menghitung Particle Size...');

        $kodeBarangIds = $ujiSampel->pluck('Kode_Barang')->unique()->filter();
        $idMesinIds    = $ujiSampel->pluck('Id_Mesin')->unique()->filter();
        $namaBarangMap = DB::table('N_EMI_View_Barang')->whereIn('Kode_Barang', $kodeBarangIds)->pluck('Nama', 'Kode_Barang');
        $namaMesinMap  = DB::table('EMI_Master_Mesin')->whereIn('Id_Master_Mesin', $idMesinIds)->pluck('Nama_Mesin', 'Id_Master_Mesin');

        $ujiSampel->each(function ($row) use ($namaBarangMap, $namaMesinMap) {
            $row->Nama_Sampel_Format = ($namaBarangMap[$row->Kode_Barang] ?? '-') . "-{$row->No_Po_Sampel}-" . ($namaMesinMap[$row->Id_Mesin] ?? '-');
        });

        $parameterRaw = DB::table('N_EMI_LAB_Uji_Sampel_Detail as usd')
            ->join('EMI_Quality_Control as qc', 'qc.Id_QC_Formula', '=', 'usd.Id_Quality_Control')
            ->whereIn('usd.No_Faktur_Uji_Sample', $ujiSampel->pluck('No_Faktur'))
            ->get(['usd.No_Faktur_Uji_Sample', 'usd.Value_Parameter as Hasil_Analisa', 'qc.Keterangan as nama_parameter'])
            ->groupBy('No_Faktur_Uji_Sample');

        $meshKeyMap = [4.0 => '4', 3.35 => '3.35', 2.0 => '2', 1.0 => '1', 850.0 => '850', 600.0 => '600', 250.0 => '250'];
        $meshMap    = $parameterRaw->flatten()->where('nama_parameter', 'UKURAN MESH')->pluck('Hasil_Analisa', 'No_Faktur_Uji_Sample');

        $ujiSampel->each(function ($sampel) use ($meshMap, $meshKeyMap) {
            $rawMesh           = (float) $meshMap->get($sampel->No_Faktur);
            $sampel->Ukuran_Mesh = $meshKeyMap[$rawMesh] ?? (string) $rawMesh;
        });

        $groupedSamples  = $ujiSampel->groupBy(fn($item) => $item->No_Split_Po . '|' . $item->Tanggal_Pengujian . '|' . $item->Id_Mesin);
        $processedReports = [];

        foreach ($groupedSamples as $group) {
            $particleMap = $group->pluck('Hasil_Akhir_Analisa', 'Ukuran_Mesh');
            $calc        = [];
            $calc['>4mm']        = (float) $particleMap->get('4', 0);
            $calc['>3.35mm']     = $calc['>4mm'] + (float) $particleMap->get('3.35', 0);
            $calc['<3.35mm']     = 100 - $calc['>3.35mm'];
            $calc['2-3.35mm']    = (float) $particleMap->get('2', 0);
            $calc['1-2mm']       = (float) $particleMap->get('1', 0);
            $calc['0.850-1mm']   = (float) $particleMap->get('850', 0);
            $calc['0.6-0.850mm'] = (float) $particleMap->get('600', 0);
            $calc['0.25-0.6mm']  = (float) $particleMap->get('250', 0);
            $calc['<0.25mm']     = $calc['<3.35mm'] - ($calc['2-3.35mm'] + $calc['1-2mm'] + $calc['0.850-1mm'] + $calc['0.6-0.850mm'] + $calc['0.25-0.6mm']);

            $finalCalc   = collect($calc)->map(fn($v) => number_format($v, 2, '.', ''))->all();
            $firstSample = $group->first();
            $namaProduk  = $namaBarangMap[$firstSample->Kode_Barang] ?? 'N/A';

            $processedReports[] = [
                'info' => [
                    'nama_sampel'          => "$namaProduk - " . ($namaMesinMap[$firstSample->Id_Mesin] ?? 'N/A'),
                    'tanggal_produksi_1'   => Carbon::parse($firstSample->Tanggal_Pengujian)->isoFormat('D MMMM YYYY'),
                    'produk'               => "$namaProduk (" . $firstSample->No_Split_Po . ")",
                ],
                'values' => $finalCalc,
            ];
        }

        $this->updateProgress($trackId, 80, 'processing', 'Merender file laporan...');

        $dateStr  = Carbon::parse($startDate)->format('Ymd') . '-' . Carbon::parse($endDate)->format('Ymd');
        $ext      = $format === 'excell' ? 'xlsx' : 'pdf';
        $fileName = "Rekap_Particle_Size_{$dateStr}.{$ext}";
        $tempPath = storage_path('app/temp/' . $fileName);

        if ($format === 'pdf') {
            $logoBase64 = 'data:image/png;base64,' . base64_encode(File::get(public_path('assets/images/thumb-excel.png')));
            PDF::loadView('pdf.rekap-particlesize', ['reports' => $processedReports, 'logoBase64' => $logoBase64])
                ->setPaper('a4', 'landscape')
                ->save($tempPath);
        } else {
            Excel::store(new ParticleSizeExport($processedReports), 'temp/' . $fileName, 'local');
        }

        $this->finalizeWithGcs($trackId, $tempPath);
    }

    // =========================================================================
    // LOGIKA 2: RINGKAS (V2)
    // =========================================================================
    private function processRingkas($trackId, $format)
    {
        $this->updateProgress($trackId, 15, 'processing', 'Mengambil data Ringkas...');

        $startDate     = $this->payload['startDate'];
        $endDate       = $this->payload['endDate'];
        $filterMesinId = $this->getFilterMesinId();

        $decodedIds = [];
        $flagMap    = [];
        foreach ($this->payload['analysis'] as $index => $encoded) {
            $id = Hashids::connection('custom')->decode($encoded)[0] ?? null;
            if ($id) {
                $decodedIds[]   = $id;
                $flagMap[$id]   = $this->payload['Flag_Perhitungan'][$index] ?? null;
            }
        }

        if (empty($decodedIds)) throw new \Exception('Data analisa tidak valid');

        $jenisAnalisaAll = DB::table('N_EMI_LAB_Jenis_Analisa')->whereIn('id', $decodedIds)->get()->keyBy('id');
        $analisaHeaders  = [];
        foreach ($decodedIds as $id) {
            if (isset($jenisAnalisaAll[$id])) {
                $analisaHeaders[] = ['id' => $id, 'nama' => $jenisAnalisaAll[$id]->Jenis_Analisa, 'kode' => $jenisAnalisaAll[$id]->Kode_Analisa];
            }
        }

        $standarRentangRaw = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
            ->whereIn('Id_Jenis_Analisa', $decodedIds)->where('Flag_Aktif', 'Y')->get();
        $standarRentangMap = [];
        foreach ($standarRentangRaw as $row) {
            $standarRentangMap[$row->Id_Jenis_Analisa][(string) ((float) $row->Nilai_Kriteria)] = $row->Keterangan_Kriteria;
        }

        $query = DB::table('N_EMI_LAB_Uji_Sampel as us')
            ->join('N_EMI_LAB_PO_Sampel as ps', 'us.No_Po_Sampel', '=', 'ps.No_Sampel')
            ->leftJoin('N_EMI_LAB_Perhitungan as p', 'p.id', '=', 'us.Id_Perhitungan')
            ->select('us.Id_Jenis_Analisa', 'ps.No_Po', 'ps.No_Split_Po', 'ps.Kode_Barang', 'us.Id_Mesin',
                'us.Tanggal as Tanggal_Pengujian', 'us.Hasil', DB::raw("COALESCE(p.Hasil_Perhitungan, 2) as Pembulatan"))
            ->whereIn('us.Id_Jenis_Analisa', $decodedIds)
            ->whereBetween('us.Tanggal', [$startDate, $endDate])
            ->where('us.Flag_Selesai', 'Y')->whereNull('us.Status');

        if ($filterMesinId) $query->where('us.Id_Mesin', $filterMesinId);
        $rawData = $query->orderBy('us.Tanggal', 'asc')->orderBy('ps.No_Split_Po', 'asc')->get();

        if ($rawData->isEmpty()) throw new \Exception('Data tidak ditemukan');

        $this->updateProgress($trackId, 45, 'processing', 'Memformat & mengelompokkan data...');

        $globalTotalNilai     = array_fill(0, count($analisaHeaders), 0);
        $globalJumlahDataValid = array_fill(0, count($analisaHeaders), 0);

        $refBarang = DB::table('N_EMI_View_Barang')->whereIn('Kode_Barang', $rawData->pluck('Kode_Barang')->unique())->pluck('Nama', 'Kode_Barang');
        $refMesin  = DB::table('EMI_Master_Mesin')->whereIn('Id_Master_Mesin', $rawData->pluck('Id_Mesin')->unique())->pluck('Nama_Mesin', 'Id_Master_Mesin');
        $refOrder  = DB::table('N_EMI_View_Order_Produksi')->whereIn('No_Faktur', $rawData->pluck('No_Po')->unique())->pluck('Tanggal', 'No_Faktur');

        $groupedData = [];
        foreach ($rawData as $item) {
            $key = $item->No_Split_Po . '|' . $item->Tanggal_Pengujian . '|' . $item->Id_Mesin;
            if (!isset($groupedData[$key])) {
                $groupedData[$key] = [
                    'No'               => 0,
                    'Nama_Sampel'      => ($refBarang[$item->Kode_Barang] ?? 'N/A') . '-' . $item->No_Split_Po . '-' . ($refMesin[$item->Id_Mesin] ?? 'N/A'),
                    'Tanggal_Produksi' => isset($refOrder[$item->No_Po]) ? Carbon::parse($refOrder[$item->No_Po])->format('d-M-Y') : 'Tidak Ada',
                    'Tanggal'          => Carbon::parse($item->Tanggal_Pengujian)->format('d-M-Y'),
                    'Raw_Analisa'      => [],
                ];
            }

            $idAnalisa     = $item->Id_Jenis_Analisa;
            $isPerhitungan = ($flagMap[$idAnalisa] ?? null) === 'Y';
            $finalValue    = $item->Hasil;

            if (!$isPerhitungan && isset($standarRentangMap[$idAnalisa][(string) ((float) $item->Hasil)])) {
                $finalValue = $standarRentangMap[$idAnalisa][(string) ((float) $item->Hasil)];
            } elseif ($isPerhitungan) {
                $finalValue = number_format((float) $item->Hasil, $item->Pembulatan, '.', '');
            }
            $groupedData[$key]['Raw_Analisa'][$idAnalisa] = $finalValue;
        }

        $finalCollection = [];
        $no = 1;
        foreach ($groupedData as $row) {
            $row['No']      = $no++;
            $analisaCells   = [];
            foreach ($analisaHeaders as $idx => $header) {
                $val = $row['Raw_Analisa'][$header['id']] ?? null;
                if ($val !== null && is_numeric($val)) {
                    $globalTotalNilai[$idx] += (float) $val;
                    $globalJumlahDataValid[$idx]++;
                }

                $displayValue = $val ?? 'Tidak Ada Data';
                if (is_numeric($val) && (float) $val == 0 && $header['kode'] === 'MBLG-STR') $displayValue = '-';

                if ($format === 'pdf') {
                    $analisaCells[] = ['nama' => $header['nama'], 'kode' => $header['kode'], 'nilai' => $displayValue, 'is_foto' => false, 'foto_base64' => null];
                } else {
                    $analisaCells[] = $displayValue;
                }
            }
            unset($row['Raw_Analisa']);
            if ($format === 'pdf') {
                $row['Analisa']   = $analisaCells;
                $finalCollection[] = $row;
            } else {
                $finalCollection[] = array_merge(array_values($row), $analisaCells);
            }
        }

        $globalRataRataValues = [];
        foreach ($analisaHeaders as $idx => $header) {
            if ($header['kode'] === 'MBLG-STR' || $globalJumlahDataValid[$idx] === 0) {
                $globalRataRataValues[] = '-';
            } else {
                $globalRataRataValues[] = number_format($globalTotalNilai[$idx] / $globalJumlahDataValid[$idx], 2, '.', '');
            }
        }

        $this->updateProgress($trackId, 75, 'processing', 'Merender file laporan...');

        if ($format === 'pdf') {
            $chunks      = array_chunk($finalCollection, 1000);
            $totalChunks = count($chunks);
            $tempFiles   = [];

            foreach ($chunks as $idx => $chunk) {
                $partName  = 'Laporan_Part_' . ($idx + 1) . '_' . time() . '.pdf';
                $partPath  = storage_path('app/temp/' . $partName);

                PDF::loadView('pdf.rekap-sampel-laporan', [
                    'collection' => $chunk,
                    'headers'    => $analisaHeaders,
                    'rataRata'   => ($idx === $totalChunks - 1) ? $globalRataRataValues : [],
                    'logoPath'   => public_path('assets/images/thumb-excel.png'),
                ])->setPaper('a4', 'landscape')->save($partPath);

                $tempFiles[] = $partPath;
            }

            if ($totalChunks > 1) {
                $zipName = 'Laporan_Full_' . now()->format('Ymd_His') . '.zip';
                $zipPath = storage_path('app/temp/' . $zipName);
                $zip     = new ZipArchive;
                if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
                    foreach ($tempFiles as $f) {
                        $zip->addFile($f, basename($f));
                    }
                    $zip->close();
                }
                foreach ($tempFiles as $f) File::delete($f);
                $this->finalizeWithGcs($trackId, $zipPath);
            } else {
                $this->finalizeWithGcs($trackId, $tempFiles[0]);
            }
        } else {
            $excelName = 'Laporan_Hasil_Analisa_' . now()->format('Ymd_His') . '.xlsx';
            $tempPath  = storage_path('app/temp/' . $excelName);
            Excel::store(
                new RekapSampelLabProduksiExport(
                    collect($finalCollection), $analisaHeaders, $globalRataRataValues,
                    Carbon::parse($startDate)->isoFormat('D MMMM YYYY'),
                    Carbon::parse($endDate)->isoFormat('D MMMM YYYY')
                ),
                'temp/' . $excelName,
                'local'
            );
            $this->finalizeWithGcs($trackId, $tempPath);
        }
    }

    // =========================================================================
    // LOGIKA 3: DEFAULT (DETAIL)
    // =========================================================================
    private function processDefault($trackId, $format)
    {
        $this->updateProgress($trackId, 15, 'processing', 'Mengambil data Detail...');

        $startDate     = $this->payload['startDate'];
        $endDate       = $this->payload['endDate'];
        $filterMesinId = $this->getFilterMesinId();

        $analysisIds    = [];
        $targetAnalyses = [];
        foreach ($this->payload['analysis'] as $index => $hashedIdAnalisa) {
            $decodedId = Hashids::connection('custom')->decode($hashedIdAnalisa)[0] ?? null;
            if ($decodedId) {
                $analysisIds[]    = $decodedId;
                $targetAnalyses[] = ['id' => $decodedId, 'index' => $index];
            }
        }

        if (empty($analysisIds)) throw new \Exception('Tidak ada analisa valid.');

        $jenisAnalisaMap = DB::table('N_EMI_LAB_Jenis_Analisa')->whereIn('id', $analysisIds)->get()->keyBy('id');
        $parametersMap   = DB::table('N_EMI_LAB_Binding_jenis_analisa as b')
            ->join('EMI_Quality_Control as q', 'q.Id_QC_Formula', '=', 'b.Id_Quality_Control')
            ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ja.id', '=', 'b.Id_Jenis_Analisa')
            ->leftJoin('EMI_Kategori_Komponen as kk', 'kk.Id_Kategori_Komponen', '=', 'q.Id_Kategori_Komponen')
            ->whereIn('b.Id_Jenis_Analisa', $analysisIds)
            ->select('b.id', 'b.Id_Quality_Control as id_qc', 'b.Id_Jenis_Analisa', 'q.Keterangan as nama_parameter',
                'kk.Keterangan AS type_inputan', 'q.Satuan as satuan', 'q.Kode_Uji as kode_uji',
                'ja.Kode_Analisa as kode_analisa', 'ja.Jenis_Analisa as jenis_analisa', 'ja.Flag_Perhitungan as flag_perhitungan')
            ->get()->groupBy('Id_Jenis_Analisa');

        $rumusMap   = DB::table('N_EMI_LAB_Perhitungan')->whereIn('Id_Jenis_Analisa', $analysisIds)
            ->select('Id', 'Id_Jenis_Analisa', 'Rumus as rumus', 'Nama_Kolom as nama_kolom', 'Hasil_Perhitungan as digit')
            ->get()->groupBy('Id_Jenis_Analisa');
        $nonCalcMap = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
            ->whereIn('Id_Jenis_Analisa', $analysisIds)->where('Flag_Aktif', 'Y')
            ->select('Id_Jenis_Analisa', 'Nilai_Kriteria', 'Keterangan_Kriteria')->get()->groupBy('Id_Jenis_Analisa');

        $generatedTempFiles = [];

        foreach ($targetAnalyses as $target) {
            $id_analisa          = $target['id'];
            $index               = $target['index'];
            $getNamaJenisAnalisa = $jenisAnalisaMap->get($id_analisa);
            if (!$getNamaJenisAnalisa) continue;

            $getParameter = $parametersMap->get($id_analisa);
            if (!$getParameter || $getParameter->isEmpty()) continue;

            $isPerhitungan       = ($this->payload['Flag_Perhitungan'][$index] ?? null) === 'Y';
            $getDataRumus        = $isPerhitungan ? ($rumusMap->get($id_analisa) ?? collect([])) : null;
            $currentNonCalcLookup = [];
            if (!$isPerhitungan && isset($nonCalcMap[$id_analisa])) {
                foreach ($nonCalcMap[$id_analisa] as $item) {
                    $currentNonCalcLookup[(string) ((float) $item->Nilai_Kriteria)] = $item->Keterangan_Kriteria;
                }
            }

            $queryHeader = DB::table('N_EMI_LAB_Uji_Sampel as US')
                ->join('N_EMI_LAB_PO_Sampel as PO', 'US.No_Po_Sampel', '=', 'PO.No_Sampel')
                ->join('EMI_Master_Mesin as M', 'PO.Id_Mesin', '=', 'M.Id_Master_Mesin')
                ->leftJoin('N_EMI_LAB_Perhitungan as C', fn($join) => $join->on('C.id', '=', 'US.Id_Perhitungan')->on('C.Kode_Perusahaan', '=', 'US.Kode_Perusahaan'))
                ->select('US.No_Faktur', 'US.No_Po_Sampel', 'US.No_Fak_Sub_Po', 'US.Id_Jenis_Analisa', 'US.Id_Mesin',
                    'US.Tanggal as Tanggal_Pengujian', 'US.Hasil as Hasil_Akhir_Analisa', 'PO.No_Po', 'PO.No_Split_Po',
                    'PO.Flag_Selesai', DB::raw("ISNULL(C.Hasil_Perhitungan, 0) AS Pembulatan"))
                ->where('US.Id_Jenis_Analisa', $id_analisa)
                ->where('US.Flag_Selesai', 'Y')
                ->whereNull('US.Status')
                ->whereBetween('US.Tanggal', [$startDate, $endDate]);

            if ($filterMesinId) $queryHeader->where('US.Id_Mesin', $filterMesinId);
            $ujiSampel = $queryHeader->get();
            if ($ujiSampel->isEmpty()) continue;

            $parameterRaw = DB::table('N_EMI_LAB_Uji_Sampel_Detail as D')
                ->join('N_EMI_LAB_Uji_Sampel as US', 'D.No_Faktur_Uji_Sample', '=', 'US.No_Faktur')
                ->select('D.Id_Uji_Sample_Detail', 'D.No_Faktur_Uji_Sample', 'D.Id_Quality_Control',
                    'D.Value_Parameter as Hasil_Analisa', 'D.Tanggal as Tanggal_Parameter_Analisa', 'D.Jam as Jam_Parameter_Analisa')
                ->where('US.Id_Jenis_Analisa', $id_analisa)->where('US.Flag_Selesai', 'Y')->whereNull('US.Status')
                ->whereBetween('US.Tanggal', [$startDate, $endDate])
                ->when($filterMesinId, fn($q) => $q->where('US.Id_Mesin', $filterMesinId))
                ->get()->groupBy('No_Faktur_Uji_Sample');

            $result = [];
            foreach ($ujiSampel as $itemObj) {
                $item      = (array) $itemObj;
                $cleanVal  = (string) ((float) $item['Hasil_Akhir_Analisa']);
                $item['Hasil_Akhir_Analisa'] = (!$isPerhitungan && isset($currentNonCalcLookup[$cleanVal]))
                    ? $currentNonCalcLookup[$cleanVal]
                    : number_format((float) $item['Hasil_Akhir_Analisa'], $item['Pembulatan'], '.', '');

                $rawParams       = $parameterRaw->get($item['No_Faktur']);
                $processedParams = [];
                if ($rawParams) {
                    foreach ($rawParams as $param) {
                        $cleanParamVal    = (string) ((float) $param->Hasil_Analisa);
                        $finalHasil       = (!$isPerhitungan && isset($currentNonCalcLookup[$cleanParamVal]))
                            ? $currentNonCalcLookup[$cleanParamVal]
                            : round(floatval($param->Hasil_Analisa), 4);
                        $processedParams[] = [
                            'Hasil_Analisa'               => $finalHasil,
                            'Tanggal_Parameter_Analisa'   => $param->Tanggal_Parameter_Analisa,
                            'Jam_Parameter_Analisa'       => $param->Jam_Parameter_Analisa,
                        ];
                    }
                }
                $item['parameter'] = $processedParams;
                $result[]          = $item;
            }

            $namaAnalisa     = ucwords(strtolower($getNamaJenisAnalisa->Jenis_Analisa));
            $safeNamaAnalisa = preg_replace('/[^A-Za-z0-9\-]/', '_', $namaAnalisa);

            if ($format === 'excell') {
                $paramForExport = $getParameter->map(fn($p) => [
                    'id'               => Hashids::connection('custom')->encode($p->id),
                    'id_qc'            => Hashids::connection('custom')->encode($p->id_qc),
                    'nama_parameter'   => $p->nama_parameter,
                    'type_inputan'     => $p->type_inputan,
                    'satuan'           => $p->satuan,
                    'kode_uji'         => $p->kode_uji,
                    'kode_analisa'     => $p->kode_analisa,
                    'jenis_analisa'    => $p->jenis_analisa,
                    'flag_perhitungan' => $p->flag_perhitungan,
                ])->toArray();

                $rumusForExport = ($getDataRumus && $getDataRumus->isNotEmpty())
                    ? $getDataRumus->map(fn($r) => [
                        'id'               => Hashids::connection('custom')->encode($r->Id),
                        'id_jenis_analisa' => Hashids::connection('custom')->encode($r->Id_Jenis_Analisa),
                        'rumus'            => $r->rumus,
                        'nama_kolom'       => $r->nama_kolom,
                        'digit'            => $r->digit,
                    ])->toArray()
                    : [];

                $excelName = 'Rekap_' . $safeNamaAnalisa . '_' . time() . '.xlsx';
                $tempPath  = storage_path('app/temp/' . $excelName);
                Excel::store(new RekapSampelExport($result, $paramForExport, $rumusForExport, $namaAnalisa), 'temp/' . $excelName, 'local');
                $generatedTempFiles[] = $tempPath;
            } else {
                $rumusCount = ($isPerhitungan && $getDataRumus) ? $getDataRumus->count() : 0;

                $headings = ['NO', 'TANGGAL ANALISA', 'NO PO', 'NO SPLIT PO', 'NAMA SAMPEL'];
                foreach ($getParameter as $param) $headings[] = strtoupper($param->nama_parameter);
                if ($isPerhitungan && $getDataRumus) {
                    foreach ($getDataRumus as $rumus) $headings[] = strtoupper($rumus->nama_kolom);
                }

                $totalNilai     = array_fill(0, max($rumusCount, 1), 0);
                $jumlahValid    = array_fill(0, max($rumusCount, 1), 0);
                $flatCollection = [];

                foreach ($result as $no => $item) {
                    $row = [
                        'no'          => $no + 1,
                        'tanggal'     => date('d-m-Y', strtotime($item['Tanggal_Pengujian'])),
                        'no_po'       => $item['No_Po'],
                        'no_split_po' => $item['No_Split_Po'],
                        'nama_sampel' => $item['No_Po_Sampel'],
                    ];

                    foreach (($item['parameter'] ?? []) as $param) $row[] = $param['Hasil_Analisa'];

                    if ($isPerhitungan) {
                        $hasilAkhir = $item['Hasil_Akhir_Analisa'];
                        $row[]      = $hasilAkhir;
                        if (is_numeric($hasilAkhir)) {
                            $totalNilai[0]  += (float) $hasilAkhir;
                            $jumlahValid[0] += 1;
                        }
                    }
                    $flatCollection[] = $row;
                }

                $rataRata = [];
                if ($isPerhitungan) {
                    $pembulatan = $result[0]['Pembulatan'] ?? 2;
                    $rataRata[] = $jumlahValid[0] > 0
                        ? number_format($totalNilai[0] / $jumlahValid[0], $pembulatan, '.', '')
                        : '-';
                }

                $pdfName  = 'Rekap_' . $safeNamaAnalisa . '_' . time() . '.pdf';
                $pdfPath  = storage_path('app/temp/' . $pdfName);
                PDF::loadView('pdf.rekap-sampel', [
                    'namaAnalisa'       => $namaAnalisa,
                    'logoPath'          => public_path('assets/images/thumb-excel.png'),
                    'headings'          => $headings,
                    'collection'        => $flatCollection,
                    'apakahPerhitungan' => $isPerhitungan,
                    'rataRata'          => $rataRata,
                    'rumusCount'        => $rumusCount ?: 1,
                ])->setPaper('a4', 'landscape')->save($pdfPath);
                $generatedTempFiles[] = $pdfPath;
            }
        }

        if (empty($generatedTempFiles)) throw new \Exception('Tidak ada data yang diproses.');

        $this->updateProgress($trackId, 90, 'processing', 'Mengunggah ke storage...');

        if (count($generatedTempFiles) === 1) {
            $this->finalizeWithGcs($trackId, $generatedTempFiles[0]);
        } else {
            $zipName = 'Rekap_Sampel_' . date('Ymd_His') . '.zip';
            $zipPath = storage_path('app/temp/' . $zipName);
            $zip     = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
                foreach ($generatedTempFiles as $file) $zip->addFile($file, basename($file));
                $zip->close();
            }
            foreach ($generatedTempFiles as $file) File::delete($file);
            $this->finalizeWithGcs($trackId, $zipPath);
        }
    }
}
