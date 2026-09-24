# Migrasi 23-09-2026 — Jejak Validasi & Approval Aktivitas

Ditulis untuk: DevOps yang menjalankan rilis, dan DBA yang mengawasi database produksi.

---

## Ringkas

Validasi palatabilitas (PLT) dan look view (LCKV) selama ini **tidak pernah tercatat** di
`N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final`, meski validasinya benar-benar terjadi.
Migrasi ini memperbaiki penyebabnya di kode, memulihkan jejak yang hilang, dan
menambah satu tabel baru untuk mencatat **siapa meng-approve apa**.

Angka dari database produksi per 23-09-2026:

| Aktivitas | Sudah divalidasi | Punya jejak | Jejak hilang |
|---|---:|---:|---:|
| Palatabilitas (PLT) | 294 | 0 | **294** |
| Look View (LCKV) | 58 | 0 | **58** |
| Analisa Lab (ANL) | 20 | 13 | **7** |

Total **359 validasi** tanpa jejak. Sesudah migrasi: **0**.

---

## Penyebab

Pencatatan jejak bersarang di dalam cabang `if ($flagFG && $flagPerhitungan === 'Y')`.
Di master `N_EMI_LAB_Jenis_Analisa`, seluruh PLT (13 jenis) dan LCKV (20 jenis)
ber-`Flag_Perhitungan = NULL`, begitu pula 8 dari 28 ANL. Analisa-analisa itu
tidak pernah masuk cabang tersebut, sehingga jejaknya hilang.

`Flag_Perhitungan` seharusnya hanya menentukan **cara menghitung** kelayakan,
bukan **apakah** jejaknya disimpan. Kedua keputusan itu kini dipisah.

### Dampak lanjutan

`Flag_Ok` pada header dihitung dari `Detail_Final`. Karena PLT/LCKV tidak punya baris
di sana, palatabilitas atau look view yang **tidak layak pun tidak pernah menurunkan**
`Flag_Ok` — produk berstatus "Lolos Uji" padahal ada tahapan yang gagal.

Contoh nyata di produksi: sampel `FS0526-0017` punya 8 detail `Flag_Layak='T'`
namun header-nya `Flag_Ok='Y'`.

---

## Urutan eksekusi

### Staging — jalankan langsung

```
00-PRACHECK.sql      → 01-STRUKTUR.sql → 02-BACKFILL.sql → 03-VERIFIKASI.sql
```

Keempatnya boleh dijalankan berurutan tanpa jeda. Deploy kode aplikasi setelahnya.

### Produksi — bertahap, dengan pemeriksaan

| # | Berkas | Sifat | Tindakan |
|---|---|---|---|
| 1 | `00-PRACHECK.sql` | read-only | Jalankan. **Bagian 5 wajib 0 baris.** Bila tidak, STOP. |
| 2 | `01-STRUKTUR.sql` | DDL, aditif | Jalankan. Tidak mengunci tabel lama secara berarti. |
| 3 | `02-BACKFILL.sql` | DML | Jalankan. Perhatikan jumlah baris yang dilaporkan. |
| 4 | *deploy kode* | — | Naikkan versi aplikasi. |
| 5 | `03-VERIFIKASI.sql` | read-only¹ | Jalankan. **Semua kolom HASIL harus LULUS.** |

¹ Bagian 1 menjalankan `CHECK CONSTRAINT` — memvalidasi FK, tidak mengubah data.

Berkas opsional:

- `04-PEMBERSIHAN-OPSIONAL.sql` — membersihkan duplikat lama. Seluruh bagian
  penghapusan dikomentari; harus dibuka manual setelah ditinjau tim lab.
- `07-QUERY-DESKTOP.sql` — kumpulan query pelaporan untuk tim desktop. Read-only.
- `99-ROLLBACK.sql` — pembatalan darurat. Seluruh isinya dikomentari.

---

## Sifat skrip

**Idempoten.** Setiap skrip aman dijalankan berulang kali. `01` memakai
`IF NOT EXISTS` di setiap objek; `02` memakai `NOT EXISTS` di setiap INSERT.
Menjalankan `02` dua kali tidak menggandakan satu baris pun — sudah diuji.

**Transaksional.** `01` dan `02` dibungkus `TRY/CATCH` + `TRANSACTION` dengan
`XACT_ABORT ON`. Gagal di tengah berarti rollback penuh: database kembali persis
seperti semula, dan skrip bisa diulang tanpa pembersihan manual.

**Aditif.** Tidak ada `DROP COLUMN`, tidak ada perubahan tipe data, tidak ada
`DROP TABLE`. Semua kolom baru NULLable, sehingga kode versi lama tetap berjalan
setelah `01` dijalankan — struktur boleh naik lebih dulu daripada aplikasi.

**Sudah diuji terhadap database sungguhan.** Seluruh DDL dan DML dijalankan di
`emi_tm_demo` di dalam transaksi yang kemudian di-rollback. Hasil: 19 pernyataan
DDL valid, backfill memulihkan 323 baris detail dan 374 baris approval,
uji idempotensi lolos, dan seluruh pemeriksaan integritas LULUS.

---

## Perubahan struktur

### `N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final` — kolom baru

| Kolom | Tipe | Guna |
|---|---|---|
| `Id_Uji_Validasi_Final` | INT NULL | **FK ke header.** Sebelumnya detail hanya terhubung lewat kecocokan `No_Sampel`. |
| `Kode_Aktivitas_Lab` | VARCHAR(10) | ANL / PLT / LCKV — snapshot, agar tetap benar bila master berubah. |
| `Nama_Jenis_Analisa` | VARCHAR(255) | Snapshot nama. |
| `Id_Session` | INT NULL | Konteks palatabilitas. |
| `Id_Pembanding` | INT NULL | Konteks palatabilitas — membedakan baris PLT yang jenis analisanya sama. |
| `Sumber_Pencatatan` | VARCHAR(30) | `VALIDASI` / `FINALISASI` / `BACKFILL` / `PRA_MIGRASI`. |
| `Dibuat_Pada` | DATETIME | Waktu baris ditulis. |

