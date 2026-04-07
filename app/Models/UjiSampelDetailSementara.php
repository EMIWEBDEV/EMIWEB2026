<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UjiSampelDetailSementara extends Model
{
    use HasFactory;

     protected $table = 'N_EMI_LAB_Uji_Sampel_Detail_Sementara';

    protected $fillable = [
        "Kode_Perusahaan",
        "No_Sementara",
        "Id_Quality_Control",
        "Value_Parameter" ,
        "Tanggal",
        "Jam",
        "Id_User",
    ];
}
