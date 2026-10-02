-- ============================================================================
-- 99-ROLLBACK-LIFECYCLE.sql  —  Batalkan 01-STRUKTUR-LIFECYCLE.sql (DDL)
-- Tanggal : 26-09-2026
-- Akun    : WAJIB akun ber-hak DDL (db_owner / db_ddladmin).
--
-- PERINGATAN:
--   * Kolom yang dibuang ikut membuang isinya: alasan resampling yang diketik
--     validator dan data pengunggah foto akan HILANG. Salin dulu bila perlu.
--   * Kode aplikasi versi 26-09-2026 menulis ke kolom-kolom ini. Kembalikan
--     kodenya ke versi sebelumnya SEBELUM menjalankan script ini.
--   * Kunci unik approval dikembalikan TANPA Tahapan_Ke. Bila sudah ada
--     validasi putaran resampling oleh orang yang sama pada sampel tunggal,
--     pembuatan ulang index lama akan GAGAL karena data kembar. Script
--     berhenti dan menampilkan baris yang bentrok; index baru dibiarkan.
--   * 02-KOREKSI-JEJAK-BACKFILL.sql TIDAK dibatalkan di sini — koreksinya
--     meluruskan data yang salah, bukan menambah fitur.
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

PRINT '99-ROLLBACK-LIFECYCLE  —  ' + DB_NAME();

-- Index baca
IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_EMI_LAB_Uji_Sampel') AND name = 'IX_UjiSampel_Sampel_Analisa_Tahapan')
    DROP INDEX IX_UjiSampel_Sampel_Analisa_Tahapan ON N_EMI_LAB_Uji_Sampel;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_EMI_LAB_Activity_Uji_Sampel') AND name = 'IX_ActivityUjiSampel_Sampel_Analisa')
    DROP INDEX IX_ActivityUjiSampel_Sampel_Analisa ON N_EMI_LAB_Activity_Uji_Sampel;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_EMI_LAB_Uji_Sampel_Resampling_Log') AND name = 'IX_ResamplingLog_Sampel_Analisa')
    DROP INDEX IX_ResamplingLog_Sampel_Analisa ON N_EMI_LAB_Uji_Sampel_Resampling_Log;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_EMI_LAB_Berkas_Uji_Lab') AND name = 'IX_BerkasUjiLab_Sampel_Faktur')
    DROP INDEX IX_BerkasUjiLab_Sampel_Faktur ON N_EMI_LAB_Berkas_Uji_Lab;
PRINT '  [-] index baca dibuang';

-- Kolom Resampling_Log
IF OBJECT_ID('DF_ResamplingLog_DibuatPada', 'D') IS NOT NULL
    ALTER TABLE N_EMI_LAB_Uji_Sampel_Resampling_Log DROP CONSTRAINT DF_ResamplingLog_DibuatPada;
IF COL_LENGTH('N_EMI_LAB_Uji_Sampel_Resampling_Log', 'Dibuat_Pada') IS NOT NULL
    ALTER TABLE N_EMI_LAB_Uji_Sampel_Resampling_Log DROP COLUMN Dibuat_Pada;
IF COL_LENGTH('N_EMI_LAB_Uji_Sampel_Resampling_Log', 'Alasan') IS NOT NULL
    ALTER TABLE N_EMI_LAB_Uji_Sampel_Resampling_Log DROP COLUMN Alasan;
PRINT '  [-] kolom Resampling_Log dibuang';

-- Kolom Berkas_Uji_Lab
IF OBJECT_ID('DF_BerkasUjiLab_DibuatPada', 'D') IS NOT NULL
    ALTER TABLE N_EMI_LAB_Berkas_Uji_Lab DROP CONSTRAINT DF_BerkasUjiLab_DibuatPada;
IF COL_LENGTH('N_EMI_LAB_Berkas_Uji_Lab', 'Dibuat_Pada') IS NOT NULL
    ALTER TABLE N_EMI_LAB_Berkas_Uji_Lab DROP COLUMN Dibuat_Pada;
