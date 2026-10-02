-- ============================================================================
-- 01-STRUKTUR-LIFECYCLE.sql  —  Kolom & index pendukung Sample Lifecycle (DDL)
-- Tanggal : 26-09-2026
-- Target  : PRODUCTION (emi_db_real) dan staging (emi_tm_demo)
-- Akun    : WAJIB akun ber-hak DDL (db_owner / db_ddladmin, mis. sa).
--           srv_lab_usr TIDAK bisa menjalankan ALTER TABLE / CREATE INDEX.
--
-- PRASYARAT:
--   docs/sql/24-09-2026/          (01-STRUKTUR, 02-BACKFILL, 03-VERIFIKASI)
--   docs/sql/24-09-2026-verifikasi/01-STRUKTUR-VERIFIKASI.sql
--
-- ISI:
--   A. N_EMI_LAB_Uji_Sampel_Resampling_Log
--        + Alasan        alasan resampling yang DIKETIK validator
--                        (kolom Keterangan tetap berisi teks otomatis sistem)
--        + Dibuat_Pada   jam server saat baris ditulis
--   B. N_EMI_LAB_Berkas_Uji_Lab
--        + Id_User, Tahapan_Ke, Dibuat_Pada        siapa & kapan foto diunggah
--        + Flag_Nonaktif, Dinonaktifkan_Pada,
--          Id_User_Nonaktif                        foto putaran yang ditolak
--                                                  dinonaktifkan, TIDAK dihapus
--   C. Index untuk membaca lifecycle per sampel
--        N_EMI_LAB_Uji_Sampel di production belum punya index SAMA SEKALI
--        (±213 ribu baris), sehingga setiap pembacaan per sampel memindai
--        seluruh tabel.
--   D. Kunci unik approval ikut Tahapan_Ke
--        Tanpa ini, validasi putaran resampling oleh orang yang sama pada
--        sampel tunggal (nomor sampel tidak berubah) ditolak sebagai ganda,
--        sehingga validasi putaran 2 tidak pernah tercatat.
--
-- SIFAT:
--   * IDEMPOTEN — aman dijalankan ulang; bagian yang sudah ada dilewati.
--   * Hanya MENAMBAH kolom nullable dan index. Tidak ada data yang diubah.
--   * Kode lama tetap berjalan: tidak ada kolom yang diwajibkan.
--
-- CATATAN PRODUCTION:
--   Pembuatan index pada N_EMI_LAB_Uji_Sampel mengunci tabel sebentar
--   (edisi Express tidak mendukung ONLINE = ON). Jalankan di luar jam sibuk.
--
-- ROLLBACK: 99-ROLLBACK-LIFECYCLE.sql
-- ============================================================================

SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
SET ANSI_PADDING ON;
SET ANSI_WARNINGS ON;
SET ARITHABORT ON;
SET CONCAT_NULL_YIELDS_NULL ON;
SET NUMERIC_ROUNDABORT OFF;
SET NOCOUNT ON;
GO

PRINT '============================================================';
PRINT ' 01-STRUKTUR-LIFECYCLE  —  ' + CONVERT(VARCHAR(19), GETDATE(), 120);
PRINT ' Database : ' + DB_NAME();
PRINT '============================================================';

IF OBJECT_ID('N_EMI_LAB_Hasil_Uji_Approval_Aktivitas', 'U') IS NULL
BEGIN
    RAISERROR('Tabel approval belum ada. Jalankan migrasi docs/sql/24-09-2026 lebih dulu.', 16, 1);
    SET NOEXEC ON;
END
GO

-- Jam server aplikasi: dbo.Get_Date_Time() bila ada (dipakai seluruh modul
-- lab), selain itu GETDATE().
DECLARE @jamServer NVARCHAR(40) =
    CASE WHEN OBJECT_ID('dbo.Get_Date_Time') IS NOT NULL
         THEN N'(dbo.Get_Date_Time())' ELSE N'(GETDATE())' END;
DECLARE @sql NVARCHAR(MAX);

