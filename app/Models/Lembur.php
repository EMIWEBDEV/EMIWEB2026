<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lembur extends Model
{
    use HasFactory;
    protected $table = 'KPI_lemburs_Test'; 

    protected $fillable = [
    'no_transaksi',
    'keterangan',
    'jam_mulai',
    'jam_selesai',
    'tanggal',
    'user_id',
    'flag_acc',
    'tanggal_acc',
    'jam_acc',
    'bukti_dukung',
    'bukti_pengerjaan_selesai',
    'keterangan_konfirmasi',
    'bukti_pengerjaan_selesai',
    'keterangan_konfirmasi'
];

}
