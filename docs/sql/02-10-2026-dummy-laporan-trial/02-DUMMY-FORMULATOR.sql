-- ============================================================================
-- 02-DUMMY-FORMULATOR.sql
-- Tanggal : 02-10-2026
-- Modul   : FORMULATOR / TRIAL KITCHEN - 1 sampel lengkap s.d. FINALISASI,
--           untuk menguji laporan "siapa memvalidasi / siapa memfinalisasi"
-- Database: HANYA emi_tm_demo. Skrip menolak berjalan di database lain.
--
-- HASIL
--   PO trial PRT0926-00001, split PRT0926-00001-1, batch 1
--   BRG08240005 (LIFE CAT 85GR CHICKEN TUNA ADULT, pouch), mesin AUTOCLAVE, 1 pcs.
--   Split ini sebelumnya kosong, jadi laporan per split hanya berisi sampel ini.
--
--   Aktivitas      Penginput      Validator (Validasi Hasil Trial)   Analisa
--   Look View      GUDANG PEKAN   DIST RIAU    WARNA + 2 foto, AROMA, TEKSTUR POUCH (Trial Kitchen R&D)
--   Analisa Lab    FRANS          SV_LAB       TRIAL PROTEIN (CRUDE PROTEIN), TRIAL ASH, TRIAL MOISTURE,
--                                              TRIAL SALT, TRIAL MIKROBIOLOGI-AC / -YM / -EC / -SALMONELLA
--   Palatabilitas  DIAH           ROBY         Responden Memakan, Konsumsi (gram), Durasi Makan (detik)
--   Pra-finalisasi (setujui tiap tahap + finalisasi pra-final) dan
--   Finalisasi Trial: VENGINE. Registrasi material: VENGINE.
--
--   Jadwal: registrasi & input 28-30 Sep 2026, validasi 28 Sep - 1 Okt,
--   pra-finalisasi & finalisasi 2 Okt 2026 pagi. Input jatuh di September
--   supaya nomor sampel (FT0926-...) dan faktur (FUS0926-...) tidak bentrok
--   dengan nomor Oktober yang sedang dipakai di demo.
--
-- BENTUK DATA = bentuk yang ditulis aplikasi sekarang (dicocokkan dengan
--   sampel produksi FT0726-0029/0003, read-only):
--   * Validasi Hasil Trial -> N_EMI_LIMS_Hasil_Uji_Validasi_Detail_Final
--     (Id_User = validator) + satu header Log_Aksi VALIDASI_FORMULATOR
--     dengan validator di tiap DETAIL (Flag_Layak terisi).
--   * Pra-finalisasi -> Flag_Approval, Keterangan_Status, header Log_Aksi
--     VALIDASI_FORMULATOR per tahap (detail Flag_Layak NULL),
--     N_EMI_LIMS_Uji_Pra_Final, Log_Aksi PRAFINALISASI_FORMULATOR.
--   * Finalisasi Trial -> Flag_Final, header N_EMI_LIMS_Hasil_Uji_Validasi_Final,
--     PO Flag_Selesai, Log_Aksi FINALISASI_FORMULATOR / CLOSE.
--   * Seperti aplikasi sekarang, pra-finalisasi MENIMPA N_EMI_LIMS_Uji_Sampel.Id_User
--     dengan akun penyetuju (VENGINE). Penginput asli tetap tercatat di
--     N_EMI_LIMS_Activity_Uji_Sampel (save_submit) dan detail aktivitasnya.
--   Hak akses akun TIDAK diubah (bypass): data ditulis langsung.
--
-- MASTER YANG DITAMBAHKAN (hanya bila belum ada)
--   * Analisa FLM: TRIAL SALT (TRSLT, rumus sama dengan SALT ANALYSIS LAB) dan
--     Durasi Makan (detik) (UPDUR, palatabilitas, parameter QC 116 "Waktu (detik)").
--   * Bila belum ada: mikro FLM TRMAC/TRMYM/TRMEC/TRMSL (lihat
--     30-09-2026-dummy-formulator/01-DUMMY-FORMULATOR.sql).
--   * Standar rentang FLM BRG08240005 x AUTOCLAVE: SALT 0,3-1,2; AC 0-500;
--     YM 0-100; EC 0-10 (TRIAL PROTEIN/ASH/MOISTURE sudah ada).
--   * Hak konten disalin dari TRIAL PROTEIN (60) untuk SALT dan dari
--     Konsumsi (gram) (63) untuk Durasi; Barang Analisa FLM BRG08240005 x
--     AUTOCLAVE untuk 14 analisa sampel ini, bagi akun ber-role FLM.
--
-- ULANG / HAPUS
--   Aman dijalankan ulang: sampel dummy di split ini dihapus lalu dibuat
--   kembali dengan nomor yang sama; master tidak digandakan.
--   Hapus: 03-HAPUS-DUMMY-LAPORAN-TRIAL.sql. Bila split sudah dipakai sampel
--   ASLI, skrip berhenti tanpa mengubah apa pun.
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;

IF DB_NAME() <> N'emi_tm_demo'
    THROW 50001, N'Skrip dummy hanya untuk database emi_tm_demo. Dibatalkan.', 1;

DECLARE
    @Penanda      varchar(40) = 'DATA DUMMY LAPORAN',
    @No_Po        varchar(30) = 'PRT0926-00001',
    @No_Split_Po  varchar(30) = 'PRT0926-00001-1',
    @No_Batch     int         = 1,
    @Kode_Barang  varchar(30) = 'BRG08240005',
    @Id_Mesin     int         = 4,          -- AUTOCLAVE
    @Registrasi   varchar(30) = 'VENGINE',
    @Input_LV     varchar(30) = 'GUDANG PEKAN',
    @Validasi_LV  varchar(30) = 'DIST RIAU',
    @Input_ANL    varchar(30) = 'FRANS',
    @Validasi_ANL varchar(30) = 'SV_LAB',
    @Input_PLT    varchar(30) = 'DIAH',
    @Validasi_PLT varchar(30) = 'ROBY',
    @Finalisasi   varchar(30) = 'VENGINE',
    @Waktu_Reg    datetime    = '2026-09-28 07:55:40',
    @Waktu_PraLV  datetime    = '2026-10-02 07:40:05',
    @Waktu_PraANL datetime    = '2026-10-02 07:41:30',
    @Waktu_PraPLT datetime    = '2026-10-02 07:42:50',
    @Waktu_Pra    datetime    = '2026-10-02 07:44:10',
    @Waktu_Final  datetime    = '2026-10-02 08:30:12',
    @Sekarang     datetime    = dbo.Get_Date_Time();

