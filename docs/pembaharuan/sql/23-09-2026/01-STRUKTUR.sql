-- ============================================================================
-- 01-STRUKTUR.sql  —  Perubahan struktur (DDL)
-- Tanggal : 23-09-2026
-- Target  : SQL Server 2016+ , collation Latin1_General_CI_AS
--
-- ISI:
--   A. Tambah kolom penghubung + konteks di Detail_Final  (ALTER, aditif)
--   B. Index pada Final & Detail_Final                    (CREATE INDEX)
--   C. Tabel BARU: N_EMI_LAB_Hasil_Uji_Approval_Aktivitas (CREATE TABLE)
--   D. Verifikasi
--
-- SIFAT SKRIP:
--   * IDEMPOTEN — aman dijalankan berulang kali (F5 berkali-kali tidak error).
--   * ADITIF     — hanya menambah kolom/index/tabel. Tidak ada DROP COLUMN,
--                  tidak ada perubahan tipe data, tidak ada DROP TABLE.
--   * TRANSAKSIONAL — dibungkus TRY/CATCH + TRANSACTION. Gagal di tengah =
--                  rollback penuh, database kembali seperti semula.
--   * Semua kolom baru NULLable => tidak mengganggu INSERT kode versi lama.
--     Artinya: aman di-deploy SEBELUM kode PHP baru naik (backward compatible).
--
-- PRASYARAT: jalankan 00-PRACHECK.sql lebih dulu. Bagian 5 harus 0 baris.
--
-- URUTAN   : 00-PRACHECK -> [01-STRUKTUR] -> 02-BACKFILL -> 03-VERIFIKASI
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;   -- error runtime apa pun => rollback otomatis

PRINT '============================================================';
PRINT ' 01-STRUKTUR — mulai ' + CONVERT(VARCHAR(19), GETDATE(), 120);
PRINT ' Database: ' + DB_NAME();
PRINT '============================================================';

