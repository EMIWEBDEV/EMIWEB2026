-- ============================================================================
-- 00-PRACHECK.sql  —  READ ONLY. Tidak mengubah apa pun.
-- Tanggal   : 23-09-2026
-- Tujuan    : Dijalankan DevOps SEBELUM 01/02/03 untuk memotret kondisi awal
--             database produksi dan memastikan skrip berikutnya aman.
-- Cara pakai: Jalankan di SSMS (F5). Simpan hasilnya sebagai bukti before-state.
--
-- CATATAN PENTING UNTUK DEVOPS:
--   Skrip ini HANYA SELECT. Aman dijalankan kapan pun, berapa kali pun.
--   Jika ada bagian yang menampilkan baris (bukan 0 baris), BACA catatannya
--   sebelum lanjut ke 01-STRUKTUR.sql.
-- ============================================================================

SET NOCOUNT ON;

PRINT '============================================================';
PRINT ' PRACHECK — ' + CONVERT(VARCHAR(19), GETDATE(), 120);
PRINT ' Database : ' + DB_NAME();
PRINT ' Server   : ' + @@SERVERNAME;
PRINT '============================================================';
PRINT '';


-- ----------------------------------------------------------------------------
-- 1. Versi & collation — memastikan sintaks skrip cocok
-- ----------------------------------------------------------------------------
PRINT '--- 1. Environment ---';
SELECT
    DB_NAME()                                      AS Database_Name,
    DATABASEPROPERTYEX(DB_NAME(), 'Collation')     AS Collation,
    SERVERPROPERTY('ProductMajorVersion')          AS SQL_Major_Version,
    SERVERPROPERTY('Edition')                      AS Edition;
-- Diharapkan: Collation = Latin1_General_CI_AS, Major Version >= 13 (2016+).
-- Skrip ini memakai sintaks yang kompatibel SQL Server 2016 ke atas.


-- ----------------------------------------------------------------------------
-- 2. Apakah tabel inti ada? Kalau ada yang hilang, STOP.
-- ----------------------------------------------------------------------------
PRINT '';
PRINT '--- 2. Keberadaan tabel inti ---';
SELECT
    t.Nama_Tabel,
    CASE WHEN OBJECT_ID(t.Nama_Tabel, 'U') IS NULL
         THEN 'TIDAK ADA  <-- STOP, jangan lanjut'
         ELSE 'ADA' END AS Status
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
-- 3. Objek BARU yang akan dibuat 01-STRUKTUR.sql
--    Kalau sudah ADA semua, artinya skrip pernah dijalankan (aman diulang).
-- ----------------------------------------------------------------------------
PRINT '';
PRINT '--- 3. Objek baru (status sebelum migrasi) ---';
SELECT 'TABEL  N_EMI_LAB_Hasil_Uji_Approval_Aktivitas' AS Objek,
       CASE WHEN OBJECT_ID('N_EMI_LAB_Hasil_Uji_Approval_Aktivitas','U') IS NULL
            THEN 'belum ada (akan dibuat)' ELSE 'sudah ada (dilewati)' END AS Status
UNION ALL
SELECT 'KOLOM  Detail_Final.Id_Uji_Validasi_Final',
       CASE WHEN COL_LENGTH('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','Id_Uji_Validasi_Final') IS NULL
            THEN 'belum ada (akan ditambah)' ELSE 'sudah ada (dilewati)' END
UNION ALL
SELECT 'KOLOM  Detail_Final.Kode_Aktivitas_Lab',
       CASE WHEN COL_LENGTH('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','Kode_Aktivitas_Lab') IS NULL
            THEN 'belum ada (akan ditambah)' ELSE 'sudah ada (dilewati)' END
UNION ALL
SELECT 'KOLOM  Detail_Final.Id_Session',
       CASE WHEN COL_LENGTH('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','Id_Session') IS NULL
            THEN 'belum ada (akan ditambah)' ELSE 'sudah ada (dilewati)' END;


-- ----------------------------------------------------------------------------
-- 4. RISIKO A — duplikat di Detail_Final
--    Skrip 01 TIDAK memasang UNIQUE constraint justru karena ini.
--    Baris di bawah = kandidat double-submit yang perlu dibersihkan manual.
-- ----------------------------------------------------------------------------
PRINT '';
PRINT '--- 4. Duplikat Detail_Final (informasi, tidak memblokir) ---';
SELECT
    d.No_Sampel,
    d.No_Sub_Sampel,
    d.Id_Jenis_Analisa,
    d.Tahapan_Ke,
    COUNT(*) AS Jumlah_Baris
FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d
GROUP BY d.No_Sampel, d.No_Sub_Sampel, d.Id_Jenis_Analisa, d.Tahapan_Ke
HAVING COUNT(*) > 1
ORDER BY COUNT(*) DESC;
-- 0 baris  = bersih.
-- >0 baris = ada duplikat lama. TIDAK memblokir migrasi (index yang dipasang
--            sengaja non-unique). Bersihkan lewat 04-PEMBERSIHAN-OPSIONAL.sql
--            setelah ditinjau tim.


