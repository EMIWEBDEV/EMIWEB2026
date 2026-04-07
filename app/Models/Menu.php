<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use HasFactory;

    protected $table = "N_EMI_LAB_Menus";

    protected $fillable = [
        'Id_Menu',
        'Nama_Menu',
        'Icon_Menu',
        'Url_Menu'
    ];
}
