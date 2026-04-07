<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class RekapSampelPrafinalisasiSheet implements FromCollection, WithHeadings, WithCustomStartCell, WithEvents, WithTitle
{
    protected $dataTerproses;
    protected $parameter;
    protected $rumus;
    protected $namaAnalisa;
    protected $rataRata = [];
    protected $apakahPerhitungan;
    protected $flagFoto;
    protected $tanggalCetak;

    public function __construct(array $dataMentah, array $parameter = [], array $rumus = [], string $namaAnalisa, string $flagFoto = 'T', string $tanggalCetak = '')
    {
        $this->parameter = $parameter;
        $this->rumus = $rumus;
        $this->namaAnalisa = $namaAnalisa;
        $this->flagFoto = $flagFoto;
        $this->tanggalCetak = $tanggalCetak;

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
        if (!$this->apakahPerhitungan || $this->dataTerproses->isEmpty()) return;

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
            $this->rataRata[] = ($jumlahData > 0) ? number_format($total / $jumlahData, $angkaDesimal, '.', '') : '-';
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
        return 'A8';
    }

    public function title(): string
    {
        return substr($this->namaAnalisa, 0, 31);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                $startCell = $this->startCell();
                $startRow = Coordinate::coordinateFromString($startCell)[1];
                $headerRow = $startRow;
                $lastRow = $sheet->getHighestRow();
                $footerRow = $lastRow + 1;
                
                $headings = $this->headings();
                $columnCount = count($headings);
                $lastColLetter = Coordinate::stringFromColumnIndex($columnCount);

                $sheet->mergeCells("A1:{$lastColLetter}1")->setCellValue('A1', 'LABORATORY ANALYSIS REPORT - DETAIL PRAFINALISASI');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'color' => ['argb' => 'FF000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $sheet->mergeCells("A2:{$lastColLetter}2")->setCellValue('A2', 'Analisa : ' . $this->namaAnalisa . ' | Tanggal Cetak : ' . $this->tanggalCetak);
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

                $headerRange = "A{$headerRow}:{$lastColLetter}{$headerRow}";
                $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9EAD3');
                $sheet->getStyle($headerRange)->getFont()->setBold(true);
                $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

                if ($this->dataTerproses->isNotEmpty()) {
                    $tableRange = "A{$headerRow}:{$lastColLetter}{$lastRow}";
                    $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                    $sheet->getStyle($tableRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                for ($col = 1; $col <= $columnCount; $col++) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
                }

                $dataStartRow = $headerRow + 1;
                for ($r = $dataStartRow; $r <= $lastRow; $r++) {
                    for ($c = 7; $c <= $columnCount; $c++) {
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

                if ($this->flagFoto === 'Y') {
                    $resultColIndex = 6 + count($this->parameter) + (empty($this->rumus) ? 1 : count($this->rumus));
                    $resultColLetter = Coordinate::stringFromColumnIndex($resultColIndex);
                    
                    $sheet->getColumnDimension($resultColLetter)->setAutoSize(false);
                    $sheet->getColumnDimension($resultColLetter)->setWidth(25);

                    foreach ($this->dataTerproses as $index => $item) {
                        $currentRow = $headerRow + 1 + $index;
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
                    $labelEndColIndex = $columnCount - count($this->rumus);
                    $labelEndCol = Coordinate::stringFromColumnIndex($labelEndColIndex);
                    
                    $averageRowRange = "A{$footerRow}:{$lastColLetter}{$footerRow}";
                    $sheet->getStyle($averageRowRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                    
                    $sheet->mergeCells("A{$footerRow}:{$labelEndCol}{$footerRow}");
                    $sheet->setCellValue("A{$footerRow}", 'Rata-Rata');
                    $sheet->getStyle("A{$footerRow}:{$labelEndCol}{$footerRow}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$footerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    $valueColIndex = $labelEndColIndex + 1;
                    foreach ($this->rataRata as $avgIndex => $avg) {
                        $valueCol = Coordinate::stringFromColumnIndex($valueColIndex + $avgIndex);
                        $sheet->setCellValue("{$valueCol}{$footerRow}", is_numeric($avg) ? (float) $avg : $avg);
                        
                        $style = $sheet->getStyle("{$valueCol}{$footerRow}");
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