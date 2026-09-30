-- ============================================================================
-- 00-PRACHECK.sql  —  READ ONLY. Tidak mengubah apa pun.
-- Tanggal : 24-09-2026
-- Target  : PRODUCTION (emi_db_real) maupun staging (emi_tm_demo)
--
-- Jalankan lebih dulu untuk memotret kondisi awal dan memastikan skrip
-- berikutnya tidak akan gagal. Simpan hasilnya sebagai bukti before-state.
--
-- ATURAN BACA:
--   Kolom HASIL berisi "LULUS"  -> aman lanjut.
--   Kolom HASIL berisi "STOP"   -> jangan lanjut, selesaikan dulu penyebabnya.
--   Kolom HASIL berisi "INFO"   -> hanya informasi, tidak memblokir.
--
-- Aman dijalankan kapan pun, berapa kali pun.
-- ============================================================================

SET NOCOUNT ON;

PRINT '============================================================';
PRINT ' PRACHECK  —  ' + CONVERT(VARCHAR(19), GETDATE(), 120);
PRINT ' Database : ' + DB_NAME();
PRINT ' Server   : ' + CONVERT(VARCHAR(128), SERVERPROPERTY('ServerName'));
PRINT '============================================================';


-- ----------------------------------------------------------------------------
-- 1. Environment
-- ----------------------------------------------------------------------------
PRINT '';
PRINT '--- 1. Environment ---';
SELECT
    DB_NAME()                                        AS Database_Name,
    DATABASEPROPERTYEX(DB_NAME(), 'Collation')       AS Collation,
    SERVERPROPERTY('ProductMajorVersion')            AS SQL_Major,
    SERVERPROPERTY('Edition')                        AS Edition,
    (SELECT recovery_model_desc FROM sys.databases WHERE name = DB_NAME()) AS Recovery_Model,
    CASE WHEN CAST(SERVERPROPERTY('ProductMajorVersion') AS INT) >= 13
         THEN 'LULUS' ELSE 'STOP — butuh SQL Server 2016+' END AS HASIL;


-- ----------------------------------------------------------------------------
-- 2. Tabel pendukung — semua harus ADA
-- ----------------------------------------------------------------------------
PRINT '';
PRINT '--- 2. Tabel pendukung ---';
SELECT
    t.Nama_Tabel,
    CASE WHEN OBJECT_ID(t.Nama_Tabel, 'U') IS NULL
         THEN 'STOP — tabel tidak ditemukan' ELSE 'LULUS' END AS HASIL
FROM (VALUES
    ('N_EMI_LAB_Hasil_Uji_Validasi_Final'),
    ('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final'),
    ('N_EMI_LAB_Uji_Sampel'),
    ('N_EMI_LAB_Jenis_Analisa'),
    ('N_EMI_LAB_PO_Sampel'),
    ('N_EMI_LAB_Users'),
    ('N_EMI_LIMS_Klasifikasi_Aktivitas_Lab')
) AS t(Nama_Tabel);


-- ----------------------------------------------------------------------------
-- 3. Objek yang akan dibuat 01-STRUKTUR.sql
--    "sudah ada" berarti skrip pernah dijalankan — itu normal dan aman.
-- ----------------------------------------------------------------------------
PRINT '';
PRINT '--- 3. Rencana perubahan struktur ---';
SELECT Objek, Status FROM (
    SELECT 1 AS Urut, 'TABEL  N_EMI_LAB_Hasil_Uji_Approval_Aktivitas' AS Objek,
           CASE WHEN OBJECT_ID('N_EMI_LAB_Hasil_Uji_Approval_Aktivitas','U') IS NULL
                THEN 'akan DIBUAT' ELSE 'sudah ada (dilewati)' END AS Status
    UNION ALL SELECT 2, 'KOLOM  Detail_Final.Id_Uji_Validasi_Final',
           CASE WHEN COL_LENGTH('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','Id_Uji_Validasi_Final') IS NULL
                THEN 'akan DITAMBAH' ELSE 'sudah ada (dilewati)' END
    UNION ALL SELECT 3, 'KOLOM  Detail_Final.Kode_Aktivitas_Lab',
           CASE WHEN COL_LENGTH('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','Kode_Aktivitas_Lab') IS NULL
                THEN 'akan DITAMBAH' ELSE 'sudah ada (dilewati)' END
    UNION ALL SELECT 4, 'KOLOM  Detail_Final.Nama_Jenis_Analisa',
           CASE WHEN COL_LENGTH('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','Nama_Jenis_Analisa') IS NULL
                THEN 'akan DITAMBAH' ELSE 'sudah ada (dilewati)' END
    UNION ALL SELECT 5, 'KOLOM  Detail_Final.Id_Session',
           CASE WHEN COL_LENGTH('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','Id_Session') IS NULL
                THEN 'akan DITAMBAH' ELSE 'sudah ada (dilewati)' END
    UNION ALL SELECT 6, 'KOLOM  Detail_Final.Id_Pembanding',
           CASE WHEN COL_LENGTH('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','Id_Pembanding') IS NULL
                THEN 'akan DITAMBAH' ELSE 'sudah ada (dilewati)' END
    UNION ALL SELECT 7, 'KOLOM  Detail_Final.Flag_Resampling',
           CASE WHEN COL_LENGTH('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','Flag_Resampling') IS NULL
                THEN 'akan DITAMBAH' ELSE 'sudah ada (dilewati)' END
    UNION ALL SELECT 8, 'KOLOM  Detail_Final.Sumber_Pencatatan',
           CASE WHEN COL_LENGTH('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','Sumber_Pencatatan') IS NULL
                THEN 'akan DITAMBAH' ELSE 'sudah ada (dilewati)' END
    UNION ALL SELECT 9, 'KOLOM  Detail_Final.Dibuat_Pada',
           CASE WHEN COL_LENGTH('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','Dibuat_Pada') IS NULL
                THEN 'akan DITAMBAH' ELSE 'sudah ada (dilewati)' END
    UNION ALL SELECT 10, 'INDEX  UX_HasilUjiValidasiFinal_SplitPo_Batch_Sampel',
           CASE WHEN NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='UX_HasilUjiValidasiFinal_SplitPo_Batch_Sampel')
                THEN 'akan DIBUAT (UNIQUE)' ELSE 'sudah ada (dilewati)' END
) x ORDER BY Urut;