IF NOT EXISTS (SELECT 1 FROM EMI_Master_Mesin WHERE Id_Master_Mesin = @Id_Mesin AND Nama_Mesin = 'AUTOCLAVE')
    THROW 50002, N'Mesin AUTOCLAVE (Id_Master_Mesin 4) tidak ditemukan.', 1;

IF NOT EXISTS (SELECT 1 FROM N_EMI_View_Trial_Order_Produksi WHERE No_Faktur = @No_Po AND Kode_Barang = @Kode_Barang)
    THROW 50003, N'PO trial PRT0926-00001 / BRG08240005 tidak ditemukan.', 1;

IF EXISTS (SELECT 1 FROM N_LIMS_PO_Sampel
           WHERE No_Split_Po = @No_Split_Po
             AND ISNULL(CAST(Keterangan AS varchar(max)), '') NOT LIKE @Penanda + '%')
    THROW 50004, N'Split PRT0926-00001-1 sudah dipakai sampel asli. Dibatalkan.', 1;

IF (SELECT COUNT(*) FROM N_EMI_LAB_Users
    WHERE UserId IN (@Registrasi, @Input_LV, @Validasi_LV, @Input_ANL, @Validasi_ANL, @Input_PLT, @Validasi_PLT, @Finalisasi)) < 7
    THROW 50005, N'Ada akun yang belum terdaftar di N_EMI_LAB_Users.', 1;

IF NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Perhitungan WHERE Id_Jenis_Analisa = 60 AND Kode_Role = 'FLM' AND Rumus = '[49]+[54]+[62]')
   OR NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Perhitungan WHERE Id_Jenis_Analisa = 58 AND Kode_Role = 'FLM' AND Rumus = '[49]+[54]+[62]')
   OR NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Perhitungan WHERE Id_Jenis_Analisa = 57 AND Kode_Role = 'FLM' AND Rumus = '[49]')
    THROW 50006, N'Rumus TRIAL PROTEIN/MOISTURE/ASH di master berbeda dari yang diharapkan skrip.', 1;

BEGIN TRANSACTION;

-- ============================================================================
-- 1. MASTER FLM (hanya bila belum ada)
-- ============================================================================
DECLARE @Master TABLE (
    Kode varchar(50) PRIMARY KEY, Nama varchar(100), Klas varchar(4), Flag_Perhitungan char(1),
    Rumus varchar(100), Nama_Kolom varchar(20), Desimal int, Range_Awal float, Range_Akhir float, Salin_Konten_Dari int);
INSERT INTO @Master VALUES
    ('TRMAC', 'TRIAL MIKROBIOLOGI-AC',         'ANL', 'Y',  '([103]-[104])/[103]*100', 'AC',     2, 0,   500, 60),
    ('TRMYM', 'TRIAL MIKROBIOLOGI-YM',         'ANL', 'Y',  '([103]-[104])/[103]*100', 'YM',     2, 0,   100, 60),
    ('TRMEC', 'TRIAL MIKROBIOLOGI-EC',         'ANL', 'Y',  '([103]-[104])/[103]*100', 'EC',     2, 0,   10,  60),
    ('TRMSL', 'TRIAL MIKROBIOLOGI-SALMONELLA', 'ANL', NULL, NULL, NULL, NULL, NULL, NULL, 60),
    ('TRSLT', 'TRIAL SALT',                    'ANL', 'Y',  '(5-[79])*5.845*0.1/[71]', '% SALT', 2, 0.3, 1.2, 60),
    ('UPDUR', 'Durasi Makan (detik)',          'PLT', NULL, NULL, NULL, NULL, NULL, NULL, 63);

INSERT INTO N_EMI_LAB_Jenis_Analisa
    (Kode_Analisa, Jenis_Analisa, Id_Mesin, Flag_Perhitungan, Created_At, Updated_At, Sifat_Kegiatan, Kode_Role, Id_User, Kode_Aktivitas_Lab, Flag_Foto)
SELECT m.Kode, m.Nama, NULL, m.Flag_Perhitungan, @Sekarang, @Sekarang, 'Rutin', 'FLM', 'FRANS', m.Klas, 'T'
FROM @Master m
WHERE NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Jenis_Analisa j WHERE j.Kode_Analisa = m.Kode AND j.Kode_Role = 'FLM');

DECLARE @IdMaster TABLE (Kode varchar(50) PRIMARY KEY, Id int);
INSERT INTO @IdMaster
SELECT m.Kode, MIN(j.id) FROM @Master m
JOIN N_EMI_LAB_Jenis_Analisa j ON j.Kode_Analisa = m.Kode AND j.Kode_Role = 'FLM'
GROUP BY m.Kode;

DECLARE @AC int = (SELECT Id FROM @IdMaster WHERE Kode = 'TRMAC'), @YM int = (SELECT Id FROM @IdMaster WHERE Kode = 'TRMYM'),
        @EC int = (SELECT Id FROM @IdMaster WHERE Kode = 'TRMEC'), @SAL int = (SELECT Id FROM @IdMaster WHERE Kode = 'TRMSL'),
        @SLT int = (SELECT Id FROM @IdMaster WHERE Kode = 'TRSLT'), @DUR int = (SELECT Id FROM @IdMaster WHERE Kode = 'UPDUR');

INSERT INTO N_EMI_LAB_Perhitungan (Id_Jenis_Analisa, Rumus, Nama_Kolom, Created_At, Updated_At, Hasil_Perhitungan, Kode_Perusahaan, Kode_Role, Id_User)
SELECT i.Id, m.Rumus, m.Nama_Kolom, @Sekarang, @Sekarang, m.Desimal, '001', 'FLM', 'FRANS'
FROM @Master m JOIN @IdMaster i ON i.Kode = m.Kode
WHERE m.Rumus IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Perhitungan p WHERE p.Id_Jenis_Analisa = i.Id AND p.Kode_Role = 'FLM');

