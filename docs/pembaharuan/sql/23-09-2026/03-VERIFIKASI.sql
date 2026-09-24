-- ============================================================================
-- 03-VERIFIKASI.sql  —  READ ONLY (kecuali CHECK CONSTRAINT di bagian 1)
-- Tanggal : 23-09-2026
-- Tujuan  : Bukti bahwa migrasi berhasil. Dijalankan setelah 01 dan 02.
--           Setiap bagian menampilkan kolom "HASIL" berisi LULUS / GAGAL
--           supaya DevOps tidak perlu menafsirkan angka sendiri.
-- ============================================================================

SET NOCOUNT ON;

PRINT '============================================================';
PRINT ' 03-VERIFIKASI — ' + CONVERT(VARCHAR(19), GETDATE(), 120);
PRINT '============================================================';


-- ----------------------------------------------------------------------------
-- 1. Aktifkan kembali pengecekan FK
--    Di 01 constraint dibuat WITH NOCHECK agar tidak gagal oleh baris lama.
--    Setelah backfill, barulah divalidasi penuh.
-- ----------------------------------------------------------------------------
PRINT '--- 1. Validasi ulang foreign key ---';
BEGIN TRY
    ALTER TABLE N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
        WITH CHECK CHECK CONSTRAINT FK_ValidasiDetailFinal_ValidasiFinal;
    PRINT '  [LULUS] FK tervalidasi, semua Id_Uji_Validasi_Final menunjuk header yang sah.';
END TRY
BEGIN CATCH
    PRINT '  [GAGAL] FK tidak lolos validasi: ' + ERROR_MESSAGE();
    PRINT '          Ada detail menunjuk header yang tidak ada. Periksa manual.';
END CATCH;

SELECT
    'Status FK' AS Pemeriksaan,
    name        AS Constraint_Name,
    CASE WHEN is_not_trusted = 0 THEN 'LULUS — tervalidasi penuh'
         ELSE 'PERHATIAN — masih NOT TRUSTED' END AS HASIL
FROM sys.foreign_keys
WHERE name = 'FK_ValidasiDetailFinal_ValidasiFinal';


-- ----------------------------------------------------------------------------
-- 2. UJI UTAMA — apakah jejak PLT & LCKV sudah lengkap?
--    Inilah bug yang dilaporkan. Setelah migrasi, JEJAK_HILANG harus 0.
-- ----------------------------------------------------------------------------
PRINT '';
PRINT '--- 2. Kelengkapan jejak per aktivitas (UJI UTAMA) ---';

-- Catatan teknis: flag "ada jejak" dihitung lebih dulu di CTE.
-- SQL Server melarang SUM(CASE WHEN EXISTS(subquery)...) — agregat tidak
-- boleh membungkus subquery — sehingga pemeriksaan dipecah dua tahap.
WITH Uji AS (
    SELECT
        ja.Kode_Aktivitas_Lab,
        u.No_Po_Sampel,
        u.Id_Jenis_Analisa,
        ISNULL(u.No_Fak_Sub_Po, u.No_Po_Sampel) AS Sub,
        ISNULL(u.Tahapan_Ke, 1)                 AS Tahapan,
        ISNULL(u.Id_Pembanding, -1)             AS Pembanding
    FROM N_EMI_LAB_Uji_Sampel u
    JOIN N_EMI_LAB_Jenis_Analisa ja ON ja.id = u.Id_Jenis_Analisa
    WHERE u.Flag_Selesai = 'Y' AND u.Status IS NULL
),
UjiFlag AS (
    SELECT
        Uji.Kode_Aktivitas_Lab,
        CASE WHEN EXISTS (
            SELECT 1 FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d
            WHERE d.No_Sampel        = Uji.No_Po_Sampel
              AND d.Id_Jenis_Analisa = Uji.Id_Jenis_Analisa
              AND ISNULL(d.No_Sub_Sampel, d.No_Sampel) = Uji.Sub
              AND ISNULL(d.Tahapan_Ke, 1)              = Uji.Tahapan
              AND ISNULL(d.Id_Pembanding, -1)          = Uji.Pembanding
        ) THEN 1 ELSE 0 END AS Ada
    FROM Uji
)
SELECT
    ISNULL(k.Nama_Aktivitas, f.Kode_Aktivitas_Lab) AS Aktivitas,
    f.Kode_Aktivitas_Lab                           AS Kode,
    COUNT(*)                                       AS Sudah_Divalidasi,
    SUM(f.Ada)                                     AS Ada_Jejak,
    SUM(1 - f.Ada)                                 AS JEJAK_HILANG,
    CASE WHEN SUM(1 - f.Ada) = 0
         THEN 'LULUS' ELSE 'GAGAL — masih ada yang hilang' END AS HASIL
FROM UjiFlag f
LEFT JOIN N_EMI_LIMS_Klasifikasi_Aktivitas_Lab k
    ON k.Kode_Aktivitas_Lab = f.Kode_Aktivitas_Lab
GROUP BY f.Kode_Aktivitas_Lab, k.Nama_Aktivitas
ORDER BY JEJAK_HILANG DESC;


-- ----------------------------------------------------------------------------
-- 3. Komposisi Detail_Final menurut asal pencatatan
-- ----------------------------------------------------------------------------
PRINT '';
PRINT '--- 3. Komposisi Detail_Final ---';
SELECT
    ISNULL(Sumber_Pencatatan, '(belum ditandai)') AS Sumber,
    ISNULL(Kode_Aktivitas_Lab, '(null)')          AS Aktivitas,
    COUNT(*)                                      AS Jumlah_Baris
FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
GROUP BY Sumber_Pencatatan, Kode_Aktivitas_Lab
ORDER BY Sumber, Aktivitas;


