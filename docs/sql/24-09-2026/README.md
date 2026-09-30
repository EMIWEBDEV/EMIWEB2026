# Migrasi 24-09-2026 — Jejak Validasi & Approval Aktivitas

Ditulis untuk: DevOps yang menjalankan rilis, dan DBA yang mengawasi database produksi.

---

## ⚠ Baca ini lebih dulu — izin database

User aplikasi **`srv_lab_usr` tidak bisa menjalankan `01-STRUKTUR.sql`.**
Sudah diuji langsung ke production:

| Izin | Status |
|---|---|
| SELECT / INSERT / UPDATE / DELETE | ✅ GRANT |
| CREATE TABLE | ❌ **denied** |
| ALTER TABLE / CREATE INDEX | ❌ **denied** |
| `db_owner` / `db_ddladmin` / `sysadmin` | ❌ bukan anggota |

**Konsekuensinya:**

- `01-STRUKTUR.sql` harus dijalankan oleh akun ber-`db_owner` atau `db_ddladmin`
  (mis. `sa`, atau login DBA). Minta ke DBA sebelum jadwal rilis.
- `02-BACKFILL.sql` dan `05-PERBAIKAN-KONTEKS.sql` **bisa** dijalankan
  `srv_lab_usr`, karena isinya hanya INSERT/UPDATE.
- `03-VERIFIKASI.sql` bagian 1 menjalankan `ALTER TABLE ... CHECK CONSTRAINT`,
  jadi bagian itu juga perlu akun DDL. Bagian lainnya read-only dan bisa
  dijalankan siapa saja.

Kalau ini tidak disiapkan, rilis akan berhenti di langkah pertama.

---

## Ringkas

Validasi palatabilitas (PLT), look view (LCKV), dan sebagian analisa lab (ANL)
selama ini **tidak tercatat** di `N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final`,
meski validasinya benar-benar terjadi.

Angka nyata dari production (`emi_db_real`) per 24-09-2026:

| Aktivitas | Sudah divalidasi | Punya jejak | **Jejak hilang** |
|---|---:|---:|---:|
| Analisa Lab (ANL) | 209.498 | 92.590 | **116.908** |
| Palatabilitas (PLT) | 1.253 | 0 | **1.253** |
| Look View (LCKV) | 148 | 0 | **148** |
| **Total** | **210.899** | **92.590** | **118.309** |

Sesudah migrasi: **0**.

### Penyebab

Pencatatan jejak bersarang di dalam cabang `if ($flagFG && $flagPerhitungan === 'Y')`.
Di master `N_EMI_LAB_Jenis_Analisa` production, seluruh PLT (8 jenis) dan LCKV
(20 jenis) — serta 5 dari 24 ANL — ber-`Flag_Perhitungan = NULL`, sehingga tidak
pernah masuk cabang tersebut.

`Flag_Perhitungan` seharusnya hanya menentukan **cara menghitung** kelayakan,
bukan **apakah** jejaknya disimpan. Kedua keputusan itu kini dipisah di kode.

---

## Perbandingan struktur: staging vs production

Diperiksa langsung ke kedua server.

| Objek | Staging | Production | Tindakan |
|---|---|---|---|
| `Hasil_Uji_Validasi_Final` | 10 kolom, 3 index | 10 kolom, **1 index** | +2 index |
| `..._Detail_Final` | 17 kolom, 4 index, 1 FK | **9 kolom, 1 index, 0 FK** | +8 kolom, +3 index, +1 FK |
| `Hasil_Uji_Approval_Aktivitas` | ada (26 kolom) | **belum ada** | CREATE TABLE + 5 index |
| Tabel pendukung | lengkap | lengkap ✅ | — |

Kolom yang kurang di production (8): `Id_Uji_Validasi_Final`, `Kode_Aktivitas_Lab`,
`Nama_Jenis_Analisa`, `Id_Session`, `Id_Pembanding`, `Flag_Resampling`,
`Sumber_Pencatatan`, `Dibuat_Pada`.

