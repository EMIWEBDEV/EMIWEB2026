-- ============================================================================
-- 09-UBAH-ISTILAH-REKOMENDASI.sql
-- Tanggal : 24-09-2026
-- Modul   : VERIFIKASI HASIL ANALISA - penyelarasan istilah keputusan
--
-- LATAR:
--   Tahap verifikasi berada SEBELUM finalisasi. Verifikator tidak menerima
--   atau menolak sampel — keputusan menerima/menolak ada di finalisasi.
--   Yang diberikan verifikator adalah REKOMENDASI kepada tahap berikutnya.
--   Istilah "Disetujui / Ditolak" karena itu menyesatkan dan diganti.
--
-- PERUBAHAN:
--   Tiga tingkat rekomendasi, tidak lebih:
--
--     REKOMENDASI      -> Direkomendasikan
--     REKOM_BERSYARAT  -> Direkomendasikan Bersyarat
--     TIDAK_REKOM      -> Tidak Direkomendasikan
--
--   Kode_Keputusan dan Kode_Status lama tetap dipertahankan sebagai baris
--   tidak aktif, supaya keputusan yang terlanjur tersimpan tidak kehilangan
--   acuan dan jejak auditnya tetap dapat dibaca.
--
-- SIFAT:
--   * IDEMPOTEN - aman dijalankan berulang.
--   * Tidak menyentuh satu pun fitur lama.
--
-- PRASYARAT: 01..08 sudah dijalankan.
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;

PRINT '============================================================';
PRINT ' PENYELARASAN ISTILAH: KEPUTUSAN -> REKOMENDASI';
PRINT ' Database : ' + DB_NAME();
PRINT '============================================================';

