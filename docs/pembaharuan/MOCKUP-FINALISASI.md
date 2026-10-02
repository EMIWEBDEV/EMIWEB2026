# Mockup — Finalisasi Sampel (pola rekomendasi)

Dokumen rancangan untuk tahap **Finalisasi** yang mengikuti pola baru:
keputusan bertingkat (rekomendasi), bukan lolos/tidak lolos.

Ditulis untuk: pengembang yang akan membangun modul ini, dan penanggung
jawab lab yang perlu menyetujui rancangannya sebelum dibangun.

---

## 1. Mengapa berubah

Finalisasi yang berjalan sekarang menyimpan satu kolom `Flag_Ok` berisi
`'Y'` atau `'T'` pada `N_EMI_LAB_Hasil_Uji_Validasi_Final`. Tiga persoalan,
yang ketiga paling serius:

1. **Terlalu kasar.** Sampel yang sebagian parameternya menyimpang namun
   masih dapat dirilis dengan justifikasi tidak punya tempat — hanya ada
   diterima atau ditolak.

2. **Tidak sejalan dengan tahap sebelumnya.** Verifikasi sudah memakai tiga
   tingkat rekomendasi. Finalisasi yang biner memaksa penerjemahan ulang dan
   memutus jejak keputusannya.

3. **Verifikasi belum menjadi gerbang finalisasi.**
   `FinalisasiLabProduksiTrialController` tidak menyebut tabel
   `N_EMI_LAB_Verifikasi_*` sama sekali (terverifikasi: nol referensi).
   Ia menyimpulkan "sudah divalidasi" langsung dari `N_EMI_LAB_Uji_Sampel`
   lewat `Flag_Selesai = 'Y'` dan `Status_Keputusan_Sampel = 'terima'`.

   Akibatnya **sampel yang belum diverifikasi — bahkan yang bertanda Tidak
   Direkomendasikan — tetap dapat difinalisasi.** Rantai
   Validasi → Verifikasi → Finalisasi sudah dirancang, tetapi belum
   tersambung di kode. Menutup celah inilah alasan utama modul ini dibuat.

Rancangan ini menyamakan polanya: **Direkomendasikan / Direkomendasikan
Bersyarat / Tidak Direkomendasikan**, tanpa memotong tahapan mana pun.

---

## 2. Tata letak — tiga panel

Sama dengan layar Verifikasi, supaya kebiasaan pengguna tidak berubah.

```
┌──────────────┬───────────────────────────────────┬─────────────────┐
│  KIRI        │  TENGAH                           │  KANAN          │
│  Antrean     │  Ringkasan + detail per           │  Sample         │
│  sampel      │  klasifikasi                      │  Lifecycle      │
│              │                                   │  (dapat ditutup)│
│  330px       │  fleksibel                        │  380px, dapat   │
│              │                                   │  ditarik        │
└──────────────┴───────────────────────────────────┴─────────────────┘
```

Pemisah antara panel tengah dan kanan dapat ditarik, dan panel kanan punya
tombol perbesar — persis seperti pada Verifikasi.

---

## 3. Panel kiri — antrean sampel

Yang masuk antrean: sampel yang **seluruh klasifikasinya sudah diverifikasi**.
Sampel yang masih menunggu verifikasi tidak ditampilkan di sini, karena
finalisasi tidak boleh mendahului verifikasi.

```
┌────────────────────────────────────────┐
│ ⌕ Cari no. sampel, PO, barang…         │
├────────────────────────────────────────┤
│ ☐ Pilih semua                          │
├────────────────────────────────────────┤
│ ▍FS0926-0001                           │
│   LIFE CAT 85GR CHICKEN KITTEN         │
│   ▤ PRD0626-00001  ⚙ AUTOCLAVE         │
│   ◆ LCKV 3   ◆ ANL 6   ◆ PLT 3         │
│   ● Menunggu Finalisasi                │
├────────────────────────────────────────┤
│  FS0926-0019                           │
│   LIFE CAT 85GR CHICKEN KITTEN         │
│   ▤ PRD0626-00001  ⚙ AUTOCLAVE         │
│   ◆ LCKV 3                             │
│   ✓ Direkomendasikan                   │
└────────────────────────────────────────┘
```

**Keputusan rancangan:** chip klasifikasi (`LCKV 3 · ANL 6 · PLT 3`) tampil
langsung di daftar. Inilah "informasi induk" yang Anda minta — penanggung
jawab langsung tahu isi sampel tanpa mengkliknya lebih dulu.

