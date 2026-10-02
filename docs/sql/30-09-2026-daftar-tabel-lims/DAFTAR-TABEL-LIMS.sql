-- =================================================================================================
-- DAFTAR-TABEL-LIMS.sql
-- Tanggal : 30-09-2026
-- Modul   : LIMS (emi-lab-pembaharuan) - daftar seluruh tabel & view beserta keterangannya
--
-- TUJUAN:
--   Satu berkas rujukan untuk melihat isi setiap tabel yang dipakai LIMS: SELECT * per tabel,
--   didahului keterangan fungsi, kolom penting, relasi, catatan cara membaca data, jumlah baris
--   (demo & produksi), berkas kode yang memakainya, dan struktur kolom lengkap.
--
-- CAKUPAN:
--   102 objek (93 tabel, 9 view) dalam 14 bagian mengikuti alur LIMS:
--   semua tabel ber-prefiks N_EMI_LAB_, N_EMI_LIMS_, N_LIMS_, EMI_LAB_, dan N_EMI_Lab_; view laporan
--   LIMS, serta tabel/view ERP yang dibaca kode LIMS (mesin, order produksi, barang, parameter QC).
--   Tabel yang namanya kebetulan mengandung "lab" tetapi bukan LIMS dicatat di Lampiran B.
--
-- SIFAT:
--   Hanya SELECT (baca saja). Tidak ada INSERT/UPDATE/DELETE/DDL. Aman dijalankan di demo
--   (emi_tm_demo) maupun produksi (emi_db_real) dengan akun baca.
--
-- CARA PAKAI:
--   - Pilih database lebih dulu (USE ... sengaja tidak ditulis), lalu jalankan PER BLOK: sorot
--     satu SELECT dan tekan F5 di SSMS.
--   - Setiap SELECT diikuti GO sehingga menjadi batch sendiri: bila satu tabel tidak ada di
--     database yang dipilih, tabel lain tetap bisa dijalankan.
--   - Jangan menjalankan seluruh berkas sekaligus di produksi: beberapa tabel berisi ratusan
--     ribu baris (bertanda [BESAR]). Untuk mengintip isinya, ganti sementara SELECT * dengan
--     SELECT TOP (100) *.
--
-- PENANDA:
--   [BESAR]       >= 100.000 baris di produksi
--   [HANYA DEMO]  belum ada di produksi (per 30-09-2026) - lewati saat menjalankan di produksi
--   [SENSITIF]    berisi kata sandi/PIN/data sesi - jangan membagikan hasilnya
--   [ERP]         milik ERP, hanya dibaca LIMS
--
-- STATUS MIGRASI PEMBAHARUAN DI PRODUKSI (diperiksa 30-09-2026, baca saja):
--   - docs/sql/24-09-2026            : SUDAH (N_EMI_LAB_Hasil_Uji_Approval_Aktivitas ada, masih
--                                      kosong; kolom baru Hasil_Uji_Validasi_Detail_Final ada).
--   - docs/sql/24-09-2026-verifikasi : BELUM (6 tabel N_EMI_LAB_Verifikasi_* belum ada).
--   - docs/sql/26-09-2026-lifecycle  : BELUM (kolom baru Uji_Sampel_Resampling_Log dan
--                                      Berkas_Uji_Lab belum ada).
--
-- RINGKASAN:
--   Hanya di demo (23) : N_EMI_LAB_Barang_Analisa_Collection,
--                        N_EMI_LAB_Uji_Sample_Detail_Sementara,
--                        EMI_LAB_Uji_Sample_Detail_Perhitungan, N_EMI_LAB_Verifikasi_Status,
--                        N_EMI_LAB_Verifikasi_Keputusan, N_EMI_LAB_Verifikasi_Kewenangan,
--                        N_EMI_LAB_Verifikasi_Header, N_EMI_LAB_Verifikasi_Detail,
--                        N_EMI_LAB_Verifikasi_Riwayat, N_EMI_LIMS_Berkas_Uji_Lab_Temp,
--                        N_EMI_Divisi_Mesin, N_EMI_View_Hasil_Uji_Laboratorium_Rpt,
--                        V_LIMS_Kelayakan_Uji, V_LIMS_Rekap_Pengguna,
--                        N_EMI_Lab_Monitoring_Frans_Master_Lokasi,
--                        N_EMI_Lab_Monitoring_Frans_Master_Parameter,
--                        N_EMI_Lab_Monitoring_Frans_Mesin_Config,
--                        N_EMI_Lab_Monitoring_Frans_Parent, N_EMI_Lab_Monitoring_Frans_Session,
--                        N_EMI_Lab_Monitoring_Frans_Detail, N_EMI_Lab_Monitoring_Frans_Parameter,
--                        N_EMI_Lab_Monitoring_Frans_Foto, N_EMI_Lab_Monitoring_Frans_Validasi
--   Besar di produksi (4) : N_EMI_LAB_Uji_Sampel, N_EMI_LAB_Uji_Sampel_Detail,
--                           N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail,
--                           N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail
--
-- DAFTAR ISI:
--    1. MASTER KLASIFIKASI, JENIS ANALISA & STANDAR MUTU (12)
--    2. MASTER BARANG YANG DIANALISA (4)
--    3. REGISTRASI SAMPEL PRODUKSI & PERANGKAT (5)
--    4. UJI SAMPEL - INPUT HASIL, FOTO, RESAMPLING & JEJAK AKTIVITAS (12)
--    5. UJI PALATABILITAS (3)
--    6. VALIDASI & FINALISASI (5)
--    7. VERIFIKASI HASIL ANALISA (PEMBAHARUAN) (6)
--    8. MODUL FORMULATOR (SAMPEL TRIAL R&D) (17)
--    9. PENGGUNA, ROLE & HAK AKSES (11)
--   10. CETAK LABEL QR (3)
--   11. SISTEM APLIKASI (4)
--   12. DATA ERP YANG DIBACA LIMS (8)
--   13. VIEW LAPORAN (HANYA DI DEMO) (3)
--   14. PROTOTIPE MONITORING SUHU PROSES (HANYA DI DEMO) (9)
--   Lampiran A. Dirujuk kode, tidak ada di database
--   Lampiran B. Tidak dimasukkan (bukan tabel LIMS)
--   Lampiran C. Query bantu: jumlah baris & struktur kolom
--
-- Jumlah baris diambil dari sys.partitions pada 30-09-2026; angka sebenarnya terus berubah.
-- =================================================================================================


-- =================================================================================================
-- 1. MASTER KLASIFIKASI, JENIS ANALISA & STANDAR MUTU
--   Apa yang diuji, dengan alat apa, dihitung bagaimana, dan kapan hasilnya dianggap
--   layak.
-- =================================================================================================

-- -------------------------------------------------------------------------------------------------
-- 1.1  N_EMI_LIMS_Klasifikasi_Aktivitas_Lab                                                   TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Master klasifikasi aktivitas lab: LCKV (Look View, urutan 1), ANL (Analisa Lab, urutan
--            2), PLT (Uji Palatabilitas, urutan 3). Menentukan urutan tahapan uji dan
--            ketergantungan antar-klasifikasi.
-- Kolom    : Butuh_Hasil_Dari = klasifikasi yang harus selesai lebih dulu (ANL butuh LCKV, PLT
--            butuh ANL); Flag_Wajib = wajib diuji; Flag_Butuh_Pembanding = 'Y' bila penilaiannya
--            memakai produk pembanding (PLT).
-- Relasi   : Kode_Aktivitas_Lab dirujuk N_EMI_LAB_Jenis_Analisa, Verifikasi_Header,
--            Hasil_Uji_Approval_Aktivitas.
-- Kunci    : primary key Id_Klasifikasi_Aktivitas_Lab
-- Baris    : demo 3 | produksi 3
-- Dipakai  : FinalisasiLabProduksiTrialController, FinalisasiSandboxController,
--            FormulatorDashboardController, FormulatorValidasiHirarkiController,
--            JejakValidasiService, JenisAnalisaController (+6 berkas lain); migrasi
--            2026_05_20_000001_create_palatabilitas_tables
-- Struktur : 7 kolom - Id_Klasifikasi_Aktivitas_Lab int, Kode_Aktivitas_Lab varchar(10),
--            Nama_Aktivitas varchar(255), Urutan int, Flag_Wajib char(1), Butuh_Hasil_Dari
--            varchar(255), Flag_Butuh_Pembanding char(1)
SELECT * FROM dbo.N_EMI_LIMS_Klasifikasi_Aktivitas_Lab;
GO

-- -------------------------------------------------------------------------------------------------
-- 1.2  N_EMI_LAB_Jenis_Analisa                                                                TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Master jenis analisa (parameter uji), mis. ASH ANALYSIS, PROTEIN ANALYSIS, TEKSTUR
--            POUCH (QA). Satu baris per jenis analisa; menjadi rujukan hampir semua tabel uji.
-- Kolom    : Kode_Analisa & Jenis_Analisa = kode & nama; Kode_Aktivitas_Lab = klasifikasinya
--            (LCKV/ANL/PLT); Flag_Perhitungan = 'Y' bila hasil dihitung dari rumus
--            (N_EMI_LAB_Perhitungan), selain itu dinilai dengan kriteria teks; Flag_Foto = analisa
--            berfoto; Id_Mesin = alat uji (N_EMI_LAB_Mesin_Analisa.No_Urut); Kode_Role = pemilik
--            data (LAB/FLM).
-- Relasi   : id dirujuk sebagai Id_Jenis_Analisa di Uji_Sampel, Barang_Analisa, Standar_Rentang,
--            Binding_Jenis_Analisa, Role_Konten_Access, dll.
-- Kunci    : primary key id
-- Baris    : demo 61 | produksi 52
-- Dipakai  : AuthController, BarangAnalisaController, BarangUjiMasterController,
--            BindingJenisAnalisaController, BypassLimsController, DaftarAnalisaKurangExport (+30
--            berkas lain)
-- Struktur : 12 kolom - id int, Kode_Analisa varchar(255), Jenis_Analisa varchar(255), Id_Mesin
--            int, Flag_Perhitungan char(1), Created_At datetime, Updated_At datetime,
--            Sifat_Kegiatan varchar(40), Kode_Role varchar(50), Id_User varchar(15),
--            Kode_Aktivitas_Lab varchar(255), Flag_Foto char(1)
SELECT * FROM dbo.N_EMI_LAB_Jenis_Analisa;
GO

-- -------------------------------------------------------------------------------------------------
-- 1.3  N_EMI_LAB_Jenis_Analisa_Berkala                                                        TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Pemetaan analisa berkala: analisa induk (Id_Jenis_Analisa) beserta sub-analisanya
--            (Id_Sub_Jenis_Analisa), keduanya dari N_EMI_LAB_Jenis_Analisa. Dipakai saat input uji
--            untuk memunculkan sub-analisa yang terkait.
-- Kunci    : primary key Id_Jenis_Analisa_Berkala
-- Baris    : demo 6 | produksi 4
-- Dipakai  : DashboardController, FormulatorTrialSampelController, JenisAnalisaBerkalaController,
--            JenisAnalisaController, UjiSampelController
-- Struktur : 8 kolom - Id_Jenis_Analisa_Berkala int, Kode_Perusahaan varchar(3), Id_Jenis_Analisa
--            int, Id_Sub_Jenis_Analisa int, Tanggal datetime, Jam varchar(8), Id_User varchar(15),
--            Kode_Role varchar(50)
SELECT * FROM dbo.N_EMI_LAB_Jenis_Analisa_Berkala;
GO

-- -------------------------------------------------------------------------------------------------
-- 1.4  N_EMI_LAB_Jenis_Analisa_Opsional                                                       TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Daftar kode analisa yang bersifat opsional - tidak wajib lengkap saat
--            validasi/finalisasi (contoh: HOMOGENITAS, KONSUMSI, QA-LV-WARNA).
-- Catatan  : Dicocokkan lewat Kode_Analisa (bukan id). Di demo ada kode ganda (QA-LV-WARNA).
-- Kunci    : primary key Id_Jenis_Analisa_Opsional
-- Baris    : demo 10 | produksi 31
-- Dipakai  : FinalisasiLabProduksiTrialController, UjiValidasiFinalController
-- Struktur : 2 kolom - Id_Jenis_Analisa_Opsional int, Kode_Analisa varchar(255)
SELECT * FROM dbo.N_EMI_LAB_Jenis_Analisa_Opsional;
GO

-- -------------------------------------------------------------------------------------------------
-- 1.5  N_EMI_LAB_Mesin_Analisa                                                                TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Master alat/instrumen uji di laboratorium (mis. HX204 Mettler Toledo, HC103 Mettler
--            Toledo). Bukan mesin produksi - mesin produksi ada di EMI_Master_Mesin.
-- Relasi   : No_Urut dirujuk N_EMI_LAB_Jenis_Analisa.Id_Mesin.
-- Catatan  : Demo berisi beberapa data uji coba ("Test SAJA", "TES BRO").
-- Kunci    : primary key No_Urut
-- Baris    : demo 7 | produksi 10
-- Dipakai  : BindingJenisAnalisaController, FormulatorTrialSampelController,
--            JenisAnalisaController, MesinAnalisa, MesinAnalisaController, PerhitunganController
--            (+1 berkas lain)
-- Struktur : 9 kolom - No_Urut int, Divisi_Mesin varchar(255), Kode_Perusahaan varchar(3),
--            Nama_Mesin varchar(255), Keterangan varchar(255), Kode_Role varchar(50), Tanggal
--            datetime, Jam varchar(8), Id_User varchar(15)
SELECT * FROM dbo.N_EMI_LAB_Mesin_Analisa;
GO

-- -------------------------------------------------------------------------------------------------
-- 1.6  N_EMI_LAB_Perhitungan                                                                  TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Rumus perhitungan hasil untuk jenis analisa ber-Flag_Perhitungan - hasil akhir
--            dihitung dari parameter masukan penguji.
-- Kolom    : Rumus = ekspresi berisi kode parameter QC; Nama_Kolom = label kolom; Hasil_Perhitungan
--            = nama hasilnya.
-- Relasi   : Id_Jenis_Analisa -> N_EMI_LAB_Jenis_Analisa; id dirujuk Uji_Sampel.Id_Perhitungan dan
--            Standar_Rentang.Id_Perhitungan.
-- Kunci    : primary key id
-- Baris    : demo 27 | produksi 19
-- Dipakai  : ExportRekapSampelJob, FormulatorTrialSampelController,
--            FormulatorValidasiHirarkiController, MonitoringAnalisaController, Perhitungan,
--            PerhitunganController (+2 berkas lain)
-- Struktur : 10 kolom - id int, Id_Jenis_Analisa int, Rumus varchar(255), Nama_Kolom varchar(225),
--            Created_At datetime, Updated_At datetime, Hasil_Perhitungan int, Kode_Perusahaan
--            varchar(3), Kode_Role varchar(50), Id_User varchar(15)
SELECT * FROM dbo.N_EMI_LAB_Perhitungan;
GO

-- -------------------------------------------------------------------------------------------------
-- 1.7  N_EMI_LAB_Binding_Jenis_Analisa                                                        TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Pengikat jenis analisa dengan parameter QC-nya (EMI_Quality_Control): daftar isian
--            yang harus diinput penguji untuk satu analisa, berurutan menurut Id_Quality_Control.
-- Catatan  : Analisa yang belum di-binding (mis. TINGKAT KONSUMSI) tetap punya parameter lewat
--            N_EMI_LAB_Uji_Sampel_Detail; tampilan membaca nama parameternya dari sana.
-- Kunci    : primary key id
-- Baris    : demo 134 | produksi 119
-- Dipakai  : BindingJenisAnalisa, BindingJenisAnalisaController, RincianHasilAnalisaService,
--            StandarRentangController
-- Struktur : 8 kolom - id int, Id_Jenis_Analisa int, Id_Quality_Control int, Keterangan
--            varchar(255), Created_At datetime, Updated_At datetime, Id_User varchar(15), Kode_Role
--            varchar(50)
SELECT * FROM dbo.N_EMI_LAB_Binding_Jenis_Analisa;
GO

