<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BindingJenisAnalisa extends Model
{
    use HasFactory;

    protected $table = 'N_EMI_LAB_Binding_Jenis_Analisa';

    protected $fillable = [
        'Id_Jenis_Analisa',
        'Id_Quality_Control',
        'Keterangan'
    ];
}