Tab status di kepala halaman: **Menunggu · Direkomendasikan · Bersyarat ·
Tidak · Semua**, sama seperti Verifikasi.

---

## 4. Panel tengah — ringkasan klasifikasi

Bagian ini menjawab permintaan "jangan banyak klik". Begitu sampel dipilih,
seluruh induk informasinya sudah terbaca tanpa membuka apa pun.

```
FS0926-0001   [Trial Produksi]   ⏱ Menunggu Finalisasi      [⇱ Lifecycle]
NO. PO             BATCH   MESIN         PRODUK
PRD0626-00001      1       ⚙ AUTOCLAVE   LIFE CAT 85GR CHICKEN KITTEN

┌─ RINGKASAN KLASIFIKASI ──────────────────────────────────────────────┐
│                                                                       │
│  ┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐         │
│  │ 👁 LOOK VIEW    │ │ 🧪 ANALISA LAB  │ │ ❤ PALATABILITAS │         │
│  │                 │ │                 │ │                 │         │
│  │   3 analisa     │ │   6 analisa     │ │   3 analisa     │         │
│  │   ✓ semua layak │ │   ✓ semua layak │ │   komparatif    │         │
│  │                 │ │                 │ │   1 pembanding  │         │
│  │ ✓ Direkomendasi │ │ ✓ Direkomendasi │ │ ⚠ Bersyarat     │         │
│  │   Jati · 25 Sep │ │   Ratna · 25 Sep│ │   Roby · 25 Sep │         │
│  └─────────────────┘ └─────────────────┘ └─────────────────┘         │
└───────────────────────────────────────────────────────────────────────┘
```

Tiap kartu menampilkan: jumlah analisa, ringkasan kelayakan, rekomendasi
verifikator, dan siapa yang memberikannya. Kartu diklik untuk membuka
detailnya di bawah — satu klik, bukan berlapis.

### Detail per klasifikasi

Di bawah kartu, detail analisa ditampilkan dengan bentuk yang **sudah
sesuai jenisnya** — sama persis dengan layar Verifikasi:

- **Look View & Analisa Lab** → tabel kelayakan
  (Jenis Analisa · Hasil · Kriteria Kelayakan · Kelayakan)
- **Palatabilitas** → blok per jenis analisa, parameter sebagai kolom,
  pembanding sebagai baris, tanpa kolom kelayakan

```
┌─ 🧪 ANALISA LAB ─────────────────────────────────────────────────────┐
│ JENIS ANALISA      HASIL    KRITERIA KELAYAKAN      KELAYAKAN        │
│ PROTEIN ANALYSIS   4,35     3,0 – 6,0               ✓ Layak          │
│ ASH ANALYSIS       11,20    0,0 – 20,0              ✓ Layak          │
│ …                                                                     │
└───────────────────────────────────────────────────────────────────────┘

┌─ ❤ PALATABILITAS ────────────────────────────────────────────────────┐
│ 🧪 RESPONDEN MEMAKAN                    Dewi Anzani · 24 Sep 10:00   │
│ ┌────────────┬──────────┬──────────┬──────────┬────────┐            │
│ │ PEMBANDING │ Pilihan  │ Pilihan  │ Tdk      │ Sampel │ →          │
│ │            │ Sampel   │ Kontrol  │ Memilih  │ (%)    │            │
│ ├────────────┼──────────┼──────────┼──────────┼────────┤            │
│ │PEMBANDING 1│ 54,7     │ 7        │ 0        │ 3      │            │
│ └────────────┴──────────┴──────────┴──────────┴────────┘            │
└───────────────────────────────────────────────────────────────────────┘
```

### Footer — keputusan finalisasi

```
ℹ Keputusan finalisasi tersimpan permanen sebagai jejak audit
                                        [ Tetapkan Keputusan Finalisasi ]
```

---

## 5. Modal keputusan

Tiga tingkat, sama dengan Verifikasi, dibaca dari master.

