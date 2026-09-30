-- ============================================================================
-- 07-QUERY-DESKTOP.sql  —  READ ONLY. Kumpulan query siap pakai.
-- Tanggal : 23-09-2026
--
-- Untuk  : tim desktop / pelapor.
-- Isi    : cara menjawab "untuk PO + sampel + batch ini, look view di-approve
--          siapa, lab siapa, palatabilitas siapa".
--
-- Semua query di bawah hanya SELECT. Aman dijalankan di produksi.
-- Ganti nilai di blok PARAMETER, atau hapus baris WHERE-nya untuk semua data.
-- ============================================================================

SET NOCOUNT ON;

-- ============================================================================
-- PARAMETER — ubah di sini saja
-- ============================================================================
DECLARE @No_Sampel   VARCHAR(30) = NULL;   -- contoh: 'FS0526-0016'
DECLARE @No_Po       VARCHAR(25) = NULL;   -- contoh: 'PRD0526-00009'
DECLARE @No_Split_Po VARCHAR(25) = NULL;
DECLARE @No_Batch    FLOAT       = NULL;
-- NULL = tidak memfilter kriteria tersebut.


-- ============================================================================
-- QUERY 1 — RINGKASAN PER AKTIVITAS  (paling sering dipakai)
-- Satu baris per aktivitas: berapa analisa, siapa saja approver-nya.
-- Inilah bentuk yang diminta tim desktop.
-- ============================================================================
PRINT '--- QUERY 1: Ringkasan approver per aktivitas ---';

WITH Baris AS (
    SELECT a.*
    FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas a
    WHERE a.Jenis_Approval = 'VALIDASI'
      AND (@No_Sampel   IS NULL OR a.No_Sampel   = @No_Sampel)
      AND (@No_Po       IS NULL OR a.No_Po       = @No_Po)
      AND (@No_Split_Po IS NULL OR a.No_Split_Po = @No_Split_Po)
      AND (@No_Batch    IS NULL OR a.No_Batch    = @No_Batch)
),
-- Nama approver di-DISTINCT dulu: satu user wajar meng-approve banyak
-- analisa dalam satu aktivitas, dan namanya tidak boleh berulang.
Approver AS (
    SELECT DISTINCT No_Sampel, Kode_Aktivitas_Lab,
           ISNULL(Nama_User, Id_User) AS Approver
    FROM Baris
),
Statistik AS (
    SELECT
        No_Sampel, No_Po, No_Split_Po, No_Batch, Kode_Barang,
        Kode_Aktivitas_Lab, Nama_Aktivitas,
        COUNT(DISTINCT Id_Jenis_Analisa) AS Jumlah_Analisa,
        COUNT(DISTINCT Id_User)          AS Jumlah_Approver,
        MIN(Tanggal)                     AS Approval_Pertama,
        MAX(Tanggal)                     AS Approval_Terakhir
    FROM Baris
    GROUP BY No_Sampel, No_Po, No_Split_Po, No_Batch, Kode_Barang,
             Kode_Aktivitas_Lab, Nama_Aktivitas
)
SELECT
    s.No_Sampel,
    s.No_Po,
    s.No_Split_Po,
    s.No_Batch,
    s.Kode_Barang,
    s.Kode_Aktivitas_Lab              AS Kode,
    ISNULL(s.Nama_Aktivitas, '-')     AS Aktivitas,
    s.Jumlah_Analisa,
    s.Jumlah_Approver,
    -- FOR XML PATH dipakai (bukan STRING_AGG) supaya query ini juga jalan
    -- di SQL Server 2016. TYPE + .value() mencegah karakter '&' dan '<'
    -- berubah jadi entity HTML seperti '&amp;'.
    STUFF((
        SELECT ', ' + p.Approver
        FROM Approver p
        WHERE p.No_Sampel = s.No_Sampel
          AND p.Kode_Aktivitas_Lab = s.Kode_Aktivitas_Lab
        ORDER BY p.Approver
        FOR XML PATH(''), TYPE
    ).value('.', 'NVARCHAR(MAX)'), 1, 2, '') AS Daftar_Approver,
    s.Approval_Pertama,
    s.Approval_Terakhir
FROM Statistik s
ORDER BY s.No_Sampel, s.Kode_Aktivitas_Lab;


-- ============================================================================
-- QUERY 2 — RINCI PER ANALISA
-- "Look view punya 5 analisa — analisa mana di-approve siapa."
-- ============================================================================
PRINT '';
PRINT '--- QUERY 2: Rinci per analisa ---';

