# Matriks Barang Uji Lab — Referensi Pengisian

Template pengisian: **`Matriks_Barang_Uji_Lab.xlsx`** (folder yang sama), satu sheet: **Matriks Barang Uji Lab**.

> Konsep: satu baris = **"Karyawan X melakukan Analisa Y di Mesin Z"**.
> Kolom **Nama Barang selalu ALL VARIAN** — saat di-Sync, sistem otomatis materialisasi ke semua varian.

## Kolom matriks

| Kolom | Wajib | Cara isi | Contoh |
|---|---|---|---|
| **No** | – | Nomor urut | 1, 2, 3 |
| **Nama Karyawan** | ✅ | Nama analis | Yunia |
| **Nama Analisa** | ✅ | Nama/jenis analisa | Warna (QA), Tekstur Push (QA), Tekstur Kaleng (QA) |
| **Mesin** | ✅ | **Klik sel → pilih** `ALL` atau nama mesin | ALL / AUTOCLAVE |
| **Nama Barang** | otomatis | Terkunci **ALL VARIAN** (tidak ada pilihan) | ALL VARIAN |
| **Keterangan** | – | Catatan bebas | – |

## Aturan pengisian
- **Mesin** = dropdown: pilih `ALL` (semua mesin) atau mesin tertentu. Kalau mesin belum ada di daftar, boleh diketik manual.
- **Beberapa mesin** untuk 1 karyawan+analisa → **buat baris terpisah** per mesin, atau pilih `ALL`.
- **Nama Barang tidak diisi manual** — otomatis semua varian. Itu tujuan fitur ini.
- **Perbarui matriks secara berkala** saat ada karyawan / analisa / mesin baru.

## Contoh isi

| No | Nama Karyawan | Nama Analisa | Mesin | Nama Barang | Keterangan |
|---|---|---|---|---|---|
| 1 | Yunia | Warna (QA) | ALL | ALL VARIAN | |
| 2 | Yunia | Tekstur Push (QA) | AUTOCLAVE | ALL VARIAN | |
| 3 | Yunia | Tekstur Kaleng (QA) | ALL | ALL VARIAN | |
| 4 | Budi | Aroma (QA) | ALL | ALL VARIAN | |

## Catatan penting
Saat data ini dimasukkan ke sistem, **nama karyawan / analisa / mesin harus dipetakan ke kode yang persis** ada di
database (`N_EMI_LAB_Users`, `N_EMI_LAB_Jenis_Analisa`, `EMI_Master_Mesin`) agar cocok saat **Sync**.
Contoh di template (Yunia, Warna (QA), AUTOCLAVE) hanya ilustrasi — ganti dengan data asli Anda.
