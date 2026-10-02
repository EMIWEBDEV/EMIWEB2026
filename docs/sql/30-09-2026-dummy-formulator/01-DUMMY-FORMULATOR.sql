-- ============================================================================
-- 01-DUMMY-FORMULATOR.sql
-- Tanggal : 30-09-2026
-- Modul   : FORMULATOR (trial R&D) - data dummy lengkap, sudah divalidasi LIMS
-- Database: HANYA emi_tm_demo. Skrip menolak berjalan di database lain.
--
-- HASIL:
--   3 sampel trial mesin AUTOCLAVE pada PO trial PRT0426-00004
--   (BRG2410001 - LIFE CAT 400GR SALMON KITTEN, formula FRM0426-00005):
--
--     FT0926-0001  batch 2  registrasi 14-09-2026  divalidasi 18-09-2026  semua layak
--     FT0926-0002  batch 3  registrasi 21-09-2026  divalidasi 25-09-2026  semua layak
--     FT0926-0003  batch 4  registrasi 23-09-2026  divalidasi 29-09-2026  tekstur tidak layak
--
--   Tiap sampel: 1 pcs (sub-sampel QR -1, tempat seluruh hasil) dan 12 analisa:
--     Look View      : AROMA, WARNA (+ 2 foto), TEKSTUR KALENG (Trial Kitchen R&D)
--     Analisa Lab    : TRIAL PROTEIN (CRUDE PROTEIN), TRIAL ASH, TRIAL MOISTURE,
--                      TRIAL MIKROBIOLOGI-AC / -YM / -EC / -SALMONELLA
--     Palatabilitas  : Responden Memakan, Konsumsi (gram)
--
--   Posisi alur: hasil uji sudah dikirim dan sudah DIVALIDASI (Validasi Hasil
--   Trial -> Status_Keputusan_Sampel 'terima', Flag_Selesai 'Y', jejak
--   validasi + log VALIDASI_FORMULATOR). Pra-finalisasi BELUM, sehingga ketiga
--   sampel muncul di antrean Validasi Hirarki / Pra-Finalisasi dengan status
--   tiap klasifikasi "MENUNGGU VALIDASI".
--
-- MASTER YANG DITAMBAHKAN (hanya bila belum ada):
--   Di demo maupun produksi, mikrobiologi hanya ada sebagai analisa role LAB,
--   padahal layar formulator menyaring Jenis_Analisa.Kode_Role = 'FLM'.
--   Karena itu dibuat versi FLM-nya, mengikuti pola TRIAL PROTEIN/ASH/MOISTURE:
--     * N_EMI_LAB_Jenis_Analisa   : TRMAC, TRMYM, TRMEC, TRMSL (role FLM, ANL)
--     * N_EMI_LAB_Perhitungan     : AC/YM/EC, rumus sama dengan versi LAB
--                                   ([103]-[104])/[103]*100, 2 desimal
--     * N_EMI_LAB_Binding_...     : AC/YM/EC -> QC 103 & 104, SALMONELLA -> QC 105
--     * N_EMI_LAB_Standar_Rentang : AC 0-500, YM 0-100, EC 0-10 (batas versi LAB)
--                                   untuk BRG2410001 x AUTOCLAVE
--     * ..._Non_Perhitungan       : SALMONELLA Negatif (layak) / Positif (tidak)
--     * N_EMI_LAB_Role_Konten_Access : hak konten analisa mikro FLM disalin
--                                   dari TRIAL PROTEIN (CRUDE PROTEIN), id 60
--     * N_EMI_LAB_Barang_Analisa  : 12 analisa di atas untuk BRG2410001 x
--                                   AUTOCLAVE, bagi semua akun ber-role FLM
--   Hak konten dibaca saat login: akun formulator perlu login ulang.
--
-- FOTO:
--   Look View WARNA (satu-satunya Look View dengan Flag_Foto 'Y' di master)
--   memakai ulang foto produk dari berkas lab (berkas/lab/*.png di GCS).
--   Tidak ada file baru yang diunggah. Berkas_Key dibuat baru per baris.
--
-- ULANG / HAPUS:
--   Aman dijalankan ulang: data transaksi ketiga sampel dihapus lalu dibuat
--   kembali (termasuk jejak pra-finalisasi/finalisasi bila sudah dicoba),
--   master tidak digandakan. Nomor faktur FUS dihitung ulang dari nomor
--   terakhir. Untuk menghapus: 02-HAPUS-DUMMY-FORMULATOR.sql.
--   Nomor FT0926-0001..0003 yang sudah dipakai sampel ASLI (Keterangan tidak
--   diawali "DATA DUMMY") membuat skrip berhenti tanpa mengubah apa pun.
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;

IF DB_NAME() <> N'emi_tm_demo'
    THROW 50001, N'Skrip dummy formulator hanya untuk database emi_tm_demo. Dibatalkan.', 1;

DECLARE
    @Kode_Barang  varchar(30) = 'BRG2410001',       -- LIFE CAT 400GR SALMON KITTEN (kaleng)
    @No_Po        varchar(30) = 'PRT0426-00004',    -- PO trial, formula FRM0426-00005
    @No_Split_Po  varchar(30) = 'PRT0426-00004-1',
    @Id_Mesin     int         = 4,                  -- AUTOCLAVE
    @Jumlah_Pcs   int         = 1,                  -- satu sub-sampel QR (-1); analisa dianggap
                                                    -- selesai bila SEMUA sub-sampel sudah diuji
    @Registrasi   varchar(30) = 'FRANS',
    @Uji_Kitchen  varchar(30) = 'FRANS',            -- Look View & Palatabilitas
    @Uji_Lab      varchar(30) = 'SV_LAB',           -- Analisa Lab
    @Validator    varchar(30) = 'VENGINE',
    @Sekarang     datetime    = dbo.Get_Date_Time();

IF NOT EXISTS (SELECT 1 FROM EMI_Master_Mesin WHERE Id_Master_Mesin = @Id_Mesin AND Nama_Mesin = 'AUTOCLAVE')
    THROW 50002, N'Mesin AUTOCLAVE (Id_Master_Mesin 4) tidak ditemukan.', 1;

IF NOT EXISTS (SELECT 1 FROM N_EMI_View_Trial_Order_Produksi WHERE No_Faktur = @No_Po AND Kode_Barang = @Kode_Barang)
    THROW 50003, N'PO trial PRT0426-00004 untuk BRG2410001 tidak ditemukan.', 1;

IF EXISTS (SELECT 1 FROM N_LIMS_PO_Sampel
           WHERE No_Sampel IN ('FT0926-0001', 'FT0926-0002', 'FT0926-0003')
             AND ISNULL(CAST(Keterangan AS varchar(max)), '') NOT LIKE 'DATA DUMMY%')
    THROW 50004, N'Nomor FT0926-0001..0003 sudah dipakai sampel asli. Dibatalkan.', 1;

IF EXISTS (SELECT 1 FROM N_LIMS_PO_Sampel
           WHERE No_Split_Po = @No_Split_Po AND No_Batch IN (2, 3, 4)
             AND ISNULL(CAST(Keterangan AS varchar(max)), '') NOT LIKE 'DATA DUMMY%')
    THROW 50005, N'Batch 2-4 PRT0426-00004-1 sudah dipakai sampel asli. Dibatalkan.', 1;

-- Rumus perhitungan yang dipakai untuk menyusun hasil di bawah harus sama
-- dengan master; bila master berubah, skrip berhenti daripada menulis hasil
-- yang tidak cocok dengan parameternya.
IF NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Perhitungan WHERE Id_Jenis_Analisa = 60 AND Kode_Role = 'FLM' AND Rumus = '[49]+[54]+[62]')
   OR NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Perhitungan WHERE Id_Jenis_Analisa = 58 AND Kode_Role = 'FLM' AND Rumus = '[49]+[54]+[62]')
   OR NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Perhitungan WHERE Id_Jenis_Analisa = 57 AND Kode_Role = 'FLM' AND Rumus = '[49]')
    THROW 50006, N'Rumus TRIAL PROTEIN/MOISTURE/ASH di master berbeda dari yang diharapkan skrip.', 1;

BEGIN TRANSACTION;

-- ============================================================================
-- 1. MASTER: analisa mikrobiologi versi FLM
-- ============================================================================
DECLARE @Mikro TABLE (
    Kode varchar(50) PRIMARY KEY, Nama varchar(100), Flag_Perhitungan char(1),
    Nama_Kolom varchar(20), Range_Awal float, Range_Akhir float);
INSERT INTO @Mikro VALUES
    ('TRMAC', 'TRIAL MIKROBIOLOGI-AC',         'Y',  'AC', 0, 500),
    ('TRMYM', 'TRIAL MIKROBIOLOGI-YM',         'Y',  'YM', 0, 100),
    ('TRMEC', 'TRIAL MIKROBIOLOGI-EC',         'Y',  'EC', 0, 10),
    ('TRMSL', 'TRIAL MIKROBIOLOGI-SALMONELLA', NULL, NULL, NULL, NULL);

INSERT INTO N_EMI_LAB_Jenis_Analisa
    (Kode_Analisa, Jenis_Analisa, Id_Mesin, Flag_Perhitungan, Created_At, Updated_At,
     Sifat_Kegiatan, Kode_Role, Id_User, Kode_Aktivitas_Lab, Flag_Foto)
SELECT m.Kode, m.Nama, NULL, m.Flag_Perhitungan, @Sekarang, @Sekarang,
       'Rutin', 'FLM', @Registrasi, 'ANL', 'T'
FROM @Mikro m
WHERE NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Jenis_Analisa j WHERE j.Kode_Analisa = m.Kode AND j.Kode_Role = 'FLM');

DECLARE @AC int, @YM int, @EC int, @SAL int;
SELECT @AC  = MIN(CASE WHEN Kode_Analisa = 'TRMAC' THEN id END),
       @YM  = MIN(CASE WHEN Kode_Analisa = 'TRMYM' THEN id END),
       @EC  = MIN(CASE WHEN Kode_Analisa = 'TRMEC' THEN id END),
       @SAL = MIN(CASE WHEN Kode_Analisa = 'TRMSL' THEN id END)
FROM N_EMI_LAB_Jenis_Analisa
WHERE Kode_Role = 'FLM' AND Kode_Analisa IN ('TRMAC', 'TRMYM', 'TRMEC', 'TRMSL');

DECLARE @IdMikro TABLE (Kode varchar(50) PRIMARY KEY, Id int);
INSERT INTO @IdMikro VALUES ('TRMAC', @AC), ('TRMYM', @YM), ('TRMEC', @EC), ('TRMSL', @SAL);

-- Rumus AC/YM/EC: sama dengan MIKROBIOLOGI-AC/YM/EC role LAB.
INSERT INTO N_EMI_LAB_Perhitungan
    (Id_Jenis_Analisa, Rumus, Nama_Kolom, Created_At, Updated_At, Hasil_Perhitungan, Kode_Perusahaan, Kode_Role, Id_User)
SELECT i.Id, '([103]-[104])/[103]*100', m.Nama_Kolom, @Sekarang, @Sekarang, 2, '001', 'FLM', @Registrasi
FROM @Mikro m JOIN @IdMikro i ON i.Kode = m.Kode
WHERE m.Flag_Perhitungan = 'Y'
  AND NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Perhitungan p WHERE p.Id_Jenis_Analisa = i.Id AND p.Kode_Role = 'FLM');

INSERT INTO N_EMI_LAB_Binding_Jenis_Analisa
    (Id_Jenis_Analisa, Id_Quality_Control, Keterangan, Created_At, Updated_At, Id_User, Kode_Role)
SELECT b.Id_Jenis_Analisa, b.Id_QC, NULL, @Sekarang, @Sekarang, NULL, 'FLM'
FROM (VALUES (@AC, 103), (@AC, 104), (@YM, 103), (@YM, 104), (@EC, 103), (@EC, 104), (@SAL, 105))
     AS b (Id_Jenis_Analisa, Id_QC)
WHERE NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Binding_Jenis_Analisa x
                  WHERE x.Id_Jenis_Analisa = b.Id_Jenis_Analisa AND x.Id_Quality_Control = b.Id_QC);

-- Standar rentang FLM: mikro baru + (bila belum ada) TRIAL PROTEIN/ASH/MOISTURE.
INSERT INTO N_EMI_LAB_Standar_Rentang
    (Kode_Perusahaan, Id_Jenis_Analisa, Kode_Barang, Id_Master_Mesin, Id_Perhitungan,
     Range_Awal, Range_Akhir, Tanggal, Jam, Id_User, Kode_Role)
SELECT '001', r.Id_Jenis_Analisa, @Kode_Barang, @Id_Mesin, p.id,
       r.Range_Awal, r.Range_Akhir, CAST(@Sekarang AS date), CONVERT(varchar(8), @Sekarang, 108), @Registrasi, 'FLM'
FROM (SELECT i.Id AS Id_Jenis_Analisa, m.Range_Awal, m.Range_Akhir
      FROM @Mikro m JOIN @IdMikro i ON i.Kode = m.Kode WHERE m.Flag_Perhitungan = 'Y'
      UNION ALL SELECT 60, 7, 10
      UNION ALL SELECT 57, 0, 3.5
      UNION ALL SELECT 58, 0, 78) r
CROSS APPLY (SELECT TOP 1 id FROM N_EMI_LAB_Perhitungan
             WHERE Id_Jenis_Analisa = r.Id_Jenis_Analisa AND Kode_Role = 'FLM' ORDER BY id) p
WHERE NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Standar_Rentang s
                  WHERE s.Id_Jenis_Analisa = r.Id_Jenis_Analisa AND s.Kode_Barang = @Kode_Barang
                    AND s.Id_Master_Mesin = @Id_Mesin AND s.Id_Perhitungan = p.id AND s.Kode_Role = 'FLM');

-- Salmonella: nilai kode sama dengan versi LAB (-999999 / -88888888).
INSERT INTO N_EMI_LAB_Standar_Rentang_Non_Perhitungan
    (Kode_Perusahaan, Id_Jenis_Analisa, Nilai_Kriteria, Keterangan_Kriteria, Tanggal, Jam, Id_User, Flag_Layak, Flag_Aktif, Kode_Role)
SELECT '001', @SAL, k.Nilai, k.Label, CAST(@Sekarang AS date), CONVERT(varchar(8), @Sekarang, 108), @Registrasi, k.Layak, 'Y', 'FLM'
FROM (VALUES (-999999.0, 'Negatif', 'Y'), (-88888888.0, 'Positif', 'T')) AS k (Nilai, Label, Layak)
WHERE NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Standar_Rentang_Non_Perhitungan x
                  WHERE x.Id_Jenis_Analisa = @SAL AND x.Nilai_Kriteria = k.Nilai AND x.Kode_Role = 'FLM');

-- Hak konten (Validasi/Finalisasi/Hasil Trial, dst.) = hak TRIAL PROTEIN (id 60).
INSERT INTO N_EMI_LAB_Role_Konten_Access (Id_Page_Access, Id_Jenis_Analisa, Kategori, Flag_Diizinkan)
SELECT rka.Id_Page_Access, i.Id, NULL, 'Y'
FROM N_EMI_LAB_Role_Konten_Access rka
CROSS JOIN @IdMikro i
WHERE rka.Id_Jenis_Analisa = 60 AND rka.Flag_Diizinkan = 'Y'
  AND NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Role_Konten_Access x
                  WHERE x.Id_Page_Access = rka.Id_Page_Access AND x.Id_Jenis_Analisa = i.Id);

-- Barang analisa: 12 analisa sampel dummy, bagi semua akun ber-role FLM.
DECLARE @Analisa TABLE (
    Id_Jenis_Analisa int PRIMARY KEY, Klas varchar(4), Sesi varchar(5), Urut int, Flag_Foto char(1));
INSERT INTO @Analisa VALUES
    (65,   'LCKV', 'LV',  1, 'T'),   -- AROMA (TRIAL KITCHEN R&D)
    (55,   'LCKV', 'LV',  2, 'Y'),   -- WARNA (TRIAL KITCHEN R&D), berfoto
    (75,   'LCKV', 'LV',  3, 'T'),   -- TEKSTUR KALENG (TRIAL KITCHEN R&D)
    (60,   'ANL',  'PRX', 4, 'T'),   -- TRIAL PROTEIN (CRUDE PROTEIN)
    (57,   'ANL',  'PRX', 5, 'T'),   -- TRIAL ASH
    (58,   'ANL',  'PRX', 6, 'T'),   -- TRIAL MOISTURE
    (@AC,  'ANL',  'MKR', 7, 'T'),
    (@YM,  'ANL',  'MKR', 8, 'T'),
    (@EC,  'ANL',  'MKR', 9, 'T'),
    (@SAL, 'ANL',  'MKR', 10, 'T'),
    (62,   'PLT',  'PLT', 11, 'T'),  -- Responden Memakan
    (63,   'PLT',  'PLT', 12, 'T');  -- Konsumsi (gram)

INSERT INTO N_EMI_LAB_Barang_Analisa
    (Kode_Perusahaan, Id_Jenis_Analisa, Kode_Barang, Id_Master_Mesin, Id_User, Tanggal, Jam, Id_User_Menginput, Flag_Aktif, Kode_Role)
SELECT '001', a.Id_Jenis_Analisa, @Kode_Barang, @Id_Mesin, u.Id_User,
       CAST(@Sekarang AS date), CONVERT(varchar(8), @Sekarang, 108), @Registrasi, 'Y', 'FLM'
FROM @Analisa a
CROSS JOIN (SELECT DISTINCT ur.Id_User FROM N_EMI_LAB_User_Roles ur
            JOIN N_EMI_LAB_Roles r ON r.Id_Role = ur.Id_Role WHERE r.Kode_Role = 'FLM') u
WHERE NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Barang_Analisa x
                  WHERE x.Id_Jenis_Analisa = a.Id_Jenis_Analisa AND x.Kode_Barang = @Kode_Barang
                    AND x.Id_Master_Mesin = @Id_Mesin AND x.Id_User = u.Id_User AND x.Kode_Role = 'FLM');

-- ============================================================================
-- 2. BERSIHKAN DATA TRANSAKSI DUMMY SEBELUMNYA (bila skrip dijalankan ulang)
-- ============================================================================
DECLARE @Dummy TABLE (No_Sampel varchar(30) PRIMARY KEY);
INSERT INTO @Dummy VALUES ('FT0926-0001'), ('FT0926-0002'), ('FT0926-0003');

DELETE d FROM N_EMI_LAB_Log_Aksi_Detail d
JOIN N_EMI_LAB_Log_Aksi l ON l.Id_Log_Aksi = d.Id_Log_Aksi
WHERE l.No_Sampel IN (SELECT No_Sampel FROM @Dummy);
DELETE FROM N_EMI_LAB_Log_Aksi                           WHERE No_Sampel    IN (SELECT No_Sampel FROM @Dummy);
DELETE FROM N_EMI_LIMS_Hasil_Uji_Validasi_Final          WHERE No_Sampel    IN (SELECT No_Sampel FROM @Dummy);
DELETE FROM N_EMI_LIMS_Hasil_Uji_Validasi_Detail_Final   WHERE No_Sampel    IN (SELECT No_Sampel FROM @Dummy);
DELETE FROM N_EMI_LIMS_Uji_Pra_Final                     WHERE No_Sampel    IN (SELECT No_Sampel FROM @Dummy);
DELETE FROM N_EMI_LIMS_Uji_Sampel_Keterangan_Status      WHERE No_Sampel    IN (SELECT No_Sampel FROM @Dummy);
DELETE FROM N_EMI_LIMS_Uji_Sampel_Resampling_Log         WHERE No_Po_Sampel IN (SELECT No_Sampel FROM @Dummy);
DELETE FROM N_EMI_LIMS_Berkas_Uji_Lab                    WHERE No_Sampel    IN (SELECT No_Sampel FROM @Dummy);
DELETE FROM N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail WHERE No_Po_Sampel IN (SELECT No_Sampel FROM @Dummy);
DELETE FROM N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail  WHERE No_Po_Sampel IN (SELECT No_Sampel FROM @Dummy);
DELETE FROM N_EMI_LIMS_Activity_Uji_Sampel               WHERE No_Po_Sampel IN (SELECT No_Sampel FROM @Dummy);
DELETE FROM N_EMI_LIMS_Uji_Sampel_Detail
WHERE No_Faktur_Uji_Sample IN (SELECT No_Faktur FROM N_EMI_LIMS_Uji_Sampel WHERE No_Po_Sampel IN (SELECT No_Sampel FROM @Dummy));
DELETE FROM N_EMI_LIMS_Uji_Sampel                        WHERE No_Po_Sampel IN (SELECT No_Sampel FROM @Dummy);
DELETE FROM N_LIMS_PO_Sampel_Multi_QrCode                WHERE No_Po_Sampel IN (SELECT No_Sampel FROM @Dummy);
DELETE FROM N_EMI_LIMS_Activity_Produksi_Sampel
WHERE No_Po = @No_Po AND No_Split_Po = @No_Split_Po AND No_Batch IN (2, 3, 4);
DELETE FROM N_LIMS_PO_Sampel                             WHERE No_Sampel    IN (SELECT No_Sampel FROM @Dummy);

-- ============================================================================
-- 3. JADWAL TIAP SAMPEL (hari kerja; semuanya sebelum 30-09-2026)
-- ============================================================================
DECLARE @Sampel TABLE (
    No_Sampel varchar(30) PRIMARY KEY, No_Batch int,
    Reg datetime,           -- registrasi material + cetak QR
    Sesi_LV datetime,       -- Look View (kitchen)
    Sesi_PRX datetime,      -- proksimat: protein, ash, moisture
    Sesi_MKR datetime,      -- mikrobiologi (setelah inkubasi)
    Sesi_PLT datetime,      -- palatabilitas
    Validasi datetime,      -- Validasi Hasil Trial
    Catatan varchar(300));
INSERT INTO @Sampel VALUES
    ('FT0926-0001', 2, '2026-09-14 08:15:20', '2026-09-15 09:05:00', '2026-09-15 13:40:00',
     '2026-09-17 10:15:00', '2026-09-18 10:20:00', '2026-09-18 15:30:12',
     'DATA DUMMY - Trial autoclave batch 2, formula FRM0426-00005. Retort 121 C / 45 menit.'),
    ('FT0926-0002', 3, '2026-09-21 08:40:05', '2026-09-22 09:10:00', '2026-09-22 14:00:00',
     '2026-09-24 10:30:00', '2026-09-25 10:30:00', '2026-09-25 16:05:41',
     'DATA DUMMY - Trial autoclave batch 3, formula FRM0426-00005. Retort 121 C / 50 menit.'),
    ('FT0926-0003', 4, '2026-09-23 09:02:44', '2026-09-24 08:50:00', '2026-09-24 13:30:00',
     '2026-09-28 10:05:00', '2026-09-29 09:45:00', '2026-09-29 14:20:37',
     'DATA DUMMY - Trial autoclave batch 4, formula FRM0426-00005. Retort 118 C / 55 menit.');

-- ============================================================================
-- 4. HASIL UJI
-- ============================================================================
-- Hasil berkriteria (Look View, Salmonella, Responden Memakan) ditulis sebagai
-- label kriteria; nilainya diambil dari master non-perhitungan FLM.
DECLARE @Label TABLE (No_Sampel varchar(30), Id_Jenis_Analisa int, Label varchar(255));
INSERT INTO @Label VALUES
    ('FT0926-0001', 65, 'Harum Khas Ikan'),
    ('FT0926-0001', 55, 'Coklat Kemerahan'),
    ('FT0926-0001', 75, 'Tidak Muncrat'),
    ('FT0926-0001', @SAL, 'Negatif'),
    ('FT0926-0001', 62, 'Ya'),
    ('FT0926-0002', 65, 'Harum Kuat'),
    ('FT0926-0002', 55, 'Merah Kecoklatan'),
    ('FT0926-0002', 75, 'Sedikit Muncrat (Layak)'),
    ('FT0926-0002', @SAL, 'Negatif'),
    ('FT0926-0002', 62, 'Ya'),
    ('FT0926-0003', 65, 'Harum Khas Ikan'),
    ('FT0926-0003', 55, 'Coklat Sempurna'),
    ('FT0926-0003', 75, 'Lengket di Tutup Kaleng'),   -- temuan: tidak layak
    ('FT0926-0003', @SAL, 'Negatif'),
    ('FT0926-0003', 62, 'Ya');

-- Parameter analisa berhitung dan angka langsung (Konsumsi gram).
--   60 & 58 : [49]+[54]+[62]      57 : [49]      AC/YM/EC : ([103]-[104])/[103]*100
DECLARE @Param TABLE (No_Sampel varchar(30), Id_Jenis_Analisa int, Id_QC int, Nilai float);
INSERT INTO @Param VALUES
    ('FT0926-0001', 60, 49, 2.82), ('FT0926-0001', 60, 54, 2.81), ('FT0926-0001', 60, 62, 2.83),
    ('FT0926-0001', 57, 49, 2.18),
    ('FT0926-0001', 58, 49, 25.47), ('FT0926-0001', 58, 54, 25.49), ('FT0926-0001', 58, 62, 25.46),
    ('FT0926-0001', @AC, 103, 250), ('FT0926-0001', @AC, 104, 240),
    ('FT0926-0001', @YM, 103, 150), ('FT0926-0001', @YM, 104, 147),
    ('FT0926-0001', @EC, 103, 100), ('FT0926-0001', @EC, 104, 100),
    ('FT0926-0001', 63, 124, 82.5),

    ('FT0926-0002', 60, 49, 2.97), ('FT0926-0002', 60, 54, 2.96), ('FT0926-0002', 60, 62, 2.98),
    ('FT0926-0002', 57, 49, 2.34),
    ('FT0926-0002', 58, 49, 25.68), ('FT0926-0002', 58, 54, 25.69), ('FT0926-0002', 58, 62, 25.68),
    ('FT0926-0002', @AC, 103, 200), ('FT0926-0002', @AC, 104, 193),
    ('FT0926-0002', @YM, 103, 180), ('FT0926-0002', @YM, 104, 177.3),
    ('FT0926-0002', @EC, 103, 120), ('FT0926-0002', @EC, 104, 120),
    ('FT0926-0002', 63, 124, 88),

    ('FT0926-0003', 60, 49, 3.04), ('FT0926-0003', 60, 54, 3.05), ('FT0926-0003', 60, 62, 3.03),
    ('FT0926-0003', 57, 49, 2.07),
    ('FT0926-0003', 58, 49, 25.29), ('FT0926-0003', 58, 54, 25.30), ('FT0926-0003', 58, 62, 25.29),
    ('FT0926-0003', @AC, 103, 320), ('FT0926-0003', @AC, 104, 304),
    ('FT0926-0003', @YM, 103, 160), ('FT0926-0003', @YM, 104, 156),
    ('FT0926-0003', @EC, 103, 90),  ('FT0926-0003', @EC, 104, 90),
    ('FT0926-0003', 63, 124, 64.5);

DECLARE @Hasil TABLE (
    No_Sampel varchar(30), Id_Jenis_Analisa int, Klas varchar(4), Urut int,
    Hasil float, Flag_Perhitungan char(1), Id_Perhitungan int,
    Range_Awal float, Range_Akhir float, Flag_Layak char(1), Flag_Foto char(1),
    Id_User varchar(30), Waktu datetime, No_Faktur varchar(30));

INSERT INTO @Hasil (No_Sampel, Id_Jenis_Analisa, Klas, Urut, Flag_Perhitungan, Id_Perhitungan, Flag_Foto, Id_User, Waktu)
SELECT s.No_Sampel, a.Id_Jenis_Analisa, a.Klas, a.Urut,
       j.Flag_Perhitungan,
       CASE WHEN j.Flag_Perhitungan = 'Y'
            THEN (SELECT TOP 1 p.id FROM N_EMI_LAB_Perhitungan p
                  WHERE p.Id_Jenis_Analisa = a.Id_Jenis_Analisa AND p.Kode_Role = 'FLM' ORDER BY p.id) END,
       a.Flag_Foto,
       CASE WHEN a.Klas = 'ANL' THEN @Uji_Lab ELSE @Uji_Kitchen END,
       -- tiap analisa dalam satu sesi dikirim berselang 4 menit
       DATEADD(MINUTE, 4 * (a.Urut - MIN(a.Urut) OVER (PARTITION BY s.No_Sampel, a.Sesi)),
               CASE a.Sesi WHEN 'LV' THEN s.Sesi_LV WHEN 'PRX' THEN s.Sesi_PRX
                           WHEN 'MKR' THEN s.Sesi_MKR ELSE s.Sesi_PLT END)
FROM @Sampel s
CROSS JOIN @Analisa a
JOIN N_EMI_LAB_Jenis_Analisa j ON j.id = a.Id_Jenis_Analisa;

-- 4a. Hasil berkriteria -> nilai kriteria, kelayakan, dan batas (nilai kriteria
--     layak pertama, sama seperti yang disimpan layar input formulator).
UPDATE h SET
    Hasil       = k.Nilai_Kriteria,
    Flag_Layak  = k.Flag_Layak,
    Range_Awal  = st.Nilai_Kriteria,
    Range_Akhir = st.Nilai_Kriteria
FROM @Hasil h
JOIN @Label l ON l.No_Sampel = h.No_Sampel AND l.Id_Jenis_Analisa = h.Id_Jenis_Analisa
CROSS APPLY (SELECT TOP 1 Nilai_Kriteria, Flag_Layak FROM N_EMI_LAB_Standar_Rentang_Non_Perhitungan
             WHERE Id_Jenis_Analisa = h.Id_Jenis_Analisa AND Keterangan_Kriteria = l.Label
               AND Flag_Aktif = 'Y' AND Kode_Role = 'FLM'
             ORDER BY Id_Standar_Rentang_Non_Perhitungan) k
OUTER APPLY (SELECT TOP 1 Nilai_Kriteria FROM N_EMI_LAB_Standar_Rentang_Non_Perhitungan
             WHERE Id_Jenis_Analisa = h.Id_Jenis_Analisa AND Flag_Layak = 'Y'
               AND Flag_Aktif = 'Y' AND Kode_Role = 'FLM'
             ORDER BY Id_Standar_Rentang_Non_Perhitungan) st;

-- 4b. Analisa berhitung -> hasil rumus (2 desimal) dan batas standar rentang.
UPDATE h SET
    Hasil = ROUND(x.Nilai, 2),
    Range_Awal = sr.Range_Awal,
    Range_Akhir = sr.Range_Akhir
FROM @Hasil h
CROSS APPLY (SELECT CASE WHEN h.Id_Jenis_Analisa IN (@AC, @YM, @EC)
                         THEN (MAX(CASE WHEN p.Id_QC = 103 THEN p.Nilai END) - MAX(CASE WHEN p.Id_QC = 104 THEN p.Nilai END))
                              / NULLIF(MAX(CASE WHEN p.Id_QC = 103 THEN p.Nilai END), 0) * 100
                         ELSE SUM(p.Nilai) END AS Nilai
             FROM @Param p WHERE p.No_Sampel = h.No_Sampel AND p.Id_Jenis_Analisa = h.Id_Jenis_Analisa) x
OUTER APPLY (SELECT TOP 1 Range_Awal, Range_Akhir FROM N_EMI_LAB_Standar_Rentang
             WHERE Kode_Barang = @Kode_Barang AND Id_Jenis_Analisa = h.Id_Jenis_Analisa
               AND Id_Master_Mesin = @Id_Mesin AND Id_Perhitungan = h.Id_Perhitungan AND Kode_Role = 'FLM'
             ORDER BY Id_Standar_Rentang) sr
WHERE h.Flag_Perhitungan = 'Y';

UPDATE @Hasil SET Flag_Layak =
    CASE WHEN (Range_Awal IS NULL OR Hasil >= Range_Awal) AND (Range_Akhir IS NULL OR Hasil <= Range_Akhir)
         THEN 'Y' ELSE 'T' END
WHERE Flag_Perhitungan = 'Y';

-- 4c. Angka langsung tanpa kriteria (Konsumsi gram) -> layak, tanpa batas.
UPDATE h SET Hasil = p.Nilai, Flag_Layak = 'Y'
FROM @Hasil h
JOIN @Param p ON p.No_Sampel = h.No_Sampel AND p.Id_Jenis_Analisa = h.Id_Jenis_Analisa
WHERE h.Flag_Perhitungan IS NULL AND h.Hasil IS NULL;

IF EXISTS (SELECT 1 FROM @Hasil WHERE Hasil IS NULL OR Flag_Layak IS NULL)
    THROW 50007, N'Ada hasil uji yang tidak dapat disusun (label kriteria / parameter tidak ditemukan di master).', 1;

-- Parameter berkriteria = nilai kriteria pada QC yang terikat ke analisanya.
INSERT INTO @Param (No_Sampel, Id_Jenis_Analisa, Id_QC, Nilai)
SELECT h.No_Sampel, h.Id_Jenis_Analisa, b.Id_Quality_Control, h.Hasil
FROM @Hasil h
JOIN @Label l ON l.No_Sampel = h.No_Sampel AND l.Id_Jenis_Analisa = h.Id_Jenis_Analisa
CROSS APPLY (SELECT TOP 1 Id_Quality_Control FROM N_EMI_LAB_Binding_Jenis_Analisa
             WHERE Id_Jenis_Analisa = h.Id_Jenis_Analisa ORDER BY id) b;

-- Nomor faktur FUS{mm}{yy}-nnnn, melanjutkan nomor terakhir bulan itu.
DECLARE @Faktur_Terakhir int = ISNULL((
    SELECT MAX(TRY_CAST(RIGHT(No_Faktur, 4) AS int)) FROM N_EMI_LIMS_Uji_Sampel
    WHERE No_Faktur LIKE 'FUS0926-[0-9][0-9][0-9][0-9]'), 0);

WITH urut AS (
    SELECT No_Faktur, ROW_NUMBER() OVER (ORDER BY Waktu, No_Sampel, Urut) AS n FROM @Hasil)
UPDATE urut SET No_Faktur = 'FUS0926-' + RIGHT('0000' + CAST(@Faktur_Terakhir + n AS varchar(10)), 4);

-- ============================================================================
-- 5. REGISTRASI MATERIAL (N_LIMS_PO_Sampel, sub-sampel QR, aktivitas produksi)
-- ============================================================================
INSERT INTO N_LIMS_PO_Sampel
    (Kode_Perusahaan, No_Po, No_Split_Po, No_Batch, Kode_Barang, No_Sampel, Status, Tanggal, Jam,
     Id_Mesin, Keterangan, Id_User, Flag_Selesai, Jumlah_Pcs)
SELECT '001', @No_Po, @No_Split_Po, s.No_Batch, @Kode_Barang, s.No_Sampel, NULL,
       CAST(CAST(s.Reg AS date) AS datetime), CONVERT(varchar(8), s.Reg, 108),
       @Id_Mesin, s.Catatan, @Registrasi, NULL, @Jumlah_Pcs
FROM @Sampel s ORDER BY s.No_Sampel;

INSERT INTO N_LIMS_PO_Sampel_Multi_QrCode
    (Kode_Perusahaan, No_Po_Multi, Kode_Barang, No_Po_Sampel, Status, Tanggal, Jam, Flag_Selesai)
SELECT '001', s.No_Sampel + '-' + CAST(n.i AS varchar(3)), @Kode_Barang, s.No_Sampel, NULL,
       CAST(CAST(s.Reg AS date) AS datetime), CONVERT(varchar(8), s.Reg, 108), NULL
FROM @Sampel s
JOIN (SELECT TOP (@Jumlah_Pcs) ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) AS i FROM sys.objects) n ON 1 = 1
ORDER BY s.No_Sampel, n.i;

INSERT INTO N_EMI_LIMS_Activity_Produksi_Sampel
    (No_Po, No_Split_Po, No_Batch, Jenis_Aktivitas, Status_Aktivitas, Keterangan, Tanggal, Id_Mesin, Jam,
     Flag_Berhasil_Cetak_QrCode, Id_User)
SELECT @No_Po, @No_Split_Po, s.No_Batch, a.Jenis, a.Status,
       'Untuk Nomor Po ' + @No_Po + a.Kalimat,
       CAST(CAST(s.Reg AS date) AS datetime), @Id_Mesin, CONVERT(varchar(8), s.Reg, 108), a.Cetak, @Registrasi
FROM @Sampel s
CROSS JOIN (VALUES
    (1, 'Registrasi Material',   'Berhasil', ' Berhasil Melakukan Registrasi Material Dan Menunggu Proses Cetak', 'Y'),
    (2, 'Cetak QrCode Material', 'berhasil', ' Berhasil Melakukan Cetak QrCode', NULL)
) AS a (Urut, Jenis, Status, Kalimat, Cetak)
ORDER BY s.No_Sampel, a.Urut;

-- ============================================================================
-- 6. HASIL UJI (N_EMI_LIMS_Uji_Sampel + detail parameter + jejak aktivitas)
-- ============================================================================
INSERT INTO N_EMI_LIMS_Uji_Sampel
    (Kode_Perusahaan, No_Faktur, No_Po_Sampel, No_Fak_Sub_Po, Id_Jenis_Analisa, Hasil, Flag_Perhitungan,
     Flag_Multi_QrCode, Status, Tanggal, Jam, Id_User, Flag_Selesai, Id_Perhitungan, Range_Awal, Range_Akhir,
     Tahapan_Ke, Flag_Resampling, Status_Keputusan_Sampel, Flag_Layak, Flag_Final, Id_Mesin,
     Flag_String, Nilai_Hasil_String, Flag_Foto, Flag_Approval)
SELECT '001', h.No_Faktur, h.No_Sampel, h.No_Sampel + '-1', h.Id_Jenis_Analisa, h.Hasil, h.Flag_Perhitungan,
       'Y', NULL, CAST(CAST(h.Waktu AS date) AS datetime), CONVERT(varchar(8), h.Waktu, 108), h.Id_User,
       'Y', h.Id_Perhitungan, h.Range_Awal, h.Range_Akhir,
       1, NULL, 'terima', h.Flag_Layak, NULL, @Id_Mesin,
       NULL, NULL, h.Flag_Foto, NULL
FROM @Hasil h ORDER BY h.No_Faktur;

INSERT INTO N_EMI_LIMS_Uji_Sampel_Detail
    (Kode_Perusahaan, No_Faktur_Uji_Sample, Id_Quality_Control, Value_Parameter, Tanggal, Jam, Id_User)
SELECT '001', h.No_Faktur, p.Id_QC, p.Nilai,
       CAST(CAST(h.Waktu AS date) AS datetime), CONVERT(varchar(8), h.Waktu, 108), h.Id_User
FROM @Hasil h
JOIN @Param p ON p.No_Sampel = h.No_Sampel AND p.Id_Jenis_Analisa = h.Id_Jenis_Analisa
ORDER BY h.No_Faktur, p.Id_QC;

INSERT INTO N_EMI_LIMS_Activity_Uji_Sampel
    (Kode_Perusahaan, No_Po_Sampel, No_Fak_Sub_Po, Jenis_Aktivitas, Id_Jenis_Analisa, Keterangan, Id_User, Tanggal, Jam)
SELECT '001', h.No_Sampel, h.No_Sampel + '-1', 'save_submit', h.Id_Jenis_Analisa,
       ISNULL(u.Nama, h.Id_User) + ' Berhasil Mengirimkan Data Analisa', h.Id_User,
       CAST(CAST(h.Waktu AS date) AS datetime), CONVERT(varchar(8), h.Waktu, 108)
FROM @Hasil h
LEFT JOIN N_EMI_LAB_Users u ON u.UserId = h.Id_User
ORDER BY h.No_Faktur;

INSERT INTO N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail
    (Id_Log_Activity_Sampel, Kode_Perusahaan, No_Po_Sampel, No_Fak_Sub_Po, Id_Jenis_Analisa, Value_Baru, Value_Lama,
     Tanggal, Jam, Id_User, Status_Submit, Id_Perhitungan)
SELECT act.Id_Log_Activity, '001', h.No_Sampel, h.No_Sampel + '-1', h.Id_Jenis_Analisa, h.Hasil, h.Hasil,
       CAST(CAST(h.Waktu AS date) AS datetime), CONVERT(varchar(8), h.Waktu, 108), h.Id_User, 'Submited', h.Id_Perhitungan
FROM @Hasil h
JOIN N_EMI_LIMS_Activity_Uji_Sampel act
  ON act.No_Po_Sampel = h.No_Sampel AND act.Id_Jenis_Analisa = h.Id_Jenis_Analisa AND act.Jenis_Aktivitas = 'save_submit'
ORDER BY h.No_Faktur;

INSERT INTO N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail
    (Id_Log_Activity_Sampel, Kode_Perusahaan, No_Po_Sampel, No_Fak_Sub_Po, Id_Jenis_Analisa, Id_Quality_Control,
     Value_Baru, Value_Lama, Tanggal, Jam, Id_User, Status_Submit, Alasan_Mengubah_Data)
SELECT act.Id_Log_Activity, '001', h.No_Sampel, h.No_Sampel + '-1', h.Id_Jenis_Analisa, p.Id_QC,
       p.Nilai, p.Nilai, CAST(CAST(h.Waktu AS date) AS datetime), CONVERT(varchar(8), h.Waktu, 108), h.Id_User,
       'Submited', NULL
FROM @Hasil h
JOIN @Param p ON p.No_Sampel = h.No_Sampel AND p.Id_Jenis_Analisa = h.Id_Jenis_Analisa
JOIN N_EMI_LIMS_Activity_Uji_Sampel act
  ON act.No_Po_Sampel = h.No_Sampel AND act.Id_Jenis_Analisa = h.Id_Jenis_Analisa AND act.Jenis_Aktivitas = 'save_submit'
ORDER BY h.No_Faktur, p.Id_QC;

-- ============================================================================
-- 7. FOTO LOOK VIEW WARNA (memakai ulang foto produk dari berkas lab)
-- ============================================================================
DECLARE @CRLF char(2) = CHAR(13) + CHAR(10);
DECLARE @Foto TABLE (No_Sampel varchar(30), Urut int, File_Path varchar(255), Keterangan varchar(1000));
INSERT INTO @Foto VALUES
    ('FT0926-0001', 1, 'berkas/lab/labwHO7X_1780477883_1.png',
     '- Warna: coklat kemerahan, merata di seluruh potongan' + @CRLF + '- Permukaan: mengkilap, tidak ada browning'
     + @CRLF + '- Kaleng: tidak kembung, seaming rapi'),
    ('FT0926-0001', 2, 'berkas/lab/labaZISF_1780392614_1.png',
     'Perbandingan dengan produk pembanding: warna setara, potongan daging utuh'),
    ('FT0926-0002', 1, 'berkas/lab/labRxKrI_1780477883_0.png',
     '- Warna: merah kecoklatan' + @CRLF + '- Gelling: padat, tidak berair' + @CRLF + '- Browning: tidak ada'),
    ('FT0926-0002', 2, 'berkas/lab/labwHO7X_1780477883_1.png',
     'Tampilan setelah dibuka dari kaleng, suhu ruang'),
    ('FT0926-0003', 1, 'berkas/lab/labwHO7X_1780477883_1.png',
     '- Warna: coklat sempurna' + @CRLF + '- Tekstur: lengket di tutup kaleng saat dibuka'
     + @CRLF + '- Aroma: harum khas ikan'),
    ('FT0926-0003', 2, 'berkas/lab/labaZISF_1780392614_1.png',
     'Perbandingan dengan produk pembanding: warna sedikit lebih terang');

INSERT INTO N_EMI_LIMS_Berkas_Uji_Lab (No_Faktur, No_Sampel, Berkas_Key, File_Path, Id_Jenis_Analisa, Keterangan)
SELECT h.No_Faktur, f.No_Sampel, LOWER(LEFT(REPLACE(CONVERT(varchar(36), NEWID()), '-', ''), 32)),
       f.File_Path, NULL, f.Keterangan
FROM @Foto f
JOIN @Hasil h ON h.No_Sampel = f.No_Sampel AND h.Flag_Foto = 'Y'
ORDER BY f.No_Sampel, f.Urut;

-- ============================================================================
-- 8. VALIDASI HASIL TRIAL (sudah divalidasi LIMS, belum pra-finalisasi)
-- ============================================================================
INSERT INTO N_EMI_LIMS_Hasil_Uji_Validasi_Detail_Final
    (No_Sampel, Id_Jenis_Analisa, Tahapan_Ke, Tanggal, Jam, Flag_Layak, Flag_Resampling, Id_User, No_Sub_Sampel)
SELECT h.No_Sampel, h.Id_Jenis_Analisa, 1, CAST(CAST(s.Validasi AS date) AS datetime), CONVERT(varchar(8), s.Validasi, 108),
       h.Flag_Layak, NULL, @Validator, h.No_Sampel + '-1'
FROM @Hasil h JOIN @Sampel s ON s.No_Sampel = h.No_Sampel
ORDER BY h.No_Sampel, h.Urut;

INSERT INTO N_EMI_LAB_Log_Aksi
    (No_Sampel, No_Po, No_Split_Po, Kode_Barang, Flag_Trial, Jenis_Aksi, Sub_Aksi, Keterangan, Id_User, Tanggal, Jam)
SELECT s.No_Sampel, @No_Po, @No_Split_Po, @Kode_Barang, NULL, 'VALIDASI_FORMULATOR', 'SETUJU', NULL, @Validator,
       CAST(s.Validasi AS date), CAST(s.Validasi AS time(0))
FROM @Sampel s ORDER BY s.Validasi;

INSERT INTO N_EMI_LAB_Log_Aksi_Detail
    (Id_Log_Aksi, Id_Jenis_Analisa, Nama_Jenis_Analisa, Flag_Layak, Tanggal, Jam, Id_User)
SELECT l.Id_Log_Aksi, h.Id_Jenis_Analisa, j.Jenis_Analisa, h.Flag_Layak,
       CONVERT(varchar(10), s.Validasi, 23), CONVERT(varchar(8), s.Validasi, 108), @Validator
FROM @Hasil h
JOIN @Sampel s ON s.No_Sampel = h.No_Sampel
JOIN N_EMI_LAB_Jenis_Analisa j ON j.id = h.Id_Jenis_Analisa
JOIN N_EMI_LAB_Log_Aksi l ON l.No_Sampel = h.No_Sampel AND l.Jenis_Aksi = 'VALIDASI_FORMULATOR' AND l.Sub_Aksi = 'SETUJU'
ORDER BY h.No_Sampel, h.Urut;

COMMIT TRANSACTION;

-- ============================================================================
-- RINGKASAN
-- ============================================================================
SELECT U.No_Po_Sampel, K.Nama_Aktivitas AS Klasifikasi, A.Jenis_Analisa, U.No_Faktur, U.Hasil,
       SR.Keterangan_Kriteria AS Label, U.Range_Awal, U.Range_Akhir, U.Flag_Layak, U.Flag_Foto,
       (SELECT COUNT(*) FROM N_EMI_LIMS_Berkas_Uji_Lab B WHERE B.No_Faktur = U.No_Faktur) AS Foto
FROM N_EMI_LIMS_Uji_Sampel U
JOIN N_EMI_LAB_Jenis_Analisa A ON A.id = U.Id_Jenis_Analisa
JOIN N_EMI_LIMS_Klasifikasi_Aktivitas_Lab K ON K.Kode_Aktivitas_Lab = A.Kode_Aktivitas_Lab
LEFT JOIN N_EMI_LAB_Standar_Rentang_Non_Perhitungan SR
       ON SR.Id_Jenis_Analisa = U.Id_Jenis_Analisa AND SR.Nilai_Kriteria = U.Hasil
      AND SR.Kode_Role = 'FLM' AND SR.Flag_Aktif = 'Y'
WHERE U.No_Po_Sampel IN ('FT0926-0001', 'FT0926-0002', 'FT0926-0003')
ORDER BY U.No_Po_Sampel, K.Urutan, U.No_Faktur;
