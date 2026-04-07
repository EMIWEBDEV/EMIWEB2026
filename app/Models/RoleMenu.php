<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleMenu extends Model
{
    use HasFactory;
    protected $table = "N_EMI_LAB_Role_Menu";

    protected $fillable = [
        'Id_Menu',
        'Id_Sub_Menu',
        'Id_User',
    ];

}
