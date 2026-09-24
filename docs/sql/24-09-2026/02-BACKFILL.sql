-- ============================================================================
-- 02-BACKFILL.sql  —  Pemulihan data historis (DML)
-- Tanggal : 24-09-2026
-- Target  : PRODUCTION (emi_db_real) dan staging (emi_tm_demo)
--
-- ISI:
--   A. Lengkapi konteks pada baris Detail_Final yang sudah ada
--   B. Sambungkan Detail_Final ke header finalisasinya
--   C. Pulihkan jejak validasi yang HILANG  (PLT, LCKV, ANL non-rumus)
--   D. Isi tabel approval dari riwayat validasi
--   E. Isi tabel approval dari riwayat finalisasi
--
-- LATAR BELAKANG:
--   Kode lama hanya mencatat ke Detail_Final bila Flag_Perhitungan='Y'.
--   Seluruh PLT dan LCKV — dan sebagian ANL — ber-Flag_Perhitungan NULL,
--   sehingga validasinya tidak pernah punya jejak. Skrip ini merekonstruksi
--   jejak itu dari N_EMI_LAB_Uji_Sampel, yang mencatat Flag_Selesai='Y'
--   dengan benar sejak awal.
--
-- ## CATATAN PENTING UNTUK PRODUCTION ##
--   Volume besar (ratusan ribu baris). Karena itu skrip ini menulis
--   PER BATCH dan COMMIT di setiap batch, BUKAN satu transaksi raksasa.
--   Konsekuensinya:
--     * Log transaksi tidak menumpuk — aman pada recovery model SIMPLE
--       dengan file log berukuran terbatas.
--     * Penguncian tabel berlangsung singkat per batch, tidak memblokir
--       aplikasi berjam-jam.
--     * Bila terhenti di tengah (timeout, koneksi putus), batch yang sudah
--       sukses tetap tersimpan. JALANKAN ULANG skrip ini — ia melanjutkan
--       dari titik terakhir, tidak mengulang dan tidak menggandakan.
--
-- SIFAT SKRIP:
--   * IDEMPOTEN — semua INSERT memakai NOT EXISTS, semua UPDATE bersyarat.
--   * Tidak menghapus baris, tidak mengubah Flag_Layak yang sudah ada.
--   * Baris hasil pemulihan ditandai Sumber_Pencatatan='BACKFILL' sehingga
--     selalu bisa dibedakan dari pencatatan realtime, dan bisa ditarik
--     kembali lewat 99-ROLLBACK.sql bila perlu.
--
-- PRASYARAT: 01-STRUKTUR.sql sudah dijalankan dan sukses.
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @now       DATETIME = GETDATE();
DECLARE @batch     INT      = 5000;   -- baris per batch; turunkan bila server sibuk
DECLARE @n         INT;
DECLARE @total     INT;
DECLARE @putaran   INT;

PRINT '============================================================';
PRINT ' 02-BACKFILL  —  ' + CONVERT(VARCHAR(19), @now, 120);
PRINT ' Database : ' + DB_NAME();
PRINT ' Ukuran batch : ' + CAST(@batch AS VARCHAR(10)) + ' baris';
PRINT '============================================================';


-- ----------------------------------------------------------------------------
-- PENJAGA
-- ----------------------------------------------------------------------------
IF COL_LENGTH('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','Id_Uji_Validasi_Final') IS NULL
BEGIN
    RAISERROR('Kolom Id_Uji_Validasi_Final belum ada. Jalankan 01-STRUKTUR.sql dulu.', 16, 1);
    RETURN;
END

IF OBJECT_ID('N_EMI_LAB_Hasil_Uji_Approval_Aktivitas','U') IS NULL
BEGIN
    RAISERROR('Tabel approval belum ada. Jalankan 01-STRUKTUR.sql dulu.', 16, 1);
    RETURN;
END

PRINT '[OK] Prasyarat terpenuhi.';
PRINT '';


-- ============================================================================
-- A. Lengkapi konteks pada baris Detail_Final yang sudah ada
-- ============================================================================
PRINT '--- A. Lengkapi konteks Detail_Final ---';

