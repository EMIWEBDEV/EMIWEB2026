<?php

/*
|--------------------------------------------------------------------------
| SANDBOX (/trial-ui) — modul pembaharuan
|--------------------------------------------------------------------------
| Validasi, Verifikasi, dan Finalisasi versi pembaharuan dicoba dari sesi
| sandbox. Pengaturan di sini hanya dipakai modul tersebut; modul lama
| tidak membacanya.
*/

return [

    /*
     | Database tempat sampel dummy boleh dibuat.
     |
     | Sampel dummy menulis ke tabel transaksi (PO sampel, uji sampel), jadi
     | hanya diizinkan di database demo. Di database lain — mis. produksi —
     | tombol "Sampel dummy" tidak tersedia dan permintaannya ditolak server.
     */
    'database_dummy' => env('SANDBOX_DATABASE_DUMMY', 'emi_tm_demo'),

    /*
     | Sampel lengkap yang disalin menjadi sampel dummy siap validasi.
     | Isinya menentukan analisa dummy: FS0926-0001 memuat Look View (3),
     | Analisa Lab (6, dengan parameter perhitungan), dan Uji Palatabilitas
     | (3, dengan sesi dan produk pembanding), plus satu foto.
     */
    'sampel_template' => env('SANDBOX_SAMPEL_TEMPLATE', 'FS0926-0001'),

];