BEGIN TRY
BEGIN TRANSACTION;

    -- ========================================================================
    -- PENJAGA: hentikan lebih awal kalau prasyarat tidak terpenuhi.
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
        THROW 51003, 'Ada duplikat (No_Split_Po,No_Batch,No_Sampel) di tabel Final. Bersihkan dulu — lihat PRACHECK bagian 5.', 1;

    PRINT '[OK] Prasyarat terpenuhi.';
    PRINT '';


    -- ========================================================================
    -- BAGIAN A — Kolom baru di Detail_Final
    -- ========================================================================
    PRINT '--- A. Kolom baru di Detail_Final ---';

    -- A1. Id_Uji_Validasi_Final — FK ke header.
    --     Inilah yang diminta: detail harus tahu header-nya secara eksplisit,
    --     bukan cuma lewat kecocokan No_Sampel.
    --     NULLable karena: (a) baris lama perlu di-backfill dulu,
    --     (b) detail sah dibuat saat validasi, sebelum finalisasi terjadi.
    IF COL_LENGTH('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','Id_Uji_Validasi_Final') IS NULL
    BEGIN
        ALTER TABLE N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
            ADD Id_Uji_Validasi_Final INT NULL;
        PRINT '  [+] Id_Uji_Validasi_Final ditambahkan.';
    END
    ELSE PRINT '  [=] Id_Uji_Validasi_Final sudah ada.';

    -- A2. Konteks aktivitas — supaya detail bisa dibaca tanpa join ke master,
    --     dan tetap benar bila master berubah di kemudian hari (snapshot).
    IF COL_LENGTH('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','Kode_Aktivitas_Lab') IS NULL
    BEGIN
        ALTER TABLE N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
            ADD Kode_Aktivitas_Lab VARCHAR(10) NULL;
        PRINT '  [+] Kode_Aktivitas_Lab ditambahkan.';
    END
    ELSE PRINT '  [=] Kode_Aktivitas_Lab sudah ada.';

    IF COL_LENGTH('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','Nama_Jenis_Analisa') IS NULL
    BEGIN
        ALTER TABLE N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
            ADD Nama_Jenis_Analisa VARCHAR(255) NULL;
        PRINT '  [+] Nama_Jenis_Analisa ditambahkan.';
    END
    ELSE PRINT '  [=] Nama_Jenis_Analisa sudah ada.';

    -- A3. Id_Session / Id_Pembanding — khusus PLT.
    --     Tanpa ini, beberapa baris palatabilitas untuk satu jenis analisa
    --     tidak bisa dibedakan satu sama lain (289 baris PLT punya Id_Session).
    IF COL_LENGTH('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','Id_Session') IS NULL
    BEGIN
        ALTER TABLE N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
            ADD Id_Session INT NULL;
        PRINT '  [+] Id_Session ditambahkan.';
    END
    ELSE PRINT '  [=] Id_Session sudah ada.';

    IF COL_LENGTH('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','Id_Pembanding') IS NULL
    BEGIN
        ALTER TABLE N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
            ADD Id_Pembanding INT NULL;
        PRINT '  [+] Id_Pembanding ditambahkan.';
    END
    ELSE PRINT '  [=] Id_Pembanding sudah ada.';

    -- A4. Jejak asal-usul baris: siapa & dari mana.
    IF COL_LENGTH('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','Sumber_Pencatatan') IS NULL
    BEGIN
        ALTER TABLE N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
            ADD Sumber_Pencatatan VARCHAR(30) NULL;   -- VALIDASI | BACKFILL | FINALISASI
        PRINT '  [+] Sumber_Pencatatan ditambahkan.';
    END
    ELSE PRINT '  [=] Sumber_Pencatatan sudah ada.';

    IF COL_LENGTH('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','Dibuat_Pada') IS NULL
    BEGIN
        ALTER TABLE N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
            ADD Dibuat_Pada DATETIME NULL;
        PRINT '  [+] Dibuat_Pada ditambahkan.';
    END
    ELSE PRINT '  [=] Dibuat_Pada sudah ada.';

    PRINT '';


    -- ========================================================================
    -- BAGIAN B — Index
    -- ========================================================================
    PRINT '--- B. Index ---';

    -- B1. UNIQUE pada header.
    --     Ini yang menutup bug "finalisasi sampel kedua menimpa yang pertama":
    --     kunci lama updateOrInsert (No_Split_Po,No_Batch) tanpa No_Sampel.
    --     Setelah index ini ada, penimpaan diam-diam jadi mustahil di level DB.
    IF NOT EXISTS (SELECT 1 FROM sys.indexes
                   WHERE name = 'UX_HasilUjiValidasiFinal_SplitPo_Batch_Sampel'
                     AND object_id = OBJECT_ID('N_EMI_LAB_Hasil_Uji_Validasi_Final'))
    BEGIN
        CREATE UNIQUE NONCLUSTERED INDEX UX_HasilUjiValidasiFinal_SplitPo_Batch_Sampel
            ON N_EMI_LAB_Hasil_Uji_Validasi_Final (No_Split_Po, No_Batch, No_Sampel);
        PRINT '  [+] UX_...SplitPo_Batch_Sampel (UNIQUE) dibuat.';
    END
    ELSE PRINT '  [=] UX_...SplitPo_Batch_Sampel sudah ada.';

    IF NOT EXISTS (SELECT 1 FROM sys.indexes
                   WHERE name = 'IX_HasilUjiValidasiFinal_NoSampel'
                     AND object_id = OBJECT_ID('N_EMI_LAB_Hasil_Uji_Validasi_Final'))
    BEGIN
        CREATE NONCLUSTERED INDEX IX_HasilUjiValidasiFinal_NoSampel
            ON N_EMI_LAB_Hasil_Uji_Validasi_Final (No_Sampel);
        PRINT '  [+] IX_...NoSampel dibuat.';
    END
    ELSE PRINT '  [=] IX_...NoSampel sudah ada.';

    -- B2. Index detail.
    --     SENGAJA NON-UNIQUE: data produksi sudah punya duplikat lama
    --     (lihat PRACHECK bagian 4). Memaksa UNIQUE di sini akan menggagalkan
    --     migrasi. Perlindungan anti-duplikat ditegakkan di kode aplikasi
    --     (updateOrInsert), dan pembersihan data lama disediakan terpisah
    --     di 04-PEMBERSIHAN-OPSIONAL.sql untuk ditinjau manusia.
    IF NOT EXISTS (SELECT 1 FROM sys.indexes
                   WHERE name = 'IX_ValidasiDetailFinal_Sampel_Sub_Analisa_Tahapan'
                     AND object_id = OBJECT_ID('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final'))
    BEGIN
        CREATE NONCLUSTERED INDEX IX_ValidasiDetailFinal_Sampel_Sub_Analisa_Tahapan
            ON N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
               (No_Sampel, No_Sub_Sampel, Id_Jenis_Analisa, Tahapan_Ke);
        PRINT '  [+] IX_...Sampel_Sub_Analisa_Tahapan dibuat.';
    END
    ELSE PRINT '  [=] IX_...Sampel_Sub_Analisa_Tahapan sudah ada.';

    IF NOT EXISTS (SELECT 1 FROM sys.indexes
                   WHERE name = 'IX_ValidasiDetailFinal_IdUjiValidasiFinal'
                     AND object_id = OBJECT_ID('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final'))
    BEGIN
        CREATE NONCLUSTERED INDEX IX_ValidasiDetailFinal_IdUjiValidasiFinal
            ON N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final (Id_Uji_Validasi_Final);
        PRINT '  [+] IX_...IdUjiValidasiFinal dibuat.';
    END
    ELSE PRINT '  [=] IX_...IdUjiValidasiFinal sudah ada.';

    IF NOT EXISTS (SELECT 1 FROM sys.indexes
                   WHERE name = 'IX_ValidasiDetailFinal_Aktivitas'
                     AND object_id = OBJECT_ID('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final'))
    BEGIN
        CREATE NONCLUSTERED INDEX IX_ValidasiDetailFinal_Aktivitas
            ON N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final (Kode_Aktivitas_Lab, No_Sampel);
        PRINT '  [+] IX_...Aktivitas dibuat.';
    END
    ELSE PRINT '  [=] IX_...Aktivitas sudah ada.';

    -- B3. FK detail -> header.
    --     NOCHECK: baris lama yang belum di-backfill tidak divalidasi mundur,
    --     supaya migrasi tidak gagal. Setelah 02-BACKFILL, constraint ini
    --     di-CHECK ulang oleh 03-VERIFIKASI.
    IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys
                   WHERE name = 'FK_ValidasiDetailFinal_ValidasiFinal')
    BEGIN
        ALTER TABLE N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
            WITH NOCHECK
            ADD CONSTRAINT FK_ValidasiDetailFinal_ValidasiFinal
            FOREIGN KEY (Id_Uji_Validasi_Final)
            REFERENCES N_EMI_LAB_Hasil_Uji_Validasi_Final (Id_Uji_Validasi_Final);
        PRINT '  [+] FK_ValidasiDetailFinal_ValidasiFinal dibuat (NOCHECK).';
    END
    ELSE PRINT '  [=] FK_ValidasiDetailFinal_ValidasiFinal sudah ada.';

    PRINT '';


    -- ========================================================================
    -- BAGIAN C — TABEL BARU: N_EMI_LAB_Hasil_Uji_Approval_Aktivitas
    -- ========================================================================
    -- KEBUTUHAN (poin 3):
    --   "analisa A klasifikasinya look view; di look view ada 5 analisa,
    --    siapa saja yang meng-approve. Tim desktop ingin tahu: untuk PO +
    --    sampel + batch ini, look view di-approve siapa, lab siapa,
    --    palatabilitas siapa."
    --
    -- BENTUK:
    --   Satu baris = satu (sampel x sub-sampel x aktivitas x jenis analisa).
    --   Jadi satu sampel bisa punya 5 baris LCKV, 20 baris ANL, N baris PLT —
    --   masing-masing dengan penanggung jawabnya sendiri.
    --
    -- MENGAPA TABEL TERSENDIRI, bukan kolom tambahan di Detail_Final:
    --   1. Detail_Final hanya terisi saat FINALISASI. Approval terjadi jauh
    --      lebih awal (saat validasi per analisa) dan harus terekam meski
    --      sampel belum/tidak pernah difinalisasi.
    --   2. Satu analisa bisa melewati lebih dari satu tahap persetujuan
    --      (validasi lab -> approval hirarki). Butuh baris sendiri, bukan
    --      satu kolom yang saling menimpa.
    --   3. Tim desktop butuh query langsung per aktivitas tanpa menyentuh
    --      tabel transaksi yang ramai.
    --
    -- DENORMALISASI YANG DISENGAJA:
    --   No_Po / No_Split_Po / No_Batch / Kode_Barang / Nama_User disalin ke
    --   sini. Ini snapshot audit: nilainya harus tetap sebagaimana saat
    --   approval terjadi, walaupun master berubah belakangan.
    -- ========================================================================
    PRINT '--- C. Tabel N_EMI_LAB_Hasil_Uji_Approval_Aktivitas ---';

    IF OBJECT_ID('N_EMI_LAB_Hasil_Uji_Approval_Aktivitas','U') IS NULL
    BEGIN
        CREATE TABLE N_EMI_LAB_Hasil_Uji_Approval_Aktivitas (
            Id_Approval_Aktivitas   INT IDENTITY(1,1) NOT NULL,

            -- === Identitas sampel (jalur query utama tim desktop) ===
            No_Sampel               VARCHAR(30)   NOT NULL,
            No_Sub_Sampel           VARCHAR(30)   NULL,   -- No_Fak_Sub_Po; NULL = sampel tunggal
            No_Po                   VARCHAR(25)   NULL,
            No_Split_Po             VARCHAR(25)   NULL,
            No_Batch                FLOAT         NULL,
            Kode_Barang             VARCHAR(50)   NULL,

            -- === Klasifikasi aktivitas ===
            Kode_Aktivitas_Lab      VARCHAR(10)   NOT NULL,  -- ANL | PLT | LCKV
            Nama_Aktivitas          VARCHAR(255)  NULL,      -- snapshot label

            -- === Analisa yang di-approve ===
            Id_Jenis_Analisa        INT           NOT NULL,
            Nama_Jenis_Analisa      VARCHAR(255)  NULL,      -- snapshot nama
            Tahapan_Ke              INT           NULL,

            -- === Konteks PLT ===
            Id_Session              INT           NULL,
            Id_Pembanding           INT           NULL,

            -- === SIAPA yang approve — inti kebutuhan ini ===
            Id_User                 VARCHAR(30)   NOT NULL,
            Nama_User               VARCHAR(255)  NULL,      -- snapshot nama
            Jenis_Approval          VARCHAR(30)   NOT NULL,  -- VALIDASI|APPROVAL_HIRARKI|FINALISASI
            Flag_Approval           CHAR(1)       NULL,      -- Y=setuju, T=tolak
            Flag_Layak              CHAR(1)       NULL,      -- Y=layak,  T=tidak layak
            Keterangan              VARCHAR(500)  NULL,

            -- === Waktu ===
            Tanggal                 DATE          NULL,
            Jam                     VARCHAR(8)    NULL,
            Dibuat_Pada             DATETIME      NOT NULL
                                    CONSTRAINT DF_ApprovalAktivitas_DibuatPada DEFAULT (GETDATE()),

            -- === Konteks alur ===
            Flag_Trial_Produksi     CHAR(1)       NULL,   -- NULL=produksi, Y=trial
            Flag_Resampling         CHAR(1)       NULL,
            Sumber_Pencatatan       VARCHAR(30)   NULL,   -- VALIDASI|BACKFILL|FINALISASI

            CONSTRAINT PK_N_EMI_LAB_Hasil_Uji_Approval_Aktivitas
                PRIMARY KEY CLUSTERED (Id_Approval_Aktivitas)
        );
        PRINT '  [+] Tabel dibuat.';

        -- Jalur query utama: "PO + sampel + batch ini, siapa approve apa"
        CREATE NONCLUSTERED INDEX IX_ApprovalAktivitas_Sampel_Aktivitas
            ON N_EMI_LAB_Hasil_Uji_Approval_Aktivitas (No_Sampel, Kode_Aktivitas_Lab)
            INCLUDE (Id_Jenis_Analisa, Id_User, Nama_User, Flag_Approval, Tanggal, Jam);

        CREATE NONCLUSTERED INDEX IX_ApprovalAktivitas_Po_Batch
            ON N_EMI_LAB_Hasil_Uji_Approval_Aktivitas (No_Po, No_Split_Po, No_Batch);

        CREATE NONCLUSTERED INDEX IX_ApprovalAktivitas_User
            ON N_EMI_LAB_Hasil_Uji_Approval_Aktivitas (Id_User, Tanggal);

        -- Anti-duplikat: satu user, satu analisa, satu jenis approval, sekali.
        -- Filtered (PLT dipisah) supaya baris PLT multi-pembanding tetap sah.
        CREATE UNIQUE NONCLUSTERED INDEX UX_ApprovalAktivitas_NonPlt
            ON N_EMI_LAB_Hasil_Uji_Approval_Aktivitas
               (No_Sampel, No_Sub_Sampel, Id_Jenis_Analisa, Jenis_Approval, Id_User)
            WHERE Id_Pembanding IS NULL;

        CREATE UNIQUE NONCLUSTERED INDEX UX_ApprovalAktivitas_Plt
            ON N_EMI_LAB_Hasil_Uji_Approval_Aktivitas
               (No_Sampel, No_Sub_Sampel, Id_Jenis_Analisa, Jenis_Approval, Id_User, Id_Pembanding)
            WHERE Id_Pembanding IS NOT NULL;

        PRINT '  [+] 5 index dibuat.';
    END
    ELSE PRINT '  [=] Tabel sudah ada, dilewati.';

    PRINT '';