`Id_Uji_Validasi_Final` sengaja NULLable: detail sah dibuat saat validasi,
jauh sebelum finalisasi terjadi. Di produksi saat ini ada 9 dari 19 detail
yang belum punya header — itu kondisi normal, bukan kesalahan.

### `N_EMI_LAB_Hasil_Uji_Approval_Aktivitas` — tabel baru

Satu baris = satu (sampel × sub-sampel × aktivitas × jenis analisa × pelaku).

Menjawab: *"untuk PO, sampel, dan batch ini, look view di-approve siapa,
analisa lab siapa, palatabilitas siapa."*

**Mengapa tabel tersendiri, bukan kolom di `Detail_Final`:**

1. `Detail_Final` terisi saat finalisasi. Approval terjadi jauh lebih awal dan
   harus terekam meski sampel belum — atau tidak pernah — difinalisasi.
2. Satu analisa bisa melewati lebih dari satu tahap persetujuan (validasi lab,
   lalu approval hirarki). Itu butuh baris tersendiri, bukan satu kolom yang
   saling menimpa.
3. Tim desktop bisa query langsung per aktivitas tanpa menyentuh tabel transaksi.

Kolom `No_Po`, `No_Split_Po`, `No_Batch`, `Kode_Barang`, `Nama_User` sengaja
didenormalisasi. Ini snapshot audit: nilainya harus tetap sebagaimana saat
approval terjadi, walaupun master berubah belakangan.

### Index

`UX_HasilUjiValidasiFinal_SplitPo_Batch_Sampel` (UNIQUE) menutup bug
"finalisasi sampel kedua menimpa yang pertama" di level database — kunci lama
`updateOrInsert` hanya `(No_Split_Po, No_Batch)` tanpa `No_Sampel`.

Index pada `Detail_Final` sengaja **non-unique**: produksi sudah punya 2 kelompok
duplikat dari masa sebelum migrasi (double-submit), dan memaksa UNIQUE akan
menggagalkan migrasi. Perlindungan anti-duplikat ditegakkan di kode aplikasi;
pembersihan data lama tersedia terpisah di `04`.

---

## Perubahan kode

| Berkas | Perubahan |
|---|---|
| `app/Services/JejakValidasiService.php` | **Baru.** Satu pintu pencatatan jejak. Idempoten — klik ganda tidak menggandakan. |
| `UjiSampelController.php` | Pencatatan dipindah ke luar percabangan `Flag_Perhitungan`, berlaku untuk semua aktivitas (versi tunggal & bulk). Perbaikan permission key. Filter multi-barcode. |
| `UjiValidasiFinalController.php` | Kunci `updateOrInsert` + `No_Sampel`. `Flag_Ok` menilai seluruh aktivitas. Penyambungan FK + pencatatan finalisasi. |
| `FinalisasiLabProduksiTrialController.php` | Penyambungan FK + pencatatan finalisasi. `Flag_Ok` menyeluruh. Filter multi-barcode. |
| `FormulatorTrialSampelController.php` | Filter multi-barcode (pengaman; data LIMS saat ini semua multi). |
| `ValidasiLabProduksiTrialController.php` | Perbaikan permission key. |

### Bug permission key

Di dua tempat, `isset()` dan `is_array()` memeriksa key yang **berbeda**:

```php
// sebelum — key tidak sama
isset($permissionKonten['Finalisasi Sampel'])
    && is_array($permissionKonten['Validasi Hasil Analisa'])
```

Akibatnya user yang punya hak `Finalisasi Sampel` tapi tidak punya
`Validasi Hasil Analisa` mendapat daftar kosong — dikembalikan sebagai
`success: true`, sehingga terlihat seperti "tidak ada data", bukan kesalahan hak akses.

### Filter single/multi barcode

Filter `Flag_Multi_QrCode` sebelumnya hanya aktif bila user memilih `qr_type`.
Saat filter kosong — kondisi default halaman — seluruh sampel single ikut masuk
daftar finalisasi. Kini filter `= 'Y'` bersifat wajib di ketiga jalur.

Data pendukung: sampel ber-`Flag_Multi_QrCode` NULL selalu punya 0 sub-sampel,
sedangkan yang ber-flag `'Y'` selalu punya `No_Fak_Sub_Po`.

---

## Bila terjadi masalah

Gagal saat `01` atau `02` → rollback otomatis, database tidak berubah.
Perbaiki penyebabnya, jalankan lagi.

Perlu membatalkan setelah sukses → `99-ROLLBACK.sql`, buka komentar per tahap.
**Turunkan aplikasi ke versi lama dulu**, baru rollback database — kode versi
baru membutuhkan kolom dan tabel ini.

Membatalkan hanya hasil backfill (tahap 1) aman: data aslinya tetap utuh di
`N_EMI_LAB_Uji_Sampel`, sehingga `02-BACKFILL.sql` bisa dijalankan ulang kapan pun.

---

## Catatan di luar cakupan

Rute finalisasi produksi di `routes/web.php:351-352` **tidak punya middleware
`permission`**, sedangkan padanan trial-produksi punya
`permission:Finalisasi_Trial_Produksi,FINALISASI`.

Otorisasi produksi saat ini hanya bergantung pada filter di query daftar, yang
bisa dilewati dengan memanggil endpoint POST secara langsung. Tidak diubah dalam
migrasi ini karena di luar cakupan yang diminta, tetapi perlu ditangani terpisah.