-- -------------------------------------------------------------------------------------------------
-- 1.8  N_EMI_LAB_Standar_Rentang                                                              TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Standar mutu numerik (batas minimum-maksimum) per jenis analisa, bisa dispesifikkan
--            per barang dan per mesin produksi. Dasar penilaian layak/tidak layak analisa
--            perhitungan.
-- Kolom    : Range_Awal/Range_Akhir = batas bawah/atas; Kode_Barang & Id_Master_Mesin = cakupan
--            standar; Id_Perhitungan = rumus yang dinilai.
-- Kunci    : primary key Id_Standar_Rentang
-- Baris    : demo 16 | produksi 441
-- Dipakai  : FormulatorTrialSampelController, RincianHasilAnalisaService, StandarRentangController,
--            UjiSampelController
-- Struktur : 12 kolom - Id_Standar_Rentang int, Kode_Perusahaan varchar(3), Id_Jenis_Analisa int,
--            Kode_Barang varchar(30), Id_Master_Mesin int, Id_Perhitungan int, Range_Awal float,
--            Range_Akhir float, Tanggal datetime, Jam varchar(8), Id_User varchar(15), Kode_Role
--            varchar(50)
SELECT * FROM dbo.N_EMI_LAB_Standar_Rentang;
GO

-- -------------------------------------------------------------------------------------------------
-- 1.9  N_EMI_LAB_Standar_Rentang_Non_Perhitungan                                              TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Kriteria kelayakan untuk analisa non-perhitungan (hasil berupa teks/pilihan), mis.
--            TEKSTUR POUCH: 'Lembut' = layak, 'Sangat Lembut' = tidak layak.
-- Kolom    : Nilai_Kriteria & Keterangan_Kriteria = teks hasil; Flag_Layak = 'Y' layak / 'T' tidak
--            layak; Flag_Aktif.
-- Kunci    : primary key Id_Standar_Rentang_Non_Perhitungan
-- Baris    : demo 108 | produksi 288
-- Dipakai  : ExportRekapSampelJob, FormulatorTrialSampelController,
--            FormulatorValidasiHirarkiController, MonitoringAnalisaController,
--            RincianHasilAnalisaService, SampelDummyService (+2 berkas lain)
-- Struktur : 11 kolom - Id_Standar_Rentang_Non_Perhitungan int, Kode_Perusahaan varchar(3),
--            Id_Jenis_Analisa int, Nilai_Kriteria float, Keterangan_Kriteria varchar(255), Tanggal
--            datetime, Jam varchar(8), Id_User varchar(15), Flag_Layak char(1), Flag_Aktif char(1),
--            Kode_Role varchar(50)
SELECT * FROM dbo.N_EMI_LAB_Standar_Rentang_Non_Perhitungan;
GO

-- -------------------------------------------------------------------------------------------------
-- 1.10  EMI_Quality_Control                                                             TABEL [ERP]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Master parameter Quality Control milik ERP (dipakai bersama modul formula): kode uji,
--            keterangan, satuan, target, dan tipe isian. Di LIMS menjadi daftar parameter masukan
--            tiap analisa.
-- Kolom    : Kode_Uji, Keterangan, Satuan, Target, Range_Awal/Range_Akhir; Id_Kategori_Komponen =
--            tipe isian (EMI_Kategori_Komponen); Flag_Tampil_Formula/Bahan/Dekstop/Android = tempat
--            parameter ditampilkan.
-- Relasi   : Id_QC_Formula dirujuk Binding_Jenis_Analisa.Id_Quality_Control dan
--            Uji_Sampel_Detail.Id_Quality_Control.
-- Kunci    : primary key Id_QC_Formula
-- Baris    : demo 62 | produksi 172
-- Dipakai  : BindingJenisAnalisaController, ExportRekapSampelJob, FormulatorTrialSampelController,
--            FormulatorValidasiHirarkiController, MonitoringAnalisaController,
--            PerhitunganController (+3 berkas lain)
-- Struktur : 15 kolom - Id_QC_Formula int, Kode_Perusahaan varchar(3), Kode_Uji varchar(100),
--            Keterangan varchar(100), Satuan varchar(10), Target int, Flag_Tampil_Formula char(1),
--            Flag_Tampil_Bahan char(1), Id_Kategori_Komponen int, Flag_Tampil_Android char(1),
--            Flag_Tampil_Dekstop char(1), Range_Awal int, Range_Akhir int, Flag_Ket_Lewat_Range
--            char(1), Kategori_Value varchar(30)
SELECT * FROM dbo.EMI_Quality_Control;
GO

-- -------------------------------------------------------------------------------------------------
-- 1.11  EMI_Kategori_Komponen                                                           TABEL [ERP]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Tipe komponen isian parameter QC: Input (angka/teks bebas), Switch (pilihan), Slider,
--            Perhitungan. Menentukan kontrol isian yang tampil di form uji.
-- Kolom    : Flag_Option / Flag_Input / Flag_Slider.
-- Kunci    : primary key Id_Kategori_Komponen
-- Baris    : demo 5 | produksi 3
-- Dipakai  : ExportRekapSampelJob, FormulatorTrialSampelController, StandarRentangController,
--            UjiSampelController
-- Struktur : 6 kolom - Id_Kategori_Komponen int, Kode_Perusahaan varchar(3), Keterangan
--            varchar(50), Flag_Option char(1), Flag_Input char(1), Flag_Slider char(1)
SELECT * FROM dbo.EMI_Kategori_Komponen;
GO

-- -------------------------------------------------------------------------------------------------
-- 1.12  EMI_Switch                                                                      TABEL [ERP]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Pilihan nilai untuk parameter QC bertipe Switch, mis. parameter 50: ADA / TIDAK ADA;
--            parameter 55: OK / NG.
-- Kolom    : Id_QC_Formula = parameternya; Flag_Default = pilihan bawaan; Flag_Tampil_Lims =
--            ditampilkan di LIMS.
-- Kunci    : primary key Id_Switch
-- Baris    : demo 59 | produksi 267
-- Dipakai  : FormulatorTrialSampelController, StandarRentangController, UjiSampelController
-- Struktur : 8 kolom - Id_Switch int, Kode_Perusahaan varchar(3), Id_QC_Formula int, Flag_Default
--            char(1), Keterangan varchar(50), Flag_Keterangan char(1), Label_Keterangan
--            varchar(255), Flag_Tampil_Lims char(1)
SELECT * FROM dbo.EMI_Switch;
GO

-- =================================================================================================
-- 2. MASTER BARANG YANG DIANALISA
--   Analisa apa saja yang wajib diuji untuk tiap produk di tiap mesin produksi.
-- =================================================================================================

-- -------------------------------------------------------------------------------------------------
-- 2.1  N_EMI_LAB_Barang_Analisa                                                               TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Daftar jenis analisa yang wajib diuji untuk tiap barang (produk) per mesin produksi -
--            menentukan analisa yang muncul saat sampel produk itu diuji.
-- Kolom    : Kode_Barang + Id_Master_Mesin + Id_Jenis_Analisa; Flag_Aktif; Kode_Role = pemilik
--            (LAB/FLM); Id_User_Menginput.
-- Relasi   : Kode_Barang -> N_EMI_View_Barang; Id_Master_Mesin -> EMI_Master_Mesin;
--            Id_Jenis_Analisa -> N_EMI_LAB_Jenis_Analisa.
-- Kunci    : primary key id
-- Baris    : demo 4.460 | produksi 39.021
-- Dipakai  : BarangAnalisa, BarangAnalisaController, BarangUjiMasterController,
--            DaftarAnalisaKurangExport, DashboardController, FinalisasiLabProduksiTrialController
--            (+9 berkas lain)
-- Struktur : 11 kolom - id int, Kode_Perusahaan varchar(3), Id_Jenis_Analisa int, Kode_Barang
--            varchar(30), Id_Master_Mesin int, Id_User varchar(15), Tanggal datetime, Jam
--            varchar(8), Id_User_Menginput varchar(15), Flag_Aktif char(1), Kode_Role varchar(50)
SELECT * FROM dbo.N_EMI_LAB_Barang_Analisa;
GO

-- -------------------------------------------------------------------------------------------------
-- 2.2  N_EMI_LAB_Barang_Analisa_Master                                                        TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Templat "barang uji master": jenis analisa bawaan per mesin/role yang disebarkan
--            otomatis ke semua barang (seluruh varian) oleh SyncBarangUjiMasterJob.
-- Kunci    : primary key id
-- Baris    : demo 1 | produksi 1
-- Dipakai  : BarangUjiMasterController, SyncBarangUjiMasterJob
-- Struktur : 10 kolom - id int, Id_User varchar(50), Id_Jenis_Analisa int, Id_Master_Mesin int,
--            Kode_Role varchar(20), Kode_Perusahaan varchar(20), Flag_Aktif char(1), Tanggal date,
--            Jam time, Id_User_Menginput varchar(50)
SELECT * FROM dbo.N_EMI_LAB_Barang_Analisa_Master;
GO

-- -------------------------------------------------------------------------------------------------
-- 2.3  N_EMI_LAB_Barang_Analisa_Sinkron                                                       TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Penanda barang yang sudah disinkronkan dari templat master ke N_EMI_LAB_Barang_Analisa
--            (satu baris per Kode_Barang), agar job sinkronisasi tidak memproses ulang.
-- Kunci    : primary key Kode_Barang
-- Baris    : demo 414 | produksi 1.072
-- Dipakai  : SyncBarangUjiMasterJob
-- Struktur : 5 kolom - Kode_Barang varchar(50), Kode_Perusahaan varchar(20), Tanggal date, Jam
--            time, Id_User_Menginput varchar(50)
SELECT * FROM dbo.N_EMI_LAB_Barang_Analisa_Sinkron;
GO

-- -------------------------------------------------------------------------------------------------
-- 2.4  N_EMI_LAB_Barang_Analisa_Collection                                       TABEL [HANYA DEMO]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Struktur mirip N_EMI_LAB_Barang_Analisa (ditambah Keterangan). Kosong dan tidak
--            dipakai kode - kemungkinan sisa percobaan.
-- Kunci    : primary key id
-- Baris    : demo 0 | produksi TIDAK ADA
-- Dipakai  : tidak dipakai kode aplikasi
-- Struktur : 12 kolom - id bigint, Id_Jenis_Analisa bigint, Kode_Barang nvarchar(50),
--            Id_Master_Mesin bigint, Id_User nvarchar(50), Kode_Role nvarchar(20), Kode_Perusahaan
--            nvarchar(20), Keterangan nvarchar(255), Tanggal date, Jam time, Id_User_Menginput
--            nvarchar(50), Flag_Aktif char(1)
SELECT * FROM dbo.N_EMI_LAB_Barang_Analisa_Collection;
GO

-- =================================================================================================
-- 3. REGISTRASI SAMPEL PRODUKSI & PERANGKAT
--   Sampel diambil dari PO/split/batch produksi, diberi QR, lalu masuk antrean uji.
-- =================================================================================================

-- -------------------------------------------------------------------------------------------------
-- 3.1  N_EMI_LAB_PO_Sampel                                                                    TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Registrasi sampel produksi - satu baris per nomor sampel (No_Sampel, mis. FS0926-0001)
--            yang diambil dari PO/split/batch produksi. Titik awal seluruh alur uji lab.
-- Kolom    : No_Po/No_Split_Po/No_Batch = asal produksi; Kode_Barang; Id_Mesin = mesin produksi;
--            Berat_Sampel, Jumlah_Pcs; Flag_Khusus + Id_Jenis_Analisa_Khusus = sampel khusus untuk
--            analisa tertentu; Flag_Selesai; Flag_Close_Po (+tanggal/jam) dan Alasan_Buka_Ulang_Po
--            (+tanggal/jam); Flag_Input_Waste; Flag_Trial_Produksi = 'Y' untuk sampel trial
--            produksi.
-- Relasi   : No_Sampel dirujuk Uji_Sampel.No_Po_Sampel, PO_Sampel_Multi_QrCode.No_Po_Sampel,
--            Hasil_Uji_Validasi_Final.No_Sampel, Verifikasi_Header.No_Sampel; Id_Mesin ->
--            EMI_Master_Mesin.
-- Kunci    : primary key id
-- Baris    : demo 32 | produksi 15.750
-- Dipakai  : BypassLimsController, DaftarAnalisaKurangExport, DashboardController,
--            ExportRekapSampelJob, FinalisasiLabProduksiTrialController,
--            FinalisasiSandboxController (+22 berkas lain)
-- Struktur : 27 kolom - id int, Kode_Perusahaan varchar(3), No_Po varchar(30), Kode_Barang
--            varchar(30), No_Sampel varchar(30), Status char(1), Tanggal date, Jam varchar(8),
--            No_Split_Po varchar(30), No_Batch int, Id_Mesin int, Keterangan text(2147483647),
--            Id_User varchar(30), Flag_Selesai char(1), Berat_Sampel float, Flag_Khusus char(1),
--            Id_Jenis_Analisa_Khusus int, Jumlah_Pcs int, Flag_Baca char(1), Flag_Close_Po char(1),
--            Tanggal_Close_Po datetime, Jam_Close_Po varchar(8), Alasan_Buka_Ulang_Po
--            text(2147483647), Tanggal_Buka_Ulang_Po datetime, Jam_Buka_Ulang_Po varchar(8),
--            Flag_Input_Waste char(1), Flag_Trial_Produksi char(1)
SELECT * FROM dbo.N_EMI_LAB_PO_Sampel;
GO

-- -------------------------------------------------------------------------------------------------
-- 3.2  N_EMI_LAB_PO_Sampel_Multi_QrCode                                                       TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Sub-sampel (multi QR) dari satu sampel, mis. FS0926-0001-1, -2 - untuk mesin yang
--            mengambil beberapa kemasan per sampel (hampir semua AUTOCLAVE). Resampling pada multi
--            QR pindah ke nomor sub-sampel baru.
-- Kolom    : No_Po_Multi = nomor sub-sampel; No_Po_Sampel = sampel induk;
--            Flag_Khusus/Id_Jenis_Analisa_Khusus; Flag_Selesai.
-- Relasi   : No_Po_Multi dirujuk Uji_Sampel.No_Fak_Sub_Po.
-- Kunci    : primary key Id_Po_Sampel_Multi
-- Baris    : demo 33 | produksi 96.837
-- Dipakai  : BypassLimsController, DashboardController, FormulatorTrialSampelController,
--            POSampelMultiQrCode, POSampleController, QuisyController (+2 berkas lain)
-- Struktur : 11 kolom - Id_Po_Sampel_Multi int, Kode_Perusahaan varchar(3), No_Po_Multi
--            varchar(30), Kode_Barang varchar(30), No_Po_Sampel varchar(30), Status char(1),
--            Tanggal date, Jam varchar(8), Flag_Khusus char(1), Id_Jenis_Analisa_Khusus int,
--            Flag_Selesai char(1)
SELECT * FROM dbo.N_EMI_LAB_PO_Sampel_Multi_QrCode;
GO

-- -------------------------------------------------------------------------------------------------
-- 3.3  N_EMI_LAB_Activity_Produksi_Sampel                                                     TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Log aktivitas produksi terkait sampel: 'Pengambilan Sampel' dan 'Cetak QrCode' per
--            PO/split/batch/mesin, beserta status berhasil.
-- Kolom    : Jenis_Aktivitas, Status_Aktivitas, Flag_Berhasil_Cetak_QrCode, Id_Mesin, Id_User.
-- Kunci    : primary key Id_Activity_Produksi_Sampel
-- Baris    : demo 76 | produksi 31.461
-- Dipakai  : FormulatorCetakUlangQrCodeController, POSampleController, QuisyController
-- Struktur : 12 kolom - Id_Activity_Produksi_Sampel int, No_Po varchar(30), No_Split_Po
--            varchar(30), No_Batch int, Jenis_Aktivitas varchar(100), Status_Aktivitas
--            varchar(100), Keterangan text(2147483647), Tanggal datetime, Id_Mesin int, Jam
--            varchar(8), Flag_Berhasil_Cetak_QrCode char(1), Id_User varchar(30)
SELECT * FROM dbo.N_EMI_LAB_Activity_Produksi_Sampel;
GO

