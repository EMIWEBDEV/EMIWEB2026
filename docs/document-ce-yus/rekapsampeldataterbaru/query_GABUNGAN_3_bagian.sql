/* =====================================================================
   REKAPITULASI LIMS - NEW PRODUCT (CF)
   Database : emi_db_real   |   READ-ONLY (SELECT saja)

   Berisi 3 result set sekaligus:
     1. Rekap per PO        -> 1 baris per No PO
     2. Rekap per Sampel    -> 1 baris per No Sampel
     3. Detail Analisa      -> 1 baris per jenis analisa per sampel

   CATATAN PENTING:
   - Tiap query diawali  ;WITH  (titik koma wajib di SQL Server bila
     ada statement sebelumnya) dan dipisah dengan GO.
   - Filter produk: Nama barang LIKE '%CF%'
   - View barang di-dedupe (MIN(Nama) GROUP BY Kode_Barang) karena
     1 barang muncul 15x (satu baris per stock owner / gudang).
   - Hasil = -999999 adalah sentinel untuk nilai non-numerik; nilai
     aslinya ada di Nilai_Hasil_String, maka sentinel dibuang dari
     perhitungan rata-rata/min/max.
   ===================================================================== */

/* ============ 1. REKAP PER PO ============ */
SELECT '=== 1. REKAP PER PO ===' AS [BAGIAN];
GO

;WITH B AS (
    SELECT Kode_Barang, MIN(Nama) AS Nama FROM N_EMI_View_Barang GROUP BY Kode_Barang
),
S AS (
    SELECT p.*, b.Nama FROM N_EMI_LAB_PO_Sampel p
    JOIN B b ON b.Kode_Barang = p.Kode_Barang
    WHERE b.Nama LIKE '%CF%'
)
SELECT
    s.No_Po       AS [No PO],
    MIN(s.Kode_Barang) AS [Kode Barang],
    MIN(s.Nama)        AS [Nama Barang],
    CONVERT(varchar(10), MIN(s.Tanggal), 120) AS [Tgl Mulai],
    CONVERT(varchar(10), MAX(s.Tanggal), 120) AS [Tgl Akhir],
    COUNT(DISTINCT s.No_Split_Po) AS [Jml Split PO],
    COUNT(DISTINCT s.No_Batch)    AS [Jml Batch],
    COUNT(*)                      AS [Jml Sampel],
    ISNULL(STUFF((SELECT DISTINCT ', ' + x.Id_User FROM S x
           WHERE x.No_Po = s.No_Po AND x.Id_User IS NOT NULL
           FOR XML PATH('')),1,2,''),'-') AS [Registrasi Sampel],
    ISNULL(STUFF((SELECT DISTINCT ', ' + ja.Kode_Analisa
           FROM N_EMI_LAB_Uji_Sampel u
           JOIN S s2 ON s2.No_Sampel = u.No_Po_Sampel
           JOIN N_EMI_LAB_Jenis_Analisa ja ON ja.id = u.Id_Jenis_Analisa
           WHERE s2.No_Po = s.No_Po
           FOR XML PATH('')),1,2,''),'-') AS [Jenis Analisa di PO Ini],
    ISNULL(STUFF((SELECT DISTINCT ', ' + u.Id_User
           FROM N_EMI_LAB_Uji_Sampel u
           JOIN S s3 ON s3.No_Sampel = u.No_Po_Sampel
           WHERE s3.No_Po = s.No_Po AND u.Id_User IS NOT NULL
           FOR XML PATH('')),1,2,''),'-') AS [Semua Penginput Hasil],
    ISNULL(STUFF((SELECT DISTINCT ', ' + v.Id_User
           FROM N_EMI_LAB_Hasil_Uji_Validasi_Final v
           JOIN S s4 ON s4.No_Sampel = v.No_Sampel
           WHERE s4.No_Po = s.No_Po AND v.Id_User IS NOT NULL
           FOR XML PATH('')),1,2,''),'-') AS [Semua Validator],
    (SELECT COUNT(*) FROM N_EMI_LAB_Hasil_Uji_Validasi_Final v
      JOIN S s5 ON s5.No_Sampel = v.No_Sampel
      WHERE s5.No_Po = s.No_Po AND v.Flag_Ok='Y')    AS [Sampel OK],
    (SELECT COUNT(*) FROM N_EMI_LAB_Hasil_Uji_Validasi_Final v
      JOIN S s6 ON s6.No_Sampel = v.No_Sampel
      WHERE s6.No_Po = s.No_Po AND v.Flag_Ok<>'Y')   AS [Sampel Tidak OK],
    COUNT(*) - (SELECT COUNT(*) FROM N_EMI_LAB_Hasil_Uji_Validasi_Final v
      JOIN S s7 ON s7.No_Sampel = v.No_Sampel
      WHERE s7.No_Po = s.No_Po)                      AS [Belum Validasi],
    CASE WHEN COUNT(*) = (SELECT COUNT(*) FROM N_EMI_LAB_Hasil_Uji_Validasi_Final v
                           JOIN S s8 ON s8.No_Sampel = v.No_Sampel WHERE s8.No_Po = s.No_Po)
         THEN 'SELESAI VALIDASI' ELSE 'PROSES' END   AS [Status PO]