Versi & collation **identik** di kedua server: SQL Server 2016 (v16),
`Latin1_General_CI_AS`. Tidak ada beda tipe data pada kolom yang sudah sama.

---

## Urutan eksekusi

| # | Berkas | Perlu izin | Sifat |
|---|---|---|---|
| 1 | `00-PRACHECK.sql` | biasa | read-only |
| 2 | `01-STRUKTUR.sql` | **DDL (db_owner)** | DDL, aditif |
| 3 | `02-BACKFILL.sql` | biasa | DML, per batch |
| 4 | *deploy kode aplikasi* | — | — |
| 5 | `03-VERIFIKASI.sql` | **DDL** (bag. 1) | read-only |

**Berhenti bila `00-PRACHECK.sql` bagian 4 tidak berstatus LULUS.**
Itu satu-satunya hal yang benar-benar memblokir — sudah diperiksa di production:
**0 duplikat, aman.**

Staging boleh dijalankan berurutan tanpa jeda.

---

## Sifat skrip

**Idempoten.** Setiap objek dijaga `IF NOT EXISTS`; setiap INSERT memakai
`NOT EXISTS`. Sudah diuji: dijalankan dua kali di staging tanpa error dan tanpa
menggandakan satu baris pun.

**Aditif.** Tidak ada `DROP COLUMN`, tidak ada perubahan tipe data, tidak ada
`DROP TABLE`. Semua kolom baru NULLable, sehingga **kode versi lama tetap
berjalan** setelah `01` dijalankan — struktur boleh naik lebih dulu daripada aplikasi.

**Transaksional.** `01` dibungkus `TRY/CATCH` + `TRANSACTION` dengan
`XACT_ABORT ON`. Gagal di tengah berarti rollback penuh: database kembali
persis seperti semula dan skrip bisa langsung diulang.

### Mengapa `02-BACKFILL.sql` memakai batch, bukan satu transaksi

Production akan menulis **~118.316** baris detail dan **~65.194** baris approval.
File log di sana hanya **259 MB** (recovery `SIMPLE`). Satu transaksi raksasa
berisiko membengkakkan log sampai penuh dan mengunci tabel berjam-jam.

Skrip menulis **5.000 baris per batch, COMMIT tiap batch** (~24 batch untuk
bagian terberat). Akibatnya:

- Log tidak menumpuk.
- Penguncian singkat per batch, aplikasi tidak terblokir lama.
- **Bila terhenti di tengah** (timeout, koneksi putus), batch yang sudah sukses
  tetap tersimpan — jalankan ulang, skrip melanjutkan dari titik terakhir.

Ukuran batch bisa diturunkan di `DECLARE @batch INT = 5000;` bila server sibuk.

---

## Yang sudah diuji

**Di staging** (`emi_tm_demo`) — ketiga skrip dijalankan penuh, dua kali:
semua pemeriksaan `LULUS`, `JEJAK_HILANG = 0`, idempotensi terbukti.

**Di production** (`emi_db_real`) — read-only, tidak ada yang ditulis:

- Prasyarat pemblokir: **0 duplikat header** → UNIQUE index akan lolos
- Semua tabel pendukung ada
- Jumlah baris yang akan ditulis terverifikasi: 118.316 detail + 65.194 approval
- **Uji anti-bentrok:** ditemukan **36.260 kombinasi** yang punya lebih dari satu
  baris `Uji_Sampel` (maksimum 60 baris per kombinasi). Tanpa `GROUP BY` di
  bagian D/E, INSERT pasti ditolak `UX_ApprovalAktivitas_Plt`. Dengan `GROUP BY`
  yang sudah ada di skrip, tiap kombinasi diringkas jadi satu baris approval.

Peringkasan itu juga benar secara makna: satu analisa menghasilkan beberapa
parameter (nilai 10, 0, 50, 1, 2, 85 dalam satu faktur), tapi sebagai
**persetujuan** itu satu tindakan oleh satu orang. `MIN(Flag_Layak)` dipakai
supaya satu parameter tidak layak membuat persetujuannya ikut tertandai
tidak layak — konservatif, dan tepat untuk audit mutu.

---

## Perubahan struktur

