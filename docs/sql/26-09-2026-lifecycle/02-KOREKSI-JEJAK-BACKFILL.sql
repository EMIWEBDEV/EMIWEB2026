-- ============================================================================
-- 02-KOREKSI-JEJAK-BACKFILL.sql  —  Luruskan validator hasil backfill (DML)
-- Tanggal : 26-09-2026
-- Target  : database yang SUDAH menjalankan docs/sql/24-09-2026/02-BACKFILL.sql
--           VERSI LAMA (sebelum 26-09-2026). Per 26-09-2026 itu hanya staging
--           (emi_tm_demo). Production belum pernah menjalankannya, dan versi
--           02-BACKFILL yang sekarang sudah benar sejak awal — script ini
--           tetap aman dijalankan di sana (tidak akan menemukan apa pun).
-- Akun    : srv_lab_usr cukup (hanya UPDATE/DELETE).
--
-- MASALAH YANG DILURUSKAN:
--   02-BACKFILL versi lama mengisi kolom Id_User dengan N_EMI_LAB_Uji_Sampel
--   .Id_User. Kolom itu adalah PENGINPUT hasil (pembuat draf), bukan
--   validator. Akibatnya:
--     * N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final  (Sumber 'BACKFILL')
--       menyebut penginput sebagai yang memvalidasi, pada jam input.
--     * N_EMI_LAB_Hasil_Uji_Approval_Aktivitas     (VALIDASI, 'BACKFILL')
--       menyebut penginput sebagai yang MENYETUJUI.
--
-- SUMBER VALIDATOR YANG SAH (dibaca berurutan):
--   1. N_EMI_LAB_Log_Aksi + _Detail  (Jenis_Aksi 'VALIDASI%', Sub_Aksi 'SETUJU')
--      — dipakai catatan PALING AWAL yang terjadi SETELAH hasil diinput.
--   2. Detail_Final ber-Sumber 'PRA_MIGRASI' — ditulis kode lama saat
--      validasi, Id_User-nya validator (terbukti di production: 0 dari 3.000
--      baris terbaru sama dengan penginputnya).
--
-- TINDAKAN:
--   A. Detail_Final BACKFILL -> Id_User/Tanggal/Jam = validator dari sumber 1.
--      Bila validatornya tidak tercatat di mana pun: Id_User, Tanggal, dan Jam
--      dikosongkan. Barisnya TETAP ada (Flag_Layak-nya masih dipakai).
--   B. Approval VALIDASI BACKFILL -> Id_User/Nama_User/Tanggal/Jam diganti
--      validator dari sumber 1 lalu 2. Baris yang validatornya tidak tercatat
--      DIHAPUS: approval tanpa penyetuju yang diketahui bukan approval.
--      Baris yang akan menjadi kembar dengan approval yang sudah ada juga
--      dihapus (sudah terwakili).
--   Approval FINALISASI tidak disentuh — Id_User header finalisasi memang
--   yang memfinalisasi.
--
-- SIFAT: IDEMPOTEN; satu transaksi; tidak menyentuh baris selain BACKFILL.
-- PRASYARAT: 01-STRUKTUR-LIFECYCLE.sql (kunci unik approval + Tahapan_Ke).
-- ============================================================================

SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

PRINT '============================================================';
PRINT ' 02-KOREKSI-JEJAK-BACKFILL  —  ' + CONVERT(VARCHAR(19), GETDATE(), 120);
PRINT ' Database : ' + DB_NAME();
PRINT '============================================================';

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes i
    JOIN sys.index_columns ic ON ic.object_id = i.object_id AND ic.index_id = i.index_id
    JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
    WHERE i.object_id = OBJECT_ID('N_EMI_LAB_Hasil_Uji_Approval_Aktivitas')
      AND i.name = 'UX_ApprovalAktivitas_NonPlt' AND c.name = 'Tahapan_Ke')
BEGIN
    RAISERROR('Jalankan 01-STRUKTUR-LIFECYCLE.sql lebih dulu.', 16, 1);
    SET NOEXEC ON;
END
GO

-- Catatan validasi dari Log_Aksi, waktunya sudah digabung jadi satu kolom.
IF OBJECT_ID('tempdb..#Val') IS NOT NULL DROP TABLE #Val;
SELECT l.No_Sampel,
       d.Id_Jenis_Analisa,
       COALESCE(NULLIF(LTRIM(RTRIM(d.Id_User)), ''), l.Id_User) AS Id_User,
       CAST(COALESCE(TRY_CONVERT(DATE, d.Tanggal), l.Tanggal) AS DATETIME)
         + CAST(COALESCE(TRY_CONVERT(TIME(0), d.Jam), CAST(l.Jam AS TIME(0))) AS DATETIME) AS Waktu