```
┌─ Keputusan Finalisasi ───────────────────────────────────────── ✕ ─┐
│ 1 sampel akan difinalisasi                                          │
├─────────────────────────────────────────────────────────────────────┤
│ AUTOCLAVE · 12 analisa · LIFE CAT 85GR CHICKEN KITTEN               │
│                                                                      │
│ ⚠ Rekomendasi verifikator:                                          │
│   Look View ✓ Direkomendasikan · Analisa Lab ✓ Direkomendasikan      │
│   Palatabilitas ⚠ Bersyarat                                          │
│   → Sistem menyarankan: Direkomendasikan Bersyarat                  │
│                                                                      │
│ Tingkat keputusan                                                    │
│ ○ ✓ Direkomendasikan            catatan opsional                    │
│   Seluruh klasifikasi direkomendasikan verifikator.                 │
│   → Dapat diteruskan ke rilis                                       │
│                                                                      │
│ ◉ ⚠ Direkomendasikan Bersyarat  catatan wajib (min 15)              │
│   Terdapat catatan yang perlu diperhatikan, namun masih dapat        │
│   diteruskan dengan justifikasi tertulis.                            │
│   → Dapat diteruskan · Perlu tindak lanjut                          │
│                                                                      │
│ ○ ✕ Tidak Direkomendasikan      catatan wajib (min 15)              │
│   Hasil tidak memenuhi. Tidak layak diteruskan tanpa tindak lanjut.  │
│                                                                      │
│ Justifikasi / catatan          wajib — minimal 15 karakter          │
│ ┌─────────────────────────────────────────────────────────────────┐ │
│ │ Palatabilitas direkomendasikan bersyarat oleh verifikator…      │ │
│ └─────────────────────────────────────────────────────────────────┘ │
│                                                       32 / 15 ✓     │
├─────────────────────────────────────────────────────────────────────┤
│                              [ Batal ]  [ Simpan Keputusan ]        │
└─────────────────────────────────────────────────────────────────────┘
```

**Saran sistem** dihitung dari rekomendasi verifikator, dengan aturan
tingkat terendah menang:

| Rekomendasi verifikator | Saran finalisasi |
|---|---|
| semua Direkomendasikan | Direkomendasikan |
| ada satu Bersyarat | Direkomendasikan Bersyarat |
| ada satu Tidak Direkomendasikan | Tidak Direkomendasikan |

Saran hanya mempraselecsi; penanggung jawab tetap bebas memilih tingkat
lain — sejalan dengan prinsip modul ini yang **mencatat, bukan memblokir**.

---

## 6. Panel kanan — Sample Lifecycle

Tidak berubah dari Verifikasi, hanya bertambah satu tahap di ujung:

```
Registrasi Sampel              Selesai
Validasi — Look View           Selesai
Verifikasi — Look View         ✓ Direkomendasikan
Validasi — Analisa Lab         Selesai
Verifikasi — Analisa Lab       ✓ Direkomendasikan
Validasi — Uji Palatabilitas   Selesai
Verifikasi — Uji Palatabilitas ⚠ Bersyarat
Finalisasi Sampel              ⚠ Direkomendasikan Bersyarat
                               Vengine · 25 Sep 2026 14:20
```

---

## 7. Tabel baru

Tabel lama `N_EMI_LAB_Hasil_Uji_Validasi_Final` **tidak diubah** — modul
lain masih memakainya, dan `Flag_Ok` tetap diisi demi kesinambungan.

Ditambahkan satu tabel berdampingan:

### `N_EMI_LAB_Finalisasi_Header`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `Id_Finalisasi` | int identity | kunci utama |
| `No_Sampel` | varchar(30) | sampel yang difinalisasi |
| `No_Po`, `No_Split_Po`, `No_Batch` | | salinan identitas PO |
| `Kode_Barang`, `Nama_Barang` | | salinan identitas produk |
| `Id_Mesin`, `Nama_Mesin` | | mesin — pembeda antar sampel ber-PO sama |
| `Kode_Status` | varchar(20) | REKOMENDASI / REKOM_BERSYARAT / TIDAK_REKOM |
| `Kode_Keputusan` | varchar(30) | merujuk master keputusan |
| `Catatan` | varchar(1000) | justifikasi |
| `Jumlah_Klasifikasi` | int | berapa klasifikasi tercakup |
| `Jumlah_Analisa` | int | total JENIS analisa (bukan baris) |
| `Id_User`, `Nama_User` | | penanggung jawab |
| `Tanggal_Keputusan`, `Jam_Keputusan` | | |
| `Revisi_Ke` | int | naik bila keputusan diubah |
| `Dibuat_Pada`, `Diubah_Pada` | datetime | |

### `N_EMI_LAB_Finalisasi_Detail`

Satu baris per klasifikasi, merekam rekomendasi verifikator saat
difinalisasi — supaya jejaknya tidak hilang bila verifikasi direvisi.

