-- ============================================================================
-- 02-BACKFILL.sql  —  Pemulihan data historis (DML)
-- Tanggal : 23-09-2026
--
-- ISI:
--   A. Isi Id_Uji_Validasi_Final pada baris Detail_Final lama
--   B. Isi Kode_Aktivitas_Lab / Nama_Jenis_Analisa / Id_Session / Id_Pembanding
--   C. Pulihkan jejak PLT & LCKV yang HILANG ke Detail_Final
--   D. Isi tabel approval dari data validasi historis
--
-- LATAR BELAKANG:
--   Kode lama hanya mencatat ke Detail_Final bila Flag_Perhitungan='Y'.
--   Seluruh PLT dan LCKV ber-Flag_Perhitungan NULL, sehingga ratusan validasi
--   yang benar-benar terjadi tidak punya jejak sama sekali. Skrip ini
--   merekonstruksi jejak itu dari N_EMI_LAB_Uji_Sampel, yang merupakan
--   sumber kebenaran (di sana Flag_Selesai='Y' tercatat dengan benar).
--
-- SIFAT SKRIP:
--   * IDEMPOTEN — semua INSERT memakai NOT EXISTS, semua UPDATE bersyarat.
--     Dijalankan 2x atau 10x hasilnya sama; tidak menggandakan baris.
--   * TRANSAKSIONAL — gagal di tengah = rollback penuh.
--   * TIDAK menghapus/mengubah baris yang sudah benar. Murni mengisi yang
--     kosong dan menambah yang hilang.
--   * Baris hasil backfill ditandai Sumber_Pencatatan='BACKFILL' sehingga
--     selalu bisa dibedakan dari pencatatan realtime — dan bisa ditarik
--     kembali bila perlu (lihat 99-ROLLBACK.sql).
--
-- PRASYARAT: 01-STRUKTUR.sql sudah dijalankan dan sukses.
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @now         DATETIME     = GETDATE();
DECLARE @userSistem  VARCHAR(30)  = 'SISTEM';
DECLARE @n           INT;

PRINT '============================================================';
PRINT ' 02-BACKFILL — mulai ' + CONVERT(VARCHAR(19), @now, 120);
PRINT '============================================================';

