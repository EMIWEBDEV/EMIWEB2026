-- ============================================================================
-- 02-SEED-USER-KEWENANGAN.sql
-- Tanggal : 24-09-2026
-- Modul   : VERIFIKASI HASIL ANALISA
--
-- ISI:
--   A. Tambah 3 user verifikator: ratna, roby, jati
--   B. Petakan kewenangan masing-masing atas klasifikasi aktivitas
--
-- PEMBAGIAN KEWENANGAN:
--   ratna  -> ANL  (Analisa Lab)      : ash, moisture, protein, AC, EC, YM, salt
--   jati   -> LCKV (Look View)        : QA — warna, aroma, tekstur pouch
--   roby   -> PLT  (Uji Palatabilitas): seluruh jenis analisa palatabilitas
--
-- CATATAN:
--   * `roby` sudah ada di N_EMI_LAB_Users sebagai 'ROBY'. Skrip mendeteksinya
--     dan tidak membuat duplikat — hanya menambah kewenangannya.
--   * Password awal ketiganya: 123456  (bcrypt). Ganti setelah uji coba.
--   * IDEMPOTEN: dijalankan berulang kali tidak menggandakan user maupun
--     kewenangan.
--   * Hanya INSERT ke tabel Users dan tabel modul verifikasi. Tidak
--     menyentuh data transaksi mana pun.
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;

-- bcrypt dari '123456'
DECLARE @pwd VARCHAR(255) = '$2y$10$t2Y9r5bXvhRzYFLByTlxROV8qrx6zVi5XRA6BaPmCaivjEbn4isC.';

PRINT '============================================================';
PRINT ' SEED USER & KEWENANGAN VERIFIKASI';
PRINT ' Database : ' + DB_NAME();
PRINT '============================================================';

BEGIN TRY
BEGIN TRANSACTION;

    IF OBJECT_ID('N_EMI_LAB_Verifikasi_Kewenangan','U') IS NULL
        THROW 54001, 'Tabel kewenangan belum ada. Jalankan 01-STRUKTUR-VERIFIKASI.sql dulu.', 1;

    -- ========================================================================
    -- A. USER VERIFIKATOR
    -- ========================================================================
    -- N_EMI_LAB_Users.UserId punya foreign key ke dbo.Users.UserID, jadi user
    -- harus dibuat di tabel induk lebih dulu. Kolom diisi mengikuti pola baris
    -- yang sudah ada (mis. 'IIS') agar konsisten dengan data existing.
    PRINT '';
    PRINT '--- A. User verifikator ---';

    -- A1. Tabel induk dbo.Users
    INSERT INTO Users (Kode_Perusahaan, UserID, UserName, Pass, UserLevel,
                       Lokasi, Kode_Kategori, Jabatan)
    SELECT '001', u.UserId, u.UserName, 'x', '1',
           'HEAD OFFICE', 'PUSAT', 'Lain-Lain'
    FROM (VALUES
        ('ratna', 'RATNA'),
        ('jati',  'JATI'),
        ('roby',  'ROBY2')
    ) AS u(UserId, UserName)
    WHERE NOT EXISTS (SELECT 1 FROM Users x WHERE x.UserID = u.UserId);
    PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' baris di dbo.Users (tabel induk).';

    -- A2. Tabel aplikasi N_EMI_LAB_Users
    INSERT INTO N_EMI_LAB_Users (Kode_Perusahaan, UserId, Nama, Password, Pin, Flag_Aktif)
    SELECT '001', u.UserId, u.Nama, @pwd, @pwd, 'Y'
    FROM (VALUES
        ('ratna', 'Ratna — Verifikator Analisa Lab'),
        ('jati',  'Jati — Verifikator Look View'),
        ('roby',  'Roby — Verifikator Palatabilitas')
    ) AS u(UserId, Nama)
    WHERE EXISTS (SELECT 1 FROM Users x WHERE x.UserID = u.UserId)
      AND NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Users x WHERE x.UserId = u.UserId);
    PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' user baru ditambahkan.';
    PRINT '      (user yang sudah ada dilewati — mis. ROBY)';


    -- ========================================================================
    -- B. KEWENANGAN
    -- ========================================================================
    -- Id_User dicocokkan case-insensitive terhadap yang benar-benar ada di
    -- tabel Users, supaya 'roby' otomatis terpetakan ke 'ROBY' yang sudah ada.
    PRINT '';
    PRINT '--- B. Kewenangan per klasifikasi ---';

    -- B1. RATNA — Analisa Lab, dibatasi pada jenis analisa tertentu
    INSERT INTO N_EMI_LAB_Verifikasi_Kewenangan
        (Id_User, Kode_Aktivitas_Lab, Id_Jenis_Analisa, Keterangan, Dibuat_Oleh)
    SELECT
        (SELECT TOP 1 UserId FROM N_EMI_LAB_Users WHERE UserId = 'ratna'),
        'ANL',
        ja.id,
        'Analisa lab: ' + ja.Jenis_Analisa,
        'SEED'
    FROM N_EMI_LAB_Jenis_Analisa ja
    WHERE ja.Kode_Aktivitas_Lab = 'ANL'
      AND ja.Kode_Role = 'LAB'
      AND (   ja.Jenis_Analisa LIKE '%ASH%'
           OR ja.Jenis_Analisa LIKE '%MOISTURE%'
           OR ja.Jenis_Analisa LIKE '%PROTEIN%'
           OR ja.Jenis_Analisa LIKE '%MIKROBIOLOGI%'
           OR ja.Jenis_Analisa LIKE '%SALT%')
      AND EXISTS (SELECT 1 FROM N_EMI_LAB_Users WHERE UserId = 'ratna')
      AND NOT EXISTS (
            SELECT 1 FROM N_EMI_LAB_Verifikasi_Kewenangan k
            WHERE k.Id_User = 'ratna'
              AND k.Kode_Aktivitas_Lab = 'ANL'
              AND k.Id_Jenis_Analisa = ja.id
      );
    PRINT '  [+] ratna : ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' jenis analisa (ANL).';

    -- B2. JATI — Look View, hanya yang QA
    INSERT INTO N_EMI_LAB_Verifikasi_Kewenangan
        (Id_User, Kode_Aktivitas_Lab, Id_Jenis_Analisa, Keterangan, Dibuat_Oleh)
    SELECT
        'jati', 'LCKV', ja.id,
        'Look view QA: ' + ja.Jenis_Analisa,
        'SEED'
    FROM N_EMI_LAB_Jenis_Analisa ja
    WHERE ja.Kode_Aktivitas_Lab = 'LCKV'
      AND (   ja.Jenis_Analisa LIKE '%WARNA (QA)%'
           OR ja.Jenis_Analisa LIKE '%AROMA (QA)%'
           OR ja.Jenis_Analisa LIKE '%TEKSTUR POUCH (QA)%')
      AND EXISTS (SELECT 1 FROM N_EMI_LAB_Users WHERE UserId = 'jati')
      AND NOT EXISTS (
            SELECT 1 FROM N_EMI_LAB_Verifikasi_Kewenangan k
            WHERE k.Id_User = 'jati'
              AND k.Kode_Aktivitas_Lab = 'LCKV'
              AND k.Id_Jenis_Analisa = ja.id
      );
    PRINT '  [+] jati  : ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' jenis analisa (LCKV).';

    -- B3. ROBY — seluruh Palatabilitas (Id_Jenis_Analisa NULL = semua)
    --     Dipetakan ke UserId yang benar-benar ada ('ROBY' atau 'roby').
    INSERT INTO N_EMI_LAB_Verifikasi_Kewenangan
        (Id_User, Kode_Aktivitas_Lab, Id_Jenis_Analisa, Keterangan, Dibuat_Oleh)
    SELECT TOP 1
        u.UserId, 'PLT', NULL,
        'Seluruh jenis analisa palatabilitas',
        'SEED'
    FROM N_EMI_LAB_Users u
    WHERE u.UserId IN ('roby', 'ROBY')
      AND NOT EXISTS (
            SELECT 1 FROM N_EMI_LAB_Verifikasi_Kewenangan k
            WHERE k.Id_User IN ('roby','ROBY')
              AND k.Kode_Aktivitas_Lab = 'PLT'
              AND k.Id_Jenis_Analisa IS NULL
      );
    PRINT '  [+] roby  : ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' kewenangan (PLT, seluruh jenis).';

