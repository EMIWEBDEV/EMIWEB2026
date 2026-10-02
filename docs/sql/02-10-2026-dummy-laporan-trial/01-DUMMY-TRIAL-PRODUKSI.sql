-- ============================================================================
-- 01-DUMMY-TRIAL-PRODUKSI.sql
-- Tanggal : 02-10-2026
-- Modul   : TRIAL PRODUKSI (modul LAB) - 1 sampel lengkap s.d. FINALISASI,
--           untuk menguji laporan "siapa memvalidasi / siapa memfinalisasi"
-- Database: HANYA emi_tm_demo. Skrip menolak berjalan di database lain.
--
-- HASIL
--   PO trial produksi PRD0626-00002, split PRD0626-00002-1, batch 1
--   0745114273048 (ORI CAT 80GR PURE TUNA, pouch), mesin AUTOCLAVE, 3 pcs.
--   Split ini belum pernah dipakai (tanpa sampel, header, approval, maupun log),
--   jadi laporan per split hanya berisi sampel ini.
--
--   Aktivitas      Penginput   Validator   Analisa
--   Look View      IIS         Yusuf       WARNA (QA) + 2 foto, AROMA (QA), TEKSTUR POUCH (QA)
--   Analisa Lab    FRANS       SV_LAB      PROTEIN, ASH, MOISTURE (MA), SALT,
--                                          MIKROBIOLOGI-AC / -YM / -EC / -SALMONELLA
--   Palatabilitas  DIAH        ROBY        RESPONDEN MEMAKAN, DURASI, TINGKAT KONSUMSI
--   Finalisasi PO trial: VENGINE.  Registrasi sampel: FRANS.
--
--   Jadwal: registrasi & input 28-30 Sep 2026, validasi 28 Sep - 1 Okt,
--   finalisasi 2 Okt 2026 08:15. Seluruh input jatuh di bulan September supaya
--   nomor sampel (FS0926-...) dan faktur (FUS0926-...) tidak bentrok dengan
--   nomor Oktober yang sedang dipakai di demo.
--
-- BENTUK DATA = bentuk yang ditulis aplikasi sekarang (dicocokkan dengan
--   sampel produksi FS0926-1030, read-only):
--   * Validasi Look View/Lab/Palatabilitas -> N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
--     (Sumber_Pencatatan 'VALIDASI', Kode_Aktivitas_Lab terisi), approval
--     'VALIDASI' di N_EMI_LAB_Hasil_Uji_Approval_Aktivitas, dan satu header
--     Log_Aksi VALIDASI_TRIAL_PRODUKSI (dibuat validator pertama) dengan
--     Id_User validator di tiap baris DETAIL.
--   * Finalisasi -> header N_EMI_LAB_Hasil_Uji_Validasi_Final, detail
--     tersambung ke header, approval 'FINALISASI', Log_Aksi
--     FINALISASI_TRIAL_PRODUKSI, Flag_Final & PO Flag_Selesai.
--   * Palatabilitas: 7 baris per faktur (parameter 111-117) dengan sesi dan
--     pembanding, sama seperti input palatabilitas di aplikasi.
--   * Batas min-max Analisa Lab disimpan di baris uji (AUTOCLAVE tidak punya
--     master standar rentang LAB di demo). Master tidak diubah.
--   * Foto WARNA memakai ulang kunci berkas foto produk lab yang sudah ada.
--   Hak akses akun TIDAK diubah (bypass): data ditulis langsung.
--
-- ULANG / HAPUS
--   Aman dijalankan ulang: sampel dummy di split ini dihapus lalu dibuat
--   kembali dengan nomor yang sama. Hapus: 03-HAPUS-DUMMY-LAPORAN-TRIAL.sql.
--   Bila split sudah dipakai sampel ASLI, skrip berhenti tanpa mengubah apa pun.
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;

IF DB_NAME() <> N'emi_tm_demo'
    THROW 50001, N'Skrip dummy hanya untuk database emi_tm_demo. Dibatalkan.', 1;

DECLARE
    @Penanda      varchar(40) = 'DATA DUMMY LAPORAN',
    @No_Po        varchar(30) = 'PRD0626-00002',
    @No_Split_Po  varchar(30) = 'PRD0626-00002-1',
    @No_Batch     int         = 1,
    @Kode_Barang  varchar(30) = '0745114273048',
    @Id_Mesin     int         = 4,          -- AUTOCLAVE
    @Jumlah_Pcs   int         = 3,          -- -1 Analisa Lab, -2 Look View, -3 Palatabilitas
    @Registrasi   varchar(30) = 'FRANS',
    @Input_LV     varchar(30) = 'IIS',
    @Validasi_LV  varchar(30) = 'Yusuf',
    @Input_ANL    varchar(30) = 'FRANS',
    @Validasi_ANL varchar(30) = 'SV_LAB',
    @Input_PLT    varchar(30) = 'DIAH',
    @Validasi_PLT varchar(30) = 'ROBY',
    @Finalisasi   varchar(30) = 'VENGINE',
    @Waktu_Reg    datetime    = '2026-09-28 07:40:15',
    @Waktu_Final  datetime    = '2026-10-02 08:15:33';