-- -------------------------------------------------------------------------------------------------
-- 3.4  N_EMI_LAB_Identity                                                                     TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Daftar perangkat/komputer yang dikenali LIMS (mis. "Komputer Lab A") berdasarkan
--            Computer_Keys - dipakai halaman registrasi dan cetak QR di lini produksi.
-- Kunci    : primary key id
-- Baris    : demo 6 | produksi 1
-- Dipakai  : BindingIdentityController, FormulatorCetakUlangQrCodeController,
--            FormulatorRegistrasiController, Identity, IdentityController, QuisyController
-- Struktur : 6 kolom - id int, Kode_Perusahaan varchar(3), Computer_Keys varchar(30), Keterangan
--            varchar(22), Created_At datetime, Updated_At datetime
SELECT * FROM dbo.N_EMI_LAB_Identity;
GO

-- -------------------------------------------------------------------------------------------------
-- 3.5  N_EMI_LAB_Binding_Identity                                                             TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Pengikat perangkat (N_EMI_LAB_Identity) dengan mesin produksi (Id_Mesin ->
--            EMI_Master_Mesin): komputer di mesin tertentu otomatis meregistrasi sampel untuk mesin
--            itu.
-- Kunci    : primary key id
-- Baris    : demo 13 | produksi 16
-- Dipakai  : BindingIdentity, BindingIdentityController, FormulatorCetakUlangQrCodeController,
--            FormulatorRegistrasiController, QuisyController
-- Struktur : 6 kolom - id int, Kode_Perusahaan varchar(3), Id_Identity int, Id_Mesin int,
--            Created_At datetime, Updated_At datetime
SELECT * FROM dbo.N_EMI_LAB_Binding_Identity;
GO

-- =================================================================================================
-- 4. UJI SAMPEL - INPUT HASIL, FOTO, RESAMPLING & JEJAK AKTIVITAS
--   Penguji mengisi hasil tiap analisa (draf -> submit), melampirkan foto, dan meminta uji
--   ulang bila perlu.
-- =================================================================================================

-- -------------------------------------------------------------------------------------------------
-- 4.1  N_EMI_LAB_Uji_Sampel                                                           TABEL [BESAR]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Hasil uji sampel - satu baris per nilai hasil analisa (per sampel/sub-sampel, per
--            jenis analisa, per putaran; palatabilitas: per pembanding). Tabel inti LIMS.
-- Kolom    : No_Faktur = nomor faktur uji; No_Po_Sampel = sampel; No_Fak_Sub_Po = sub-sampel (multi
--            QR); Id_Jenis_Analisa; Hasil (angka) atau Nilai_Hasil_String (teks, Flag_String =
--            'Y'); Range_Awal/Range_Akhir = salinan standar saat diuji; Tahapan_Ke = putaran (1, 2,
--            ...); Flag_Resampling; Status_Keputusan_Sampel (menunggu/terima/tolak); Flag_Layak;
--            Flag_Final; Flag_Foto; Id_Perhitungan; Id_Session/Id_Pembanding = palatabilitas;
--            Id_Mesin.
-- Catatan  : Tanpa primary key/index dan No_Faktur tidak unik - jangkarnya No_Po_Sampel. Id_User =
--            pembuat draf (penguji), BUKAN validator. Tahapan_Ke bisa keliru, dan
--            Status_Keputusan_Sampel = 'tolak' tidak dapat dipercaya pada sampel tunggal (validasi
--            putaran berikutnya menimpanya jadi 'terima') - status ditolak diturunkan dari
--            N_EMI_LAB_Uji_Sampel_Resampling_Log.
-- Kunci    : tanpa primary key
-- Baris    : demo 298 | produksi 214.833
-- Dipakai  : BypassLimsController, DaftarAnalisaKurangExport, DashboardController,
--            ExportRekapSampelJob, FinalisasiLabProduksiTrialController,
--            FinalisasiSandboxController (+14 berkas lain); migrasi
--            2026_05_20_000001_create_palatabilitas_tables
-- Struktur : 27 kolom - Kode_Perusahaan varchar(3), No_Faktur varchar(30), No_Po_Sampel
--            varchar(30), No_Fak_Sub_Po varchar(30), Id_Jenis_Analisa int, Hasil float,
--            Flag_Perhitungan char(1), Flag_Multi_QrCode char(1), Status char(1), Tanggal datetime,
--            Jam varchar(8), Id_User varchar(30), Flag_Selesai char(1), Id_Perhitungan int,
--            Range_Awal float, Range_Akhir float, Tahapan_Ke int, Flag_Resampling char(1),
--            Status_Keputusan_Sampel varchar(255), Flag_Layak char(1), Flag_Final char(1), Id_Mesin
--            int, Flag_String char(1), Nilai_Hasil_String varchar(225), Flag_Foto char(1),
--            Id_Session int, Id_Pembanding int
SELECT * FROM dbo.N_EMI_LAB_Uji_Sampel;
GO

-- -------------------------------------------------------------------------------------------------
-- 4.2  N_EMI_LAB_Uji_Sampel_Detail                                                    TABEL [BESAR]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Nilai parameter masukan per faktur uji - angka mentah yang diinput penguji (mis. berat
--            cawan, berat sampel) sebelum dihitung menjadi hasil.
-- Kolom    : No_Faktur_Uji_Sample -> Uji_Sampel.No_Faktur; Id_Quality_Control ->
--            EMI_Quality_Control; Value_Parameter.
-- Kunci    : primary key Id_Uji_Sample_Detail
-- Baris    : demo 214 | produksi 574.389
-- Dipakai  : ExportRekapSampelJob, FormulatorTrialSampelController, MonitoringAnalisaController,
--            RincianHasilAnalisaService, SampelDummyService, UjiSampelController (+1 berkas lain)
-- Struktur : 8 kolom - Id_Uji_Sample_Detail int, Kode_Perusahaan varchar(3), No_Faktur_Uji_Sample
--            varchar(30), Id_Quality_Control int, Value_Parameter float, Tanggal datetime, Jam
--            varchar(8), Id_User varchar(30)
SELECT * FROM dbo.N_EMI_LAB_Uji_Sampel_Detail;
GO

-- -------------------------------------------------------------------------------------------------
-- 4.3  N_EMI_LAB_Uji_Sampel_Sementara                                                         TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Draf hasil uji yang belum disubmit (tersimpan sementara selama penguji mengisi form);
--            dipindah ke N_EMI_LAB_Uji_Sampel saat submit.
-- Kolom    : No_Sementara = nomor draf; RV = rowversion (penjaga simpan bersamaan).
-- Kunci    : primary key No_Urut
-- Baris    : demo 0 | produksi 758
-- Dipakai  : FormulatorTrialSampelController, UjiSampelController, UjiSampelSementara
-- Struktur : 15 kolom - No_Urut int, Kode_Perusahaan varchar(3), No_Sementara varchar(30),
--            No_Po_Sampel varchar(30), No_Fak_Sub_Po varchar(30), Id_Jenis_Analisa int, Hasil
--            float, Flag_Perhitungan char(1), Flag_Multi_QrCode char(1), Status char(1), Tanggal
--            datetime, Jam varchar(8), Id_User varchar(30), RV timestamp, Id_Perhitungan int
SELECT * FROM dbo.N_EMI_LAB_Uji_Sampel_Sementara;
GO

-- -------------------------------------------------------------------------------------------------
-- 4.4  N_EMI_LAB_Uji_Sampel_Detail_Sementara                                                  TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Draf nilai parameter masukan untuk N_EMI_LAB_Uji_Sampel_Sementara (dikaitkan lewat
--            No_Sementara).
-- Kunci    : primary key No_Urut
-- Baris    : demo 0 | produksi 2.357
-- Dipakai  : FormulatorTrialSampelController, UjiSampelController, UjiSampelDetailSementara
-- Struktur : 10 kolom - No_Urut int, Kode_Perusahaan varchar(3), No_Sementara varchar(30),
--            Id_Quality_Control int, Value_Parameter float, Tanggal datetime, Jam varchar(8),
--            Id_User varchar(30), RV timestamp, Id_Perhitungan int
SELECT * FROM dbo.N_EMI_LAB_Uji_Sampel_Detail_Sementara;
GO

-- -------------------------------------------------------------------------------------------------
-- 4.5  N_EMI_LAB_Uji_Sample_Detail_Sementara                                     TABEL [HANYA DEMO]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Varian lama tabel draf detail (ejaan "Sample", kolom No_Faktur_Sementara). Kosong dan
--            tidak dipakai kode.
-- Kunci    : primary key No_Urut
-- Baris    : demo 0 | produksi TIDAK ADA
-- Dipakai  : tidak dipakai kode aplikasi
-- Struktur : 8 kolom - No_Urut int, Kode_Perusahaan varchar(3), No_Faktur_Sementara varchar(30),
--            Id_Quality_Control int, Value_Parameter decimal, Tanggal datetime, Jam varchar(8),
--            Id_User varchar(30)
SELECT * FROM dbo.N_EMI_LAB_Uji_Sample_Detail_Sementara;
GO

-- -------------------------------------------------------------------------------------------------
-- 4.6  EMI_LAB_Uji_Sample_Detail_Perhitungan                                     TABEL [HANYA DEMO]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Detail parameter perhitungan per faktur berprefiks lama EMI_LAB_. Kosong dan tidak
--            dipakai kode.
-- Kunci    : primary key Urut_Oto
-- Baris    : demo 0 | produksi TIDAK ADA
-- Dipakai  : tidak dipakai kode aplikasi
-- Struktur : 8 kolom - Urut_Oto int, Kode_Perusahaan varchar(3), No_Faktur varchar(30),
--            Id_Quality_Control int, Value_Parameter decimal, No_Urut_Detail int, Created_At
--            datetime, Updated_At datetime
SELECT * FROM dbo.EMI_LAB_Uji_Sample_Detail_Perhitungan;
GO

-- -------------------------------------------------------------------------------------------------
-- 4.7  N_EMI_LAB_Uji_Sampel_Resampling_Log                                                    TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Jejak resampling (uji ulang): analisa yang diputuskan diuji ulang, dari sub-sampel
--            asal ke sub-sampel tujuan, beserta status selesainya. Sumber paling tepercaya untuk
--            menentukan hasil yang ditolak.
-- Kolom    : No_Po_Sampel; Tahapan_Ke = putaran yang ditolak; No_Sampel_Resampling_Origin ->
--            No_Sampel_Resampling (multi QR pindah nomor, sampel tunggal nomornya sama);
--            Id_Jenis_Analisa; Flag_Selesai_Resampling; Id_Session/Id_Pembanding (palatabilitas);
--            Alasan & Dibuat_Pada = kolom migrasi 26-09-2026.
-- Catatan  : Kolom Alasan dan Dibuat_Pada belum ada di produksi (migrasi
--            docs/sql/26-09-2026-lifecycle belum dijalankan).
-- Kunci    : primary key Id_Resampling
-- Baris    : demo 9 | produksi 338
-- Dipakai  : FormulatorTrialSampelController, LifecycleSampelService, PalatabilitasController,
--            ResamplingController, RincianHasilAnalisaService, SampelDummyService (+1 berkas lain);
--            migrasi 2026_05_20_000001_create_palatabilitas_tables
-- Struktur : 15 kolom - Id_Resampling int, No_Po_Sampel varchar(30), Tahapan_Ke int,
--            No_Sampel_Resampling_Origin varchar(30), No_Sampel_Resampling varchar(30), Keterangan
--            text(2147483647), Tanggal datetime, Jam varchar(8), Id_User varchar(30),
--            Id_Jenis_Analisa int, Flag_Selesai_Resampling char(1), Id_Session int, Id_Pembanding
--            int, Alasan varchar(500), Dibuat_Pada datetime
SELECT * FROM dbo.N_EMI_LAB_Uji_Sampel_Resampling_Log;
GO

-- -------------------------------------------------------------------------------------------------
-- 4.8  N_EMI_LAB_Pengajuan_Buka_Ulang_Uji_Sampel                                              TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Pengajuan membuka ulang sampel yang sudah terkunci agar hasil ujinya dapat diubah
--            dalam rentang waktu tertentu.
-- Kolom    : Waktu_Mulai-Waktu_Akhir = jendela buka ulang; Keterangan = alasan; Id_User = pengaju.
-- Kunci    : primary key Id_Pengajuan_Buka_Ulang
-- Baris    : demo 0 | produksi 8.129
-- Dipakai  : PengajuanBukaUlangUjiSampelController, UjiSampelController
-- Struktur : 8 kolom - Id_Pengajuan_Buka_Ulang int, No_Sampel varchar(30), Waktu_Mulai datetime,
--            Waktu_Akhir datetime, Tanggal datetime, Jam varchar(8), Keterangan text(2147483647),
--            Id_User varchar(15)
SELECT * FROM dbo.N_EMI_LAB_Pengajuan_Buka_Ulang_Uji_Sampel;
GO

-- -------------------------------------------------------------------------------------------------
-- 4.9  N_EMI_LAB_Berkas_Uji_Lab                                                               TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Lampiran foto hasil uji per faktur uji. Berkasnya tersimpan di Google Cloud Storage;
--            tabel ini menyimpan kunci & lokasinya.
-- Kolom    : Berkas_Key = kunci akses foto (endpoint stream memakai token 30 detik); File_Path =
--            lokasi berkas di GCS; No_Faktur, No_Sampel, Keterangan; Id_User, Tahapan_Ke,
--            Dibuat_Pada, Flag_Nonaktif, Dinonaktifkan_Pada, Id_User_Nonaktif = kolom migrasi
--            26-09-2026.
-- Catatan  : Enam kolom migrasi 26-09-2026 belum ada di produksi.
-- Kunci    : primary key Id_Berkas_Lab
-- Baris    : demo 47 | produksi 127
-- Dipakai  : FinalisasiSandboxController, LifecycleSampelService, MonitoringAnalisaController,
--            RincianHasilAnalisaService, SampelDummyService, UjiSampelController
-- Struktur : 12 kolom - Id_Berkas_Lab int, No_Faktur varchar(255), No_Sampel varchar(255),
--            Berkas_Key varchar(255), File_Path varchar(255), Keterangan varchar(255), Id_User
--            varchar(30), Tahapan_Ke int, Dibuat_Pada datetime, Flag_Nonaktif char(1),
--            Dinonaktifkan_Pada datetime, Id_User_Nonaktif varchar(30)
SELECT * FROM dbo.N_EMI_LAB_Berkas_Uji_Lab;
GO

-- -------------------------------------------------------------------------------------------------
-- 4.10  N_EMI_LAB_Activity_Uji_Sampel                                                         TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Log pengiriman hasil uji: save_submit (submit hasil) dan save_submit_resampling
--            (submit hasil uji ulang), per sampel/sub-sampel/analisa.
-- Catatan  : Id_User di sini = pengirim hasil; waktunya sama persis dengan baris
--            N_EMI_LAB_Uji_Sampel yang dikirim.
-- Kunci    : primary key Id_Log_Activity
-- Baris    : demo 191 | produksi 71.607
-- Dipakai  : FormulatorTrialSampelController, LifecycleSampelService, SampelDummyService,
--            UjiSampelController
-- Struktur : 10 kolom - Id_Log_Activity int, Kode_Perusahaan varchar(3), No_Po_Sampel varchar(30),
--            No_Fak_Sub_Po varchar(30), Jenis_Aktivitas varchar(50), Id_Jenis_Analisa int,
--            Keterangan text(2147483647), Id_User varchar(30), Tanggal datetime, Jam varchar(8)
SELECT * FROM dbo.N_EMI_LAB_Activity_Uji_Sampel;
GO