INTO #Val
FROM N_EMI_LAB_Log_Aksi l
JOIN N_EMI_LAB_Log_Aksi_Detail d ON d.Id_Log_Aksi = l.Id_Log_Aksi
WHERE l.Jenis_Aksi LIKE 'VALIDASI%'
  AND l.Sub_Aksi = 'SETUJU'
  AND COALESCE(NULLIF(LTRIM(RTRIM(d.Id_User)), ''), l.Id_User) IS NOT NULL;
CREATE INDEX IX_Val ON #Val (No_Sampel, Id_Jenis_Analisa, Waktu);

-- Validator pra-migrasi dari Detail_Final.
IF OBJECT_ID('tempdb..#Pra') IS NOT NULL DROP TABLE #Pra;
SELECT No_Sampel,
       ISNULL(No_Sub_Sampel, '') AS Sub,
       Id_Jenis_Analisa,
       ISNULL(Tahapan_Ke, 1) AS Tahapan_Ke,
       Id_User,
       CAST(CAST(Tanggal AS DATE) AS DATETIME) + CAST(TRY_CONVERT(TIME(0), Jam) AS DATETIME) AS Waktu
INTO #Pra
FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
WHERE Sumber_Pencatatan = 'PRA_MIGRASI' AND Id_User IS NOT NULL;
CREATE INDEX IX_Pra ON #Pra (No_Sampel, Id_Jenis_Analisa, Sub, Tahapan_Ke);