IF NOT EXISTS (SELECT 1 FROM EMI_Master_Mesin WHERE Id_Master_Mesin = @Id_Mesin AND Nama_Mesin = 'AUTOCLAVE')
    THROW 50002, N'Mesin AUTOCLAVE (Id_Master_Mesin 4) tidak ditemukan.', 1;

IF NOT EXISTS (SELECT 1 FROM N_EMI_View_Order_Produksi
               WHERE No_Faktur = @No_Po AND Kode_Barang = @Kode_Barang AND Flag_Trial_Produksi = 'Y')
    THROW 50003, N'PO trial produksi PRD0626-00002 / 0745114273048 tidak ditemukan.', 1;

IF EXISTS (SELECT 1 FROM N_EMI_LAB_PO_Sampel
           WHERE No_Split_Po = @No_Split_Po
             AND ISNULL(CAST(Keterangan AS varchar(max)), '') NOT LIKE @Penanda + '%')
    THROW 50004, N'Split PRD0626-00002-1 sudah dipakai sampel asli. Dibatalkan.', 1;

IF (SELECT COUNT(*) FROM N_EMI_LAB_Users
    WHERE UserId IN (@Registrasi, @Input_LV, @Validasi_LV, @Input_ANL, @Validasi_ANL, @Input_PLT, @Validasi_PLT, @Finalisasi)) < 7
    THROW 50005, N'Ada akun yang belum terdaftar di N_EMI_LAB_Users.', 1;

-- Rumus yang dipakai menyusun hasil harus sama dengan master.
IF (SELECT COUNT(*) FROM N_EMI_LAB_Perhitungan WHERE Kode_Role = 'LAB' AND (
        (id = 35 AND Id_Jenis_Analisa = 14 AND Rumus = '([69]+[71]-[89])*100/[71]') OR
        (id = 36 AND Id_Jenis_Analisa = 15 AND Rumus = '([78]-0.2275)*0.2457*8.754/[71]') OR
        (id = 37 AND Id_Jenis_Analisa = 16 AND Rumus = '([89]-[69])*100/[71]') OR
        (id = 38 AND Id_Jenis_Analisa = 17 AND Rumus = '(5-[79])*5.845*0.1/[71]') OR
        (id = 66 AND Id_Jenis_Analisa = 43 AND Rumus = '([103]-[104])/[103]*100') OR
        (id = 67 AND Id_Jenis_Analisa = 42 AND Rumus = '([103]-[104])/[103]*100') OR
        (id = 68 AND Id_Jenis_Analisa = 44 AND Rumus = '([103]-[104])/[103]*100'))) <> 7
    THROW 50006, N'Rumus perhitungan LAB di master berbeda dari yang diharapkan skrip.', 1;

BEGIN TRANSACTION;

-- ============================================================================
-- 1. NOMOR SAMPEL (pakai ulang nomor dummy sebelumnya) + bersihkan jejak lama
-- ============================================================================
DECLARE @No_Sampel varchar(30) = (
    SELECT TOP 1 No_Sampel FROM N_EMI_LAB_PO_Sampel
    WHERE No_Split_Po = @No_Split_Po AND CAST(Keterangan AS varchar(max)) LIKE @Penanda + '%'
    ORDER BY id);

IF @No_Sampel IS NULL
    SELECT @No_Sampel = 'FS0926-' + RIGHT('0000' + CAST(ISNULL(MAX(TRY_CAST(SUBSTRING(No_Sampel, 8, 10) AS int)), 0) + 1 AS varchar(10)), 4)
    FROM N_EMI_LAB_PO_Sampel WITH (UPDLOCK, HOLDLOCK)
    WHERE No_Sampel LIKE 'FS0926-[0-9][0-9][0-9][0-9]';

IF OBJECT_ID('dbo.N_EMI_LAB_Verifikasi_Header') IS NOT NULL
    EXEC sp_executesql N'
        DELETE r FROM N_EMI_LAB_Verifikasi_Riwayat r JOIN N_EMI_LAB_Verifikasi_Header h ON h.Id_Verifikasi = r.Id_Verifikasi WHERE h.No_Sampel = @s;
        DELETE d FROM N_EMI_LAB_Verifikasi_Detail d JOIN N_EMI_LAB_Verifikasi_Header h ON h.Id_Verifikasi = d.Id_Verifikasi WHERE h.No_Sampel = @s;
        DELETE FROM N_EMI_LAB_Verifikasi_Header WHERE No_Sampel = @s;', N'@s varchar(30)', @s = @No_Sampel;

