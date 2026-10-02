# Data dummy laporan validator & finalisator (02-10-2026)

Dua sampel trial yang sudah melewati seluruh alur sampai finalisasi. Dipakai untuk menguji laporan per split: siapa yang memvalidasi tiap aktivitas dan siapa yang memfinalisasi.

> Hanya untuk `emi_tm_demo`. Semua skrip berhenti sendiri bila dijalankan di database lain. Hak akses akun tidak diubah: data ditulis langsung.

| File | Isi |
|---|---|
| `01-DUMMY-TRIAL-PRODUKSI.sql` | Sampel trial produksi (modul LAB) di split `PRD0626-00002-1`. |
| `02-DUMMY-FORMULATOR.sql` | Sampel formulator / trial kitchen di split `PRT0926-00001-1`, termasuk master FLM TRIAL SALT dan Durasi Makan. |
| `03-HAPUS-DUMMY-LAPORAN-TRIAL.sql` | Menghapus kedua sampel dummy beserta jejaknya (master dibiarkan). |

Skrip 01 dan 02 aman dijalankan ulang: sampel dummy di split itu dibuat ulang dengan nomor yang sama. Bila split sudah dipakai sampel asli, skrip berhenti tanpa mengubah apa pun.

## Akun per aktivitas

| Aktivitas | Trial Produksi (penginput → validator) | Formulator (penginput → validator) |
|---|---|---|
| Look View | IIS → Yusuf | GUDANG PEKAN → DIST RIAU |
| Analisa Lab | FRANS → SV_LAB | FRANS → SV_LAB |
| Palatabilitas | DIAH → ROBY | DIAH → ROBY |
| Finalisasi | VENGINE | VENGINE (pra-finalisasi & finalisasi) |

Mesin: AUTOCLAVE. Input 28–30 Sep 2026, validasi 28 Sep – 1 Okt, finalisasi 2 Okt 2026 pagi.

## Analisa

- **Trial Produksi:** Look View WARNA (2 foto), AROMA, TEKSTUR POUCH; Analisa Lab PROTEIN, ASH, MOISTURE, SALT, MIKROBIOLOGI AC/YM/EC/SALMONELLA; Palatabilitas RESPONDEN MEMAKAN, DURASI, TINGKAT KONSUMSI (sesi + pembanding).
- **Formulator:** Look View WARNA (2 foto), AROMA, TEKSTUR POUCH (Trial Kitchen R&D); Analisa Lab TRIAL PROTEIN, ASH, MOISTURE, SALT, MIKROBIOLOGI AC/YM/EC/SALMONELLA; Palatabilitas Responden Memakan, Konsumsi (gram), Durasi Makan (detik).

## Yang perlu diketahui saat membaca laporan

- Validator per aktivitas: `N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final` (trial produksi) dan `N_EMI_LIMS_Hasil_Uji_Validasi_Detail_Final` (formulator), kolom `Id_User`.
- Finalisator: header `..._Hasil_Uji_Validasi_Final` per `No_Sampel` dan Log_Aksi `FINALISASI_TRIAL_PRODUKSI` / `FINALISASI_FORMULATOR`.
- Seperti aplikasi sekarang, pra-finalisasi formulator menimpa `N_EMI_LIMS_Uji_Sampel.Id_User` dengan akun penyetuju (VENGINE). Penginput asli ada di `N_EMI_LIMS_Activity_Uji_Sampel` (`save_submit`).
- Header Log_Aksi validasi dibuat validator pertama dan dipakai ulang; validator sebenarnya ada di `Id_User` tiap baris `N_EMI_LAB_Log_Aksi_Detail`.
