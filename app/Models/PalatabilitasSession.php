<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PalatabilitasSession extends Model
{
    protected $table = 'N_EMI_LAB_Palatabilitas_Session';
    protected $primaryKey = 'Id_Session';
    public $timestamps = false;

    protected $fillable = [
        'Kode_Perusahaan',
        'No_Po_Sampel',
        'Kode_Aktivitas_Lab',
        'Status_Session',
        'Tanggal_Buat',
        'Jam_Buat',
        'Id_User_Buat',
        'Kode_Role',
        'Tanggal_Final',
        'Jam_Final',
        'Id_User_Final',
    ];

    public function pembanding()
    {
        return $this->hasMany(PalatabilitasPembanding::class, 'Id_Session', 'Id_Session')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan');
    }

    public function sementara()
    {
        return $this->hasMany(PalatabilitasSementara::class, 'Id_Session', 'Id_Session');
    }
}