INSERT INTO N_EMI_LAB_Binding_Jenis_Analisa (Id_Jenis_Analisa, Id_Quality_Control, Keterangan, Created_At, Updated_At, Id_User, Kode_Role)
SELECT b.Id_Jenis_Analisa, b.Id_QC, NULL, @Sekarang, @Sekarang, NULL, 'FLM'
FROM (VALUES (@AC, 103), (@AC, 104), (@YM, 103), (@YM, 104), (@EC, 103), (@EC, 104), (@SAL, 105),
             (@SLT, 79), (@SLT, 71), (@DUR, 116)) AS b (Id_Jenis_Analisa, Id_QC)
WHERE NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Binding_Jenis_Analisa x WHERE x.Id_Jenis_Analisa = b.Id_Jenis_Analisa AND x.Id_Quality_Control = b.Id_QC);

INSERT INTO N_EMI_LAB_Standar_Rentang
    (Kode_Perusahaan, Id_Jenis_Analisa, Kode_Barang, Id_Master_Mesin, Id_Perhitungan, Range_Awal, Range_Akhir, Tanggal, Jam, Id_User, Kode_Role)
SELECT '001', i.Id, @Kode_Barang, @Id_Mesin, p.id, m.Range_Awal, m.Range_Akhir,
       CAST(@Sekarang AS date), CONVERT(varchar(8), @Sekarang, 108), 'FRANS', 'FLM'
FROM @Master m JOIN @IdMaster i ON i.Kode = m.Kode
CROSS APPLY (SELECT TOP 1 id FROM N_EMI_LAB_Perhitungan WHERE Id_Jenis_Analisa = i.Id AND Kode_Role = 'FLM' ORDER BY id) p
WHERE m.Rumus IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Standar_Rentang s WHERE s.Id_Jenis_Analisa = i.Id AND s.Kode_Barang = @Kode_Barang
                    AND s.Id_Master_Mesin = @Id_Mesin AND s.Id_Perhitungan = p.id AND s.Kode_Role = 'FLM');

INSERT INTO N_EMI_LAB_Standar_Rentang_Non_Perhitungan
    (Kode_Perusahaan, Id_Jenis_Analisa, Nilai_Kriteria, Keterangan_Kriteria, Tanggal, Jam, Id_User, Flag_Layak, Flag_Aktif, Kode_Role)
SELECT '001', @SAL, k.Nilai, k.Label, CAST(@Sekarang AS date), CONVERT(varchar(8), @Sekarang, 108), 'FRANS', k.Layak, 'Y', 'FLM'
FROM (VALUES (-999999.0, 'Negatif', 'Y'), (-88888888.0, 'Positif', 'T')) AS k (Nilai, Label, Layak)
WHERE NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Standar_Rentang_Non_Perhitungan x WHERE x.Id_Jenis_Analisa = @SAL AND x.Nilai_Kriteria = k.Nilai AND x.Kode_Role = 'FLM');

INSERT INTO N_EMI_LAB_Role_Konten_Access (Id_Page_Access, Id_Jenis_Analisa, Kategori, Flag_Diizinkan)
SELECT rka.Id_Page_Access, i.Id, NULL, 'Y'
FROM @Master m JOIN @IdMaster i ON i.Kode = m.Kode
JOIN N_EMI_LAB_Role_Konten_Access rka ON rka.Id_Jenis_Analisa = m.Salin_Konten_Dari AND rka.Flag_Diizinkan = 'Y'
WHERE NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Role_Konten_Access x WHERE x.Id_Page_Access = rka.Id_Page_Access AND x.Id_Jenis_Analisa = i.Id);

-- ============================================================================
-- 2. RANCANGAN HASIL (14 analisa)
-- ============================================================================
DECLARE @Analisa TABLE (
    Urut int PRIMARY KEY, Id_Jenis_Analisa int, Klas varchar(4), Waktu_Input datetime, Waktu_Validasi datetime,
    Label varchar(255), Nilai_Langsung float, Flag_Foto char(1),
    Hasil float, Flag_Perhitungan char(1), Id_Perhitungan int, Range_Awal float, Range_Akhir float, Flag_Layak char(1), No_Faktur varchar(30));
INSERT INTO @Analisa (Urut, Id_Jenis_Analisa, Klas, Waktu_Input, Waktu_Validasi, Label, Nilai_Langsung, Flag_Foto) VALUES
    -- Look View (GUDANG PEKAN -> DIST RIAU)
    ( 1, 55,   'LCKV', '2026-09-28 09:00:00', '2026-09-28 15:05:18', 'Coklat Kemerahan',       NULL, 'Y'),
    ( 2, 65,   'LCKV', '2026-09-28 09:03:00', '2026-09-28 15:05:18', 'Wangi Khas Ikan Tuna',   NULL, 'T'),
    ( 3, 64,   'LCKV', '2026-09-28 09:06:00', '2026-09-28 15:05:18', 'Tidak Lengket di Pouch', NULL, 'T'),
    -- Analisa Lab proksimat (FRANS -> SV_LAB)
    ( 4, 60,   'ANL',  '2026-09-28 13:20:00', '2026-09-30 14:10:00', NULL, NULL, 'T'),
    ( 5, 57,   'ANL',  '2026-09-28 13:24:00', '2026-09-30 14:10:20', NULL, NULL, 'T'),
    ( 6, 58,   'ANL',  '2026-09-28 13:28:00', '2026-09-30 14:10:40', NULL, NULL, 'T'),
    ( 7, @SLT, 'ANL',  '2026-09-28 13:32:00', '2026-09-30 14:11:00', NULL, NULL, 'T'),
    -- Palatabilitas (DIAH -> ROBY)
    ( 8, 62,   'PLT',  '2026-09-29 14:00:00', '2026-09-30 10:40:10', 'Ya', NULL, 'T'),
    ( 9, 63,   'PLT',  '2026-09-29 14:04:00', '2026-09-30 10:40:10', NULL, 78.5, 'T'),
    (10, @DUR, 'PLT',  '2026-09-29 14:08:00', '2026-09-30 10:40:10', NULL, 245,  'T'),
    -- Mikrobiologi setelah inkubasi (FRANS -> SV_LAB)
    (11, @AC,  'ANL',  '2026-09-30 13:00:00', '2026-10-01 14:45:00', NULL, NULL, 'T'),
    (12, @YM,  'ANL',  '2026-09-30 13:04:00', '2026-10-01 14:45:20', NULL, NULL, 'T'),
    (13, @EC,  'ANL',  '2026-09-30 13:08:00', '2026-10-01 14:45:40', NULL, NULL, 'T'),
    (14, @SAL, 'ANL',  '2026-09-30 16:30:00', '2026-10-01 14:46:00', 'Negatif', NULL, 'T');