-- A1. Kode aktivitas + nama analisa, dari master. Dibatch karena
--     Detail_Final di production berisi puluhan ribu baris.
SET @total = 0; SET @putaran = 0;
WHILE 1 = 1
BEGIN
    UPDATE TOP (@batch) d
       SET d.Kode_Aktivitas_Lab = ja.Kode_Aktivitas_Lab,
           d.Nama_Jenis_Analisa = ja.Jenis_Analisa
    FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d
    JOIN N_EMI_LAB_Jenis_Analisa ja ON ja.id = d.Id_Jenis_Analisa
    WHERE d.Kode_Aktivitas_Lab IS NULL;

    SET @n = @@ROWCOUNT;
    SET @total += @n;
    SET @putaran += 1;
    IF @n = 0 BREAK;
    IF @putaran % 5 = 0 PRINT '      ... ' + CAST(@total AS VARCHAR(12)) + ' baris';
END
PRINT '  [A1] ' + CAST(@total AS VARCHAR(12)) + ' baris dilengkapi aktivitas & nama analisa.';

-- A2. Konteks palatabilitas (Id_Session / Id_Pembanding) dari Uji_Sampel.
--     Hanya baris yang kedua kolomnya masih kosong yang disentuh.
SET @total = 0; SET @putaran = 0;
WHILE 1 = 1
BEGIN
    UPDATE TOP (@batch) d
       SET d.Id_Session    = x.Id_Session,
           d.Id_Pembanding = x.Id_Pembanding
    FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d
    CROSS APPLY (
        SELECT TOP 1 u.Id_Session, u.Id_Pembanding
        FROM N_EMI_LAB_Uji_Sampel u
        WHERE u.No_Po_Sampel     = d.No_Sampel
          AND u.Id_Jenis_Analisa = d.Id_Jenis_Analisa
          AND (u.Id_Session IS NOT NULL OR u.Id_Pembanding IS NOT NULL)
        ORDER BY u.Tahapan_Ke DESC
    ) x
    WHERE d.Id_Session IS NULL
      AND d.Id_Pembanding IS NULL
      AND d.Kode_Aktivitas_Lab = 'PLT';

    SET @n = @@ROWCOUNT;
    SET @total += @n;
    SET @putaran += 1;
    IF @n = 0 BREAK;
END
PRINT '  [A2] ' + CAST(@total AS VARCHAR(12)) + ' baris PLT dipulihkan konteks sesinya.';

-- A3. Tandai baris pra-migrasi supaya asal-usulnya jelas selamanya.
SET @total = 0;
WHILE 1 = 1
BEGIN
    UPDATE TOP (@batch) N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
       SET Sumber_Pencatatan = 'PRA_MIGRASI',
           Dibuat_Pada       = ISNULL(Dibuat_Pada, Tanggal)
     WHERE Sumber_Pencatatan IS NULL;

    SET @n = @@ROWCOUNT;
    SET @total += @n;
    IF @n = 0 BREAK;
END
PRINT '  [A3] ' + CAST(@total AS VARCHAR(12)) + ' baris ditandai PRA_MIGRASI.';
PRINT '';


-- ============================================================================
-- B. Sambungkan Detail_Final ke header finalisasinya
-- ============================================================================
-- Aman karena No_Sampel unik di tabel header (dijamin UNIQUE index dari
-- 01-STRUKTUR dan diperiksa PRACHECK). Detail yang sampelnya belum
-- difinalisasi sengaja dibiarkan NULL — itu kondisi sah, bukan kesalahan.
PRINT '--- B. Sambungkan Detail_Final ke header ---';

SET @total = 0; SET @putaran = 0;
WHILE 1 = 1
BEGIN
    UPDATE TOP (@batch) d
       SET d.Id_Uji_Validasi_Final = h.Id_Uji_Validasi_Final
    FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d
    JOIN N_EMI_LAB_Hasil_Uji_Validasi_Final h ON h.No_Sampel = d.No_Sampel
    WHERE d.Id_Uji_Validasi_Final IS NULL;

    SET @n = @@ROWCOUNT;
    SET @total += @n;
    SET @putaran += 1;
    IF @n = 0 BREAK;
    IF @putaran % 5 = 0 PRINT '      ... ' + CAST(@total AS VARCHAR(12)) + ' baris';
END
PRINT '  [B] ' + CAST(@total AS VARCHAR(12)) + ' baris detail tersambung ke header.';
PRINT '';


