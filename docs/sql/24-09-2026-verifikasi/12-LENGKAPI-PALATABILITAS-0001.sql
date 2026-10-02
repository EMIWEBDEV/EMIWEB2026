-- ============================================================================
-- 12-LENGKAPI-PALATABILITAS-0001.sql
-- Tanggal : 25-09-2026
-- Modul   : VERIFIKASI HASIL ANALISA - contoh palatabilitas lengkap
--
-- LATAR:
--   Sampel FS0926-0001 baru memiliki satu jenis analisa palatabilitas
--   (RESPONDEN MEMAKAN), sedangkan master N_EMI_LAB_Barang_Analisa untuk
--   produk ini menetapkan TIGA: DURASI, RESPONDEN MEMAKAN, dan TINGKAT
--   KONSUMSI. Akibatnya layar verifikasi hanya menampilkan satu baris dan
--   alur palatabilitas tidak dapat ditinjau secara utuh.
--
-- STRUKTUR DATA PALATABILITAS (hasil penelusuran):
--   Satu jenis analisa diuji atas BEBERAPA PARAMETER. Nama parameter berasal
--   dari N_EMI_LAB_Binding_Jenis_Analisa -> EMI_Quality_Control:
--
--     qc=111 PH7  Pilihan Sampel        qc=115 H7P  Sampel vs Kontrol
--     qc=112 PRC  Pilihan Kontrol       qc=116 WM   Waktu (detik)
--     qc=113 TM   Tdk Memilih           qc=117 SH   Habis (%)
--     qc=114 H7   Sampel (%)
--
--   Tiap parameter tersimpan sebagai SATU BARIS pada N_EMI_LAB_Uji_Sampel,
--   berurutan mengikuti daftar di atas, dan nilainya digandakan pada
--   N_EMI_LAB_Uji_Sampel_Detail per Id_Quality_Control.
--
-- PERUBAHAN:
--   Menambah dua jenis analisa PLT yang belum ada pada FS0926-0001, masing-
--   masing 7 baris parameter, terhubung ke sesi dan pembanding yang sudah
--   ada (Id_Session 22, PEMBANDING 1), dengan status sudah divalidasi.
--
-- SIFAT:
--   * IDEMPOTEN - dijaga NOT EXISTS; aman dijalankan berulang.
--   * HANYA MENAMBAH baris untuk FS0926-0001 pada analisa 52 dan 87.
--     Baris RESPONDEN MEMAKAN yang sudah ada TIDAK DISENTUH.
--   * Tidak mengubah satu pun fitur lama.
--   * Dapat dicabut lewat bagian ROLLBACK di bawah.
--
-- PRASYARAT: 01..11 sudah dijalankan.
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @Sampel  VARCHAR(30) = 'FS0926-0001';
DECLARE @Mesin   INT         = 4;      -- AUTOCLAVE
DECLARE @Session INT;
DECLARE @Pemb    INT;
DECLARE @Sub     VARCHAR(50);
DECLARE @Now     DATETIME    = CAST(dbo.Get_Date_Time() AS DATETIME);
DECLARE @Hari    DATE        = CAST(@Now AS DATE);
DECLARE @User    VARCHAR(30) = 'IIS';

PRINT '============================================================';
PRINT ' LENGKAPI PALATABILITAS FS0926-0001';
PRINT ' Database : ' + DB_NAME();
PRINT '============================================================';

