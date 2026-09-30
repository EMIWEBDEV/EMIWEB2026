-- ============================================================================
-- 05-PERBAIKAN-KONTEKS.sql  —  Perbaikan data hasil validasi (DML)
-- Tanggal : 24-09-2026
--
-- Memperbaiki tiga kesalahan pada baris Detail_Final dan Approval yang
-- terlanjur tersimpan sebelum perbaikan kode:
--
--   A. No_Sub_Sampel salah
--      Sampel tunggal (bukan multi QR) seharusnya ber-No_Sub_Sampel NULL,
--      sama seperti No_Fak_Sub_Po di N_EMI_LAB_Uji_Sampel. Yang tersimpan
--      justru No_Sampel itu sendiri, atau placeholder tampilan "—" yang ikut
--      terkirim dari frontend.
--
--   B. Id_Session / Id_Pembanding kosong pada palatabilitas
--      Konteks PLT diambil dari request, padahal frontend tidak pernah
--      mengirimkannya. Nilainya ada di Uji_Sampel dan dipulihkan dari sana.
--
--   C. Id_Uji_Validasi_Final kosong padahal header-nya sudah ada
--      Terjadi bila detail ditulis setelah sampel difinalisasi.
--
-- SIFAT SKRIP:
--   * IDEMPOTEN   — semua UPDATE bersyarat; dijalankan berulang kali aman.
--   * TRANSAKSIONAL — gagal di tengah = rollback penuh.
--   * Tidak menghapus baris, tidak mengubah Flag_Layak, tidak menyentuh
--     kolom yang sudah benar.
--
-- PRASYARAT: 01-STRUKTUR.sql sudah dijalankan.
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @n INT;

PRINT '============================================================';
PRINT ' 05-PERBAIKAN-KONTEKS — mulai ' + CONVERT(VARCHAR(19), GETDATE(), 120);
PRINT '============================================================';

BEGIN TRY
BEGIN TRANSACTION;

    IF COL_LENGTH('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','Id_Session') IS NULL
        THROW 53001, 'Kolom Id_Session belum ada. Jalankan 01-STRUKTUR.sql dulu.', 1;

    PRINT '[OK] Prasyarat terpenuhi.';
    PRINT '';


    -- ========================================================================
    -- A. No_Sub_Sampel — kembalikan ke NULL untuk sampel tunggal
    -- ========================================================================
    PRINT '--- A. Perbaiki No_Sub_Sampel ---';

    -- A1. Placeholder tampilan yang ikut tersimpan.
    UPDATE N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
       SET No_Sub_Sampel = NULL
     WHERE No_Sub_Sampel IN ('—', '–', '-', '');
    SET @n = @@ROWCOUNT;
    PRINT '  [A1] ' + CAST(@n AS VARCHAR(10)) + ' baris detail: placeholder -> NULL.';

    UPDATE N_EMI_LAB_Hasil_Uji_Approval_Aktivitas
       SET No_Sub_Sampel = NULL
     WHERE No_Sub_Sampel IN ('—', '–', '-', '');
    SET @n = @@ROWCOUNT;
    PRINT '  [A2] ' + CAST(@n AS VARCHAR(10)) + ' baris approval: placeholder -> NULL.';

    -- A3. No_Sub_Sampel = No_Sampel padahal di Uji_Sampel NULL (sampel tunggal).
    --     Hanya disentuh bila Uji_Sampel memang membuktikan sampel itu tunggal,
    --     supaya sub-sampel multi QR yang sah tidak ikut dikosongkan.
    UPDATE d
       SET d.No_Sub_Sampel = NULL
    FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d
    WHERE d.No_Sub_Sampel = d.No_Sampel
      AND EXISTS (
            SELECT 1 FROM N_EMI_LAB_Uji_Sampel u
            WHERE u.No_Po_Sampel     = d.No_Sampel
              AND u.Id_Jenis_Analisa = d.Id_Jenis_Analisa
              AND u.No_Fak_Sub_Po IS NULL
      );
    SET @n = @@ROWCOUNT;
    PRINT '  [A3] ' + CAST(@n AS VARCHAR(10)) + ' baris detail: No_Sampel -> NULL (sampel tunggal).';

    UPDATE a
       SET a.No_Sub_Sampel = NULL
    FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas a
    WHERE a.No_Sub_Sampel = a.No_Sampel
      AND EXISTS (
            SELECT 1 FROM N_EMI_LAB_Uji_Sampel u
            WHERE u.No_Po_Sampel     = a.No_Sampel
              AND u.Id_Jenis_Analisa = a.Id_Jenis_Analisa
              AND u.No_Fak_Sub_Po IS NULL
      );
    SET @n = @@ROWCOUNT;
    PRINT '  [A4] ' + CAST(@n AS VARCHAR(10)) + ' baris approval: No_Sampel -> NULL (sampel tunggal).';
    PRINT '';


    -- ========================================================================
    -- B. Id_Session / Id_Pembanding — pulihkan konteks palatabilitas
    -- ========================================================================
    -- Diambil dari Uji_Sampel, yang menyimpannya dengan benar sejak awal.
    -- Hanya baris yang kedua kolomnya masih kosong yang disentuh.
    PRINT '--- B. Pulihkan konteks PLT ---';

    UPDATE d
       SET d.Id_Session    = x.Id_Session,
           d.Id_Pembanding = x.Id_Pembanding
    FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d
    CROSS APPLY (
        SELECT TOP 1 u.Id_Session, u.Id_Pembanding
        FROM N_EMI_LAB_Uji_Sampel u
        WHERE u.No_Po_Sampel     = d.No_Sampel
          AND u.Id_Jenis_Analisa = d.Id_Jenis_Analisa
          AND (u.Id_Session IS NOT NULL OR u.Id_Pembanding IS NOT NULL)
          AND (d.No_Sub_Sampel IS NULL OR u.No_Fak_Sub_Po = d.No_Sub_Sampel
               OR u.No_Fak_Sub_Po IS NULL)
        ORDER BY u.Tahapan_Ke DESC
    ) x
    WHERE d.Id_Session IS NULL
      AND d.Id_Pembanding IS NULL;
    SET @n = @@ROWCOUNT;
    PRINT '  [B1] ' + CAST(@n AS VARCHAR(10)) + ' baris detail dipulihkan konteks PLT-nya.';

    UPDATE a
       SET a.Id_Session    = x.Id_Session,
           a.Id_Pembanding = x.Id_Pembanding
    FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas a
    CROSS APPLY (
        SELECT TOP 1 u.Id_Session, u.Id_Pembanding
        FROM N_EMI_LAB_Uji_Sampel u
        WHERE u.No_Po_Sampel     = a.No_Sampel
          AND u.Id_Jenis_Analisa = a.Id_Jenis_Analisa
          AND (u.Id_Session IS NOT NULL OR u.Id_Pembanding IS NOT NULL)
          AND (a.No_Sub_Sampel IS NULL OR u.No_Fak_Sub_Po = a.No_Sub_Sampel
               OR u.No_Fak_Sub_Po IS NULL)
        ORDER BY u.Tahapan_Ke DESC
    ) x
    WHERE a.Id_Session IS NULL
      AND a.Id_Pembanding IS NULL
      AND a.Jenis_Approval = 'VALIDASI';
    SET @n = @@ROWCOUNT;
    PRINT '  [B2] ' + CAST(@n AS VARCHAR(10)) + ' baris approval dipulihkan konteks PLT-nya.';
    PRINT '';


    -- ========================================================================
    -- C. Id_Uji_Validasi_Final — sambungkan detail yang header-nya sudah ada
    -- ========================================================================
    -- Aman karena No_Sampel terbukti unik di tabel header (lihat PRACHECK 6
    -- dan UNIQUE index UX_HasilUjiValidasiFinal_SplitPo_Batch_Sampel).
    PRINT '--- C. Sambungkan detail ke header ---';

    UPDATE d
       SET d.Id_Uji_Validasi_Final = h.Id_Uji_Validasi_Final
    FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d
    JOIN N_EMI_LAB_Hasil_Uji_Validasi_Final h
        ON h.No_Sampel = d.No_Sampel
    WHERE d.Id_Uji_Validasi_Final IS NULL;
    SET @n = @@ROWCOUNT;
    PRINT '  [C] ' + CAST(@n AS VARCHAR(10)) + ' baris detail tersambung ke header.';
    PRINT '';


