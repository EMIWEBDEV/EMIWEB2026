<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubMenu extends Model
{
    use HasFactory;

    protected $table = "N_EMI_LAB_Sub_Menus";

    protected $fillable = [
        'Id_Sub_Menu',
        'Nama_Sub_Menu',
        'Icon_Sub_Menu'
    ];
}