### `N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final` — 8 kolom baru

| Kolom | Tipe | Guna |
|---|---|---|
| `Id_Uji_Validasi_Final` | INT NULL | **FK ke header.** Sebelumnya detail hanya terhubung lewat kecocokan `No_Sampel`. |
| `Kode_Aktivitas_Lab` | VARCHAR(10) | ANL / PLT / LCKV — snapshot. |
| `Nama_Jenis_Analisa` | VARCHAR(255) | Snapshot nama. |
| `Id_Session` | INT NULL | Konteks palatabilitas. |
| `Id_Pembanding` | INT NULL | Membedakan baris PLT dengan jenis analisa sama. |
| `Flag_Resampling` | CHAR(1) | Mengikuti kolom sejenis di `Uji_Sampel`. |
| `Sumber_Pencatatan` | VARCHAR(30) | `VALIDASI` / `FINALISASI` / `BACKFILL` / `PRA_MIGRASI`. |
| `Dibuat_Pada` | DATETIME | Waktu baris ditulis. |

`Id_Uji_Validasi_Final` sengaja NULLable: detail sah dibuat saat validasi, jauh
sebelum finalisasi. NULL di sana berarti "belum difinalisasi", bukan kesalahan.

### `N_EMI_LAB_Hasil_Uji_Approval_Aktivitas` — tabel baru

Satu baris = satu (sampel × sub-sampel × aktivitas × jenis analisa × pelaku).

Menjawab: *"untuk PO, sampel, dan batch ini — look view di-approve siapa,
analisa lab siapa, palatabilitas siapa."*

Dibuat sebagai tabel tersendiri, bukan kolom di `Detail_Final`, karena approval
bisa berlapis (validasi lab, lalu approval hirarki) dan masing-masing perlu
barisnya sendiri; juga harus terekam meski sampel tidak pernah difinalisasi.

### Index

`UX_HasilUjiValidasiFinal_SplitPo_Batch_Sampel` (UNIQUE) menutup bug
"finalisasi sampel kedua menimpa yang pertama" di level database — kunci
`updateOrInsert` lama hanya `(No_Split_Po, No_Batch)` tanpa `No_Sampel`.

Index pada `Detail_Final` sengaja **non-unique**: production punya **123 kelompok
duplikat** dari double-submit lama. Memaksa UNIQUE akan menggagalkan migrasi.
Perlindungan anti-duplikat ditegakkan di kode aplikasi.

Dua index UNIQUE pada tabel approval dibuat **ber-filter** (`WHERE Id_Pembanding
IS NULL` dan `IS NOT NULL`) karena index UNIQUE biasa memperlakukan seluruh NULL
sebagai satu nilai yang sama, sehingga baris non-PLT yang berbeda akan salah
dianggap kembar.

---

## Bila terjadi masalah

Gagal saat `01` → rollback otomatis, database tidak berubah. Perbaiki
penyebabnya, jalankan lagi.

Gagal/terhenti saat `02` → batch yang sudah sukses tetap tersimpan. **Jalankan
ulang** — skrip melanjutkan, tidak mengulang, tidak menggandakan.

Perlu membatalkan setelah sukses → turunkan aplikasi ke versi lama **dulu**,
baru rollback database; kode versi baru membutuhkan kolom dan tabel ini. Baris
hasil backfill semuanya bertanda `Sumber_Pencatatan = 'BACKFILL'` sehingga bisa
dihapus terpisah tanpa menyentuh data asli — dan data sumbernya tetap utuh di
`N_EMI_LAB_Uji_Sampel`, jadi `02-BACKFILL.sql` bisa dijalankan ulang kapan pun.

---

## Catatan di luar cakupan

Rute finalisasi produksi di `routes/web.php` **tidak punya middleware
`permission`**, sedangkan padanan trial-produksi punya
`permission:Finalisasi_Trial_Produksi,FINALISASI`. Otorisasi produksi saat ini
hanya bergantung pada filter di query daftar, yang bisa dilewati dengan
memanggil endpoint POST secara langsung. Perlu ditangani terpisah.
