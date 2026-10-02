-- ============================================================================
-- 04-AKSES-TRIAL-UI.sql
-- Tanggal : 24-09-2026
-- Modul   : TRIAL UI — sandbox alur penuh tanpa login
--
-- PEMBAGIAN PERAN:
--   ratna / jati / roby  -> Uji Sampel, Validasi Trial Produksi,
--                           Verifikasi Hasil Analisa
--   VENGINE              -> Finalisasi Trial Produksi saja, atas data yang
--                           sudah diinput dan disetujui ketiga user di atas
--
-- Pemisahan ini membuat alur terasa nyata: pekerjaan mengalir dari pelaksana
-- ke penanggung jawab rilis, bukan dikerjakan satu orang.
--
-- MENGAPA PERLU:
--   Controller lama membaca Session 'user_permissions' yang dibentuk dari
--   N_EMI_LAB_Page_Access_2 + N_EMI_LAB_Role_Menu_Access. Tanpa baris di sana,
--   menu tidak muncul di sidebar dan daftar kerja akan kosong.
--
-- SIFAT:
--   * IDEMPOTEN — hanya menambah yang belum ada.
--   * Hanya menyentuh hak akses user sandbox. User lain tidak diubah.
--
-- PRASYARAT: 02-SEED-USER-KEWENANGAN.sql sudah dijalankan.
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;

PRINT '============================================================';
PRINT ' AKSES MENU UNTUK SANDBOX /trial-ui';
PRINT ' Database : ' + DB_NAME();
PRINT '============================================================';

BEGIN TRY
BEGIN TRANSACTION;

    -- Peta peran: user x menu yang boleh diaksesnya.
    -- Menu dicari berdasarkan URL agar tidak bergantung pada Id_Menu yang
    -- bisa berbeda antar-database.
    DECLARE @Peran TABLE (UserKey VARCHAR(30), Url VARCHAR(255));

    INSERT INTO @Peran (UserKey, Url) VALUES
        -- Pelaksana: tiga tahap awal
        ('ratna',   '/lab/home'),
        ('ratna',   '/validasi-trial/produksi'),
        ('ratna',   '/verifikasi-hasil-analisa'),
        ('jati',    '/lab/home'),
        ('jati',    '/validasi-trial/produksi'),
        ('jati',    '/verifikasi-hasil-analisa'),
        ('roby',    '/lab/home'),
        ('roby',    '/validasi-trial/produksi'),
        ('roby',    '/verifikasi-hasil-analisa'),
        -- Penanggung jawab rilis: finalisasi saja
        ('vengine', '/finalisai/trial-produksi');

    -- Pasangkan ke user dan menu yang benar-benar ada.
    DECLARE @Akses TABLE (UserId VARCHAR(30), Id_Menu INT, Url VARCHAR(255));

    INSERT INTO @Akses (UserId, Id_Menu, Url)
    SELECT u.UserId, m.Id_Menu, m.Url_Menu
    FROM @Peran p
    JOIN N_EMI_LAB_Users u ON LOWER(u.UserId) = p.UserKey
    JOIN N_EMI_LAB_Menus m ON m.Url_Menu = p.Url;

    PRINT '';
    PRINT '--- Rencana akses ---';
    SELECT UserId, Url FROM @Akses ORDER BY UserId, Url;


    -- ========================================================================
    -- A. Page access
    -- ========================================================================
    PRINT '';
    PRINT '--- A. Page access ---';

    INSERT INTO N_EMI_LAB_Page_Access_2 (Kode_Perusahaan, Id_Menu, Id_User, Urutan_Menu)
    SELECT '001', a.Id_Menu, a.UserId,
           ROW_NUMBER() OVER (PARTITION BY a.UserId ORDER BY a.Id_Menu)
    FROM @Akses a
    WHERE NOT EXISTS (
        SELECT 1 FROM N_EMI_LAB_Page_Access_2 pa
        WHERE pa.Id_User = a.UserId AND pa.Id_Menu = a.Id_Menu
    );

    PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' baris page access.';


    -- ========================================================================
    -- B. Aksi per menu
    -- ========================================================================
    -- Seluruh aksi diberikan agar tombol-tombol pada modul lama berfungsi
    -- sebagaimana mestinya (VIEW, DETAIL, VALIDASI, FINALISASI, dll).
    PRINT '';
    PRINT '--- B. Aksi menu ---';

    INSERT INTO N_EMI_LAB_Role_Menu_Access (Id_Page_Access, Id_Aksi, Flag_Diizinkan, Flag_Access_Konten)
    SELECT pa.Id_Page_Access, ka.Id_Klasifikasi_Actions, 'Y', 'Y'
    FROM N_EMI_LAB_Page_Access_2 pa
    JOIN @Akses a ON a.UserId = pa.Id_User AND a.Id_Menu = pa.Id_Menu
    CROSS JOIN N_EMI_LAB_Klasifikasi_Aksi ka
    WHERE NOT EXISTS (
        SELECT 1 FROM N_EMI_LAB_Role_Menu_Access rma
        WHERE rma.Id_Page_Access = pa.Id_Page_Access
          AND rma.Id_Aksi = ka.Id_Klasifikasi_Actions
    );

    PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' baris aksi.';


    -- ========================================================================
    -- C. Akses konten (jenis analisa)
    -- ========================================================================
    -- Modul lama memfilter daftar kerja berdasarkan permission_konten.
    --
    --   Pelaksana  -> dibatasi sesuai kewenangan verifikasinya
    --                 (ratna: analisa lab, jati: look view QA, roby: PLT)
    --   VENGINE    -> seluruh jenis analisa, karena finalisasi menilai
    --                 sampel secara utuh lintas klasifikasi
    PRINT '';
    PRINT '--- C. Akses konten ---';

    -- C1. Pelaksana: mengikuti kewenangan verifikasi
    INSERT INTO N_EMI_LAB_Role_Konten_Access (Id_Page_Access, Id_Jenis_Analisa, Flag_Diizinkan)
    SELECT DISTINCT pa.Id_Page_Access, ja.id, 'Y'
    FROM N_EMI_LAB_Page_Access_2 pa
    JOIN @Akses a ON a.UserId = pa.Id_User AND a.Id_Menu = pa.Id_Menu
    JOIN N_EMI_LAB_Verifikasi_Kewenangan k
        ON LOWER(k.Id_User) = LOWER(pa.Id_User) AND k.Flag_Aktif = 'Y'
    JOIN N_EMI_LAB_Jenis_Analisa ja
        ON ja.Kode_Aktivitas_Lab = k.Kode_Aktivitas_Lab
       AND (k.Id_Jenis_Analisa IS NULL OR k.Id_Jenis_Analisa = ja.id)
    WHERE LOWER(pa.Id_User) <> 'vengine'
      AND NOT EXISTS (
        SELECT 1 FROM N_EMI_LAB_Role_Konten_Access rka
        WHERE rka.Id_Page_Access = pa.Id_Page_Access
          AND rka.Id_Jenis_Analisa = ja.id
    );

    PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' baris konten (pelaksana).';

    -- C2. VENGINE: seluruh jenis analisa berklasifikasi
    INSERT INTO N_EMI_LAB_Role_Konten_Access (Id_Page_Access, Id_Jenis_Analisa, Flag_Diizinkan)
    SELECT DISTINCT pa.Id_Page_Access, ja.id, 'Y'
    FROM N_EMI_LAB_Page_Access_2 pa
    JOIN @Akses a ON a.UserId = pa.Id_User AND a.Id_Menu = pa.Id_Menu
    CROSS JOIN N_EMI_LAB_Jenis_Analisa ja
    WHERE LOWER(pa.Id_User) = 'vengine'
      AND ja.Kode_Aktivitas_Lab IS NOT NULL
      AND NOT EXISTS (
        SELECT 1 FROM N_EMI_LAB_Role_Konten_Access rka
        WHERE rka.Id_Page_Access = pa.Id_Page_Access
          AND rka.Id_Jenis_Analisa = ja.id
    );

    PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' baris konten (VENGINE).';

