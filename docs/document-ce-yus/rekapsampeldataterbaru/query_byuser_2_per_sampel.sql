/* 2. REKAP PER SAMPEL - 1 baris per No Sampel
      Cakupan: sampel yang pernah disentuh 4 user target. */
;WITH B AS (
    SELECT Kode_Barang, MIN(Nama) AS Nama, MIN(Satuan) AS Satuan
    FROM N_EMI_View_Barang GROUP BY Kode_Barang
),
T AS (
    SELECT DISTINCT No_Po_Sampel
    FROM N_EMI_LAB_Uji_Sampel
    WHERE Id_User IN ('DHEA','ROBY','DEA RANIA','DEZARA')
),
S AS (
    SELECT p.* FROM N_EMI_LAB_PO_Sampel p
    JOIN T ON T.No_Po_Sampel = p.No_Sampel
),
U AS (
    SELECT No_Po_Sampel,
           COUNT(*) AS Jml_Ulangan,
           COUNT(DISTINCT Id_Jenis_Analisa) AS Jml_Jenis,
           MAX(Tahapan_Ke) AS Tahapan
    FROM N_EMI_LAB_Uji_Sampel GROUP BY No_Po_Sampel
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
    ISNULL(U.Jml_Jenis,0) AS [Jml Jenis Analisa],
    ISNULL(STUFF((SELECT DISTINCT ', ' + ja2.Kode_Analisa
           FROM N_EMI_LAB_Uji_Sampel u2
           JOIN N_EMI_LAB_Jenis_Analisa ja2 ON ja2.id = u2.Id_Jenis_Analisa
           WHERE u2.No_Po_Sampel = s.No_Sampel
           FOR XML PATH('')),1,2,''),'-') AS [Daftar Jenis Analisa],
    ISNULL(STUFF((SELECT DISTINCT ' | ' + ja3.Kode_Analisa + ': ' + u3.Id_User
           FROM N_EMI_LAB_Uji_Sampel u3
           JOIN N_EMI_LAB_Jenis_Analisa ja3 ON ja3.id = u3.Id_Jenis_Analisa
           WHERE u3.No_Po_Sampel = s.No_Sampel AND u3.Id_User IS NOT NULL
           FOR XML PATH('')),1,3,''),'-') AS [Analisa : Penginput],
    -- khusus 4 user target
    ISNULL(STUFF((SELECT DISTINCT ', ' + u5.Id_User
           FROM N_EMI_LAB_Uji_Sampel u5
           WHERE u5.No_Po_Sampel = s.No_Sampel
             AND u5.Id_User IN ('DHEA','ROBY','DEA RANIA','DEZARA')
           FOR XML PATH('')),1,2,''),'-') AS [User Target di Sampel Ini],
    ISNULL(STUFF((SELECT DISTINCT ', ' + ja6.Kode_Analisa
           FROM N_EMI_LAB_Uji_Sampel u6
           JOIN N_EMI_LAB_Jenis_Analisa ja6 ON ja6.id = u6.Id_Jenis_Analisa
           WHERE u6.No_Po_Sampel = s.No_Sampel
             AND u6.Id_User IN ('DHEA','ROBY','DEA RANIA','DEZARA')
           FOR XML PATH('')),1,2,''),'-') AS [Analisa oleh User Target],
    ISNULL(STUFF((SELECT DISTINCT ', ' + u4.Id_User
           FROM N_EMI_LAB_Uji_Sampel u4
           WHERE u4.No_Po_Sampel = s.No_Sampel AND u4.Id_User IS NOT NULL
           FOR XML PATH('')),1,2,''),'-') AS [Semua Penginput],
    ISNULL(v.Id_User,'-') AS [Yang Memvalidasi Hasil],
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
