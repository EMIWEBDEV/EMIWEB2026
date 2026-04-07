<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class ParticleSizeExport implements WithEvents, ShouldAutoSize, WithDrawings, WithTitle
{
    protected $reports;

    public function __construct(array $reports)
    {
        $this->reports = $reports;
    }

    public function title(): string
    {
        return 'Particle Size Report';
    }

    public function drawings()
    {
        $drawing = new Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Company Logo');
        $drawing->setPath(public_path('assets/images/thumb-excel.png')); // logo kiri
        $drawing->setHeight(70);
        $drawing->setCoordinates('A1');

        return $drawing;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // --- STYLING ---
                $companyStyle = [
                    'font' => ['bold' => true, 'size' => 18],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ];
                $departmentStyle = [
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ];
                $titleStyle = [
                    'font' => ['bold' => true, 'size' => 16],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ];
                $infoLabelStyle = [
                    'font' => ['bold' => true, 'size' => 10],
                ];
                $tableHeaderStyle = [
                    'font' => ['bold' => true, 'size' => 10],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF5E7B0']], // sama kayak PDF
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ];

                // --- HEADER ---
                $sheet->mergeCells('B1:F1')->setCellValue('B1', 'PT. EVO MANUFACTURING INDONESIA');
                $sheet->getStyle('B1')->applyFromArray($companyStyle);

                $sheet->mergeCells('B2:F2')->setCellValue('B2', 'FTN DEPARTMENT - SUMATERA SELATAN');
                $sheet->getStyle('B2')->applyFromArray($departmentStyle);

                $sheet->mergeCells('B3:F3')->setCellValue('B3', 'PARTICLE SIZE REPORT');
                $sheet->getStyle('B3')->applyFromArray($titleStyle);

                $sheet->getRowDimension(1)->setRowHeight(20);
                $sheet->getRowDimension(2)->setRowHeight(18);
                $sheet->getRowDimension(3)->setRowHeight(22);

                $currentRow = 5;

                // --- LOOP REPORT ---
                foreach ($this->reports as $report) {
                    $startBlock = $currentRow;

                    // Info Sampel
                    $sheet->setCellValue("B{$currentRow}", 'Nama Sampel');
                    $sheet->mergeCells("C{$currentRow}:E{$currentRow}")
                          ->setCellValue("C{$currentRow}", ': ' . $report['info']['nama_sampel']);
                    $sheet->getStyle("B{$currentRow}")->applyFromArray($infoLabelStyle);
                    $currentRow++;

                    $sheet->setCellValue("B{$currentRow}", 'Tanggal Produksi');
                    $sheet->mergeCells("C{$currentRow}:E{$currentRow}")
                          ->setCellValue("C{$currentRow}", ': ' . $report['info']['tanggal_produksi_1']);
                    $sheet->getStyle("B{$currentRow}")->applyFromArray($infoLabelStyle);
                    $currentRow++;

                    if (!empty($report['info']['tanggal_produksi_2'])) {
                        $sheet->setCellValue("B{$currentRow}", 'Tanggal Produksi 2');
                        $sheet->mergeCells("C{$currentRow}:E{$currentRow}")
                              ->setCellValue("C{$currentRow}", ': ' . $report['info']['tanggal_produksi_2']);
                        $sheet->getStyle("B{$currentRow}")->applyFromArray($infoLabelStyle);
                        $currentRow++;
                    }

                    $sheet->setCellValue("B{$currentRow}", 'Produk');
                    $sheet->mergeCells("C{$currentRow}:E{$currentRow}")
                          ->setCellValue("C{$currentRow}", ': ' . $report['info']['produk']);
                    $sheet->getStyle("B{$currentRow}")->applyFromArray($infoLabelStyle);
                    $currentRow += 2;

                    // Tabel Particle Size
                    $startTable = $currentRow;
                    $sheet->mergeCells("B{$currentRow}:C{$currentRow}")->setCellValue("B{$currentRow}", 'Particle Size');
                    $sheet->mergeCells("D{$currentRow}:E{$currentRow}")->setCellValue("D{$currentRow}", 'Percentage (%)');
                    $sheet->getStyle("B{$currentRow}:E{$currentRow}")->applyFromArray($tableHeaderStyle);
                    $currentRow++;

                    foreach ($report['values'] as $particleSize => $percentage) {
                        $sheet->mergeCells("B{$currentRow}:C{$currentRow}")->setCellValue("B{$currentRow}", $particleSize);
                        $sheet->mergeCells("D{$currentRow}:E{$currentRow}")->setCellValue("D{$currentRow}", $percentage);
                        $sheet->getStyle("D{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $currentRow++;
                    }

                    // Border blok
                    $sheet->getStyle("B{$startBlock}:E" . ($currentRow - 1))
                          ->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THICK);

                    $sheet->getStyle("B{$startTable}:E" . ($currentRow - 1))
                          ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                    $currentRow += 3; // jarak antar blok
                }

                // Lebar kolom
                foreach (['A'=>12,'B'=>20,'C'=>20,'D'=>20,'E'=>20] as $col=>$w) {
                    $sheet->getColumnDimension($col)->setWidth($w);
                }
            },
        ];
    }
}