COMMIT TRANSACTION;
PRINT '';
PRINT '============================================================';
PRINT ' AKSES SANDBOX SELESAI.';
PRINT '============================================================';

END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    PRINT '';
    PRINT '### GAGAL — SEMUA PERUBAHAN DIBATALKAN (ROLLBACK) ###';
    PRINT 'Pesan : ' + ERROR_MESSAGE();
    PRINT 'Baris : ' + CAST(ERROR_LINE() AS VARCHAR(10));
    THROW;
END CATCH;
GO


-- ============================================================================
-- VERIFIKASI
-- ============================================================================
SET NOCOUNT ON;
PRINT '';
PRINT '--- Verifikasi akses sandbox ---';

SELECT
    pa.Id_User                           AS User_Sandbox,
    m.Nama_Menu                          AS Menu,
    COUNT(DISTINCT rma.Id_Aksi)          AS Jumlah_Aksi,
    COUNT(DISTINCT rka.Id_Jenis_Analisa) AS Jumlah_Analisa,
    CASE WHEN COUNT(DISTINCT rma.Id_Aksi) > 0 THEN 'LULUS' ELSE 'GAGAL' END AS HASIL
FROM N_EMI_LAB_Page_Access_2 pa
JOIN N_EMI_LAB_Menus m
    ON m.Id_Menu = pa.Id_Menu
LEFT JOIN N_EMI_LAB_Role_Menu_Access rma
    ON rma.Id_Page_Access = pa.Id_Page_Access AND rma.Flag_Diizinkan = 'Y'
LEFT JOIN N_EMI_LAB_Role_Konten_Access rka
    ON rka.Id_Page_Access = pa.Id_Page_Access AND rka.Flag_Diizinkan = 'Y'
WHERE LOWER(pa.Id_User) IN ('ratna', 'jati', 'roby', 'vengine')
  AND m.Url_Menu IN ('/lab/home', '/validasi-trial/produksi',
                     '/verifikasi-hasil-analisa', '/finalisai/trial-produksi')
GROUP BY pa.Id_User, m.Nama_Menu
ORDER BY pa.Id_User, m.Nama_Menu;