-- ----------------------------------------------------------------------------
-- 5. RISIKO B — duplikat header (No_Split_Po, No_Batch, No_Sampel)
--    Skrip 01 memasang UNIQUE index pada kombinasi ini. Kalau ada baris di
--    bawah, UNIQUE index AKAN GAGAL. Wajib 0 baris sebelum lanjut.
-- ----------------------------------------------------------------------------
PRINT '';
PRINT '--- 5. Duplikat header — WAJIB 0 BARIS ---';
SELECT
    h.No_Split_Po,
    h.No_Batch,
    h.No_Sampel,
    COUNT(*) AS Jumlah_Baris
FROM N_EMI_LAB_Hasil_Uji_Validasi_Final h
GROUP BY h.No_Split_Po, h.No_Batch, h.No_Sampel
HAVING COUNT(*) > 1;
-- 0 baris  = aman, lanjut.
-- >0 baris = STOP. Bersihkan duplikat dulu, jangan jalankan 01-STRUKTUR.sql.


-- ----------------------------------------------------------------------------
-- 6. RISIKO C — satu (No_Split_Po,No_Batch) dipakai banyak sampel
--    Ini korban bug updateOrInsert lama (kunci tanpa No_Sampel).
--    Sifatnya informasi: header lama mungkin sudah tertimpa dan tidak bisa
--    dipulihkan dari tabel ini sendiri.
-- ----------------------------------------------------------------------------
PRINT '';
PRINT '--- 6. Satu batch dipakai banyak sampel (jejak bug lama) ---';
SELECT
    h.No_Split_Po,
    h.No_Batch,
    COUNT(DISTINCT h.No_Sampel) AS Jumlah_Sampel_Berbeda
FROM N_EMI_LAB_Hasil_Uji_Validasi_Final h
GROUP BY h.No_Split_Po, h.No_Batch
HAVING COUNT(DISTINCT h.No_Sampel) > 1;


-- ----------------------------------------------------------------------------
-- 7. DAMPAK BUG — berapa validasi yang jejaknya hilang
--    Inilah angka yang membenarkan seluruh migrasi ini.
-- ----------------------------------------------------------------------------
PRINT '';
PRINT '--- 7. Validasi tanpa jejak di Detail_Final (dampak bug) ---';
-- Flag dihitung di CTE: SQL Server melarang agregat membungkus subquery.
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
    WHERE u.Flag_Selesai = 'Y'
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
-- Kolom JEJAK_HILANG untuk PLT dan LCKV diharapkan besar — itulah bug-nya.
-- Setelah 02-BACKFILL.sql dijalankan, angka ini harus turun mendekati 0.


-- ----------------------------------------------------------------------------
-- 8. Akar masalah di master: Flag_Perhitungan sebagai gerbang pencatatan
-- ----------------------------------------------------------------------------
PRINT '';
PRINT '--- 8. Sebaran Flag_Perhitungan per aktivitas (akar masalah) ---';
SELECT
    ISNULL(ja.Kode_Aktivitas_Lab, '(null)') AS Kode_Aktivitas,
    ISNULL(ja.Flag_Perhitungan, '(null)')   AS Flag_Perhitungan,
    COUNT(*)                                AS Jumlah_Jenis_Analisa
FROM N_EMI_LAB_Jenis_Analisa ja
GROUP BY ja.Kode_Aktivitas_Lab, ja.Flag_Perhitungan
ORDER BY Kode_Aktivitas, Flag_Perhitungan;
-- Kode lama hanya mencatat baris ber-Flag_Perhitungan='Y'. Baris '(null)'
-- itulah yang selama ini hilang. Perbaikan di kode aplikasi (PHP) melepas
-- ketergantungan ini; skrip SQL menyiapkan strukturnya.


-- ----------------------------------------------------------------------------
-- 9. Volume data — perkiraan durasi 02-BACKFILL.sql
-- ----------------------------------------------------------------------------
PRINT '';
PRINT '--- 9. Volume data ---';
SELECT 'N_EMI_LAB_Hasil_Uji_Validasi_Final'        AS Tabel, COUNT(*) AS Jumlah_Baris FROM N_EMI_LAB_Hasil_Uji_Validasi_Final
UNION ALL SELECT 'N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final', COUNT(*) FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
UNION ALL SELECT 'N_EMI_LAB_Uji_Sampel (Flag_Selesai=Y)',     COUNT(*) FROM N_EMI_LAB_Uji_Sampel WHERE Flag_Selesai = 'Y'
UNION ALL SELECT 'N_EMI_LAB_Uji_Sampel (total)',              COUNT(*) FROM N_EMI_LAB_Uji_Sampel;

PRINT '';
PRINT '============================================================';
PRINT ' PRACHECK SELESAI.';
PRINT ' Lanjut ke 01-STRUKTUR.sql HANYA JIKA bagian 5 = 0 baris.';
PRINT '============================================================';
