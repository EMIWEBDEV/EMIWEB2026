<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class DaftarAnalisaKurangExport implements FromArray, WithEvents, WithTitle
{
    protected string $startDate;
    protected string $endDate;
    protected ?string $type;
    protected Collection $rows;

    // Kode analisa yang tidak masuk standar mutu
    private const KODE_DIKECUALIKAN = ['HOMOGENITAS', 'MBLG-STR', 'PSZ'];

    public function __construct(string $startDate, string $endDate, ?string $type = null)
    {
        $this->startDate = $startDate;
        $this->endDate   = $endDate;
        $this->type      = $type;
        $this->rows      = $this->fetchRows();
    }

    public function array(): array
    {
        return [];
    }

    public function title(): string
    {
        return 'Daftar Analisa Kurang';
    }

    protected function fetchRows(): Collection
    {
        $poQuery = DB::table('N_EMI_LAB_PO_Sampel as po')
            ->join('N_EMI_View_Barang as brg', 'po.Kode_Barang', '=', 'brg.Kode_Barang')
            ->select(
                'po.No_Sampel', 'po.No_Po', 'po.No_Split_Po', 'po.No_Batch',
                'po.Kode_Barang', 'brg.Nama as Nama_Barang',
                'po.Id_Mesin', 'po.Flag_Trial_Produksi', 'po.Tanggal'
            )
            ->whereBetween('po.Tanggal', [$this->startDate, $this->endDate])
            ->whereNull('po.Status')
            ->whereNull('po.Flag_Selesai')
            ->orderBy('po.Tanggal')
            ->orderBy('po.No_Po');

        if ($this->type === 'trial') {
            $poQuery->where('po.Flag_Trial_Produksi', 'Y');
        } elseif ($this->type === 'produksi') {
            $poQuery->whereNull('po.Flag_Trial_Produksi');
        }

        $poList = $poQuery->get();

        if ($poList->isEmpty()) {
            return collect([]);
        }

        $kodeBarangList = $poList->pluck('Kode_Barang')->unique()->values()->toArray();
        $mesinList      = $poList->pluck('Id_Mesin')->unique()->values()->toArray();
        $noSampelList   = $poList->pluck('No_Sampel')->toArray();

        // Standar analisa per Kode_Barang + Id_Mesin
        // Chunked by Kode_Barang to avoid SQL Server's 2100-parameter limit
        $standardAnalisaRaw = collect([]);
        foreach (array_chunk($kodeBarangList, 1000) as $kodeChunk) {
            $chunkResult = DB::table('N_EMI_LAB_Barang_Analisa as ba')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ba.Id_Jenis_Analisa', '=', 'ja.id')
                ->whereIn('ba.Kode_Barang', $kodeChunk)
                ->whereIn('ba.Id_Master_Mesin', $mesinList)
                ->where('ba.Flag_Aktif', 'Y')
                ->where('ba.Kode_Role', 'LAB')
                ->where('ja.Kode_Role', 'LAB')
                ->whereNotIn('ja.Kode_Analisa', self::KODE_DIKECUALIKAN)
                ->select('ba.Kode_Barang', 'ba.Id_Master_Mesin', 'ba.Id_Jenis_Analisa', 'ja.Jenis_Analisa')
                ->distinct()
                ->get();
            $standardAnalisaRaw = $standardAnalisaRaw->concat($chunkResult);
        }
        $standardAnalisa = $standardAnalisaRaw->groupBy(fn($i) => $i->Kode_Barang . '|' . $i->Id_Master_Mesin);

        // Analisa yang sudah dikerjakan per No_Sampel
        // Chunked to stay within SQL Server's 2100-parameter limit
        $actualAnalisaRaw = collect([]);
        foreach (array_chunk($noSampelList, 2000) as $chunk) {
            $chunkResult = DB::table('N_EMI_LAB_Uji_Sampel as uji')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'uji.Id_Jenis_Analisa', '=', 'ja.id')
                ->whereIn('uji.No_Po_Sampel', $chunk)
                ->whereNull('uji.Status')
                ->where('uji.Flag_Selesai', 'Y')
                ->where('uji.Status_Keputusan_Sampel', 'terima')
                ->where(function ($q) {
                    $q->where('uji.Flag_Resampling', '!=', 'Y')->orWhereNull('uji.Flag_Resampling');
                })
                ->whereNotIn('ja.Kode_Analisa', self::KODE_DIKECUALIKAN)
                ->select('uji.No_Po_Sampel', 'uji.Id_Jenis_Analisa', 'ja.Jenis_Analisa')
                ->distinct()
                ->get();
            $actualAnalisaRaw = $actualAnalisaRaw->concat($chunkResult);
        }
        $actualAnalisa = $actualAnalisaRaw->groupBy('No_Po_Sampel');

        $rows = collect([]);
        $no   = 1;

        foreach ($poList as $po) {
            $key        = $po->Kode_Barang . '|' . $po->Id_Mesin;
            $stdList    = $standardAnalisa->get($key, collect());
            $actualList = $actualAnalisa->get($po->No_Sampel, collect());

            if ($actualList->isEmpty()) {
                continue;
            }

            $stdIds     = $stdList->pluck('Id_Jenis_Analisa')->unique()->toArray();
            $actualIds  = $actualList->pluck('Id_Jenis_Analisa')->unique()->toArray();
            $missingIds = array_diff($stdIds, $actualIds);

            if (empty($missingIds)) {
                continue;
            }

            $missingNames   = $stdList->whereIn('Id_Jenis_Analisa', $missingIds)->pluck('Jenis_Analisa')->values();
            $availableNames = $actualList->pluck('Jenis_Analisa')->values();

            $rows->push([
                'no'               => $no++,
                'no_po'            => $po->No_Po,
                'no_split_po'      => $po->No_Split_Po,
                'no_sampel'        => $po->No_Sampel,
                'no_batch'         => $po->No_Batch ?? '-',
                'kode_barang'      => $po->Kode_Barang,
                'nama_barang'      => $po->Nama_Barang,
                'tipe'             => $po->Flag_Trial_Produksi === 'Y' ? 'Trial Produksi' : 'Produksi',
                'tanggal'          => $po->Tanggal ? date('d/m/Y', strtotime($po->Tanggal)) : '-',
                'total_standar'    => count($stdIds),
                'total_tersedia'   => count($actualIds),
                'total_kurang'     => count($missingIds),
                'analisa_tersedia' => $availableNames->isEmpty() ? '-' : $availableNames->implode("\n"),
                'analisa_kurang'   => $missingNames->implode("\n"),
            ]);
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet    = $event->sheet->getDelegate();
                $rows     = $this->rows;
                $rowCount = $rows->count();

                // ── Lebar kolom ──────────────────────────────────────────
                $sheet->getColumnDimension('A')->setWidth(5);
                $sheet->getColumnDimension('B')->setWidth(20);
                $sheet->getColumnDimension('C')->setWidth(22);
                $sheet->getColumnDimension('D')->setWidth(17);
                $sheet->getColumnDimension('E')->setWidth(20);
                $sheet->getColumnDimension('F')->setWidth(17);
                $sheet->getColumnDimension('G')->setWidth(34);
                $sheet->getColumnDimension('H')->setWidth(14);
                $sheet->getColumnDimension('I')->setWidth(15);
                $sheet->getColumnDimension('J')->setWidth(13);
                $sheet->getColumnDimension('K')->setWidth(13);
                $sheet->getColumnDimension('L')->setWidth(13);
                $sheet->getColumnDimension('M')->setWidth(46);
                $sheet->getColumnDimension('N')->setWidth(46);

                // ── Header dokumen ────────────────────────────────────────
                $typeLabel = match ($this->type) {
                    'trial'    => 'Trial Produksi',
                    'produksi' => 'Produksi',
                    default    => 'Produksi & Trial Produksi',
                };

                $headerInfo = [
                    1 => ['text' => 'PT. EVO NUSA BERSAUDARA',                                        'size' => 14, 'bold' => true,  'height' => 26],
                    2 => ['text' => 'LAPORAN DAFTAR ANALISA KURANG',                                   'size' => 13, 'bold' => true,  'height' => 22],
                    3 => ['text' => 'Jenis Produksi  : ' . $typeLabel,                                 'size' => 10, 'bold' => false, 'height' => 16],
                    4 => ['text' => 'Periode           : ' . date('d/m/Y', strtotime($this->startDate)) . ' s.d. ' . date('d/m/Y', strtotime($this->endDate)), 'size' => 10, 'bold' => false, 'height' => 16],
                    5 => ['text' => 'Tanggal Cetak  : ' . date('d/m/Y H:i:s'),                        'size' => 10, 'bold' => false, 'height' => 16],
                ];

                foreach ($headerInfo as $row => $info) {
                    $sheet->mergeCells("A{$row}:N{$row}");
                    $sheet->setCellValue("A{$row}", $info['text']);
                    $sheet->getStyle("A{$row}")->applyFromArray([
                        'font'      => ['bold' => $info['bold'], 'size' => $info['size'], 'color' => ['rgb' => '1F3864']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getRowDimension($row)->setRowHeight($info['height']);
                }

                // Row 6: separator kosong
                $sheet->getRowDimension(6)->setRowHeight(6);

                // ── Header tabel (baris 7) ────────────────────────────────
                $headers = [
                    'A' => 'No',
                    'B' => 'No. PO',
                    'C' => 'No. Split PO',
                    'D' => 'No. Sampel',
                    'E' => 'No. Batch',
                    'F' => 'Kode Barang',
                    'G' => 'Nama Barang',
                    'H' => 'Tipe',
                    'I' => 'Tgl. Registrasi',
                    'J' => 'Total Standar',
                    'K' => 'Total Tersedia',
                    'L' => 'Total Kurang',
                    'M' => 'Analisa Tersedia',
                    'N' => 'Analisa Kurang',
                ];

                foreach ($headers as $col => $label) {
                    $sheet->setCellValue("{$col}7", $label);
                }

                $sheet->getRowDimension(7)->setRowHeight(34);
                $sheet->getStyle('A7:N7')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F3864']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                        'wrapText'   => true,
                    ],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '8EA9C1']]],
                ]);

                // ── Baris data ────────────────────────────────────────────
                if ($rowCount === 0) {
                    $sheet->mergeCells('A8:N8');
                    $sheet->setCellValue('A8', 'Tidak ada data analisa yang kurang pada periode yang dipilih.');
                    $sheet->getStyle('A8:N8')->applyFromArray([
                        'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '808080']],
                        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF2CC']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9D9D9']]],
                    ]);
                    $sheet->getRowDimension(8)->setRowHeight(28);
                } else {
                    $rowNum = 8;
                    foreach ($rows as $row) {
                        $bgColor = ($rowNum % 2 === 0) ? 'EBF3FB' : 'FFFFFF';

                        $values = [
                            'A' => $row['no'],
                            'B' => $row['no_po'],
                            'C' => $row['no_split_po'],
                            'D' => $row['no_sampel'],
                            'E' => $row['no_batch'],
                            'F' => $row['kode_barang'],
                            'G' => $row['nama_barang'],
                            'H' => $row['tipe'],
                            'I' => $row['tanggal'],
                            'J' => $row['total_standar'],
                            'K' => $row['total_tersedia'],
                            'L' => $row['total_kurang'],
                            'M' => $row['analisa_tersedia'],
                            'N' => $row['analisa_kurang'],
                        ];

                        foreach ($values as $col => $val) {
                            $sheet->setCellValue("{$col}{$rowNum}", $val);
                        }

                        // Style dasar seluruh baris
                        $sheet->getStyle("A{$rowNum}:N{$rowNum}")->applyFromArray([
                            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
                            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
                            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9D9D9']]],
                            'font'      => ['size' => 9],
                        ]);

                        // Kolom center
                        foreach (['A', 'H', 'I', 'J', 'K'] as $c) {
                            $sheet->getStyle("{$c}{$rowNum}")->getAlignment()
                                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        }

                        // Kolom Total Kurang — merah
                        $sheet->getStyle("L{$rowNum}")->applyFromArray([
                            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'C00000']],
                            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFE7E7']],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_TOP],
                        ]);

                        // Kolom Analisa Kurang — teks merah
                        $sheet->getStyle("N{$rowNum}")->applyFromArray([
                            'font' => ['size' => 9, 'color' => ['rgb' => 'C00000']],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF0F0']],
                        ]);

                        // Tipe Trial Produksi — biru
                        if ($row['tipe'] === 'Trial Produksi') {
                            $sheet->getStyle("H{$rowNum}")->applyFromArray([
                                'font' => ['bold' => true, 'color' => ['rgb' => '0070C0']],
                            ]);
                        }

                        $sheet->getRowDimension($rowNum)->setRowHeight(-1);
                        $rowNum++;
                    }

                    // ── Baris ringkasan ───────────────────────────────────
                    $summaryRow = $rowNum + 1;
                    $sheet->mergeCells("A{$summaryRow}:K{$summaryRow}");
                    $sheet->setCellValue("A{$summaryRow}", 'Total Sampel dengan Analisa Kurang: ' . $rowCount . ' sampel');
                    $sheet->mergeCells("L{$summaryRow}:N{$summaryRow}");
                    $sheet->getStyle("A{$summaryRow}:N{$summaryRow}")->applyFromArray([
                        'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
                        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '833333']],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '6A2929']]],
                    ]);
                    $sheet->getRowDimension($summaryRow)->setRowHeight(22);

                    // ── Footer ────────────────────────────────────────────
                    $footerRow = $summaryRow + 2;
                    $sheet->mergeCells("A{$footerRow}:N{$footerRow}");
                    $sheet->setCellValue("A{$footerRow}", 'Dokumen ini dicetak secara otomatis oleh Sistem LIMS — PT. Evo Manufacturing Indonesia');
                    $sheet->getStyle("A{$footerRow}")->applyFromArray([
                        'font'      => ['italic' => true, 'size' => 8, 'color' => ['rgb' => 'A0A0A0']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                    ]);
                }

                // ── Freeze pane di baris data ─────────────────────────────
                $sheet->freezePane('A8');

                // ── Pengaturan cetak ──────────────────────────────────────
                $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageSetup()->setFitToPage(true);
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);
                $sheet->getPageMargins()->setTop(0.5);
                $sheet->getPageMargins()->setRight(0.3);
                $sheet->getPageMargins()->setLeft(0.3);
                $sheet->getPageMargins()->setBottom(0.5);
                $sheet->getHeaderFooter()->setOddHeader('&C&B&10LAPORAN DAFTAR ANALISA KURANG');
                $sheet->getHeaderFooter()->setOddFooter('&L&8PT. Evo Manufacturing Indonesia&R&8Halaman &P dari &N');
            },
        ];
    }
}
