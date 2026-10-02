-- ============================================================================
-- 08-CONTOH-STANDAR-LENGKAP.sql
-- Tanggal : 24-09-2026
-- Modul   : VERIFIKASI HASIL ANALISA - contoh sampel "bersih"
--
-- TUJUAN:
--   Menyediakan satu sampel yang enak dipandang sebagai acuan tampilan:
--   SELURUH analisa punya spesifikasi di master DAN seluruh hasilnya LAYAK.
--   Tidak ada kolom yang kosong, tidak ada peringatan "tidak ada di master",
--   tidak ada resampling.
--
--     FS0926-0018 -> 6 analisa, 6 spesifikasi, 6 hasil layak
--
-- MENGAPA MESIN 2:
--   Pada mesin 1, MIKRO-AC (43) dan MIKRO-EC (42) memang sengaja dibiarkan
--   tanpa standar agar keadaan "tidak ada di master" tetap terwakili di
--   layar. Menambahkan standar untuk keduanya di mesin 1 akan menghapus
--   contoh tersebut. Karena itu sampel bersih ini memakai mesin 2 dengan
--   set standar sendiri yang lengkap, sehingga kedua keadaan tetap ada.
--
-- SIFAT:
--   * IDEMPOTEN - seluruh INSERT dijaga NOT EXISTS; aman dijalankan ulang.
--   * HANYA MENAMBAH: sampel FS0926-0018 dan standar rentang mesin 2.
--     Sampel FS0926-0001 s/d FS0926-0017 TIDAK DISENTUH.
--   * Tidak mengubah satu pun baris milik modul lama.
--   * Dapat dicabut seluruhnya lewat bagian ROLLBACK di bawah.
--
-- PRASYARAT: 01..07 sudah dijalankan.
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @Sampel VARCHAR(30)  = 'FS0926-0018';
DECLARE @Barang VARCHAR(50)  = 'BRG08240003';
DECLARE @Mesin  INT          = 2;     -- mesin tersendiri, lihat catatan di atas
DECLARE @Po     VARCHAR(50)  = 'PRD0626-00001';
DECLARE @Split  VARCHAR(50)  = 'PRD0626-00001-1';
DECLARE @User   VARCHAR(30)  = 'SV_LAB';
DECLARE @Now    DATETIME     = CAST(dbo.Get_Date_Time() AS DATETIME);
DECLARE @Hari   DATE         = CAST(@Now AS DATE);

PRINT '============================================================';
PRINT ' DATA CONTOH: SAMPEL DENGAN STANDAR LENGKAP & SEMUA LAYAK';
PRINT ' Database : ' + DB_NAME();
PRINT '============================================================';

