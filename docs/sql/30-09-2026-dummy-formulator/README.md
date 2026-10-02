# Data dummy Formulator (30-09-2026)

Tiga sampel trial Formulator yang lengkap dan sudah divalidasi LIMS, untuk mencoba alur Validasi Hirarki (pra-finalisasi), Finalisasi Trial, dan Hasil Trial di database demo.

> Hanya untuk `emi_tm_demo`. Kedua skrip berhenti sendiri bila dijalankan di database lain.

| File | Isi |
|---|---|
| `01-DUMMY-FORMULATOR.sql` | Membuat/menyusun ulang 3 sampel dummy beserta master yang dibutuhkan. Aman dijalankan ulang. |
| `02-HAPUS-DUMMY-FORMULATOR.sql` | Menghapus data transaksi ketiga sampel; master mikro FLM ikut dihapus bila `@Hapus_Master = 1`. |

## Sampel

Semua sampel: PO trial `PRT0426-00004` / split `PRT0426-00004-1`, barang `BRG2410001` (LIFE CAT 400GR SALMON KITTEN), mesin **AUTOCLAVE**, 1 pcs (sub-sampel QR `-1`). Untuk mesin multi-QR, daftar Uji Sampel baru menganggap analisa selesai bila semua sub-sampel sudah diuji, jadi jumlah pcs sengaja 1.

| Sampel | Batch | Registrasi | Validasi | Catatan |
|---|---|---|---|---|
| FT0926-0001 | 2 | 14-09-2026 | 18-09-2026 | semua layak |
| FT0926-0002 | 3 | 21-09-2026 | 25-09-2026 | semua layak |
| FT0926-0003 | 4 | 23-09-2026 | 29-09-2026 | Tekstur Kaleng "Lengket di Tutup Kaleng" (tidak layak) |

Analisa tiap sampel (12):

- **Look View** — Aroma, Warna (2 foto), Tekstur Kaleng (Trial Kitchen R&D).
- **Analisa Lab** — Trial Protein (Crude Protein), Trial Ash, Trial Moisture, Trial Mikrobiologi AC / YM / EC / Salmonella.
- **Palatabilitas** — Responden Memakan, Konsumsi (gram).

Penginput: `FRANS` (Look View, Palatabilitas), `SV_LAB` (Analisa Lab). Validator: `VENGINE`.

## Master yang ditambahkan

Mikrobiologi hanya ada sebagai analisa role LAB (di demo maupun produksi), sedangkan layar Formulator menyaring `Kode_Role = 'FLM'`. Skrip membuat versi FLM-nya (`TRMAC`, `TRMYM`, `TRMEC`, `TRMSL`) dengan rumus, parameter, dan batas yang sama dengan versi LAB, menyalin hak konten dari Trial Protein (id 60), dan mendaftarkan 12 analisa di atas ke Barang Analisa `BRG2410001 × AUTOCLAVE` untuk semua akun ber-role FLM.

Hak konten dibaca saat login: **akun formulator perlu login ulang** agar analisa mikro FLM muncul.

## Foto

Look View Warna (satu-satunya Look View dengan `Flag_Foto = 'Y'` di master) memakai ulang foto produk dari berkas lab di GCS (`berkas/lab/labwHO7X…`, `labaZISF…`, `labRxKrI…`). Tidak ada file yang diunggah.
