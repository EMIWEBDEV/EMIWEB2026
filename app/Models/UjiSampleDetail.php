<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UjiSampleDetail extends Model
{
    use HasFactory;

    protected $table = 'N_EMI_LAB_Uji_Sampel_Detail';

    protected $fillable = [
        "Kode_Perusahaan",
        "No_Faktur_Uji_Sample",
        "Id_Quality_Control",
        "Value_Parameter" ,
        "Tanggal",
        "Jam",
        "Id_User",
    ];

}
