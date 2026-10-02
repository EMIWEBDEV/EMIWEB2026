-- ============================================================================
-- 11-CONTOH-LOOKVIEW-PALATABILITAS.sql
-- Tanggal : 25-09-2026
-- Modul   : VERIFIKASI HASIL ANALISA - contoh Look View & Palatabilitas
--
-- LATAR:
--   Antrean verifikasi selama ini hanya kaya pada klasifikasi Analisa Lab
--   (18 sampel), sedangkan Look View dan Palatabilitas masing-masing hanya
--   punya 2 sampel. Verifikator Look View (jati) dan Palatabilitas (ROBY)
--   karena itu tidak dapat mencoba alur kerjanya dengan layak.
--
-- TUJUAN:
--   Menambah contoh untuk kedua klasifikasi tersebut, dengan sebaran keadaan
--   yang sama lengkapnya dengan Analisa Lab:
--
--     LOOK VIEW (LCKV) - FS0926-0019 .. FS0926-0021
--       0019 -> seluruh parameter LAYAK
--       0020 -> ada parameter TIDAK LAYAK (aroma & tekstur menyimpang)
--       0021 -> ada parameter TANPA KRITERIA di master (ORGANOLEPTIK)
--
--     PALATABILITAS (PLT) - FS0926-0022 .. FS0926-0024
--       0022 -> seluruh parameter LAYAK
--       0023 -> ada parameter TIDAK LAYAK (responden menolak)
--       0024 -> ada parameter TANPA KRITERIA di master (konsumsi gram)
--
--   Seluruhnya memakai mesin AUTOCLAVE (Id_Mesin = 4), sesuai permintaan
--   agar contoh terpusat pada satu mesin sehingga mudah ditelusuri.
--
-- SIFAT:
--   * IDEMPOTEN - seluruh INSERT dijaga NOT EXISTS; aman dijalankan ulang.
--   * HANYA MENAMBAH sampel bernomor FS0926-0019 ke atas.
--     Sampel FS0926-0001 s/d FS0926-0018 TIDAK DISENTUH.
--   * Tidak mengubah satu pun baris milik modul lama.
--   * Dapat dicabut seluruhnya lewat bagian ROLLBACK di bawah.
--
-- PRASYARAT: 01..10 sudah dijalankan.
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @Barang VARCHAR(50) = 'BRG08240003';
DECLARE @Mesin  INT         = 4;      -- AUTOCLAVE
DECLARE @Po     VARCHAR(50) = 'PRD0626-00001';
DECLARE @Split  VARCHAR(50) = 'PRD0626-00001-1';
DECLARE @Now    DATETIME    = CAST(dbo.Get_Date_Time() AS DATETIME);
DECLARE @Hari   DATE        = CAST(@Now AS DATE);

PRINT '============================================================';
PRINT ' DATA CONTOH: LOOK VIEW & PALATABILITAS';
PRINT ' Mesin    : AUTOCLAVE (Id_Mesin = 4)';
PRINT ' Database : ' + DB_NAME();
PRINT '============================================================';

