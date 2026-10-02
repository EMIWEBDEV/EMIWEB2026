-- ============================================================================
-- 14-CONTOH-LIFECYCLE-RESAMPLING.sql  —  Rapikan contoh resampling (DEMO SAJA)
-- Tanggal : 26-09-2026
-- Target  : HANYA staging (emi_tm_demo). Menolak berjalan di emi_db_real.
--
-- MASALAH:
--   Contoh resampling dari 05-CONTOH-SPESIFIKASI-RESAMPLING.sql dan
--   07-CONTOH-ANTREAN-VERIFIKASI.sql tidak mungkin terjadi di aplikasi:
--     * log resampling bernomor multi QR (FS…-1 -> FS…-2), padahal baris
--       ujinya sampel tunggal (No_Fak_Sub_Po NULL);
--     * SEMUA analisa bertanda Tahapan_Ke = 2 dan Flag_Resampling = 'Y',
--       padahal yang diresampling hanya ASH;
--     * hasil putaran 1 (yang ditolak) tidak ada, dan hasil putaran 2
--       tercatat SEBELUM resampling diminta.
--   Sample Lifecycle menampilkannya apa adanya — "data putaran tidak
--   ditemukan" — sehingga contohnya tidak berguna untuk peragaan.
--
-- TINDAKAN (FS0926-0004, -0005, -0009, -0012, -0015; analisa ASH = 16):
--   1. Log resampling  -> sampel tunggal (asal = tujuan = nomor sampel),
--                         + Alasan yang diketik validator.
--   2. Analisa selain ASH -> putaran 1 (Tahapan_Ke 1, Flag_Resampling NULL).
--   3. ASH putaran 1   -> DITAMBAHKAN: hasil di luar rentang, ditolak
--                         (Status 'tolak', Flag_Resampling 'Y'), diinput
--                         sebelum resampling diminta.
--   4. ASH putaran 2   -> baris yang ada, jamnya digeser SESUDAH resampling.
--   5. Jejak validasi  -> approval VALIDASI realtime oleh TEST - SPV LAB
--                         untuk putaran 1 (analisa yang diterima) dan
--                         putaran 2 (ASH).
--
-- IDEMPOTEN: setiap langkah memeriksa keadaan sebelumnya.
-- ROLLBACK: lihat bagian paling bawah (dikomentari).
-- ============================================================================

SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

IF DB_NAME() = 'emi_db_real'
BEGIN
    RAISERROR('Script contoh ini HANYA untuk staging. Dibatalkan.', 16, 1);
    SET NOEXEC ON;
END
GO

PRINT '14-CONTOH-LIFECYCLE-RESAMPLING  —  ' + DB_NAME();

DECLARE @sampel TABLE (No_Sampel VARCHAR(30) PRIMARY KEY, Hasil_Tolak FLOAT);
INSERT INTO @sampel VALUES
    ('FS0926-0004', 23.4),
    ('FS0926-0005', 44.1),
    ('FS0926-0009', 21.7),
    ('FS0926-0012', 26.0),
    ('FS0926-0015', 22.9);

DECLARE @ASH INT = 16;
DECLARE @validator VARCHAR(30) = 'SV_LAB';
DECLARE @namaValidator VARCHAR(255) = (SELECT Nama FROM N_EMI_LAB_Users WHERE UserId = @validator);

BEGIN TRANSACTION;

-- 1. Log resampling: sampel tunggal + alasan.
UPDATE r
   SET r.No_Sampel_Resampling_Origin = r.No_Po_Sampel,
       r.No_Sampel_Resampling        = r.No_Po_Sampel,
       r.Keterangan                  = 'Reanalisa tanpa multi QR (sampel sama)',
       r.Alasan = ISNULL(r.Alasan, 'Hasil ASH di luar rentang standar 0–20%. Duplo analis konsisten; '
                                 + 'uji ulang dari sampel yang sama untuk memastikan.'),
       r.Dibuat_Pada = ISNULL(r.Dibuat_Pada, CAST(CAST(r.Tanggal AS DATE) AS DATETIME) + CAST(CAST(r.Jam AS TIME(0)) AS DATETIME))
FROM N_EMI_LAB_Uji_Sampel_Resampling_Log r
JOIN @sampel s ON s.No_Sampel = r.No_Po_Sampel
WHERE r.Id_Jenis_Analisa = @ASH;
PRINT '  [1] log resampling: ' + CAST(@@ROWCOUNT AS VARCHAR(10));

