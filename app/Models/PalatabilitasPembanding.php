<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PalatabilitasPembanding extends Model
{
    protected $table = 'N_EMI_LAB_Palatabilitas_Pembanding';
    protected $primaryKey = 'Id_Pembanding';
    public $timestamps = false;

    protected $fillable = [
        'Id_Session',
        'Kode_Perusahaan',
        'Urutan',
        'Kode_Barang_Pembanding',
        'Nama_Pembanding',
        'Tanggal',
        'Jam',
        'Id_User',
        'Kode_Role',
        'Flag_Aktif',
    ];

    public function session()
    {
        return $this->belongsTo(PalatabilitasSession::class, 'Id_Session', 'Id_Session');
    }

    public function sementara()
    {
        return $this->hasMany(PalatabilitasSementara::class, 'Id_Pembanding', 'Id_Pembanding');
    }
}