-- ----------------------------------------------------------------------------
-- 4. Isi tabel approval
-- ----------------------------------------------------------------------------
PRINT '';
PRINT '--- 4. Isi tabel approval ---';
SELECT
    a.Kode_Aktivitas_Lab            AS Kode,
    ISNULL(a.Nama_Aktivitas,'-')    AS Aktivitas,
    a.Jenis_Approval                AS Jenis,
    COUNT(*)                        AS Jumlah_Baris,
    COUNT(DISTINCT a.No_Sampel)     AS Jumlah_Sampel,
    COUNT(DISTINCT a.Id_User)       AS Jumlah_User
FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas a
GROUP BY a.Kode_Aktivitas_Lab, a.Nama_Aktivitas, a.Jenis_Approval
ORDER BY Kode, Jenis;


-- ----------------------------------------------------------------------------
-- 5. Integritas — semua harus LULUS
-- ----------------------------------------------------------------------------
PRINT '';
PRINT '--- 5. Integritas data ---';

SELECT 'Detail yatim (Id_Uji_Validasi_Final menunjuk header tidak ada)' AS Pemeriksaan,
       COUNT(*) AS Jumlah,
       CASE WHEN COUNT(*) = 0 THEN 'LULUS' ELSE 'GAGAL' END AS HASIL
FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d
WHERE d.Id_Uji_Validasi_Final IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Hasil_Uji_Validasi_Final h
                  WHERE h.Id_Uji_Validasi_Final = d.Id_Uji_Validasi_Final)

UNION ALL
SELECT 'Detail tanpa Kode_Aktivitas_Lab',
       COUNT(*),
       CASE WHEN COUNT(*) = 0 THEN 'LULUS' ELSE 'PERHATIAN — jenis analisa tidak ada di master' END
FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
WHERE Kode_Aktivitas_Lab IS NULL

UNION ALL
SELECT 'Approval dengan aktivitas di luar ANL/PLT/LCKV',
       COUNT(*),
       CASE WHEN COUNT(*) = 0 THEN 'LULUS' ELSE 'PERHATIAN' END
FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas
WHERE Kode_Aktivitas_Lab NOT IN ('ANL','PLT','LCKV')

UNION ALL
SELECT 'Approval tanpa Id_User',
       COUNT(*),
       CASE WHEN COUNT(*) = 0 THEN 'LULUS' ELSE 'GAGAL' END
FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas
WHERE Id_User IS NULL OR LTRIM(RTRIM(Id_User)) = ''

UNION ALL
SELECT 'Header duplikat (No_Split_Po,No_Batch,No_Sampel)',
       ISNULL(SUM(x.N),0),
       CASE WHEN ISNULL(SUM(x.N),0) = 0 THEN 'LULUS' ELSE 'GAGAL' END
FROM (SELECT COUNT(*) AS N FROM N_EMI_LAB_Hasil_Uji_Validasi_Final
      GROUP BY No_Split_Po, No_Batch, No_Sampel HAVING COUNT(*) > 1) x;


-- ----------------------------------------------------------------------------
-- 6. Contoh keluaran untuk tim desktop
--    Persis bentuk yang diminta: per sampel & batch, siapa approve apa.
-- ----------------------------------------------------------------------------
PRINT '';
PRINT '--- 6. Contoh: siapa approve apa (20 baris pertama) ---';
-- Catatan teknis: STRING_AGG tidak menerima DISTINCT, sedangkan satu user
-- wajar meng-approve banyak analisa dalam satu aktivitas (terutama PLT yang
-- punya banyak pembanding). Nama di-DISTINCT-kan dulu di CTE supaya tidak
-- berulang. STRING_AGG butuh SQL Server 2017+; untuk 2016 pakai versi
-- FOR XML PATH di 07-QUERY-DESKTOP.sql.
WITH Ringkas AS (
    SELECT DISTINCT
        a.No_Sampel, a.No_Po, a.No_Batch,
        a.Kode_Aktivitas_Lab, a.Nama_Aktivitas,
        ISNULL(a.Nama_User, a.Id_User) AS Approver
    FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas a
    WHERE a.Jenis_Approval = 'VALIDASI'
),
Hitung AS (
    SELECT
        a.No_Sampel, a.Kode_Aktivitas_Lab,
        COUNT(DISTINCT a.Id_Jenis_Analisa) AS Jumlah_Analisa
    FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas a
    WHERE a.Jenis_Approval = 'VALIDASI'
    GROUP BY a.No_Sampel, a.Kode_Aktivitas_Lab
)
SELECT TOP 20
    r.No_Sampel,
    r.No_Po,
    r.No_Batch,
    r.Kode_Aktivitas_Lab            AS Kode,
    ISNULL(r.Nama_Aktivitas,'-')    AS Aktivitas,
    MAX(h.Jumlah_Analisa)           AS Jumlah_Analisa,
    COUNT(*)                        AS Jumlah_Approver,
    STRING_AGG(CONVERT(NVARCHAR(MAX), r.Approver), ', ')
        WITHIN GROUP (ORDER BY r.Approver) AS Daftar_Approver
FROM Ringkas r
JOIN Hitung h
    ON h.No_Sampel = r.No_Sampel
   AND h.Kode_Aktivitas_Lab = r.Kode_Aktivitas_Lab
GROUP BY r.No_Sampel, r.No_Po, r.No_Batch, r.Kode_Aktivitas_Lab, r.Nama_Aktivitas
ORDER BY r.No_Sampel, r.Kode_Aktivitas_Lab;

PRINT '';
PRINT '============================================================';
PRINT ' VERIFIKASI SELESAI.';
PRINT ' Pastikan seluruh kolom HASIL berisi LULUS.';
PRINT '============================================================';
