-- ============================================================================
-- 03-VERIFIKASI.sql  —  Bukti migrasi berhasil
-- Tanggal : 24-09-2026
--
-- Dijalankan setelah 01 dan 02. Setiap bagian memunculkan kolom HASIL berisi
-- LULUS atau GAGAL, sehingga DevOps tidak perlu menafsirkan angka sendiri.
--
-- Read-only, KECUALI bagian 1 yang menjalankan CHECK CONSTRAINT — itu
-- memvalidasi foreign key, tidak mengubah data.
-- ============================================================================

SET NOCOUNT ON;

PRINT '============================================================';
PRINT ' 03-VERIFIKASI  —  ' + CONVERT(VARCHAR(19), GETDATE(), 120);
PRINT ' Database : ' + DB_NAME();
PRINT '============================================================';


-- ----------------------------------------------------------------------------
-- 1. Aktifkan kembali pengecekan foreign key
--    Di 01 constraint dibuat WITH NOCHECK agar tidak gagal oleh baris lama.
--    Setelah backfill, barulah divalidasi penuh.
-- ----------------------------------------------------------------------------
PRINT '';
PRINT '--- 1. Validasi ulang foreign key ---';
BEGIN TRY
    ALTER TABLE N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
        WITH CHECK CHECK CONSTRAINT FK_ValidasiDetailFinal_ValidasiFinal;
    PRINT '  [LULUS] FK tervalidasi — semua Id_Uji_Validasi_Final menunjuk header yang sah.';
END TRY
BEGIN CATCH
    PRINT '  [GAGAL] ' + ERROR_MESSAGE();
    PRINT '          Ada detail menunjuk header yang tidak ada. Periksa manual.';
END CATCH;

SELECT 'Status foreign key' AS Pemeriksaan,
       name                 AS Constraint_Name,
       CASE WHEN is_not_trusted = 0 THEN 'LULUS — tervalidasi penuh'
            ELSE 'PERHATIAN — masih NOT TRUSTED' END AS HASIL
FROM sys.foreign_keys
WHERE name = 'FK_ValidasiDetailFinal_ValidasiFinal';


-- ----------------------------------------------------------------------------
-- 2. UJI UTAMA — apakah jejak PLT, LCKV, dan ANL sudah lengkap?
--    Inilah bug yang diperbaiki. JEJAK_HILANG harus 0 di semua aktivitas.
-- ----------------------------------------------------------------------------
PRINT '';
PRINT '--- 2. Kelengkapan jejak per aktivitas (UJI UTAMA) ---';

-- Flag dihitung lebih dulu di CTE: SQL Server melarang agregat membungkus
-- subquery, sehingga pemeriksaan dipecah dua tahap.
WITH Uji AS (
    SELECT
        ja.Kode_Aktivitas_Lab,
        u.No_Po_Sampel,
        u.Id_Jenis_Analisa,
        u.No_Fak_Sub_Po,
        ISNULL(u.Tahapan_Ke, 1)     AS Tahapan,
        ISNULL(u.Id_Pembanding, -1) AS Pembanding
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
              AND ISNULL(d.No_Sub_Sampel, '~') = ISNULL(Uji.No_Fak_Sub_Po, '~')
              AND ISNULL(d.Tahapan_Ke, 1)      = Uji.Tahapan
              AND ISNULL(d.Id_Pembanding, -1)  = Uji.Pembanding
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
    ISNULL(a.Nama_Aktivitas, '-')   AS Aktivitas,
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

SELECT 'Detail yatim (FK menunjuk header tidak ada)' AS Pemeriksaan,
       COUNT(*) AS Jumlah,
       CASE WHEN COUNT(*) = 0 THEN 'LULUS' ELSE 'GAGAL' END AS HASIL
FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d
WHERE d.Id_Uji_Validasi_Final IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Hasil_Uji_Validasi_Final h
                  WHERE h.Id_Uji_Validasi_Final = d.Id_Uji_Validasi_Final)

UNION ALL
SELECT 'Detail: header sudah ada tapi FK masih kosong',
       COUNT(*),
       CASE WHEN COUNT(*) = 0 THEN 'LULUS' ELSE 'GAGAL' END
FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d
WHERE d.Id_Uji_Validasi_Final IS NULL
  AND EXISTS (SELECT 1 FROM N_EMI_LAB_Hasil_Uji_Validasi_Final h
              WHERE h.No_Sampel = d.No_Sampel)