| Kolom | Keterangan |
|---|---|
| `Id_Finalisasi_Detail` | kunci utama |
| `Id_Finalisasi` | induk |
| `Kode_Aktivitas_Lab`, `Nama_Aktivitas` | klasifikasi |
| `Jumlah_Analisa`, `Jumlah_Tidak_Layak` | ringkasan saat itu |
| `Kode_Status_Verifikasi` | rekomendasi verifikator |
| `Id_User_Verifikator`, `Nama_Verifikator` | |
| `Tanggal_Verifikasi`, `Jam_Verifikasi` | |

### `N_EMI_LAB_Finalisasi_Riwayat`

Sama pola dengan riwayat verifikasi: tiap perubahan keputusan menambah
baris, tidak menimpa yang lama.

**Master keputusan dipakai bersama.** Tidak dibuat master baru —
`N_EMI_LAB_Verifikasi_Keputusan` sudah berisi tiga tingkat yang sama,
termasuk aturan wajib-catatan dan panjang minimalnya.

---

## 8. Cacat modul lama yang jangan diulang

Penelusuran modul finalisasi lama menemukan beberapa hal yang sebaiknya
tidak diwariskan. Dicatat di sini supaya tidak terulang.

| Cacat | Letak | Akibat |
|---|---|---|
| `store()` dan `storeBulk()` menghitung `Flag_Ok` **berbeda** | `:643-653` vs `:930-932` | Satu sampel bisa menghasilkan keputusan berbeda tergantung difinalisasi satuan atau massal. Satuan hanya menilai analisa lab pada mesin FG; massal menilai seluruh klasifikasi. |
| `store()` **diam-diam sukses tanpa menyimpan** | `:641`, `if` tanpa `else` | Bila mesin tidak bertanda `Flag_FG='Y'`, seluruh blok penyimpanan dilewati namun respons tetap 200 "Data Berhasil Disimpan". |
| `Flag_Multi_QrCode = 'Y'` di-hardcode pada daftar | `:273` | Sampel single-QR tidak pernah muncul, padahal saringan QR di layar menawarkan pilihan Single. |
| Syarat berat baru diperiksa saat menyimpan | `:484-600` | Sampel tampil di daftar lalu ditolak 422 saat tombol ditekan — sumber keluhan "kenapa gagal padahal muncul". |
| Typo URL `/finalisai/` | rute | Kurang satu huruf; perlu diputuskan apakah diperbaiki dengan pengalihan. |

**Yang justru layak dipakai ulang:** `app/Services/JejakValidasiService.php`
sudah menjadi satu pintu pencatatan jejak dan bersifat idempoten. Modul baru
sebaiknya memanggilnya, bukan membuat pencatatan sendiri.

---

## 9. Yang perlu diputuskan sebelum dibangun

1. **Cakupan mesin.** Rancangan ini menganggap finalisasi hanya untuk
   AUTOCLAVE, sesuai arahan. Perlu dipastikan: apakah pembatasan itu
   permanen, atau AUTOCLAVE hanya tahap pertama?

2. **Syarat masuk antrean.** Rancangan ini mensyaratkan seluruh klasifikasi
   sudah diverifikasi. Perlu ditegaskan apa yang terjadi bila satu
   klasifikasi bertanda *Tidak Direkomendasikan* — tetap boleh difinalisasi
   (dengan tingkat Tidak Direkomendasikan), atau ditahan di antrean?

   Catatan: `N_EMI_LAB_Verifikasi_Header` menyimpan **satu baris per
   (sampel x klasifikasi)**, sehingga satu sampel dapat memiliki tiga
   rekomendasi berbeda. Aturan penggabungannya perlu ditetapkan — usulan
   pada bagian 5 memakai "tingkat terendah menang".

3. **Sampel single-QR.** Modul lama menyaring hanya multi-QR. Perlu
   ditegaskan apakah modul baru juga demikian, atau keduanya dilayani.

4. **Hubungan dengan `Flag_Ok` lama.** Usulan: `REKOMENDASI` dan
   `REKOM_BERSYARAT` → `'Y'`, `TIDAK_REKOM` → `'T'`, supaya modul lama tetap
   berjalan. Perlu persetujuan bahwa pemetaan ini dapat diterima.

---

## 10. Yang tidak berubah

- Modul lama tidak disentuh; seluruhnya berkas baru dan tabel baru.
- `N_EMI_LAB_Hasil_Uji_Validasi_Final` tetap diisi seperti biasa.
- Tidak ada tahapan yang dipotong — finalisasi tetap sesudah verifikasi.