-- ============================================================================
-- A. Resampling_Log
-- ============================================================================
PRINT '--- A. N_EMI_LAB_Uji_Sampel_Resampling_Log ---';

IF COL_LENGTH('N_EMI_LAB_Uji_Sampel_Resampling_Log', 'Alasan') IS NULL
BEGIN
    ALTER TABLE N_EMI_LAB_Uji_Sampel_Resampling_Log ADD Alasan VARCHAR(500) NULL;
    PRINT '  [+] Alasan';
END ELSE PRINT '  [=] Alasan sudah ada';

IF COL_LENGTH('N_EMI_LAB_Uji_Sampel_Resampling_Log', 'Dibuat_Pada') IS NULL
BEGIN
    SET @sql = N'ALTER TABLE N_EMI_LAB_Uji_Sampel_Resampling_Log ADD Dibuat_Pada DATETIME NULL '
             + N'CONSTRAINT DF_ResamplingLog_DibuatPada DEFAULT ' + @jamServer;
    EXEC sp_executesql @sql;
    PRINT '  [+] Dibuat_Pada';
END ELSE PRINT '  [=] Dibuat_Pada sudah ada';

-- ============================================================================
-- B. Berkas_Uji_Lab
-- ============================================================================
PRINT '--- B. N_EMI_LAB_Berkas_Uji_Lab ---';

IF COL_LENGTH('N_EMI_LAB_Berkas_Uji_Lab', 'Id_User') IS NULL
BEGIN
    ALTER TABLE N_EMI_LAB_Berkas_Uji_Lab ADD Id_User VARCHAR(30) NULL;
    PRINT '  [+] Id_User';
END ELSE PRINT '  [=] Id_User sudah ada';

IF COL_LENGTH('N_EMI_LAB_Berkas_Uji_Lab', 'Tahapan_Ke') IS NULL
BEGIN
    ALTER TABLE N_EMI_LAB_Berkas_Uji_Lab ADD Tahapan_Ke INT NULL;
    PRINT '  [+] Tahapan_Ke';
END ELSE PRINT '  [=] Tahapan_Ke sudah ada';

IF COL_LENGTH('N_EMI_LAB_Berkas_Uji_Lab', 'Dibuat_Pada') IS NULL
BEGIN
    SET @sql = N'ALTER TABLE N_EMI_LAB_Berkas_Uji_Lab ADD Dibuat_Pada DATETIME NULL '
             + N'CONSTRAINT DF_BerkasUjiLab_DibuatPada DEFAULT ' + @jamServer;
    EXEC sp_executesql @sql;
    PRINT '  [+] Dibuat_Pada';
END ELSE PRINT '  [=] Dibuat_Pada sudah ada';

IF COL_LENGTH('N_EMI_LAB_Berkas_Uji_Lab', 'Flag_Nonaktif') IS NULL
BEGIN
    ALTER TABLE N_EMI_LAB_Berkas_Uji_Lab ADD Flag_Nonaktif CHAR(1) NULL;
    PRINT '  [+] Flag_Nonaktif';
END ELSE PRINT '  [=] Flag_Nonaktif sudah ada';

IF COL_LENGTH('N_EMI_LAB_Berkas_Uji_Lab', 'Dinonaktifkan_Pada') IS NULL
BEGIN
    ALTER TABLE N_EMI_LAB_Berkas_Uji_Lab ADD Dinonaktifkan_Pada DATETIME NULL;
    PRINT '  [+] Dinonaktifkan_Pada';
END ELSE PRINT '  [=] Dinonaktifkan_Pada sudah ada';

IF COL_LENGTH('N_EMI_LAB_Berkas_Uji_Lab', 'Id_User_Nonaktif') IS NULL
BEGIN
    ALTER TABLE N_EMI_LAB_Berkas_Uji_Lab ADD Id_User_Nonaktif VARCHAR(30) NULL;
    PRINT '  [+] Id_User_Nonaktif';
END ELSE PRINT '  [=] Id_User_Nonaktif sudah ada';
GO