UNION ALL
SELECT 'Detail tanpa Kode_Aktivitas_Lab',
       COUNT(*),
       CASE WHEN COUNT(*) = 0 THEN 'LULUS'
            ELSE 'PERHATIAN — jenis analisa tidak ada di master' END
FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
WHERE Kode_Aktivitas_Lab IS NULL

UNION ALL
SELECT 'Detail: placeholder tampilan tersimpan sbg sub-sampel',
       COUNT(*),
       CASE WHEN COUNT(*) = 0 THEN 'LULUS' ELSE 'GAGAL' END
FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
WHERE No_Sub_Sampel IN ('—', '–', '-', '')

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
       ISNULL(SUM(x.N), 0),
       CASE WHEN ISNULL(SUM(x.N), 0) = 0 THEN 'LULUS' ELSE 'GAGAL' END
FROM (SELECT COUNT(*) AS N FROM N_EMI_LAB_Hasil_Uji_Validasi_Final
      GROUP BY No_Split_Po, No_Batch, No_Sampel HAVING COUNT(*) > 1) x;

-- Informasi, bukan kesalahan: detail yang sampelnya memang belum difinalisasi.
PRINT '';
PRINT '--- 5b. Informasi ---';
SELECT 'Detail belum difinalisasi (FK NULL — kondisi sah)' AS Keterangan,
       COUNT(*) AS Jumlah
FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d
WHERE d.Id_Uji_Validasi_Final IS NULL
  AND NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Hasil_Uji_Validasi_Final h
                  WHERE h.No_Sampel = d.No_Sampel);


-- ----------------------------------------------------------------------------
-- 6. Contoh keluaran: siapa meng-approve apa
-- ----------------------------------------------------------------------------
PRINT '';
PRINT '--- 6. Contoh: siapa approve apa (20 sampel terbaru) ---';

-- Nama approver di-DISTINCT dulu: satu user wajar meng-approve banyak analisa
-- dalam satu aktivitas, dan namanya tidak boleh berulang.
-- FOR XML PATH dipakai (bukan STRING_AGG) supaya jalan juga di SQL Server 2016.
-- TYPE + .value() mencegah '&' dan '<' berubah jadi entity HTML.
WITH Sampel20 AS (
    SELECT TOP 20 No_Sampel
    FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas
    WHERE Jenis_Approval = 'VALIDASI'
    GROUP BY No_Sampel
    ORDER BY MAX(Tanggal) DESC, No_Sampel DESC
),
Approver AS (
    SELECT DISTINCT a.No_Sampel, a.Kode_Aktivitas_Lab,
           ISNULL(a.Nama_User, a.Id_User) AS Approver
    FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas a
    JOIN Sampel20 s ON s.No_Sampel = a.No_Sampel
    WHERE a.Jenis_Approval = 'VALIDASI'
),
Statistik AS (
    SELECT a.No_Sampel, a.No_Po, a.No_Batch,
           a.Kode_Aktivitas_Lab, a.Nama_Aktivitas,
           COUNT(DISTINCT a.Id_Jenis_Analisa) AS Jumlah_Analisa,
           COUNT(DISTINCT a.Id_User)          AS Jumlah_Approver
    FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas a
    JOIN Sampel20 s ON s.No_Sampel = a.No_Sampel
    WHERE a.Jenis_Approval = 'VALIDASI'
    GROUP BY a.No_Sampel, a.No_Po, a.No_Batch, a.Kode_Aktivitas_Lab, a.Nama_Aktivitas
)
SELECT
    st.No_Sampel,
    st.No_Po,
    st.No_Batch,
    st.Kode_Aktivitas_Lab           AS Kode,
    ISNULL(st.Nama_Aktivitas, '-')  AS Aktivitas,
    st.Jumlah_Analisa,
    st.Jumlah_Approver,
    STUFF((
        SELECT ', ' + p.Approver
        FROM Approver p
        WHERE p.No_Sampel = st.No_Sampel
          AND p.Kode_Aktivitas_Lab = st.Kode_Aktivitas_Lab
        ORDER BY p.Approver
        FOR XML PATH(''), TYPE
    ).value('.', 'NVARCHAR(MAX)'), 1, 2, '') AS Daftar_Approver
FROM Statistik st
ORDER BY st.No_Sampel, st.Kode_Aktivitas_Lab;

PRINT '';
PRINT '============================================================';
PRINT ' VERIFIKASI SELESAI.';
PRINT ' Pastikan seluruh kolom HASIL berisi LULUS.';
PRINT '============================================================';