-- -------------------------------------------------------------------------------------------------
-- 4.11  N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail                                    TABEL [BESAR]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Jejak audit perubahan nilai HASIL per analisa: Value_Lama -> Value_Baru, status
--            submit, dan rumus yang dipakai (Id_Perhitungan).
-- Relasi   : Id_Log_Activity_Sampel -> N_EMI_LAB_Activity_Uji_Sampel.Id_Log_Activity.
-- Kunci    : primary key Id_Activity_Uji_Sampel
-- Baris    : demo 457 | produksi 229.132
-- Dipakai  : FormulatorTrialSampelController, LifecycleSampelService, LogLabActivityDetailsJob,
--            UjiSampelController
-- Struktur : 13 kolom - Id_Activity_Uji_Sampel int, Id_Log_Activity_Sampel int, Kode_Perusahaan
--            varchar(3), No_Po_Sampel varchar(30), No_Fak_Sub_Po varchar(30), Id_Jenis_Analisa int,
--            Value_Baru float, Value_Lama float, Tanggal datetime, Jam varchar(8), Id_User
--            varchar(30), Status_Submit varchar(30), Id_Perhitungan int
SELECT * FROM dbo.N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail;
GO

-- -------------------------------------------------------------------------------------------------
-- 4.12  N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail                                TABEL [BESAR]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Jejak audit perubahan nilai PARAMETER masukan (per Id_Quality_Control): Value_Lama ->
--            Value_Baru beserta Alasan_Mengubah_Data.
-- Relasi   : Id_Log_Activity_Sampel -> N_EMI_LAB_Activity_Uji_Sampel.Id_Log_Activity.
-- Kunci    : primary key Id_Activity_Uji_Sampel
-- Baris    : demo 504 | produksi 618.302
-- Dipakai  : FormulatorTrialSampelController, LifecycleSampelService, LogLabActivityDetailsJob,
--            UjiSampelController
-- Struktur : 14 kolom - Id_Activity_Uji_Sampel int, Id_Log_Activity_Sampel int, Kode_Perusahaan
--            varchar(3), No_Po_Sampel varchar(30), No_Fak_Sub_Po varchar(30), Id_Jenis_Analisa int,
--            Id_Quality_Control int, Value_Baru float, Value_Lama float, Tanggal datetime, Jam
--            varchar(8), Id_User varchar(30), Status_Submit varchar(30), Alasan_Mengubah_Data
--            varchar(255)
SELECT * FROM dbo.N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail;
GO

-- =================================================================================================
-- 5. UJI PALATABILITAS
--   Sampel dinilai dengan membandingkannya terhadap produk pembanding, per sesi.
-- =================================================================================================

-- -------------------------------------------------------------------------------------------------
-- 5.1  N_EMI_LAB_Palatabilitas_Session                                                        TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Sesi uji palatabilitas per faktur uji/sampel - mengelompokkan pembanding dan hasil
--            satu sesi; Status_Session berubah saat sesi difinalkan.
-- Relasi   : Id_Session dirujuk Palatabilitas_Pembanding, Palatabilitas_Sementara, Uji_Sampel,
--            Resampling_Log.
-- Kunci    : primary key Id_Session
-- Baris    : demo 7 | produksi 31
-- Dipakai  : FinalisasiLabProduksiTrialController, LifecycleSampelService, PalatabilitasController,
--            PalatabilitasSession, SampelDummyService, UjiSampelController (+1 berkas lain);
--            migrasi 2026_05_20_000001_create_palatabilitas_tables,
--            2026_05_21_000001_fix_uq_palatabilitas_session
-- Struktur : 13 kolom - Id_Session int, Kode_Perusahaan varchar(3), No_Faktur_Uji_Sampel
--            varchar(30), No_Po_Sampel varchar(30), Kode_Aktivitas_Lab varchar(10), Status_Session
--            char(1), Tanggal_Buat datetime, Jam_Buat varchar(8), Id_User_Buat varchar(30),
--            Kode_Role varchar(50), Tanggal_Final datetime, Jam_Final varchar(8), Id_User_Final
--            varchar(30)
SELECT * FROM dbo.N_EMI_LAB_Palatabilitas_Session;
GO

-- -------------------------------------------------------------------------------------------------
-- 5.2  N_EMI_LAB_Palatabilitas_Pembanding                                                     TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Produk pembanding dalam satu sesi palatabilitas (mis. Pembanding 1), berurutan.
-- Kolom    : Kode_Barang_Pembanding, Nama_Pembanding, Urutan, Flag_Aktif.
-- Relasi   : Id_Pembanding dirujuk Uji_Sampel dan Resampling_Log.
-- Kunci    : primary key Id_Pembanding
-- Baris    : demo 7 | produksi 34
-- Dipakai  : FinalisasiLabProduksiTrialController, MonitoringAnalisaController,
--            PalatabilitasController, PalatabilitasPembanding, ResamplingController,
--            RincianHasilAnalisaService (+3 berkas lain); migrasi
--            2026_05_20_000001_create_palatabilitas_tables
-- Struktur : 11 kolom - Id_Pembanding int, Id_Session int, Kode_Perusahaan varchar(3), Urutan int,
--            Kode_Barang_Pembanding varchar(30), Nama_Pembanding varchar(255), Tanggal datetime,
--            Jam varchar(8), Id_User varchar(30), Kode_Role varchar(50), Flag_Aktif char(1)
SELECT * FROM dbo.N_EMI_LAB_Palatabilitas_Pembanding;
GO

-- -------------------------------------------------------------------------------------------------
-- 5.3  N_EMI_LAB_Palatabilitas_Sementara                                                      TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Draf input hasil palatabilitas per sesi & pembanding sebelum disubmit ke
--            N_EMI_LAB_Uji_Sampel.
-- Kunci    : primary key No_Urut
-- Baris    : demo 0 | produksi 0
-- Dipakai  : FinalisasiLabProduksiTrialController, PalatabilitasController, PalatabilitasSementara,
--            UjiValidasiFinalController; migrasi 2026_05_20_000001_create_palatabilitas_tables
-- Struktur : 18 kolom - No_Urut int, Id_Session int, Id_Pembanding int, Kode_Perusahaan varchar(3),
--            No_Po_Sampel varchar(30), No_Fak_Sub_Po varchar(30), Id_Jenis_Analisa int, Hasil
--            float, Flag_Perhitungan char(1), Flag_Multi_QrCode char(1), Nilai_Hasil_String
--            varchar(225), Flag_String char(1), Flag_Foto char(1), Status char(1), Tanggal
--            datetime, Jam varchar(8), Id_User varchar(30), Kode_Role varchar(50)
SELECT * FROM dbo.N_EMI_LAB_Palatabilitas_Sementara;
GO

-- =================================================================================================
-- 6. VALIDASI & FINALISASI
--   Validator menyetujui hasil tiap analisa; penanggung jawab menetapkan keputusan akhir
--   sampel.
-- =================================================================================================

-- -------------------------------------------------------------------------------------------------
-- 6.1  N_EMI_LAB_Hasil_Uji_Approval_Aktivitas                                                 TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Jejak persetujuan per analisa per sub-sampel: siapa memvalidasi atau memfinalisasi,
--            kapan, dan layak/tidaknya (tabel migrasi 24-09-2026).
-- Kolom    : Jenis_Approval = VALIDASI / FINALISASI; Flag_Approval; Flag_Layak; Tahapan_Ke;
--            Sumber_Pencatatan = VALIDASI (tercatat langsung saat validasi) atau BACKFILL (isian
--            ulang data lama - jangan dipakai sebagai bukti validator); Flag_Trial_Produksi,
--            Flag_Resampling.
-- Catatan  : Sudah ada di produksi tetapi masih kosong.
-- Kunci    : primary key Id_Approval_Aktivitas
-- Baris    : demo 110 | produksi 0
-- Dipakai  : JejakValidasiService, LifecycleSampelService, RincianHasilAnalisaService,
--            SampelDummyService
-- Struktur : 26 kolom - Id_Approval_Aktivitas int, No_Sampel varchar(30), No_Sub_Sampel
--            varchar(30), No_Po varchar(25), No_Split_Po varchar(25), No_Batch float, Kode_Barang
--            varchar(50), Kode_Aktivitas_Lab varchar(10), Nama_Aktivitas varchar(255),
--            Id_Jenis_Analisa int, Nama_Jenis_Analisa varchar(255), Tahapan_Ke int, Id_Session int,
--            Id_Pembanding int, Id_User varchar(30), Nama_User varchar(255), Jenis_Approval
--            varchar(30), Flag_Approval char(1), Flag_Layak char(1), Keterangan varchar(500),
--            Tanggal date, Jam varchar(8), Dibuat_Pada datetime, Flag_Trial_Produksi char(1),
--            Flag_Resampling char(1), Sumber_Pencatatan varchar(30)
SELECT * FROM dbo.N_EMI_LAB_Hasil_Uji_Approval_Aktivitas;
GO

-- -------------------------------------------------------------------------------------------------
-- 6.2  N_EMI_LAB_Hasil_Uji_Validasi_Final                                                     TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Header keputusan akhir (finalisasi) per sampel.
-- Kolom    : No_Po/No_Split_Po/No_Batch/No_Sampel; Flag_Ok = hasil akhir diterima; Flag_FG =
--            finished goods; Id_User & waktu finalisasi.
-- Kunci    : primary key Id_Uji_Validasi_Final
-- Baris    : demo 6 | produksi 6.469
-- Dipakai  : DashboardController, FinalisasiLabProduksiTrialController,
--            FormulatorFinalisasiController, JejakValidasiService, LifecycleSampelService,
--            MonitoringAnalisaController (+2 berkas lain)
-- Struktur : 10 kolom - Id_Uji_Validasi_Final int, No_Po varchar(25), No_Split_Po varchar(25),
--            No_Batch float, No_Sampel varchar(30), Tanggal datetime, Jam varchar(8), Flag_Ok
--            char(1), Id_User varchar(30), Flag_FG char(1)
SELECT * FROM dbo.N_EMI_LAB_Hasil_Uji_Validasi_Final;
GO

-- -------------------------------------------------------------------------------------------------
-- 6.3  N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final                                              TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Rincian keputusan per analisa/sub-sampel/putaran: Flag_Layak dan Flag_Resampling,
--            terhubung ke header lewat Id_Uji_Validasi_Final (kolom migrasi 24-09-2026).
-- Catatan  : Sumber_Pencatatan = VALIDASI / BACKFILL / PRA_MIGRASI; pada baris PRA_MIGRASI, Id_User
--            adalah validator.
-- Kunci    : primary key Id_Uji_Validasi_Detail_Final
-- Baris    : demo 496 | produksi 25.875
-- Dipakai  : FinalisasiLabProduksiTrialController, JejakValidasiService, LifecycleSampelService,
--            RincianHasilAnalisaService, SampelDummyService
-- Struktur : 17 kolom - Id_Uji_Validasi_Detail_Final int, No_Sampel varchar(30), Id_Jenis_Analisa
--            int, Tahapan_Ke int, Tanggal datetime, Jam varchar(8), Flag_Layak char(1),
--            Flag_Resampling char(1), Id_User varchar(30), No_Sub_Sampel varchar(30),
--            Id_Uji_Validasi_Final int, Kode_Aktivitas_Lab varchar(10), Nama_Jenis_Analisa
--            varchar(255), Id_Session int, Id_Pembanding int, Sumber_Pencatatan varchar(30),
--            Dibuat_Pada datetime
SELECT * FROM dbo.N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final;
GO

-- -------------------------------------------------------------------------------------------------
-- 6.4  N_EMI_LAB_Log_Aksi                                                                     TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Log aksi validasi/finalisasi per sampel (mis. Jenis_Aksi VALIDASI_TRIAL_PRODUKSI,
--            Sub_Aksi SETUJU) - tercatat sejak Juni 2026.
-- Kolom    : No_Sampel, No_Po, No_Split_Po, Kode_Barang, Flag_Trial, Keterangan, Id_User, Tanggal,
--            Jam.
-- Kunci    : primary key Id_Log_Aksi
-- Baris    : demo 4 | produksi 7.301
-- Dipakai  : FinalisasiLabProduksiTrialController, FormulatorFinalisasiController,
--            FormulatorTrialSampelController, FormulatorValidasiHirarkiController,
--            LifecycleSampelService, RincianHasilAnalisaService (+3 berkas lain)
-- Struktur : 12 kolom - Id_Log_Aksi int, No_Sampel varchar(50), No_Po varchar(50), No_Split_Po
--            varchar(50), Kode_Barang varchar(50), Flag_Trial char(1), Jenis_Aksi varchar(50),
--            Sub_Aksi varchar(30), Keterangan varchar(max), Id_User nvarchar(50), Tanggal date, Jam
--            time
SELECT * FROM dbo.N_EMI_LAB_Log_Aksi;
GO

-- -------------------------------------------------------------------------------------------------
-- 6.5  N_EMI_LAB_Log_Aksi_Detail                                                              TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Rincian log aksi per jenis analisa: nama analisa, Flag_Layak, pelaku dan waktu.
-- Relasi   : Id_Log_Aksi -> N_EMI_LAB_Log_Aksi.
-- Kunci    : primary key Id_Log_Aksi_Detail
-- Baris    : demo 36 | produksi 24.156
-- Dipakai  : FinalisasiLabProduksiTrialController, FormulatorFinalisasiController,
--            FormulatorTrialSampelController, FormulatorValidasiHirarkiController,
--            LifecycleSampelService, RincianHasilAnalisaService (+3 berkas lain)
-- Struktur : 8 kolom - Id_Log_Aksi_Detail int, Id_Log_Aksi int, Id_Jenis_Analisa int,
--            Nama_Jenis_Analisa nvarchar(255), Flag_Layak char(1), Tanggal nvarchar(10), Jam
--            nvarchar(8), Id_User nvarchar(50)
SELECT * FROM dbo.N_EMI_LAB_Log_Aksi_Detail;
GO

-- =================================================================================================
-- 7. VERIFIKASI HASIL ANALISA (PEMBAHARUAN)
--   Verifikator memberi rekomendasi per klasifikasi sebelum finalisasi. Keenam tabel ini
--   hasil migrasi docs/sql/24-09-2026-verifikasi dan BELUM ada di produksi.
-- =================================================================================================

-- -------------------------------------------------------------------------------------------------
-- 7.1  N_EMI_LAB_Verifikasi_Status                                               TABEL [HANYA DEMO]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Master status verifikasi: MENUNGGU; hasil verifikator REKOMENDASI, REKOM_BERSYARAT,
--            TIDAK_REKOM; status final DISETUJUI, DISETUJUI_BERSYARAT, DITOLAK (Flag_Final = 'Y').
-- Kolom    : Nama_Status, Keterangan, Warna_Badge, Urutan, Flag_Final, Flag_Aktif.
-- Kunci    : primary key Kode_Status
-- Baris    : demo 7 | produksi TIDAK ADA
-- Dipakai  : FinalisasiSandboxController, VerifikasiHasilAnalisaController
-- Struktur : 7 kolom - Kode_Status varchar(20), Nama_Status varchar(100), Keterangan varchar(255),
--            Warna_Badge varchar(20), Urutan int, Flag_Final char(1), Flag_Aktif char(1)
SELECT * FROM dbo.N_EMI_LAB_Verifikasi_Status;
GO