DELETE d FROM N_EMI_LAB_Log_Aksi_Detail d JOIN N_EMI_LAB_Log_Aksi l ON l.Id_Log_Aksi = d.Id_Log_Aksi WHERE l.No_Sampel = @No_Sampel;
DELETE FROM N_EMI_LAB_Log_Aksi                        WHERE No_Sampel = @No_Sampel;
DELETE FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas    WHERE No_Sampel = @No_Sampel;
DELETE FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final WHERE No_Sampel = @No_Sampel;
DELETE FROM N_EMI_LAB_Hasil_Uji_Validasi_Final        WHERE No_Sampel = @No_Sampel;
DELETE FROM N_EMI_LAB_Uji_Sampel_Resampling_Log       WHERE No_Po_Sampel = @No_Sampel;
DELETE FROM N_EMI_LAB_Berkas_Uji_Lab                  WHERE No_Sampel = @No_Sampel;
DELETE FROM N_EMI_LAB_Activity_Uji_Sampel             WHERE No_Po_Sampel = @No_Sampel;
DELETE FROM N_EMI_LAB_Uji_Sampel_Detail
WHERE No_Faktur_Uji_Sample IN (SELECT No_Faktur FROM N_EMI_LAB_Uji_Sampel WHERE No_Po_Sampel = @No_Sampel);
DELETE FROM N_EMI_LAB_Uji_Sampel                      WHERE No_Po_Sampel = @No_Sampel;
DELETE p FROM N_EMI_LAB_Palatabilitas_Pembanding p
JOIN N_EMI_LAB_Palatabilitas_Session s ON s.Id_Session = p.Id_Session WHERE s.No_Po_Sampel = @No_Sampel;
DELETE FROM N_EMI_LAB_Palatabilitas_Sementara         WHERE No_Po_Sampel = @No_Sampel;
DELETE FROM N_EMI_LAB_Palatabilitas_Session           WHERE No_Po_Sampel = @No_Sampel;
DELETE FROM N_EMI_LAB_PO_Sampel_Multi_QrCode          WHERE No_Po_Sampel = @No_Sampel;
DELETE FROM N_EMI_LAB_Activity_Produksi_Sampel        WHERE No_Split_Po = @No_Split_Po AND No_Batch = @No_Batch;
DELETE FROM N_EMI_LAB_PO_Sampel                       WHERE No_Sampel = @No_Sampel;

-- ============================================================================
-- 2. REGISTRASI
-- ============================================================================
INSERT INTO N_EMI_LAB_PO_Sampel
    (Kode_Perusahaan, No_Po, Kode_Barang, No_Sampel, Status, Tanggal, Jam, No_Split_Po, No_Batch, Id_Mesin,
     Keterangan, Id_User, Flag_Selesai, Jumlah_Pcs, Flag_Trial_Produksi)
VALUES ('001', @No_Po, @Kode_Barang, @No_Sampel, NULL, CAST(@Waktu_Reg AS date), CONVERT(varchar(8), @Waktu_Reg, 108),
        @No_Split_Po, @No_Batch, @Id_Mesin,
        @Penanda + ' - uji laporan validator & finalisator (Look View IIS/Yusuf, Lab FRANS/SV_LAB, Palatabilitas DIAH/ROBY, final VENGINE)',
        @Registrasi, NULL, @Jumlah_Pcs, 'Y');

INSERT INTO N_EMI_LAB_PO_Sampel_Multi_QrCode (Kode_Perusahaan, No_Po_Multi, Kode_Barang, No_Po_Sampel, Status, Tanggal, Jam)
SELECT '001', @No_Sampel + '-' + CAST(n AS varchar(2)), @Kode_Barang, @No_Sampel, NULL, CAST(@Waktu_Reg AS date), CONVERT(varchar(8), @Waktu_Reg, 108)
FROM (VALUES (1), (2), (3)) AS x (n) ORDER BY n;

INSERT INTO N_EMI_LAB_Activity_Produksi_Sampel
    (No_Po, No_Split_Po, No_Batch, Jenis_Aktivitas, Status_Aktivitas, Keterangan, Tanggal, Id_Mesin, Jam, Flag_Berhasil_Cetak_QrCode, Id_User)
VALUES (@No_Po, @No_Split_Po, @No_Batch, 'Pengambilan Sampel', 'Berhasil',
        'Untuk Nomor Po ' + @No_Po + ' Berhasil Melakukan Pengambilan Sampel Dan Menunggu Proses Cetak',
        CAST(@Waktu_Reg AS date), @Id_Mesin, CONVERT(varchar(8), @Waktu_Reg, 108), 'Y', @Registrasi);

-- Sesi & pembanding palatabilitas (status 'D' seperti sampel produksi)
DECLARE @Waktu_Sesi datetime = '2026-09-29 13:20:30', @Id_Session int, @Id_Pembanding int;
INSERT INTO N_EMI_LAB_Palatabilitas_Session
    (Kode_Perusahaan, No_Faktur_Uji_Sampel, No_Po_Sampel, Kode_Aktivitas_Lab, Status_Session, Tanggal_Buat, Jam_Buat, Id_User_Buat, Kode_Role)
VALUES ('001', NULL, @No_Sampel, 'PLT', 'D', @Waktu_Sesi, CONVERT(varchar(8), @Waktu_Sesi, 108), @Input_PLT, 'LAB');
SET @Id_Session = SCOPE_IDENTITY();
INSERT INTO N_EMI_LAB_Palatabilitas_Pembanding
    (Id_Session, Kode_Perusahaan, Urutan, Kode_Barang_Pembanding, Nama_Pembanding, Tanggal, Jam, Id_User, Kode_Role, Flag_Aktif)
VALUES (@Id_Session, '001', 1, NULL, 'ORI CAT PURE TUNA EXISTING (PD 15/9/2026)', @Waktu_Sesi, CONVERT(varchar(8), @Waktu_Sesi, 108), @Input_PLT, 'LAB', 'Y');
SET @Id_Pembanding = SCOPE_IDENTITY();