-- Parameter analisa berhitung.  60 & 58: [49]+[54]+[62]  57: [49]
-- SALT: (5-[79])*5.845*0.1/[71]   mikro: ([103]-[104])/[103]*100
DECLARE @Param TABLE (Urut int, Id_QC int, Nilai float);
INSERT INTO @Param VALUES
    (4, 49, 3.12), (4, 54, 3.10), (4, 62, 3.13),
    (5, 49, 2.26),
    (6, 49, 25.80), (6, 54, 25.78), (6, 62, 25.82),
    (7, 71, 2.0000), (7, 79, 3.40),
    (11, 103, 300), (11, 104, 288),
    (12, 103, 160), (12, 104, 157),
    (13, 103, 110), (13, 104, 110);

UPDATE a SET Flag_Perhitungan = j.Flag_Perhitungan,
             Id_Perhitungan = CASE WHEN j.Flag_Perhitungan = 'Y'
                                   THEN (SELECT TOP 1 p.id FROM N_EMI_LAB_Perhitungan p WHERE p.Id_Jenis_Analisa = a.Id_Jenis_Analisa AND p.Kode_Role = 'FLM' ORDER BY p.id) END
FROM @Analisa a JOIN N_EMI_LAB_Jenis_Analisa j ON j.id = a.Id_Jenis_Analisa;

UPDATE a SET Hasil = ROUND(x.Nilai, 2), Range_Awal = sr.Range_Awal, Range_Akhir = sr.Range_Akhir
FROM @Analisa a
CROSS APPLY (SELECT CASE
                 WHEN a.Id_Jenis_Analisa IN (@AC, @YM, @EC)
                     THEN (MAX(CASE WHEN Id_QC = 103 THEN Nilai END) - MAX(CASE WHEN Id_QC = 104 THEN Nilai END)) / NULLIF(MAX(CASE WHEN Id_QC = 103 THEN Nilai END), 0) * 100
                 WHEN a.Id_Jenis_Analisa = @SLT
                     THEN (5 - MAX(CASE WHEN Id_QC = 79 THEN Nilai END)) * 5.845 * 0.1 / NULLIF(MAX(CASE WHEN Id_QC = 71 THEN Nilai END), 0)
                 ELSE SUM(Nilai) END AS Nilai
             FROM @Param WHERE Urut = a.Urut) x
OUTER APPLY (SELECT TOP 1 Range_Awal, Range_Akhir FROM N_EMI_LAB_Standar_Rentang
             WHERE Kode_Barang = @Kode_Barang AND Id_Jenis_Analisa = a.Id_Jenis_Analisa AND Id_Master_Mesin = @Id_Mesin
               AND Id_Perhitungan = a.Id_Perhitungan AND Kode_Role = 'FLM' ORDER BY Id_Standar_Rentang) sr
WHERE a.Flag_Perhitungan = 'Y';

UPDATE @Analisa SET Flag_Layak = CASE WHEN (Range_Awal IS NULL OR Hasil >= Range_Awal) AND (Range_Akhir IS NULL OR Hasil <= Range_Akhir) THEN 'Y' ELSE 'T' END
WHERE Flag_Perhitungan = 'Y';

UPDATE a SET Hasil = k.Nilai_Kriteria, Flag_Layak = k.Flag_Layak, Range_Awal = st.Nilai_Kriteria, Range_Akhir = st.Nilai_Kriteria
FROM @Analisa a
CROSS APPLY (SELECT TOP 1 Nilai_Kriteria, Flag_Layak FROM N_EMI_LAB_Standar_Rentang_Non_Perhitungan
             WHERE Id_Jenis_Analisa = a.Id_Jenis_Analisa AND Keterangan_Kriteria = a.Label AND Flag_Aktif = 'Y' AND Kode_Role = 'FLM'
             ORDER BY Id_Standar_Rentang_Non_Perhitungan) k
OUTER APPLY (SELECT TOP 1 Nilai_Kriteria FROM N_EMI_LAB_Standar_Rentang_Non_Perhitungan
             WHERE Id_Jenis_Analisa = a.Id_Jenis_Analisa AND Flag_Layak = 'Y' AND Flag_Aktif = 'Y' AND Kode_Role = 'FLM'
             ORDER BY Id_Standar_Rentang_Non_Perhitungan) st
WHERE a.Label IS NOT NULL;

UPDATE @Analisa SET Hasil = Nilai_Langsung, Flag_Layak = 'Y' WHERE Nilai_Langsung IS NOT NULL;

IF EXISTS (SELECT 1 FROM @Analisa WHERE Hasil IS NULL OR Flag_Layak IS NULL)
    THROW 50007, N'Ada hasil uji yang tidak dapat disusun (kriteria / parameter tidak ditemukan).', 1;

-- Parameter analisa tanpa rumus = hasilnya sendiri, pada QC yang terikat.
INSERT INTO @Param (Urut, Id_QC, Nilai)
SELECT a.Urut, b.Id_Quality_Control, a.Hasil
FROM @Analisa a
CROSS APPLY (SELECT TOP 1 Id_Quality_Control FROM N_EMI_LAB_Binding_Jenis_Analisa WHERE Id_Jenis_Analisa = a.Id_Jenis_Analisa ORDER BY id) b
WHERE ISNULL(a.Flag_Perhitungan, 'T') <> 'Y';

IF EXISTS (SELECT 1 FROM @Analisa a WHERE NOT EXISTS (SELECT 1 FROM @Param p WHERE p.Urut = a.Urut))
    THROW 50008, N'Ada analisa tanpa parameter (binding QC kosong).', 1;

-- Barang Analisa FLM: 14 analisa sampel ini untuk akun ber-role FLM.
INSERT INTO N_EMI_LAB_Barang_Analisa
    (Kode_Perusahaan, Id_Jenis_Analisa, Kode_Barang, Id_Master_Mesin, Id_User, Tanggal, Jam, Id_User_Menginput, Flag_Aktif, Kode_Role)
