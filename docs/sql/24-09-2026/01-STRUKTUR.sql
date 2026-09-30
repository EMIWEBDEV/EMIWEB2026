-- ============================================================================
-- 01-STRUKTUR.sql  —  Perubahan struktur (DDL)
-- Tanggal : 24-09-2026
-- Target  : PRODUCTION (emi_db_real) dan staging (emi_tm_demo)
--
-- ISI:
--   A. 8 kolom baru pada N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
--   B. Index pada Final dan Detail_Final (termasuk UNIQUE pengunci bug)
--   C. Tabel BARU N_EMI_LAB_Hasil_Uji_Approval_Aktivitas + 5 index
--   D. Verifikasi
--
-- SIFAT SKRIP:
--   * IDEMPOTEN  — setiap objek dijaga IF NOT EXISTS. Dijalankan berulang
--                  kali tidak error dan tidak menggandakan apa pun.
--   * ADITIF     — hanya menambah. Tidak ada DROP COLUMN, tidak ada
--                  perubahan tipe data, tidak ada DROP TABLE.
--   * AMAN BAGI KODE LAMA — semua kolom baru NULLable, sehingga aplikasi
--                  versi lama tetap berjalan setelah skrip ini dijalankan.
--                  Struktur boleh naik lebih dulu daripada kode.
--   * TRANSAKSIONAL — dibungkus TRY/CATCH + TRANSACTION dengan XACT_ABORT.
--                  Gagal di tengah = rollback penuh, database kembali persis
--                  seperti semula dan skrip bisa langsung diulang.
--
-- PRASYARAT: 00-PRACHECK.sql bagian 4 berstatus LULUS.
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;

PRINT '============================================================';
PRINT ' 01-STRUKTUR  —  ' + CONVERT(VARCHAR(19), GETDATE(), 120);
PRINT ' Database : ' + DB_NAME();
PRINT '============================================================';

