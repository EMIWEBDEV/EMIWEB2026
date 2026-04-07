<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MesinAnalisa extends Model
{
    use HasFactory;

    protected $table = 'N_EMI_LAB_Mesin_Analisa';

    protected $fillable = [
        'Kode_Perusahaan',
        'Divisi_Mesin',
        'Nama_Mesin',
        'Keterangan'
    ];
}