-- ============================================================================
-- 3. RANCANGAN HASIL
-- ============================================================================
DECLARE @Analisa TABLE (
    Urut int PRIMARY KEY, Id_Jenis_Analisa int, Klas varchar(4), Sub int,
    Waktu_Input datetime, Waktu_Validasi datetime, Label varchar(255),
    Range_Awal float, Range_Akhir float, Flag_Foto char(1),
    Id_Perhitungan int, Hasil float, Flag_Layak char(1), No_Faktur varchar(30));
INSERT INTO @Analisa (Urut, Id_Jenis_Analisa, Klas, Sub, Waktu_Input, Waktu_Validasi, Label, Range_Awal, Range_Akhir, Flag_Foto, Id_Perhitungan) VALUES
    -- Look View (IIS -> Yusuf)
    ( 1, 73, 'LCKV', 2, '2026-09-28 08:30:10', '2026-09-28 14:10:22', 'Merah Kecoklatan',      NULL, NULL, 'Y', NULL),
    ( 2, 74, 'LCKV', 2, '2026-09-28 08:33:40', '2026-09-28 14:10:22', 'Harum Khas Daging',     NULL, NULL, 'T', NULL),
    ( 3, 72, 'LCKV', 2, '2026-09-28 08:36:05', '2026-09-28 14:10:22', 'Tidak Lengket di Pouch',NULL, NULL, 'T', NULL),
    -- Analisa Lab proksimat (FRANS -> SV_LAB)
    ( 4, 15, 'ANL',  1, '2026-09-28 10:05:20', '2026-09-30 11:02:15', NULL, 8,   13,  'T', 36),
    ( 5, 16, 'ANL',  1, '2026-09-28 10:09:45', '2026-09-30 11:02:40', NULL, 0,   3.5, 'T', 37),
    ( 6, 14, 'ANL',  1, '2026-09-28 10:14:30', '2026-09-30 11:03:05', NULL, 0,   82,  'T', 35),
    ( 7, 17, 'ANL',  1, '2026-09-28 10:18:55', '2026-09-30 11:03:30', NULL, 0.3, 1.2, 'T', 38),
    -- Palatabilitas (DIAH -> ROBY)
    ( 8, 71, 'PLT',  3, '2026-09-29 13:35:12', '2026-09-30 10:25:40', NULL, NULL, NULL, 'T', NULL),
    ( 9, 52, 'PLT',  3, '2026-09-29 13:38:40', '2026-09-30 10:25:40', NULL, NULL, NULL, 'T', NULL),
    (10, 87, 'PLT',  3, '2026-09-29 13:42:05', '2026-09-30 10:25:40', NULL, NULL, NULL, 'T', NULL),
    -- Mikrobiologi setelah inkubasi (FRANS -> SV_LAB)
    (11, 43, 'ANL',  1, '2026-09-30 09:10:00', '2026-10-01 10:12:30', NULL, 0, 500, 'T', 66),
    (12, 44, 'ANL',  1, '2026-09-30 09:13:30', '2026-10-01 10:13:00', NULL, 0, 100, 'T', 68),
    (13, 42, 'ANL',  1, '2026-09-30 09:17:10', '2026-10-01 10:13:30', NULL, 0, 10,  'T', 67),
    (14, 45, 'ANL',  1, '2026-09-30 16:05:00', '2026-10-01 10:14:00', '-',  NULL, NULL, 'T', NULL);

-- Parameter. Palatabilitas mengikuti pola produksi: 111 sampel, 112 kontrol,
-- 115 sampel vs kontrol (rasio); parameter lain boleh kosong.
DECLARE @Param TABLE (Urut int, Id_QC int, Nilai float NULL, No int);
INSERT INTO @Param VALUES
    (4, 71, 1.0000, 1), (4, 78, 4.80, 2),                                         -- PROTEIN
    (5, 50, 1, 1), (5, 69, 21.3500, 2), (5, 71, 5.0000, 3), (5, 89, 21.4630, 4),   -- ASH
    (6, 69, 24.1200, 1), (6, 71, 5.0000, 2), (6, 89, 25.2210, 3),                 -- MOISTURE
    (7, 71, 2.0000, 1), (7, 79, 3.30, 2),                                         -- SALT
    (11, 103, 250, 1), (11, 104, 241, 2),                                         -- AC
    (12, 103, 150, 1), (12, 104, 147, 2),                                         -- YM
    (13, 103, 120, 1), (13, 104, 120, 2),                                         -- EC
    (8, 111, 22, 1), (8, 112, 19, 2), (8, 113, 2, 3), (8, 114, 92, 4), (8, 115, 1.16, 5), (8, 116, NULL, 6), (8, 117, NULL, 7),
    (9, 111, 268, 1), (9, 112, 231, 2), (9, 113, NULL, 3), (9, 114, NULL, 4), (9, 115, 1.16, 5), (9, 116, NULL, 6), (9, 117, NULL, 7),
    (10, 111, 64.8, 1), (10, 112, 48.2, 2), (10, 113, NULL, 3), (10, 114, NULL, 4), (10, 115, 1.34, 5), (10, 116, NULL, 6), (10, 117, NULL, 7);

