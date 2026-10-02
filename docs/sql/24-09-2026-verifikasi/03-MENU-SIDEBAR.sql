-- ============================================================================
-- 03-MENU-SIDEBAR.sql
-- Tanggal : 24-09-2026
-- Modul   : VERIFIKASI HASIL ANALISA
--
-- Menambahkan satu entri menu pada sidebar aplikasi.
--
-- SIFAT:
--   * IDEMPOTEN — hanya menambah bila Url_Menu belum terdaftar.
--   * Hanya INSERT satu baris ke N_EMI_LAB_Menus. Tidak mengubah menu lain.
--
-- Letak menu mengikuti pola menu yang sudah ada:
--   Header 'LIMS' > Sub 'Verifikasi' > 'Verifikasi Hasil Analisa'
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;

PRINT '--- Menu sidebar: Verifikasi Hasil Analisa ---';

INSERT INTO N_EMI_LAB_Menus (Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Nama_Header, Sub_Header)
SELECT '001', 'Verifikasi Hasil Analisa', 'fas fa-clipboard-check',
       '/verifikasi-hasil-analisa', 'LIMS', 'Verifikasi'
WHERE NOT EXISTS (
    SELECT 1 FROM N_EMI_LAB_Menus WHERE Url_Menu = '/verifikasi-hasil-analisa'
);

PRINT '  ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' menu ditambahkan (0 = sudah ada).';

-- Verifikasi
SELECT 'Menu terpasang' AS Pemeriksaan,
       Id_Menu, Nama_Menu, Url_Menu, Nama_Header, Sub_Header,
       CASE WHEN Id_Menu IS NOT NULL THEN 'LULUS' ELSE 'GAGAL' END AS HASIL
FROM N_EMI_LAB_Menus
WHERE Url_Menu = '/verifikasi-hasil-analisa';
