WITH B AS (
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