SELECT '001', a.Id_Jenis_Analisa, @Kode_Barang, @Id_Mesin, u.Id_User, CAST(@Sekarang AS date), CONVERT(varchar(8), @Sekarang, 108), 'FRANS', 'Y', 'FLM'
FROM @Analisa a
CROSS JOIN (SELECT DISTINCT ur.Id_User FROM N_EMI_LAB_User_Roles ur JOIN N_EMI_LAB_Roles r ON r.Id_Role = ur.Id_Role WHERE r.Kode_Role = 'FLM') u
WHERE NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Barang_Analisa x WHERE x.Id_Jenis_Analisa = a.Id_Jenis_Analisa AND x.Kode_Barang = @Kode_Barang
                    AND x.Id_Master_Mesin = @Id_Mesin AND x.Id_User = u.Id_User AND x.Kode_Role = 'FLM');

-- ============================================================================
-- 3. NOMOR SAMPEL (pakai ulang nomor dummy sebelumnya) + bersihkan jejak lama
-- ============================================================================
DECLARE @No_Sampel varchar(30) = (
    SELECT TOP 1 No_Sampel FROM N_LIMS_PO_Sampel
    WHERE No_Split_Po = @No_Split_Po AND CAST(Keterangan AS varchar(max)) LIKE @Penanda + '%' ORDER BY id);
IF @No_Sampel IS NULL
    SELECT @No_Sampel = 'FT0926-' + RIGHT('0000' + CAST(ISNULL(MAX(TRY_CAST(SUBSTRING(No_Sampel, 8, 10) AS int)), 0) + 1 AS varchar(10)), 4)
    FROM N_LIMS_PO_Sampel WITH (UPDLOCK, HOLDLOCK)
    WHERE No_Sampel LIKE 'FT0926-[0-9][0-9][0-9][0-9]';
DECLARE @Sub varchar(30) = @No_Sampel + '-1';

DELETE d FROM N_EMI_LAB_Log_Aksi_Detail d JOIN N_EMI_LAB_Log_Aksi l ON l.Id_Log_Aksi = d.Id_Log_Aksi WHERE l.No_Sampel = @No_Sampel;
DELETE FROM N_EMI_LAB_Log_Aksi                              WHERE No_Sampel = @No_Sampel;
DELETE FROM N_EMI_LIMS_Hasil_Uji_Validasi_Final             WHERE No_Sampel = @No_Sampel;
DELETE FROM N_EMI_LIMS_Hasil_Uji_Validasi_Detail_Final      WHERE No_Sampel = @No_Sampel;
DELETE FROM N_EMI_LIMS_Uji_Pra_Final                        WHERE No_Sampel = @No_Sampel;
DELETE FROM N_EMI_LIMS_Uji_Sampel_Keterangan_Status         WHERE No_Sampel = @No_Sampel OR No_Sampel LIKE @No_Sampel + '-%';
DELETE FROM N_EMI_LIMS_Uji_Sampel_Resampling_Log            WHERE No_Po_Sampel = @No_Sampel;
DELETE FROM N_EMI_LIMS_Berkas_Uji_Lab                       WHERE No_Sampel = @No_Sampel;
DELETE FROM N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail WHERE No_Po_Sampel = @No_Sampel;
DELETE FROM N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail     WHERE No_Po_Sampel = @No_Sampel;
DELETE FROM N_EMI_LIMS_Activity_Uji_Sampel                  WHERE No_Po_Sampel = @No_Sampel;
DELETE FROM N_EMI_LIMS_Uji_Sampel_Detail
WHERE No_Faktur_Uji_Sample IN (SELECT No_Faktur FROM N_EMI_LIMS_Uji_Sampel WHERE No_Po_Sampel = @No_Sampel);
DELETE FROM N_EMI_LIMS_Uji_Sampel                           WHERE No_Po_Sampel = @No_Sampel;
DELETE FROM N_LIMS_PO_Sampel_Multi_QrCode                   WHERE No_Po_Sampel = @No_Sampel;
DELETE FROM N_EMI_LIMS_Activity_Produksi_Sampel             WHERE No_Po = @No_Po AND No_Split_Po = @No_Split_Po AND No_Batch = @No_Batch;
DELETE FROM N_LIMS_PO_Sampel                                WHERE No_Sampel = @No_Sampel;

DECLARE @Faktur_Terakhir int = ISNULL((
    SELECT MAX(TRY_CAST(SUBSTRING(No_Faktur, 9, 10) AS int)) FROM N_EMI_LIMS_Uji_Sampel WITH (UPDLOCK, HOLDLOCK)
    WHERE No_Faktur LIKE 'FUS0926-%'), 0);
WITH u AS (SELECT No_Faktur, ROW_NUMBER() OVER (ORDER BY Waktu_Input, Urut) n FROM @Analisa)
UPDATE u SET No_Faktur = 'FUS0926-' + RIGHT('0000' + CAST(@Faktur_Terakhir + n AS varchar(10)), 4);

-- ============================================================================
-- 4. REGISTRASI MATERIAL
-- ============================================================================
INSERT INTO N_LIMS_PO_Sampel
    (Kode_Perusahaan, No_Po, No_Split_Po, No_Batch, Kode_Barang, No_Sampel, Status, Tanggal, Jam, Id_Mesin, Keterangan, Id_User, Flag_Selesai, Jumlah_Pcs)
VALUES ('001', @No_Po, @No_Split_Po, @No_Batch, @Kode_Barang, @No_Sampel, NULL, CAST(CAST(@Waktu_Reg AS date) AS datetime), CONVERT(varchar(8), @Waktu_Reg, 108),
        @Id_Mesin, @Penanda + ' - uji laporan validator & finalisator (Look View GUDANG PEKAN/DIST RIAU, Lab FRANS/SV_LAB, Palatabilitas DIAH/ROBY, final VENGINE)',
        @Registrasi, NULL, 1);

INSERT INTO N_LIMS_PO_Sampel_Multi_QrCode (Kode_Perusahaan, No_Po_Multi, Kode_Barang, No_Po_Sampel, Status, Tanggal, Jam, Flag_Selesai)
VALUES ('001', @Sub, @Kode_Barang, @No_Sampel, NULL, CAST(CAST(@Waktu_Reg AS date) AS datetime), CONVERT(varchar(8), @Waktu_Reg, 108), NULL);

INSERT INTO N_EMI_LIMS_Activity_Produksi_Sampel
    (No_Po, No_Split_Po, No_Batch, Jenis_Aktivitas, Status_Aktivitas, Keterangan, Tanggal, Id_Mesin, Jam, Flag_Berhasil_Cetak_QrCode, Id_User)