IF COL_LENGTH('N_EMI_LAB_Berkas_Uji_Lab', 'Id_User_Nonaktif') IS NOT NULL
    ALTER TABLE N_EMI_LAB_Berkas_Uji_Lab DROP COLUMN Id_User_Nonaktif;
IF COL_LENGTH('N_EMI_LAB_Berkas_Uji_Lab', 'Dinonaktifkan_Pada') IS NOT NULL
    ALTER TABLE N_EMI_LAB_Berkas_Uji_Lab DROP COLUMN Dinonaktifkan_Pada;
IF COL_LENGTH('N_EMI_LAB_Berkas_Uji_Lab', 'Flag_Nonaktif') IS NOT NULL
    ALTER TABLE N_EMI_LAB_Berkas_Uji_Lab DROP COLUMN Flag_Nonaktif;
IF COL_LENGTH('N_EMI_LAB_Berkas_Uji_Lab', 'Tahapan_Ke') IS NOT NULL
    ALTER TABLE N_EMI_LAB_Berkas_Uji_Lab DROP COLUMN Tahapan_Ke;
IF COL_LENGTH('N_EMI_LAB_Berkas_Uji_Lab', 'Id_User') IS NOT NULL
    ALTER TABLE N_EMI_LAB_Berkas_Uji_Lab DROP COLUMN Id_User;
PRINT '  [-] kolom Berkas_Uji_Lab dibuang';
GO

-- Kunci unik approval kembali tanpa Tahapan_Ke — hanya bila datanya muat.
IF EXISTS (
    SELECT 1 FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas
    GROUP BY No_Sampel, No_Sub_Sampel, Id_Jenis_Analisa, Jenis_Approval, Id_User, Id_Pembanding
    HAVING COUNT(*) > 1)
BEGIN
    PRINT '  [!] Kunci unik lama TIDAK dipulihkan: ada approval per putaran yang akan bentrok.';
    SELECT No_Sampel, No_Sub_Sampel, Id_Jenis_Analisa, Jenis_Approval, Id_User, Id_Pembanding, COUNT(*) AS Jumlah
    FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas
    GROUP BY No_Sampel, No_Sub_Sampel, Id_Jenis_Analisa, Jenis_Approval, Id_User, Id_Pembanding
    HAVING COUNT(*) > 1;
END
ELSE
BEGIN
    IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_EMI_LAB_Hasil_Uji_Approval_Aktivitas') AND name = 'UX_ApprovalAktivitas_NonPlt')
        DROP INDEX UX_ApprovalAktivitas_NonPlt ON N_EMI_LAB_Hasil_Uji_Approval_Aktivitas;
    CREATE UNIQUE NONCLUSTERED INDEX UX_ApprovalAktivitas_NonPlt
        ON N_EMI_LAB_Hasil_Uji_Approval_Aktivitas (No_Sampel, No_Sub_Sampel, Id_Jenis_Analisa, Jenis_Approval, Id_User)
        WHERE Id_Pembanding IS NULL;

    IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_EMI_LAB_Hasil_Uji_Approval_Aktivitas') AND name = 'UX_ApprovalAktivitas_Plt')
        DROP INDEX UX_ApprovalAktivitas_Plt ON N_EMI_LAB_Hasil_Uji_Approval_Aktivitas;
    CREATE UNIQUE NONCLUSTERED INDEX UX_ApprovalAktivitas_Plt
        ON N_EMI_LAB_Hasil_Uji_Approval_Aktivitas (No_Sampel, No_Sub_Sampel, Id_Jenis_Analisa, Jenis_Approval, Id_User, Id_Pembanding)
        WHERE Id_Pembanding IS NOT NULL;
    PRINT '  [=] kunci unik approval dikembalikan seperti 24-09-2026';
END
GO

PRINT '99-ROLLBACK-LIFECYCLE SELESAI';
GO