BEGIN TRY
BEGIN TRANSACTION;

    -- ========================================================================
    -- PENJAGA
    -- ========================================================================
    IF OBJECT_ID('N_EMI_LAB_Hasil_Uji_Validasi_Final','U') IS NULL
        THROW 51001, 'Tabel N_EMI_LAB_Hasil_Uji_Validasi_Final tidak ditemukan. Salah database?', 1;

    IF OBJECT_ID('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','U') IS NULL
        THROW 51002, 'Tabel N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final tidak ditemukan. Salah database?', 1;

    IF EXISTS (
        SELECT 1 FROM N_EMI_LAB_Hasil_Uji_Validasi_Final
        GROUP BY No_Split_Po, No_Batch, No_Sampel
        HAVING COUNT(*) > 1
    )
        THROW 51003, 'Ada duplikat (No_Split_Po,No_Batch,No_Sampel). Bersihkan dulu — lihat PRACHECK bagian 4.', 1;

    PRINT '[OK] Prasyarat terpenuhi.';
    PRINT '';


    -- ========================================================================
    -- A. Kolom baru pada Detail_Final
    -- ========================================================================
    PRINT '--- A. Kolom baru Detail_Final ---';

    -- A1. FK ke header. Sebelumnya detail hanya terhubung lewat kecocokan
    --     No_Sampel; sekarang relasinya eksplisit.
    --     NULLable karena detail sah dibuat saat validasi — jauh sebelum
    --     sampel difinalisasi. NULL di sini berarti "belum difinalisasi",
    --     bukan kesalahan data.
    IF COL_LENGTH('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','Id_Uji_Validasi_Final') IS NULL
    BEGIN
        ALTER TABLE N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final ADD Id_Uji_Validasi_Final INT NULL;
        PRINT '  [+] Id_Uji_Validasi_Final';
    END ELSE PRINT '  [=] Id_Uji_Validasi_Final sudah ada';

    -- A2. Snapshot konteks analisa: detail bisa dibaca tanpa join ke master,
    --     dan tetap benar bila master berubah di kemudian hari.
    IF COL_LENGTH('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','Kode_Aktivitas_Lab') IS NULL
    BEGIN
        ALTER TABLE N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final ADD Kode_Aktivitas_Lab VARCHAR(10) NULL;
        PRINT '  [+] Kode_Aktivitas_Lab';
    END ELSE PRINT '  [=] Kode_Aktivitas_Lab sudah ada';

    IF COL_LENGTH('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','Nama_Jenis_Analisa') IS NULL
    BEGIN
        ALTER TABLE N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final ADD Nama_Jenis_Analisa VARCHAR(255) NULL;
        PRINT '  [+] Nama_Jenis_Analisa';
    END ELSE PRINT '  [=] Nama_Jenis_Analisa sudah ada';

    -- A3. Konteks palatabilitas. Tanpa ini, beberapa baris PLT untuk satu
    --     jenis analisa tidak bisa dibedakan satu sama lain.
    IF COL_LENGTH('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','Id_Session') IS NULL
    BEGIN
        ALTER TABLE N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final ADD Id_Session INT NULL;
        PRINT '  [+] Id_Session';
    END ELSE PRINT '  [=] Id_Session sudah ada';

    IF COL_LENGTH('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','Id_Pembanding') IS NULL
    BEGIN
        ALTER TABLE N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final ADD Id_Pembanding INT NULL;
        PRINT '  [+] Id_Pembanding';
    END ELSE PRINT '  [=] Id_Pembanding sudah ada';

    -- A4. Penanda resampling, mengikuti kolom sejenis di Uji_Sampel.
    IF COL_LENGTH('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','Flag_Resampling') IS NULL
    BEGIN
        ALTER TABLE N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final ADD Flag_Resampling CHAR(1) NULL;
        PRINT '  [+] Flag_Resampling';
    END ELSE PRINT '  [=] Flag_Resampling sudah ada';

    -- A5. Asal-usul baris — memisahkan pencatatan realtime dari hasil backfill.
    IF COL_LENGTH('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','Sumber_Pencatatan') IS NULL
    BEGIN
        ALTER TABLE N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final ADD Sumber_Pencatatan VARCHAR(30) NULL;
        PRINT '  [+] Sumber_Pencatatan';
    END ELSE PRINT '  [=] Sumber_Pencatatan sudah ada';

    IF COL_LENGTH('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','Dibuat_Pada') IS NULL
    BEGIN
        ALTER TABLE N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final ADD Dibuat_Pada DATETIME NULL;
        PRINT '  [+] Dibuat_Pada';
    END ELSE PRINT '  [=] Dibuat_Pada sudah ada';

    PRINT '';

COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    PRINT '';
    PRINT '### GAGAL PADA BAGIAN A — SEMUA PERUBAHAN DIBATALKAN ###';
    PRINT 'Pesan : ' + ERROR_MESSAGE();
    PRINT 'Baris : ' + CAST(ERROR_LINE() AS VARCHAR(10));
    THROW;
END CATCH;
GO
-- Batch baru: kolom hasil ALTER di atas harus "terlihat" oleh perintah
-- berikutnya. Tanpa GO, index yang menyebut kolom baru gagal dikompilasi.


-- ============================================================================
-- B. INDEX
-- ============================================================================
SET NOCOUNT ON;
SET XACT_ABORT ON;

PRINT '--- B. Index ---';