-- Hasil perhitungan (rumus master LAB, dibulatkan sesuai Hasil_Perhitungan)
UPDATE a SET Hasil = CASE a.Id_Jenis_Analisa
        WHEN 15 THEN ROUND((p.p78 - 0.2275) * 0.2457 * 8.754 / p.p71, 2)
        WHEN 16 THEN ROUND((p.p89 - p.p69) * 100 / p.p71, 4)
        WHEN 14 THEN ROUND((p.p69 + p.p71 - p.p89) * 100 / p.p71, 2)
        WHEN 17 THEN ROUND((5 - p.p79) * 5.845 * 0.1 / p.p71, 2)
        ELSE ROUND((p.p103 - p.p104) / p.p103 * 100, 2) END
FROM @Analisa a
CROSS APPLY (SELECT MAX(CASE WHEN Id_QC = 69 THEN Nilai END) p69, MAX(CASE WHEN Id_QC = 71 THEN Nilai END) p71,
                    MAX(CASE WHEN Id_QC = 78 THEN Nilai END) p78, MAX(CASE WHEN Id_QC = 79 THEN Nilai END) p79,
                    MAX(CASE WHEN Id_QC = 89 THEN Nilai END) p89, MAX(CASE WHEN Id_QC = 103 THEN Nilai END) p103,
                    MAX(CASE WHEN Id_QC = 104 THEN Nilai END) p104
             FROM @Param WHERE Urut = a.Urut) p
WHERE a.Id_Perhitungan IS NOT NULL;

UPDATE @Analisa SET Flag_Layak = CASE WHEN Hasil BETWEEN Range_Awal AND Range_Akhir THEN 'Y' ELSE 'T' END
WHERE Id_Perhitungan IS NOT NULL;

-- Hasil berkriteria (Look View & Salmonella): nilai kriteria LAB; batas = kriteria layak pertama.
UPDATE a SET Hasil = k.Nilai_Kriteria, Flag_Layak = k.Flag_Layak,
             Range_Awal = st.Nilai_Kriteria, Range_Akhir = st.Nilai_Kriteria
FROM @Analisa a
CROSS APPLY (SELECT TOP 1 Nilai_Kriteria, Flag_Layak FROM N_EMI_LAB_Standar_Rentang_Non_Perhitungan
             WHERE Id_Jenis_Analisa = a.Id_Jenis_Analisa AND Keterangan_Kriteria = a.Label AND Flag_Aktif = 'Y' AND Kode_Role = 'LAB'
             ORDER BY Id_Standar_Rentang_Non_Perhitungan) k
OUTER APPLY (SELECT TOP 1 Nilai_Kriteria FROM N_EMI_LAB_Standar_Rentang_Non_Perhitungan
             WHERE Id_Jenis_Analisa = a.Id_Jenis_Analisa AND Flag_Layak = 'Y' AND Flag_Aktif = 'Y' AND Kode_Role = 'LAB'
             ORDER BY Id_Standar_Rentang_Non_Perhitungan) st
WHERE a.Label IS NOT NULL;

UPDATE @Analisa SET Flag_Layak = 'Y' WHERE Klas = 'PLT';

IF EXISTS (SELECT 1 FROM @Analisa WHERE Flag_Layak IS NULL OR (Klas <> 'PLT' AND Hasil IS NULL))
    THROW 50007, N'Ada hasil uji yang tidak dapat disusun (kriteria / parameter tidak ditemukan).', 1;

-- Parameter berkriteria = nilai kriteria pada QC yang terikat.
INSERT INTO @Param (Urut, Id_QC, Nilai, No)
SELECT a.Urut, b.Id_Quality_Control, a.Hasil, 1
FROM @Analisa a
CROSS APPLY (SELECT TOP 1 Id_Quality_Control FROM N_EMI_LAB_Binding_Jenis_Analisa WHERE Id_Jenis_Analisa = a.Id_Jenis_Analisa ORDER BY id) b
WHERE a.Label IS NOT NULL;

-- Nomor faktur FUS0926-nnnn, melanjutkan nomor terakhir.
DECLARE @Faktur_Terakhir int = ISNULL((
    SELECT MAX(TRY_CAST(SUBSTRING(No_Faktur, 9, 10) AS int)) FROM N_EMI_LAB_Uji_Sampel WITH (UPDLOCK, HOLDLOCK)
    WHERE No_Faktur LIKE 'FUS0926-%'), 0);
WITH u AS (SELECT No_Faktur, ROW_NUMBER() OVER (ORDER BY Waktu_Input, Urut) n FROM @Analisa)
UPDATE u SET No_Faktur = 'FUS0926-' + RIGHT('0000' + CAST(@Faktur_Terakhir + n AS varchar(10)), 4);

-- ============================================================================
-- 4. HASIL UJI + PARAMETER + JEJAK KIRIM + FOTO
-- ============================================================================
INSERT INTO N_EMI_LAB_Uji_Sampel
    (Kode_Perusahaan, No_Faktur, No_Po_Sampel, No_Fak_Sub_Po, Id_Jenis_Analisa, Hasil, Flag_Perhitungan, Flag_Multi_QrCode,
     Status, Tanggal, Jam, Id_User, Flag_Selesai, Id_Perhitungan, Range_Awal, Range_Akhir, Tahapan_Ke, Flag_Resampling,
     Status_Keputusan_Sampel, Flag_Layak, Flag_Final, Id_Mesin, Flag_String, Nilai_Hasil_String, Flag_Foto, Id_Session, Id_Pembanding)