COMMIT TRANSACTION;
PRINT '============================================================';
PRINT ' 05-PERBAIKAN-KONTEKS BERHASIL.';
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
PRINT '';
PRINT '--- Verifikasi ---';

SELECT 'Detail: No_Sub_Sampel = No_Sampel padahal sampel tunggal' AS Pemeriksaan,
       COUNT(*) AS Jumlah,
       CASE WHEN COUNT(*) = 0 THEN 'LULUS' ELSE 'MASIH ADA' END AS HASIL
FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d
WHERE d.No_Sub_Sampel = d.No_Sampel
  AND EXISTS (SELECT 1 FROM N_EMI_LAB_Uji_Sampel u
              WHERE u.No_Po_Sampel = d.No_Sampel
                AND u.Id_Jenis_Analisa = d.Id_Jenis_Analisa
                AND u.No_Fak_Sub_Po IS NULL)

UNION ALL
SELECT 'Detail: placeholder tampilan tersisa',
       COUNT(*),
       CASE WHEN COUNT(*) = 0 THEN 'LULUS' ELSE 'MASIH ADA' END
FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
WHERE No_Sub_Sampel IN ('—', '–', '-', '')

UNION ALL
SELECT 'Detail PLT: konteks sesi masih kosong padahal ada di Uji_Sampel',
       COUNT(*),
       CASE WHEN COUNT(*) = 0 THEN 'LULUS' ELSE 'MASIH ADA' END
FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d
WHERE d.Kode_Aktivitas_Lab = 'PLT'
  AND d.Id_Session IS NULL
  AND EXISTS (SELECT 1 FROM N_EMI_LAB_Uji_Sampel u
              WHERE u.No_Po_Sampel = d.No_Sampel
                AND u.Id_Jenis_Analisa = d.Id_Jenis_Analisa
                AND u.Id_Session IS NOT NULL)

UNION ALL
SELECT 'Detail: header sudah ada tapi FK masih kosong',
       COUNT(*),
       CASE WHEN COUNT(*) = 0 THEN 'LULUS' ELSE 'MASIH ADA' END
FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d
WHERE d.Id_Uji_Validasi_Final IS NULL
  AND EXISTS (SELECT 1 FROM N_EMI_LAB_Hasil_Uji_Validasi_Final h
              WHERE h.No_Sampel = d.No_Sampel);

-- Detail yang FK-nya NULL karena sampelnya memang belum difinalisasi.
-- Ini kondisi SAH, bukan kesalahan — ditampilkan sebagai informasi.
SELECT 'Detail belum difinalisasi (FK NULL, wajar)' AS Keterangan,
       COUNT(*) AS Jumlah
FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d
WHERE d.Id_Uji_Validasi_Final IS NULL
  AND NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Hasil_Uji_Validasi_Final h
                  WHERE h.No_Sampel = d.No_Sampel);