SELECT
    a.No_Sampel,
    a.No_Sub_Sampel,
    a.No_Po,
    a.No_Batch,
    a.Kode_Aktivitas_Lab            AS Kode,
    ISNULL(a.Nama_Aktivitas,'-')    AS Aktivitas,
    a.Id_Jenis_Analisa,
    ISNULL(a.Nama_Jenis_Analisa,'-') AS Jenis_Analisa,
    a.Tahapan_Ke,
    a.Id_User                       AS Approver_Id,
    ISNULL(a.Nama_User, a.Id_User)  AS Approver_Nama,
    a.Jenis_Approval,
    a.Flag_Approval,
    a.Flag_Layak,
    CASE a.Flag_Layak WHEN 'Y' THEN 'Layak'
                      WHEN 'T' THEN 'Tidak Layak'
                      ELSE '-' END AS Status_Kelayakan,
    a.Tanggal,
    a.Jam,
    CASE WHEN a.Flag_Trial_Produksi = 'Y' THEN 'Trial Produksi'
         ELSE 'Produksi' END       AS Jalur,
    a.Id_Session,
    a.Id_Pembanding,
    a.Sumber_Pencatatan
FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas a
WHERE (@No_Sampel   IS NULL OR a.No_Sampel   = @No_Sampel)
  AND (@No_Po       IS NULL OR a.No_Po       = @No_Po)
  AND (@No_Split_Po IS NULL OR a.No_Split_Po = @No_Split_Po)
  AND (@No_Batch    IS NULL OR a.No_Batch    = @No_Batch)
ORDER BY a.No_Sampel, a.Kode_Aktivitas_Lab, a.Nama_Jenis_Analisa, a.Tanggal, a.Jam;


-- ============================================================================
-- QUERY 3 — SATU BARIS PER SAMPEL (pivot)
-- Cocok untuk grid desktop: tiga aktivitas jadi tiga kolom.
-- ============================================================================
PRINT '';
PRINT '--- QUERY 3: Pivot satu baris per sampel ---';

WITH Approver AS (
    SELECT DISTINCT
        a.No_Sampel, a.Kode_Aktivitas_Lab,
        ISNULL(a.Nama_User, a.Id_User) AS Approver
    FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas a
    WHERE a.Jenis_Approval = 'VALIDASI'
      AND (@No_Sampel   IS NULL OR a.No_Sampel   = @No_Sampel)
      AND (@No_Po       IS NULL OR a.No_Po       = @No_Po)
      AND (@No_Split_Po IS NULL OR a.No_Split_Po = @No_Split_Po)
      AND (@No_Batch    IS NULL OR a.No_Batch    = @No_Batch)
),
Sampel AS (
    SELECT DISTINCT a.No_Sampel, a.No_Po, a.No_Split_Po, a.No_Batch, a.Kode_Barang
    FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas a
    WHERE a.Jenis_Approval = 'VALIDASI'
      AND (@No_Sampel   IS NULL OR a.No_Sampel   = @No_Sampel)
      AND (@No_Po       IS NULL OR a.No_Po       = @No_Po)
      AND (@No_Split_Po IS NULL OR a.No_Split_Po = @No_Split_Po)
      AND (@No_Batch    IS NULL OR a.No_Batch    = @No_Batch)
)
SELECT
    s.No_Sampel,
    s.No_Po,
    s.No_Split_Po,
    s.No_Batch,
    s.Kode_Barang,
    STUFF((SELECT ', ' + p.Approver FROM Approver p
           WHERE p.No_Sampel = s.No_Sampel AND p.Kode_Aktivitas_Lab = 'LCKV'
           ORDER BY p.Approver FOR XML PATH(''), TYPE).value('.','NVARCHAR(MAX)'),1,2,'') AS Approver_Look_View,
    STUFF((SELECT ', ' + p.Approver FROM Approver p
           WHERE p.No_Sampel = s.No_Sampel AND p.Kode_Aktivitas_Lab = 'ANL'
           ORDER BY p.Approver FOR XML PATH(''), TYPE).value('.','NVARCHAR(MAX)'),1,2,'') AS Approver_Analisa_Lab,
    STUFF((SELECT ', ' + p.Approver FROM Approver p
           WHERE p.No_Sampel = s.No_Sampel AND p.Kode_Aktivitas_Lab = 'PLT'
           ORDER BY p.Approver FOR XML PATH(''), TYPE).value('.','NVARCHAR(MAX)'),1,2,'') AS Approver_Palatabilitas,
    -- Finalisator: persetujuan akhir atas keseluruhan sampel
    STUFF((SELECT DISTINCT ', ' + ISNULL(f.Nama_User, f.Id_User)
           FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas f
           WHERE f.No_Sampel = s.No_Sampel AND f.Jenis_Approval = 'FINALISASI'
           FOR XML PATH(''), TYPE).value('.','NVARCHAR(MAX)'),1,2,'') AS Finalisator
FROM Sampel s
ORDER BY s.No_Sampel;


-- ============================================================================
-- QUERY 4 — KELENGKAPAN TAHAPAN
-- Ketiga aktivitas wajib (Flag_Wajib='Y' di master). Query ini menunjukkan
-- sampel mana yang belum lengkap tahapannya.
-- ============================================================================
PRINT '';
PRINT '--- QUERY 4: Kelengkapan tahapan per sampel ---';

