-- ============================================================================
-- 13-CONTOH-PINDAH-KE-AUTOCLAVE.sql
-- Tanggal : 25-09-2026
-- Modul   : VERIFIKASI & FINALISASI - cakupan mesin AUTOCLAVE
--
-- LATAR:
--   Verifikasi dan finalisasi kini HANYA melayani sampel mesin AUTOCLAVE
--   (lihat app/Services/CakupanMesinService.php). Sampel contoh dari skrip
--   07 dan 08 masih bermesin GRINDER (FS0926-0003 .. 0017) dan MIXER
--   (FS0926-0018), sehingga seluruhnya keluar dari antrean: verifikator
--   Analisa Lab tinggal punya satu sampel, dan tab Direkomendasikan,
--   Bersyarat, serta Tidak menjadi kosong.
--
-- PERUBAHAN (hanya sampel contoh FS0926-0003 .. FS0926-0018):
--   1. Batas min-max yang berlaku saat pengujian dibekukan ke baris uji.
--      Contoh 0003..0017 dinilai memakai master standar rentang GRINDER
--      (barang x mesin 1). AUTOCLAVE tidak punya master tersebut, sehingga
--      tanpa langkah ini seluruh contoh berubah menjadi "belum dapat
--      dinilai". Batasnya disalin ke Range_Awal / Range_Akhir baris uji —
--      kolom yang memang menyimpan batas saat pengujian — sehingga layak /
--      tidak layak tiap contoh tetap sama. MIKRO-AC dan MIKRO-EC yang sejak
--      awal sengaja tanpa standar tetap tanpa standar.
--      FS0926-0018 sudah menyimpan batasnya sendiri; tidak tersentuh.
--   2. Mesin sampel dipindah ke AUTOCLAVE pada N_EMI_LAB_PO_Sampel dan
--      N_EMI_LAB_Uji_Sampel.
--   3. Salinan mesin pada header rekomendasi (N_EMI_LAB_Verifikasi_Header)
--      disamakan, supaya layar tidak menyebut GRINDER untuk sampel AUTOCLAVE.
--
-- YANG TIDAK DIUBAH:
--   * Master standar rentang. Tidak ditambah untuk AUTOCLAVE, supaya
--     penilaian sampel nyata FS0926-0001 tidak ikut berubah.
--   * FS0926-0001 dan FS0926-0002 (data nyata). FS0926-0002 bermesin
--     GRINDER sehingga memang keluar dari antrean verifikasi & finalisasi.
--   * Snapshot audit N_EMI_LAB_Verifikasi_Detail dan riwayat rekomendasi.
--
-- SIFAT:
--   * IDEMPOTEN - baris yang sudah sesuai dilewati; aman dijalankan ulang.
--   * TRANSAKSIONAL - gagal di tengah = rollback penuh.
--   * Menolak berjalan di database produksi.
--   * Dapat dicabut lewat bagian ROLLBACK di bawah.
--
-- PRASYARAT: 01..12 sudah dijalankan.
-- ============================================================================

-- Header rekomendasi memiliki filtered index: UPDATE-nya menuntut
-- QUOTED_IDENTIFIER dan ANSI_NULLS ON. SSMS menyalakannya secara bawaan,
-- sqlcmd tidak (kecuali dengan -I). Disetel di batch tersendiri karena
-- keduanya berlaku sejak batch berikutnya diurai.
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;

PRINT '============================================================';
PRINT ' DATA CONTOH: PINDAH KE MESIN AUTOCLAVE';
PRINT ' Database : ' + DB_NAME();
PRINT '============================================================';

IF DB_NAME() = 'emi_db_real'
BEGIN
    RAISERROR('Skrip data contoh tidak boleh dijalankan di database produksi.', 16, 1);
    RETURN;
END;

DECLARE @Autoclave INT;
DECLARE @Nama      VARCHAR(100);