-- ============================================================================
-- C. PEMULIHAN INTI — jejak validasi yang hilang
-- ============================================================================
-- Sumber kebenaran: N_EMI_LAB_Uji_Sampel dengan Flag_Selesai='Y',
-- yang berarti analisa itu benar-benar sudah divalidasi seseorang.
--
-- Flag_Layak diambil apa adanya. Bila NULL, diisi 'Y': alur non-perhitungan
-- lama menyetel Flag_Layak='Y' secara eksplisit saat validasi, sehingga NULL
-- berarti "tidak pernah ditandai tidak layak". Asumsi ini didokumentasikan
-- di sini agar dapat ditelusuri auditor.
--
-- NOT EXISTS memastikan tidak menimpa dan tidak menggandakan, sehingga skrip
-- aman dilanjutkan bila sempat terhenti.
PRINT '--- C. Pulihkan jejak validasi yang hilang ---';
PRINT '      (bagian terberat — ini yang memakan waktu paling lama)';

SET @total = 0; SET @putaran = 0;
WHILE 1 = 1
BEGIN
    INSERT INTO N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final (
        No_Sampel, No_Sub_Sampel, Id_Jenis_Analisa, Tahapan_Ke,
        Tanggal, Jam, Flag_Layak, Flag_Resampling, Id_User,
        Kode_Aktivitas_Lab, Nama_Jenis_Analisa, Id_Session, Id_Pembanding,
        Id_Uji_Validasi_Final, Sumber_Pencatatan, Dibuat_Pada
    )
    SELECT TOP (@batch)
        u.No_Po_Sampel,
        u.No_Fak_Sub_Po,                      -- NULL bila sampel tunggal
        u.Id_Jenis_Analisa,
        ISNULL(u.Tahapan_Ke, 1),
        u.Tanggal,
        u.Jam,
        ISNULL(u.Flag_Layak, 'Y'),
        u.Flag_Resampling,
        u.Id_User,
        ja.Kode_Aktivitas_Lab,
        ja.Jenis_Analisa,
        u.Id_Session,
        u.Id_Pembanding,
        h.Id_Uji_Validasi_Final,
        'BACKFILL',
        @now
    FROM N_EMI_LAB_Uji_Sampel u
    JOIN N_EMI_LAB_Jenis_Analisa ja
        ON ja.id = u.Id_Jenis_Analisa
    LEFT JOIN N_EMI_LAB_Hasil_Uji_Validasi_Final h
        ON h.No_Sampel = u.No_Po_Sampel
    WHERE u.Flag_Selesai = 'Y'
      AND u.Status IS NULL
      AND NOT EXISTS (
            SELECT 1
            FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d
            WHERE d.No_Sampel        = u.No_Po_Sampel
              AND d.Id_Jenis_Analisa = u.Id_Jenis_Analisa
              AND ISNULL(d.No_Sub_Sampel, '~') = ISNULL(u.No_Fak_Sub_Po, '~')
              AND ISNULL(d.Tahapan_Ke, 1)      = ISNULL(u.Tahapan_Ke, 1)
              AND ISNULL(d.Id_Pembanding, -1)  = ISNULL(u.Id_Pembanding, -1)
      );

    SET @n = @@ROWCOUNT;
    SET @total += @n;
    SET @putaran += 1;
    IF @n = 0 BREAK;
    IF @putaran % 5 = 0 PRINT '      ... ' + CAST(@total AS VARCHAR(12)) + ' jejak dipulihkan';
END
PRINT '  [C] ' + CAST(@total AS VARCHAR(12)) + ' jejak validasi dipulihkan ke Detail_Final.';
PRINT '';


-- ============================================================================
-- D. Isi tabel approval dari riwayat validasi
-- ============================================================================
-- Setiap Uji_Sampel ber-Flag_Selesai='Y' berarti ada orang yang
-- menyetujuinya; Id_User di baris itu adalah pelakunya.
--
-- PENTING — kenapa ada GROUP BY:
--   Satu analisa bisa punya BANYAK baris Uji_Sampel, karena satu uji
--   menghasilkan beberapa parameter (mis. palatabilitas dengan nilai 10, 0,
--   50, 1, 2, 85 dalam satu No_Faktur dan satu sesi). Sebagai HASIL UJI itu
--   memang banyak baris, tetapi sebagai PERSETUJUAN itu satu tindakan oleh
--   satu orang. Tanpa GROUP BY, INSERT ditolak UX_ApprovalAktivitas_Plt.
--
--   MIN(Flag_Layak) dipakai karena 'T' < 'Y' secara leksikal: bila salah satu
--   parameter tidak layak, persetujuannya ikut tertandai tidak layak.
--   Konservatif, dan memang itu yang diinginkan untuk audit mutu.
PRINT '--- D. Isi approval dari riwayat validasi ---';

