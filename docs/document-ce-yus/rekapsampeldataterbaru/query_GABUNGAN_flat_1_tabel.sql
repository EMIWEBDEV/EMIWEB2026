/* =====================================================================
   REKAP LIMS GABUNGAN - SATU TABEL UTUH (1 result set)
   Database : emi_db_real   |   READ-ONLY

   1 baris = 1 jenis analisa pada 1 sampel, lengkap dengan konteks
   PO + barang + penginput + validator. Cocok untuk Pivot Table.
   ===================================================================== */
;WITH B AS (
    SELECT Kode_Barang, MIN(Nama) AS Nama, MIN(Satuan) AS Satuan
    FROM N_EMI_View_Barang GROUP BY Kode_Barang
),
S AS (
    SELECT p.* FROM N_EMI_LAB_PO_Sampel p
    JOIN B b ON b.Kode_Barang = p.Kode_Barang
    WHERE b.Nama LIKE '%CF%'
),
PO AS (
    SELECT No_Po, COUNT(*) AS Jml_Sampel_Di_PO FROM S GROUP BY No_Po
)
SELECT
    s.No_Po        AS [No PO],
    po.Jml_Sampel_Di_PO AS [Jml Sampel di PO],
    s.No_Split_Po  AS [Split PO],
    s.No_Batch     AS [Batch],
    s.No_Sampel    AS [No Sampel],
    s.Kode_Barang  AS [Kode Barang],
    b.Nama         AS [Nama Barang],
    b.Satuan       AS [Satuan],
    CONVERT(varchar(10), s.Tanggal, 120) AS [Tgl Registrasi],
    s.Id_User      AS [Registrasi Sampel],
    ISNULL(ja.Kode_Analisa,'-')  AS [Kode Analisa],
    ISNULL(ja.Jenis_Analisa,'-') AS [Jenis Analisa],
    COUNT(u.Id_Jenis_Analisa)    AS [Jml Ulangan],
    MAX(u.Tahapan_Ke)            AS [Tahapan],
    CAST(ROUND(AVG(CASE WHEN u.Flag_String='Y' OR u.Hasil=-999999 THEN NULL ELSE u.Hasil END),3) AS decimal(18,3)) AS [Nilai Rata2],
    CAST(ROUND(MIN(CASE WHEN u.Flag_String='Y' OR u.Hasil=-999999 THEN NULL ELSE u.Hasil END),3) AS decimal(18,3)) AS [Nilai Min],
    CAST(ROUND(MAX(CASE WHEN u.Flag_String='Y' OR u.Hasil=-999999 THEN NULL ELSE u.Hasil END),3) AS decimal(18,3)) AS [Nilai Max],
    MAX(CASE WHEN u.Flag_String='Y' THEN u.Nilai_Hasil_String END) AS [Hasil Teks],
    ISNULL(STUFF((SELECT DISTINCT ', ' + y.Id_User
           FROM N_EMI_LAB_Uji_Sampel y
           WHERE y.No_Po_Sampel = s.No_Sampel
             AND y.Id_Jenis_Analisa = u.Id_Jenis_Analisa
             AND y.Id_User IS NOT NULL
           FOR XML PATH('')),1,2,''),'-') AS [Penginput Analisa Ini],
    CASE WHEN SUM(CASE WHEN u.Flag_Layak='T' THEN 1 ELSE 0 END) > 0 THEN 'TIDAK LAYAK'
         WHEN SUM(CASE WHEN u.Flag_Layak='Y' THEN 1 ELSE 0 END) > 0 THEN 'LAYAK'
         ELSE '-' END AS [Status Analisa],
    CONVERT(varchar(10), MAX(u.Tanggal), 120) AS [Tgl Analisa],
    ISNULL(v.Id_User,'-') AS [Yang Memvalidasi Hasil],
    CONVERT(varchar(10), v.Tanggal, 120) AS [Tgl Validasi],
    CASE WHEN v.No_Sampel IS NULL THEN 'BELUM VALIDASI'
         WHEN v.Flag_Ok='Y' THEN 'OK' ELSE 'TIDAK OK' END AS [Hasil Sampel],
    CASE WHEN v.Flag_FG='Y' THEN 'FG' ELSE '' END AS [Status FG],
    CASE WHEN s.Flag_Selesai='Y' THEN 'SELESAI' ELSE 'BELUM SELESAI' END AS [Status Sampel]
FROM S s
JOIN B b   ON b.Kode_Barang = s.Kode_Barang
JOIN PO po ON po.No_Po = s.No_Po
LEFT JOIN N_EMI_LAB_Uji_Sampel u ON u.No_Po_Sampel = s.No_Sampel
LEFT JOIN N_EMI_LAB_Jenis_Analisa ja ON ja.id = u.Id_Jenis_Analisa
LEFT JOIN N_EMI_LAB_Hasil_Uji_Validasi_Final v ON v.No_Sampel = s.No_Sampel
GROUP BY s.No_Po, po.Jml_Sampel_Di_PO, s.No_Split_Po, s.No_Batch, s.No_Sampel,
         s.Kode_Barang, b.Nama, b.Satuan, s.Tanggal, s.Id_User, s.Flag_Selesai,
         ja.Kode_Analisa, ja.Jenis_Analisa, u.Id_Jenis_Analisa,
         v.Id_User, v.Tanggal, v.No_Sampel, v.Flag_Ok, v.Flag_FG
ORDER BY s.No_Po DESC, s.No_Sampel DESC, ja.Kode_Analisa