WITH Ada AS (
    SELECT DISTINCT a.No_Sampel, a.Kode_Aktivitas_Lab
    FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas a
    WHERE a.Jenis_Approval = 'VALIDASI'
),
Sampel AS (
    SELECT DISTINCT No_Sampel FROM Ada
)
SELECT
    s.No_Sampel,
    MAX(CASE WHEN a.Kode_Aktivitas_Lab = 'LCKV' THEN 'Sudah' ELSE '' END) AS Look_View,
    MAX(CASE WHEN a.Kode_Aktivitas_Lab = 'ANL'  THEN 'Sudah' ELSE '' END) AS Analisa_Lab,
    MAX(CASE WHEN a.Kode_Aktivitas_Lab = 'PLT'  THEN 'Sudah' ELSE '' END) AS Palatabilitas,
    CASE WHEN COUNT(DISTINCT a.Kode_Aktivitas_Lab) = 3
         THEN 'LENGKAP'
         ELSE 'BELUM LENGKAP' END AS Status_Tahapan
FROM Sampel s
LEFT JOIN Ada a ON a.No_Sampel = s.No_Sampel
GROUP BY s.No_Sampel
ORDER BY Status_Tahapan, s.No_Sampel;


-- ============================================================================
-- QUERY 5 — REKAP PER USER
-- "Siapa mengerjakan apa, berapa banyak."
-- ============================================================================
PRINT '';
PRINT '--- QUERY 5: Rekap beban per user ---';

SELECT
    a.Id_User,
    ISNULL(a.Nama_User, a.Id_User)     AS Nama,
    a.Kode_Aktivitas_Lab               AS Kode,
    ISNULL(a.Nama_Aktivitas,'-')       AS Aktivitas,
    COUNT(*)                           AS Jumlah_Approval,
    COUNT(DISTINCT a.No_Sampel)        AS Jumlah_Sampel,
    SUM(CASE WHEN a.Flag_Layak = 'T' THEN 1 ELSE 0 END) AS Ditandai_Tidak_Layak,
    MIN(a.Tanggal)                     AS Sejak,
    MAX(a.Tanggal)                     AS Terakhir
FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas a
WHERE a.Jenis_Approval = 'VALIDASI'
GROUP BY a.Id_User, a.Nama_User, a.Kode_Aktivitas_Lab, a.Nama_Aktivitas
ORDER BY Jumlah_Approval DESC;


-- ============================================================================
-- QUERY 6 — HEADER + DETAIL lewat FK baru
-- Membuktikan Id_Uji_Validasi_Final benar-benar menghubungkan keduanya.
-- ============================================================================
PRINT '';
PRINT '--- QUERY 6: Header dan detail tersambung ---';

SELECT
    h.Id_Uji_Validasi_Final,
    h.No_Sampel,
    h.No_Po,
    h.No_Split_Po,
    h.No_Batch,
    CASE h.Flag_Ok WHEN 'Y' THEN 'Lolos Uji'
                   WHEN 'T' THEN 'Tidak Lolos Uji'
                   ELSE '-' END        AS Status_Final,
    h.Id_User                          AS Finalisator,
    h.Tanggal                          AS Tanggal_Finalisasi,
    COUNT(d.Id_Uji_Validasi_Detail_Final) AS Jumlah_Detail,
    SUM(CASE WHEN d.Kode_Aktivitas_Lab = 'LCKV' THEN 1 ELSE 0 END) AS Detail_Look_View,
    SUM(CASE WHEN d.Kode_Aktivitas_Lab = 'ANL'  THEN 1 ELSE 0 END) AS Detail_Analisa,
    SUM(CASE WHEN d.Kode_Aktivitas_Lab = 'PLT'  THEN 1 ELSE 0 END) AS Detail_Palatabilitas,
    SUM(CASE WHEN d.Flag_Layak = 'T' THEN 1 ELSE 0 END)            AS Detail_Tidak_Layak
FROM N_EMI_LAB_Hasil_Uji_Validasi_Final h
LEFT JOIN N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final d
    ON d.Id_Uji_Validasi_Final = h.Id_Uji_Validasi_Final
WHERE (@No_Sampel   IS NULL OR h.No_Sampel   = @No_Sampel)
  AND (@No_Po       IS NULL OR h.No_Po       = @No_Po)
  AND (@No_Split_Po IS NULL OR h.No_Split_Po = @No_Split_Po)
  AND (@No_Batch    IS NULL OR h.No_Batch    = @No_Batch)
GROUP BY h.Id_Uji_Validasi_Final, h.No_Sampel, h.No_Po, h.No_Split_Po,
         h.No_Batch, h.Flag_Ok, h.Id_User, h.Tanggal
ORDER BY h.Tanggal DESC, h.No_Sampel;