-- -------------------------------------------------------------------------------------------------
-- 7.2  N_EMI_LAB_Verifikasi_Keputusan                                            TABEL [HANYA DEMO]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Master tingkat keputusan di layar verifikasi: Direkomendasikan, Direkomendasikan
--            Bersyarat, Tidak Direkomendasikan (aktif) serta istilah lama Setuju
--            Penuh/Bersyarat/Tolak (nonaktif).
-- Kolom    : Kode_Status -> Verifikasi_Status; Flag_Wajib_Catatan & Panjang_Min_Catatan = aturan
--            catatan; Flag_Boleh_Lanjut = boleh diteruskan ke finalisasi; Flag_Perlu_Tindak;
--            Warna_Badge, Ikon, Urutan, Flag_Aktif.
-- Kunci    : primary key Kode_Keputusan
-- Baris    : demo 6 | produksi TIDAK ADA
-- Dipakai  : FinalisasiSandboxController, MockupSiklusController, VerifikasiHasilAnalisaController
-- Struktur : 12 kolom - Kode_Keputusan varchar(30), Nama_Keputusan varchar(100), Keterangan
--            varchar(500), Kode_Status varchar(20), Flag_Wajib_Catatan char(1), Panjang_Min_Catatan
--            int, Flag_Boleh_Lanjut char(1), Flag_Perlu_Tindak char(1), Warna_Badge varchar(20),
--            Ikon varchar(50), Urutan int, Flag_Aktif char(1)
SELECT * FROM dbo.N_EMI_LAB_Verifikasi_Keputusan;
GO

-- -------------------------------------------------------------------------------------------------
-- 7.3  N_EMI_LAB_Verifikasi_Kewenangan                                           TABEL [HANYA DEMO]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Kewenangan verifikator: user mana boleh memverifikasi klasifikasi/jenis analisa apa
--            (Id_Jenis_Analisa kosong = seluruh analisa klasifikasi itu).
-- Kolom    : Id_User, Kode_Aktivitas_Lab, Id_Jenis_Analisa, Flag_Approve, Flag_Reject, Flag_Aktif.
-- Kunci    : primary key Id_Kewenangan
-- Baris    : demo 16 | produksi TIDAK ADA
-- Dipakai  : MockupSiklusController, RincianHasilAnalisaService, TrialUiController,
--            VerifikasiHasilAnalisaController
-- Struktur : 10 kolom - Id_Kewenangan int, Id_User varchar(30), Kode_Aktivitas_Lab varchar(10),
--            Id_Jenis_Analisa int, Flag_Approve char(1), Flag_Reject char(1), Flag_Aktif char(1),
--            Keterangan varchar(255), Dibuat_Pada datetime, Dibuat_Oleh varchar(30)
SELECT * FROM dbo.N_EMI_LAB_Verifikasi_Kewenangan;
GO

-- -------------------------------------------------------------------------------------------------
-- 7.4  N_EMI_LAB_Verifikasi_Header                                               TABEL [HANYA DEMO]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Rekomendasi verifikasi terkini per sampel per klasifikasi.
-- Kolom    : No_Sampel/No_Sub_Sampel, No_Po/No_Split_Po/No_Batch, Kode_Barang & Nama_Barang,
--            Kode_Aktivitas_Lab; Kode_Status & Kode_Keputusan; Catatan; Revisi_Ke = jumlah revisi;
--            Jumlah_Analisa & Jumlah_Tidak_Layak; Id_Mesin & Nama_Mesin (kolom tambahan
--            10-TAMBAH-KOLOM-MESIN).
-- Kunci    : primary key Id_Verifikasi
-- Baris    : demo 12 | produksi TIDAK ADA
-- Dipakai  : FinalisasiSandboxController, LifecycleSampelService, SampelDummyService,
--            VerifikasiHasilAnalisaController
-- Struktur : 25 kolom - Id_Verifikasi int, No_Sampel varchar(30), No_Sub_Sampel varchar(30), No_Po
--            varchar(25), No_Split_Po varchar(25), No_Batch float, Kode_Barang varchar(50),
--            Nama_Barang varchar(255), Flag_Trial_Produksi char(1), Kode_Aktivitas_Lab varchar(10),
--            Nama_Aktivitas varchar(255), Kode_Status varchar(20), Catatan varchar(1000), Id_User
--            varchar(30), Nama_User varchar(255), Tanggal_Keputusan date, Jam_Keputusan varchar(8),
--            Jumlah_Analisa int, Jumlah_Tidak_Layak int, Revisi_Ke int, Dibuat_Pada datetime,
--            Diubah_Pada datetime, Kode_Keputusan varchar(30), Id_Mesin int, Nama_Mesin varchar(50)
SELECT * FROM dbo.N_EMI_LAB_Verifikasi_Header;
GO

-- -------------------------------------------------------------------------------------------------
-- 7.5  N_EMI_LAB_Verifikasi_Detail                                               TABEL [HANYA DEMO]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Salinan hasil per analisa saat rekomendasi diberikan (hasil, standar, kelayakan,
--            validator) - bukti apa yang dilihat verifikator.
-- Relasi   : Id_Verifikasi -> N_EMI_LAB_Verifikasi_Header.
-- Kunci    : primary key Id_Verifikasi_Detail
-- Baris    : demo 72 | produksi TIDAK ADA
-- Dipakai  : SampelDummyService, VerifikasiHasilAnalisaController
-- Struktur : 23 kolom - Id_Verifikasi_Detail int, Id_Verifikasi int, Id_Jenis_Analisa int,
--            Nama_Jenis_Analisa varchar(255), Kode_Analisa varchar(50), No_Sub_Sampel varchar(30),
--            Tahapan_Ke int, Hasil float, Nilai_Hasil_String varchar(255), Range_Awal float,
--            Range_Akhir float, Flag_Layak char(1), Flag_Perhitungan char(1), Id_Session int,
--            Id_Pembanding int, Nama_Pembanding varchar(255), Id_User_Validasi varchar(30),
--            Nama_User_Validasi varchar(255), Tanggal_Validasi date, Jam_Validasi varchar(8),
--            Kode_Status varchar(20), Catatan varchar(1000), Dibuat_Pada datetime
SELECT * FROM dbo.N_EMI_LAB_Verifikasi_Detail;
GO

-- -------------------------------------------------------------------------------------------------
-- 7.6  N_EMI_LAB_Verifikasi_Riwayat                                              TABEL [HANYA DEMO]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Riwayat setiap aksi verifikasi, termasuk revisi: status sebelum -> sesudah, keputusan,
--            catatan, pelaku, waktu.
-- Kolom    : Aksi, Status_Sebelum, Status_Sesudah, Kode_Keputusan, Catatan; Sumber_Aksi = SATUAN
--            (satu klasifikasi) atau BULK (banyak sekaligus); Jumlah_Analisa.
-- Relasi   : Id_Verifikasi -> N_EMI_LAB_Verifikasi_Header.
-- Kunci    : primary key Id_Riwayat
-- Baris    : demo 12 | produksi TIDAK ADA
-- Dipakai  : LifecycleSampelService, SampelDummyService, VerifikasiHasilAnalisaController
-- Struktur : 16 kolom - Id_Riwayat int, Id_Verifikasi int, No_Sampel varchar(30),
--            Kode_Aktivitas_Lab varchar(10), Status_Sebelum varchar(20), Status_Sesudah
--            varchar(20), Aksi varchar(30), Catatan varchar(1000), Id_User varchar(30), Nama_User
--            varchar(255), Tanggal date, Jam varchar(8), Dibuat_Pada datetime, Sumber_Aksi
--            varchar(20), Jumlah_Analisa int, Kode_Keputusan varchar(30)
SELECT * FROM dbo.N_EMI_LAB_Verifikasi_Riwayat;
GO

-- =================================================================================================
-- 8. MODUL FORMULATOR (SAMPEL TRIAL R&D)
--   Salinan alur registrasi -> uji -> validasi -> finalisasi khusus sampel formulator (PO
--   trial R&D). Tabel berprefiks N_LIMS_ dan N_EMI_LIMS_. Di demo sebagian besar kosong;
--   datanya ada di produksi.
-- =================================================================================================

-- -------------------------------------------------------------------------------------------------
-- 8.1  N_LIMS_PO_Sampel                                                                       TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Registrasi sampel trial formulator - padanan N_EMI_LAB_PO_Sampel untuk PO trial
--            (N_EMI_View_Trial_Order_Produksi).
-- Kolom    : Sama dengan N_EMI_LAB_PO_Sampel, ditambah Flag_Validasi_Formulator_Desktop
--            (+tanggal/jam/user) = sudah divalidasi formulator lewat aplikasi desktop.
-- Kunci    : primary key id
-- Baris    : demo 15 | produksi 79
-- Dipakai  : FormulatorCetakUlangQrCodeController, FormulatorDashboardController,
--            FormulatorFinalisasiController, FormulatorRegistrasiController,
--            FormulatorStatusDataController, FormulatorTrialSampelController (+1 berkas lain)
-- Struktur : 29 kolom - id int, Kode_Perusahaan varchar(3), No_Po varchar(30), No_Split_Po
--            varchar(30), No_Batch int, Kode_Barang varchar(30), No_Sampel varchar(30), Status
--            char(1), Tanggal datetime, Jam varchar(8), Id_Mesin int, Keterangan text(2147483647),
--            Id_User varchar(30), Flag_Selesai char(1), Berat_Sampel float, Flag_Khusus char(1),
--            Id_Jenis_Analisa_Khusus int, Jumlah_Pcs int, Flag_Baca char(1), Flag_Close_Po char(1),
--            Tanggal_Close_Po datetime, Jam_Close_Po varchar(8), Alasan_Buka_Ulang_Po
--            text(2147483647), Tanggal_Buka_Ulang_Po datetime, Jam_Buka_Ulang_Po varchar(8),
--            Flag_Validasi_Formulator_Desktop char(1), Tanggal_Validasi_Formulator_Desktop
--            datetime, Jam_Validasi_Formulator_Desktop datetime,
--            Id_User_Validasi_Formulator_Desktop varchar(15)
SELECT * FROM dbo.N_LIMS_PO_Sampel;
GO

-- -------------------------------------------------------------------------------------------------
-- 8.2  N_LIMS_PO_Sampel_Multi_QrCode                                                          TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Sub-sampel (multi QR) sampel formulator.
-- Kunci    : primary key Id_Po_Sampel_Multi
-- Baris    : demo 73 | produksi 472
-- Dipakai  : FormulatorCetakUlangQrCodeController, FormulatorRegistrasiController,
--            FormulatorTrialSampelController
-- Struktur : 11 kolom - Id_Po_Sampel_Multi int, Kode_Perusahaan varchar(3), No_Po_Multi
--            varchar(30), Kode_Barang varchar(30), No_Po_Sampel varchar(30), Status char(1),
--            Tanggal datetime, Jam varchar(8), Flag_Khusus char(1), Id_Jenis_Analisa_Khusus int,
--            Flag_Selesai char(1)
SELECT * FROM dbo.N_LIMS_PO_Sampel_Multi_QrCode;
GO

-- -------------------------------------------------------------------------------------------------
-- 8.3  N_EMI_LIMS_Activity_Produksi_Sampel                                                    TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Log pengambilan sampel & cetak QR sampel formulator (padanan
--            N_EMI_LAB_Activity_Produksi_Sampel).
-- Kunci    : primary key Id_Activity_Produksi_Sampel
-- Baris    : demo 0 | produksi 180
-- Dipakai  : FormulatorCetakUlangQrCodeController, FormulatorRegistrasiController
-- Struktur : 12 kolom - Id_Activity_Produksi_Sampel int, No_Po varchar(30), No_Split_Po
--            varchar(30), No_Batch int, Jenis_Aktivitas varchar(100), Status_Aktivitas
--            varchar(100), Keterangan text(2147483647), Tanggal datetime, Id_Mesin int, Jam
--            varchar(8), Flag_Berhasil_Cetak_QrCode char(1), Id_User varchar(30)
SELECT * FROM dbo.N_EMI_LIMS_Activity_Produksi_Sampel;
GO

-- -------------------------------------------------------------------------------------------------
-- 8.4  N_EMI_LIMS_Uji_Sampel                                                                  TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Hasil uji sampel formulator - struktur sama dengan N_EMI_LAB_Uji_Sampel, ditambah
--            Flag_Approval.
-- Catatan  : Tanpa primary key, seperti N_EMI_LAB_Uji_Sampel.
-- Kunci    : tanpa primary key
-- Baris    : demo 0 | produksi 482
-- Dipakai  : FormulatorDashboardController, FormulatorFinalisasiController,
--            FormulatorStatusDataController, FormulatorTrialSampelController,
--            FormulatorValidasiHirarkiController, UjiSampelController (+1 berkas lain)
-- Struktur : 26 kolom - Kode_Perusahaan varchar(3), No_Faktur varchar(30), No_Po_Sampel
--            varchar(30), No_Fak_Sub_Po varchar(30), Id_Jenis_Analisa int, Hasil float,
--            Flag_Perhitungan char(1), Flag_Multi_QrCode char(1), Status char(1), Tanggal datetime,
--            Jam varchar(8), Id_User varchar(30), Flag_Selesai char(1), Id_Perhitungan int,
--            Range_Awal float, Range_Akhir float, Tahapan_Ke int, Flag_Resampling char(1),
--            Status_Keputusan_Sampel varchar(255), Flag_Layak char(1), Flag_Final char(1), Id_Mesin
--            int, Flag_String char(1), Nilai_Hasil_String varchar(255), Flag_Foto char(1),
--            Flag_Approval char(1)
SELECT * FROM dbo.N_EMI_LIMS_Uji_Sampel;
GO

-- -------------------------------------------------------------------------------------------------
-- 8.5  N_EMI_LIMS_Uji_Sampel_Detail                                                           TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Nilai parameter masukan per faktur uji formulator.
-- Kunci    : primary key Id_Uji_Sample_Detail
-- Baris    : demo 0 | produksi 553
-- Dipakai  : FormulatorTrialSampelController, FormulatorValidasiHirarkiController
-- Struktur : 8 kolom - Id_Uji_Sample_Detail int, Kode_Perusahaan varchar(3), No_Faktur_Uji_Sample
--            varchar(30), Id_Quality_Control int, Value_Parameter float, Tanggal datetime, Jam
--            varchar(8), Id_User varchar(30)
SELECT * FROM dbo.N_EMI_LIMS_Uji_Sampel_Detail;
GO

-- -------------------------------------------------------------------------------------------------
-- 8.6  N_EMI_LIMS_Uji_Sampel_Sementara                                                        TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Draf hasil uji formulator sebelum submit.
-- Kunci    : primary key No_Urut
-- Baris    : demo 0 | produksi 0
-- Dipakai  : FormulatorTrialSampelController
-- Struktur : 15 kolom - No_Urut int, Kode_Perusahaan varchar(3), No_Sementara varchar(30),
--            No_Po_Sampel varchar(30), No_Fak_Sub_Po varchar(30), Id_Jenis_Analisa int, Hasil
--            float, Flag_Perhitungan char(1), Flag_Multi_QrCode char(1), Status char(1), Tanggal
--            datetime, Jam varchar(8), Id_User varchar(30), RV timestamp, Id_Perhitungan int
SELECT * FROM dbo.N_EMI_LIMS_Uji_Sampel_Sementara;
GO

-- -------------------------------------------------------------------------------------------------
-- 8.7  N_EMI_LIMS_Uji_Sampel_Detail_Sementara                                                 TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Draf nilai parameter masukan formulator.
-- Kunci    : primary key No_Urut
-- Baris    : demo 0 | produksi 0
-- Dipakai  : FormulatorTrialSampelController
-- Struktur : 10 kolom - No_Urut int, Kode_Perusahaan varchar(3), No_Sementara varchar(30),
--            Id_Quality_Control int, Value_Parameter float, Tanggal datetime, Jam varchar(8),
--            Id_User varchar(30), RV timestamp, Id_Perhitungan int
SELECT * FROM dbo.N_EMI_LIMS_Uji_Sampel_Detail_Sementara;
GO

-- -------------------------------------------------------------------------------------------------
-- 8.8  N_EMI_LIMS_Uji_Sampel_Resampling_Log                                                   TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Jejak resampling sampel formulator (tanpa kolom palatabilitas).
-- Kunci    : primary key Id_Resampling
-- Baris    : demo 0 | produksi 7
-- Dipakai  : FormulatorTrialSampelController
-- Struktur : 11 kolom - Id_Resampling int, No_Po_Sampel varchar(30), Tahapan_Ke int,
--            No_Sampel_Resampling_Origin varchar(30), No_Sampel_Resampling varchar(30), Keterangan
--            text(2147483647), Tanggal datetime, Jam varchar(8), Id_User varchar(30),
--            Id_Jenis_Analisa int, Flag_Selesai_Resampling char(1)
SELECT * FROM dbo.N_EMI_LIMS_Uji_Sampel_Resampling_Log;
GO

