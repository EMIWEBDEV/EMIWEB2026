# Migrasi 26-09-2026 — Sample Lifecycle & Jejak Resampling

Ditulis untuk: DevOps yang menjalankan rilis, dan DBA yang mengawasi database production.

---

## ⚠ Baca ini lebih dulu

- `01-STRUKTUR-LIFECYCLE.sql` dan `99-ROLLBACK-LIFECYCLE.sql` berisi `ALTER TABLE` /
  `CREATE INDEX`. **`srv_lab_usr` tidak punya izin DDL** — jalankan dengan akun
  `db_owner` / `db_ddladmin` (mis. `sa`), sama seperti `24-09-2026/01-STRUKTUR.sql`.
- Migrasi ini **mensyaratkan** migrasi `docs/sql/24-09-2026/` dan
  `docs/sql/24-09-2026-verifikasi/`. Per 26-09-2026 keduanya **belum** dijalankan di
  production (tabel `N_EMI_LAB_Hasil_Uji_Approval_Aktivitas` dan `N_EMI_LAB_Verifikasi_*`
  belum ada di `emi_db_real`).
- `docs/sql/24-09-2026/02-BACKFILL.sql` **direvisi 26-09-2026** (bagian C dan D) — pakai
  versi terbaru dari repo. Versi awalnya mengisi penginput hasil sebagai validator.
- Pembuatan index pada `N_EMI_LAB_Uji_Sampel` (±213 ribu baris, belum punya index sama
  sekali) mengunci tabel sebentar — edisi Express tidak mendukung `ONLINE = ON`.
  **Jalankan di luar jam sibuk lab.**
- Jalankan `sqlcmd` dengan `-I` (QUOTED_IDENTIFIER) dan `-f 65001` (berkas UTF-8).

---

## Urutan eksekusi production (lengkap)

| # | Berkas | Izin | Sifat |
|---|---|---|---|
| 1 | `24-09-2026/00-PRACHECK.sql` | biasa | read-only |
| 2 | `24-09-2026/01-STRUKTUR.sql` | **DDL** | DDL, aditif |
| 3 | **`26-09-2026-lifecycle/01-STRUKTUR-LIFECYCLE.sql`** | **DDL** | DDL, aditif |
| 4 | `24-09-2026/02-BACKFILL.sql` *(versi revisi)* | biasa | DML, per batch |
| 5 | `24-09-2026/03-VERIFIKASI.sql` | DDL (bag. 1) | read-only |
| 6 | `24-09-2026-verifikasi/*` selain berkas `CONTOH-*` | lihat tajuk tiap berkas | struktur, master, menu |
| 7 | **`26-09-2026-lifecycle/02-KOREKSI-JEJAK-BACKFILL.sql`** | biasa | DML — di database yang baru, tidak mengubah apa pun |
| 8 | *deploy kode aplikasi* | — | — |

Berkas bernama `*-CONTOH-*` dan `12-LENGKAPI-PALATABILITAS-0001.sql` di
`24-09-2026-verifikasi/` adalah **data contoh** untuk staging — jangan dijalankan di
production (`13` dan `14` menolak berjalan di `emi_db_real`).

Staging (`emi_tm_demo`) sudah menjalankan semuanya per 26-09-2026.

---

## Isi migrasi

### 01-STRUKTUR-LIFECYCLE.sql (DDL, idempoten, aditif)

| Tabel | Perubahan | Untuk apa |
|---|---|---|
| `N_EMI_LAB_Uji_Sampel_Resampling_Log` | + `Alasan` VARCHAR(500), + `Dibuat_Pada` | Alasan resampling yang diketik validator; waktu pencatatan jam server |
| `N_EMI_LAB_Berkas_Uji_Lab` | + `Id_User`, `Tahapan_Ke`, `Dibuat_Pada`, `Flag_Nonaktif`, `Dinonaktifkan_Pada`, `Id_User_Nonaktif` | Siapa & kapan foto diunggah; foto putaran yang ditolak **dinonaktifkan, tidak dihapus** |
| `N_EMI_LAB_Uji_Sampel` | + index `(No_Po_Sampel, Id_Jenis_Analisa, Tahapan_Ke)` | Membaca lifecycle per sampel tanpa memindai seluruh tabel |
| `N_EMI_LAB_Activity_Uji_Sampel` | + index `(No_Po_Sampel, Id_Jenis_Analisa)` | idem |
| `N_EMI_LAB_Uji_Sampel_Resampling_Log` | + index `(No_Po_Sampel, Id_Jenis_Analisa, Tahapan_Ke)` | idem |
| `N_EMI_LAB_Berkas_Uji_Lab` | + index `(No_Sampel, No_Faktur)` | idem |
| `N_EMI_LAB_Hasil_Uji_Approval_Aktivitas` | kunci unik `UX_ApprovalAktivitas_*` **+ Tahapan_Ke** | Validasi putaran resampling oleh orang yang sama (sampel tunggal, nomor sampel tetap) tidak lagi ditolak sebagai ganda |

