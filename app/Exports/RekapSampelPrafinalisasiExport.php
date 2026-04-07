<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class RekapSampelPrafinalisasiExport implements WithMultipleSheets
{
    protected array $sheetsData;
    protected string $tanggalCetak;

    public function __construct(array $sheetsData, string $tanggalCetak)
    {
        $this->sheetsData = $sheetsData;
        $this->tanggalCetak = $tanggalCetak;
    }

    public function sheets(): array
    {
        $sheets = [];
        
        // Looping untuk membuat tab di dalam 1 file Excel
        foreach ($this->sheetsData as $data) {
            $sheets[] = new RekapSampelPrafinalisasiSheet(
                $data['data'],
                $data['parameters'],
                $data['rumus'],
                $data['namaAnalisa'],
                $data['flagFoto'],
                $this->tanggalCetak
            );
        }

        return $sheets;
    }
}