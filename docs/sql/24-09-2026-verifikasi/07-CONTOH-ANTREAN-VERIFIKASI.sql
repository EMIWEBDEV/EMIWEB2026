-- ============================================================================
-- 07-CONTOH-ANTREAN-VERIFIKASI.sql
-- Tanggal : 24-09-2026
-- Modul   : VERIFIKASI HASIL ANALISA - data contoh antrean & keputusan
--
-- TUJUAN:
--   Mengisi layar verifikasi dengan sebaran keadaan yang wajar dijumpai
--   sehari-hari, sehingga setiap tab pada layar punya isi:
--
--     MENUNGGU  : 3 sampel  (FS0926-0003 .. FS0926-0005)
--                   0003 -> spesifikasi LENGKAP, seluruh hasil LAYAK
--                   0004 -> pernah RESAMPLING, hasil akhir LAYAK
--                   0005 -> pernah RESAMPLING, hasil akhir TIDAK LAYAK
--     DISETUJUI : 4 sampel  (FS0926-0006 .. FS0926-0009)
--                   0009 memakai keputusan "Disetujui Bersyarat"
--     DITOLAK   : 8 sampel  (FS0926-0010 .. FS0926-0017)
--
-- SIFAT:
--   * IDEMPOTEN - seluruh INSERT dijaga NOT EXISTS; aman dijalankan ulang.
--   * HANYA MENAMBAH sampel baru bernomor FS0926-0003 ke atas.
--     FS0926-0001 dan FS0926-0002 (data nyata) TIDAK DISENTUH sama sekali.
--   * Tidak mengubah satu pun baris milik modul lama.
--   * Dapat dicabut seluruhnya lewat bagian ROLLBACK di bawah.
--
-- PRASYARAT: 01..06 sudah dijalankan.
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @Barang VARCHAR(50) = 'BRG08240003';
DECLARE @Mesin  INT         = 1;      -- mesin 1 sudah punya standar rentang
DECLARE @Po     VARCHAR(50) = 'PRD0626-00001';
DECLARE @Split  VARCHAR(50) = 'PRD0626-00001-1';
DECLARE @Now    DATETIME    = CAST(dbo.Get_Date_Time() AS DATETIME);
DECLARE @Hari   DATE        = CAST(@Now AS DATE);

PRINT '============================================================';
PRINT ' DATA CONTOH: ANTREAN & KEPUTUSAN VERIFIKASI';
PRINT ' Database : ' + DB_NAME();
PRINT '============================================================';

