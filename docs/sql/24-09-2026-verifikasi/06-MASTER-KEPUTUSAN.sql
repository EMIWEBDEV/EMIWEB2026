-- ============================================================================
-- 06-MASTER-KEPUTUSAN.sql
-- Tanggal : 24-09-2026
-- Modul   : VERIFIKASI HASIL ANALISA — master jenis keputusan
--
-- LATAR BELAKANG:
--   Pada LIMS, verifikator jarang hanya punya pilihan "setuju" atau "tolak".
--   Praktik yang lazim (ISO 17025, GMP) mengenal tingkatan keputusan:
--
--     DISETUJUI             — seluruh hasil memenuhi spesifikasi; rilis penuh
--     DISETUJUI_BERSYARAT   — ada penyimpangan, tetapi masih dapat dilanjutkan
--                             dengan justifikasi tertulis (concession release)
--     DITOLAK               — hasil tidak memenuhi; tidak boleh dilanjutkan
--
--   Perbedaan pentingnya ada pada KEWAJIBAN CATATAN. Persetujuan penuh boleh
--   tanpa catatan, sedangkan persetujuan bersyarat dan penolakan WAJIB
--   disertai alasan — itulah yang membuat jejak audit bermakna.
--
-- ISI:
--   A. Perluas master status: tambah DISETUJUI_BERSYARAT
--   B. Tabel baru N_EMI_LAB_Verifikasi_Keputusan — master jenis keputusan
--      beserta aturannya (wajib catatan, boleh lanjut finalisasi, dsb.)
--   C. Kolom Kode_Keputusan pada header & riwayat
--
-- SIFAT:
--   * IDEMPOTEN — aman dijalankan berulang kali.
--   * ADITIF — hanya menambah; keputusan lama tetap sah karena dipetakan
--     otomatis ke kode yang setara.
--
-- PRASYARAT: 01-STRUKTUR-VERIFIKASI.sql sudah dijalankan.
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;

PRINT '============================================================';
PRINT ' MASTER KEPUTUSAN VERIFIKASI';
PRINT ' Database : ' + DB_NAME();
PRINT '============================================================';