-- -------------------------------------------------------------------------------------------------
-- 8.9  N_EMI_LIMS_Uji_Sampel_Keterangan_Status                                                TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Keterangan status sampel formulator pada validasi hierarki: Status_Keterangan beserta
--            Alasan, pelaku, waktu.
-- Kunci    : primary key Id_Keterangan_Status
-- Baris    : demo 0 | produksi 389
-- Dipakai  : FormulatorValidasiHirarkiController
-- Struktur : 8 kolom - Id_Keterangan_Status int, Kode_Perusahaan varchar(3), No_Sampel
--            varchar(255), Status_Keterangan varchar(100), Alasan text(2147483647), Tanggal
--            datetime, Jam varchar(8), Id_User varchar(15)
SELECT * FROM dbo.N_EMI_LIMS_Uji_Sampel_Keterangan_Status;
GO

-- -------------------------------------------------------------------------------------------------
-- 8.10  N_EMI_LIMS_Uji_Pra_Final                                                              TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Persetujuan pra-final sampel formulator (Flag_Setuju + Alasan) sebelum finalisasi.
-- Kunci    : primary key Id_Uji_Pra_Final
-- Baris    : demo 0 | produksi 52
-- Dipakai  : FormulatorDashboardController, FormulatorStatusDataController,
--            FormulatorTrialSampelController, FormulatorValidasiHirarkiController
-- Struktur : 7 kolom - Id_Uji_Pra_Final int, No_Sampel varchar(255), Alasan text(2147483647),
--            Flag_Setuju char(1), Tanggal datetime, Jam varchar(8), Id_User varchar(15)
SELECT * FROM dbo.N_EMI_LIMS_Uji_Pra_Final;
GO

-- -------------------------------------------------------------------------------------------------
-- 8.11  N_EMI_LIMS_Hasil_Uji_Validasi_Final                                                   TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Header keputusan akhir sampel formulator (Flag_Ok, Flag_FG).
-- Kunci    : primary key Id_Uji_Validasi_Final
-- Baris    : demo 0 | produksi 23
-- Dipakai  : FormulatorDashboardController, FormulatorFinalisasiController,
--            FormulatorStatusDataController
-- Struktur : 10 kolom - Id_Uji_Validasi_Final int, No_Po varchar(25), No_Split_Po varchar(25),
--            No_Batch float, No_Sampel varchar(30), Tanggal datetime, Jam varchar(8), Flag_Ok
--            char(1), Id_User varchar(30), Flag_FG char(1)
SELECT * FROM dbo.N_EMI_LIMS_Hasil_Uji_Validasi_Final;
GO

-- -------------------------------------------------------------------------------------------------
-- 8.12  N_EMI_LIMS_Hasil_Uji_Validasi_Detail_Final                                            TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Rincian keputusan per analisa/sub-sampel/putaran sampel formulator (Flag_Layak,
--            Flag_Resampling).
-- Kunci    : primary key Id_Uji_Validasi_Detail_Final
-- Baris    : demo 0 | produksi 424
-- Dipakai  : FormulatorTrialSampelController
-- Struktur : 10 kolom - Id_Uji_Validasi_Detail_Final int, No_Sampel varchar(30), Id_Jenis_Analisa
--            int, Tahapan_Ke int, Tanggal datetime, Jam varchar(8), Flag_Layak char(1),
--            Flag_Resampling char(1), Id_User varchar(30), No_Sub_Sampel varchar(30)
SELECT * FROM dbo.N_EMI_LIMS_Hasil_Uji_Validasi_Detail_Final;
GO

-- -------------------------------------------------------------------------------------------------
-- 8.13  N_EMI_LIMS_Activity_Uji_Sampel                                                        TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Log pengiriman hasil uji formulator (padanan N_EMI_LAB_Activity_Uji_Sampel).
-- Kunci    : primary key Id_Log_Activity
-- Baris    : demo 0 | produksi 462
-- Dipakai  : FormulatorTrialSampelController
-- Struktur : 10 kolom - Id_Log_Activity int, Kode_Perusahaan varchar(3), No_Po_Sampel varchar(30),
--            No_Fak_Sub_Po varchar(30), Jenis_Aktivitas varchar(50), Id_Jenis_Analisa int,
--            Keterangan text(2147483647), Id_User varchar(30), Tanggal datetime, Jam varchar(8)
SELECT * FROM dbo.N_EMI_LIMS_Activity_Uji_Sampel;
GO

-- -------------------------------------------------------------------------------------------------
-- 8.14  N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail                                           TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Jejak audit perubahan nilai hasil uji formulator.
-- Kunci    : primary key Id_Activity_Uji_Sampel
-- Baris    : demo 0 | produksi 493
-- Dipakai  : FormulatorTrialSampelController
-- Struktur : 13 kolom - Id_Activity_Uji_Sampel int, Id_Log_Activity_Sampel int, Kode_Perusahaan
--            varchar(3), No_Po_Sampel varchar(30), No_Fak_Sub_Po varchar(30), Id_Jenis_Analisa int,
--            Value_Baru float, Value_Lama float, Tanggal datetime, Jam varchar(8), Id_User
--            varchar(30), Status_Submit varchar(30), Id_Perhitungan int
SELECT * FROM dbo.N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail;
GO

-- -------------------------------------------------------------------------------------------------
-- 8.15  N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail                                       TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Jejak audit perubahan nilai parameter masukan formulator, beserta
--            Alasan_Mengubah_Data.
-- Kunci    : primary key Id_Activity_Uji_Sampel
-- Baris    : demo 0 | produksi 555
-- Dipakai  : FormulatorTrialSampelController
-- Struktur : 14 kolom - Id_Activity_Uji_Sampel int, Id_Log_Activity_Sampel int, Kode_Perusahaan
--            varchar(3), No_Po_Sampel varchar(30), No_Fak_Sub_Po varchar(30), Id_Jenis_Analisa int,
--            Id_Quality_Control int, Value_Baru float, Value_Lama float, Tanggal datetime, Jam
--            varchar(8), Id_User varchar(30), Status_Submit varchar(30), Alasan_Mengubah_Data
--            varchar(255)
SELECT * FROM dbo.N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail;
GO

-- -------------------------------------------------------------------------------------------------
-- 8.16  N_EMI_LIMS_Berkas_Uji_Lab                                                             TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Foto hasil uji formulator per faktur (dengan Id_Jenis_Analisa).
-- Kunci    : primary key Id_Berkas_Uji_Lab
-- Baris    : demo 0 | produksi 96
-- Dipakai  : FormulatorDashboardController, FormulatorTrialSampelController,
--            FormulatorValidasiHirarkiController, UjiSampelController
-- Struktur : 7 kolom - Id_Berkas_Uji_Lab int, No_Faktur varchar(255), No_Sampel varchar(255),
--            Berkas_Key varchar(32), File_Path varchar(255), Id_Jenis_Analisa int, Keterangan
--            text(2147483647)
SELECT * FROM dbo.N_EMI_LIMS_Berkas_Uji_Lab;
GO

-- -------------------------------------------------------------------------------------------------
-- 8.17  N_EMI_LIMS_Berkas_Uji_Lab_Temp                                           TABEL [HANYA DEMO]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Tabel sementara berkas formulator (dengan kolom cetak dan Gambar_Convert_Image).
--            Kosong dan tidak dipakai kode.
-- Kunci    : tanpa primary key
-- Baris    : demo 0 | produksi TIDAK ADA
-- Dipakai  : tidak dipakai kode aplikasi
-- Struktur : 10 kolom - Id_Berkas_Uji_Lab int, No_Faktur varchar(255), No_Sampel varchar(255),
--            Berkas_Key varchar(32), File_Path varchar(255), Id_Jenis_Analisa int,
--            Gambar_Convert_Image varbinary(max), Tanggal_Cetak datetime, Jam_Cetak datetime,
--            Userid_Cetak varchar(30)
SELECT * FROM dbo.N_EMI_LIMS_Berkas_Uji_Lab_Temp;
GO

-- =================================================================================================
-- 9. PENGGUNA, ROLE & HAK AKSES
--   Siapa bisa login, halaman apa yang tampil, aksi apa yang boleh, dan analisa apa yang
--   boleh diproses.
-- =================================================================================================

-- -------------------------------------------------------------------------------------------------
-- 9.1  N_EMI_LAB_Users                                                             TABEL [SENSITIF]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Akun pengguna LIMS: UserId, nama, password, PIN, jabatan, status aktif.
-- Catatan  : Kolom Password dan Pin SENSITIF - jangan membagikan hasil SELECT * tabel ini; pilih
--            kolom yang diperlukan saja.
-- Kunci    : primary key Id_Lab_Users
-- Baris    : demo 13 | produksi 44
-- Dipakai  : AuthController, BarangAnalisaController, BarangUjiMasterController,
--            BypassLimsController, FinalisasiLabProduksiTrialController,
--            FinalisasiSandboxController (+16 berkas lain)
-- Struktur : 8 kolom - Id_Lab_Users int, Kode_Perusahaan varchar(3), UserId varchar(15), Nama
--            varchar(255), Password varchar(255), Pin varchar(255), Flag_Aktif char(1), Id_Jabatan
--            int
SELECT * FROM dbo.N_EMI_LAB_Users;
GO

-- -------------------------------------------------------------------------------------------------
-- 9.2  N_EMI_LAB_Roles                                                                        TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Master role: LAB, FLM (Formulator), PRD (Produksi).
-- Kunci    : primary key Id_Role
-- Baris    : demo 3 | produksi 2
-- Dipakai  : AuthController, PrinterTemplatesControllerController, RoleMiddleware,
--            TrialUiController, UserRoleApi
-- Struktur : 8 kolom - Id_Role int, Kode_Perusahan varchar(3), Kode_Role varchar(50), Nama_Role
--            varchar(100), Deskripsi varchar(255), Tanggal datetime, Jam varchar(8), Flag_Aktif
--            char(1)
SELECT * FROM dbo.N_EMI_LAB_Roles;
GO

-- -------------------------------------------------------------------------------------------------
-- 9.3  N_EMI_LAB_User_Roles                                                                   TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Role milik tiap user (satu user dapat memiliki beberapa role).
-- Relasi   : Id_Role -> N_EMI_LAB_Roles.
-- Kunci    : primary key Id_User_Role
-- Baris    : demo 9 | produksi 59
-- Dipakai  : AuthController, PrinterTemplatesControllerController, RoleMiddleware,
--            TrialUiController, UserRoleApi
-- Struktur : 3 kolom - Id_User_Role int, Id_User varchar(50), Id_Role int
SELECT * FROM dbo.N_EMI_LAB_User_Roles;
GO

-- -------------------------------------------------------------------------------------------------
-- 9.4  N_EMI_LAB_Menus                                                                        TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Master menu sidebar LIMS: nama, ikon, URL, dan pengelompokan header/sub-header.
-- Kunci    : primary key Id_Menu
-- Baris    : demo 51 | produksi 50
-- Dipakai  : AuthController, CheckUserMenuAccess, ManagementAksesKontenController, Menu,
--            MenuController, MockupSiklusController (+2 berkas lain); migrasi
--            2026_07_17_000001_fix_menu_header_per_user
-- Struktur : 8 kolom - Id_Menu int, Kode_Perusahaan varchar(3), Nama_Menu varchar(225), Icon_Menu
--            varchar(255), Url_Menu varchar(255), Nama_Header varchar(255), Sub_Header
--            varchar(255), Sub_Sub_Header varchar(255)
SELECT * FROM dbo.N_EMI_LAB_Menus;
GO

-- -------------------------------------------------------------------------------------------------
-- 9.5  N_EMI_LAB_Sub_Menus                                                                    TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Master sub-menu skema lama. Kosong.
-- Kunci    : primary key Id_Sub_Menu
-- Baris    : demo 0 | produksi 0
-- Dipakai  : RoleMenuController, SubMenu, SubMenuController
-- Struktur : 4 kolom - Id_Sub_Menu int, Kode_Perusahaan varchar(3), Nama_Sub_Menu varchar(225),
--            Icon_Sub_Menu varchar(255)
SELECT * FROM dbo.N_EMI_LAB_Sub_Menus;
GO

-- -------------------------------------------------------------------------------------------------
-- 9.6  N_EMI_LAB_Role_Menu                                                                    TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Pemetaan menu/sub-menu per user skema lama - dipakai middleware CheckUserMenuAccess.
-- Kunci    : primary key Id_Role_Menu
-- Baris    : demo 260 | produksi 281
-- Dipakai  : CheckUserMenuAccess, RoleMenu, RoleMenuController
-- Struktur : 5 kolom - Id_Role_Menu int, Kode_Perusahaan varchar(3), Id_Menu int, Id_Sub_Menu int,
--            Id_User varchar(15)
SELECT * FROM dbo.N_EMI_LAB_Role_Menu;
GO

-- -------------------------------------------------------------------------------------------------
-- 9.7  N_EMI_LAB_Page_Access_2                                                                TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Hak membuka halaman per user: menu mana yang tampil beserta urutannya. Id_Page_Access
--            menjadi induk hak aksi dan hak konten.
-- Catatan  : Tanpa primary key.
-- Kunci    : tanpa primary key
-- Baris    : demo 134 | produksi 387
-- Dipakai  : AuthController, ManagementAksesKontenController, MockupSiklusController,
--            RoleMenuController, TrialUiController; migrasi
--            2026_05_17_000001_add_nama_header_to_page_access_2,
--            2026_07_17_000001_fix_menu_header_per_user
-- Struktur : 5 kolom - Id_Page_Access int, Kode_Perusahaan varchar(3), Id_Menu int, Id_User
--            varchar(15), Urutan_Menu int
SELECT * FROM dbo.N_EMI_LAB_Page_Access_2;
GO

-- -------------------------------------------------------------------------------------------------
-- 9.8  N_EMI_LAB_Role_Menu_Access                                                             TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Hak aksi per halaman: Id_Page_Access x Id_Aksi (N_EMI_LAB_Klasifikasi_Aksi) ->
--            Flag_Diizinkan; Flag_Access_Konten = halaman itu memakai pembatasan konten.
-- Kunci    : tanpa primary key
-- Baris    : demo 1.137 | produksi 1.580
-- Dipakai  : AuthController, ManagementAksesKontenController, TrialUiController
-- Struktur : 5 kolom - Id_Role_Menu_Access int, Id_Page_Access int, Id_Aksi int, Flag_Diizinkan
--            char(1), Flag_Access_Konten char(1)
SELECT * FROM dbo.N_EMI_LAB_Role_Menu_Access;
GO

-- -------------------------------------------------------------------------------------------------
-- 9.9  N_EMI_LAB_Role_Konten_Access                                                           TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Hak konten per halaman: jenis analisa (Id_Jenis_Analisa) yang boleh dilihat/diproses
--            user di halaman itu - mis. analisa yang boleh divalidasi pada menu "Validasi Trial
--            Produksi".
-- Kolom    : Id_Page_Access, Id_Jenis_Analisa, Kategori, Flag_Diizinkan.
-- Kunci    : tanpa primary key
-- Baris    : demo 857 | produksi 1.014
-- Dipakai  : AuthController, ManagementAksesKontenController, MockupSiklusController,
--            TrialUiController
-- Struktur : 5 kolom - Id_Role_Konten_Access int, Id_Page_Access int, Id_Jenis_Analisa int,
--            Kategori varchar(50), Flag_Diizinkan char(1)
SELECT * FROM dbo.N_EMI_LAB_Role_Konten_Access;
GO

