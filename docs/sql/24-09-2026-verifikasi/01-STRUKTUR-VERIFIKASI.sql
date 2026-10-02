-- ============================================================================
-- 01-STRUKTUR-VERIFIKASI.sql
-- Tanggal : 24-09-2026
-- Modul   : VERIFIKASI HASIL ANALISA  (step baru: Validasi -> Verifikasi -> Finalisasi)
--
-- ISI:
--   A. N_EMI_LAB_Verifikasi_Header   — satu keputusan per (sampel x klasifikasi)
--   B. N_EMI_LAB_Verifikasi_Detail   — snapshot analisa yang diverifikasi
--   C. N_EMI_LAB_Verifikasi_Riwayat  — jejak setiap perubahan keputusan
--   D. N_EMI_LAB_Verifikasi_Kewenangan — siapa berwenang atas klasifikasi apa
--   E. N_EMI_LAB_Verifikasi_Status   — master status (bisa ditambah kemudian)
--   F. Verifikasi
--
-- SIFAT:
--   * TERISOLASI — hanya membuat tabel BARU berawalan N_EMI_LAB_Verifikasi_*.
--     TIDAK menyentuh satu pun tabel lama. Tidak ada ALTER pada tabel existing.
--     Modul ini bisa dihapus total tanpa memengaruhi fitur yang sudah jalan.
--   * IDEMPOTEN — setiap objek dijaga IF NOT EXISTS; aman dijalankan berulang.
--   * TRANSAKSIONAL — gagal di tengah = rollback penuh.
--   * MUDAH DIPERLUAS — status, kewenangan, dan jenis keputusan disimpan
--     sebagai DATA (tabel master), bukan di-hardcode. Menambah status atau
--     mengubah kewenangan cukup INSERT/UPDATE, tanpa mengubah struktur.
--
-- CATATAN IZIN: skrip ini butuh hak CREATE TABLE (db_owner / db_ddladmin).
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;

PRINT '============================================================';
PRINT ' MODUL VERIFIKASI HASIL ANALISA — ' + CONVERT(VARCHAR(19), GETDATE(), 120);
PRINT ' Database : ' + DB_NAME();
PRINT '============================================================';