BEGIN TRY
BEGIN TRANSACTION;

    IF OBJECT_ID('N_EMI_LAB_Verifikasi_Status','U') IS NULL
        THROW 56001, 'Tabel status belum ada. Jalankan 01-STRUKTUR-VERIFIKASI.sql dulu.', 1;

    -- ========================================================================
    -- A. Status baru: DISETUJUI_BERSYARAT
    -- ========================================================================
    PRINT '';
    PRINT '--- A. Status verifikasi ---';

    INSERT INTO N_EMI_LAB_Verifikasi_Status
        (Kode_Status, Nama_Status, Keterangan, Warna_Badge, Urutan, Flag_Final)
    SELECT 'DISETUJUI_BERSYARAT', 'Disetujui Bersyarat',
           'Terdapat penyimpangan, namun sampel dapat dilanjutkan dengan justifikasi tertulis',
           'warn', 3, 'Y'
    WHERE NOT EXISTS (
        SELECT 1 FROM N_EMI_LAB_Verifikasi_Status WHERE Kode_Status = 'DISETUJUI_BERSYARAT'
    );

    PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' status ditambahkan.';

    -- Geser urutan DITOLAK agar berada paling akhir.
    UPDATE N_EMI_LAB_Verifikasi_Status SET Urutan = 4 WHERE Kode_Status = 'DITOLAK';


    -- ========================================================================
    -- B. Master jenis keputusan
    -- ========================================================================
    -- Disimpan sebagai DATA, bukan hardcode di aplikasi, supaya penambahan
    -- jenis keputusan baru (mis. "Ditahan Sementara") cukup lewat INSERT.
    PRINT '';
    PRINT '--- B. Master jenis keputusan ---';

    IF OBJECT_ID('N_EMI_LAB_Verifikasi_Keputusan','U') IS NULL
    BEGIN
        CREATE TABLE N_EMI_LAB_Verifikasi_Keputusan (
            Kode_Keputusan     VARCHAR(30)   NOT NULL,
            Nama_Keputusan     VARCHAR(100)  NOT NULL,
            Keterangan         VARCHAR(500)  NULL,

            -- Status yang dihasilkan bila keputusan ini dipakai
            Kode_Status        VARCHAR(20)   NOT NULL,

            -- Aturan pengisian
            Flag_Wajib_Catatan CHAR(1)       NOT NULL,   -- Y = catatan wajib
            Panjang_Min_Catatan INT          NULL,        -- panjang minimum alasan

            -- Dampak terhadap alur
            Flag_Boleh_Lanjut  CHAR(1)       NOT NULL,   -- Y = boleh difinalisasi
            Flag_Perlu_Tindak  CHAR(1)       NULL,       -- Y = perlu tindak lanjut

            -- Tampilan
            Warna_Badge        VARCHAR(20)   NULL,       -- ok | warn | bad
            Ikon               VARCHAR(50)   NULL,
            Urutan             INT           NULL,

            Flag_Aktif         CHAR(1)       NOT NULL
                               CONSTRAINT DF_VerKep_Aktif DEFAULT ('Y'),

            CONSTRAINT PK_N_EMI_LAB_Verifikasi_Keputusan
                PRIMARY KEY CLUSTERED (Kode_Keputusan),
            CONSTRAINT FK_VerKeputusan_Status
                FOREIGN KEY (Kode_Status)
                REFERENCES N_EMI_LAB_Verifikasi_Status (Kode_Status)
        );
        PRINT '  [+] Tabel dibuat.';
    END
    ELSE PRINT '  [=] Tabel sudah ada.';

    -- Isi master: tiga tingkatan keputusan standar LIMS.
    INSERT INTO N_EMI_LAB_Verifikasi_Keputusan
        (Kode_Keputusan, Nama_Keputusan, Keterangan, Kode_Status,
         Flag_Wajib_Catatan, Panjang_Min_Catatan, Flag_Boleh_Lanjut,
         Flag_Perlu_Tindak, Warna_Badge, Ikon, Urutan)
    SELECT k.Kode, k.Nama, k.Ket, k.Status, k.Wajib, k.MinLen,
           k.Lanjut, k.Tindak, k.Warna, k.Ikon, k.Urut
    FROM (VALUES
        ('SETUJU_PENUH',
         'Disetujui — Memenuhi Spesifikasi',
         'Seluruh hasil analisa memenuhi spesifikasi yang ditetapkan. Sampel dapat dilanjutkan ke finalisasi tanpa catatan khusus.',
         'DISETUJUI', 'T', NULL, 'Y', NULL, 'ok',
         'ri-checkbox-circle-line', 1),

        ('SETUJU_BERSYARAT',
         'Disetujui Bersyarat — Dengan Justifikasi',
         'Terdapat penyimpangan atau analisa tanpa standar mutu, namun secara keseluruhan sampel masih dapat dilanjutkan. Justifikasi tertulis wajib diisi sebagai dasar keputusan.',
         'DISETUJUI_BERSYARAT', 'Y', 15, 'Y', 'Y', 'warn',
         'ri-error-warning-line', 2),

        ('TOLAK',
         'Ditolak — Tidak Memenuhi Spesifikasi',
         'Hasil analisa tidak memenuhi spesifikasi. Alasan penolakan wajib diisi dan menjadi dasar tindak lanjut (uji ulang atau penanganan lain).',
         'DITOLAK', 'Y', 15, 'T', 'Y', 'bad',
         'ri-close-circle-line', 3)
    ) AS k(Kode, Nama, Ket, Status, Wajib, MinLen, Lanjut, Tindak, Warna, Ikon, Urut)
    WHERE NOT EXISTS (
        SELECT 1 FROM N_EMI_LAB_Verifikasi_Keputusan x WHERE x.Kode_Keputusan = k.Kode
    );

    PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' jenis keputusan ditambahkan.';


    -- ========================================================================
    -- C. Kolom Kode_Keputusan pada header & riwayat
    -- ========================================================================
    PRINT '';
    PRINT '--- C. Kolom jenis keputusan ---';

    IF COL_LENGTH('N_EMI_LAB_Verifikasi_Header','Kode_Keputusan') IS NULL
    BEGIN
        ALTER TABLE N_EMI_LAB_Verifikasi_Header ADD Kode_Keputusan VARCHAR(30) NULL;
        PRINT '  [+] Header.Kode_Keputusan';
    END ELSE PRINT '  [=] Header.Kode_Keputusan sudah ada';

    IF COL_LENGTH('N_EMI_LAB_Verifikasi_Riwayat','Kode_Keputusan') IS NULL
    BEGIN
        ALTER TABLE N_EMI_LAB_Verifikasi_Riwayat ADD Kode_Keputusan VARCHAR(30) NULL;
        PRINT '  [+] Riwayat.Kode_Keputusan';
    END ELSE PRINT '  [=] Riwayat.Kode_Keputusan sudah ada';

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
-- D. Pemetaan keputusan lama  (batch terpisah: kolom harus sudah terlihat)
-- ============================================================================
SET NOCOUNT ON;
PRINT '';
PRINT '--- D. Petakan keputusan lama ---';

