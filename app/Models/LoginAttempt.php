<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    use HasFactory;
    protected $table = 'B2B_Login_Attempts';

    protected $fillable = [
        'Username',
        'Ip_Address',
        'Attempted_At'
    ];

    public $timestamps = false;
}