DECLARE @nVal INT = (SELECT COUNT(*) FROM #Val);
DECLARE @nPra INT = (SELECT COUNT(*) FROM #Pra);
PRINT 'Sumber validator: ' + CAST(@nVal AS VARCHAR(12)) + ' catatan Log_Aksi, '
    + CAST(@nPra AS VARCHAR(12)) + ' baris PRA_MIGRASI.';

BEGIN TRANSACTION;

-- ============================================================================
-- A. Detail_Final BACKFILL
-- ============================================================================
PRINT '--- A. Detail_Final BACKFILL ---';

UPDATE d
   SET d.Id_User = v.Id_User,
       d.Tanggal = CASE WHEN v.Waktu IS NULL THEN NULL ELSE CAST(CAST(v.Waktu AS DATE) AS DATETIME) END,
       d.Jam     = CASE WHEN v.Waktu IS NULL THEN NULL ELSE CONVERT(VARCHAR(8), v.Waktu, 108) END
FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d
OUTER APPLY (
    SELECT TOP 1 x.Id_User, x.Waktu
    FROM #Val x
    WHERE x.No_Sampel = d.No_Sampel
      AND x.Id_Jenis_Analisa = d.Id_Jenis_Analisa
      AND x.Waktu >= CAST(CAST(d.Tanggal AS DATE) AS DATETIME) + CAST(TRY_CONVERT(TIME(0), d.Jam) AS DATETIME)
    ORDER BY x.Waktu
) v
WHERE d.Sumber_Pencatatan = 'BACKFILL'
  AND (ISNULL(d.Id_User, '~') <> ISNULL(v.Id_User, '~')
       OR ISNULL(CONVERT(VARCHAR(8), d.Jam), '~') <> ISNULL(CONVERT(VARCHAR(8), v.Waktu, 108), '~'));

PRINT '  [A] ' + CAST(@@ROWCOUNT AS VARCHAR(12)) + ' baris diluruskan (validator asli atau dikosongkan).';

-- ============================================================================
-- B. Approval VALIDASI BACKFILL
-- ============================================================================
PRINT '--- B. Approval VALIDASI BACKFILL ---';

IF OBJECT_ID('tempdb..#Koreksi') IS NOT NULL DROP TABLE #Koreksi;
SELECT a.Id_Approval_Aktivitas,
       a.No_Sampel,
       ISNULL(a.No_Sub_Sampel, '~')   AS Sub,
       a.Id_Jenis_Analisa,
       ISNULL(a.Tahapan_Ke, 1)        AS Tahapan_Ke,
       ISNULL(a.Id_Pembanding, -1)    AS Pembanding,
       a.Id_User                      AS User_Lama,
       v.Id_User                      AS Validator,
       v.Waktu
INTO #Koreksi
FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas a
OUTER APPLY (
    SELECT TOP 1 x.Id_User, x.Waktu
    FROM (
        SELECT Id_User, Waktu, 1 AS Prio
        FROM #Val
        WHERE No_Sampel = a.No_Sampel
          AND Id_Jenis_Analisa = a.Id_Jenis_Analisa
          AND Waktu >= CAST(a.Tanggal AS DATETIME) + CAST(TRY_CONVERT(TIME(0), a.Jam) AS DATETIME)
        UNION ALL
        SELECT Id_User, Waktu, 2
        FROM #Pra
        WHERE No_Sampel = a.No_Sampel
          AND Id_Jenis_Analisa = a.Id_Jenis_Analisa
          AND Sub = ISNULL(a.No_Sub_Sampel, '')
    ) x
    ORDER BY x.Prio, x.Waktu
) v
WHERE a.Sumber_Pencatatan = 'BACKFILL'
  AND a.Jenis_Approval = 'VALIDASI';

-- B1. Validator tidak tercatat -> bukan approval.
DELETE a
FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas a
JOIN #Koreksi k ON k.Id_Approval_Aktivitas = a.Id_Approval_Aktivitas
WHERE k.Validator IS NULL;
PRINT '  [B1] ' + CAST(@@ROWCOUNT AS VARCHAR(12)) + ' approval tanpa validator tercatat dihapus.';

-- B2. Akan kembar dengan approval lain (yang realtime, atau sesama backfill
--     yang ternyata validatornya sama) -> hapus, sisakan satu.
;WITH Urut AS (
    SELECT k.Id_Approval_Aktivitas,
           ROW_NUMBER() OVER (PARTITION BY k.No_Sampel, k.Sub, k.Id_Jenis_Analisa, k.Tahapan_Ke,
                                           k.Pembanding, k.Validator
                              ORDER BY k.Id_Approval_Aktivitas) AS rn
    FROM #Koreksi k
    WHERE k.Validator IS NOT NULL
)
DELETE a
FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas a
JOIN #Koreksi k ON k.Id_Approval_Aktivitas = a.Id_Approval_Aktivitas
JOIN Urut u ON u.Id_Approval_Aktivitas = k.Id_Approval_Aktivitas
WHERE k.Validator IS NOT NULL
  AND (u.rn > 1
       OR EXISTS (SELECT 1 FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas o
                  WHERE o.Id_Approval_Aktivitas <> a.Id_Approval_Aktivitas
                    AND o.No_Sampel = a.No_Sampel
                    AND ISNULL(o.No_Sub_Sampel, '~') = k.Sub
                    AND o.Id_Jenis_Analisa = a.Id_Jenis_Analisa
                    AND ISNULL(o.Tahapan_Ke, 1) = k.Tahapan_Ke
                    AND o.Jenis_Approval = 'VALIDASI'
                    AND o.Id_User = k.Validator
                    AND ISNULL(o.Id_Pembanding, -1) = k.Pembanding));
PRINT '  [B2] ' + CAST(@@ROWCOUNT AS VARCHAR(12)) + ' approval yang akan kembar dihapus.';

-- B3. Sisanya diganti ke validator yang sebenarnya.
UPDATE a
   SET a.Id_User   = k.Validator,
       a.Nama_User = us.Nama,
       a.Tanggal   = CAST(k.Waktu AS DATE),
       a.Jam       = CONVERT(VARCHAR(8), k.Waktu, 108)
FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas a
JOIN #Koreksi k ON k.Id_Approval_Aktivitas = a.Id_Approval_Aktivitas
LEFT JOIN N_EMI_LAB_Users us ON us.UserId = k.Validator
WHERE k.Validator IS NOT NULL
  AND (a.Id_User <> k.Validator
       OR ISNULL(a.Jam, '~') <> CONVERT(VARCHAR(8), k.Waktu, 108)
       OR ISNULL(CONVERT(VARCHAR(10), a.Tanggal, 120), '~') <> CONVERT(VARCHAR(10), k.Waktu, 120));
PRINT '  [B3] ' + CAST(@@ROWCOUNT AS VARCHAR(12)) + ' approval diganti ke validator asli.';

COMMIT TRANSACTION;

-- ============================================================================
-- Verifikasi — hasil backfill tidak boleh lagi menyebut penginput sebagai
-- validator kecuali orangnya memang sama menurut Log_Aksi / PRA_MIGRASI.
-- ============================================================================
PRINT '';
PRINT '--- Verifikasi ---';
SELECT 'Detail_Final BACKFILL' AS Bagian,
       COUNT(*) AS Total,
       SUM(CASE WHEN Id_User IS NULL THEN 1 ELSE 0 END) AS Validator_Tidak_Tercatat,
       SUM(CASE WHEN Id_User IS NOT NULL THEN 1 ELSE 0 END) AS Validator_Asli
FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
WHERE Sumber_Pencatatan = 'BACKFILL'
UNION ALL
SELECT 'Approval VALIDASI BACKFILL', COUNT(*), 0, COUNT(*)
FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas
WHERE Sumber_Pencatatan = 'BACKFILL' AND Jenis_Approval = 'VALIDASI';

PRINT '============================================================';
PRINT ' 02-KOREKSI-JEJAK-BACKFILL SELESAI';
PRINT '============================================================';
GO
SET NOEXEC OFF;
GO