BEGIN TRY
BEGIN TRANSACTION;

    -- ========================================================================
    -- 0. Rencana sampel
    -- ========================================================================
    DECLARE @S TABLE (
        No_Sampel VARCHAR(30) PRIMARY KEY,
        Akt       VARCHAR(10),   -- LCKV / PLT
        Pola      VARCHAR(20),   -- LAYAK / TIDAK_LAYAK / TANPA_MASTER
        Urut      INT
    );

    INSERT INTO @S (No_Sampel, Akt, Pola, Urut) VALUES
        ('FS0926-0019', 'LCKV', 'LAYAK',        19),
        ('FS0926-0020', 'LCKV', 'TIDAK_LAYAK',  20),
        ('FS0926-0021', 'LCKV', 'TANPA_MASTER', 21),
        ('FS0926-0022', 'PLT',  'LAYAK',        22),
        ('FS0926-0023', 'PLT',  'TIDAK_LAYAK',  23),
        ('FS0926-0024', 'PLT',  'TANPA_MASTER', 24);


    -- ========================================================================
    -- 1. PO Sampel
    -- ========================================================================
    PRINT '';
    PRINT '--- 1. PO Sampel ---';

    INSERT INTO N_EMI_LAB_PO_Sampel
        (Kode_Perusahaan, No_Po, Kode_Barang, No_Sampel, Tanggal, Jam,
         No_Split_Po, No_Batch, Id_Mesin, Keterangan, Id_User,
         Jumlah_Pcs, Flag_Trial_Produksi)
    SELECT '001', @Po, @Barang, s.No_Sampel, @Hari,
           CONVERT(VARCHAR(8), DATEADD(MINUTE, s.Urut * 6, @Now), 108),
           @Split, '1', @Mesin,
           CASE s.Akt WHEN 'LCKV' THEN 'Contoh look view'
                      ELSE 'Contoh palatabilitas' END,
           'FRANS', 6, 'Y'
    FROM @S s
    WHERE NOT EXISTS (SELECT 1 FROM N_EMI_LAB_PO_Sampel p
                      WHERE p.No_Sampel = s.No_Sampel);

    PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' baris PO sampel.';


    -- ========================================================================
    -- 2. LOOK VIEW - hasil uji
    -- ========================================================================
    -- Nilai hasil adalah Nilai_Kriteria dari master, sama seperti yang
    -- ditulis modul uji sampel. Teks bacanya disimpan di Nilai_Hasil_String.
    --
    --   72 = TEKSTUR POUCH (QA)   73 = WARNA (QA)   74 = AROMA (QA)
    --   85 = ORGANOLEPTIK         -> sengaja tanpa kriteria di master
    PRINT '';
    PRINT '--- 2. Look View: hasil uji ---';

    DECLARE @LV TABLE (
        Pola  VARCHAR(20),
        Ja    INT,
        Nilai FLOAT,
        Teks  VARCHAR(100),
        Layak CHAR(1),
        Urut  INT
    );

    INSERT INTO @LV (Pola, Ja, Nilai, Teks, Layak, Urut) VALUES
        -- Seluruhnya di dalam kriteria layak
        ('LAYAK',        72, -965064442.0, 'Lembut',                 'Y', 1),
        ('LAYAK',        73, -672860195.0, 'Merah',                  'Y', 2),
        ('LAYAK',        74, -631590435.0, 'Harum Khas Daging',      'Y', 3),
        -- Dua parameter menyimpang
        ('TIDAK_LAYAK',  72, -547374595.0, 'Lengket di Pouch',       'T', 1),
        ('TIDAK_LAYAK',  73, -762900483.0, 'Merah Kecoklatan',       'Y', 2),
        ('TIDAK_LAYAK',  74, -831565647.0, 'Tidak Berbau',           'T', 3),
        -- Ada parameter yang kriterianya belum diatur
        ('TANPA_MASTER', 72, -965064442.0, 'Lembut',                 'Y', 1),
        ('TANPA_MASTER', 73, -427413204.0, 'Merah Muda',             'Y', 2),
        ('TANPA_MASTER', 85, -123456789.0, 'Normal',                 'Y', 3);

    INSERT INTO N_EMI_LAB_Uji_Sampel
        (Kode_Perusahaan, No_Faktur, No_Po_Sampel, No_Fak_Sub_Po,
         Id_Jenis_Analisa, Hasil, Flag_Perhitungan, Status, Tanggal, Jam,
         Id_User, Flag_Selesai, Tahapan_Ke, Status_Keputusan_Sampel,
         Flag_Layak, Id_Mesin, Flag_String, Nilai_Hasil_String, Flag_Foto)
    SELECT '001',
           'FUS0926-7' + RIGHT('00' + CAST(s.Urut AS VARCHAR(3)), 2)
                       + CAST(v.Urut AS VARCHAR(1)),
           s.No_Sampel, NULL, v.Ja, v.Nilai, NULL, NULL, @Hari,
           CONVERT(VARCHAR(8), DATEADD(MINUTE, s.Urut * 6 + v.Urut, @Now), 108),
           'GUDANG PEKAN', 'Y', 1, 'terima', v.Layak, @Mesin,
           'Y', v.Teks, 'T'
    FROM @S s
    JOIN @LV v ON v.Pola = s.Pola
    WHERE s.Akt = 'LCKV'
      AND NOT EXISTS (
          SELECT 1 FROM N_EMI_LAB_Uji_Sampel u
          WHERE u.No_Po_Sampel = s.No_Sampel
            AND u.Id_Jenis_Analisa = v.Ja
      );

    PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' baris look view.';


    -- ========================================================================
    -- 3. PALATABILITAS - hasil uji
    -- ========================================================================
    --   62 = Responden Memakan     (punya kriteria di master)
    --   66 = Uji Palatabilitas     (punya kriteria di master)
    --   63 = Konsumsi (gram)       -> sengaja tanpa kriteria di master
    --
    -- Nilai kriteria yang dipakai:
    --   -9379744  = "Ya"    (layak)
    --   -9379745  = "Tidak" (tidak layak)
    PRINT '';
    PRINT '--- 3. Palatabilitas: hasil uji ---';

    DECLARE @PL TABLE (
        Pola  VARCHAR(20),
        Ja    INT,
        Nilai FLOAT,
        Teks  VARCHAR(100),
        Layak CHAR(1),
        Str   CHAR(1),
        Urut  INT
    );

    INSERT INTO @PL (Pola, Ja, Nilai, Teks, Layak, Str, Urut) VALUES
        -- Responden memakan, uji palatabilitas diterima
        ('LAYAK',        62, -9379744.0, 'Ya',    'Y', 'Y', 1),
        ('LAYAK',        66, -9379744.0, 'Ya',    'Y', 'Y', 2),
        -- Responden menolak sampel
        ('TIDAK_LAYAK',  62, -9379745.0, 'Tidak', 'T', 'Y', 1),
        ('TIDAK_LAYAK',  66, -9379745.0, 'Tidak', 'T', 'Y', 2),
        -- Ada parameter tanpa kriteria: konsumsi dalam gram
        ('TANPA_MASTER', 62, -9379744.0, 'Ya',    'Y', 'Y', 1),
        ('TANPA_MASTER', 63,      48.5,  NULL,    'Y', NULL, 2);

    INSERT INTO N_EMI_LAB_Uji_Sampel
        (Kode_Perusahaan, No_Faktur, No_Po_Sampel, No_Fak_Sub_Po,
         Id_Jenis_Analisa, Hasil, Flag_Perhitungan, Status, Tanggal, Jam,
         Id_User, Flag_Selesai, Tahapan_Ke, Status_Keputusan_Sampel,
         Flag_Layak, Id_Mesin, Flag_String, Nilai_Hasil_String, Flag_Foto,
         Id_Session, Id_Pembanding)
    SELECT '001',
           'FUS0926-6' + RIGHT('00' + CAST(s.Urut AS VARCHAR(3)), 2)
                       + CAST(v.Urut AS VARCHAR(1)),
           s.No_Sampel, NULL, v.Ja, v.Nilai, NULL, NULL, @Hari,
           CONVERT(VARCHAR(8), DATEADD(MINUTE, s.Urut * 6 + v.Urut, @Now), 108),
           'IIS', 'Y', 1, 'terima', v.Layak, @Mesin,
           v.Str, v.Teks, 'T',
           -- Sesi dan pembanding dibiarkan NULL: contoh ini tidak memakai
           -- perbandingan antar produk, sehingga tidak perlu dikarang.
           NULL, NULL
    FROM @S s
    JOIN @PL v ON v.Pola = s.Pola
    WHERE s.Akt = 'PLT'
      AND NOT EXISTS (
          SELECT 1 FROM N_EMI_LAB_Uji_Sampel u
          WHERE u.No_Po_Sampel = s.No_Sampel
            AND u.Id_Jenis_Analisa = v.Ja
      );

    PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' baris palatabilitas.';


    -- ========================================================================
    -- 4. Kewenangan verifikator
    -- ========================================================================
    -- jati baru berwenang atas 72, 73, 74. Sampel contoh memakai 85
    -- (ORGANOLEPTIK) untuk memperagakan keadaan tanpa kriteria, sehingga
    -- kewenangan itu perlu ditambahkan agar barisnya ikut tampil.
    PRINT '';
    PRINT '--- 4. Kewenangan ---';

    INSERT INTO N_EMI_LAB_Verifikasi_Kewenangan
        (Id_User, Kode_Aktivitas_Lab, Id_Jenis_Analisa,
         Flag_Approve, Flag_Reject, Flag_Aktif, Keterangan,
         Dibuat_Pada, Dibuat_Oleh)
    SELECT 'jati', 'LCKV', 85, 'Y', 'Y', 'Y',
           'Look view: ORGANOLEPTIK', @Now, 'SEED'
    WHERE EXISTS (SELECT 1 FROM N_EMI_LAB_Jenis_Analisa WHERE id = 85)
      AND NOT EXISTS (
          SELECT 1 FROM N_EMI_LAB_Verifikasi_Kewenangan
          WHERE Id_User = 'jati' AND Id_Jenis_Analisa = 85);

    PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' kewenangan look view.';

    -- ROBY sudah berwenang atas seluruh PLT (Id_Jenis_Analisa NULL), sehingga
    -- analisa palatabilitas baru otomatis termasuk dan tidak perlu didaftarkan
    -- satu per satu.

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
PRINT '--- Antrean per klasifikasi (mesin AUTOCLAVE) ---';