BEGIN TRY
BEGIN TRANSACTION;

    -- ========================================================================
    -- 0. Ambil konteks sesi & pembanding yang sudah ada
    -- ========================================================================
    SELECT TOP 1 @Session = u.Id_Session, @Pemb = u.Id_Pembanding, @Sub = u.No_Fak_Sub_Po
    FROM N_EMI_LAB_Uji_Sampel u
    JOIN N_EMI_LAB_Jenis_Analisa ja ON ja.id = u.Id_Jenis_Analisa
    WHERE u.No_Po_Sampel = @Sampel AND ja.Kode_Aktivitas_Lab = 'PLT'
      AND u.Id_Session IS NOT NULL;

    IF @Session IS NULL
        THROW 55001, 'Sesi palatabilitas FS0926-0001 tidak ditemukan.', 1;

    PRINT '';
    PRINT '--- Konteks ---';
    PRINT '  Sesi       : ' + CAST(@Session AS VARCHAR(10));
    PRINT '  Pembanding : ' + CAST(@Pemb AS VARCHAR(10));
    PRINT '  Sub sampel : ' + ISNULL(@Sub, '(tanpa sub)');


    -- ========================================================================
    -- 1. Rencana nilai per parameter
    -- ========================================================================
    -- Urutan baris HARUS mengikuti urutan Id_Quality_Control (111..117),
    -- karena pemasangan nama parameter di layar memakai urutan tersebut.
    DECLARE @N TABLE (
        Ja    INT,
        Qc    INT,
        Urut  INT,
        Nilai FLOAT
    );

    -- DURASI (52) — lama responden menghabiskan sampel
    INSERT INTO @N (Ja, Qc, Urut, Nilai) VALUES
        (52, 111, 1, 61.20),   -- Pilihan Sampel
        (52, 112, 2,  5.00),   -- Pilihan Kontrol
        (52, 113, 3,  1.00),   -- Tdk Memilih
        (52, 114, 4,  4.00),   -- Sampel (%)
        (52, 115, 5,  1.78),   -- Sampel vs Kontrol
        (52, 116, 6, 12.00),   -- Waktu (detik)
        (52, 117, 7, 88.00);   -- Habis (%)

    -- TINGKAT KONSUMSI (87) — seberapa banyak sampel dikonsumsi
    INSERT INTO @N (Ja, Qc, Urut, Nilai) VALUES
        (87, 111, 1, 58.40),
        (87, 112, 2,  6.00),
        (87, 113, 3,  0.00),
        (87, 114, 4,  3.00),
        (87, 115, 5,  1.62),
        (87, 116, 6,  9.00),
        (87, 117, 7, 92.00);


    -- ========================================================================
    -- 2. Baris hasil uji
    -- ========================================================================
    PRINT '';
    PRINT '--- 2. Hasil uji ---';

    INSERT INTO N_EMI_LAB_Uji_Sampel
        (Kode_Perusahaan, No_Faktur, No_Po_Sampel, No_Fak_Sub_Po,
         Id_Jenis_Analisa, Hasil, Flag_Perhitungan, Status, Tanggal, Jam,
         Id_User, Flag_Selesai, Tahapan_Ke, Status_Keputusan_Sampel,
         Flag_Layak, Id_Mesin, Flag_Foto, Id_Session, Id_Pembanding)
    SELECT '001',
           'FUS0926-5' + CAST(n.Ja AS VARCHAR(3)),
           @Sampel, @Sub, n.Ja, n.Nilai, NULL, NULL, @Hari,
           CONVERT(VARCHAR(8), DATEADD(MINUTE, n.Ja, @Now), 108),
           @User, 'Y', 1, 'terima', 'Y', @Mesin, 'T', @Session, @Pemb
    FROM @N n
    WHERE NOT EXISTS (
        SELECT 1 FROM N_EMI_LAB_Uji_Sampel u
        WHERE u.No_Po_Sampel = @Sampel AND u.Id_Jenis_Analisa = n.Ja
    );

    PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' baris hasil uji.';


    -- ========================================================================
    -- 3. Detail parameter
    -- ========================================================================
    -- Mengikuti pola data yang sudah ada: seluruh nilai parameter satu jenis
    -- analisa dicatat pada faktur yang sama.
    PRINT '';
    PRINT '--- 3. Detail parameter ---';

    INSERT INTO N_EMI_LAB_Uji_Sampel_Detail
        (Kode_Perusahaan, No_Faktur_Uji_Sample, Id_Quality_Control,
         Value_Parameter, Tanggal, Jam, Id_User)
    SELECT '001', 'FUS0926-5' + CAST(n.Ja AS VARCHAR(3)), n.Qc,
           n.Nilai, @Hari, CONVERT(VARCHAR(8), @Now, 108), @User
    FROM @N n
    WHERE NOT EXISTS (
        SELECT 1 FROM N_EMI_LAB_Uji_Sampel_Detail d
        WHERE d.No_Faktur_Uji_Sample = 'FUS0926-5' + CAST(n.Ja AS VARCHAR(3))
          AND d.Id_Quality_Control = n.Qc
    );

    PRINT '  [+] ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' baris detail parameter.';

COMMIT TRANSACTION;
PRINT '';
PRINT '============================================================';
PRINT ' SELESAI.';
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
PRINT '--- Palatabilitas FS0926-0001 setelah dilengkapi ---';

SELECT ja.Jenis_Analisa,
       COUNT(*) AS Jumlah_Parameter,
       pb.Nama_Pembanding
FROM N_EMI_LAB_Uji_Sampel u
JOIN N_EMI_LAB_Jenis_Analisa ja ON ja.id = u.Id_Jenis_Analisa
LEFT JOIN N_EMI_LAB_Palatabilitas_Pembanding pb
       ON pb.Id_Pembanding = u.Id_Pembanding
WHERE u.No_Po_Sampel = 'FS0926-0001' AND ja.Kode_Aktivitas_Lab = 'PLT'
GROUP BY ja.Jenis_Analisa, pb.Nama_Pembanding
ORDER BY ja.Jenis_Analisa;

PRINT '';
PRINT '--- Nilai per parameter ---';

SELECT ja.Jenis_Analisa,
       q.Keterangan AS Parameter,
       CASE WHEN q.Satuan = 'NONE' THEN '' ELSE q.Satuan END AS Satuan,
       d.Value_Parameter AS Nilai
FROM N_EMI_LAB_Uji_Sampel u
JOIN N_EMI_LAB_Jenis_Analisa ja ON ja.id = u.Id_Jenis_Analisa
JOIN N_EMI_LAB_Uji_Sampel_Detail d ON d.No_Faktur_Uji_Sample = u.No_Faktur
LEFT JOIN EMI_Quality_Control q ON q.Id_QC_Formula = d.Id_Quality_Control
WHERE u.No_Po_Sampel = 'FS0926-0001' AND ja.Kode_Aktivitas_Lab = 'PLT'
GROUP BY ja.Jenis_Analisa, q.Keterangan, q.Satuan, d.Value_Parameter,
         d.Id_Quality_Control
ORDER BY ja.Jenis_Analisa, d.Id_Quality_Control;


/* ============================================================================
   ROLLBACK - buka komentar bila tambahan ini ingin dicabut.
   Baris RESPONDEN MEMAKAN yang lama tidak ikut terhapus.
   ============================================================================

DELETE FROM N_EMI_LAB_Uji_Sampel_Detail
WHERE No_Faktur_Uji_Sample IN ('FUS0926-552', 'FUS0926-587');

DELETE FROM N_EMI_LAB_Uji_Sampel
WHERE No_Po_Sampel = 'FS0926-0001' AND Id_Jenis_Analisa IN (52, 87);

   ==========================================================================*/