VALUES
    (@No_Po, @No_Split_Po, @No_Batch, 'Registrasi Material', 'Berhasil',
     'Untuk Nomor Po ' + @No_Po + ' Berhasil Melakukan Registrasi Material Dan Menunggu Proses Cetak',
     CAST(CAST(@Waktu_Reg AS date) AS datetime), @Id_Mesin, CONVERT(varchar(8), @Waktu_Reg, 108), 'Y', @Registrasi),
    (@No_Po, @No_Split_Po, @No_Batch, 'Cetak QrCode Material', 'berhasil',
     'Untuk Nomor Po ' + @No_Po + ' Berhasil Melakukan Cetak QrCode',
     CAST(CAST(@Waktu_Reg AS date) AS datetime), @Id_Mesin, CONVERT(varchar(8), @Waktu_Reg, 108), NULL, @Registrasi);

-- ============================================================================
-- 5. HASIL UJI + PARAMETER + JEJAK KIRIM + FOTO
--    Id_User baris uji = VENGINE: pra-finalisasi aplikasi menimpanya dengan
--    akun penyetuju. Penginput asli ada di tabel aktivitas di bawah.
-- ============================================================================
INSERT INTO N_EMI_LIMS_Uji_Sampel
    (Kode_Perusahaan, No_Faktur, No_Po_Sampel, No_Fak_Sub_Po, Id_Jenis_Analisa, Hasil, Flag_Perhitungan, Flag_Multi_QrCode, Status,
     Tanggal, Jam, Id_User, Flag_Selesai, Id_Perhitungan, Range_Awal, Range_Akhir, Tahapan_Ke, Flag_Resampling,
     Status_Keputusan_Sampel, Flag_Layak, Flag_Final, Id_Mesin, Flag_String, Nilai_Hasil_String, Flag_Foto, Flag_Approval)
SELECT '001', a.No_Faktur, @No_Sampel, @Sub, a.Id_Jenis_Analisa, a.Hasil, a.Flag_Perhitungan, 'Y', NULL,
       CAST(CAST(a.Waktu_Input AS date) AS datetime), CONVERT(varchar(8), a.Waktu_Input, 108), @Finalisasi,
       'Y', a.Id_Perhitungan, a.Range_Awal, a.Range_Akhir, 1, NULL,
       'terima', a.Flag_Layak, 'Y', @Id_Mesin, NULL, NULL, a.Flag_Foto, 'Y'
FROM @Analisa a ORDER BY a.No_Faktur;

DECLARE @Penginput TABLE (Urut int PRIMARY KEY, Id_User varchar(30), Nama varchar(200));
INSERT INTO @Penginput
SELECT a.Urut, x.Id_User, ISNULL(u.Nama, x.Id_User)
FROM @Analisa a
CROSS APPLY (SELECT CASE a.Klas WHEN 'LCKV' THEN @Input_LV WHEN 'ANL' THEN @Input_ANL ELSE @Input_PLT END AS Id_User) x
LEFT JOIN N_EMI_LAB_Users u ON u.UserId = x.Id_User;

INSERT INTO N_EMI_LIMS_Uji_Sampel_Detail (Kode_Perusahaan, No_Faktur_Uji_Sample, Id_Quality_Control, Value_Parameter, Tanggal, Jam, Id_User)
SELECT '001', a.No_Faktur, p.Id_QC, p.Nilai, CAST(CAST(a.Waktu_Input AS date) AS datetime), CONVERT(varchar(8), a.Waktu_Input, 108), pi.Id_User
FROM @Analisa a JOIN @Param p ON p.Urut = a.Urut JOIN @Penginput pi ON pi.Urut = a.Urut
ORDER BY a.No_Faktur, p.Id_QC;

INSERT INTO N_EMI_LIMS_Activity_Uji_Sampel (Kode_Perusahaan, No_Po_Sampel, No_Fak_Sub_Po, Jenis_Aktivitas, Id_Jenis_Analisa, Keterangan, Id_User, Tanggal, Jam)
SELECT '001', @No_Sampel, @Sub, 'save_submit', a.Id_Jenis_Analisa, pi.Nama + ' Berhasil Mengirimkan Data Analisa', pi.Id_User,
       CAST(CAST(a.Waktu_Input AS date) AS datetime), CONVERT(varchar(8), a.Waktu_Input, 108)
FROM @Analisa a JOIN @Penginput pi ON pi.Urut = a.Urut
ORDER BY a.No_Faktur;

INSERT INTO N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail
    (Id_Log_Activity_Sampel, Kode_Perusahaan, No_Po_Sampel, No_Fak_Sub_Po, Id_Jenis_Analisa, Value_Baru, Value_Lama, Tanggal, Jam, Id_User, Status_Submit, Id_Perhitungan)
SELECT act.Id_Log_Activity, '001', @No_Sampel, @Sub, a.Id_Jenis_Analisa, a.Hasil, a.Hasil,
       CAST(CAST(a.Waktu_Input AS date) AS datetime), CONVERT(varchar(8), a.Waktu_Input, 108), pi.Id_User, 'Submited', a.Id_Perhitungan
FROM @Analisa a JOIN @Penginput pi ON pi.Urut = a.Urut
JOIN N_EMI_LIMS_Activity_Uji_Sampel act ON act.No_Po_Sampel = @No_Sampel AND act.Id_Jenis_Analisa = a.Id_Jenis_Analisa AND act.Jenis_Aktivitas = 'save_submit'
ORDER BY a.No_Faktur;

INSERT INTO N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail
    (Id_Log_Activity_Sampel, Kode_Perusahaan, No_Po_Sampel, No_Fak_Sub_Po, Id_Jenis_Analisa, Id_Quality_Control, Value_Baru, Value_Lama, Tanggal, Jam, Id_User, Status_Submit, Alasan_Mengubah_Data)
SELECT act.Id_Log_Activity, '001', @No_Sampel, @Sub, a.Id_Jenis_Analisa, p.Id_QC, p.Nilai, p.Nilai,
       CAST(CAST(a.Waktu_Input AS date) AS datetime), CONVERT(varchar(8), a.Waktu_Input, 108), pi.Id_User, 'Submited', NULL
FROM @Analisa a JOIN @Param p ON p.Urut = a.Urut JOIN @Penginput pi ON pi.Urut = a.Urut
JOIN N_EMI_LIMS_Activity_Uji_Sampel act ON act.No_Po_Sampel = @No_Sampel AND act.Id_Jenis_Analisa = a.Id_Jenis_Analisa AND act.Jenis_Aktivitas = 'save_submit'
ORDER BY a.No_Faktur, p.Id_QC;