BEGIN TRY
BEGIN TRANSACTION;

    -- ========================================================================
    -- 0. Mesin tujuan dan sampel contoh
    -- ========================================================================
    -- Dikenali dari namanya, sama seperti aplikasi, karena id mesin dapat
    -- berbeda antarlingkungan.
    IF (SELECT COUNT(*) FROM EMI_Master_Mesin
        WHERE UPPER(LTRIM(RTRIM(Nama_Mesin))) = 'AUTOCLAVE') <> 1
        THROW 50001, 'Mesin AUTOCLAVE harus terdaftar tepat satu kali di EMI_Master_Mesin.', 1;

    SELECT @Autoclave = Id_Master_Mesin, @Nama = LTRIM(RTRIM(Nama_Mesin))
    FROM EMI_Master_Mesin
    WHERE UPPER(LTRIM(RTRIM(Nama_Mesin))) = 'AUTOCLAVE';

    PRINT ' Mesin tujuan : ' + @Nama + ' (Id_Master_Mesin = ' + CAST(@Autoclave AS VARCHAR(10)) + ')';

    DECLARE @S TABLE (No_Sampel VARCHAR(30) PRIMARY KEY);

    INSERT INTO @S (No_Sampel) VALUES
        ('FS0926-0003'), ('FS0926-0004'), ('FS0926-0005'), ('FS0926-0006'),
        ('FS0926-0007'), ('FS0926-0008'), ('FS0926-0009'), ('FS0926-0010'),
        ('FS0926-0011'), ('FS0926-0012'), ('FS0926-0013'), ('FS0926-0014'),
        ('FS0926-0015'), ('FS0926-0016'), ('FS0926-0017'), ('FS0926-0018');


    -- ========================================================================
    -- 1. Bekukan batas min-max ke baris uji
    -- ========================================================================
    -- Harus SEBELUM mesin dipindah: batasnya dibaca dari master mesin asal.
    PRINT '';
    PRINT '--- 1. Batas min-max dari master mesin asal ---';

    UPDATE u
    SET    u.Range_Awal  = s.Range_Awal,
           u.Range_Akhir = s.Range_Akhir
    FROM   N_EMI_LAB_Uji_Sampel u
    JOIN   @S x                       ON x.No_Sampel = u.No_Po_Sampel
    JOIN   N_EMI_LAB_PO_Sampel p      ON p.No_Sampel = u.No_Po_Sampel
    JOIN   N_EMI_LAB_Standar_Rentang s
           ON  s.Id_Jenis_Analisa = u.Id_Jenis_Analisa
           AND s.Kode_Barang      = p.Kode_Barang
           AND s.Id_Master_Mesin  = p.Id_Mesin
    WHERE  u.Flag_Perhitungan = 'Y'
      AND  u.Range_Awal  IS NULL
      AND  u.Range_Akhir IS NULL
      AND  p.Id_Mesin <> @Autoclave;

    PRINT '  [~] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' baris uji diberi batas.';


    -- ========================================================================
    -- 2. Pindahkan mesin sampel
    -- ========================================================================
    PRINT '';
    PRINT '--- 2. Mesin sampel -> AUTOCLAVE ---';

    UPDATE p
    SET    p.Id_Mesin = @Autoclave
    FROM   N_EMI_LAB_PO_Sampel p
    JOIN   @S x ON x.No_Sampel = p.No_Sampel
    WHERE  ISNULL(p.Id_Mesin, -1) <> @Autoclave;

    PRINT '  [~] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' baris PO sampel.';

    UPDATE u
    SET    u.Id_Mesin = @Autoclave
    FROM   N_EMI_LAB_Uji_Sampel u
    JOIN   @S x ON x.No_Sampel = u.No_Po_Sampel
    WHERE  ISNULL(u.Id_Mesin, -1) <> @Autoclave;

    PRINT '  [~] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' baris uji sampel.';


    -- ========================================================================
    -- 3. Salinan mesin pada header rekomendasi
    -- ========================================================================
    PRINT '';
    PRINT '--- 3. Header rekomendasi ---';

    UPDATE h
    SET    h.Id_Mesin   = @Autoclave,
           h.Nama_Mesin = @Nama
    FROM   N_EMI_LAB_Verifikasi_Header h
    JOIN   @S x ON x.No_Sampel = h.No_Sampel
    WHERE  ISNULL(h.Id_Mesin, -1) <> @Autoclave
       OR  ISNULL(h.Nama_Mesin, '') <> @Nama;

    PRINT '  [~] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' baris header.';

