<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterMesin extends Model
{
    use HasFactory;

    protected $table = "EMI_Master_Mesin";

    protected $fillable = [
        'Kode_Perusahaan',
        'Divisi_Mesin',
        'Seri_Mesin',
        'Nama_Mesin',
        'Keterangan',
        'Id_Master_Mesin',
        'Id_Divisi_Mesin',
        'Flag_Multi_Qrcode',
        'Jumlah_Print_QRCode'
    ];

}