DECLARE @CRLF char(2) = CHAR(13) + CHAR(10);
INSERT INTO N_EMI_LIMS_Berkas_Uji_Lab (No_Faktur, No_Sampel, Berkas_Key, File_Path, Id_Jenis_Analisa, Keterangan)
SELECT a.No_Faktur, @No_Sampel, LOWER(LEFT(REPLACE(CONVERT(varchar(36), NEWID()), '-', ''), 32)), f.File_Path, NULL, f.Keterangan
FROM @Analisa a
CROSS APPLY (VALUES
    (1, 'berkas/lab/labwHO7X_1780477883_1.png',
        '- Warna: coklat kemerahan, merata' + @CRLF + '- Permukaan: mengkilap, tidak ada browning' + @CRLF + '- Pouch: tidak menggembung'),
    (2, 'berkas/lab/labRxKrI_1780477883_0.png', 'Perbandingan dengan produk existing: warna setara')
) AS f (No, File_Path, Keterangan)
WHERE a.Flag_Foto = 'Y'
ORDER BY f.No;

-- ============================================================================
-- 6. VALIDASI HASIL TRIAL (Look View: DIST RIAU, Lab: SV_LAB, Palatabilitas: ROBY)
-- ============================================================================
DECLARE @Validasi TABLE (Urut int PRIMARY KEY, Id_User varchar(30), Waktu datetime);
INSERT INTO @Validasi
SELECT Urut, CASE Klas WHEN 'LCKV' THEN @Validasi_LV WHEN 'ANL' THEN @Validasi_ANL ELSE @Validasi_PLT END, Waktu_Validasi FROM @Analisa;

INSERT INTO N_EMI_LIMS_Hasil_Uji_Validasi_Detail_Final (No_Sampel, Id_Jenis_Analisa, Tahapan_Ke, Tanggal, Jam, Flag_Layak, Flag_Resampling, Id_User, No_Sub_Sampel)
SELECT @No_Sampel, a.Id_Jenis_Analisa, 1, CAST(CAST(v.Waktu AS date) AS datetime), CONVERT(varchar(8), v.Waktu, 108), a.Flag_Layak, NULL, v.Id_User, @Sub
FROM @Analisa a JOIN @Validasi v ON v.Urut = a.Urut ORDER BY v.Waktu, a.Urut;

DECLARE @Pertama_User varchar(30), @Pertama_Waktu datetime, @Id_Log int;
SELECT TOP 1 @Pertama_User = Id_User, @Pertama_Waktu = Waktu FROM @Validasi ORDER BY Waktu, Urut;
INSERT INTO N_EMI_LAB_Log_Aksi (No_Sampel, No_Po, No_Split_Po, Kode_Barang, Flag_Trial, Jenis_Aksi, Sub_Aksi, Keterangan, Id_User, Tanggal, Jam)
VALUES (@No_Sampel, @No_Po, @No_Split_Po, @Kode_Barang, NULL, 'VALIDASI_FORMULATOR', 'SETUJU', NULL, @Pertama_User, CAST(@Pertama_Waktu AS date), CAST(@Pertama_Waktu AS time(0)));
SET @Id_Log = SCOPE_IDENTITY();
INSERT INTO N_EMI_LAB_Log_Aksi_Detail (Id_Log_Aksi, Id_Jenis_Analisa, Nama_Jenis_Analisa, Flag_Layak, Tanggal, Jam, Id_User)
SELECT @Id_Log, a.Id_Jenis_Analisa, j.Jenis_Analisa, a.Flag_Layak, CONVERT(varchar(10), v.Waktu, 23), CONVERT(varchar(8), v.Waktu, 108), v.Id_User
FROM @Analisa a JOIN @Validasi v ON v.Urut = a.Urut JOIN N_EMI_LAB_Jenis_Analisa j ON j.id = a.Id_Jenis_Analisa
ORDER BY v.Waktu, a.Urut;

-- ============================================================================
-- 7. PRA-FINALISASI (VENGINE): setujui tiap tahap, lalu finalisasi pra-final
-- ============================================================================
DECLARE @Tahap TABLE (Urut int PRIMARY KEY, Klas varchar(4), Waktu datetime);
INSERT INTO @Tahap VALUES (1, 'LCKV', @Waktu_PraLV), (2, 'ANL', @Waktu_PraANL), (3, 'PLT', @Waktu_PraPLT);

INSERT INTO N_EMI_LIMS_Uji_Sampel_Keterangan_Status (Kode_Perusahaan, No_Sampel, Status_Keterangan, Alasan, Tanggal, Jam, Id_User)
SELECT '001', @Sub, 'Persetujuan Tahap ' + t.Klas, 'Disetujui otomatis oleh sistem',
       CAST(CAST(t.Waktu AS date) AS datetime), CONVERT(varchar(8), t.Waktu, 108), @Finalisasi
FROM @Tahap t JOIN @Analisa a ON a.Klas = t.Klas
ORDER BY t.Urut, a.Urut;

DECLARE @k varchar(4), @w datetime, @i int = 1;
WHILE @i <= 3
BEGIN
    SELECT @k = Klas, @w = Waktu FROM @Tahap WHERE Urut = @i;
    INSERT INTO N_EMI_LAB_Log_Aksi (No_Sampel, No_Po, No_Split_Po, Kode_Barang, Flag_Trial, Jenis_Aksi, Sub_Aksi, Keterangan, Id_User, Tanggal, Jam)
    VALUES (@No_Sampel, @No_Po, @No_Split_Po, @Kode_Barang, NULL, 'VALIDASI_FORMULATOR', 'SETUJU', NULL, @Finalisasi, CAST(@w AS date), CAST(@w AS time(0)));
    SET @Id_Log = SCOPE_IDENTITY();
    INSERT INTO N_EMI_LAB_Log_Aksi_Detail (Id_Log_Aksi, Id_Jenis_Analisa, Nama_Jenis_Analisa, Flag_Layak, Tanggal, Jam, Id_User)
    SELECT @Id_Log, a.Id_Jenis_Analisa, j.Jenis_Analisa, NULL, CONVERT(varchar(10), @w, 23), CONVERT(varchar(8), @w, 108), @Finalisasi
    FROM @Analisa a JOIN N_EMI_LAB_Jenis_Analisa j ON j.id = a.Id_Jenis_Analisa
    WHERE a.Klas = @k ORDER BY a.Urut;
    SET @i += 1;
