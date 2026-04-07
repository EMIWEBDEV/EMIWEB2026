<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class RekapSampelLabProduksiExport implements FromCollection, WithHeadings, WithCustomStartCell, WithEvents
{
    protected Collection $collection;
    protected array $headers;
    protected array $rataRata;
    protected string $startDate;
    protected string $endDate;

    public function __construct(Collection $collection, array $headers, array $rataRata, string $startDate, string $endDate)
    {
        $this->collection = $collection;
        $this->headers = $headers;
        $this->rataRata = $rataRata;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection(): Collection
    {
        return $this->collection;
    }

    public function headings(): array
    {
        // Disesuaikan menjadi 4 kolom statis sesuai dengan $groupedData
        $header1 = ['No', 'Nama Sampel', 'Tanggal Produksi', 'Tanggal Uji Sampel', 'Result'];
        $jumlahKolomAnalisa = count($this->headers);
        if ($jumlahKolomAnalisa > 1) {
            $header1 = array_merge($header1, array_fill(0, $jumlahKolomAnalisa - 1, null));
        }

        // Sub-header butuh 4 kolom kosong di awal
        $header2 = array_fill(0, 4, null);
        $header2 = array_merge($header2, array_column($this->headers, 'nama'));

        return [$header1, $header2];
    }

    public function startCell(): string
    {
        return 'A3';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $startCell = $this->startCell();
                $startRow = Coordinate::coordinateFromString($startCell)[1];
                $header1Row = $startRow;
                $header2Row = $startRow + 1;
                $lastRow = $sheet->getHighestRow();
                $footerRow = $lastRow + 1;
                $jumlahKolomAnalisa = count($this->headers);
                
                // Index terakhir = 4 kolom statis + jumlah dinamis
                $lastColIndex = 4 + $jumlahKolomAnalisa;
                $lastColLetter = Coordinate::stringFromColumnIndex($lastColIndex);

                // Menambahkan Periode Tanggal ke Judul Laporan di A1
                $periode = $this->startDate === $this->endDate ? $this->startDate : "{$this->startDate} - {$this->endDate}";
                $sheet->mergeCells("A1:{$lastColLetter}1")->setCellValue('A1', "LABORATORY ANALYSIS REPORT ({$periode})");
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Footer ditambah 1 null agar 'Rata-rata' pas dengan kolom analisa
                $footerData = array_merge(['Rata-rata', null, null, null], $this->rataRata);
                $sheet->fromArray($footerData, null, "A{$footerRow}");

                // Merge Cells Header
                $sheet->mergeCells("A{$header1Row}:A{$header2Row}"); // No
                $sheet->mergeCells("B{$header1Row}:B{$header2Row}"); // Nama Sampel
                $sheet->mergeCells("C{$header1Row}:C{$header2Row}"); // Tanggal Produksi
                $sheet->mergeCells("D{$header1Row}:D{$header2Row}"); // Tanggal Uji Sampel
                
                if ($jumlahKolomAnalisa > 0) {
                     // Sub-header Result dimulai dari kolom E (5)
                     $sheet->mergeCells("E{$header1Row}:{$lastColLetter}{$header1Row}");
                }
                
                // Merge text "Rata-rata" dari A sampai D
                $sheet->mergeCells("A{$footerRow}:D{$footerRow}");

                $fullRange = "A{$header1Row}:{$lastColLetter}{$footerRow}";
                $sheet->getStyle($fullRange)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => '000000']]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
                    'font' => ['size' => 9]
                ]);

                $sheet->getStyle("A{$header1Row}:{$lastColLetter}{$header1Row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFf5e7b0']],
                ]);
                
                // Warna sub-header Result (dimulai dari E)
                $sheet->getStyle("E{$header2Row}:{$lastColLetter}{$header2Row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFddbe59']],
                ]);
                $sheet->getStyle("A{$header1Row}:{$lastColLetter}{$header2Row}")->getFont()->setBold(true);
                $sheet->getStyle("A{$footerRow}:{$lastColLetter}{$footerRow}")->getFont()->setBold(true);

                $dataStartRow = $header2Row + 1;
                if ($lastRow >= $dataStartRow) {
                    $sheet->getStyle("B{$dataStartRow}:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }

                // Lebar Kolom Statis
                $sheet->getColumnDimension('A')->setWidth(6);
                $sheet->getColumnDimension('B')->setWidth(50);
                $sheet->getColumnDimension('C')->setWidth(18); // Tanggal Produksi
                $sheet->getColumnDimension('D')->setWidth(18); // Tanggal Uji Sampel
                
                // AutoSize Kolom Dinamis Analisa (Mulai dari E)
                for ($i = 0; $i < $jumlahKolomAnalisa; $i++) {
                    $colLetter = Coordinate::stringFromColumnIndex(5 + $i);
                    $sheet->getColumnDimension($colLetter)->setAutoSize(true);
                }
            },
        ];
    }
}