COMMIT TRANSACTION;
PRINT '';
PRINT '============================================================';
PRINT ' SEED SELESAI.';
PRINT '============================================================';

END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    PRINT '';
    PRINT '### GAGAL — SEMUA PERUBAHAN DIBATALKAN (ROLLBACK) ###';
    PRINT 'Pesan : ' + ERROR_MESSAGE();
    THROW;
END CATCH;
GO


-- ============================================================================
-- VERIFIKASI
-- ============================================================================
SET NOCOUNT ON;
PRINT '';
PRINT '--- Verifikasi seed ---';

SELECT 'User verifikator' AS Pemeriksaan,
       u.UserId, u.Nama, u.Flag_Aktif,
       CASE WHEN u.UserId IS NOT NULL THEN 'LULUS' ELSE 'GAGAL' END AS HASIL
FROM N_EMI_LAB_Users u
WHERE u.UserId IN ('ratna','jati','roby','ROBY')
ORDER BY u.UserId;

SELECT 'Kewenangan per user' AS Pemeriksaan,
       k.Id_User,
       k.Kode_Aktivitas_Lab                       AS Kode,
       ISNULL(kl.Nama_Aktivitas, k.Kode_Aktivitas_Lab) AS Aktivitas,
       COUNT(*)                                   AS Jumlah_Kewenangan,
       CASE WHEN MAX(CASE WHEN k.Id_Jenis_Analisa IS NULL THEN 1 ELSE 0 END) = 1
            THEN 'seluruh jenis analisa'
            ELSE 'jenis analisa tertentu' END     AS Cakupan
FROM N_EMI_LAB_Verifikasi_Kewenangan k
LEFT JOIN N_EMI_LIMS_Klasifikasi_Aktivitas_Lab kl
    ON kl.Kode_Aktivitas_Lab = k.Kode_Aktivitas_Lab
WHERE k.Flag_Aktif = 'Y'
GROUP BY k.Id_User, k.Kode_Aktivitas_Lab, kl.Nama_Aktivitas
ORDER BY k.Id_User;

SELECT 'Rincian kewenangan' AS Pemeriksaan,
       k.Id_User, k.Kode_Aktivitas_Lab AS Kode,
       ISNULL(CAST(k.Id_Jenis_Analisa AS VARCHAR(10)), 'SEMUA') AS Id_Analisa,
       ISNULL(ja.Jenis_Analisa, '(seluruh jenis dalam klasifikasi)') AS Jenis_Analisa
FROM N_EMI_LAB_Verifikasi_Kewenangan k
LEFT JOIN N_EMI_LAB_Jenis_Analisa ja ON ja.id = k.Id_Jenis_Analisa
WHERE k.Flag_Aktif = 'Y'
ORDER BY k.Id_User, ja.Jenis_Analisa;
