<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UjiSampel extends Model
{
    use HasFactory;

    protected $table = 'N_EMI_LAB_Uji_Sampel';

    protected $fillable = [
        "Kode_Perusahaan",
        "No_Faktur",
        "No_Po_Sampel",
        'No_Fak_Sub_Po',
        'Id_Jenis_Analisa',
        'Hasil',
        'Flag_Perhitungan',
        'Flag_Multi_Qrcode',
        "Status" ,
        "Tanggal",
        "Jam",
        "Id_User",
    ];
}