-- 2. Analisa selain ASH kembali ke putaran 1.
UPDATE u
   SET u.Tahapan_Ke = 1, u.Flag_Resampling = NULL
FROM N_EMI_LAB_Uji_Sampel u
JOIN @sampel s ON s.No_Sampel = u.No_Po_Sampel
WHERE u.Id_Jenis_Analisa <> @ASH
  AND (u.Tahapan_Ke <> 1 OR u.Flag_Resampling IS NOT NULL);
PRINT '  [2] analisa non-ASH ke putaran 1: ' + CAST(@@ROWCOUNT AS VARCHAR(10));

-- 3. ASH putaran 1 yang ditolak (bila belum ada).
DECLARE @no INT = ISNULL((SELECT MAX(TRY_CAST(SUBSTRING(No_Faktur, 9, 10) AS INT))
                          FROM N_EMI_LAB_Uji_Sampel WHERE No_Faktur LIKE 'FUS0926-%'), 0);

INSERT INTO N_EMI_LAB_Uji_Sampel (
    Kode_Perusahaan, No_Faktur, No_Po_Sampel, No_Fak_Sub_Po, Id_Jenis_Analisa, Hasil,
    Flag_Perhitungan, Flag_Multi_QrCode, Status, Tanggal, Jam, Id_User, Flag_Selesai,
    Id_Perhitungan, Range_Awal, Range_Akhir, Tahapan_Ke, Flag_Resampling,
    Status_Keputusan_Sampel, Flag_Layak, Flag_Final, Id_Mesin)
SELECT u.Kode_Perusahaan,
       'FUS0926-' + RIGHT('0000' + CAST(@no + ROW_NUMBER() OVER (ORDER BY u.No_Po_Sampel) AS VARCHAR(10)), 4),
       u.No_Po_Sampel, NULL, u.Id_Jenis_Analisa, s.Hasil_Tolak,
       u.Flag_Perhitungan, u.Flag_Multi_QrCode, NULL, u.Tanggal, u.Jam, u.Id_User, NULL,
       u.Id_Perhitungan, u.Range_Awal, u.Range_Akhir, 1, 'Y',
       'tolak', 'T', NULL, u.Id_Mesin
FROM N_EMI_LAB_Uji_Sampel u
JOIN @sampel s ON s.No_Sampel = u.No_Po_Sampel
WHERE u.Id_Jenis_Analisa = @ASH
  AND u.Tahapan_Ke = 2
  AND NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Uji_Sampel x
                  WHERE x.No_Po_Sampel = u.No_Po_Sampel AND x.Id_Jenis_Analisa = @ASH AND x.Tahapan_Ke = 1);
PRINT '  [3] ASH putaran 1 (ditolak) ditambahkan: ' + CAST(@@ROWCOUNT AS VARCHAR(10));

-- 4. ASH putaran 2 diinput 25 menit SESUDAH resampling diminta.
UPDATE u
   SET u.Jam = CONVERT(VARCHAR(8), DATEADD(MINUTE, 25, CAST(r.Jam AS TIME(0))), 108),
       u.Flag_Resampling = NULL
FROM N_EMI_LAB_Uji_Sampel u
JOIN @sampel s ON s.No_Sampel = u.No_Po_Sampel
JOIN N_EMI_LAB_Uji_Sampel_Resampling_Log r
  ON r.No_Po_Sampel = u.No_Po_Sampel AND r.Id_Jenis_Analisa = @ASH AND r.Tahapan_Ke = 2
WHERE u.Id_Jenis_Analisa = @ASH
  AND u.Tahapan_Ke = 2
  AND CAST(u.Jam AS TIME(0)) < CAST(r.Jam AS TIME(0));
PRINT '  [4] ASH putaran 2 digeser sesudah resampling: ' + CAST(@@ROWCOUNT AS VARCHAR(10));