COMMIT TRANSACTION;
PRINT '============================================================';
PRINT ' 01-STRUKTUR BERHASIL. Lanjut ke 02-BACKFILL.sql';
PRINT '============================================================';

END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;

    PRINT '';
    PRINT '### GAGAL — SEMUA PERUBAHAN DIBATALKAN (ROLLBACK) ###';
    PRINT 'Database tidak berubah sama sekali. Aman untuk diulang.';
    PRINT '';
    PRINT 'Pesan : ' + ERROR_MESSAGE();
    PRINT 'Baris : ' + CAST(ERROR_LINE() AS VARCHAR(10));
    PRINT 'Nomor : ' + CAST(ERROR_NUMBER() AS VARCHAR(10));

    THROW;   -- teruskan error supaya pipeline CI/CD ikut gagal
END CATCH;
GO


-- ============================================================================
-- D. VERIFIKASI (di luar transaksi)
-- ============================================================================
PRINT '';
PRINT '--- D. Verifikasi struktur ---';

SELECT 'Kolom baru Detail_Final' AS Pemeriksaan, c.name AS Kolom,
       t.name AS Tipe, c.max_length AS Panjang, c.is_nullable AS Nullable
FROM sys.columns c
JOIN sys.types t ON c.user_type_id = t.user_type_id
WHERE c.object_id = OBJECT_ID('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final')
  AND c.name IN ('Id_Uji_Validasi_Final','Kode_Aktivitas_Lab','Nama_Jenis_Analisa',
                 'Id_Session','Id_Pembanding','Sumber_Pencatatan','Dibuat_Pada')
ORDER BY c.name;

SELECT 'Struktur tabel approval' AS Pemeriksaan, c.name AS Kolom,
       t.name AS Tipe, c.max_length AS Panjang, c.is_nullable AS Nullable
FROM sys.columns c
JOIN sys.types t ON c.user_type_id = t.user_type_id
WHERE c.object_id = OBJECT_ID('N_EMI_LAB_Hasil_Uji_Approval_Aktivitas')
ORDER BY c.column_id;

SELECT 'Index terpasang' AS Pemeriksaan, i.name AS Index_Name,
       OBJECT_NAME(i.object_id) AS Tabel, i.is_unique AS Unik
FROM sys.indexes i
WHERE i.object_id IN (
        OBJECT_ID('N_EMI_LAB_Hasil_Uji_Validasi_Final'),
        OBJECT_ID('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final'),
        OBJECT_ID('N_EMI_LAB_Hasil_Uji_Approval_Aktivitas'))
  AND i.type > 0 AND i.name IS NOT NULL
ORDER BY Tabel, Index_Name;