SET @total = 0; SET @putaran = 0;
WHILE 1 = 1
BEGIN
    ;WITH Sumber AS (
        SELECT TOP (@batch)
            u.No_Po_Sampel                          AS No_Sampel,
            u.No_Fak_Sub_Po                         AS No_Sub_Sampel,
            u.Id_Jenis_Analisa,
            u.Id_Pembanding,
            u.Id_User,
            MAX(ja.Kode_Aktivitas_Lab)              AS Kode_Aktivitas_Lab,
            MAX(ja.Jenis_Analisa)                   AS Nama_Jenis_Analisa,
            MAX(ISNULL(u.Tahapan_Ke, 1))            AS Tahapan_Ke,
            MAX(u.Id_Session)                       AS Id_Session,
            MIN(ISNULL(u.Flag_Layak, 'Y'))          AS Flag_Layak,
            MIN(CAST(u.Tanggal AS DATE))            AS Tanggal,
            MIN(u.Jam)                              AS Jam,
            MAX(u.Flag_Resampling)                  AS Flag_Resampling
        FROM N_EMI_LAB_Uji_Sampel u
        JOIN N_EMI_LAB_Jenis_Analisa ja
            ON ja.id = u.Id_Jenis_Analisa
        WHERE u.Flag_Selesai = 'Y'
          AND u.Status IS NULL
          AND u.Id_User IS NOT NULL
          AND ja.Kode_Aktivitas_Lab IS NOT NULL
          AND NOT EXISTS (
                SELECT 1
                FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas a
                WHERE a.No_Sampel        = u.No_Po_Sampel
                  AND ISNULL(a.No_Sub_Sampel,'~') = ISNULL(u.No_Fak_Sub_Po,'~')
                  AND a.Id_Jenis_Analisa = u.Id_Jenis_Analisa
                  AND a.Jenis_Approval   = 'VALIDASI'
                  AND a.Id_User          = u.Id_User
                  AND ISNULL(a.Id_Pembanding, -1) = ISNULL(u.Id_Pembanding, -1)
          )
        GROUP BY u.No_Po_Sampel, u.No_Fak_Sub_Po, u.Id_Jenis_Analisa,
                 u.Id_Pembanding, u.Id_User
    )
    INSERT INTO N_EMI_LAB_Hasil_Uji_Approval_Aktivitas (
        No_Sampel, No_Sub_Sampel, No_Po, No_Split_Po, No_Batch, Kode_Barang,
        Kode_Aktivitas_Lab, Nama_Aktivitas,
        Id_Jenis_Analisa, Nama_Jenis_Analisa, Tahapan_Ke,
        Id_Session, Id_Pembanding,
        Id_User, Nama_User, Jenis_Approval, Flag_Approval, Flag_Layak,
        Tanggal, Jam, Dibuat_Pada,
        Flag_Trial_Produksi, Flag_Resampling, Sumber_Pencatatan
    )
    SELECT
        s.No_Sampel, s.No_Sub_Sampel,
        po.No_Po, po.No_Split_Po, po.No_Batch, po.Kode_Barang,
        s.Kode_Aktivitas_Lab, k.Nama_Aktivitas,
        s.Id_Jenis_Analisa, s.Nama_Jenis_Analisa, s.Tahapan_Ke,
        s.Id_Session, s.Id_Pembanding,
        s.Id_User, us.Nama, 'VALIDASI', 'Y', s.Flag_Layak,
        s.Tanggal, s.Jam, @now,
        po.Flag_Trial_Produksi, s.Flag_Resampling, 'BACKFILL'
    FROM Sumber s
    LEFT JOIN N_EMI_LIMS_Klasifikasi_Aktivitas_Lab k
        ON k.Kode_Aktivitas_Lab = s.Kode_Aktivitas_Lab
    LEFT JOIN N_EMI_LAB_PO_Sampel po
        ON po.No_Sampel = s.No_Sampel
    LEFT JOIN N_EMI_LAB_Users us
        ON us.UserId = s.Id_User;

    SET @n = @@ROWCOUNT;
    SET @total += @n;
    SET @putaran += 1;
    IF @n = 0 BREAK;
    IF @putaran % 5 = 0 PRINT '      ... ' + CAST(@total AS VARCHAR(12)) + ' baris approval';
END
PRINT '  [D] ' + CAST(@total AS VARCHAR(12)) + ' baris approval VALIDASI dipulihkan.';
PRINT '';


