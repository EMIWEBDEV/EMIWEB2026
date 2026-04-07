<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Perhitungan extends Model
{
    use HasFactory;

    protected $table = 'N_EMI_LAB_Perhitungan';

    protected $fillable = [
        'Id_Jenis_Analisa',
        'Rumus',
        'Nama_Kolom',
        'Hasil_Perhitungan'
    ];
}
