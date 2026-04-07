<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Identity extends Model
{
    use HasFactory;

    protected $table = 'N_EMI_LAB_Identity';

    protected $fillable = [
        'Kode_Perusahaan',
        'Computer_Keys',
        'Keterangan'
    ];

    
}
