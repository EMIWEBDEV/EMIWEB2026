/* 1. REKAP PER PO - 1 baris per No PO
      Cakupan: PO yang punya sampel dikerjakan 4 user target. */
;WITH B AS (
    SELECT Kode_Barang, MIN(Nama) AS Nama FROM N_EMI_View_Barang GROUP BY Kode_Barang
),
T AS (
    SELECT DISTINCT No_Po_Sampel
    FROM N_EMI_LAB_Uji_Sampel
    WHERE Id_User IN ('DHEA','ROBY','DEA RANIA','DEZARA')
),
S AS (
    SELECT p.*, b.Nama FROM N_EMI_LAB_PO_Sampel p
    JOIN T ON T.No_Po_Sampel = p.No_Sampel
    JOIN B b ON b.Kode_Barang = p.Kode_Barang
)
SELECT
    s.No_Po            AS [No PO],
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
    ISNULL(STUFF((SELECT DISTINCT ', ' + u5.Id_User
           FROM N_EMI_LAB_Uji_Sampel u5
           JOIN S s9 ON s9.No_Sampel = u5.No_Po_Sampel
           WHERE s9.No_Po = s.No_Po
             AND u5.Id_User IN ('DHEA','ROBY','DEA RANIA','DEZARA')
           FOR XML PATH('')),1,2,''),'-') AS [User Target di PO Ini],
    ISNULL(STUFF((SELECT DISTINCT ', ' + ja6.Kode_Analisa
           FROM N_EMI_LAB_Uji_Sampel u6
           JOIN S s10 ON s10.No_Sampel = u6.No_Po_Sampel
           JOIN N_EMI_LAB_Jenis_Analisa ja6 ON ja6.id = u6.Id_Jenis_Analisa
           WHERE s10.No_Po = s.No_Po
             AND u6.Id_User IN ('DHEA','ROBY','DEA RANIA','DEZARA')
           FOR XML PATH('')),1,2,''),'-') AS [Analisa oleh User Target],
    ISNULL(STUFF((SELECT DISTINCT ', ' + ja.Kode_Analisa
           FROM N_EMI_LAB_Uji_Sampel u
           JOIN S s2 ON s2.No_Sampel = u.No_Po_Sampel
           JOIN N_EMI_LAB_Jenis_Analisa ja ON ja.id = u.Id_Jenis_Analisa
           WHERE s2.No_Po = s.No_Po
           FOR XML PATH('')),1,2,''),'-') AS [Semua Jenis Analisa di PO],
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
      WHERE s5.No_Po = s.No_Po AND v.Flag_Ok='Y')  AS [Sampel OK],
    (SELECT COUNT(*) FROM N_EMI_LAB_Hasil_Uji_Validasi_Final v
      JOIN S s6 ON s6.No_Sampel = v.No_Sampel
      WHERE s6.No_Po = s.No_Po AND v.Flag_Ok<>'Y') AS [Sampel Tidak OK],
    COUNT(*) - (SELECT COUNT(*) FROM N_EMI_LAB_Hasil_Uji_Validasi_Final v
      JOIN S s7 ON s7.No_Sampel = v.No_Sampel
      WHERE s7.No_Po = s.No_Po)                    AS [Belum Validasi],
    CASE WHEN COUNT(*) = (SELECT COUNT(*) FROM N_EMI_LAB_Hasil_Uji_Validasi_Final v
                           JOIN S s8 ON s8.No_Sampel = v.No_Sampel WHERE s8.No_Po = s.No_Po)
         THEN 'SELESAI VALIDASI' ELSE 'PROSES' END AS [Status PO]
FROM S s
GROUP BY s.No_Po
ORDER BY s.No_Po DESC
