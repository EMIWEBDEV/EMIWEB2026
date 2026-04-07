<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BarangAnalisa extends Model
{
    use HasFactory;

    protected $table = 'N_EMI_LAB_Barang_Analisa';

    protected $fillable = [
        'Id_Jenis_Analisa',
        'Kode_Barang',
        'Kode_Perusahaan',
        'Id_Master_Mesin'
    ];
}