SELECT '001', a.No_Faktur, @No_Sampel, @No_Sampel + '-' + CAST(a.Sub AS varchar(2)), a.Id_Jenis_Analisa,
       CASE WHEN a.Klas = 'PLT' THEN p.Nilai ELSE a.Hasil END,
       CASE WHEN a.Id_Perhitungan IS NOT NULL THEN 'Y' END, 'Y',
       NULL, CAST(CAST(a.Waktu_Input AS date) AS datetime), CONVERT(varchar(8), a.Waktu_Input, 108),
       CASE a.Klas WHEN 'LCKV' THEN @Input_LV WHEN 'ANL' THEN @Input_ANL ELSE @Input_PLT END,
       'Y', a.Id_Perhitungan, a.Range_Awal, a.Range_Akhir, 1, NULL,
       'terima', a.Flag_Layak, NULL, @Id_Mesin,
       CASE WHEN a.Label IS NOT NULL THEN 'Y' END, a.Label, a.Flag_Foto,
       CASE WHEN a.Klas = 'PLT' THEN @Id_Session END, CASE WHEN a.Klas = 'PLT' THEN @Id_Pembanding END
FROM @Analisa a
LEFT JOIN @Param p ON p.Urut = a.Urut AND a.Klas = 'PLT'
ORDER BY a.No_Faktur, p.No;

INSERT INTO N_EMI_LAB_Uji_Sampel_Detail (Kode_Perusahaan, No_Faktur_Uji_Sample, Id_Quality_Control, Value_Parameter, Tanggal, Jam, Id_User)
SELECT '001', a.No_Faktur, p.Id_QC, p.Nilai, CAST(CAST(a.Waktu_Input AS date) AS datetime), CONVERT(varchar(8), a.Waktu_Input, 108),
       CASE a.Klas WHEN 'LCKV' THEN @Input_LV WHEN 'ANL' THEN @Input_ANL ELSE @Input_PLT END
FROM @Analisa a JOIN @Param p ON p.Urut = a.Urut
ORDER BY a.No_Faktur, p.No;

INSERT INTO N_EMI_LAB_Activity_Uji_Sampel (Kode_Perusahaan, No_Po_Sampel, No_Fak_Sub_Po, Jenis_Aktivitas, Id_Jenis_Analisa, Keterangan, Id_User, Tanggal, Jam)
SELECT '001', @No_Sampel, @No_Sampel + '-' + CAST(a.Sub AS varchar(2)), 'save_submit', a.Id_Jenis_Analisa,
       ISNULL(u.Nama, x.Id_User) + ' Berhasil Mengirimkan Data Analisa', x.Id_User,
       CAST(CAST(a.Waktu_Input AS date) AS datetime), CONVERT(varchar(8), a.Waktu_Input, 108)
FROM @Analisa a
CROSS APPLY (SELECT CASE a.Klas WHEN 'LCKV' THEN @Input_LV WHEN 'ANL' THEN @Input_ANL ELSE @Input_PLT END AS Id_User) x
LEFT JOIN N_EMI_LAB_Users u ON u.UserId = x.Id_User
ORDER BY a.Waktu_Input, a.Urut;

-- Foto WARNA: kunci berkas foto produk yang sudah ada (berkas tidak digandakan).
INSERT INTO N_EMI_LAB_Berkas_Uji_Lab (No_Faktur, No_Sampel, Berkas_Key, File_Path, Keterangan, Id_User, Tahapan_Ke, Dibuat_Pada)
SELECT a.No_Faktur, @No_Sampel, f.Berkas_Key, f.File_Path, f.Keterangan, @Input_LV, 1, a.Waktu_Input
FROM @Analisa a
CROSS APPLY (VALUES
    (1, 'c5Y2uCSXyrCVyoZPKinJW9tFPVRUwWlv', 'berkas/lab/labwHO7X_1780477883_1.png', 'Warna merah kecoklatan merata, permukaan mengkilap, tidak ada browning'),
    (2, '0mT7sIg18msehDFtm8CfdmibS9Grotrh', 'berkas/lab/labaZISF_1780392614_1.png', 'Perbandingan dengan produk existing: warna setara')
) AS f (No, Berkas_Key, File_Path, Keterangan)
WHERE a.Flag_Foto = 'Y'
ORDER BY f.No;

-- ============================================================================
-- 5. VALIDASI (Look View: Yusuf, Analisa Lab: SV_LAB, Palatabilitas: ROBY)
-- ============================================================================
DECLARE @Validasi TABLE (Urut int PRIMARY KEY, Id_User varchar(30), Waktu datetime);
INSERT INTO @Validasi
SELECT a.Urut, CASE a.Klas WHEN 'LCKV' THEN @Validasi_LV WHEN 'ANL' THEN @Validasi_ANL ELSE @Validasi_PLT END, a.Waktu_Validasi
FROM @Analisa a;

INSERT INTO N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
    (No_Sampel, Id_Jenis_Analisa, Tahapan_Ke, Tanggal, Jam, Flag_Layak, Flag_Resampling, Id_User, No_Sub_Sampel,
     Id_Uji_Validasi_Final, Kode_Aktivitas_Lab, Nama_Jenis_Analisa, Id_Session, Id_Pembanding, Sumber_Pencatatan, Dibuat_Pada)