BEGIN TRY
BEGIN TRANSACTION;

    -- ========================================================================
    -- 0. Rencana sampel
    -- ========================================================================
    -- Nilai hasil dipilih relatif terhadap standar rentang mesin 1 yang
    -- dibuat pada skrip 05:
    --     PROTEIN (15) 3,0-6,0 | ASH (16) 0-20 | SALT (17) 1,5-4,0
    --     MIKRO-YM (44) 0-100
    -- MIKRO-AC (43) & MIKRO-EC (42) sengaja tanpa standar, agar keadaan
    -- "tidak ada di master" tetap terwakili.
    DECLARE @S TABLE (
        No_Sampel   VARCHAR(30) PRIMARY KEY,
        Status      VARCHAR(30),   -- MENUNGGU / DISETUJUI / DISETUJUI_BERSYARAT / DITOLAK
        Keputusan   VARCHAR(30),   -- NULL untuk yang masih menunggu
        Layak       CHAR(1),       -- Y = semua hasil dalam rentang
        Resampling  CHAR(1),       -- Y = pernah diuji ulang
        Catatan     VARCHAR(400),
        Urut        INT
    );

    INSERT INTO @S (No_Sampel, Status, Keputusan, Layak, Resampling, Catatan, Urut) VALUES
    -- ---- MENUNGGU (3) -------------------------------------------------------
    ('FS0926-0003','MENUNGGU',NULL,'Y','T',NULL,3),
    ('FS0926-0004','MENUNGGU',NULL,'Y','Y',NULL,4),
    ('FS0926-0005','MENUNGGU',NULL,'T','Y',NULL,5),
    -- ---- DISETUJUI (4) ------------------------------------------------------
    ('FS0926-0006','DISETUJUI','SETUJU_PENUH','Y','T',
     'Seluruh parameter berada di dalam batas spesifikasi. Hasil diterima.',6),
    ('FS0926-0007','DISETUJUI','SETUJU_PENUH','Y','T',
     'Hasil uji konsisten dengan batch sebelumnya. Tidak ada penyimpangan.',7),
    ('FS0926-0008','DISETUJUI','SETUJU_PENUH','Y','T',
     'Verifikasi selesai, seluruh parameter memenuhi spesifikasi produk.',8),
    ('FS0926-0009','DISETUJUI_BERSYARAT','SETUJU_BERSYARAT','Y','Y',
     'Kadar abu sempat di luar batas pada uji pertama, hasil uji ulang kembali masuk spesifikasi. Dirilis bersyarat dengan pemantauan batch berikutnya.',9),
    -- ---- DITOLAK (8) --------------------------------------------------------
    ('FS0926-0010','DITOLAK','TOLAK','T','T',
     'Kadar abu melampaui batas maksimum spesifikasi. Hasil tidak dapat diterima.',10),
    ('FS0926-0011','DITOLAK','TOLAK','T','T',
     'Kadar protein di bawah batas minimum. Sampel dikembalikan ke produksi.',11),
    ('FS0926-0012','DITOLAK','TOLAK','T','Y',
     'Hasil uji ulang tetap berada di luar spesifikasi. Batch ditahan.',12),
    ('FS0926-0013','DITOLAK','TOLAK','T','T',
     'Kadar garam melampaui batas maksimum. Tidak memenuhi spesifikasi produk.',13),
    ('FS0926-0014','DITOLAK','TOLAK','T','T',
     'Beberapa parameter di luar rentang. Perlu penyesuaian proses produksi.',14),
    ('FS0926-0015','DITOLAK','TOLAK','T','Y',
     'Penyimpangan berulang pada dua kali pengujian. Batch tidak dirilis.',15),
    ('FS0926-0016','DITOLAK','TOLAK','T','T',
     'Kadar abu dan protein sama-sama di luar batas. Hasil ditolak.',16),
    ('FS0926-0017','DITOLAK','TOLAK','T','T',
     'Hasil tidak memenuhi spesifikasi. Diteruskan ke tindak lanjut QA.',17);


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
           CONVERT(VARCHAR(8), DATEADD(MINUTE, s.Urut * 7, @Now), 108),
           @Split, '1', @Mesin, 'Data contoh verifikasi', 'FRANS', 6, 'Y'
    FROM @S s
    WHERE NOT EXISTS (SELECT 1 FROM N_EMI_LAB_PO_Sampel p
                      WHERE p.No_Sampel = s.No_Sampel);

    PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' baris PO sampel.';


    -- ========================================================================
    -- 2. Hasil uji (6 analisa per sampel, sama pola dengan data nyata)
    -- ========================================================================
    -- Nilai dibentuk dari kolom Layak: sampel layak memakai nilai di tengah
    -- rentang, sampel tidak layak memakai nilai di luar batas atas/bawah.
    PRINT '';
    PRINT '--- 2. Hasil uji ---';

    DECLARE @A TABLE (Id_Jenis_Analisa INT, Id_Perhitungan INT,
                      Nilai_Layak FLOAT, Nilai_Tidak FLOAT, Urut INT);
    INSERT INTO @A VALUES
        (15, 36,   4.20,   2.10, 1),   -- PROTEIN : tidak layak = di bawah min 3,0
        (16, 37,  12.50,  41.80, 2),   -- ASH     : tidak layak = di atas max 20
        (17, 38,   2.40,   5.60, 3),   -- SALT    : tidak layak = di atas max 4,0
        (42, 67,   0.00,   0.00, 4),   -- MIKRO-EC: tanpa standar di master
        (43, 66,   5.00,   5.00, 5),   -- MIKRO-AC: tanpa standar di master
        -- Catatan: kedua analisa mikro di atas tidak punya standar rentang,
        -- sehingga kelayakannya memang tidak dapat dinilai. Flag_Layak-nya
        -- dipaksa 'Y' pada langkah 2b agar tidak terbaca sebagai penyimpangan.
        (44, 68,  25.00, 180.00, 6);   -- MIKRO-YM: tidak layak = di atas max 100

    INSERT INTO N_EMI_LAB_Uji_Sampel
        (Kode_Perusahaan, No_Faktur, No_Po_Sampel, No_Fak_Sub_Po,
         Id_Jenis_Analisa, Hasil, Flag_Perhitungan, Status, Tanggal, Jam,
         Id_User, Flag_Selesai, Id_Perhitungan, Tahapan_Ke, Flag_Resampling,
         Status_Keputusan_Sampel, Flag_Layak, Id_Mesin)
    SELECT '001',
           'FUS0926-9' + RIGHT('00' + CAST(s.Urut AS VARCHAR(3)), 2)
                       + CAST(a.Urut AS VARCHAR(1)),
           s.No_Sampel,
           NULL,                                   -- bukan multi QR code
           a.Id_Jenis_Analisa,
           CASE WHEN s.Layak = 'Y' THEN a.Nilai_Layak ELSE a.Nilai_Tidak END,
           'Y', NULL, @Hari,
           CONVERT(VARCHAR(8), DATEADD(MINUTE, s.Urut * 7 + a.Urut, @Now), 108),
           'SV_LAB', 'Y', a.Id_Perhitungan,
           CASE WHEN s.Resampling = 'Y' THEN 2 ELSE 1 END,
           CASE WHEN s.Resampling = 'Y' THEN 'Y' ELSE NULL END,
           'terima',
           CASE WHEN s.Layak = 'Y' THEN 'Y' ELSE 'T' END,
           @Mesin
    FROM @S s
    CROSS JOIN @A a
    WHERE NOT EXISTS (
        SELECT 1 FROM N_EMI_LAB_Uji_Sampel u
        WHERE u.No_Po_Sampel = s.No_Sampel
          AND u.Id_Jenis_Analisa = a.Id_Jenis_Analisa
    );

    PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' baris hasil uji.';


    -- ------------------------------------------------------------------------
    -- 2b. Analisa tanpa standar rentang tidak boleh ditandai tidak layak
    -- ------------------------------------------------------------------------
    -- MIKRO-EC (42) dan MIKRO-AC (43) sengaja tidak punya baris di
    -- N_EMI_LAB_Standar_Rentang. Tanpa batas pembanding, kelayakannya tidak
    -- dapat dinilai, sehingga menandainya 'T' akan terbaca keliru sebagai
    -- penyimpangan pada layar verifikasi.
    UPDATE N_EMI_LAB_Uji_Sampel
    SET Flag_Layak = 'Y'
    WHERE No_Po_Sampel IN (SELECT No_Sampel FROM @S)
      AND Id_Jenis_Analisa IN (42, 43)
      AND Flag_Layak = 'T';

    PRINT '  [~] ' + CAST(@@ROWCOUNT AS VARCHAR(10))
        + ' baris mikro tanpa standar dinormalkan.';


    -- ========================================================================
    -- 3. Log resampling
    -- ========================================================================
    -- Hanya untuk sampel yang ditandai pernah diuji ulang. Pola penomoran
    -- mengikuti sistem: <sampel>-1 diulang menjadi <sampel>-2.
    PRINT '';
    PRINT '--- 3. Log resampling ---';

    INSERT INTO N_EMI_LAB_Uji_Sampel_Resampling_Log
        (No_Po_Sampel, Tahapan_Ke, No_Sampel_Resampling_Origin, No_Sampel_Resampling,
         Keterangan, Tanggal, Jam, Id_User, Id_Jenis_Analisa, Flag_Selesai_Resampling)
    SELECT s.No_Sampel, 2, s.No_Sampel + '-1', s.No_Sampel + '-2',
           CASE WHEN s.Layak = 'Y'
                THEN 'Hasil pertama di luar spesifikasi, uji ulang kembali masuk rentang'
                ELSE 'Hasil pertama di luar spesifikasi, uji ulang tetap di luar rentang'
           END,
           @Hari, CONVERT(VARCHAR(8), DATEADD(MINUTE, s.Urut * 7 + 30, @Now), 108),
           'SV_LAB', 16, 'Y'
    FROM @S s
    WHERE s.Resampling = 'Y'
      AND NOT EXISTS (
          SELECT 1 FROM N_EMI_LAB_Uji_Sampel_Resampling_Log g
          WHERE g.No_Po_Sampel = s.No_Sampel AND g.Id_Jenis_Analisa = 16
      );

    PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' baris log resampling.';


    -- ========================================================================
    -- 4. Header keputusan verifikasi (hanya yang sudah diputuskan)
    -- ========================================================================
    PRINT '';
    PRINT '--- 4. Header keputusan ---';

    INSERT INTO N_EMI_LAB_Verifikasi_Header
        (No_Sampel, No_Sub_Sampel, No_Po, No_Split_Po, No_Batch, Kode_Barang,
         Nama_Barang, Flag_Trial_Produksi, Kode_Aktivitas_Lab, Nama_Aktivitas,
         Kode_Status, Kode_Keputusan, Catatan, Id_User, Nama_User,
         Tanggal_Keputusan, Jam_Keputusan, Jumlah_Analisa, Jumlah_Tidak_Layak,
         Revisi_Ke, Dibuat_Pada)
    SELECT s.No_Sampel, NULL, @Po, @Split, 1, @Barang,
           (SELECT TOP 1 b.Nama FROM N_EMI_View_Barang b WHERE b.Kode_Barang = @Barang),
           'Y', 'ANL',
           (SELECT TOP 1 kl.Nama_Aktivitas FROM N_EMI_LIMS_Klasifikasi_Aktivitas_Lab kl
            WHERE kl.Kode_Aktivitas_Lab = 'ANL'),
           s.Status, s.Keputusan, s.Catatan, 'ratna',
           (SELECT TOP 1 us.Nama FROM N_EMI_LAB_Users us WHERE us.UserId = 'ratna'),
           @Hari, CONVERT(VARCHAR(8), DATEADD(MINUTE, s.Urut * 7 + 45, @Now), 108),
           6,
           CASE WHEN s.Layak = 'Y' THEN 0 ELSE 4 END,
           0, @Now
    FROM @S s
    WHERE s.Keputusan IS NOT NULL
      AND NOT EXISTS (
          SELECT 1 FROM N_EMI_LAB_Verifikasi_Header h
          WHERE h.No_Sampel = s.No_Sampel AND h.Kode_Aktivitas_Lab = 'ANL'
      );

    PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' baris header keputusan.';


    -- ========================================================================
    -- 5. Detail keputusan
    -- ========================================================================
    PRINT '';
    PRINT '--- 5. Detail keputusan ---';

    INSERT INTO N_EMI_LAB_Verifikasi_Detail
        (Id_Verifikasi, Id_Jenis_Analisa, Nama_Jenis_Analisa, Kode_Analisa,
         No_Sub_Sampel, Tahapan_Ke, Hasil, Range_Awal, Range_Akhir,
         Flag_Layak, Flag_Perhitungan, Id_User_Validasi, Nama_User_Validasi,
         Tanggal_Validasi, Jam_Validasi, Kode_Status, Dibuat_Pada)
    SELECT h.Id_Verifikasi, u.Id_Jenis_Analisa, ja.Jenis_Analisa, ja.Kode_Analisa,
           u.No_Fak_Sub_Po, u.Tahapan_Ke, u.Hasil, r.Range_Awal, r.Range_Akhir,
           u.Flag_Layak, u.Flag_Perhitungan, u.Id_User,
           (SELECT TOP 1 us.Nama FROM N_EMI_LAB_Users us WHERE us.UserId = u.Id_User),
           CAST(u.Tanggal AS DATE), u.Jam, h.Kode_Status, @Now
    FROM N_EMI_LAB_Verifikasi_Header h
    JOIN @S s                       ON s.No_Sampel = h.No_Sampel
    JOIN N_EMI_LAB_Uji_Sampel u     ON u.No_Po_Sampel = h.No_Sampel
    JOIN N_EMI_LAB_Jenis_Analisa ja ON ja.id = u.Id_Jenis_Analisa
    LEFT JOIN N_EMI_LAB_Standar_Rentang r
           ON r.Id_Jenis_Analisa = u.Id_Jenis_Analisa
          AND r.Kode_Barang      = @Barang
          AND r.Id_Master_Mesin  = @Mesin
    WHERE h.Kode_Aktivitas_Lab  = 'ANL'
      AND ja.Kode_Aktivitas_Lab = 'ANL'
      AND NOT EXISTS (
          SELECT 1 FROM N_EMI_LAB_Verifikasi_Detail d
          WHERE d.Id_Verifikasi = h.Id_Verifikasi
            AND d.Id_Jenis_Analisa = u.Id_Jenis_Analisa
      );

    PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' baris detail keputusan.';


    -- ------------------------------------------------------------------------
    -- 5b. Samakan Jumlah_Tidak_Layak header dengan detail sebenarnya
    -- ------------------------------------------------------------------------
    -- Dihitung ulang dari detail, bukan ditebak di langkah 4, supaya angka
    -- pada header tetap benar setelah normalisasi analisa tanpa standar.
    UPDATE h
    SET h.Jumlah_Tidak_Layak = x.n
    FROM N_EMI_LAB_Verifikasi_Header h
    JOIN @S s ON s.No_Sampel = h.No_Sampel
    CROSS APPLY (SELECT COUNT(*) AS n
                 FROM N_EMI_LAB_Verifikasi_Detail d
                 WHERE d.Id_Verifikasi = h.Id_Verifikasi
                   AND d.Flag_Layak = 'T') x
    WHERE h.Kode_Aktivitas_Lab = 'ANL'
      AND h.Jumlah_Tidak_Layak <> x.n;

    PRINT '  [~] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' header disinkronkan.';


    -- ========================================================================
    -- 6. Riwayat (jejak audit)
    -- ========================================================================
    PRINT '';
    PRINT '--- 6. Riwayat keputusan ---';

    INSERT INTO N_EMI_LAB_Verifikasi_Riwayat
        (Id_Verifikasi, No_Sampel, Kode_Aktivitas_Lab, Status_Sebelum,
         Status_Sesudah, Aksi, Kode_Keputusan, Catatan, Id_User, Nama_User,
         Tanggal, Jam, Dibuat_Pada, Sumber_Aksi, Jumlah_Analisa)
    SELECT h.Id_Verifikasi, h.No_Sampel, 'ANL', 'MENUNGGU',
           h.Kode_Status, h.Kode_Keputusan, h.Kode_Keputusan, h.Catatan,
           h.Id_User, h.Nama_User, h.Tanggal_Keputusan, h.Jam_Keputusan,
           @Now, 'SATUAN', h.Jumlah_Analisa
    FROM N_EMI_LAB_Verifikasi_Header h
    JOIN @S s ON s.No_Sampel = h.No_Sampel
    WHERE h.Kode_Aktivitas_Lab = 'ANL'
      AND NOT EXISTS (
          SELECT 1 FROM N_EMI_LAB_Verifikasi_Riwayat w
          WHERE w.Id_Verifikasi = h.Id_Verifikasi
      );

    PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' baris riwayat.';