BEGIN TRY
BEGIN TRANSACTION;

    -- ========================================================================
    -- 0. Rencana analisa
    -- ========================================================================
    -- Setiap analisa diberi batas DAN nilai hasil yang berada nyaman di
    -- tengah batas tersebut, supaya seluruh baris terbaca "Layak".
    DECLARE @A TABLE (
        Id_Jenis_Analisa INT,
        Id_Perhitungan   INT,
        Range_Awal       FLOAT,
        Range_Akhir      FLOAT,
        Hasil            FLOAT,
        Urut             INT
    );

    INSERT INTO @A (Id_Jenis_Analisa, Id_Perhitungan, Range_Awal, Range_Akhir, Hasil, Urut) VALUES
        (15, 36,   3.00,    6.00,    4.35, 1),   -- PROTEIN ANALYSIS
        (16, 37,   0.00,   20.00,   11.20, 2),   -- ASH ANALYSIS
        (17, 38,   1.50,    4.00,    2.45, 3),   -- SALT ANALYSIS
        (42, 67,   0.00,   10.00,    0.00, 4),   -- MIKROBIOLOGI-EC
        (43, 66,   0.00,  500.00,   42.00, 5),   -- MIKROBIOLOGI-AC
        (44, 68,   0.00,  100.00,   18.00, 6);   -- MIKROBIOLOGI-YM


    -- ========================================================================
    -- 1. Standar rentang untuk mesin 2 - LENGKAP, tanpa kecuali
    -- ========================================================================
    PRINT '';
    PRINT '--- 1. Standar rentang (mesin 2) ---';

    INSERT INTO N_EMI_LAB_Standar_Rentang
        (Kode_Perusahaan, Id_Jenis_Analisa, Kode_Barang, Id_Master_Mesin,
         Id_Perhitungan, Range_Awal, Range_Akhir, Tanggal, Jam, Id_User, Kode_Role)
    SELECT '001', a.Id_Jenis_Analisa, @Barang, @Mesin,
           NULL, a.Range_Awal, a.Range_Akhir, @Now,
           CONVERT(VARCHAR(8), @Now, 108), @User, 'LAB'
    FROM @A a
    WHERE EXISTS (SELECT 1 FROM N_EMI_LAB_Jenis_Analisa ja WHERE ja.id = a.Id_Jenis_Analisa)
      AND NOT EXISTS (
            SELECT 1 FROM N_EMI_LAB_Standar_Rentang r
            WHERE r.Id_Jenis_Analisa = a.Id_Jenis_Analisa
              AND r.Kode_Barang      = @Barang
              AND r.Id_Master_Mesin  = @Mesin
      );

    PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' baris standar rentang.';


    -- ========================================================================
    -- 2. PO Sampel
    -- ========================================================================
    PRINT '';
    PRINT '--- 2. PO Sampel ---';

    INSERT INTO N_EMI_LAB_PO_Sampel
        (Kode_Perusahaan, No_Po, Kode_Barang, No_Sampel, Tanggal, Jam,
         No_Split_Po, No_Batch, Id_Mesin, Keterangan, Id_User,
         Jumlah_Pcs, Flag_Trial_Produksi)
    SELECT '001', @Po, @Barang, @Sampel, @Hari,
           CONVERT(VARCHAR(8), DATEADD(MINUTE, 130, @Now), 108),
           @Split, '1', @Mesin, 'Contoh sampel dengan spesifikasi lengkap',
           'FRANS', 6, 'Y'
    WHERE NOT EXISTS (SELECT 1 FROM N_EMI_LAB_PO_Sampel p
                      WHERE p.No_Sampel = @Sampel);

    PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' baris PO sampel.';


    -- ========================================================================
    -- 3. Hasil uji - seluruhnya di dalam rentang
    -- ========================================================================
    -- Range_Awal / Range_Akhir ikut disimpan pada baris hasil uji agar
    -- batas yang berlaku saat pengujian tetap terekam, tidak hanya
    -- bergantung pada master yang bisa berubah di kemudian hari.
    PRINT '';
    PRINT '--- 3. Hasil uji ---';

    INSERT INTO N_EMI_LAB_Uji_Sampel
        (Kode_Perusahaan, No_Faktur, No_Po_Sampel, No_Fak_Sub_Po,
         Id_Jenis_Analisa, Hasil, Flag_Perhitungan, Status, Tanggal, Jam,
         Id_User, Flag_Selesai, Id_Perhitungan, Range_Awal, Range_Akhir,
         Tahapan_Ke, Flag_Resampling, Status_Keputusan_Sampel, Flag_Layak, Id_Mesin)
    SELECT '001',
           'FUS0926-98' + CAST(a.Urut AS VARCHAR(1)),
           @Sampel,
           NULL,                                  -- bukan multi QR code
           a.Id_Jenis_Analisa, a.Hasil, 'Y', NULL, @Hari,
           CONVERT(VARCHAR(8), DATEADD(MINUTE, 130 + a.Urut, @Now), 108),
           @User, 'Y', a.Id_Perhitungan,
           a.Range_Awal, a.Range_Akhir,
           1,                                     -- sekali uji, tanpa resampling
           NULL, 'terima', 'Y', @Mesin
    FROM @A a
    WHERE NOT EXISTS (
        SELECT 1 FROM N_EMI_LAB_Uji_Sampel u
        WHERE u.No_Po_Sampel = @Sampel
          AND u.Id_Jenis_Analisa = a.Id_Jenis_Analisa
    );

    PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' baris hasil uji.';

COMMIT TRANSACTION;
PRINT '';
PRINT '============================================================';
PRINT ' DATA CONTOH SELESAI.';
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
PRINT '--- FS0926-0018 pada layar verifikasi ---';

SELECT ja.Jenis_Analisa                          AS Analisa,
       CAST(ROUND(u.Hasil, 4) AS DECIMAL(18,4))  AS Hasil,
       r.Range_Awal                              AS Spesifikasi_Min,
       r.Range_Akhir                             AS Spesifikasi_Max,
       CASE
           WHEN r.Id_Standar_Rentang IS NULL THEN 'Tidak ada di master'
           WHEN u.Hasil < r.Range_Awal       THEN 'TIDAK LAYAK (di bawah min)'
           WHEN u.Hasil > r.Range_Akhir      THEN 'TIDAK LAYAK (di atas max)'
           ELSE 'Layak'
       END                                       AS Penilaian
FROM N_EMI_LAB_Uji_Sampel u
JOIN N_EMI_LAB_Jenis_Analisa ja ON ja.id = u.Id_Jenis_Analisa
JOIN N_EMI_LAB_PO_Sampel p      ON p.No_Sampel = u.No_Po_Sampel
LEFT JOIN N_EMI_LAB_Standar_Rentang r
       ON r.Id_Jenis_Analisa = u.Id_Jenis_Analisa
      AND r.Kode_Barang      = p.Kode_Barang
      AND r.Id_Master_Mesin  = p.Id_Mesin
WHERE u.No_Po_Sampel = 'FS0926-0018'
ORDER BY ja.Jenis_Analisa;


/* ============================================================================
   ROLLBACK - buka komentar bila data contoh ingin dicabut kembali.
   ============================================================================

DELETE FROM N_EMI_LAB_Uji_Sampel  WHERE No_Po_Sampel = 'FS0926-0018';
DELETE FROM N_EMI_LAB_PO_Sampel   WHERE No_Sampel    = 'FS0926-0018';

DELETE FROM N_EMI_LAB_Standar_Rentang
WHERE Kode_Barang = 'BRG08240003'
  AND Id_Master_Mesin = 2
  AND Id_Jenis_Analisa IN (15, 16, 17, 42, 43, 44);

   ==========================================================================*/
