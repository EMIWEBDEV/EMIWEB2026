-- ============================================================================
-- 99-ROLLBACK.sql  —  Pembatalan migrasi (DARURAT)
-- Tanggal : 23-09-2026
--
-- ### SELURUH ISI SKRIP INI DIKOMENTARI SECARA SENGAJA. ###
-- Tidak ada yang berjalan bila di-F5 tanpa dibuka lebih dulu.
--
-- Gunakan hanya bila migrasi harus dibatalkan dan aplikasi dikembalikan ke
-- versi lama. Buka komentar per tahap, sesuai sejauh mana migrasi berjalan.
--
-- YANG PERLU DIPAHAMI SEBELUM MEMAKAI:
--   * Tahap 1 (hapus baris BACKFILL) MEMBUANG jejak yang baru dipulihkan.
--     Setelah itu, PLT dan LCKV kembali tidak punya jejak — kondisi bug
--     semula. Data aslinya tetap aman di N_EMI_LAB_Uji_Sampel, jadi
--     02-BACKFILL.sql bisa dijalankan lagi kapan pun.
--   * Tahap 2 (hapus kolom) MEMBUANG isi kolom tersebut secara permanen.
--   * Kode aplikasi versi BARU membutuhkan kolom dan tabel ini. Jangan
--     jalankan tahap 2/3 selama versi baru masih ter-deploy.
--
-- URUTAN AMAN: turunkan aplikasi ke versi lama dulu, baru rollback database.
-- ============================================================================

SET NOCOUNT ON;

PRINT '============================================================';
PRINT ' 99-ROLLBACK — seluruh perintah dikomentari.';
PRINT ' Buka komentar pada tahap yang diperlukan saja.';
PRINT '============================================================';


-- ----------------------------------------------------------------------------
-- PEMERIKSAAN — berapa yang akan terdampak (read-only, boleh dijalankan)
-- ----------------------------------------------------------------------------
PRINT '';
PRINT '--- Dampak bila rollback dijalankan ---';

SELECT 'Detail_Final hasil BACKFILL (akan dihapus tahap 1)' AS Objek,
       COUNT(*) AS Jumlah_Baris
FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
WHERE Sumber_Pencatatan = 'BACKFILL'

UNION ALL
SELECT 'Detail_Final dari pencatatan realtime (TIDAK dihapus)',
       COUNT(*)
FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
WHERE Sumber_Pencatatan IN ('VALIDASI','FINALISASI')

UNION ALL
SELECT 'Detail_Final sebelum migrasi (TIDAK dihapus)',
       COUNT(*)
FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
WHERE Sumber_Pencatatan = 'PRA_MIGRASI' OR Sumber_Pencatatan IS NULL

UNION ALL
SELECT 'Baris tabel approval (akan hilang bila tabel di-drop)',
       COUNT(*)
FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas;


/* ============================================================================
   TAHAP 1 — Batalkan data hasil backfill saja (struktur tetap)
   Paling ringan. Dipakai bila hasil backfill dianggap salah, tapi struktur
   barunya tetap ingin dipertahankan.
   ============================================================================

BEGIN TRANSACTION;

    DELETE FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
    WHERE Sumber_Pencatatan = 'BACKFILL';
    PRINT CAST(@@ROWCOUNT AS VARCHAR(10)) + ' baris detail hasil backfill dihapus.';

    DELETE FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas
    WHERE Sumber_Pencatatan = 'BACKFILL';
    PRINT CAST(@@ROWCOUNT AS VARCHAR(10)) + ' baris approval hasil backfill dihapus.';

    -- Kembalikan penanda ke keadaan semula
    UPDATE N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
       SET Sumber_Pencatatan = NULL
     WHERE Sumber_Pencatatan = 'PRA_MIGRASI';

COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;   -- pakai ini bila hasilnya tidak sesuai

   ==========================================================================*/


/* ============================================================================
   TAHAP 2 — Buang struktur baru pada Detail_Final
   PERMANEN: isi kolom-kolom ini hilang.
   ============================================================================

-- Lepas FK lebih dulu
IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name='FK_ValidasiDetailFinal_ValidasiFinal')
    ALTER TABLE N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
        DROP CONSTRAINT FK_ValidasiDetailFinal_ValidasiFinal;

-- Lalu index
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_ValidasiDetailFinal_IdUjiValidasiFinal'
           AND object_id=OBJECT_ID('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final'))
    DROP INDEX IX_ValidasiDetailFinal_IdUjiValidasiFinal
        ON N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final;

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_ValidasiDetailFinal_Aktivitas'
           AND object_id=OBJECT_ID('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final'))
    DROP INDEX IX_ValidasiDetailFinal_Aktivitas
        ON N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final;

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_ValidasiDetailFinal_Sampel_Sub_Analisa_Tahapan'
           AND object_id=OBJECT_ID('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final'))
    DROP INDEX IX_ValidasiDetailFinal_Sampel_Sub_Analisa_Tahapan
        ON N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final;

-- Baru kolomnya
ALTER TABLE N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final
    DROP COLUMN Id_Uji_Validasi_Final, Kode_Aktivitas_Lab, Nama_Jenis_Analisa,
                Id_Session, Id_Pembanding, Sumber_Pencatatan, Dibuat_Pada;

PRINT 'Kolom tambahan pada Detail_Final dibuang.';

   ==========================================================================*/


/* ============================================================================
   TAHAP 3 — Buang index pada tabel Final
   Catatan: UNIQUE index inilah yang mencegah finalisasi saling menimpa.
   Membuangnya mengembalikan celah bug tersebut.
   ============================================================================

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='UX_HasilUjiValidasiFinal_SplitPo_Batch_Sampel'
           AND object_id=OBJECT_ID('N_EMI_LAB_Hasil_Uji_Validasi_Final'))
    DROP INDEX UX_HasilUjiValidasiFinal_SplitPo_Batch_Sampel
        ON N_EMI_LAB_Hasil_Uji_Validasi_Final;

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_HasilUjiValidasiFinal_NoSampel'
           AND object_id=OBJECT_ID('N_EMI_LAB_Hasil_Uji_Validasi_Final'))
    DROP INDEX IX_HasilUjiValidasiFinal_NoSampel
        ON N_EMI_LAB_Hasil_Uji_Validasi_Final;

PRINT 'Index pada tabel Final dibuang.';

   ==========================================================================*/


/* ============================================================================
   TAHAP 4 — Buang tabel approval
   PERMANEN: seluruh jejak siapa-approve-apa hilang.
   Disarankan backup dulu (baris pertama di bawah).
   ============================================================================

SELECT * INTO N_EMI_LAB_Hasil_Uji_Approval_Aktivitas_BAK
FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas;

DROP TABLE N_EMI_LAB_Hasil_Uji_Approval_Aktivitas;

PRINT 'Tabel approval dibuang (salinan tersimpan di ..._BAK).';

   ==========================================================================*/


PRINT '';
PRINT '=== Tidak ada perubahan dilakukan. Semua tahap masih dikomentari. ===';