FROM S s
GROUP BY s.No_Po
ORDER BY s.No_Po DESC
;
GO

/* ============ 2. REKAP PER SAMPEL ============ */
SELECT '=== 2. REKAP PER SAMPEL ===' AS [BAGIAN];
GO

;WITH B AS (
    SELECT Kode_Barang, MIN(Nama) AS Nama, MIN(Satuan) AS Satuan
    FROM N_EMI_View_Barang GROUP BY Kode_Barang
),
S AS (
    SELECT p.* FROM N_EMI_LAB_PO_Sampel p
    JOIN B b ON b.Kode_Barang = p.Kode_Barang
    WHERE b.Nama LIKE '%CF%'
),
U AS (
    SELECT u.No_Po_Sampel,
           COUNT(*) AS Jml_Ulangan,
           COUNT(DISTINCT u.Id_Jenis_Analisa) AS Jml_Jenis,
           MAX(u.Tahapan_Ke) AS Tahapan
    FROM N_EMI_LAB_Uji_Sampel u GROUP BY u.No_Po_Sampel
)
SELECT
    s.No_Po        AS [No PO],
    s.No_Split_Po  AS [Split PO],
    s.No_Batch     AS [Batch],
    s.No_Sampel    AS [No Sampel],
    s.Kode_Barang  AS [Kode Barang],
    b.Nama         AS [Nama Barang],
    b.Satuan       AS [Satuan],
    CONVERT(varchar(10), s.Tanggal, 120) AS [Tgl Registrasi],
    s.Id_User      AS [Registrasi Sampel],
    ISNULL(U.Jml_Jenis,0)   AS [Jml Jenis Analisa],
    -- daftar analisa apa saja
    ISNULL(STUFF((SELECT DISTINCT ', ' + ja2.Kode_Analisa
           FROM N_EMI_LAB_Uji_Sampel u2
           JOIN N_EMI_LAB_Jenis_Analisa ja2 ON ja2.id = u2.Id_Jenis_Analisa
           WHERE u2.No_Po_Sampel = s.No_Sampel
           FOR XML PATH('')), 1, 2, ''), '-') AS [Daftar Jenis Analisa],
    -- pasangan analisa:penginput
    ISNULL(STUFF((SELECT DISTINCT ' | ' + ja3.Kode_Analisa + ': ' + u3.Id_User
           FROM N_EMI_LAB_Uji_Sampel u3
           JOIN N_EMI_LAB_Jenis_Analisa ja3 ON ja3.id = u3.Id_Jenis_Analisa
           WHERE u3.No_Po_Sampel = s.No_Sampel AND u3.Id_User IS NOT NULL
           FOR XML PATH('')), 1, 3, ''), '-') AS [Analisa : Penginput],
    ISNULL(STUFF((SELECT DISTINCT ', ' + u4.Id_User
           FROM N_EMI_LAB_Uji_Sampel u4
           WHERE u4.No_Po_Sampel = s.No_Sampel AND u4.Id_User IS NOT NULL
           FOR XML PATH('')), 1, 2, ''), '-') AS [Semua Penginput],
    ISNULL(v.Id_User,'-')  AS [Yang Memvalidasi Hasil],
    CONVERT(varchar(10), v.Tanggal, 120) AS [Tgl Validasi],
    CASE WHEN v.No_Sampel IS NULL THEN 'BELUM VALIDASI'
         WHEN v.Flag_Ok='Y' THEN 'OK' ELSE 'TIDAK OK' END AS [Hasil],
    CASE WHEN v.Flag_FG='Y' THEN 'FG' ELSE '' END AS [Status FG],
    ISNULL(U.Jml_Ulangan,0) AS [Total Pembacaan],
    ISNULL(U.Tahapan,0)     AS [Tahapan Tertinggi],
    CASE WHEN s.Flag_Selesai='Y' THEN 'SELESAI' ELSE 'BELUM SELESAI' END AS [Status Sampel]
