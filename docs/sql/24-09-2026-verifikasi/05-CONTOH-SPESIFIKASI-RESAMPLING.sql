-- ============================================================================
-- 05-CONTOH-SPESIFIKASI-RESAMPLING.sql
-- Tanggal : 24-09-2026
-- Modul   : VERIFIKASI HASIL ANALISA — data contoh
--
-- TUJUAN:
--   Menyediakan contoh nyata agar ketiga keadaan kelayakan terlihat
--   berdampingan pada satu layar verifikasi:
--
--     A. Spesifikasi ADA & hasil LAYAK        -> PROTEIN, SALT, MIKRO-YM
--     B. Spesifikasi ADA & hasil TIDAK LAYAK  -> ASH (66,67 di luar 0–20)
--     C. Spesifikasi TIDAK ADA di master      -> MIKROBIOLOGI-AC & EC
--
--   Ditambah satu contoh RESAMPLING pada ASH: hasil pertama di luar
--   spesifikasi, lalu diuji ulang pada sampel berikutnya.
--
-- SIFAT:
--   * IDEMPOTEN — hanya menambah yang belum ada; aman dijalankan berulang.
--   * Hanya MENAMBAH baris master rentang untuk kombinasi
--     (barang BRG08240003 x mesin 1) dan satu baris log resampling.
--     Tidak mengubah hasil uji, tidak menyentuh data transaksi lain.
--   * Seluruhnya dapat dicabut kembali lewat bagian ROLLBACK di bawah.
--
-- PRASYARAT: 01..04 sudah dijalankan.
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @Sampel  VARCHAR(30) = 'FS0926-0002';   -- sampel yang dipakai contoh
DECLARE @Barang  VARCHAR(50);
DECLARE @Mesin   INT;
DECLARE @User    VARCHAR(30) = 'SV_LAB';
DECLARE @Now     DATETIME;

PRINT '============================================================';
PRINT ' DATA CONTOH: SPESIFIKASI & RESAMPLING';
PRINT ' Database : ' + DB_NAME();
PRINT '============================================================';

BEGIN TRY
BEGIN TRANSACTION;

    SELECT @Barang = Kode_Barang, @Mesin = Id_Mesin
    FROM N_EMI_LAB_PO_Sampel
    WHERE No_Sampel = @Sampel;

    IF @Barang IS NULL
        THROW 55001, 'Sampel contoh tidak ditemukan. Sesuaikan @Sampel di skrip ini.', 1;

    SELECT @Now = CAST(dbo.Get_Date_Time() AS DATETIME);

    PRINT '';
    PRINT '--- Konteks ---';
    PRINT '  Sampel : ' + @Sampel;
    PRINT '  Barang : ' + @Barang;
    PRINT '  Mesin  : ' + CAST(@Mesin AS VARCHAR(10));


    -- ========================================================================
    -- A. Spesifikasi (batas min–max) untuk sebagian analisa
    -- ========================================================================
    -- Batas dipilih agar hasil yang sudah ada menghasilkan campuran layak
    -- dan tidak layak, sehingga perbedaannya terlihat jelas di layar.
    --
    --   PROTEIN  hasil 4,06   -> batas 3,0–6,0   = LAYAK
    --   SALT     hasil 2,63   -> batas 1,5–4,0   = LAYAK
    --   MIKRO-YM hasil 10     -> batas 0–100     = LAYAK
    --   ASH      hasil 66,67  -> batas 0–20      = TIDAK LAYAK (memicu resampling)
    --
    -- MIKROBIOLOGI-AC dan EC sengaja TIDAK diberi batas, supaya contoh
    -- "tidak ada di master kriteria kelayakan" tetap tampil.
    PRINT '';
    PRINT '--- A. Standar rentang (spesifikasi) ---';

    INSERT INTO N_EMI_LAB_Standar_Rentang
        (Kode_Perusahaan, Id_Jenis_Analisa, Kode_Barang, Id_Master_Mesin,
         Id_Perhitungan, Range_Awal, Range_Akhir, Tanggal, Jam, Id_User, Kode_Role)
    SELECT '001', s.Id_Jenis_Analisa, @Barang, @Mesin,
           NULL, s.Awal, s.Akhir, @Now, CONVERT(VARCHAR(8), @Now, 108), @User, 'LAB'
    FROM (VALUES
        (15, 3.0,  6.0),    -- PROTEIN ANALYSIS  -> layak
        (17, 1.5,  4.0),    -- SALT ANALYSIS     -> layak
        (44, 0.0,  100.0),  -- MIKROBIOLOGI-YM   -> layak
        (16, 0.0,  20.0)    -- ASH ANALYSIS      -> TIDAK layak
    ) AS s(Id_Jenis_Analisa, Awal, Akhir)
    WHERE EXISTS (SELECT 1 FROM N_EMI_LAB_Jenis_Analisa ja WHERE ja.id = s.Id_Jenis_Analisa)
      AND NOT EXISTS (
            SELECT 1 FROM N_EMI_LAB_Standar_Rentang r
            WHERE r.Id_Jenis_Analisa = s.Id_Jenis_Analisa
              AND r.Kode_Barang      = @Barang
              AND r.Id_Master_Mesin  = @Mesin
      );

    PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' baris standar rentang.';
    PRINT '      (MIKROBIOLOGI-AC & EC sengaja dibiarkan tanpa standar)';


    -- ========================================================================
    -- B. Contoh resampling pada ASH ANALYSIS
    -- ========================================================================
    -- Menggambarkan alur nyata: hasil pertama di luar spesifikasi, lalu
    -- sampel diuji ulang. Nomor sampel ulang memakai pola yang sama dengan
    -- sistem: <sampel>-1 diulang menjadi <sampel>-2.
    PRINT '';
    PRINT '--- B. Log resampling (ASH ANALYSIS) ---';

    INSERT INTO N_EMI_LAB_Uji_Sampel_Resampling_Log
        (No_Po_Sampel, Tahapan_Ke, No_Sampel_Resampling_Origin, No_Sampel_Resampling,
         Keterangan, Tanggal, Jam, Id_User, Id_Jenis_Analisa, Flag_Selesai_Resampling)
    SELECT @Sampel, 2, @Sampel + '-1', @Sampel + '-2',
           'Hasil abu di luar spesifikasi, dilakukan uji ulang',
           @Now, CONVERT(VARCHAR(8), @Now, 108), @User, 16, 'Y'
    WHERE NOT EXISTS (
        SELECT 1 FROM N_EMI_LAB_Uji_Sampel_Resampling_Log
        WHERE No_Po_Sampel = @Sampel AND Id_Jenis_Analisa = 16
    );

    PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' baris log resampling.';

