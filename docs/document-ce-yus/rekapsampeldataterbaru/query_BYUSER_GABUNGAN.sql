/* =====================================================================
   REKAPITULASI LIMS - NEW PRODUCT BERDASARKAN USER
   Database : emi_db_real   |   READ-ONLY (SELECT saja)

   FILTER : u.Id_User IN ('DHEA','ROBY','DEA RANIA','DEZARA')
            (TANPA filter kode barang / CF)

   4 result set:
     1. Rekap per PO       -> 31 baris
     2. Rekap per Sampel   -> 32 baris
     3. Detail Analisa     -> 447 baris (semua analisa pd sampel tsb)
     4. Gabungan Flat      -> 166 baris (hanya analisa 4 user target)

   CAKUPAN: "sampel yang pernah disentuh 4 user tersebut". Pada sampel
   yang sama ada juga analisa dari tim lain (kimia/mikro), sehingga:
     - Sheet 1/2/3 menampilkan KONTEKS PENUH sampel, dengan penanda
       [Dikerjakan User Target] / [Analisa oleh User Target].
     - Sheet 4 (Flat) HANYA berisi analisa milik 4 user tersebut.

   CATATAN DATA:
   - Hanya DEA RANIA (658 pembacaan) & DEZARA (644) yang punya data.
     DHEA dan ROBY terdaftar aktif di master user tapi BELUM pernah
     menginput hasil analisa.
   - 4 user ini menangani uji PANEL/PALATABILITAS: PREFERENSI PERTAMA,
     TINGKAT KONSUMSI, RESPONDEN MEMAKAN, DURASI, SAMPEL HABIS,
     P-MARKETING-LV-*.
   - View barang di-dedupe (1 barang muncul 15x per stock owner).
   - Hasil = -999999 = sentinel non-numerik; nilai asli di
     Nilai_Hasil_String -> dibuang dari rata-rata/min/max.
   ===================================================================== */

SELECT '=== 1. REKAP PER PO ===' AS [BAGIAN];
GO

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
;
GO

SELECT '=== 2. REKAP PER SAMPEL ===' AS [BAGIAN];
GO

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
;
GO

SELECT '=== 3. DETAIL ANALISA ===' AS [BAGIAN];
GO

/* 3. DETAIL ANALISA - 1 baris per jenis analisa per sampel
      Cakupan: sampel yang pernah disentuh 4 user target.
      Kolom [Dikerjakan User Target] menandai analisa milik 4 user itu. */
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
    COUNT(*)              AS [Jml Ulangan],
    MAX(u.Tahapan_Ke)     AS [Tahapan],
    CAST(ROUND(AVG(CASE WHEN u.Flag_String='Y' OR u.Hasil=-999999 THEN NULL ELSE u.Hasil END),3) AS decimal(18,3)) AS [Nilai Rata2],
    CAST(ROUND(MIN(CASE WHEN u.Flag_String='Y' OR u.Hasil=-999999 THEN NULL ELSE u.Hasil END),3) AS decimal(18,3)) AS [Nilai Min],
    CAST(ROUND(MAX(CASE WHEN u.Flag_String='Y' OR u.Hasil=-999999 THEN NULL ELSE u.Hasil END),3) AS decimal(18,3)) AS [Nilai Max],
    MAX(CASE WHEN u.Flag_String='Y' THEN u.Nilai_Hasil_String END) AS [Hasil Teks],
    ISNULL(STUFF((SELECT DISTINCT ', ' + y.Id_User
           FROM N_EMI_LAB_Uji_Sampel y
           WHERE y.No_Po_Sampel = u.No_Po_Sampel
             AND y.Id_Jenis_Analisa = u.Id_Jenis_Analisa
             AND y.Id_User IS NOT NULL
           FOR XML PATH('')),1,2,''),'-') AS [Penginput Analisa Ini],
    CASE WHEN SUM(CASE WHEN u.Id_User IN ('DHEA','ROBY','DEA RANIA','DEZARA') THEN 1 ELSE 0 END) > 0
         THEN 'YA' ELSE 'TIDAK' END AS [Dikerjakan User Target],
    CASE WHEN SUM(CASE WHEN u.Flag_Layak='T' THEN 1 ELSE 0 END) > 0 THEN 'TIDAK LAYAK'
         WHEN SUM(CASE WHEN u.Flag_Layak='Y' THEN 1 ELSE 0 END) > 0 THEN 'LAYAK'
         ELSE '-' END AS [Status Analisa],
    CONVERT(varchar(10), MAX(u.Tanggal), 120) AS [Tgl Analisa]