SELECT @No_Sampel, a.Id_Jenis_Analisa, 1, CAST(CAST(v.Waktu AS date) AS datetime), CONVERT(varchar(8), v.Waktu, 108),
       a.Flag_Layak, NULL, v.Id_User, @No_Sampel + '-' + CAST(a.Sub AS varchar(2)),
       NULL, j.Kode_Aktivitas_Lab, j.Jenis_Analisa,
       CASE WHEN a.Klas = 'PLT' THEN @Id_Session END, CASE WHEN a.Klas = 'PLT' THEN @Id_Pembanding END,
       'VALIDASI', v.Waktu
FROM @Analisa a JOIN @Validasi v ON v.Urut = a.Urut JOIN N_EMI_LAB_Jenis_Analisa j ON j.id = a.Id_Jenis_Analisa
ORDER BY v.Waktu, a.Urut;

INSERT INTO N_EMI_LAB_Hasil_Uji_Approval_Aktivitas
    (No_Sampel, No_Sub_Sampel, No_Po, No_Split_Po, No_Batch, Kode_Barang, Kode_Aktivitas_Lab, Nama_Aktivitas,
     Id_Jenis_Analisa, Nama_Jenis_Analisa, Tahapan_Ke, Id_Session, Id_Pembanding, Id_User, Nama_User,
     Jenis_Approval, Flag_Approval, Flag_Layak, Keterangan, Tanggal, Jam, Dibuat_Pada, Flag_Trial_Produksi, Flag_Resampling, Sumber_Pencatatan)
SELECT @No_Sampel, @No_Sampel + '-' + CAST(a.Sub AS varchar(2)), @No_Po, @No_Split_Po, @No_Batch, @Kode_Barang,
       j.Kode_Aktivitas_Lab, k.Nama_Aktivitas, a.Id_Jenis_Analisa, j.Jenis_Analisa, 1,
       CASE WHEN a.Klas = 'PLT' THEN @Id_Session END, CASE WHEN a.Klas = 'PLT' THEN @Id_Pembanding END,
       v.Id_User, u.Nama, 'VALIDASI', 'Y', a.Flag_Layak, NULL,
       CAST(v.Waktu AS date), CONVERT(varchar(8), v.Waktu, 108), v.Waktu, 'Y', NULL, 'VALIDASI'
FROM @Analisa a
JOIN @Validasi v ON v.Urut = a.Urut
JOIN N_EMI_LAB_Jenis_Analisa j ON j.id = a.Id_Jenis_Analisa
LEFT JOIN N_EMI_LIMS_Klasifikasi_Aktivitas_Lab k ON k.Kode_Aktivitas_Lab = j.Kode_Aktivitas_Lab
LEFT JOIN N_EMI_LAB_Users u ON u.UserId = v.Id_User
ORDER BY v.Waktu, a.Urut;

-- Satu header Log_Aksi per sampel (dibuat validator pertama); tiap detail
-- mencatat validatornya sendiri — sama seperti aplikasi.
DECLARE @Pertama_User varchar(30), @Pertama_Waktu datetime, @Id_Log int;
SELECT TOP 1 @Pertama_User = Id_User, @Pertama_Waktu = Waktu FROM @Validasi ORDER BY Waktu, Urut;
INSERT INTO N_EMI_LAB_Log_Aksi (No_Sampel, No_Po, No_Split_Po, Kode_Barang, Flag_Trial, Jenis_Aksi, Sub_Aksi, Keterangan, Id_User, Tanggal, Jam)
VALUES (@No_Sampel, @No_Po, @No_Split_Po, @Kode_Barang, 'Y', 'VALIDASI_TRIAL_PRODUKSI', 'SETUJU', NULL,
        @Pertama_User, CAST(@Pertama_Waktu AS date), CAST(@Pertama_Waktu AS time(0)));
SET @Id_Log = SCOPE_IDENTITY();
INSERT INTO N_EMI_LAB_Log_Aksi_Detail (Id_Log_Aksi, Id_Jenis_Analisa, Nama_Jenis_Analisa, Flag_Layak, Tanggal, Jam, Id_User)
SELECT @Id_Log, a.Id_Jenis_Analisa, j.Jenis_Analisa, 'Y', CONVERT(varchar(10), v.Waktu, 23), CONVERT(varchar(8), v.Waktu, 108), v.Id_User
FROM @Analisa a JOIN @Validasi v ON v.Urut = a.Urut JOIN N_EMI_LAB_Jenis_Analisa j ON j.id = a.Id_Jenis_Analisa
ORDER BY v.Waktu, a.Urut;

-- ============================================================================
-- 6. FINALISASI PO TRIAL (VENGINE)
-- ============================================================================
DECLARE @Flag_Ok char(1) = CASE WHEN EXISTS (SELECT 1 FROM @Analisa WHERE Flag_Layak = 'T') THEN 'T' ELSE 'Y' END, @Id_Header int;

UPDATE N_EMI_LAB_Uji_Sampel SET Flag_Final = 'Y' WHERE No_Po_Sampel = @No_Sampel AND Status IS NULL AND Flag_Resampling IS NULL;

