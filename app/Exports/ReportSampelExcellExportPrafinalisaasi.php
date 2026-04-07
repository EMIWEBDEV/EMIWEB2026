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
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class ReportSampelExcellExportPrafinalisaasi implements FromCollection, WithHeadings, WithCustomStartCell, WithEvents
{
    protected Collection $collection;
    protected array $headers;
    protected array $rataRata;
    protected array $imagesData;
    protected string $tanggalCetak;

    public function __construct(Collection $collection, array $headers, array $rataRata, array $imagesData = [], string $tanggalCetak = '')
    {
        $this->collection = $collection;
        $this->headers = $headers;
        $this->rataRata = $rataRata;
        $this->imagesData = $imagesData;
        $this->tanggalCetak = $tanggalCetak;
    }

    public function collection(): Collection
    {
        return $this->collection;
    }

    public function headings(): array
    {
        $header1 = ['No', 'Nama Sampel', 'Tanggal Mulai Uji', 'Result'];
        $jumlahKolomAnalisa = count($this->headers);
        if ($jumlahKolomAnalisa > 1) {
            $header1 = array_merge($header1, array_fill(0, $jumlahKolomAnalisa - 1, null));
        }

        $header2 = array_fill(0, 3, null);
        $header2 = array_merge($header2, array_column($this->headers, 'nama'));

        return [$header1, $header2];
    }

    public function startCell(): string
    {
        return 'A8';
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
                $lastColIndex = 3 + $jumlahKolomAnalisa;
                $lastColLetter = Coordinate::stringFromColumnIndex($lastColIndex);

                $sheet->mergeCells("A1:{$lastColLetter}1")->setCellValue('A1', 'LABORATORY ANALYSIS REPORT - RINGKASAN PRAFINALISASI');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'color' => ['argb' => 'FF000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $sheet->mergeCells("A2:{$lastColLetter}2")->setCellValue('A2', 'Tanggal Cetak : ' . $this->tanggalCetak);
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['italic' => true, 'size' => 11, 'color' => ['argb' => 'FF555555']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $noteRange = "A4:{$lastColLetter}6";
                $sheet->mergeCells("A4:{$lastColLetter}4")->setCellValue('A4', '  ⚠️ CATATAN PENTING:');
                $sheet->mergeCells("A5:{$lastColLetter}5")->setCellValue('A5', '       1. Laporan ini bersifat SEMENTARA (Prafinalisasi), bukan merupakan hasil akhir yang sah/rilis resmi.');
                $sheet->mergeCells("A6:{$lastColLetter}6")->setCellValue('A6', '       2. Jika terdapat kolom analisa yang disorot warna merah dengan nilai "-" (strip), hal tersebut menandakan bahwa data analisa belum diinput.');

                $sheet->getStyle($noteRange)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFFFEBEE'], 
                    ],
                    'font' => [
                        'color' => ['argb' => 'FFC62828'], 
                        'size' => 10,
                    ],
                    'borders' => [
                        'outline' => [
                            'borderStyle' => Border::BORDER_MEDIUM,
                            'color' => ['argb' => 'FFC62828'],
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle("A4:{$lastColLetter}4")->getFont()->setBold(true);

                $footerData = array_merge(['Rata-rata', null, null], $this->rataRata);
                $sheet->fromArray($footerData, null, "A{$footerRow}");

                $sheet->mergeCells("A{$header1Row}:A{$header2Row}");
                $sheet->mergeCells("B{$header1Row}:B{$header2Row}");
                $sheet->mergeCells("C{$header1Row}:C{$header2Row}");
                if ($jumlahKolomAnalisa > 0) {
                     $sheet->mergeCells("D{$header1Row}:{$lastColLetter}{$header1Row}");
                }
                $sheet->mergeCells("A{$footerRow}:C{$footerRow}");

                $fullRange = "A{$header1Row}:{$lastColLetter}{$footerRow}";
                $sheet->getStyle($fullRange)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => '000000']]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
                    'font' => ['size' => 9]
                ]);

                $sheet->getStyle("A{$header1Row}:{$lastColLetter}{$header1Row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFf5e7b0']],
                ]);
                $sheet->getStyle("D{$header2Row}:{$lastColLetter}{$header2Row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFddbe59']],
                ]);
                
                $sheet->getStyle("A{$header1Row}:{$lastColLetter}{$header2Row}")->getFont()->setBold(true);
                $sheet->getStyle("A{$footerRow}:{$lastColLetter}{$footerRow}")->getFont()->setBold(true);

                $dataStartRow = $header2Row + 1;
                $sheet->getStyle("B{$dataStartRow}:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                for ($r = $dataStartRow; $r < $footerRow; $r++) {
                    for ($c = 4; $c <= $lastColIndex; $c++) {
                        $colLet = Coordinate::stringFromColumnIndex($c);
                        $cellVal = $sheet->getCell($colLet . $r)->getValue();
                        
                        if ($cellVal === '-' || $cellVal === '') {
                            $sheet->getStyle($colLet . $r)->applyFromArray([
                                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFCDD2']],
                                'font' => ['color' => ['argb' => 'FFB71C1C'], 'bold' => true]
                            ]);
                        }
                    }
                }

                $sheet->getColumnDimension('A')->setWidth(6);
                $sheet->getColumnDimension('B')->setWidth(50);
                $sheet->getColumnDimension('C')->setWidth(18);
                
                for ($i = 0; $i < $jumlahKolomAnalisa; $i++) {
                    $colLetter = Coordinate::stringFromColumnIndex(4 + $i);
                    if ($this->headers[$i]['flag_foto'] === 'Y') {
                        $sheet->getColumnDimension($colLetter)->setWidth(25);
                    } else {
                        $sheet->getColumnDimension($colLetter)->setAutoSize(true);
                    }
                }

                foreach ($this->imagesData as $img) {
                    try {
                        $fileContent = \Illuminate\Support\Facades\Storage::disk('gcs')->get($img['path']);
                        
                        if ($fileContent) {
                            $tempPath = tempnam(sys_get_temp_dir(), 'exc_img_') . '.png';
                            file_put_contents($tempPath, $fileContent);

                            $drawing = new Drawing();
                            $drawing->setName('Foto Uji');
                            $drawing->setDescription('Foto Uji Lab');
                            $drawing->setPath($tempPath);
                            $drawing->setCoordinates($img['col'] . $img['row']);
                            $drawing->setHeight(70); 
                            $drawing->setOffsetX(10);
                            $drawing->setOffsetY(30); 
                            $drawing->setWorksheet($sheet);

                            $sheet->getStyle($img['col'] . $img['row'])->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                            $sheet->getRowDimension($img['row'])->setRowHeight(95);
                        } else {
                            $currentVal = $sheet->getCell($img['col'] . $img['row'])->getValue();
                            $sheet->setCellValue($img['col'] . $img['row'], $currentVal . "\n[Gambar Kosong]");
                        }
                    } catch (\Exception $e) {
                        $currentVal = $sheet->getCell($img['col'] . $img['row'])->getValue();
                        $sheet->setCellValue($img['col'] . $img['row'], $currentVal . "\n[Error Foto]");
                        \Illuminate\Support\Facades\Log::error('Gagal memuat foto GCS di Excel: ' . $e->getMessage());
                    }
                }
            },
        ];
    }
}