-- ============================================================================
-- E. Isi tabel approval dari riwayat finalisasi
-- ============================================================================
-- Finalisasi adalah persetujuan atas keseluruhan sampel, jadi dipasangkan ke
-- setiap analisa dalam sampel tersebut. Diringkas dengan GROUP BY karena satu
-- sampel bisa punya banyak baris detail untuk satu jenis analisa.
PRINT '--- E. Isi approval dari riwayat finalisasi ---';

SET @total = 0; SET @putaran = 0;
WHILE 1 = 1
BEGIN
    ;WITH Sumber AS (
        SELECT TOP (@batch)
            h.No_Sampel,
            d.No_Sub_Sampel,
            d.Id_Jenis_Analisa,
            h.Id_User,
            MAX(h.No_Po)                            AS No_Po,
            MAX(h.No_Split_Po)                      AS No_Split_Po,
            MAX(h.No_Batch)                         AS No_Batch,
            MAX(ISNULL(d.Kode_Aktivitas_Lab,'ANL')) AS Kode_Aktivitas_Lab,
            MAX(d.Nama_Jenis_Analisa)               AS Nama_Jenis_Analisa,
            MAX(ISNULL(d.Tahapan_Ke, 1))            AS Tahapan_Ke,
            MAX(h.Flag_Ok)                          AS Flag_Ok,
            MAX(CAST(h.Tanggal AS DATE))            AS Tanggal,
            MAX(h.Jam)                              AS Jam
        FROM N_EMI_LAB_Hasil_Uji_Validasi_Final h
        JOIN N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d
            ON d.No_Sampel = h.No_Sampel
        WHERE h.Id_User IS NOT NULL
          AND d.Id_Jenis_Analisa IS NOT NULL
          AND NOT EXISTS (
                SELECT 1
                FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas a
                WHERE a.No_Sampel        = h.No_Sampel
                  AND ISNULL(a.No_Sub_Sampel,'~') = ISNULL(d.No_Sub_Sampel,'~')
                  AND a.Id_Jenis_Analisa = d.Id_Jenis_Analisa
                  AND a.Jenis_Approval   = 'FINALISASI'
                  AND a.Id_User          = h.Id_User
                  AND a.Id_Pembanding IS NULL
          )
        GROUP BY h.No_Sampel, d.No_Sub_Sampel, d.Id_Jenis_Analisa, h.Id_User
    )
    INSERT INTO N_EMI_LAB_Hasil_Uji_Approval_Aktivitas (
        No_Sampel, No_Sub_Sampel, No_Po, No_Split_Po, No_Batch, Kode_Barang,
        Kode_Aktivitas_Lab, Nama_Aktivitas,
        Id_Jenis_Analisa, Nama_Jenis_Analisa, Tahapan_Ke,
        Id_User, Nama_User, Jenis_Approval, Flag_Approval, Flag_Layak,
        Tanggal, Jam, Dibuat_Pada,
        Flag_Trial_Produksi, Sumber_Pencatatan
    )
    SELECT
        s.No_Sampel, s.No_Sub_Sampel,
        s.No_Po, s.No_Split_Po, s.No_Batch, po.Kode_Barang,
        s.Kode_Aktivitas_Lab, k.Nama_Aktivitas,
        s.Id_Jenis_Analisa, s.Nama_Jenis_Analisa, s.Tahapan_Ke,
        s.Id_User, us.Nama, 'FINALISASI', 'Y', s.Flag_Ok,
        s.Tanggal, s.Jam, @now,
        po.Flag_Trial_Produksi, 'BACKFILL'
    FROM Sumber s
    LEFT JOIN N_EMI_LIMS_Klasifikasi_Aktivitas_Lab k
        ON k.Kode_Aktivitas_Lab = s.Kode_Aktivitas_Lab
    LEFT JOIN N_EMI_LAB_PO_Sampel po
        ON po.No_Sampel = s.No_Sampel
    LEFT JOIN N_EMI_LAB_Users us
        ON us.UserId = s.Id_User;

    SET @n = @@ROWCOUNT;
    SET @total += @n;
    SET @putaran += 1;
    IF @n = 0 BREAK;
    IF @putaran % 5 = 0 PRINT '      ... ' + CAST(@total AS VARCHAR(12)) + ' baris approval';
END
PRINT '  [E] ' + CAST(@total AS VARCHAR(12)) + ' baris approval FINALISASI dipulihkan.';
PRINT '';

PRINT '============================================================';
PRINT ' 02-BACKFILL SELESAI  —  ' + CONVERT(VARCHAR(19), GETDATE(), 120);
PRINT ' Lanjut ke 03-VERIFIKASI.sql';
PRINT '============================================================';
