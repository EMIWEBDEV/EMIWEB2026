<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BindingIdentity extends Model
{
    use HasFactory;

    protected $table = 'N_EMI_LAB_Binding_Identity';

    protected $fillable = [
        'Kode_Perusahaan',
        'Id_Identity',
        'Id_Mesin'
    ];
}
