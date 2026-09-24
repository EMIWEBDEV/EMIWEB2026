-- ============================================================================
-- 04-PEMBERSIHAN-OPSIONAL.sql
-- Tanggal : 23-09-2026
--
-- ### TIDAK WAJIB. JANGAN dijalankan otomatis oleh pipeline. ###
--
-- Skrip ini MENGHAPUS baris. Bagian penghapusannya sengaja dikomentari;
-- harus dibuka manual oleh DBA setelah hasil pemeriksaan ditinjau bersama
-- tim lab. Jalankan bagian 1 dan 2 (read-only) lebih dulu.
--
-- Konteks: Detail_Final punya baris duplikat dari masa sebelum migrasi —
-- akibat double-submit (dua baris identik berjarak beberapa detik, dari user
-- dan waktu yang sama). Duplikat ini TIDAK menghalangi migrasi; index pada
-- 01-STRUKTUR sengaja dibuat non-unique. Pembersihan murni kebersihan data.
-- ============================================================================

SET NOCOUNT ON;

-- ----------------------------------------------------------------------------
-- 1. Tampilkan kelompok duplikat
-- ----------------------------------------------------------------------------
PRINT '--- 1. Kelompok duplikat di Detail_Final ---';

SELECT
    d.No_Sampel,
    d.No_Sub_Sampel,
    d.Id_Jenis_Analisa,
    ISNULL(d.Nama_Jenis_Analisa, '-') AS Jenis_Analisa,
    d.Tahapan_Ke,
    ISNULL(d.Id_Pembanding, -1)       AS Pembanding,
    COUNT(*)                          AS Jumlah_Baris,
    MIN(d.Id_Uji_Validasi_Detail_Final) AS Id_Dipertahankan,
    COUNT(*) - 1                        AS Akan_Dihapus
FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d
GROUP BY d.No_Sampel, d.No_Sub_Sampel, d.Id_Jenis_Analisa,
         d.Nama_Jenis_Analisa, d.Tahapan_Ke, d.Id_Pembanding
HAVING COUNT(*) > 1
ORDER BY COUNT(*) DESC, d.No_Sampel;


-- ----------------------------------------------------------------------------
-- 2. Tampilkan baris yang AKAN dihapus (tinjau dulu)
--    Aturan: pertahankan Id terkecil (pencatatan pertama), hapus sisanya.
-- ----------------------------------------------------------------------------
PRINT '';
PRINT '--- 2. Baris yang akan dihapus ---';

WITH Peringkat AS (
    SELECT
        d.*,
        ROW_NUMBER() OVER (
            PARTITION BY d.No_Sampel, d.No_Sub_Sampel, d.Id_Jenis_Analisa,
                         d.Tahapan_Ke, ISNULL(d.Id_Pembanding, -1)
            ORDER BY d.Id_Uji_Validasi_Detail_Final
        ) AS Urutan
    FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d
)
SELECT
    Id_Uji_Validasi_Detail_Final AS Id_Dihapus,
    No_Sampel, No_Sub_Sampel, Id_Jenis_Analisa,
    ISNULL(Nama_Jenis_Analisa,'-') AS Jenis_Analisa,
    Tahapan_Ke, Flag_Layak, Tanggal, Jam, Id_User,
    ISNULL(Sumber_Pencatatan,'-')  AS Sumber
FROM Peringkat
WHERE Urutan > 1
ORDER BY No_Sampel, Id_Uji_Validasi_Detail_Final;

-- PERIKSA: pastikan tidak ada baris dengan Flag_Layak berbeda dalam satu
-- kelompok. Bila ada, JANGAN hapus otomatis — artinya kedua baris membawa
-- informasi berbeda dan perlu keputusan manusia.
PRINT '';
PRINT '--- 2b. Duplikat dengan Flag_Layak BERBEDA (jangan hapus otomatis) ---';

SELECT
    No_Sampel, No_Sub_Sampel, Id_Jenis_Analisa, Tahapan_Ke,
    COUNT(DISTINCT ISNULL(Flag_Layak,'?')) AS Variasi_Flag_Layak,
    COUNT(*)                               AS Jumlah_Baris
FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
GROUP BY No_Sampel, No_Sub_Sampel, Id_Jenis_Analisa, Tahapan_Ke
HAVING COUNT(*) > 1
   AND COUNT(DISTINCT ISNULL(Flag_Layak,'?')) > 1;
-- 0 baris = aman untuk pembersihan otomatis di bagian 3.


-- ----------------------------------------------------------------------------
-- 3. PENGHAPUSAN — sengaja dikomentari
--    Buka komentar HANYA setelah bagian 2b menghasilkan 0 baris DAN
--    tim lab menyetujui daftar di bagian 2.
--    Ambil backup tabel lebih dulu (bagian 3a).
-- ----------------------------------------------------------------------------
PRINT '';
PRINT '--- 3. Penghapusan (dikomentari — buka manual) ---';

/*  ===== BUKA KOMENTAR MULAI DARI SINI =====

-- 3a. Backup dulu. Tabel salinan diberi cap waktu.
DECLARE @backup NVARCHAR(200) =
    'N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final_BAK_' + FORMAT(GETDATE(),'yyyyMMdd_HHmm');
DECLARE @sql NVARCHAR(MAX) =
    'SELECT * INTO ' + QUOTENAME(@backup) +
    ' FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final';
EXEC sp_executesql @sql;
PRINT 'Backup dibuat: ' + @backup;

-- 3b. Hapus duplikat, pertahankan Id terkecil.
BEGIN TRANSACTION;

    WITH Peringkat AS (
        SELECT
            d.Id_Uji_Validasi_Detail_Final,
            ROW_NUMBER() OVER (
                PARTITION BY d.No_Sampel, d.No_Sub_Sampel, d.Id_Jenis_Analisa,
                             d.Tahapan_Ke, ISNULL(d.Id_Pembanding, -1)
                ORDER BY d.Id_Uji_Validasi_Detail_Final
            ) AS Urutan
        FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d
    )
    DELETE FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
    WHERE Id_Uji_Validasi_Detail_Final IN (
        SELECT Id_Uji_Validasi_Detail_Final FROM Peringkat WHERE Urutan > 1
    );

    PRINT CAST(@@ROWCOUNT AS VARCHAR(10)) + ' baris duplikat dihapus.';

-- Periksa hasilnya dulu, baru COMMIT. Kalau ragu: ROLLBACK.
COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;

    ===== SAMPAI SINI =====  */

PRINT '  (bagian penghapusan masih dikomentari — tidak ada yang dihapus)';


-- ----------------------------------------------------------------------------
-- 4. Setelah bersih: naikkan index detail menjadi UNIQUE (opsional)
--    Ini mencegah duplikat baru di level database, bukan cuma di aplikasi.
--    Jalankan HANYA bila bagian 1 sudah menghasilkan 0 baris.
-- ----------------------------------------------------------------------------
PRINT '';
PRINT '--- 4. Naikkan index jadi UNIQUE (dikomentari) ---';

/*  ===== BUKA KOMENTAR SETELAH DATA BERSIH =====

IF EXISTS (SELECT 1 FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
           GROUP BY No_Sampel, No_Sub_Sampel, Id_Jenis_Analisa, Tahapan_Ke,
                    ISNULL(Id_Pembanding,-1)
           HAVING COUNT(*) > 1)
BEGIN
    PRINT 'MASIH ADA DUPLIKAT — index UNIQUE tidak dibuat.';
END
ELSE
BEGIN
    IF EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'IX_ValidasiDetailFinal_Sampel_Sub_Analisa_Tahapan'
                 AND object_id = OBJECT_ID('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final'))
        DROP INDEX IX_ValidasiDetailFinal_Sampel_Sub_Analisa_Tahapan
            ON N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final;

    CREATE UNIQUE NONCLUSTERED INDEX UX_ValidasiDetailFinal_Unik
        ON N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
           (No_Sampel, No_Sub_Sampel, Id_Jenis_Analisa, Tahapan_Ke, Id_Pembanding);

    PRINT 'Index UNIQUE berhasil dibuat.';
END

    ===== SAMPAI SINI =====  */

PRINT '  (bagian index UNIQUE masih dikomentari)';
PRINT '';
PRINT '=== 04-PEMBERSIHAN SELESAI (mode tinjau saja) ===';