COMMIT TRANSACTION;
PRINT '';
PRINT '============================================================';
PRINT ' DATA CONTOH SELESAI.';
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
SET NOCOUNT ON;
PRINT '';
PRINT '--- Hasil yang akan tampil di layar verifikasi ---';

SELECT
    ja.Jenis_Analisa                              AS Analisa,
    CAST(ROUND(u.Hasil, 4) AS DECIMAL(18,4))      AS Hasil,
    r.Range_Awal                                  AS Spesifikasi_Min,
    r.Range_Akhir                                 AS Spesifikasi_Max,
    CASE
        WHEN r.Id_Standar_Rentang IS NULL THEN 'Tidak ada di master'
        WHEN u.Hasil < r.Range_Awal  THEN 'TIDAK LAYAK (di bawah min)'
        WHEN u.Hasil > r.Range_Akhir THEN 'TIDAK LAYAK (di atas max)'
        ELSE 'Layak'
    END                                           AS Penilaian,
    ISNULL(CAST(rs.Jumlah AS VARCHAR(10)) + 'x diulang', 'Tidak ada') AS Resampling
FROM N_EMI_LAB_Uji_Sampel u
JOIN N_EMI_LAB_Jenis_Analisa ja
    ON ja.id = u.Id_Jenis_Analisa
JOIN N_EMI_LAB_PO_Sampel p
    ON p.No_Sampel = u.No_Po_Sampel
LEFT JOIN N_EMI_LAB_Standar_Rentang r
    ON r.Id_Jenis_Analisa = u.Id_Jenis_Analisa
   AND r.Kode_Barang      = p.Kode_Barang
   AND r.Id_Master_Mesin  = p.Id_Mesin
OUTER APPLY (
    SELECT COUNT(*) AS Jumlah
    FROM N_EMI_LAB_Uji_Sampel_Resampling_Log g
    WHERE g.No_Po_Sampel = u.No_Po_Sampel
      AND g.Id_Jenis_Analisa = u.Id_Jenis_Analisa
) rs
WHERE u.No_Po_Sampel = 'FS0926-0002'
  AND ja.Kode_Aktivitas_Lab = 'ANL'
ORDER BY ja.Jenis_Analisa;


/* ============================================================================
   ROLLBACK — buka komentar bila data contoh ingin dicabut kembali.
   ============================================================================

DELETE FROM N_EMI_LAB_Uji_Sampel_Resampling_Log
WHERE No_Po_Sampel = 'FS0926-0002' AND Id_Jenis_Analisa = 16;

DELETE r
FROM N_EMI_LAB_Standar_Rentang r
JOIN N_EMI_LAB_PO_Sampel p ON p.No_Sampel = 'FS0926-0002'
WHERE r.Kode_Barang = p.Kode_Barang
  AND r.Id_Master_Mesin = p.Id_Mesin
  AND r.Id_Jenis_Analisa IN (15, 16, 17, 44);

   ==========================================================================*/
