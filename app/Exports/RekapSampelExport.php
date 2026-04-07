<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class RekapSampelExport implements FromCollection, WithHeadings, WithDrawings, WithCustomStartCell, WithEvents
{
    protected $dataTerproses;
    protected $parameter;
    protected $rumus;
    protected $namaAnalisa;
    protected $rataRata = [];
    protected $apakahPerhitungan;
    protected $flagFoto;

    public function __construct(array $dataMentah, array $parameter = [], array $rumus = [], string $namaAnalisa, string $flagFoto = 'T')
    {
        $this->parameter = $parameter;
        $this->rumus = $rumus;
        $this->namaAnalisa = $namaAnalisa;
        $this->flagFoto = $flagFoto;

        $this->apakahPerhitungan = !empty($this->rumus);

        $dataDikelompokkan = collect($dataMentah)->groupBy('No_Faktur');

        $this->dataTerproses = $dataDikelompokkan->map(function ($grup) {
            $itemPertama = $grup->first(); 

            $hasilParameter = collect($itemPertama['parameter'] ?? [])->map(function ($param) {
                return $param['Hasil_Analisa'] ?? '-';
            })->all();

            $hasilAkhir = [];
            if ($this->apakahPerhitungan) {
                $hasilAkhir = $grup->map(function ($item) {
                    return $item['Hasil_Akhir_Analisa'] ?? '-';
                })->all();
            } else if ($this->flagFoto === 'Y') {
                $hasilAkhir[] = $itemPertama['Hasil_Akhir_Analisa'] ?? '-';
            }

            return [
                'No_Faktur' => $itemPertama['No_Faktur'] ?? '-',
                'No_Po_Sampel' => $itemPertama['No_Po_Sampel'] ?? '-',
                'No_Po' => $itemPertama['No_Po'] ?? '-',
                'No_Split_Po' => $itemPertama['No_Split_Po'] ?? '-',
                'Tanggal_Pengujian' => $itemPertama['Tanggal_Pengujian'] ?? '-',
                'parameters' => $hasilParameter,
                'results' => $hasilAkhir,
                'image_path' => $itemPertama['image_path'] ?? null,
                '_original_group' => $grup->all()
            ];
        })->values();

        $this->hitungRataRata();
    }

    private function hitungRataRata()
    {
        if (!$this->apakahPerhitungan || $this->dataTerproses->isEmpty()) {
            return;
        }

        $jumlahKolomRumus = count($this->rumus);

        for ($i = 0; $i < $jumlahKolomRumus; $i++) {
            $total = 0;
            $jumlahData = 0;
            $angkaDesimal = 2;

            foreach ($this->dataTerproses as $baris) {
                $nilai = $baris['results'][$i] ?? null;
                if ($nilai !== null && is_numeric($nilai)) {
                    $total += (float)$nilai;
                    $jumlahData++;

                    $itemAsli = $baris['_original_group'][$i] ?? null;
                    if ($itemAsli && isset($itemAsli['Pembulatan'])) {
                        $angkaDesimal = (int)$itemAsli['Pembulatan'];
                    }
                }
            }

            $this->rataRata[] = ($jumlahData > 0)
                ? number_format($total / $jumlahData, $angkaDesimal, '.', '')
                : '-';
        }
    }

    public function collection()
    {
        return $this->dataTerproses->map(function ($item, $key) {
            $baris = [
                'no' => $key + 1,
                'no_transaksi' => $item['No_Faktur'] ?? '-',
                'no_sampel' => $item['No_Po_Sampel'] ?? '-',
                'no_po' => $item['No_Po'] ?? '-',
                'no_split_po' => $item['No_Split_Po'] ?? '-',
                'tanggal' => isset($item['Tanggal_Pengujian']) ? Carbon::parse($item['Tanggal_Pengujian'])->format('d-M-Y') : '-',
            ];

            $semuaNilai = array_merge($item['parameters'], $item['results']);

            return array_merge($baris, $semuaNilai);
        });
    }

    public function headings(): array
    {
        $headings = ['NO', 'NO TRANSAKSI', 'NO SAMPEL', 'NO PO', 'NO SPLIT PO', 'TANGGAL'];
        
        foreach ($this->parameter as $param) {
            $headings[] = strtoupper($param['nama_parameter']);
        }
        
        foreach ($this->rumus as $rum) {
            $headings[] = strtoupper($rum['nama_kolom']);
        }

        if ($this->flagFoto === 'Y' && empty($this->rumus)) {
            $headings[] = 'RESULT';
        }
        
        return $headings;
    }

    public function startCell(): string
    {
        return 'B7';
    }

    public function drawings()
    {
        $drawing = new Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Logo Perusahaan');
        $drawing->setPath(public_path('assets/images/thumb-excel.png'));
        $drawing->setHeight(70);
        $drawing->setCoordinates('B1');
        return [$drawing];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $startRow = (int) substr($this->startCell(), 1);
                $headings = $this->headings();
                $columnCount = count($headings);
                $lastColLetter = Coordinate::stringFromColumnIndex($columnCount + 1);
                $lastRow = $startRow + $this->dataTerproses->count();

                $sheet->mergeCells("B2:{$lastColLetter}2");
                $sheet->mergeCells("B3:{$lastColLetter}3");
                $sheet->mergeCells("B5:{$lastColLetter}5");
                $sheet->setCellValue('B2', 'PT. EVO MANUFACTURING INDONESIA');
                $sheet->setCellValue('B3', 'FTN DEPARTMENT - SUMATERA SELATAN');
                $sheet->setCellValue('B5', $this->namaAnalisa);
                $sheet->getStyle("B2:B5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B2:B3")->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle("B5")->getFont()->setBold(true)->setSize(16);
                $event->sheet->setTitle(substr($this->namaAnalisa, 0, 31));

                $headerRange = "B{$startRow}:{$lastColLetter}{$startRow}";
                $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9EAD3');
                $sheet->getStyle($headerRange)->getFont()->setBold(true);
                $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                if ($this->dataTerproses->isNotEmpty()) {
                    $tableRange = "B{$startRow}:{$lastColLetter}{$lastRow}";
                    $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                }
                for ($col = 2; $col <= $columnCount + 1; $col++) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
                }

                if ($this->flagFoto === 'Y') {
                    $resultColIndex = $columnCount + 1;
                    $resultColLetter = Coordinate::stringFromColumnIndex($resultColIndex);
                    
                    $sheet->getColumnDimension($resultColLetter)->setAutoSize(false);
                    $sheet->getColumnDimension($resultColLetter)->setWidth(25);

                    foreach ($this->dataTerproses as $index => $item) {
                        $currentRow = $startRow + 1 + $index;
                        $imagePath = $item['image_path'] ?? null;

                        if ($imagePath) {
                            try {
                                $fileContent = \Illuminate\Support\Facades\Storage::disk('gcs')->get($imagePath);
                                if ($fileContent) {
                                    $tempPath = tempnam(sys_get_temp_dir(), 'exc_img_') . '.png';
                                    file_put_contents($tempPath, $fileContent);

                                    $drawing = new Drawing();
                                    $drawing->setName('Foto');
                                    $drawing->setDescription('Foto Uji');
                                    $drawing->setPath($tempPath);
                                    $drawing->setCoordinates($resultColLetter . $currentRow);
                                    $drawing->setHeight(70);
                                    $drawing->setOffsetX(10);
                                    $drawing->setOffsetY(30);
                                    $drawing->setWorksheet($sheet);

                                    $sheet->getStyle($resultColLetter . $currentRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                                    $sheet->getRowDimension($currentRow)->setRowHeight(95);
                                }
                            } catch (\Exception $e) {
                                $currentVal = $sheet->getCell($resultColLetter . $currentRow)->getValue();
                                $sheet->setCellValue($resultColLetter . $currentRow, $currentVal . "\n[Error Foto]");
                            }
                        }
                    }
                }

                if ($this->apakahPerhitungan && $this->dataTerproses->isNotEmpty()) {
                    $averageRow = $lastRow + 1;
                    $labelEndColIndex = $columnCount + 1 - count($this->rumus);
                    $labelEndCol = Coordinate::stringFromColumnIndex($labelEndColIndex);
                    
                    $averageRowRange = "B{$averageRow}:{$lastColLetter}{$averageRow}";
                    $sheet->getStyle($averageRowRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                    
                    $sheet->mergeCells("B{$averageRow}:{$labelEndCol}{$averageRow}");
                    $sheet->setCellValue("B{$averageRow}", 'Rata-Rata');
                    $sheet->getStyle("B{$averageRow}:{$labelEndCol}{$averageRow}")->getFont()->setBold(true);
                    $sheet->getStyle("B{$averageRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    $valueColIndex = $labelEndColIndex + 1;
                    foreach ($this->rataRata as $avgIndex => $avg) {
                        $valueCol = Coordinate::stringFromColumnIndex($valueColIndex + $avgIndex);
                        $sheet->setCellValue("{$valueCol}{$averageRow}", is_numeric($avg) ? (float) $avg : $avg);
                        
                        $style = $sheet->getStyle("{$valueCol}{$averageRow}");
                        $style->getFont()->setBold(true);
                        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        
                        $pembulatanAvg = 2; 
                        foreach($this->dataTerproses as $row) {
                           $originalItem = $row['_original_group'][$avgIndex] ?? null;
                           if($originalItem && isset($originalItem['Pembulatan'])) {
                               $pembulatanAvg = (int)$originalItem['Pembulatan'];
                               break; 
                           }
                        }
                        $formatAvg = '0.' . str_repeat('0', $pembulatanAvg);
                        $style->getNumberFormat()->setFormatCode($formatAvg);
                    }
                }
            }
        ];
    }
}