-- ----------------------------------------------------------------------------
-- 4. PEMERIKSAAN PEMBLOKIR — wajib LULUS sebelum 01-STRUKTUR.sql
--
--    UNIQUE index pada (No_Split_Po, No_Batch, No_Sampel) akan GAGAL bila
--    sudah ada kombinasi ganda. Itulah satu-satunya hal yang benar-benar
--    memblokir migrasi ini.
-- ----------------------------------------------------------------------------
PRINT '';
PRINT '--- 4. Pemeriksaan pemblokir ---';
SELECT
    'Duplikat header (No_Split_Po, No_Batch, No_Sampel)' AS Pemeriksaan,
    ISNULL(SUM(x.N), 0)                                  AS Jumlah_Baris_Berlebih,
    CASE WHEN ISNULL(SUM(x.N), 0) = 0
         THEN 'LULUS' ELSE 'STOP — UNIQUE index akan gagal' END AS HASIL
FROM (
    SELECT COUNT(*) - 1 AS N
    FROM N_EMI_LAB_Hasil_Uji_Validasi_Final
    GROUP BY No_Split_Po, No_Batch, No_Sampel
    HAVING COUNT(*) > 1
) x;

-- Rincian bila ada (kosong = aman)
SELECT TOP 20 No_Split_Po, No_Batch, No_Sampel, COUNT(*) AS Jumlah_Baris
FROM N_EMI_LAB_Hasil_Uji_Validasi_Final
GROUP BY No_Split_Po, No_Batch, No_Sampel
HAVING COUNT(*) > 1
ORDER BY COUNT(*) DESC;


-- ----------------------------------------------------------------------------
-- 5. Pemeriksaan informatif — tidak memblokir
-- ----------------------------------------------------------------------------
PRINT '';
PRINT '--- 5. Informasi (tidak memblokir) ---';
SELECT 'Satu No_Sampel dipakai >1 header' AS Pemeriksaan,
       COUNT(*) AS Jumlah,
       CASE WHEN COUNT(*) = 0 THEN 'LULUS'
            ELSE 'INFO — backfill FK akan melewati sampel ini' END AS HASIL
FROM (SELECT No_Sampel FROM N_EMI_LAB_Hasil_Uji_Validasi_Final
      GROUP BY No_Sampel HAVING COUNT(*) > 1) a

UNION ALL
SELECT 'Satu (No_Split_Po,No_Batch) dipakai banyak sampel',
       COUNT(*),
       CASE WHEN COUNT(*) = 0 THEN 'LULUS'
            ELSE 'INFO — jejak bug updateOrInsert lama' END
FROM (SELECT No_Split_Po, No_Batch FROM N_EMI_LAB_Hasil_Uji_Validasi_Final
      GROUP BY No_Split_Po, No_Batch HAVING COUNT(DISTINCT No_Sampel) > 1) b

UNION ALL
SELECT 'Kelompok duplikat di Detail_Final',
       COUNT(*),
       'INFO — index detail sengaja NON-unique, tidak memblokir'
FROM (SELECT No_Sampel, No_Sub_Sampel, Id_Jenis_Analisa, Tahapan_Ke
      FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
      GROUP BY No_Sampel, No_Sub_Sampel, Id_Jenis_Analisa, Tahapan_Ke
      HAVING COUNT(*) > 1) c;


