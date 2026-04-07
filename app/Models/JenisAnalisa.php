<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisAnalisa extends Model
{
    use HasFactory;

    protected $table = 'N_EMI_LAB_Jenis_Analisa';

    protected $fillable = [
        'Kode_Analisa',
        'Jenis_Analisa',
        'Id_Mesin'
    ];
}