Semua kolom baru NULLable; kode versi lama tetap berjalan. Kunci unik baru lebih longgar
dari yang lama, jadi data yang ada pasti lolos.

### 02-KOREKSI-JEJAK-BACKFILL.sql (DML, idempoten, satu transaksi)

Untuk database yang **sudah** menjalankan `02-BACKFILL.sql` versi awal (hanya staging):

- `Detail_Final` ber-Sumber `BACKFILL`: validator diganti dengan yang tercatat di
  `N_EMI_LAB_Log_Aksi`, atau dikosongkan bila tidak tercatat.
- Approval `VALIDASI` ber-Sumber `BACKFILL`: diganti ke validator asli (Log_Aksi, lalu
  `Detail_Final` pra-migrasi); yang validatornya tidak tercatat **dihapus** — approval
  tanpa penyetuju yang diketahui bukan approval.

Hasil di staging: 323 baris detail diluruskan; 75 approval tanpa validator dan 8 yang
akan kembar dihapus; 13 diganti ke validator asli.

---

## Perubahan kode yang menyertai (deploy langkah 8)

| Area | Berkas | Perubahan |
|---|---|---|
| Sample Lifecycle | `app/Services/LifecycleSampelService.php` (baru), `VerifikasiHasilAnalisaController::lifecycle`, komponen `LifecycleSampel.vue`, `LifecycleKejadian.vue` | Alur registrasi → per klasifikasi (uji → validasi → resampling → uji ulang → validasi) → verifikasi → finalisasi, mode Ringkas/Audit, per klasifikasi/kronologis |
| Layar Verifikasi & Finalisasi | `RincianHasilAnalisaService::tanpaPutaranDitolak()` | Hanya membaca hasil putaran yang berlaku; putaran yang ditolak tetap terbaca di lifecycle |
| Validator | `RincianHasilAnalisaService::petaValidasi()` | + `Detail_Final` pra-migrasi/realtime sebagai sumber validator |
| Snapshot verifikasi | `VerifikasiHasilAnalisaController::tulisKeputusan()` | Kolom `*_Validasi` diisi validator asli, bukan penginput |
| Validasi V2 | `UjiSampelController::storeConfirmedUjiSampelV2()` | Satu transaksi untuk semua analisa; semua analisa diproses (dulu hanya yang pertama); putaran lama yang ditolak tidak ikut diubah; tidak ada jejak palsu dari klik ganda |
| Validasi massal & lama | `storeBulkConfirmedUjiSampel()`, `storeConfirmedUjiSampel()`, `hitungKelayakan()` | Putaran lama yang ditolak tidak ikut dinilai/diubah |
| Resampling | `resampelingAnalisa()`, `resampelingAnalisaSingle()` | Jam server; putaran dihitung per jenis analisa; simpan `Alasan` |
| Uji ulang | `storeMultiRumusResamplingV2()` | Putaran dari log resampling untuk semua mesin (dulu non-FG dipaksa 1) |
| Foto | 4 fungsi simpan hasil | Foto putaran ditolak dinonaktifkan, tidak dihapus dari DB/GCS; pengunggah & putaran dicatat |
| Palatabilitas | `PalatabilitasController::finalisasi()`, `finalisasiResampling()` | Finalisasi sesi dicatat sebagai validasi; jam server |
| Approval | `JejakValidasiService::catatApproval()` | Satu approval per putaran (bila kunci unik sudah + Tahapan_Ke) |
| Form resampling | `ConfirmedUjiAnalisav2.vue`, `ValidasiTrialProduksi.vue`, `verfikasiv2pcs.vue`, `verfikasiv2nopcs.vue` | Isian **Alasan resampling** wajib (min. 5 karakter) |

Kode baru tetap berjalan bila langkah 3 belum dijalankan (kolom/index diperiksa lebih
dulu), tetapi alasan resampling dan data pengunggah foto baru tersimpan setelah langkah 3.

---

## Pemeriksaan setelah rilis

1. Buka **Verifikasi Hasil Analisa**, pilih sampel yang pernah diresampling → panel
   *Sample Lifecycle* menampilkan Putaran 1 → kartu **Resampling** (kuning) → Putaran 2.
2. Di layar validasi lab, tombol **Uji Ulang** → isian *Alasan resampling* wajib diisi.
3. Query cepat:
   ```sql
   SELECT TOP 5 No_Po_Sampel, Tahapan_Ke, Alasan, Dibuat_Pada
   FROM N_EMI_LAB_Uji_Sampel_Resampling_Log ORDER BY Id_Resampling DESC;
   ```

---

## Bila terjadi masalah

- Gagal di `01` → setiap langkah memeriksa keadaan; perbaiki penyebab, jalankan ulang.
- Perlu membatalkan → turunkan kode aplikasi dulu, lalu `99-ROLLBACK-LIFECYCLE.sql`.
  Kolom yang dibuang ikut membuang isinya (alasan resampling, data pengunggah foto).
  Kunci unik approval hanya dikembalikan ke bentuk lama bila datanya muat; bila tidak,
  script berhenti dan menampilkan baris yang bentrok.