-- Keputusan yang tercatat sebelum master ini ada dipetakan ke kode setara,
-- supaya seluruh riwayat tetap dapat dibaca dengan tata bahasa yang sama.
UPDATE N_EMI_LAB_Verifikasi_Header
   SET Kode_Keputusan = CASE Kode_Status
                            WHEN 'DISETUJUI' THEN 'SETUJU_PENUH'
                            WHEN 'DITOLAK'   THEN 'TOLAK'
                        END
 WHERE Kode_Keputusan IS NULL
   AND Kode_Status IN ('DISETUJUI', 'DITOLAK');
PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' header dipetakan.';

UPDATE N_EMI_LAB_Verifikasi_Riwayat
   SET Kode_Keputusan = CASE Status_Sesudah
                            WHEN 'DISETUJUI' THEN 'SETUJU_PENUH'
                            WHEN 'DITOLAK'   THEN 'TOLAK'
                        END
 WHERE Kode_Keputusan IS NULL
   AND Status_Sesudah IN ('DISETUJUI', 'DITOLAK');
PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' riwayat dipetakan.';

PRINT '';
PRINT '============================================================';
PRINT ' MASTER KEPUTUSAN SELESAI.';
PRINT '============================================================';
GO


-- ============================================================================
-- VERIFIKASI
-- ============================================================================
SET NOCOUNT ON;
PRINT '';
PRINT '--- Master jenis keputusan ---';

SELECT
    k.Urutan                                    AS No,
    k.Kode_Keputusan                            AS Kode,
    k.Nama_Keputusan                            AS Nama,
    s.Nama_Status                               AS Menjadi_Status,
    CASE k.Flag_Wajib_Catatan WHEN 'Y'
         THEN 'WAJIB (min ' + CAST(ISNULL(k.Panjang_Min_Catatan,0) AS VARCHAR(5)) + ' huruf)'
         ELSE 'opsional' END                    AS Catatan,
    CASE k.Flag_Boleh_Lanjut WHEN 'Y' THEN 'boleh' ELSE 'tidak' END AS Lanjut_Finalisasi,
    k.Warna_Badge                               AS Warna
FROM N_EMI_LAB_Verifikasi_Keputusan k
JOIN N_EMI_LAB_Verifikasi_Status s
    ON s.Kode_Status = k.Kode_Status
WHERE k.Flag_Aktif = 'Y'
ORDER BY k.Urutan;

PRINT '';
PRINT '--- Master status ---';
SELECT Kode_Status, Nama_Status, Warna_Badge, Urutan, ISNULL(Flag_Final,'-') AS Final
FROM N_EMI_LAB_Verifikasi_Status
ORDER BY Urutan;