-- -------------------------------------------------------------------------------------------------
-- 9.10  N_EMI_LAB_Klasifikasi_Aksi                                                            TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Master aksi hak akses: CREATE, VIEW, EDIT, DELETE, PRINT, FINALISASI, DETAIL,
--            RESAMPLING, VALIDASI.
-- Catatan  : Tanpa primary key.
-- Kunci    : tanpa primary key
-- Baris    : demo 9 | produksi 9
-- Dipakai  : AuthController, ManagementAksesKontenController, TrialUiController
-- Struktur : 2 kolom - Id_Klasifikasi_Actions int, Nama_Aksi varchar(255)
SELECT * FROM dbo.N_EMI_LAB_Klasifikasi_Aksi;
GO

-- -------------------------------------------------------------------------------------------------
-- 9.11  N_EMI_View_Users                                                                 VIEW [ERP]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : View pengguna ERP (Kode_Perusahaan, UserID) - dicocokkan saat login.
-- Baris    : view (tanpa hitungan baris) - ada di demo & produksi
-- Dipakai  : AuthController
-- Struktur : 2 kolom - Kode_Perusahaan varchar(3), UserID varchar(15)
SELECT * FROM dbo.N_EMI_View_Users;
GO

-- =================================================================================================
-- 10. CETAK LABEL QR
--   Templat label QR sampel untuk printer di lini produksi.
-- =================================================================================================

-- -------------------------------------------------------------------------------------------------
-- 10.1  N_EMI_LAB_Master_Printer_Templates                                                    TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Master templat label: nama, lebar & tinggi label, jarak antar-label, arah cetak.
-- Kunci    : primary key Id_Master_Printer_Templates
-- Baris    : demo 2 | produksi 2
-- Dipakai  : BypassLimsController, FormulatorCetakUlangQrCodeController,
--            FormulatorRegistrasiController, POSampleController,
--            PrinterTemplatesControllerController, QuisyController
-- Struktur : 10 kolom - Id_Master_Printer_Templates int, Nama_Template varchar(255), Lebar_Label
--            int, Tinggi_Label int, Gap_Antar_Label int, Direction int, Tanggal datetime, Jam
--            varchar(8), Id_User varchar(15), Flag_Aktif char(1)
SELECT * FROM dbo.N_EMI_LAB_Master_Printer_Templates;
GO

-- -------------------------------------------------------------------------------------------------
-- 10.2  N_EMI_LAB_Printer_Template_Items                                                      TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Elemen di dalam templat label: teks/QR, posisi X/Y, font, rotasi, skala, isi konten,
--            pengaturan QR (ECC, ukuran, model).
-- Relasi   : Id_Master_Printer_Templates -> N_EMI_LAB_Master_Printer_Templates.
-- Kunci    : primary key Id_Printer_Template_Items
-- Baris    : demo 15 | produksi 15
-- Dipakai  : BypassLimsController, FormulatorCetakUlangQrCodeController,
--            FormulatorRegistrasiController, POSampleController,
--            PrinterTemplatesControllerController, QuisyController
-- Struktur : 18 kolom - Id_Printer_Template_Items int, Id_Master_Printer_Templates int, Jenis
--            varchar(255), Lebar_Label int, Posisi_X int, Posisi_Y int, Font varchar(255), Rotation
--            int, Scale_X int, Scale_Y int, Isi_Konten varchar(255), Qr_Ecc varchar(255), Qr_Size
--            int, Qr_Model varchar(255), Tanggal datetime, Jam varchar(8), Id_User varchar(15),
--            Flag_Aktif char(1)
SELECT * FROM dbo.N_EMI_LAB_Printer_Template_Items;
GO

-- -------------------------------------------------------------------------------------------------
-- 10.3  N_EMI_LAB_Printer_Template_Transaksi                                                  TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Templat yang dipakai per role (Id_Role) beserta templat bawaan (Flag_Default) saat
--            mencetak.
-- Kunci    : primary key Id_Template_Transaksi
-- Baris    : demo 22 | produksi 22
-- Dipakai  : FormulatorCetakUlangQrCodeController, FormulatorRegistrasiController,
--            POSampleController, PrinterTemplatesControllerController, QuisyController
-- Struktur : 9 kolom - Id_Template_Transaksi int, Id_Master_Printer_Templates int, Flag_Default
--            char(1), Tanggal datetime, Jam varchar(8), Id_User varchar(15), Keterangan
--            varchar(255), Flag_Aktif char(1), Id_Role int
SELECT * FROM dbo.N_EMI_LAB_Printer_Template_Transaksi;
GO

-- =================================================================================================
-- 11. SISTEM APLIKASI
--   Tabel teknis Laravel: antrean job, sesi, dan pelacakan ekspor.
-- =================================================================================================

-- -------------------------------------------------------------------------------------------------
-- 11.1  N_EMI_LAB_Jobs                                                                        TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Antrean job Laravel (config/queue.php), mis. sinkronisasi barang uji master.
-- Kunci    : primary key id
-- Baris    : demo 1 | produksi 0
-- Dipakai  : BarangUjiMasterController; konfigurasi config/queue.php
-- Struktur : 7 kolom - id bigint, queue varchar(255), payload varchar(max), attempts tinyint,
--            reserved_at int, available_at int, created_at int
SELECT * FROM dbo.N_EMI_LAB_Jobs;
GO

-- -------------------------------------------------------------------------------------------------
-- 11.2  N_EMI_LAB_Failed_Jobs                                                                 TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Job antrean yang gagal beserta payload dan pesan error (config/queue.php).
-- Kunci    : primary key id
-- Baris    : demo 0 | produksi 0
-- Dipakai  : konfigurasi config/queue.php
-- Struktur : 7 kolom - id bigint, uuid varchar(255), connection varchar(max), queue varchar(max),
--            payload varchar(max), exception varchar(max), failed_at datetime
SELECT * FROM dbo.N_EMI_LAB_Failed_Jobs;
GO

-- -------------------------------------------------------------------------------------------------
-- 11.3  N_EMI_LAB_Sessions                                                         TABEL [SENSITIF]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Sesi login bila SESSION_DRIVER=database (config/session.php).
-- Catatan  : Kolom payload berisi data sesi - jangan dibagikan.
-- Kunci    : primary key id
-- Baris    : demo 0 | produksi 7
-- Dipakai  : konfigurasi config/session.php
-- Struktur : 6 kolom - id nvarchar(255), user_id bigint, ip_address nvarchar(45), user_agent
--            nvarchar(max), payload nvarchar(max), last_activity int
SELECT * FROM dbo.N_EMI_LAB_Sessions;
GO

-- -------------------------------------------------------------------------------------------------
-- 11.4  N_EMI_LAB_Export_Tracking                                                             TABEL
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Pelacakan proses ekspor di latar belakang (mis. rekap_pdf): status, progres, pesan,
--            dan lokasi berkas hasil.
-- Kunci    : primary key id
-- Baris    : demo 14 | produksi 166
-- Dipakai  : ExportRekapSampelJob, UjiSampelController; migrasi
--            2026_05_21_000002_add_file_path_to_export_tracking
-- Struktur : 10 kolom - id varchar(36), jenis_export varchar(50), status varchar(50), progress int,
--            message varchar(255), file_url varchar(max), error_message varchar(max), created_at
--            datetime, updated_at datetime, file_path varchar(500)
SELECT * FROM dbo.N_EMI_LAB_Export_Tracking;
GO

-- =================================================================================================
-- 12. DATA ERP YANG DIBACA LIMS
--   Milik ERP, dibaca LIMS untuk identitas produksi. LIMS tidak menulis ke tabel/view ini.
-- =================================================================================================

-- -------------------------------------------------------------------------------------------------
-- 12.1  EMI_Master_Mesin                                                                TABEL [ERP]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Master mesin produksi (GRINDER, MIXER, FILLER, AUTOCLAVE, ...).
-- Kolom    : Flag_Multi_Qrcode = sampel mesin ini memakai multi QR; Jumlah_Print_QRCode; Flag_FG;
--            Urutan_Prosessing; Flag_Input_Suhu; Id_Divisi_Mesin.
-- Relasi   : Id_Master_Mesin dirujuk PO_Sampel.Id_Mesin, Barang_Analisa.Id_Master_Mesin,
--            Standar_Rentang.Id_Master_Mesin, Binding_Identity.Id_Mesin.
-- Kunci    : primary key Id_Master_Mesin
-- Baris    : demo 5 | produksi 22
-- Dipakai  : BarangAnalisaController, BarangUjiMasterController, BindingIdentityController,
--            BypassLimsController, CakupanMesinService, DashboardController (+21 berkas lain)
-- Struktur : 14 kolom - Id_Master_Mesin int, Kode_Perusahaan varchar(3), Divisi_Mesin varchar(30),
--            Seri_Mesin varchar(20), Nama_Mesin varchar(20), Keterangan varchar(50),
--            Id_Divisi_Mesin int, Flag_Multi_Qrcode char(1), Jumlah_Print_QRCode int, Flag_Kg
--            char(1), NoUrut int, Flag_FG char(1), Urutan_Prosessing int, Flag_Input_Suhu char(1)
SELECT * FROM dbo.EMI_Master_Mesin;
GO

-- -------------------------------------------------------------------------------------------------
-- 12.2  N_EMI_Divisi_Mesin                                                 TABEL [ERP] [HANYA DEMO]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Master divisi mesin - dipakai halaman master mesin LIMS.
-- Catatan  : Belum ada di produksi, padahal dipakai MasterMesinController.
-- Kunci    : primary key Id_Divisi + Kode_Perusahaan
-- Baris    : demo 6 | produksi TIDAK ADA
-- Dipakai  : MasterMesinController
-- Struktur : 4 kolom - Kode_Perusahaan nchar(10), Id_Divisi int, Kode_Divisi nchar(10), Keterangan
--            varchar(30)
SELECT * FROM dbo.N_EMI_Divisi_Mesin;
GO

-- -------------------------------------------------------------------------------------------------
-- 12.3  EMI_Order_Produksi                                                              TABEL [ERP]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Order produksi (PO) ERP: nomor faktur, barang, Kode_Formula, jumlah, status release,
--            Flag_Trial_Produksi. LIMS membaca formula & identitas PO dari sini.
-- Kunci    : primary key Kode_Perusahaan + Lokasi + No_Faktur
-- Baris    : demo 287 | produksi 1.133
-- Dipakai  : IdentitasPoService, MockupContohService, TrackingController
-- Struktur : 41 kolom - Kode_Perusahaan varchar(3), No_Faktur varchar(20), Lokasi varchar(30),
--            Status char(1), Tanggal date, Tanggal_Release datetime, Jam varchar(8), Jam_Release
--            varchar(8), UserId varchar(20), UserId_Release varchar(15), Id_Routing bigint, Selesai
--            char(1), Keterangan varchar(200), Id_Jenis_Produk bigint, Kode_Formula varchar(30),
--            Flag_Release char(1), Id_Schedule int, Tanggal_Produksi datetime, Jam_Produksi
--            varchar(8), Id_Line int, Jumlah float, Satuan varchar(8), Kode_Barang varchar(40),
--            Kode_stock_Owner varchar(15), Flag_Selesai_Split char(1), Flag_Selesai_Produksi
--            char(1), Flag_Selesai_Hasil_Produksi char(1), Berat float,
--            Flag_Selesai_Request_Material char(1), Flag_Commercial char(1), RV timestamp,
--            Flag_Validasi_Outstanding char(1), UserID_Validasi_Outstanding char(50),
--            Tanggal_Validasi_Outstanding datetime, Jam_Validasi_Outstanding char(8),
--            Flag_preservative char(1), Urut_Production_Schedule int, Tanggal_Batal_PO datetime,
--            Jam_Batal_PO varchar(8), UserId_Batal_PO varchar(30), Flag_Trial_Produksi char(1)
SELECT * FROM dbo.EMI_Order_Produksi;
GO

-- -------------------------------------------------------------------------------------------------
-- 12.4  N_EMI_View_Barang                                                                VIEW [ERP]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : View master barang ERP (Kode_Barang, Nama) - sumber nama produk di seluruh layar LIMS.
-- Baris    : view (tanpa hitungan baris) - ada di demo & produksi
-- Dipakai  : BarangAnalisaController, BypassLimsController, DaftarAnalisaKurangExport,
--            DashboardController, ExportRekapSampelJob, FinalisasiLabProduksiTrialController (+16
--            berkas lain)
-- Struktur : 4 kolom - Kode_Perusahaan varchar(3), Kode_Stock_Owner varchar(15), Kode_Barang
--            varchar(40), Nama varchar(100)
SELECT * FROM dbo.N_EMI_View_Barang;
GO

-- -------------------------------------------------------------------------------------------------
-- 12.5  N_EMI_View_Order_Produksi                                                        VIEW [ERP]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : View PO produksi yang dapat diregistrasi sampelnya (status, release,
--            Flag_Trial_Produksi).
-- Baris    : view (tanpa hitungan baris) - ada di demo & produksi
-- Dipakai  : ExportRekapSampelJob, FormulatorCetakUlangQrCodeController,
--            FormulatorRegistrasiController, POSampleController, QuisyController,
--            UjiSampelController
-- Struktur : 10 kolom - Kode_Perusahaan varchar(3), Kode_Stock_Owner varchar(15), Kode_Barang
--            varchar(40), No_Faktur varchar(20), Jumlah float, Tanggal datetime, Status char(1),
--            Satuan varchar(8), Flag_Release char(1), Flag_Trial_Produksi char(1)
SELECT * FROM dbo.N_EMI_View_Order_Produksi;
GO

-- -------------------------------------------------------------------------------------------------
-- 12.6  N_EMI_View_Split_Production_Order                                                VIEW [ERP]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : View split PO produksi (No_Transaksi = nomor split, Jumlah_Batch).
-- Baris    : view (tanpa hitungan baris) - ada di demo & produksi
-- Dipakai  : FormulatorCetakUlangQrCodeController, QuisyController
-- Struktur : 8 kolom - Kode_Perusahaan varchar(3), Kode_Barang varchar(50), No_PO varchar(25),
--            No_Transaksi varchar(25), Jumlah float, Jumlah_Batch float, Tanggal datetime, Jam
--            varchar(8)
SELECT * FROM dbo.N_EMI_View_Split_Production_Order;
GO

-- -------------------------------------------------------------------------------------------------
-- 12.7  N_EMI_View_Trial_Order_Produksi                                                  VIEW [ERP]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : View PO trial (formulator/R&D), termasuk Kode_Formula.
-- Baris    : view (tanpa hitungan baris) - ada di demo & produksi
-- Dipakai  : FormulatorRegistrasiController, FormulatorTrialSampelController
-- Struktur : 10 kolom - Kode_Perusahaan varchar(3), Kode_Stock_Owner varchar(15), Kode_Barang
--            varchar(40), Kode_Formula varchar(30), No_Faktur varchar(20), Jumlah float, Tanggal
--            date, Status char(1), Satuan varchar(8), Flag_Release char(1)
SELECT * FROM dbo.N_EMI_View_Trial_Order_Produksi;
GO

-- -------------------------------------------------------------------------------------------------
-- 12.8  N_EMI_View_Trial_Split_Production_Order                                          VIEW [ERP]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : View split PO trial.
-- Baris    : view (tanpa hitungan baris) - ada di demo & produksi
-- Dipakai  : FormulatorRegistrasiController
-- Struktur : 8 kolom - Kode_Perusahaan varchar(3), Kode_Barang varchar(50), No_PO varchar(25),
--            No_Transaksi varchar(25), Jumlah float, Jumlah_Batch float, Tanggal datetime, Jam
--            varchar(8)
SELECT * FROM dbo.N_EMI_View_Trial_Split_Production_Order;
GO

-- =================================================================================================
-- 13. VIEW LAPORAN (HANYA DI DEMO)
--   View bantu laporan yang ada di database demo; tidak dipakai kode aplikasi dan belum
--   ada di produksi.
-- =================================================================================================

