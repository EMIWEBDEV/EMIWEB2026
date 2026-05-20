<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PalatabilitasSementara extends Model
{
    protected $table = 'N_EMI_LAB_Palatabilitas_Sementara';
    protected $primaryKey = 'No_Urut';
    public $timestamps = false;

    protected $fillable = [
        'Id_Session',
        'Id_Pembanding',
        'Kode_Perusahaan',
        'No_Po_Sampel',
        'No_Fak_Sub_Po',
        'Id_Jenis_Analisa',
        'Hasil',
        'Flag_Perhitungan',
        'Flag_Multi_QrCode',
        'Nilai_Hasil_String',
        'Flag_String',
        'Flag_Foto',
        'Status',
        'Tanggal',
        'Jam',
        'Id_User',
        'Kode_Role',
    ];

    public function session()
    {
        return $this->belongsTo(PalatabilitasSession::class, 'Id_Session', 'Id_Session');
    }

    public function pembanding()
    {
        return $this->belongsTo(PalatabilitasPembanding::class, 'Id_Pembanding', 'Id_Pembanding');
    }
}