END;

INSERT INTO N_EMI_LIMS_Uji_Pra_Final (No_Sampel, Alasan, Flag_Setuju, Tanggal, Jam, Id_User)
VALUES (@No_Sampel, 'Disetujui otomatis oleh sistem informasi', 'Y', CAST(CAST(@Waktu_Pra AS date) AS datetime), CONVERT(varchar(8), @Waktu_Pra, 108), @Finalisasi);

-- No_Po / No_Split_Po '-' : sama dengan yang ditulis finalizePraFinal saat ini.
INSERT INTO N_EMI_LAB_Log_Aksi (No_Sampel, No_Po, No_Split_Po, Kode_Barang, Flag_Trial, Jenis_Aksi, Sub_Aksi, Keterangan, Id_User, Tanggal, Jam)
VALUES (@No_Sampel, '-', '-', @Kode_Barang, NULL, 'PRAFINALISASI_FORMULATOR', 'SETUJU', NULL, @Finalisasi, CAST(@Waktu_Pra AS date), CAST(@Waktu_Pra AS time(0)));
SET @Id_Log = SCOPE_IDENTITY();
INSERT INTO N_EMI_LAB_Log_Aksi_Detail (Id_Log_Aksi, Id_Jenis_Analisa, Nama_Jenis_Analisa, Flag_Layak, Tanggal, Jam, Id_User)
SELECT @Id_Log, a.Id_Jenis_Analisa, j.Jenis_Analisa, NULL, CONVERT(varchar(10), @Waktu_Pra, 23), CONVERT(varchar(8), @Waktu_Pra, 108), @Finalisasi
FROM @Analisa a JOIN N_EMI_LAB_Jenis_Analisa j ON j.id = a.Id_Jenis_Analisa ORDER BY a.Urut;

-- ============================================================================
-- 8. FINALISASI TRIAL (VENGINE)
-- ============================================================================
DECLARE @Flag_Ok char(1) = CASE WHEN EXISTS (SELECT 1 FROM @Analisa WHERE Flag_Layak = 'T') THEN 'T' ELSE 'Y' END, @Id_Header int;

INSERT INTO N_EMI_LIMS_Hasil_Uji_Validasi_Final (No_Po, No_Split_Po, No_Batch, No_Sampel, Tanggal, Jam, Flag_FG, Flag_Ok, Id_User)
VALUES (@No_Po, @No_Split_Po, @No_Batch, @No_Sampel, CAST(CAST(@Waktu_Final AS date) AS datetime), CONVERT(varchar(8), @Waktu_Final, 108), 'Y', @Flag_Ok, @Finalisasi);
SET @Id_Header = SCOPE_IDENTITY();

UPDATE N_LIMS_PO_Sampel SET Flag_Selesai = 'Y' WHERE No_Sampel = @No_Sampel;

INSERT INTO N_EMI_LAB_Log_Aksi (No_Sampel, No_Po, No_Split_Po, Kode_Barang, Flag_Trial, Jenis_Aksi, Sub_Aksi, Keterangan, Id_User, Tanggal, Jam)
VALUES (@No_Sampel, @No_Po, @No_Split_Po, @Kode_Barang, 'Y', 'FINALISASI_FORMULATOR', 'CLOSE', NULL, @Finalisasi, CAST(@Waktu_Final AS date), CAST(@Waktu_Final AS time(0)));
SET @Id_Log = SCOPE_IDENTITY();
INSERT INTO N_EMI_LAB_Log_Aksi_Detail (Id_Log_Aksi, Id_Jenis_Analisa, Nama_Jenis_Analisa, Flag_Layak, Tanggal, Jam, Id_User)
SELECT @Id_Log, a.Id_Jenis_Analisa, j.Jenis_Analisa, NULL, CONVERT(varchar(10), @Waktu_Final, 23), CONVERT(varchar(8), @Waktu_Final, 108), @Finalisasi
FROM @Analisa a JOIN N_EMI_LAB_Jenis_Analisa j ON j.id = a.Id_Jenis_Analisa ORDER BY a.Urut;

COMMIT TRANSACTION;

-- ============================================================================
-- RINGKASAN
-- ============================================================================
SELECT @No_Sampel AS No_Sampel, @No_Po AS No_Po, @No_Split_Po AS No_Split_Po, @Id_Header AS Id_Header_Final;

SELECT K.Nama_Aktivitas AS Klasifikasi, J.Jenis_Analisa, U.No_Faktur, U.Hasil, SR.Keterangan_Kriteria AS Label,
       U.Range_Awal, U.Range_Akhir, U.Flag_Layak, U.Flag_Approval, U.Flag_Final,
       (SELECT TOP 1 act.Id_User FROM N_EMI_LIMS_Activity_Uji_Sampel act WHERE act.No_Po_Sampel = U.No_Po_Sampel AND act.Id_Jenis_Analisa = U.Id_Jenis_Analisa) AS Penginput,
       (SELECT TOP 1 d.Id_User FROM N_EMI_LIMS_Hasil_Uji_Validasi_Detail_Final d WHERE d.No_Sampel = U.No_Po_Sampel AND d.Id_Jenis_Analisa = U.Id_Jenis_Analisa) AS Validator,
       (SELECT COUNT(*) FROM N_EMI_LIMS_Berkas_Uji_Lab b WHERE b.No_Faktur = U.No_Faktur) AS Foto
FROM N_EMI_LIMS_Uji_Sampel U
JOIN N_EMI_LAB_Jenis_Analisa J ON J.id = U.Id_Jenis_Analisa
JOIN N_EMI_LIMS_Klasifikasi_Aktivitas_Lab K ON K.Kode_Aktivitas_Lab = J.Kode_Aktivitas_Lab
LEFT JOIN N_EMI_LAB_Standar_Rentang_Non_Perhitungan SR
       ON SR.Id_Jenis_Analisa = U.Id_Jenis_Analisa AND SR.Nilai_Kriteria = U.Hasil AND SR.Kode_Role = 'FLM' AND SR.Flag_Aktif = 'Y'
WHERE U.No_Po_Sampel = @No_Sampel
ORDER BY K.Urutan, U.No_Faktur;