-- -------------------------------------------------------------------------------------------------
-- 13.1  N_EMI_View_Hasil_Uji_Laboratorium_Rpt                                     VIEW [HANYA DEMO]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Laporan hasil uji per split/batch/analisa: rata-rata hasil vs standar min/max, status,
--            final, approval, serta status Look View & Analisa Lab per split.
-- Baris    : view (tanpa hitungan baris) - hanya di demo
-- Dipakai  : tidak dipakai kode aplikasi
-- Struktur : 16 kolom - No_Split_Po varchar(30), No_Batch int, Nama_Mesin varchar(20),
--            Kode_Aktivitas_Lab varchar(255), Id_Jenis_Analisa int, Jenis_Analisa varchar(255),
--            No_Po_Sampel varchar(30), Avg_Hasil float, Std_Min varchar(255), Std_Max varchar(255),
--            Hasil_Uji varchar(255), Status char(1), Flag_Final char(1), Flag_Approval char(1),
--            status_lock_view_split varchar(17), status_analisa_lab_split varchar(17)
SELECT * FROM dbo.N_EMI_View_Hasil_Uji_Laboratorium_Rpt;
GO

-- -------------------------------------------------------------------------------------------------
-- 13.2  V_LIMS_Kelayakan_Uji                                                      VIEW [HANYA DEMO]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Rincian kelayakan per hasil uji: pengguna, sampel, analisa, hasil angka/teks, acuan,
--            kesimpulan layak, alasan, keputusan manusia, bukti dukung.
-- Baris    : view (tanpa hitungan baris) - hanya di demo
-- Dipakai  : tidak dipakai kode aplikasi
-- Struktur : 29 kolom - Pengguna varchar(30), No_Po_Sampel varchar(30), Sub_Sampel varchar(30),
--            No_Po varchar(30), No_Split_Po varchar(30), No_Batch int, Kode_Barang varchar(30),
--            Nama_Barang varchar(100), Kode_Aktivitas_Lab varchar(10), Jenis_Analisa varchar(255),
--            Urutan_Tahapan int, Kode_Analisa varchar(255), Nama_Analisa varchar(255),
--            Id_Jenis_Analisa int, Flag_Layak char(1), Kesimpulan varchar(17), Standar_Di_LIMS
--            varchar(21), Alasan varchar(305), Hasil_Angka float, Hasil_Teks varchar(225),
--            Pilihan_Terpilih varchar(255), Acuan_Awal float, Acuan_Akhir float, Keputusan_Manusia
--            varchar(255), No_Faktur varchar(30), Nama_Mesin varchar(20), Tanggal datetime, Jam
--            varchar(8), Bukti_Dukung char(1)
SELECT * FROM dbo.V_LIMS_Kelayakan_Uji;
GO

-- -------------------------------------------------------------------------------------------------
-- 13.3  V_LIMS_Rekap_Pengguna                                                     VIEW [HANYA DEMO]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Rekap per pengguna per analisa per tanggal: total uji, jumlah sampel, layak/tidak
--            layak/belum ada standar, persentase layak.
-- Baris    : view (tanpa hitungan baris) - hanya di demo
-- Dipakai  : tidak dipakai kode aplikasi
-- Struktur : 13 kolom - Pengguna varchar(30), Kode_Aktivitas_Lab varchar(10), Jenis_Analisa
--            varchar(255), Tanggal date, Total_Uji int, Jml_Sampel int, Jml_Jenis int, Jml_Layak
--            int, Jml_Tidak_Layak int, Jml_Belum_Ada_Standar int, Persen_Layak decimal,
--            Kesimpulan_Tahapan varchar(20), Alasan_Belum_Ada_Standar varchar(68)
SELECT * FROM dbo.V_LIMS_Rekap_Pengguna;
GO

-- =================================================================================================
-- 14. PROTOTIPE MONITORING SUHU PROSES (HANYA DI DEMO)
--   Rancangan pemantauan suhu proses produksi berbasis RFID per titik (lokasi) dan jenis
--   kemasan (Can/Pouch/Gravy). Tidak dipakai kode aplikasi ini dan belum ada di produksi.
-- =================================================================================================

-- -------------------------------------------------------------------------------------------------
-- 14.1  N_EMI_Lab_Monitoring_Frans_Master_Lokasi                                 TABEL [HANYA DEMO]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Master titik pemantauan (GRAVY, COLD STORAGE, GRINDER IN/OUT, MIXER IN, ...) dengan
--            batas suhu Min_Suhu-Max_Suhu, urutan, lokasi berikutnya, dan aturan wajib per jenis
--            kemasan.
-- Kunci    : primary key Id_Lokasi
-- Baris    : demo 16 | produksi TIDAK ADA
-- Dipakai  : tidak dipakai kode aplikasi
-- Struktur : 21 kolom - Id_Lokasi int, Kode_Perusahaan varchar(3), Nama_Lokasi varchar(50),
--            Lokasi_Pairing varchar(30), Urutan_Lokasi int, Urutan_Tampil int, Min_Suhu decimal,
--            Max_Suhu decimal, Flag_Can char(1), Flag_Pouch char(1), Flag_Gravy char(1),
--            Next_Lokasi_Can int, Next_Lokasi_Pouch int, Next_Lokasi_Gravy int, Flag_Urutan_Pertama
--            char(1), Flag_Parameter_Tambahan char(1), Flag_No_RFID char(1), Flag_Is_Validasi
--            char(1), Flag_Wajib_Can char(1), Flag_Wajib_Pouch char(1), Flag_Wajib_Gravy char(1)
SELECT * FROM dbo.N_EMI_Lab_Monitoring_Frans_Master_Lokasi;
GO

-- -------------------------------------------------------------------------------------------------
-- 14.2  N_EMI_Lab_Monitoring_Frans_Master_Parameter                              TABEL [HANYA DEMO]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Parameter tambahan yang dicatat di lokasi tertentu (nama, jenis routing, tipe input).
-- Kunci    : primary key Id_Parameter
-- Baris    : demo 13 | produksi TIDAK ADA
-- Dipakai  : tidak dipakai kode aplikasi
-- Struktur : 6 kolom - Id_Parameter int, Kode_Perusahaan varchar(3), Id_Lokasi int, Nama
--            varchar(100), Jenis_Routing varchar(10), Type_Input varchar(20)
SELECT * FROM dbo.N_EMI_Lab_Monitoring_Frans_Master_Parameter;
GO

-- -------------------------------------------------------------------------------------------------
-- 14.3  N_EMI_Lab_Monitoring_Frans_Mesin_Config                                  TABEL [HANYA DEMO]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Konfigurasi wajib/tidak, RFID, dan validasi per lokasi & jenis kemasan.
-- Kunci    : primary key No_Urut
-- Baris    : demo 0 | produksi TIDAK ADA
-- Dipakai  : tidak dipakai kode aplikasi
-- Struktur : 11 kolom - No_Urut int, Kode_Perusahaan varchar(3), Id_Lokasi int, Flag_No_RFID
--            char(1), Flag_Is_Validasi char(1), Flag_Wajib char(1), Flag_Wajib_Can char(1),
--            Flag_Wajib_Pouch char(1), Flag_Wajib_Gravy char(1), Urutan_Tampil int, Keterangan
--            varchar(200)
SELECT * FROM dbo.N_EMI_Lab_Monitoring_Frans_Mesin_Config;
GO

-- -------------------------------------------------------------------------------------------------
-- 14.4  N_EMI_Lab_Monitoring_Frans_Parent                                        TABEL [HANYA DEMO]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Header transaksi pemantauan per PO split/batch dan jenis routing; status selesai.
-- Kunci    : primary key Kode_Perusahaan + No_Transaksi
-- Baris    : demo 0 | produksi TIDAK ADA
-- Dipakai  : tidak dipakai kode aplikasi
-- Struktur : 13 kolom - No_Urut_Oto int, Kode_Perusahaan varchar(3), No_Transaksi varchar(30),
--            No_Split varchar(30), No_Batch varchar(20), Jenis_Routing varchar(10), Status char(1),
--            Flag_Selesai char(1), Tanggal datetime, Jam varchar(8), Userid varchar(30),
--            Userid_Selesai varchar(30), Tgl_Selesai datetime
SELECT * FROM dbo.N_EMI_Lab_Monitoring_Frans_Parent;
GO

-- -------------------------------------------------------------------------------------------------
-- 14.5  N_EMI_Lab_Monitoring_Frans_Session                                       TABEL [HANYA DEMO]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Sesi pengecekan per lokasi dalam satu transaksi.
-- Kunci    : primary key No_Urut_Session
-- Baris    : demo 0 | produksi TIDAK ADA
-- Dipakai  : tidak dipakai kode aplikasi
-- Struktur : 11 kolom - No_Urut_Session int, Kode_Perusahaan varchar(3), No_Transaksi varchar(30),
--            Id_Lokasi int, No_Urut_Check int, Flag_Sementara char(1), Userid varchar(30), Tanggal
--            datetime, Jam varchar(8), Keterangan varchar(200), Status char(1)
SELECT * FROM dbo.N_EMI_Lab_Monitoring_Frans_Session;
GO

-- -------------------------------------------------------------------------------------------------
-- 14.6  N_EMI_Lab_Monitoring_Frans_Detail                                        TABEL [HANYA DEMO]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Pembacaan suhu per tag RFID dalam satu sesi.
-- Kunci    : primary key No_Urut
-- Baris    : demo 0 | produksi TIDAK ADA
-- Dipakai  : tidak dipakai kode aplikasi
-- Struktur : 6 kolom - No_Urut int, Kode_Perusahaan varchar(3), No_Transaksi varchar(30),
--            No_Urut_Session int, RFID_Tag varchar(100), Suhu float
SELECT * FROM dbo.N_EMI_Lab_Monitoring_Frans_Detail;
GO

-- -------------------------------------------------------------------------------------------------
-- 14.7  N_EMI_Lab_Monitoring_Frans_Parameter                                     TABEL [HANYA DEMO]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Nilai parameter tambahan per sesi.
-- Kunci    : primary key No_Urut_Oto
-- Baris    : demo 0 | produksi TIDAK ADA
-- Dipakai  : tidak dipakai kode aplikasi
-- Struktur : 6 kolom - No_Urut_Oto int, Kode_Perusahaan varchar(3), No_Transaksi varchar(30),
--            No_Urut_Session int, Id_Parameter int, Value_Parameter varchar(100)
SELECT * FROM dbo.N_EMI_Lab_Monitoring_Frans_Parameter;
GO

-- -------------------------------------------------------------------------------------------------
-- 14.8  N_EMI_Lab_Monitoring_Frans_Foto                                          TABEL [HANYA DEMO]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Foto bukti per sesi.
-- Kunci    : primary key No_Urut
-- Baris    : demo 0 | produksi TIDAK ADA
-- Dipakai  : tidak dipakai kode aplikasi
-- Struktur : 7 kolom - No_Urut int, Kode_Perusahaan varchar(3), No_Transaksi varchar(30),
--            No_Urut_Session int, Nama_File varchar(250), Flag_Berhasil_Simpan char(1),
--            Flag_Sementara char(1)
SELECT * FROM dbo.N_EMI_Lab_Monitoring_Frans_Foto;
GO

-- -------------------------------------------------------------------------------------------------
-- 14.9  N_EMI_Lab_Monitoring_Frans_Validasi                                      TABEL [HANYA DEMO]
-- -------------------------------------------------------------------------------------------------
-- Fungsi   : Validasi transaksi pemantauan: status, catatan, validator, waktu.
-- Kunci    : primary key No_Urut_Oto
-- Baris    : demo 0 | produksi TIDAK ADA
-- Dipakai  : tidak dipakai kode aplikasi
-- Struktur : 8 kolom - No_Urut_Oto int, Kode_Perusahaan varchar(3), No_Transaksi varchar(30),
--            Status_Validasi varchar(10), Catatan varchar(300), Userid_Validasi varchar(30),
--            Tanggal_Validasi datetime, Jam_Validasi varchar(8)
SELECT * FROM dbo.N_EMI_Lab_Monitoring_Frans_Validasi;
GO

-- =================================================================================================
-- LAMPIRAN A. DIRUJUK KODE, TIDAK ADA DI DATABASE (demo maupun produksi)
--   Sisa modul lama; SELECT di bawah sengaja dijadikan komentar karena pasti gagal.
-- =================================================================================================
-- SELECT * FROM dbo.EMI_LAB_Master_Mac;                  -- dirujuk BindingMacMesinController (MAC perangkat)
-- SELECT * FROM dbo.EMI_LAB_Binding_Mac_Mesin;           -- dirujuk BindingMacMesinController
-- SELECT * FROM dbo.EMI_LAB_Master_Mesin;                -- dirujuk model App\Models\Mesin
-- SELECT * FROM dbo.EMI_LAB_Mesin;                       -- dirujuk BindingMacMesinController

-- =================================================================================================
-- LAMPIRAN B. TIDAK DIMASUKKAN (namanya mengandung "lab" / tertangkap pencarian, bukan LIMS)
-- =================================================================================================
--   - N_EMI_Barcode_Label_Barcode_GR_1 / _GR_1_Scrap / _GR_2 / _GR_2_Scrap,
--     N_EMI_Barcode_Label_Retur_Packaging, N_EMI_Cetak_Label_Packing_Box / _Pallet,
--     N_EMI_Transaksi_Trial_Barcode_Label_Barcode_GR_1, Cetak_Label_Barcode_Asset,
--     EMI_Label_Kemasan - label barcode gudang/kemasan
--   - Laba_Rugi, Laba_Rugi_New, VW_Laba_Rugi - akuntansi ("laba")
--   - Pelabuhan, Pelabuhan_Supplier, Storage - logistik
--   - KPI_lemburs_Test, Akun - modul absensi/akun ERP

-- =================================================================================================
-- LAMPIRAN C. QUERY BANTU (baca saja)
-- =================================================================================================

-- C.1 Jumlah baris seluruh tabel LIMS pada database yang sedang dipilih.
SELECT t.name AS Nama_Tabel, SUM(p.rows) AS Jumlah_Baris
FROM sys.tables t
JOIN sys.partitions p ON p.object_id = t.object_id AND p.index_id IN (0, 1)
WHERE t.name LIKE 'N[_]EMI[_]LAB[_]%' OR t.name LIKE 'N[_]EMI[_]LIMS[_]%' OR t.name LIKE 'N[_]LIMS[_]%'
   OR t.name LIKE 'EMI[_]LAB[_]%' OR t.name LIKE 'N[_]EMI[_]Lab[_]%'
GROUP BY t.name
ORDER BY t.name;
GO

-- C.2 Struktur kolom seluruh tabel & view LIMS (nama, tipe, panjang, boleh NULL).
SELECT c.TABLE_NAME, c.ORDINAL_POSITION, c.COLUMN_NAME, c.DATA_TYPE, c.CHARACTER_MAXIMUM_LENGTH, c.IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS c
WHERE c.TABLE_NAME LIKE 'N[_]EMI[_]LAB[_]%' OR c.TABLE_NAME LIKE 'N[_]EMI[_]LIMS[_]%' OR c.TABLE_NAME LIKE 'N[_]LIMS[_]%'
   OR c.TABLE_NAME LIKE 'EMI[_]LAB[_]%' OR c.TABLE_NAME LIKE 'N[_]EMI[_]Lab[_]%' OR c.TABLE_NAME LIKE 'V[_]LIMS[_]%'
ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION;
GO

-- C.3 Cek cepat: tabel pembaharuan yang belum ada di database yang dipilih.
SELECT v.Nama_Tabel, CASE WHEN OBJECT_ID('dbo.' + v.Nama_Tabel) IS NULL THEN 'BELUM ADA' ELSE 'ADA' END AS Status
FROM (VALUES ('N_EMI_LAB_Hasil_Uji_Approval_Aktivitas'), ('N_EMI_LAB_Verifikasi_Status'),
             ('N_EMI_LAB_Verifikasi_Keputusan'), ('N_EMI_LAB_Verifikasi_Kewenangan'),
             ('N_EMI_LAB_Verifikasi_Header'), ('N_EMI_LAB_Verifikasi_Detail'),
             ('N_EMI_LAB_Verifikasi_Riwayat')) AS v(Nama_Tabel);
GO