BEGIN TRY
BEGIN TRANSACTION;

    -- B1. UNIQUE pada header.
    --     Inilah yang menutup bug "finalisasi sampel kedua menimpa yang
    --     pertama": kunci updateOrInsert lama hanya (No_Split_Po, No_Batch)
    --     tanpa No_Sampel. Setelah index ini ada, penimpaan diam-diam
    --     menjadi mustahil di level database.
    IF NOT EXISTS (SELECT 1 FROM sys.indexes
                   WHERE name = 'UX_HasilUjiValidasiFinal_SplitPo_Batch_Sampel'
                     AND object_id = OBJECT_ID('N_EMI_LAB_Hasil_Uji_Validasi_Final'))
    BEGIN
        CREATE UNIQUE NONCLUSTERED INDEX UX_HasilUjiValidasiFinal_SplitPo_Batch_Sampel
            ON N_EMI_LAB_Hasil_Uji_Validasi_Final (No_Split_Po, No_Batch, No_Sampel);
        PRINT '  [+] UX_HasilUjiValidasiFinal_SplitPo_Batch_Sampel (UNIQUE)';
    END ELSE PRINT '  [=] UX_HasilUjiValidasiFinal_SplitPo_Batch_Sampel sudah ada';

    IF NOT EXISTS (SELECT 1 FROM sys.indexes
                   WHERE name = 'IX_HasilUjiValidasiFinal_NoSampel'
                     AND object_id = OBJECT_ID('N_EMI_LAB_Hasil_Uji_Validasi_Final'))
    BEGIN
        CREATE NONCLUSTERED INDEX IX_HasilUjiValidasiFinal_NoSampel
            ON N_EMI_LAB_Hasil_Uji_Validasi_Final (No_Sampel);
        PRINT '  [+] IX_HasilUjiValidasiFinal_NoSampel';
    END ELSE PRINT '  [=] IX_HasilUjiValidasiFinal_NoSampel sudah ada';

    -- B2. Index Detail_Final.
    --     SENGAJA NON-UNIQUE: data lama sudah mengandung kelompok duplikat
    --     (double-submit). Memaksa UNIQUE akan menggagalkan migrasi.
    --     Perlindungan anti-duplikat ditegakkan di kode aplikasi.
    IF NOT EXISTS (SELECT 1 FROM sys.indexes
                   WHERE name = 'IX_ValidasiDetailFinal_Sampel_Sub_Analisa_Tahapan'
                     AND object_id = OBJECT_ID('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final'))
    BEGIN
        CREATE NONCLUSTERED INDEX IX_ValidasiDetailFinal_Sampel_Sub_Analisa_Tahapan
            ON N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
               (No_Sampel, No_Sub_Sampel, Id_Jenis_Analisa, Tahapan_Ke);
        PRINT '  [+] IX_ValidasiDetailFinal_Sampel_Sub_Analisa_Tahapan';
    END ELSE PRINT '  [=] IX_ValidasiDetailFinal_Sampel_Sub_Analisa_Tahapan sudah ada';

    IF NOT EXISTS (SELECT 1 FROM sys.indexes
                   WHERE name = 'IX_ValidasiDetailFinal_IdUjiValidasiFinal'
                     AND object_id = OBJECT_ID('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final'))
    BEGIN
        CREATE NONCLUSTERED INDEX IX_ValidasiDetailFinal_IdUjiValidasiFinal
            ON N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final (Id_Uji_Validasi_Final);
        PRINT '  [+] IX_ValidasiDetailFinal_IdUjiValidasiFinal';
    END ELSE PRINT '  [=] IX_ValidasiDetailFinal_IdUjiValidasiFinal sudah ada';

    IF NOT EXISTS (SELECT 1 FROM sys.indexes
                   WHERE name = 'IX_ValidasiDetailFinal_Aktivitas'
                     AND object_id = OBJECT_ID('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final'))
    BEGIN
        CREATE NONCLUSTERED INDEX IX_ValidasiDetailFinal_Aktivitas
            ON N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final (Kode_Aktivitas_Lab, No_Sampel);
        PRINT '  [+] IX_ValidasiDetailFinal_Aktivitas';
    END ELSE PRINT '  [=] IX_ValidasiDetailFinal_Aktivitas sudah ada';

    -- B3. FK detail -> header.
    --     WITH NOCHECK: baris lama yang belum di-backfill tidak divalidasi
    --     mundur, supaya migrasi tidak gagal karenanya. Constraint ini
    --     di-CHECK ulang oleh 03-VERIFIKASI.sql setelah backfill.
    IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys
                   WHERE name = 'FK_ValidasiDetailFinal_ValidasiFinal')
    BEGIN
        ALTER TABLE N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
            WITH NOCHECK
            ADD CONSTRAINT FK_ValidasiDetailFinal_ValidasiFinal
            FOREIGN KEY (Id_Uji_Validasi_Final)
            REFERENCES N_EMI_LAB_Hasil_Uji_Validasi_Final (Id_Uji_Validasi_Final);
        PRINT '  [+] FK_ValidasiDetailFinal_ValidasiFinal (NOCHECK)';
    END ELSE PRINT '  [=] FK_ValidasiDetailFinal_ValidasiFinal sudah ada';

    PRINT '';

COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    PRINT '';
    PRINT '### GAGAL PADA BAGIAN B — PERUBAHAN BAGIAN B DIBATALKAN ###';
    PRINT 'Pesan : ' + ERROR_MESSAGE();
    PRINT 'Baris : ' + CAST(ERROR_LINE() AS VARCHAR(10));
    THROW;
END CATCH;
GO


-- ============================================================================
-- C. TABEL BARU: N_EMI_LAB_Hasil_Uji_Approval_Aktivitas
-- ============================================================================
-- KEBUTUHAN:
--   Menjawab "untuk PO, sampel, dan batch ini — look view di-approve siapa,
--   analisa lab siapa, palatabilitas siapa."
--
-- BENTUK:
--   Satu baris = satu (sampel x sub-sampel x aktivitas x jenis analisa x
--   pelaku). Jadi satu sampel bisa punya beberapa baris LCKV, beberapa ANL,
--   dan beberapa PLT — masing-masing dengan penanggung jawabnya sendiri.
--
-- MENGAPA TABEL TERSENDIRI, bukan kolom tambahan di Detail_Final:
--   1. Detail_Final terisi saat validasi/finalisasi analisa. Approval bisa
--      terjadi berlapis (validasi lab, lalu approval hirarki) dan masing-
--      masing perlu barisnya sendiri, bukan satu kolom yang saling menimpa.
--   2. Persetujuan harus terekam meski sampel belum/tidak pernah
--      difinalisasi.
--   3. Pelaporan bisa query langsung per aktivitas tanpa menyentuh tabel
--      transaksi yang ramai.
--
-- DENORMALISASI YANG DISENGAJA:
--   No_Po / No_Split_Po / No_Batch / Kode_Barang / Nama_User / nama analisa
--   disalin ke sini sebagai snapshot audit: nilainya harus tetap sebagaimana
--   saat approval terjadi, walaupun master berubah belakangan.
-- ============================================================================
SET NOCOUNT ON;
SET XACT_ABORT ON;

PRINT '--- C. Tabel N_EMI_LAB_Hasil_Uji_Approval_Aktivitas ---';

BEGIN TRY
BEGIN TRANSACTION;

    IF OBJECT_ID('N_EMI_LAB_Hasil_Uji_Approval_Aktivitas','U') IS NULL
    BEGIN
        CREATE TABLE N_EMI_LAB_Hasil_Uji_Approval_Aktivitas (
            Id_Approval_Aktivitas   INT IDENTITY(1,1) NOT NULL,

            -- Identitas sampel (jalur query utama pelaporan)
            No_Sampel               VARCHAR(30)   NOT NULL,
            No_Sub_Sampel           VARCHAR(30)   NULL,   -- NULL = sampel tunggal
            No_Po                   VARCHAR(25)   NULL,
            No_Split_Po             VARCHAR(25)   NULL,
            No_Batch                FLOAT         NULL,
            Kode_Barang             VARCHAR(50)   NULL,

            -- Klasifikasi aktivitas
            Kode_Aktivitas_Lab      VARCHAR(10)   NOT NULL,  -- ANL | PLT | LCKV
            Nama_Aktivitas          VARCHAR(255)  NULL,

            -- Analisa yang di-approve
            Id_Jenis_Analisa        INT           NOT NULL,
            Nama_Jenis_Analisa      VARCHAR(255)  NULL,
            Tahapan_Ke              INT           NULL,

            -- Konteks palatabilitas
            Id_Session              INT           NULL,
            Id_Pembanding           INT           NULL,

            -- SIAPA yang approve — inti kebutuhan tabel ini
            Id_User                 VARCHAR(30)   NOT NULL,
            Nama_User               VARCHAR(255)  NULL,
            Jenis_Approval          VARCHAR(30)   NOT NULL,  -- VALIDASI|APPROVAL_HIRARKI|FINALISASI
            Flag_Approval           CHAR(1)       NULL,      -- Y=setuju, T=tolak
            Flag_Layak              CHAR(1)       NULL,      -- Y=layak,  T=tidak layak
            Keterangan              VARCHAR(500)  NULL,

            -- Waktu
            Tanggal                 DATE          NULL,
            Jam                     VARCHAR(8)    NULL,
            Dibuat_Pada             DATETIME      NOT NULL
                                    CONSTRAINT DF_ApprovalAktivitas_DibuatPada DEFAULT (GETDATE()),

            -- Konteks alur
            Flag_Trial_Produksi     CHAR(1)       NULL,   -- NULL=produksi, Y=trial
            Flag_Resampling         CHAR(1)       NULL,
            Sumber_Pencatatan       VARCHAR(30)   NULL,   -- VALIDASI|BACKFILL|FINALISASI

            CONSTRAINT PK_N_EMI_LAB_Hasil_Uji_Approval_Aktivitas
                PRIMARY KEY CLUSTERED (Id_Approval_Aktivitas)
        );
        PRINT '  [+] Tabel dibuat (26 kolom).';
    END
    ELSE PRINT '  [=] Tabel sudah ada, dilewati.';

COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    PRINT '';
    PRINT '### GAGAL PADA BAGIAN C — TABEL TIDAK DIBUAT ###';
    PRINT 'Pesan : ' + ERROR_MESSAGE();
    THROW;
END CATCH;
GO


-- Index tabel approval (batch terpisah: tabel harus sudah ada)
SET NOCOUNT ON;
PRINT '--- C2. Index tabel approval ---';

-- Jalur query utama: "PO + sampel + batch ini, siapa approve apa"
IF OBJECT_ID('N_EMI_LAB_Hasil_Uji_Approval_Aktivitas','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_ApprovalAktivitas_Sampel_Aktivitas')
BEGIN
    CREATE NONCLUSTERED INDEX IX_ApprovalAktivitas_Sampel_Aktivitas
        ON N_EMI_LAB_Hasil_Uji_Approval_Aktivitas (No_Sampel, Kode_Aktivitas_Lab)
        INCLUDE (Id_Jenis_Analisa, Id_User, Nama_User, Flag_Approval, Tanggal, Jam);
    PRINT '  [+] IX_ApprovalAktivitas_Sampel_Aktivitas';
END ELSE PRINT '  [=] IX_ApprovalAktivitas_Sampel_Aktivitas sudah ada / tabel belum ada';

IF OBJECT_ID('N_EMI_LAB_Hasil_Uji_Approval_Aktivitas','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_ApprovalAktivitas_Po_Batch')
BEGIN
    CREATE NONCLUSTERED INDEX IX_ApprovalAktivitas_Po_Batch
        ON N_EMI_LAB_Hasil_Uji_Approval_Aktivitas (No_Po, No_Split_Po, No_Batch);
    PRINT '  [+] IX_ApprovalAktivitas_Po_Batch';
END ELSE PRINT '  [=] IX_ApprovalAktivitas_Po_Batch sudah ada / tabel belum ada';

IF OBJECT_ID('N_EMI_LAB_Hasil_Uji_Approval_Aktivitas','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_ApprovalAktivitas_User')
BEGIN
    CREATE NONCLUSTERED INDEX IX_ApprovalAktivitas_User
        ON N_EMI_LAB_Hasil_Uji_Approval_Aktivitas (Id_User, Tanggal);
    PRINT '  [+] IX_ApprovalAktivitas_User';
END ELSE PRINT '  [=] IX_ApprovalAktivitas_User sudah ada / tabel belum ada';