INSERT INTO N_EMI_LAB_Hasil_Uji_Validasi_Final (No_Po, No_Split_Po, No_Batch, No_Sampel, Tanggal, Jam, Flag_Ok, Id_User, Flag_FG)
VALUES (@No_Po, @No_Split_Po, @No_Batch, @No_Sampel, CAST(CAST(@Waktu_Final AS date) AS datetime), CONVERT(varchar(8), @Waktu_Final, 108),
        @Flag_Ok, @Finalisasi, 'Y');
SET @Id_Header = SCOPE_IDENTITY();

UPDATE N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final SET Id_Uji_Validasi_Final = @Id_Header
WHERE No_Sampel = @No_Sampel AND Id_Uji_Validasi_Final IS NULL;

INSERT INTO N_EMI_LAB_Hasil_Uji_Approval_Aktivitas
    (No_Sampel, No_Sub_Sampel, No_Po, No_Split_Po, No_Batch, Kode_Barang, Kode_Aktivitas_Lab, Nama_Aktivitas,
     Id_Jenis_Analisa, Nama_Jenis_Analisa, Tahapan_Ke, Id_Session, Id_Pembanding, Id_User, Nama_User,
     Jenis_Approval, Flag_Approval, Flag_Layak, Keterangan, Tanggal, Jam, Dibuat_Pada, Flag_Trial_Produksi, Flag_Resampling, Sumber_Pencatatan)
SELECT d.No_Sampel, d.No_Sub_Sampel, @No_Po, @No_Split_Po, @No_Batch, @Kode_Barang, d.Kode_Aktivitas_Lab, k.Nama_Aktivitas,
       d.Id_Jenis_Analisa, d.Nama_Jenis_Analisa, d.Tahapan_Ke, d.Id_Session, d.Id_Pembanding, @Finalisasi, u.Nama,
       'FINALISASI', 'Y', @Flag_Ok, NULL, CAST(@Waktu_Final AS date), CONVERT(varchar(8), @Waktu_Final, 108), @Waktu_Final, 'Y', NULL, 'VALIDASI'
FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d
LEFT JOIN N_EMI_LIMS_Klasifikasi_Aktivitas_Lab k ON k.Kode_Aktivitas_Lab = d.Kode_Aktivitas_Lab
LEFT JOIN N_EMI_LAB_Users u ON u.UserId = @Finalisasi
WHERE d.No_Sampel = @No_Sampel
ORDER BY d.Id_Uji_Validasi_Detail_Final;

UPDATE N_EMI_LAB_PO_Sampel SET Flag_Selesai = 'Y' WHERE No_Sampel = @No_Sampel AND Flag_Trial_Produksi = 'Y';

INSERT INTO N_EMI_LAB_Log_Aksi (No_Sampel, No_Po, No_Split_Po, Kode_Barang, Flag_Trial, Jenis_Aksi, Sub_Aksi, Keterangan, Id_User, Tanggal, Jam)
VALUES (@No_Sampel, @No_Po, @No_Split_Po, @Kode_Barang, 'Y', 'FINALISASI_TRIAL_PRODUKSI', 'CLOSE', NULL,
        @Finalisasi, CAST(@Waktu_Final AS date), CAST(@Waktu_Final AS time(0)));
SET @Id_Log = SCOPE_IDENTITY();
INSERT INTO N_EMI_LAB_Log_Aksi_Detail (Id_Log_Aksi, Id_Jenis_Analisa, Nama_Jenis_Analisa, Flag_Layak, Tanggal, Jam, Id_User)
SELECT @Id_Log, a.Id_Jenis_Analisa, j.Jenis_Analisa, NULL, CONVERT(varchar(10), @Waktu_Final, 23), CONVERT(varchar(8), @Waktu_Final, 108), @Finalisasi
FROM @Analisa a JOIN N_EMI_LAB_Jenis_Analisa j ON j.id = a.Id_Jenis_Analisa
ORDER BY a.Urut;

COMMIT TRANSACTION;

-- ============================================================================
-- RINGKASAN
-- ============================================================================
SELECT @No_Sampel AS No_Sampel, @No_Po AS No_Po, @No_Split_Po AS No_Split_Po, @Id_Header AS Id_Header_Final;

SELECT j.Kode_Aktivitas_Lab, j.Jenis_Analisa, MIN(U.No_Faktur) No_Faktur, MIN(U.No_Fak_Sub_Po) Sub, COUNT(*) Baris,
       MAX(U.Hasil) Hasil_Maks, MAX(U.Nilai_Hasil_String) Label, MAX(U.Range_Awal) Range_Awal, MAX(U.Range_Akhir) Range_Akhir,
       MAX(U.Flag_Layak) Flag_Layak, MAX(U.Id_User) Penginput, MAX(d.Id_User) Validator,
       (SELECT COUNT(*) FROM N_EMI_LAB_Berkas_Uji_Lab b WHERE b.No_Faktur = MIN(U.No_Faktur)) Foto
FROM N_EMI_LAB_Uji_Sampel U
JOIN N_EMI_LAB_Jenis_Analisa j ON j.id = U.Id_Jenis_Analisa
LEFT JOIN N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d ON d.No_Sampel = U.No_Po_Sampel AND d.Id_Jenis_Analisa = U.Id_Jenis_Analisa
WHERE U.No_Po_Sampel = @No_Sampel
GROUP BY j.Kode_Aktivitas_Lab, j.Jenis_Analisa
ORDER BY j.Kode_Aktivitas_Lab, MIN(U.No_Faktur);