COMMIT TRANSACTION;
PRINT '';
PRINT '============================================================';
PRINT ' DATA CONTOH ANTREAN SELESAI.';
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
PRINT '--- Sebaran status pada layar verifikasi ---';

SELECT ISNULL(h.Kode_Status, 'MENUNGGU') AS Status,
       COUNT(DISTINCT p.No_Sampel)       AS Jumlah
FROM N_EMI_LAB_PO_Sampel p
JOIN N_EMI_LAB_Uji_Sampel u     ON u.No_Po_Sampel = p.No_Sampel
JOIN N_EMI_LAB_Jenis_Analisa ja ON ja.id = u.Id_Jenis_Analisa
                               AND ja.Kode_Aktivitas_Lab = 'ANL'
LEFT JOIN N_EMI_LAB_Verifikasi_Header h
       ON h.No_Sampel = p.No_Sampel AND h.Kode_Aktivitas_Lab = 'ANL'
WHERE u.Flag_Selesai = 'Y' AND u.Status IS NULL
GROUP BY ISNULL(h.Kode_Status, 'MENUNGGU')
ORDER BY Status;

PRINT '';
PRINT '--- Rincian antrean MENUNGGU ---';

SELECT p.No_Sampel,
       SUM(CASE WHEN u.Flag_Layak = 'T' THEN 1 ELSE 0 END) AS Tidak_Layak,
       ISNULL(CAST(g.Jumlah AS VARCHAR(5)) + 'x diulang', 'Tidak ada') AS Resampling