-- ============================================================================
-- C. Index baca lifecycle
-- ============================================================================
PRINT '--- C. Index ---';

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE object_id = OBJECT_ID('N_EMI_LAB_Uji_Sampel')
                 AND name = 'IX_UjiSampel_Sampel_Analisa_Tahapan')
BEGIN
    CREATE NONCLUSTERED INDEX IX_UjiSampel_Sampel_Analisa_Tahapan
        ON N_EMI_LAB_Uji_Sampel (No_Po_Sampel, Id_Jenis_Analisa, Tahapan_Ke)
        INCLUDE (No_Fak_Sub_Po, No_Faktur, Id_User, Tanggal, Jam, Status,
                 Flag_Selesai, Status_Keputusan_Sampel, Flag_Resampling);
    PRINT '  [+] IX_UjiSampel_Sampel_Analisa_Tahapan';
END ELSE PRINT '  [=] IX_UjiSampel_Sampel_Analisa_Tahapan sudah ada';

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE object_id = OBJECT_ID('N_EMI_LAB_Activity_Uji_Sampel')
                 AND name = 'IX_ActivityUjiSampel_Sampel_Analisa')
BEGIN
    CREATE NONCLUSTERED INDEX IX_ActivityUjiSampel_Sampel_Analisa
        ON N_EMI_LAB_Activity_Uji_Sampel (No_Po_Sampel, Id_Jenis_Analisa)
        INCLUDE (No_Fak_Sub_Po, Jenis_Aktivitas, Id_User, Tanggal, Jam);
    PRINT '  [+] IX_ActivityUjiSampel_Sampel_Analisa';
END ELSE PRINT '  [=] IX_ActivityUjiSampel_Sampel_Analisa sudah ada';

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE object_id = OBJECT_ID('N_EMI_LAB_Uji_Sampel_Resampling_Log')
                 AND name = 'IX_ResamplingLog_Sampel_Analisa')
BEGIN
    CREATE NONCLUSTERED INDEX IX_ResamplingLog_Sampel_Analisa
        ON N_EMI_LAB_Uji_Sampel_Resampling_Log (No_Po_Sampel, Id_Jenis_Analisa, Tahapan_Ke);
    PRINT '  [+] IX_ResamplingLog_Sampel_Analisa';
END ELSE PRINT '  [=] IX_ResamplingLog_Sampel_Analisa sudah ada';

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE object_id = OBJECT_ID('N_EMI_LAB_Berkas_Uji_Lab')
                 AND name = 'IX_BerkasUjiLab_Sampel_Faktur')
BEGIN
    CREATE NONCLUSTERED INDEX IX_BerkasUjiLab_Sampel_Faktur
        ON N_EMI_LAB_Berkas_Uji_Lab (No_Sampel, No_Faktur);
    PRINT '  [+] IX_BerkasUjiLab_Sampel_Faktur';
END ELSE PRINT '  [=] IX_BerkasUjiLab_Sampel_Faktur sudah ada';
GO

-- ============================================================================
-- D. Kunci unik approval ikut Tahapan_Ke
-- ============================================================================
-- Kunci baru LEBIH LONGGAR dari yang lama (satu kolom ditambahkan), sehingga
-- data yang sudah ada pasti lolos. Index lama dibuang hanya bila belum
-- memuat Tahapan_Ke.
PRINT '--- D. Kunci unik approval + Tahapan_Ke ---';

IF EXISTS (SELECT 1 FROM sys.indexes i
           WHERE i.object_id = OBJECT_ID('N_EMI_LAB_Hasil_Uji_Approval_Aktivitas')
             AND i.name = 'UX_ApprovalAktivitas_NonPlt'
             AND NOT EXISTS (SELECT 1 FROM sys.index_columns ic
                             JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
                             WHERE ic.object_id = i.object_id AND ic.index_id = i.index_id
                               AND c.name = 'Tahapan_Ke'))