BEGIN TRY
BEGIN TRANSACTION;

    -- ========================================================================
    -- 1. Status baru
    -- ========================================================================
    PRINT '';
    PRINT '--- 1. Status ---';

    INSERT INTO N_EMI_LAB_Verifikasi_Status (Kode_Status, Nama_Status, Urutan, Flag_Aktif)
    SELECT s.Kode_Status, s.Nama_Status, s.Urutan, 'Y'
    FROM (VALUES
        ('MENUNGGU',        'Menunggu Verifikasi',        1),
        ('REKOMENDASI',     'Direkomendasikan',           2),
        ('REKOM_BERSYARAT', 'Direkomendasikan Bersyarat', 3),
        ('TIDAK_REKOM',     'Tidak Direkomendasikan',     4)
    ) AS s(Kode_Status, Nama_Status, Urutan)
    WHERE NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Verifikasi_Status x
                      WHERE x.Kode_Status = s.Kode_Status);

    PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' status baru.';

    -- Nama status lama diperjelas sebagai riwayat, bukan pilihan aktif.
    UPDATE N_EMI_LAB_Verifikasi_Status
    SET Flag_Aktif = 'T'
    WHERE Kode_Status IN ('DISETUJUI', 'DISETUJUI_BERSYARAT', 'DITOLAK')
      AND Flag_Aktif <> 'T';

    PRINT '  [~] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' status lama dinonaktifkan.';


    -- ========================================================================
    -- 2. Jenis rekomendasi
    -- ========================================================================
    -- Aturan catatan mengikuti bobot pertanggungjawabannya: rekomendasi
    -- penuh cukup opsional, dua lainnya wajib disertai alasan.
    PRINT '';
    PRINT '--- 2. Jenis rekomendasi ---';

    INSERT INTO N_EMI_LAB_Verifikasi_Keputusan
        (Kode_Keputusan, Nama_Keputusan, Keterangan, Kode_Status,
         Flag_Wajib_Catatan, Panjang_Min_Catatan, Flag_Boleh_Lanjut,
         Flag_Perlu_Tindak, Warna_Badge, Ikon, Urutan, Flag_Aktif)
    SELECT k.Kode_Keputusan, k.Nama_Keputusan, k.Keterangan, k.Kode_Status,
           k.Wajib, k.Minimal, k.Lanjut, k.Tindak, k.Warna, k.Ikon, k.Urutan, 'Y'
    FROM (VALUES
        ('REKOMENDASI',
         'Direkomendasikan',
         'Seluruh analisa memenuhi kriteria kelayakan di master. Hasil layak diteruskan ke finalisasi.',
         'REKOMENDASI',     'T', 0,  'Y', 'T', 'ok',   'ri-checkbox-circle-line', 1),

        ('REKOM_BERSYARAT',
         'Direkomendasikan Bersyarat',
         'Terdapat catatan yang perlu diperhatikan, namun hasil masih dapat diteruskan ke finalisasi dengan justifikasi tertulis.',
         'REKOM_BERSYARAT', 'Y', 15, 'Y', 'Y', 'warn', 'ri-error-warning-line',   2),

        ('TIDAK_REKOM',
         'Tidak Direkomendasikan',
         'Hasil tidak memenuhi kriteria kelayakan. Tidak layak diteruskan ke finalisasi tanpa tindak lanjut.',
         'TIDAK_REKOM',     'Y', 15, 'T', 'Y', 'bad',  'ri-close-circle-line',    3)
    ) AS k(Kode_Keputusan, Nama_Keputusan, Keterangan, Kode_Status,
           Wajib, Minimal, Lanjut, Tindak, Warna, Ikon, Urutan)
    WHERE NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Verifikasi_Keputusan x
                      WHERE x.Kode_Keputusan = k.Kode_Keputusan);

    PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' jenis rekomendasi baru.';

    -- Jenis keputusan lama disembunyikan dari pilihan, tanpa dihapus.
    UPDATE N_EMI_LAB_Verifikasi_Keputusan
    SET Flag_Aktif = 'T'
    WHERE Kode_Keputusan IN ('SETUJU_PENUH', 'SETUJU_BERSYARAT', 'TOLAK')
      AND Flag_Aktif <> 'T';

    PRINT '  [~] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' jenis lama dinonaktifkan.';


    -- ========================================================================
    -- 3. Pemetaan data yang sudah tersimpan
    -- ========================================================================
    -- Keputusan yang terlanjur tercatat dipindahkan ke istilah baru agar
    -- layar tidak menampilkan campuran dua penamaan.
    PRINT '';
    PRINT '--- 3. Pemetaan data lama ---';

    DECLARE @Peta TABLE (Lama VARCHAR(30), Baru VARCHAR(30), StatusBaru VARCHAR(30));
    INSERT INTO @Peta VALUES
        ('SETUJU_PENUH',     'REKOMENDASI',     'REKOMENDASI'),
        ('SETUJU_BERSYARAT', 'REKOM_BERSYARAT', 'REKOM_BERSYARAT'),
        ('TOLAK',            'TIDAK_REKOM',     'TIDAK_REKOM');

    UPDATE h
    SET h.Kode_Keputusan = p.Baru,
        h.Kode_Status    = p.StatusBaru
    FROM N_EMI_LAB_Verifikasi_Header h
    JOIN @Peta p ON p.Lama = h.Kode_Keputusan;

    PRINT '  [~] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' header dipetakan.';

    -- Header tanpa Kode_Keputusan (mis. hasil migrasi lama) dipetakan dari
    -- statusnya saja.
    UPDATE N_EMI_LAB_Verifikasi_Header
    SET Kode_Status = CASE Kode_Status
            WHEN 'DISETUJUI'           THEN 'REKOMENDASI'
            WHEN 'DISETUJUI_BERSYARAT' THEN 'REKOM_BERSYARAT'
            WHEN 'DITOLAK'             THEN 'TIDAK_REKOM'
            ELSE Kode_Status END
    WHERE Kode_Status IN ('DISETUJUI', 'DISETUJUI_BERSYARAT', 'DITOLAK');

    PRINT '  [~] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' header status saja.';

    UPDATE w
    SET w.Kode_Keputusan = p.Baru
    FROM N_EMI_LAB_Verifikasi_Riwayat w
    JOIN @Peta p ON p.Lama = w.Kode_Keputusan;

    PRINT '  [~] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' riwayat: kode keputusan.';

    -- Riwayat adalah jejak audit: Status_Sebelum / Status_Sesudah ikut
    -- diselaraskan supaya rangkaian perubahan tetap terbaca utuh.
    UPDATE N_EMI_LAB_Verifikasi_Riwayat
    SET Status_Sesudah = CASE Status_Sesudah
            WHEN 'DISETUJUI'           THEN 'REKOMENDASI'
            WHEN 'DISETUJUI_BERSYARAT' THEN 'REKOM_BERSYARAT'
            WHEN 'DITOLAK'             THEN 'TIDAK_REKOM'
            ELSE Status_Sesudah END,
        Status_Sebelum = CASE Status_Sebelum
            WHEN 'DISETUJUI'           THEN 'REKOMENDASI'
            WHEN 'DISETUJUI_BERSYARAT' THEN 'REKOM_BERSYARAT'
            WHEN 'DITOLAK'             THEN 'TIDAK_REKOM'
            ELSE Status_Sebelum END,
        Aksi = CASE Aksi
            WHEN 'SETUJU_PENUH'     THEN 'REKOMENDASI'
            WHEN 'SETUJU_BERSYARAT' THEN 'REKOM_BERSYARAT'
            WHEN 'TOLAK'            THEN 'TIDAK_REKOM'
            ELSE Aksi END
    WHERE Status_Sesudah IN ('DISETUJUI', 'DISETUJUI_BERSYARAT', 'DITOLAK')
       OR Status_Sebelum IN ('DISETUJUI', 'DISETUJUI_BERSYARAT', 'DITOLAK')
       OR Aksi           IN ('SETUJU_PENUH', 'SETUJU_BERSYARAT', 'TOLAK');

    PRINT '  [~] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' riwayat: status & aksi.';

    UPDATE d
    SET d.Kode_Status = CASE d.Kode_Status
            WHEN 'DISETUJUI'           THEN 'REKOMENDASI'
            WHEN 'DISETUJUI_BERSYARAT' THEN 'REKOM_BERSYARAT'
            WHEN 'DITOLAK'             THEN 'TIDAK_REKOM'
            ELSE d.Kode_Status END
    FROM N_EMI_LAB_Verifikasi_Detail d
    WHERE d.Kode_Status IN ('DISETUJUI', 'DISETUJUI_BERSYARAT', 'DITOLAK');

    PRINT '  [~] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' detail dipetakan.';