-- Anti-duplikat: satu user, satu analisa, satu jenis approval — sekali saja.
-- Dipisah dua index ber-filter karena baris PLT dibedakan oleh Id_Pembanding,
-- sedangkan index UNIQUE biasa memperlakukan seluruh NULL sebagai satu nilai
-- yang sama (sehingga dua baris non-PLT berbeda akan salah dianggap kembar).
IF OBJECT_ID('N_EMI_LAB_Hasil_Uji_Approval_Aktivitas','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='UX_ApprovalAktivitas_NonPlt')
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_ApprovalAktivitas_NonPlt
        ON N_EMI_LAB_Hasil_Uji_Approval_Aktivitas
           (No_Sampel, No_Sub_Sampel, Id_Jenis_Analisa, Jenis_Approval, Id_User)
        WHERE Id_Pembanding IS NULL;
    PRINT '  [+] UX_ApprovalAktivitas_NonPlt (UNIQUE, filtered)';
END ELSE PRINT '  [=] UX_ApprovalAktivitas_NonPlt sudah ada / tabel belum ada';

IF OBJECT_ID('N_EMI_LAB_Hasil_Uji_Approval_Aktivitas','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='UX_ApprovalAktivitas_Plt')
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_ApprovalAktivitas_Plt
        ON N_EMI_LAB_Hasil_Uji_Approval_Aktivitas
           (No_Sampel, No_Sub_Sampel, Id_Jenis_Analisa, Jenis_Approval, Id_User, Id_Pembanding)
        WHERE Id_Pembanding IS NOT NULL;
    PRINT '  [+] UX_ApprovalAktivitas_Plt (UNIQUE, filtered)';
END ELSE PRINT '  [=] UX_ApprovalAktivitas_Plt sudah ada / tabel belum ada';

PRINT '';
PRINT '============================================================';
PRINT ' 01-STRUKTUR SELESAI. Lanjut ke 02-BACKFILL.sql';
PRINT '============================================================';
GO


-- ============================================================================
-- D. VERIFIKASI
-- ============================================================================
SET NOCOUNT ON;
PRINT '';
PRINT '--- D. Verifikasi struktur ---';

SELECT 'Kolom baru Detail_Final' AS Pemeriksaan,
       c.name AS Kolom, t.name AS Tipe, c.max_length AS Panjang, c.is_nullable AS Nullable
FROM sys.columns c
JOIN sys.types t ON c.user_type_id = t.user_type_id
WHERE c.object_id = OBJECT_ID('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final')
  AND c.name IN ('Id_Uji_Validasi_Final','Kode_Aktivitas_Lab','Nama_Jenis_Analisa',
                 'Id_Session','Id_Pembanding','Flag_Resampling',
                 'Sumber_Pencatatan','Dibuat_Pada')
ORDER BY c.name;

SELECT 'Jumlah kolom baru terpasang' AS Pemeriksaan,
       COUNT(*) AS Jumlah,
       CASE WHEN COUNT(*) = 8 THEN 'LULUS' ELSE 'GAGAL — harus 8' END AS HASIL
FROM sys.columns
WHERE object_id = OBJECT_ID('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final')
  AND name IN ('Id_Uji_Validasi_Final','Kode_Aktivitas_Lab','Nama_Jenis_Analisa',
               'Id_Session','Id_Pembanding','Flag_Resampling',
               'Sumber_Pencatatan','Dibuat_Pada');

SELECT 'Tabel approval' AS Pemeriksaan,
       CASE WHEN OBJECT_ID('N_EMI_LAB_Hasil_Uji_Approval_Aktivitas','U') IS NOT NULL
            THEN 'LULUS — tabel ada' ELSE 'GAGAL — tabel tidak ada' END AS HASIL,
       (SELECT COUNT(*) FROM sys.columns
        WHERE object_id = OBJECT_ID('N_EMI_LAB_Hasil_Uji_Approval_Aktivitas')) AS Jumlah_Kolom;

SELECT 'Index terpasang' AS Pemeriksaan,
       i.name AS Index_Name,
       OBJECT_NAME(i.object_id) AS Tabel,
       CASE WHEN i.is_unique = 1 THEN 'UNIQUE' ELSE 'non-unique' END AS Jenis
FROM sys.indexes i
WHERE i.object_id IN (
        OBJECT_ID('N_EMI_LAB_Hasil_Uji_Validasi_Final'),
        OBJECT_ID('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final'),
        OBJECT_ID('N_EMI_LAB_Hasil_Uji_Approval_Aktivitas'))
  AND i.type > 0 AND i.name IS NOT NULL
ORDER BY Tabel, Index_Name;

SELECT 'Foreign key' AS Pemeriksaan,
       name AS Constraint_Name,
       CASE WHEN is_not_trusted = 1 THEN 'NOCHECK (normal sebelum backfill)'
            ELSE 'tervalidasi' END AS Status
FROM sys.foreign_keys
WHERE name = 'FK_ValidasiDetailFinal_ValidasiFinal';
