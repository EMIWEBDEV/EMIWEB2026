<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class POSample extends Model
{
    use HasFactory;

    public $table = 'N_EMI_LAB_PO_Sampel';

    public $fillable =[
        'Kode_Perusahaan',
        'No_Sampel',
        'Status',
        'Tanggal',
        'Jam',
        'No_Split_Po',
        'No_Batch',
        'Id_Mesin',
        'Keterangan',
        'Kode_Barang',
        'No_Po',
        'Id_User'
    ];
}