SELECT ja.Kode_Aktivitas_Lab AS Klasifikasi,
       COUNT(DISTINCT p.No_Sampel) AS Jumlah_Sampel
FROM N_EMI_LAB_PO_Sampel p
JOIN N_EMI_LAB_Uji_Sampel u     ON u.No_Po_Sampel = p.No_Sampel
JOIN N_EMI_LAB_Jenis_Analisa ja ON ja.id = u.Id_Jenis_Analisa
WHERE p.Id_Mesin = 4 AND u.Flag_Selesai = 'Y' AND u.Status IS NULL
GROUP BY ja.Kode_Aktivitas_Lab
ORDER BY Klasifikasi;

PRINT '';
PRINT '--- Rincian sampel contoh baru ---';

SELECT p.No_Sampel,
       ja.Kode_Aktivitas_Lab AS Akt,
       ja.Jenis_Analisa,
       ISNULL(u.Nilai_Hasil_String, CAST(u.Hasil AS VARCHAR(30))) AS Hasil,
       CASE WHEN k.Id_Jenis_Analisa IS NULL THEN 'tanpa kriteria di master'
            WHEN u.Flag_Layak = 'T' THEN 'TIDAK LAYAK'
            ELSE 'layak' END AS Penilaian
FROM N_EMI_LAB_PO_Sampel p
JOIN N_EMI_LAB_Uji_Sampel u     ON u.No_Po_Sampel = p.No_Sampel
JOIN N_EMI_LAB_Jenis_Analisa ja ON ja.id = u.Id_Jenis_Analisa
OUTER APPLY (
    SELECT TOP 1 x.Id_Jenis_Analisa
    FROM N_EMI_LAB_Standar_Rentang_Non_Perhitungan x
    WHERE x.Id_Jenis_Analisa = u.Id_Jenis_Analisa AND x.Flag_Aktif = 'Y'
) k
WHERE p.No_Sampel BETWEEN 'FS0926-0019' AND 'FS0926-0024'
ORDER BY p.No_Sampel, ja.Jenis_Analisa;


/* ============================================================================
   ROLLBACK - buka komentar bila data contoh ingin dicabut kembali.
   Sampel FS0926-0001 s/d FS0926-0018 tidak ikut terhapus.
   ============================================================================

DELETE FROM N_EMI_LAB_Verifikasi_Kewenangan
WHERE Id_User = 'jati' AND Id_Jenis_Analisa = 85;

DELETE FROM N_EMI_LAB_Uji_Sampel
WHERE No_Po_Sampel BETWEEN 'FS0926-0019' AND 'FS0926-0024';

DELETE FROM N_EMI_LAB_PO_Sampel
WHERE No_Sampel BETWEEN 'FS0926-0019' AND 'FS0926-0024';

   ==========================================================================*/