COMMIT TRANSACTION;
PRINT '';
PRINT '============================================================';
PRINT ' PENYELARASAN ISTILAH SELESAI.';
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
PRINT '--- Pilihan rekomendasi yang aktif ---';

SELECT k.Urutan, k.Kode_Keputusan, k.Nama_Keputusan,
       k.Flag_Wajib_Catatan AS Wajib_Catatan,
       k.Panjang_Min_Catatan AS Min_Karakter,
       k.Flag_Boleh_Lanjut  AS Boleh_Lanjut,
       s.Nama_Status
FROM N_EMI_LAB_Verifikasi_Keputusan k
JOIN N_EMI_LAB_Verifikasi_Status s ON s.Kode_Status = k.Kode_Status
WHERE k.Flag_Aktif = 'Y'
ORDER BY k.Urutan;

PRINT '';
PRINT '--- Sebaran data setelah pemetaan ---';

SELECT Kode_Status, Kode_Keputusan, COUNT(*) AS Jumlah
FROM N_EMI_LAB_Verifikasi_Header
GROUP BY Kode_Status, Kode_Keputusan
ORDER BY Kode_Status;


/* ============================================================================
   ROLLBACK - mengembalikan istilah lama.
   ============================================================================

UPDATE N_EMI_LAB_Verifikasi_Header
SET Kode_Keputusan = CASE Kode_Keputusan
        WHEN 'REKOMENDASI'           THEN 'SETUJU_PENUH'
        WHEN 'REKOM_BERSYARAT' THEN 'SETUJU_BERSYARAT'
        WHEN 'TIDAK_REKOM'     THEN 'TOLAK' ELSE Kode_Keputusan END,
    Kode_Status = CASE Kode_Status
        WHEN 'REKOMENDASI'           THEN 'DISETUJUI'
        WHEN 'REKOM_BERSYARAT' THEN 'DISETUJUI_BERSYARAT'
        WHEN 'TIDAK_REKOM'     THEN 'DITOLAK' ELSE Kode_Status END;

UPDATE N_EMI_LAB_Verifikasi_Keputusan SET Flag_Aktif = 'Y'
WHERE Kode_Keputusan IN ('SETUJU_PENUH', 'SETUJU_BERSYARAT', 'TOLAK');

UPDATE N_EMI_LAB_Verifikasi_Keputusan SET Flag_Aktif = 'T'
WHERE Kode_Keputusan IN ('REKOMENDASI', 'REKOM_BERSYARAT', 'TIDAK_REKOM');

   ==========================================================================*/