BEGIN TRY
BEGIN TRANSACTION;

    -- ========================================================================
    -- E. MASTER STATUS  (dibuat pertama: dirujuk tabel lain)
    -- ========================================================================
    -- Status disimpan sebagai data supaya penambahan status baru di kemudian
    -- hari (mis. 'REVISI', 'ESKALASI') cukup INSERT, tanpa ubah struktur.
    PRINT '';
    PRINT '--- E. Master status verifikasi ---';

    IF OBJECT_ID('N_EMI_LAB_Verifikasi_Status','U') IS NULL
    BEGIN
        CREATE TABLE N_EMI_LAB_Verifikasi_Status (
            Kode_Status       VARCHAR(20)   NOT NULL,
            Nama_Status       VARCHAR(100)  NOT NULL,
            Keterangan        VARCHAR(255)  NULL,
            Warna_Badge       VARCHAR(20)   NULL,   -- dipakai UI: ok|bad|wait|info
            Urutan            INT           NULL,
            Flag_Final        CHAR(1)       NULL,   -- Y = keputusan akhir
            Flag_Aktif        CHAR(1)       NOT NULL
                              CONSTRAINT DF_VerStatus_Aktif DEFAULT ('Y'),
            CONSTRAINT PK_N_EMI_LAB_Verifikasi_Status PRIMARY KEY CLUSTERED (Kode_Status)
        );
        PRINT '  [+] Tabel dibuat.';
    END ELSE PRINT '  [=] Tabel sudah ada.';

    -- Isi status awal (idempoten: hanya yang belum ada)
    INSERT INTO N_EMI_LAB_Verifikasi_Status (Kode_Status, Nama_Status, Keterangan, Warna_Badge, Urutan, Flag_Final)
    SELECT s.Kode, s.Nama, s.Ket, s.Warna, s.Urut, s.Fin
    FROM (VALUES
        ('MENUNGGU',  'Menunggu Verifikasi', 'Hasil analisa sudah divalidasi, menunggu keputusan verifikator', 'wait', 1, NULL),
        ('DISETUJUI', 'Disetujui',           'Verifikator menyetujui seluruh analisa pada klasifikasi ini',    'ok',   2, 'Y'),
        ('DITOLAK',   'Ditolak',             'Verifikator menolak; alasan wajib diisi',                        'bad',  3, 'Y')
    ) AS s(Kode, Nama, Ket, Warna, Urut, Fin)
    WHERE NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Verifikasi_Status x WHERE x.Kode_Status = s.Kode);
    PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' status ditambahkan.';


    -- ========================================================================
    -- D. KEWENANGAN — siapa boleh memverifikasi klasifikasi apa
    -- ========================================================================
    -- Disimpan sebagai data, bukan hardcode di kode, supaya penambahan
    -- verifikator atau pemindahan kewenangan cukup lewat INSERT/UPDATE.
    --
    -- Cakupan bisa diatur dua tingkat:
    --   1. Seluruh klasifikasi  -> Id_Jenis_Analisa NULL
    --   2. Jenis analisa tertentu saja -> Id_Jenis_Analisa diisi
    PRINT '';
    PRINT '--- D. Kewenangan verifikator ---';

    IF OBJECT_ID('N_EMI_LAB_Verifikasi_Kewenangan','U') IS NULL
    BEGIN
        CREATE TABLE N_EMI_LAB_Verifikasi_Kewenangan (
            Id_Kewenangan       INT IDENTITY(1,1) NOT NULL,
            Id_User             VARCHAR(30)  NOT NULL,
            Kode_Aktivitas_Lab  VARCHAR(10)  NOT NULL,   -- ANL | PLT | LCKV
            Id_Jenis_Analisa    INT          NULL,       -- NULL = semua dlm klasifikasi
            Flag_Approve        CHAR(1)      NOT NULL
                                CONSTRAINT DF_VerKwn_App DEFAULT ('Y'),
            Flag_Reject         CHAR(1)      NOT NULL
                                CONSTRAINT DF_VerKwn_Rej DEFAULT ('Y'),
            Flag_Aktif          CHAR(1)      NOT NULL
                                CONSTRAINT DF_VerKwn_Aktif DEFAULT ('Y'),
            Keterangan          VARCHAR(255) NULL,
            Dibuat_Pada         DATETIME     NOT NULL
                                CONSTRAINT DF_VerKwn_Dibuat DEFAULT (GETDATE()),
            Dibuat_Oleh         VARCHAR(30)  NULL,
            CONSTRAINT PK_N_EMI_LAB_Verifikasi_Kewenangan PRIMARY KEY CLUSTERED (Id_Kewenangan)
        );
        PRINT '  [+] Tabel dibuat.';
    END ELSE PRINT '  [=] Tabel sudah ada.';


    -- ========================================================================
    -- A. HEADER — satu keputusan per (sampel x sub-sampel x klasifikasi)
    -- ========================================================================
    -- Inilah "induk" yang diminta: satu baris menjawab
    -- "Look View pada sampel ini disetujui/ditolak siapa, kapan, alasannya apa".
    --
    -- Baris dibuat saat verifikator membuka atau memutuskan, sehingga daftar
    -- kerja tetap bisa menampilkan sampel yang belum pernah disentuh
    -- (dianggap MENUNGGU bila belum ada barisnya).
    PRINT '';
    PRINT '--- A. Header verifikasi ---';

    IF OBJECT_ID('N_EMI_LAB_Verifikasi_Header','U') IS NULL
    BEGIN
        CREATE TABLE N_EMI_LAB_Verifikasi_Header (
            Id_Verifikasi         INT IDENTITY(1,1) NOT NULL,

            -- Identitas sampel (snapshot, agar laporan tetap benar
            -- walaupun master berubah di kemudian hari)
            No_Sampel             VARCHAR(30)   NOT NULL,
            No_Sub_Sampel         VARCHAR(30)   NULL,     -- NULL = sampel tunggal
            No_Po                 VARCHAR(25)   NULL,
            No_Split_Po           VARCHAR(25)   NULL,
            No_Batch              FLOAT         NULL,
            Kode_Barang           VARCHAR(50)   NULL,
            Nama_Barang           VARCHAR(255)  NULL,
            Flag_Trial_Produksi   CHAR(1)       NULL,     -- NULL=produksi, Y=trial

            -- Klasifikasi yang diverifikasi
            Kode_Aktivitas_Lab    VARCHAR(10)   NOT NULL, -- ANL | PLT | LCKV
            Nama_Aktivitas        VARCHAR(255)  NULL,

            -- Keputusan
            Kode_Status           VARCHAR(20)   NOT NULL
                                  CONSTRAINT DF_VerHdr_Status DEFAULT ('MENUNGGU'),
            Catatan               VARCHAR(1000) NULL,     -- wajib diisi saat DITOLAK
            Id_User               VARCHAR(30)   NULL,     -- pemutus
            Nama_User             VARCHAR(255)  NULL,
            Tanggal_Keputusan     DATE          NULL,
            Jam_Keputusan         VARCHAR(8)    NULL,

            -- Ringkasan analisa (dihitung saat keputusan, untuk laporan cepat)
            Jumlah_Analisa        INT           NULL,
            Jumlah_Tidak_Layak    INT           NULL,

            -- Jejak sistem
            Revisi_Ke             INT           NOT NULL
                                  CONSTRAINT DF_VerHdr_Revisi DEFAULT (0),
            Dibuat_Pada           DATETIME      NOT NULL
                                  CONSTRAINT DF_VerHdr_Dibuat DEFAULT (GETDATE()),
            Diubah_Pada           DATETIME      NULL,

            CONSTRAINT PK_N_EMI_LAB_Verifikasi_Header PRIMARY KEY CLUSTERED (Id_Verifikasi),
            CONSTRAINT FK_VerHeader_Status FOREIGN KEY (Kode_Status)
                REFERENCES N_EMI_LAB_Verifikasi_Status (Kode_Status)
        );
        PRINT '  [+] Tabel dibuat.';
    END ELSE PRINT '  [=] Tabel sudah ada.';


    -- ========================================================================
    -- B. DETAIL — snapshot analisa yang ikut dalam satu keputusan
    -- ========================================================================
    -- Menyimpan hasil APA ADANYA pada saat diverifikasi. Bila hasil di
    -- Uji_Sampel berubah kemudian, jejak verifikasi tetap menunjukkan apa
    -- yang benar-benar dilihat verifikator saat memutuskan.
    PRINT '';
    PRINT '--- B. Detail verifikasi ---';

    IF OBJECT_ID('N_EMI_LAB_Verifikasi_Detail','U') IS NULL
    BEGIN
        CREATE TABLE N_EMI_LAB_Verifikasi_Detail (
            Id_Verifikasi_Detail  INT IDENTITY(1,1) NOT NULL,
            Id_Verifikasi         INT           NOT NULL,

            Id_Jenis_Analisa      INT           NOT NULL,
            Nama_Jenis_Analisa    VARCHAR(255)  NULL,
            Kode_Analisa          VARCHAR(50)   NULL,
            No_Sub_Sampel         VARCHAR(30)   NULL,
            Tahapan_Ke            INT           NULL,

            -- Snapshot hasil saat diverifikasi
            Hasil                 FLOAT         NULL,
            Nilai_Hasil_String    VARCHAR(255)  NULL,
            Range_Awal            FLOAT         NULL,
            Range_Akhir           FLOAT         NULL,
            Flag_Layak            CHAR(1)       NULL,
            Flag_Perhitungan      CHAR(1)       NULL,

            -- Konteks palatabilitas
            Id_Session            INT           NULL,
            Id_Pembanding         INT           NULL,
            Nama_Pembanding       VARCHAR(255)  NULL,

            -- Siapa yang memvalidasi analisa ini (step sebelumnya)
            Id_User_Validasi      VARCHAR(30)   NULL,
            Nama_User_Validasi    VARCHAR(255)  NULL,
            Tanggal_Validasi      DATE          NULL,
            Jam_Validasi          VARCHAR(8)    NULL,

            -- Keputusan per analisa (opsional; default ikut header)
            Kode_Status           VARCHAR(20)   NULL,
            Catatan               VARCHAR(1000) NULL,

            Dibuat_Pada           DATETIME      NOT NULL
                                  CONSTRAINT DF_VerDet_Dibuat DEFAULT (GETDATE()),

            CONSTRAINT PK_N_EMI_LAB_Verifikasi_Detail PRIMARY KEY CLUSTERED (Id_Verifikasi_Detail),
            CONSTRAINT FK_VerDetail_Header FOREIGN KEY (Id_Verifikasi)
                REFERENCES N_EMI_LAB_Verifikasi_Header (Id_Verifikasi)
                ON DELETE CASCADE
        );
        PRINT '  [+] Tabel dibuat.';
    END ELSE PRINT '  [=] Tabel sudah ada.';


    -- ========================================================================
    -- C. RIWAYAT — jejak setiap perubahan keputusan
    -- ========================================================================
    -- Append-only. Keputusan yang diubah (mis. DITOLAK lalu DISETUJUI) tidak
    -- menimpa jejak lama; setiap perubahan menambah baris baru di sini.
    -- Inilah yang membuat audit "siapa mengubah apa, kapan, kenapa" lengkap.
    PRINT '';
    PRINT '--- C. Riwayat keputusan ---';

    IF OBJECT_ID('N_EMI_LAB_Verifikasi_Riwayat','U') IS NULL
    BEGIN
        CREATE TABLE N_EMI_LAB_Verifikasi_Riwayat (
            Id_Riwayat            INT IDENTITY(1,1) NOT NULL,
            Id_Verifikasi         INT           NOT NULL,

            No_Sampel             VARCHAR(30)   NOT NULL,
            Kode_Aktivitas_Lab    VARCHAR(10)   NOT NULL,

            Status_Sebelum        VARCHAR(20)   NULL,
            Status_Sesudah        VARCHAR(20)   NOT NULL,
            Aksi                  VARCHAR(30)   NOT NULL,  -- APPROVE|REJECT|REVISI|BUKA
            Catatan               VARCHAR(1000) NULL,

            Id_User               VARCHAR(30)   NOT NULL,
            Nama_User             VARCHAR(255)  NULL,
            Tanggal               DATE          NULL,
            Jam                   VARCHAR(8)    NULL,
            Dibuat_Pada           DATETIME      NOT NULL
                                  CONSTRAINT DF_VerRiw_Dibuat DEFAULT (GETDATE()),

            Sumber_Aksi           VARCHAR(20)   NULL,      -- SATUAN | BULK
            Jumlah_Analisa        INT           NULL,

            CONSTRAINT PK_N_EMI_LAB_Verifikasi_Riwayat PRIMARY KEY CLUSTERED (Id_Riwayat),
            CONSTRAINT FK_VerRiwayat_Header FOREIGN KEY (Id_Verifikasi)
                REFERENCES N_EMI_LAB_Verifikasi_Header (Id_Verifikasi)
                ON DELETE CASCADE
        );
        PRINT '  [+] Tabel dibuat.';
    END ELSE PRINT '  [=] Tabel sudah ada.';

