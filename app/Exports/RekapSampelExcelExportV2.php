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

class RekapSampelExcelExportV2 implements FromCollection, WithHeadings, WithCustomStartCell, WithEvents
{
    protected Collection $collection;
    protected array $headers;
    protected array $rataRata;
    protected array $imagesData;

    public function __construct(Collection $collection, array $headers, array $rataRata, array $imagesData = [])
    {
        $this->collection = $collection;
        $this->headers = $headers;
        $this->rataRata = $rataRata;
        $this->imagesData = $imagesData;
    }

    public function collection(): Collection
    {
        return $this->collection;
    }

    public function headings(): array
    {
        // Tanggal Produksi dihapus
        $header1 = ['No', 'Nama Sampel', 'Tanggal Uji Sampel', 'Result'];
        $jumlahKolomAnalisa = count($this->headers);
        if ($jumlahKolomAnalisa > 1) {
            $header1 = array_merge($header1, array_fill(0, $jumlahKolomAnalisa - 1, null));
        }

        // Disesuaikan menjadi 3 kolom awal yang kosong untuk sub-header
        $header2 = array_fill(0, 3, null);
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
                
                // Disesuaikan karena kolom statis berkurang 1 (dari 4 menjadi 3)
                $lastColIndex = 3 + $jumlahKolomAnalisa;
                $lastColLetter = Coordinate::stringFromColumnIndex($lastColIndex);

                $sheet->mergeCells("A1:{$lastColLetter}1")->setCellValue('A1', 'LABORATORY ANALYSIS REPORT');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Array dikurangi 1 null karena kolom statis berkurang
                $footerData = array_merge(['Rata-rata', null, null], $this->rataRata);
                $sheet->fromArray($footerData, null, "A{$footerRow}");

                $sheet->mergeCells("A{$header1Row}:A{$header2Row}");
                $sheet->mergeCells("B{$header1Row}:B{$header2Row}");
                $sheet->mergeCells("C{$header1Row}:C{$header2Row}");
                
                if ($jumlahKolomAnalisa > 0) {
                    // Result sekarang dimulai dari kolom D
                     $sheet->mergeCells("D{$header1Row}:{$lastColLetter}{$header1Row}");
                }
                
                // Merge footer disesuaikan hingga kolom C
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
                
                // Warna header 2 dimulai dari D
                $sheet->getStyle("D{$header2Row}:{$lastColLetter}{$header2Row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFddbe59']],
                ]);
                $sheet->getStyle("A{$header1Row}:{$lastColLetter}{$header2Row}")->getFont()->setBold(true);

                $sheet->getStyle("A{$footerRow}:{$lastColLetter}{$footerRow}")->getFont()->setBold(true);

                $dataStartRow = $header2Row + 1;
                $sheet->getStyle("B{$dataStartRow}:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $sheet->getColumnDimension('A')->setWidth(6);
                $sheet->getColumnDimension('B')->setWidth(50);
                $sheet->getColumnDimension('C')->setWidth(18); // Ini sekarang untuk Tanggal Uji Sampel
                
                for ($i = 0; $i < $jumlahKolomAnalisa; $i++) {
                    // Start kolom dinamis di indeks 4 (D) bukan 5 (E)
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