FROM S s
JOIN B b ON b.Kode_Barang = s.Kode_Barang
LEFT JOIN U ON U.No_Po_Sampel = s.No_Sampel
LEFT JOIN N_EMI_LAB_Hasil_Uji_Validasi_Final v ON v.No_Sampel = s.No_Sampel
ORDER BY s.No_Po DESC, s.No_Sampel DESC
;
GO

/* ============ 3. DETAIL ANALISA ============ */
SELECT '=== 3. DETAIL ANALISA ===' AS [BAGIAN];
GO

;WITH B AS (
    SELECT Kode_Barang, MIN(Nama) AS Nama, MIN(Satuan) AS Satuan
    FROM N_EMI_View_Barang GROUP BY Kode_Barang
),
S AS (
    SELECT p.* FROM N_EMI_LAB_PO_Sampel p
    JOIN B b ON b.Kode_Barang = p.Kode_Barang
    WHERE b.Nama LIKE '%CF%'
)
SELECT
    s.No_Po        AS [No PO],
    s.No_Split_Po  AS [Split PO],
    s.No_Batch     AS [Batch],
    s.No_Sampel    AS [No Sampel],
    s.Kode_Barang  AS [Kode Barang],
    b.Nama         AS [Nama Barang],
    ja.Kode_Analisa   AS [Kode Analisa],
    ja.Jenis_Analisa  AS [Jenis Analisa],
    COUNT(*)                      AS [Jml Ulangan],
    MAX(u.Tahapan_Ke)             AS [Tahapan],
    -- rata-rata hanya dari nilai numerik asli (buang sentinel -999999)
    CAST(ROUND(AVG(CASE WHEN u.Flag_String='Y' OR u.Hasil=-999999 THEN NULL ELSE u.Hasil END), 3) AS decimal(18,3)) AS [Nilai Rata2],
    CAST(ROUND(MIN(CASE WHEN u.Flag_String='Y' OR u.Hasil=-999999 THEN NULL ELSE u.Hasil END), 3) AS decimal(18,3)) AS [Nilai Min],
    CAST(ROUND(MAX(CASE WHEN u.Flag_String='Y' OR u.Hasil=-999999 THEN NULL ELSE u.Hasil END), 3) AS decimal(18,3)) AS [Nilai Max],
    MAX(CASE WHEN u.Flag_String='Y' THEN u.Nilai_Hasil_String END) AS [Hasil Teks],
    STUFF((SELECT DISTINCT ', ' + y.Id_User
           FROM N_EMI_LAB_Uji_Sampel y
           WHERE y.No_Po_Sampel = u.No_Po_Sampel
             AND y.Id_Jenis_Analisa = u.Id_Jenis_Analisa
             AND y.Id_User IS NOT NULL
           FOR XML PATH('')), 1, 2, '') AS [Penginput Analisa Ini],
    CASE WHEN SUM(CASE WHEN u.Flag_Layak='T' THEN 1 ELSE 0 END) > 0 THEN 'TIDAK LAYAK'
         WHEN SUM(CASE WHEN u.Flag_Layak='Y' THEN 1 ELSE 0 END) > 0 THEN 'LAYAK'
         ELSE '-' END AS [Status Analisa],
    CONVERT(varchar(10), MAX(u.Tanggal), 120) AS [Tgl Analisa]
FROM N_EMI_LAB_Uji_Sampel u
JOIN S s  ON s.No_Sampel = u.No_Po_Sampel
JOIN B b  ON b.Kode_Barang = s.Kode_Barang
LEFT JOIN N_EMI_LAB_Jenis_Analisa ja ON ja.id = u.Id_Jenis_Analisa
GROUP BY s.No_Po, s.No_Split_Po, s.No_Batch, s.No_Sampel, s.Kode_Barang, b.Nama,
         ja.Kode_Analisa, ja.Jenis_Analisa, u.No_Po_Sampel, u.Id_Jenis_Analisa
ORDER BY s.No_Po DESC, s.No_Sampel DESC, ja.Kode_Analisa
;
GO