COMMIT TRANSACTION;
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
-- INDEX  (batch terpisah: tabel harus sudah ada)
-- ============================================================================
SET NOCOUNT ON;
PRINT '';
PRINT '--- Index ---';

-- Satu keputusan aktif per (sampel, sub-sampel, klasifikasi).
-- Filtered karena No_Sub_Sampel boleh NULL untuk sampel tunggal, dan index
-- UNIQUE biasa memperlakukan seluruh NULL sebagai satu nilai yang sama.
IF OBJECT_ID('N_EMI_LAB_Verifikasi_Header','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='UX_VerHeader_Tunggal')
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_VerHeader_Tunggal
        ON N_EMI_LAB_Verifikasi_Header (No_Sampel, Kode_Aktivitas_Lab)
        WHERE No_Sub_Sampel IS NULL;
    PRINT '  [+] UX_VerHeader_Tunggal (UNIQUE, filtered)';
END ELSE PRINT '  [=] UX_VerHeader_Tunggal sudah ada / tabel belum ada';

IF OBJECT_ID('N_EMI_LAB_Verifikasi_Header','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='UX_VerHeader_Multi')
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_VerHeader_Multi
        ON N_EMI_LAB_Verifikasi_Header (No_Sampel, No_Sub_Sampel, Kode_Aktivitas_Lab)
        WHERE No_Sub_Sampel IS NOT NULL;
    PRINT '  [+] UX_VerHeader_Multi (UNIQUE, filtered)';
END ELSE PRINT '  [=] UX_VerHeader_Multi sudah ada / tabel belum ada';

IF OBJECT_ID('N_EMI_LAB_Verifikasi_Header','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_VerHeader_Status_Aktivitas')
BEGIN
    CREATE NONCLUSTERED INDEX IX_VerHeader_Status_Aktivitas
        ON N_EMI_LAB_Verifikasi_Header (Kode_Status, Kode_Aktivitas_Lab)
        INCLUDE (No_Sampel, No_Po, No_Batch, Id_User, Tanggal_Keputusan);
    PRINT '  [+] IX_VerHeader_Status_Aktivitas';
END ELSE PRINT '  [=] IX_VerHeader_Status_Aktivitas sudah ada / tabel belum ada';

IF OBJECT_ID('N_EMI_LAB_Verifikasi_Header','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_VerHeader_Po')
BEGIN
    CREATE NONCLUSTERED INDEX IX_VerHeader_Po
        ON N_EMI_LAB_Verifikasi_Header (No_Po, No_Split_Po, No_Batch);
    PRINT '  [+] IX_VerHeader_Po';
END ELSE PRINT '  [=] IX_VerHeader_Po sudah ada / tabel belum ada';

IF OBJECT_ID('N_EMI_LAB_Verifikasi_Header','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_VerHeader_User')
BEGIN
    CREATE NONCLUSTERED INDEX IX_VerHeader_User
        ON N_EMI_LAB_Verifikasi_Header (Id_User, Tanggal_Keputusan);
    PRINT '  [+] IX_VerHeader_User';
END ELSE PRINT '  [=] IX_VerHeader_User sudah ada / tabel belum ada';

IF OBJECT_ID('N_EMI_LAB_Verifikasi_Detail','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_VerDetail_Header')
BEGIN
    CREATE NONCLUSTERED INDEX IX_VerDetail_Header
        ON N_EMI_LAB_Verifikasi_Detail (Id_Verifikasi);
    PRINT '  [+] IX_VerDetail_Header';
END ELSE PRINT '  [=] IX_VerDetail_Header sudah ada / tabel belum ada';

IF OBJECT_ID('N_EMI_LAB_Verifikasi_Riwayat','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_VerRiwayat_Header')
BEGIN
    CREATE NONCLUSTERED INDEX IX_VerRiwayat_Header
        ON N_EMI_LAB_Verifikasi_Riwayat (Id_Verifikasi, Id_Riwayat);
    PRINT '  [+] IX_VerRiwayat_Header';
END ELSE PRINT '  [=] IX_VerRiwayat_Header sudah ada / tabel belum ada';

IF OBJECT_ID('N_EMI_LAB_Verifikasi_Riwayat','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_VerRiwayat_Sampel')
BEGIN
    CREATE NONCLUSTERED INDEX IX_VerRiwayat_Sampel
        ON N_EMI_LAB_Verifikasi_Riwayat (No_Sampel, Kode_Aktivitas_Lab);
    PRINT '  [+] IX_VerRiwayat_Sampel';
END ELSE PRINT '  [=] IX_VerRiwayat_Sampel sudah ada / tabel belum ada';

IF OBJECT_ID('N_EMI_LAB_Verifikasi_Kewenangan','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_VerKewenangan_User')
BEGIN
    CREATE NONCLUSTERED INDEX IX_VerKewenangan_User
        ON N_EMI_LAB_Verifikasi_Kewenangan (Id_User, Flag_Aktif)
        INCLUDE (Kode_Aktivitas_Lab, Id_Jenis_Analisa);
    PRINT '  [+] IX_VerKewenangan_User';
END ELSE PRINT '  [=] IX_VerKewenangan_User sudah ada / tabel belum ada';

PRINT '';
PRINT '============================================================';
PRINT ' STRUKTUR MODUL VERIFIKASI SELESAI.';
PRINT ' Lanjut ke 02-SEED-USER-KEWENANGAN.sql';
PRINT '============================================================';
GO


-- ============================================================================
-- F. VERIFIKASI
-- ============================================================================
SET NOCOUNT ON;
PRINT '';
PRINT '--- Verifikasi struktur ---';

SELECT t.Nama_Tabel,
       CASE WHEN OBJECT_ID(t.Nama_Tabel,'U') IS NOT NULL THEN 'LULUS' ELSE 'GAGAL' END AS HASIL,
       (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID(t.Nama_Tabel)) AS Jumlah_Kolom
FROM (VALUES
    ('N_EMI_LAB_Verifikasi_Status'),
    ('N_EMI_LAB_Verifikasi_Kewenangan'),
    ('N_EMI_LAB_Verifikasi_Header'),
    ('N_EMI_LAB_Verifikasi_Detail'),
    ('N_EMI_LAB_Verifikasi_Riwayat')
) AS t(Nama_Tabel);

SELECT 'Master status' AS Pemeriksaan, Kode_Status, Nama_Status, Warna_Badge, Flag_Final
FROM N_EMI_LAB_Verifikasi_Status ORDER BY Urutan;

SELECT 'Index modul verifikasi' AS Pemeriksaan,
       i.name AS Index_Name, OBJECT_NAME(i.object_id) AS Tabel,
       CASE WHEN i.is_unique = 1 THEN 'UNIQUE' ELSE 'non-unique' END AS Jenis
FROM sys.indexes i
WHERE OBJECT_NAME(i.object_id) LIKE 'N_EMI_LAB_Verifikasi_%'
  AND i.type > 0 AND i.name IS NOT NULL
ORDER BY Tabel, Index_Name;