FROM N_EMI_LAB_Uji_Sampel u
JOIN S s ON s.No_Sampel = u.No_Po_Sampel
JOIN B b ON b.Kode_Barang = s.Kode_Barang
LEFT JOIN N_EMI_LAB_Jenis_Analisa ja ON ja.id = u.Id_Jenis_Analisa
GROUP BY s.No_Po, s.No_Split_Po, s.No_Batch, s.No_Sampel, s.Kode_Barang, b.Nama,
         ja.Kode_Analisa, ja.Jenis_Analisa, u.No_Po_Sampel, u.Id_Jenis_Analisa
ORDER BY s.No_Po DESC, s.No_Sampel DESC, ja.Kode_Analisa
;
GO

SELECT '=== 4. GABUNGAN FLAT ===' AS [BAGIAN];
GO

/* 4. GABUNGAN FLAT - 1 baris per jenis analisa per sampel, konteks penuh.
      HANYA analisa milik 4 user target (1.302 pembacaan -> 146 baris). */
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
PO AS (SELECT No_Po, COUNT(*) AS Jml_Sampel_Di_PO FROM S GROUP BY No_Po)
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
    ja.Kode_Analisa   AS [Kode Analisa],
    ja.Jenis_Analisa  AS [Jenis Analisa],
    u.Id_User      AS [Penginput Hasil],
    COUNT(*)       AS [Jml Ulangan],
    MAX(u.Tahapan_Ke) AS [Tahapan],
    CAST(ROUND(AVG(CASE WHEN u.Flag_String='Y' OR u.Hasil=-999999 THEN NULL ELSE u.Hasil END),3) AS decimal(18,3)) AS [Nilai Rata2],
    CAST(ROUND(MIN(CASE WHEN u.Flag_String='Y' OR u.Hasil=-999999 THEN NULL ELSE u.Hasil END),3) AS decimal(18,3)) AS [Nilai Min],
    CAST(ROUND(MAX(CASE WHEN u.Flag_String='Y' OR u.Hasil=-999999 THEN NULL ELSE u.Hasil END),3) AS decimal(18,3)) AS [Nilai Max],
    MAX(CASE WHEN u.Flag_String='Y' THEN u.Nilai_Hasil_String END) AS [Hasil Teks],
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
FROM N_EMI_LAB_Uji_Sampel u
JOIN S s   ON s.No_Sampel = u.No_Po_Sampel
JOIN B b   ON b.Kode_Barang = s.Kode_Barang
JOIN PO po ON po.No_Po = s.No_Po
LEFT JOIN N_EMI_LAB_Jenis_Analisa ja ON ja.id = u.Id_Jenis_Analisa
LEFT JOIN N_EMI_LAB_Hasil_Uji_Validasi_Final v ON v.No_Sampel = s.No_Sampel
WHERE u.Id_User IN ('DHEA','ROBY','DEA RANIA','DEZARA')
GROUP BY s.No_Po, po.Jml_Sampel_Di_PO, s.No_Split_Po, s.No_Batch, s.No_Sampel,
         s.Kode_Barang, b.Nama, b.Satuan, s.Tanggal, s.Id_User, s.Flag_Selesai,
         ja.Kode_Analisa, ja.Jenis_Analisa, u.Id_User,
         v.Id_User, v.Tanggal, v.No_Sampel, v.Flag_Ok, v.Flag_FG
ORDER BY s.No_Po DESC, s.No_Sampel DESC, ja.Kode_Analisa
;
GO
