-- ============================================================================
-- 10-TAMBAH-KOLOM-MESIN.sql
-- Tanggal : 25-09-2026
-- Modul   : VERIFIKASI HASIL ANALISA - identitas mesin pada rekaman
--
-- LATAR:
--   Satu nomor PO dan batch dapat dipakai oleh beberapa sampel yang diproses
--   pada mesin berbeda. Tanpa identitas mesin, dua baris di layar verifikasi
--   tampak serupa padahal berasal dari mesin yang tidak sama — dan batas
--   kelayakan di N_EMI_LAB_Standar_Rentang memang ditentukan per mesin,
--   sehingga mesin ikut menentukan hasilnya layak atau tidak.
--
-- PERUBAHAN:
--   Menambah Id_Mesin dan Nama_Mesin pada header verifikasi, lalu mengisinya
--   untuk baris yang sudah ada. Nama_Mesin disimpan sebagai salinan (bukan
--   hanya id) supaya jejak audit tetap terbaca meski master mesin berubah
--   atau baris masternya dihapus di kemudian hari.
--
-- SIFAT:
--   * IDEMPOTEN - aman dijalankan berulang.
--   * Hanya MENAMBAH kolom pada tabel milik modul verifikasi.
--   * Tidak menyentuh satu pun tabel atau fitur lama.
--
-- PRASYARAT: 01..09 sudah dijalankan.
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;

PRINT '============================================================';
PRINT ' IDENTITAS MESIN PADA HEADER VERIFIKASI';
PRINT ' Database : ' + DB_NAME();
PRINT '============================================================';

BEGIN TRY
BEGIN TRANSACTION;

    -- ========================================================================
    -- 1. Kolom baru
    -- ========================================================================
    PRINT '';
    PRINT '--- 1. Kolom ---';

    IF NOT EXISTS (SELECT 1 FROM sys.columns
                   WHERE object_id = OBJECT_ID('N_EMI_LAB_Verifikasi_Header')
                     AND name = 'Id_Mesin')
    BEGIN
        ALTER TABLE N_EMI_LAB_Verifikasi_Header ADD Id_Mesin INT NULL;
        PRINT '  [+] Id_Mesin ditambahkan.';
    END
    ELSE
        PRINT '  [=] Id_Mesin sudah ada.';

    IF NOT EXISTS (SELECT 1 FROM sys.columns
                   WHERE object_id = OBJECT_ID('N_EMI_LAB_Verifikasi_Header')
                     AND name = 'Nama_Mesin')
    BEGIN
        ALTER TABLE N_EMI_LAB_Verifikasi_Header ADD Nama_Mesin VARCHAR(50) NULL;
        PRINT '  [+] Nama_Mesin ditambahkan.';
    END
    ELSE
        PRINT '  [=] Nama_Mesin sudah ada.';

COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    PRINT '';
    PRINT '### GAGAL - SEMUA PERUBAHAN DIBATALKAN (ROLLBACK) ###';
    PRINT 'Pesan : ' + ERROR_MESSAGE();
    PRINT 'Baris : ' + CAST(ERROR_LINE() AS VARCHAR(10));
    THROW;
END CATCH;
GO


-- ============================================================================
-- Pengisian data lama — batch terpisah, karena kolom di atas baru dikenali
-- pengurai setelah ALTER TABLE selesai.
-- ============================================================================
SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRY
BEGIN TRANSACTION;

    PRINT '';
    PRINT '--- 2. Pengisian data lama ---';

    UPDATE h
    SET h.Id_Mesin   = p.Id_Mesin,
        h.Nama_Mesin = (SELECT TOP 1 m.Nama_Mesin FROM EMI_Master_Mesin m
                        WHERE m.Id_Master_Mesin = p.Id_Mesin)
    FROM N_EMI_LAB_Verifikasi_Header h
    JOIN N_EMI_LAB_PO_Sampel p ON p.No_Sampel = h.No_Sampel
    WHERE h.Id_Mesin IS NULL
      AND p.Id_Mesin IS NOT NULL;

    PRINT '  [~] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' header diisi.';

COMMIT TRANSACTION;
PRINT '';
PRINT '============================================================';
PRINT ' SELESAI.';
PRINT '============================================================';

END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    PRINT '';
    PRINT '### GAGAL - SEMUA PERUBAHAN DIBATALKAN (ROLLBACK) ###';
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
PRINT '--- Mesin pada rekaman verifikasi ---';

SELECT h.No_Sampel, h.No_Po, h.No_Batch,
       h.Id_Mesin, h.Nama_Mesin, h.Kode_Status
FROM N_EMI_LAB_Verifikasi_Header h
ORDER BY h.No_Sampel;

PRINT '';
PRINT '--- Sebaran mesin pada antrean ---';

SELECT p.Id_Mesin,
       (SELECT TOP 1 m.Nama_Mesin FROM EMI_Master_Mesin m
        WHERE m.Id_Master_Mesin = p.Id_Mesin) AS Nama_Mesin,
       COUNT(DISTINCT p.No_Sampel) AS Jumlah_Sampel
FROM N_EMI_LAB_PO_Sampel p
WHERE p.No_Sampel LIKE 'FS0926-%'
GROUP BY p.Id_Mesin
ORDER BY p.Id_Mesin;


/* ============================================================================
   ROLLBACK - buka komentar bila kolom ingin dicabut kembali.
   ============================================================================

ALTER TABLE N_EMI_LAB_Verifikasi_Header DROP COLUMN Nama_Mesin;
ALTER TABLE N_EMI_LAB_Verifikasi_Header DROP COLUMN Id_Mesin;

   ==========================================================================*/