BEGIN
    DROP INDEX UX_ApprovalAktivitas_NonPlt ON N_EMI_LAB_Hasil_Uji_Approval_Aktivitas;
    PRINT '  [-] UX_ApprovalAktivitas_NonPlt lama dibuang';
END

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE object_id = OBJECT_ID('N_EMI_LAB_Hasil_Uji_Approval_Aktivitas')
                 AND name = 'UX_ApprovalAktivitas_NonPlt')
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_ApprovalAktivitas_NonPlt
        ON N_EMI_LAB_Hasil_Uji_Approval_Aktivitas
           (No_Sampel, No_Sub_Sampel, Id_Jenis_Analisa, Tahapan_Ke, Jenis_Approval, Id_User)
        WHERE Id_Pembanding IS NULL;
    PRINT '  [+] UX_ApprovalAktivitas_NonPlt (+Tahapan_Ke)';
END ELSE PRINT '  [=] UX_ApprovalAktivitas_NonPlt sudah memuat Tahapan_Ke';

IF EXISTS (SELECT 1 FROM sys.indexes i
           WHERE i.object_id = OBJECT_ID('N_EMI_LAB_Hasil_Uji_Approval_Aktivitas')
             AND i.name = 'UX_ApprovalAktivitas_Plt'
             AND NOT EXISTS (SELECT 1 FROM sys.index_columns ic
                             JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
                             WHERE ic.object_id = i.object_id AND ic.index_id = i.index_id
                               AND c.name = 'Tahapan_Ke'))
BEGIN
    DROP INDEX UX_ApprovalAktivitas_Plt ON N_EMI_LAB_Hasil_Uji_Approval_Aktivitas;
    PRINT '  [-] UX_ApprovalAktivitas_Plt lama dibuang';
END

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE object_id = OBJECT_ID('N_EMI_LAB_Hasil_Uji_Approval_Aktivitas')
                 AND name = 'UX_ApprovalAktivitas_Plt')
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_ApprovalAktivitas_Plt
        ON N_EMI_LAB_Hasil_Uji_Approval_Aktivitas
           (No_Sampel, No_Sub_Sampel, Id_Jenis_Analisa, Tahapan_Ke, Jenis_Approval, Id_User, Id_Pembanding)
        WHERE Id_Pembanding IS NOT NULL;
    PRINT '  [+] UX_ApprovalAktivitas_Plt (+Tahapan_Ke)';
END ELSE PRINT '  [=] UX_ApprovalAktivitas_Plt sudah memuat Tahapan_Ke';
GO

-- ============================================================================
-- Verifikasi
-- ============================================================================
PRINT '';
PRINT '--- Verifikasi ---';
SELECT 'Resampling_Log' AS Tabel, name AS Kolom FROM sys.columns
 WHERE object_id = OBJECT_ID('N_EMI_LAB_Uji_Sampel_Resampling_Log') AND name IN ('Alasan', 'Dibuat_Pada')
UNION ALL
SELECT 'Berkas_Uji_Lab', name FROM sys.columns
 WHERE object_id = OBJECT_ID('N_EMI_LAB_Berkas_Uji_Lab')
   AND name IN ('Id_User', 'Tahapan_Ke', 'Dibuat_Pada', 'Flag_Nonaktif', 'Dinonaktifkan_Pada', 'Id_User_Nonaktif');

SELECT OBJECT_NAME(object_id) AS Tabel, name AS Index_Baru FROM sys.indexes
 WHERE name IN ('IX_UjiSampel_Sampel_Analisa_Tahapan', 'IX_ActivityUjiSampel_Sampel_Analisa',
                'IX_ResamplingLog_Sampel_Analisa', 'IX_BerkasUjiLab_Sampel_Faktur',
                'UX_ApprovalAktivitas_NonPlt', 'UX_ApprovalAktivitas_Plt');

PRINT '============================================================';
PRINT ' 01-STRUKTUR-LIFECYCLE SELESAI. Lanjut ke 02-KOREKSI-JEJAK-BACKFILL.sql';
PRINT '============================================================';
GO
SET NOEXEC OFF;
GO