FROM N_EMI_LAB_PO_Sampel p
JOIN N_EMI_LAB_Uji_Sampel u     ON u.No_Po_Sampel = p.No_Sampel
JOIN N_EMI_LAB_Jenis_Analisa ja ON ja.id = u.Id_Jenis_Analisa
                               AND ja.Kode_Aktivitas_Lab = 'ANL'
LEFT JOIN N_EMI_LAB_Verifikasi_Header h
       ON h.No_Sampel = p.No_Sampel AND h.Kode_Aktivitas_Lab = 'ANL'
OUTER APPLY (SELECT COUNT(*) AS Jumlah
             FROM N_EMI_LAB_Uji_Sampel_Resampling_Log l
             WHERE l.No_Po_Sampel = p.No_Sampel) g
WHERE u.Flag_Selesai = 'Y' AND u.Status IS NULL AND h.Id_Verifikasi IS NULL
GROUP BY p.No_Sampel, g.Jumlah
ORDER BY p.No_Sampel;


/* ============================================================================
   ROLLBACK - buka komentar bila data contoh ingin dicabut kembali.
   FS0926-0001 dan FS0926-0002 tidak ikut terhapus.
   ============================================================================

DELETE w FROM N_EMI_LAB_Verifikasi_Riwayat w
JOIN N_EMI_LAB_Verifikasi_Header h ON h.Id_Verifikasi = w.Id_Verifikasi
WHERE h.No_Sampel BETWEEN 'FS0926-0003' AND 'FS0926-0017';

DELETE d FROM N_EMI_LAB_Verifikasi_Detail d
JOIN N_EMI_LAB_Verifikasi_Header h ON h.Id_Verifikasi = d.Id_Verifikasi
WHERE h.No_Sampel BETWEEN 'FS0926-0003' AND 'FS0926-0017';

DELETE FROM N_EMI_LAB_Verifikasi_Header
WHERE No_Sampel BETWEEN 'FS0926-0003' AND 'FS0926-0017';

DELETE FROM N_EMI_LAB_Uji_Sampel_Resampling_Log
WHERE No_Po_Sampel BETWEEN 'FS0926-0003' AND 'FS0926-0017';

DELETE FROM N_EMI_LAB_Uji_Sampel
WHERE No_Po_Sampel BETWEEN 'FS0926-0003' AND 'FS0926-0017';

DELETE FROM N_EMI_LAB_PO_Sampel
WHERE No_Sampel BETWEEN 'FS0926-0003' AND 'FS0926-0017';

   ==========================================================================*/