-- 5. Jejak validasi realtime (approval + detail) oleh validator contoh.
--    Putaran 1: analisa yang diterima, 2 menit sebelum resampling diminta.
--    Putaran 2: ASH, 10 menit sesudah hasil ulang diinput.
;WITH Target AS (
    SELECT u.No_Po_Sampel, u.Id_Jenis_Analisa, u.Tahapan_Ke, u.Flag_Layak,
           CASE WHEN u.Tahapan_Ke = 1
                THEN DATEADD(MINUTE, -2, CAST(CAST(r.Tanggal AS DATE) AS DATETIME) + CAST(CAST(r.Jam AS TIME(0)) AS DATETIME))
                ELSE DATEADD(MINUTE, 10, CAST(CAST(u.Tanggal AS DATE) AS DATETIME) + CAST(CAST(u.Jam AS TIME(0)) AS DATETIME))
           END AS Waktu
    FROM N_EMI_LAB_Uji_Sampel u
    JOIN @sampel s ON s.No_Sampel = u.No_Po_Sampel
    JOIN N_EMI_LAB_Uji_Sampel_Resampling_Log r
      ON r.No_Po_Sampel = u.No_Po_Sampel AND r.Id_Jenis_Analisa = @ASH AND r.Tahapan_Ke = 2
    WHERE u.Flag_Selesai = 'Y'
      AND ISNULL(u.Status_Keputusan_Sampel, '') <> 'tolak'
)
INSERT INTO N_EMI_LAB_Hasil_Uji_Approval_Aktivitas (
    No_Sampel, No_Sub_Sampel, No_Po, No_Split_Po, No_Batch, Kode_Barang,
    Kode_Aktivitas_Lab, Nama_Aktivitas, Id_Jenis_Analisa, Nama_Jenis_Analisa, Tahapan_Ke,
    Id_User, Nama_User, Jenis_Approval, Flag_Approval, Flag_Layak,
    Tanggal, Jam, Dibuat_Pada, Flag_Trial_Produksi, Sumber_Pencatatan)
SELECT t.No_Po_Sampel, NULL, po.No_Po, po.No_Split_Po, po.No_Batch, po.Kode_Barang,
       ja.Kode_Aktivitas_Lab, k.Nama_Aktivitas, t.Id_Jenis_Analisa, ja.Jenis_Analisa, t.Tahapan_Ke,
       @validator, @namaValidator, 'VALIDASI', 'Y', ISNULL(t.Flag_Layak, 'Y'),
       CAST(t.Waktu AS DATE), CONVERT(VARCHAR(8), t.Waktu, 108), t.Waktu, po.Flag_Trial_Produksi, 'VALIDASI'
FROM Target t
JOIN N_EMI_LAB_Jenis_Analisa ja ON ja.id = t.Id_Jenis_Analisa
LEFT JOIN N_EMI_LIMS_Klasifikasi_Aktivitas_Lab k ON k.Kode_Aktivitas_Lab = ja.Kode_Aktivitas_Lab
LEFT JOIN N_EMI_LAB_PO_Sampel po ON po.No_Sampel = t.No_Po_Sampel
WHERE NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas a
                  WHERE a.No_Sampel = t.No_Po_Sampel AND a.No_Sub_Sampel IS NULL
                    AND a.Id_Jenis_Analisa = t.Id_Jenis_Analisa AND ISNULL(a.Tahapan_Ke, 1) = t.Tahapan_Ke
                    AND a.Jenis_Approval = 'VALIDASI' AND a.Id_User = @validator
                    AND a.Id_Pembanding IS NULL);
PRINT '  [5] approval validasi contoh: ' + CAST(@@ROWCOUNT AS VARCHAR(10));

COMMIT TRANSACTION;

-- Verifikasi
SELECT u.No_Po_Sampel, u.Id_Jenis_Analisa, u.Tahapan_Ke, u.Hasil, u.Status_Keputusan_Sampel,
       u.Flag_Selesai, u.Flag_Resampling, u.Jam
FROM N_EMI_LAB_Uji_Sampel u
WHERE u.No_Po_Sampel IN ('FS0926-0004', 'FS0926-0005', 'FS0926-0009', 'FS0926-0012', 'FS0926-0015')
  AND u.Id_Jenis_Analisa = 16
ORDER BY u.No_Po_Sampel, u.Tahapan_Ke;
GO
SET NOEXEC OFF;
GO

-- ============================================================================
-- ROLLBACK (jalankan manual bila perlu mengembalikan contoh lama)
-- ============================================================================
-- DELETE FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas
--  WHERE Sumber_Pencatatan = 'VALIDASI' AND Id_User = 'SV_LAB'
--    AND No_Sampel IN ('FS0926-0004','FS0926-0005','FS0926-0009','FS0926-0012','FS0926-0015');
-- DELETE FROM N_EMI_LAB_Uji_Sampel
--  WHERE No_Po_Sampel IN ('FS0926-0004','FS0926-0005','FS0926-0009','FS0926-0012','FS0926-0015')
--    AND Id_Jenis_Analisa = 16 AND Tahapan_Ke = 1 AND Status_Keputusan_Sampel = 'tolak';
-- (Tahapan_Ke / Flag_Resampling / Jam lama: lihat cadangan scratchpad sebelum-14.json)