-- ----------------------------------------------------------------------------
-- 6. DAMPAK BUG — berapa validasi yang jejaknya hilang
--    Inilah angka yang dipulihkan 02-BACKFILL.sql.
-- ----------------------------------------------------------------------------
PRINT '';
PRINT '--- 6. Dampak bug: jejak validasi yang hilang ---';
WITH UjiFlag AS (
    SELECT
        ja.Kode_Aktivitas_Lab,
        CASE WHEN EXISTS (
            SELECT 1 FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d
            WHERE d.No_Sampel        = u.No_Po_Sampel
              AND d.Id_Jenis_Analisa = u.Id_Jenis_Analisa
        ) THEN 1 ELSE 0 END AS Ada
    FROM N_EMI_LAB_Uji_Sampel u
    JOIN N_EMI_LAB_Jenis_Analisa ja ON ja.id = u.Id_Jenis_Analisa
    WHERE u.Flag_Selesai = 'Y' AND u.Status IS NULL
)
SELECT
    ISNULL(f.Kode_Aktivitas_Lab, '(null)')      AS Kode_Aktivitas,
    ISNULL(k.Nama_Aktivitas, '(tidak dikenal)') AS Nama_Aktivitas,
    COUNT(*)                                    AS Sudah_Divalidasi,
    SUM(f.Ada)                                  AS Ada_Jejak,
    SUM(1 - f.Ada)                              AS JEJAK_HILANG
FROM UjiFlag f
LEFT JOIN N_EMI_LIMS_Klasifikasi_Aktivitas_Lab k
    ON k.Kode_Aktivitas_Lab = f.Kode_Aktivitas_Lab
GROUP BY f.Kode_Aktivitas_Lab, k.Nama_Aktivitas
ORDER BY JEJAK_HILANG DESC;

-- Akar masalahnya: pencatatan lama hanya berjalan untuk analisa
-- ber-Flag_Perhitungan='Y'. Sebaran di bawah menunjukkan mengapa seluruh
-- PLT dan LCKV tidak pernah tercatat.
PRINT '';
PRINT '--- 6b. Sebaran Flag_Perhitungan per aktivitas (akar masalah) ---';
SELECT
    ISNULL(Kode_Aktivitas_Lab, '(null)') AS Kode_Aktivitas,
    ISNULL(Flag_Perhitungan, '(null)')   AS Flag_Perhitungan,
    COUNT(*)                             AS Jumlah_Jenis_Analisa
FROM N_EMI_LAB_Jenis_Analisa
GROUP BY Kode_Aktivitas_Lab, Flag_Perhitungan
ORDER BY Kode_Aktivitas, Flag_Perhitungan;


-- ----------------------------------------------------------------------------
-- 7. Volume — menentukan lama proses dan kebutuhan ruang log
-- ----------------------------------------------------------------------------
PRINT '';
PRINT '--- 7. Volume data ---';
SELECT 'N_EMI_LAB_Uji_Sampel (total)'          AS Tabel, COUNT(*) AS Jumlah_Baris FROM N_EMI_LAB_Uji_Sampel
UNION ALL SELECT 'N_EMI_LAB_Uji_Sampel (Flag_Selesai=Y)', COUNT(*) FROM N_EMI_LAB_Uji_Sampel WHERE Flag_Selesai = 'Y' AND Status IS NULL
UNION ALL SELECT 'N_EMI_LAB_Hasil_Uji_Validasi_Final',    COUNT(*) FROM N_EMI_LAB_Hasil_Uji_Validasi_Final
UNION ALL SELECT 'N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final', COUNT(*) FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final;

PRINT '';
PRINT '--- 7b. Ruang file database ---';
SELECT
    name                                   AS Nama_File,
    type_desc                              AS Jenis,
    CAST(size * 8.0 / 1024 AS DECIMAL(12,1)) AS Ukuran_MB,
    CASE WHEN max_size = -1 THEN 'unlimited'
         ELSE CAST(CAST(max_size * 8.0 / 1024 AS DECIMAL(12,1)) AS VARCHAR(20)) + ' MB' END AS Batas_Maks
FROM sys.database_files;
-- 02-BACKFILL.sql menulis per-batch dan COMMIT tiap batch, sehingga pada
-- recovery model SIMPLE log tidak menumpuk. Tetap pastikan file log punya
-- ruang tumbuh yang wajar sebelum menjalankannya.

PRINT '';
PRINT '============================================================';
PRINT ' PRACHECK SELESAI.';
PRINT ' Lanjut ke 01-STRUKTUR.sql HANYA JIKA bagian 4 berstatus LULUS.';
PRINT '============================================================';