COMMIT TRANSACTION;

    PRINT '';
    PRINT 'SELESAI - seluruh perubahan tersimpan.';

END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    PRINT '';
    PRINT 'GAGAL - seluruh perubahan dibatalkan.';
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
PRINT '--- Mesin sampel dalam antrean (harus AUTOCLAVE, kecuali data nyata FS0926-0002) ---';

SELECT m.Nama_Mesin,
       COUNT(DISTINCT p.No_Sampel) AS Jumlah_Sampel,
       MIN(p.No_Sampel)            AS Dari,
       MAX(p.No_Sampel)            AS Sampai
FROM N_EMI_LAB_PO_Sampel p
JOIN EMI_Master_Mesin m ON m.Id_Master_Mesin = p.Id_Mesin
WHERE EXISTS (SELECT 1 FROM N_EMI_LAB_Uji_Sampel u
              WHERE u.No_Po_Sampel = p.No_Sampel
                AND u.Flag_Selesai = 'Y' AND u.Status IS NULL)
GROUP BY m.Nama_Mesin
ORDER BY m.Nama_Mesin;

PRINT '';
PRINT '--- Contoh: batas pada baris uji (analisa tanpa batas = sengaja tanpa standar) ---';

SELECT u.No_Po_Sampel,
       SUM(CASE WHEN u.Range_Awal IS NOT NULL OR u.Range_Akhir IS NOT NULL
                THEN 1 ELSE 0 END)                          AS Dengan_Batas,
       SUM(CASE WHEN u.Range_Awal IS NULL AND u.Range_Akhir IS NULL
                THEN 1 ELSE 0 END)                          AS Tanpa_Batas,
       MAX(u.Id_Mesin)                                      AS Id_Mesin
FROM N_EMI_LAB_Uji_Sampel u
WHERE u.No_Po_Sampel BETWEEN 'FS0926-0003' AND 'FS0926-0018'
GROUP BY u.No_Po_Sampel
ORDER BY u.No_Po_Sampel;


/* ============================================================================
   ROLLBACK - buka komentar untuk mengembalikan contoh ke mesin semula.
   Dicatat dari keadaan demo 25-09-2026: seluruh baris uji contoh
   0003..0017 sebelumnya TANPA batas; FS0926-0018 sejak awal menyimpan
   batasnya sendiri sehingga batasnya tidak ikut dikosongkan.
   ============================================================================

UPDATE N_EMI_LAB_Uji_Sampel
SET    Range_Awal = NULL, Range_Akhir = NULL
WHERE  No_Po_Sampel BETWEEN 'FS0926-0003' AND 'FS0926-0017'
  AND  Flag_Perhitungan = 'Y';

UPDATE N_EMI_LAB_PO_Sampel  SET Id_Mesin = 1 WHERE No_Sampel    BETWEEN 'FS0926-0003' AND 'FS0926-0017';
UPDATE N_EMI_LAB_Uji_Sampel SET Id_Mesin = 1 WHERE No_Po_Sampel BETWEEN 'FS0926-0003' AND 'FS0926-0017';
UPDATE N_EMI_LAB_PO_Sampel  SET Id_Mesin = 2 WHERE No_Sampel    = 'FS0926-0018';
UPDATE N_EMI_LAB_Uji_Sampel SET Id_Mesin = 2 WHERE No_Po_Sampel = 'FS0926-0018';

UPDATE N_EMI_LAB_Verifikasi_Header
SET    Id_Mesin = 1, Nama_Mesin = 'GRINDER'
WHERE  No_Sampel BETWEEN 'FS0926-0003' AND 'FS0926-0017';

   ==========================================================================*/