BEGIN TRY
BEGIN TRANSACTION;

    -- Penjaga: pastikan 01-STRUKTUR sudah jalan.
    IF COL_LENGTH('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final','Id_Uji_Validasi_Final') IS NULL
        THROW 52001, 'Kolom Id_Uji_Validasi_Final belum ada. Jalankan 01-STRUKTUR.sql dulu.', 1;

    IF OBJECT_ID('N_EMI_LAB_Hasil_Uji_Approval_Aktivitas','U') IS NULL
        THROW 52002, 'Tabel approval belum ada. Jalankan 01-STRUKTUR.sql dulu.', 1;

    PRINT '[OK] Prasyarat terpenuhi.';
    PRINT '';


    -- ========================================================================
    -- A. Hubungkan Detail_Final lama ke header-nya
    -- ========================================================================
    -- Aman karena No_Sampel terbukti unik di tabel header (dicek PRACHECK 6).
    -- Detail yang belum punya header (sampel belum difinalisasi) sengaja
    -- dibiarkan NULL — itu kondisi yang sah, bukan kesalahan.
    PRINT '--- A. Sambungkan Detail_Final -> Final ---';

    UPDATE d
       SET d.Id_Uji_Validasi_Final = h.Id_Uji_Validasi_Final
    FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d
    JOIN N_EMI_LAB_Hasil_Uji_Validasi_Final h
        ON h.No_Sampel = d.No_Sampel
    WHERE d.Id_Uji_Validasi_Final IS NULL;

    SET @n = @@ROWCOUNT;
    PRINT '  [A] ' + CAST(@n AS VARCHAR(10)) + ' baris detail tersambung ke header.';
    PRINT '';


    -- ========================================================================
    -- B. Lengkapi kolom konteks pada baris lama
    -- ========================================================================
    PRINT '--- B. Lengkapi konteks Detail_Final ---';

    UPDATE d
       SET d.Kode_Aktivitas_Lab = ja.Kode_Aktivitas_Lab,
           d.Nama_Jenis_Analisa = ja.Jenis_Analisa
    FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d
    JOIN N_EMI_LAB_Jenis_Analisa ja
        ON ja.id = d.Id_Jenis_Analisa
    WHERE d.Kode_Aktivitas_Lab IS NULL
       OR d.Nama_Jenis_Analisa IS NULL;

    SET @n = @@ROWCOUNT;
    PRINT '  [B1] ' + CAST(@n AS VARCHAR(10)) + ' baris dilengkapi aktivitas & nama analisa.';

    -- Id_Session / Id_Pembanding dari Uji_Sampel (relevan untuk PLT).
    -- Dipakai padanan sub-sampel yang toleran terhadap data lama, yang
    -- kadang menyimpan No_Sampel di kolom No_Sub_Sampel.
    UPDATE d
       SET d.Id_Session    = u.Id_Session,
           d.Id_Pembanding = u.Id_Pembanding
    FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d
    JOIN N_EMI_LAB_Uji_Sampel u
        ON  u.No_Po_Sampel     = d.No_Sampel
        AND u.Id_Jenis_Analisa = d.Id_Jenis_Analisa
        AND ISNULL(u.No_Fak_Sub_Po, u.No_Po_Sampel) = ISNULL(d.No_Sub_Sampel, d.No_Sampel)
    WHERE d.Id_Session IS NULL
      AND d.Id_Pembanding IS NULL
      AND (u.Id_Session IS NOT NULL OR u.Id_Pembanding IS NOT NULL);

    SET @n = @@ROWCOUNT;
    PRINT '  [B2] ' + CAST(@n AS VARCHAR(10)) + ' baris dilengkapi konteks PLT.';

    -- Tandai baris pra-migrasi supaya asal-usulnya jelas selamanya.
    UPDATE N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
       SET Sumber_Pencatatan = 'PRA_MIGRASI',
           Dibuat_Pada       = ISNULL(Dibuat_Pada, Tanggal)
    WHERE Sumber_Pencatatan IS NULL;

    SET @n = @@ROWCOUNT;
    PRINT '  [B3] ' + CAST(@n AS VARCHAR(10)) + ' baris ditandai PRA_MIGRASI.';
    PRINT '';


    -- ========================================================================
    -- C. PEMULIHAN INTI — jejak PLT & LCKV yang hilang
    -- ========================================================================
    -- Sumber kebenaran: N_EMI_LAB_Uji_Sampel dengan Flag_Selesai='Y'
    -- (artinya analisa itu benar-benar sudah divalidasi seseorang).
    --
    -- Flag_Layak diambil apa adanya dari Uji_Sampel. Bila NULL, diisi 'Y':
    --   alur non-perhitungan lama memang menyetel Flag_Layak='Y' secara
    --   eksplisit saat validasi, sehingga NULL berarti "tidak pernah
    --   ditandai tidak layak". Ini asumsi konservatif dan didokumentasikan
    --   di sini agar dapat ditelusuri auditor.
    --
    -- NOT EXISTS memastikan: tidak menimpa, tidak menggandakan.
    PRINT '--- C. Pulihkan jejak validasi yang hilang ---';

    INSERT INTO N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final (
        No_Sampel, No_Sub_Sampel, Id_Jenis_Analisa, Tahapan_Ke,
        Tanggal, Jam, Flag_Layak, Flag_Resampling, Id_User,
        Kode_Aktivitas_Lab, Nama_Jenis_Analisa, Id_Session, Id_Pembanding,
        Id_Uji_Validasi_Final, Sumber_Pencatatan, Dibuat_Pada
    )
    SELECT
        u.No_Po_Sampel,
        ISNULL(u.No_Fak_Sub_Po, u.No_Po_Sampel),
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
              AND ISNULL(d.No_Sub_Sampel, d.No_Sampel) = ISNULL(u.No_Fak_Sub_Po, u.No_Po_Sampel)
              AND ISNULL(d.Tahapan_Ke, 1)              = ISNULL(u.Tahapan_Ke, 1)
              AND ISNULL(d.Id_Pembanding, -1)          = ISNULL(u.Id_Pembanding, -1)
      );

    SET @n = @@ROWCOUNT;
    PRINT '  [C] ' + CAST(@n AS VARCHAR(10)) + ' jejak validasi dipulihkan ke Detail_Final.';
    PRINT '';


    -- ========================================================================
    -- D. Isi tabel approval dari riwayat validasi
    -- ========================================================================
    -- Setiap Uji_Sampel yang Flag_Selesai='Y' berarti ada orang yang
    -- menyetujuinya; Id_User di baris itu adalah pelakunya. Inilah yang
    -- direkonstruksi sebagai Jenis_Approval='VALIDASI'.
    --
    -- PENTING — kenapa ada GROUP BY:
    -- Satu analisa bisa punya BANYAK baris Uji_Sampel, karena satu uji
    -- menghasilkan beberapa parameter (mis. palatabilitas FS0626-0001 punya
    -- 7 baris dengan Hasil 10, 0, 50, 1, 2, 85 ... dalam satu No_Faktur dan
    -- satu Id_Session). Sebagai HASIL UJI itu memang banyak baris, tetapi
    -- sebagai PERSETUJUAN itu satu tindakan oleh satu orang.
    --
    -- Tanpa GROUP BY, backfill mencoba menulis satu baris approval per
    -- parameter dan langsung ditolak UX_ApprovalAktivitas_Plt. Peringkasan
    -- ini yang membuat tabel approval benar secara makna: satu baris = satu
    -- persetujuan, bukan satu nilai hasil.
    --
    -- Flag_Layak memakai MIN(): bila salah satu parameter ditandai 'T',
    -- 'T' < 'Y' secara leksikal sehingga persetujuan ikut tertandai tidak
    -- layak. Konservatif, dan itu memang yang diinginkan untuk audit mutu.
    PRINT '--- D. Isi tabel approval aktivitas ---';

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
        u.No_Po_Sampel,
        ISNULL(u.No_Fak_Sub_Po, u.No_Po_Sampel),
        MAX(po.No_Po),
        MAX(po.No_Split_Po),
        MAX(po.No_Batch),
        MAX(po.Kode_Barang),
        MAX(ja.Kode_Aktivitas_Lab),
        MAX(k.Nama_Aktivitas),
        u.Id_Jenis_Analisa,
        MAX(ja.Jenis_Analisa),
        MAX(ISNULL(u.Tahapan_Ke, 1)),
        MAX(u.Id_Session),
        u.Id_Pembanding,
        u.Id_User,
        MAX(us.Nama),
        'VALIDASI',
        'Y',
        MIN(ISNULL(u.Flag_Layak, 'Y')),      -- 'T' menang atas 'Y'
        MIN(CAST(u.Tanggal AS DATE)),        -- waktu persetujuan paling awal
        MIN(u.Jam),
        @now,
        MAX(po.Flag_Trial_Produksi),
        MAX(u.Flag_Resampling),
        'BACKFILL'
    FROM N_EMI_LAB_Uji_Sampel u
    JOIN N_EMI_LAB_Jenis_Analisa ja
        ON ja.id = u.Id_Jenis_Analisa
    LEFT JOIN N_EMI_LIMS_Klasifikasi_Aktivitas_Lab k
        ON k.Kode_Aktivitas_Lab = ja.Kode_Aktivitas_Lab
    LEFT JOIN N_EMI_LAB_PO_Sampel po
        ON po.No_Sampel = u.No_Po_Sampel
    LEFT JOIN N_EMI_LAB_Users us
        ON us.UserId = u.Id_User
    WHERE u.Flag_Selesai = 'Y'
      AND u.Status IS NULL
      AND u.Id_User IS NOT NULL
      AND ja.Kode_Aktivitas_Lab IS NOT NULL
      AND NOT EXISTS (
            SELECT 1
            FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas a
            WHERE a.No_Sampel        = u.No_Po_Sampel
              AND ISNULL(a.No_Sub_Sampel,'') = ISNULL(ISNULL(u.No_Fak_Sub_Po, u.No_Po_Sampel),'')
              AND a.Id_Jenis_Analisa = u.Id_Jenis_Analisa
              AND a.Jenis_Approval   = 'VALIDASI'
              AND a.Id_User          = u.Id_User
              AND ISNULL(a.Id_Pembanding, -1) = ISNULL(u.Id_Pembanding, -1)
      )
    GROUP BY
        u.No_Po_Sampel,
        ISNULL(u.No_Fak_Sub_Po, u.No_Po_Sampel),
        u.Id_Jenis_Analisa,
        u.Id_Pembanding,
        u.Id_User;

    SET @n = @@ROWCOUNT;
    PRINT '  [D1] ' + CAST(@n AS VARCHAR(10)) + ' baris approval VALIDASI dipulihkan.';

    -- Jejak FINALISASI dari header yang sudah ada.
    -- Dipasangkan ke setiap analisa dalam sampel tersebut, karena finalisasi
    -- adalah persetujuan atas keseluruhan sampel.
    INSERT INTO N_EMI_LAB_Hasil_Uji_Approval_Aktivitas (
        No_Sampel, No_Sub_Sampel, No_Po, No_Split_Po, No_Batch, Kode_Barang,
        Kode_Aktivitas_Lab, Nama_Aktivitas,
        Id_Jenis_Analisa, Nama_Jenis_Analisa, Tahapan_Ke,
        Id_User, Nama_User, Jenis_Approval, Flag_Approval, Flag_Layak,
        Tanggal, Jam, Dibuat_Pada,
        Flag_Trial_Produksi, Sumber_Pencatatan
    )
    -- Diringkas dengan GROUP BY, bukan DISTINCT: satu sampel bisa punya
    -- banyak baris detail untuk satu jenis analisa (mis. PLT dengan beberapa
    -- pembanding), sedangkan finalisasinya satu tindakan. Kunci peringkasan
    -- mengikuti UX_ApprovalAktivitas_NonPlt, karena baris FINALISASI selalu
    -- ber-Id_Pembanding NULL.
    SELECT
        h.No_Sampel,
        ISNULL(d.No_Sub_Sampel, h.No_Sampel),
        MAX(h.No_Po),
        MAX(h.No_Split_Po),
        MAX(h.No_Batch),
        MAX(po.Kode_Barang),
        MAX(ISNULL(d.Kode_Aktivitas_Lab, 'ANL')),
        MAX(k.Nama_Aktivitas),
        d.Id_Jenis_Analisa,
        MAX(d.Nama_Jenis_Analisa),
        MAX(ISNULL(d.Tahapan_Ke, 1)),
        h.Id_User,
        MAX(us.Nama),
        'FINALISASI',
        'Y',
        MAX(h.Flag_Ok),
        MAX(CAST(h.Tanggal AS DATE)),
        MAX(h.Jam),
        @now,
        MAX(po.Flag_Trial_Produksi),
        'BACKFILL'
    FROM N_EMI_LAB_Hasil_Uji_Validasi_Final h
    JOIN N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d
        ON d.No_Sampel = h.No_Sampel
    LEFT JOIN N_EMI_LIMS_Klasifikasi_Aktivitas_Lab k
        ON k.Kode_Aktivitas_Lab = d.Kode_Aktivitas_Lab
    LEFT JOIN N_EMI_LAB_PO_Sampel po
        ON po.No_Sampel = h.No_Sampel
    LEFT JOIN N_EMI_LAB_Users us
        ON us.UserId = h.Id_User
    WHERE h.Id_User IS NOT NULL
      AND d.Id_Jenis_Analisa IS NOT NULL
      AND NOT EXISTS (
            SELECT 1
            FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas a
            WHERE a.No_Sampel        = h.No_Sampel
              AND ISNULL(a.No_Sub_Sampel,'') = ISNULL(ISNULL(d.No_Sub_Sampel, h.No_Sampel),'')
              AND a.Id_Jenis_Analisa = d.Id_Jenis_Analisa
              AND a.Jenis_Approval   = 'FINALISASI'
              AND a.Id_User          = h.Id_User
              AND a.Id_Pembanding IS NULL
      )
    GROUP BY
        h.No_Sampel,
        ISNULL(d.No_Sub_Sampel, h.No_Sampel),
        d.Id_Jenis_Analisa,
        h.Id_User;

    SET @n = @@ROWCOUNT;
    PRINT '  [D2] ' + CAST(@n AS VARCHAR(10)) + ' baris approval FINALISASI dipulihkan.';
    PRINT '';


COMMIT TRANSACTION;
PRINT '============================================================';
PRINT ' 02-BACKFILL BERHASIL. Lanjut ke 03-VERIFIKASI.sql';
PRINT '============================================================';

END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;

    PRINT '';
    PRINT '### GAGAL — SEMUA PERUBAHAN DIBATALKAN (ROLLBACK) ###';
    PRINT 'Tidak ada satu baris pun yang tersimpan. Aman untuk diulang.';
    PRINT '';
    PRINT 'Pesan : ' + ERROR_MESSAGE();
    PRINT 'Baris : ' + CAST(ERROR_LINE() AS VARCHAR(10));

    THROW;
END CATCH;
