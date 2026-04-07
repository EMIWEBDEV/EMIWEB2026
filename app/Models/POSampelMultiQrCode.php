<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class POSampelMultiQrCode extends Model
{
    use HasFactory;

    public $table = 'N_EMI_LAB_PO_Sampel_Multi_QrCode';

    public $fillable =[
        'Kode_Perusahaan',
        'No_Po_Multi',
        'Kode_Barang',
        'No_PO_Sampel',
        'Status',
        'Tanggal',
        'Jam',
    ];
}
