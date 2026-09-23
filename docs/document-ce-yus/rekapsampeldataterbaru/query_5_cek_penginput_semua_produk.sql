/* =====================================================================
   CEK HASIL ANALISA BERDASARKAN PENGINPUT (Id_User) + FILTER PRODUK CF
   Database : emi_db_real   |   READ-ONLY

   PERBAIKAN dari query awal:
   1. JOIN sebelumnya salah:
          ON N_EMI_LAB_Uji_Sampel.No_Po_Sampel = N_EMI_LAB_Uji_Sampel.No_Po_Sampel
      -> kedua sisi tabel yang SAMA, kondisi selalu TRUE = CROSS JOIN
         (212.824 x 15.461 baris). Yang benar menyambung ke tabel PO:
          ON p.No_Sampel = u.No_Po_Sampel
   2. "Kode_Barang LIKE '%CF%'" tidak akan pernah cocok: Kode_Barang
      berisi barcode angka (mis. 0745114273680), 0 dari 1.202 kode
      mengandung 'CF'. Penanda CF ada di NAMA barang -> b.Nama LIKE '%CF%'
   3. N_EMI_View_Barang di-dedupe: 1 barang muncul 15x (per stock owner),
      kalau tidak, tiap baris berlipat 15x.
   4. Hasil = -999999 adalah sentinel non-numerik; nilai asli di
      Nilai_Hasil_String.

   CATATAN DATA: dari 4 user yang diminta, hanya DEA RANIA & DEZARA yang
   punya data. DHEA dan ROBY terdaftar aktif di master user tapi BELUM
   pernah menginput hasil analisa.
   ===================================================================== */
;WITH B AS (
    SELECT Kode_Barang, MIN(Nama) AS Nama, MIN(Satuan) AS Satuan
    FROM N_EMI_View_Barang
    GROUP BY Kode_Barang
)
SELECT
    p.No_Po         AS [No PO],
    p.No_Split_Po   AS [Split PO],
    p.No_Batch      AS [Batch],
    p.No_Sampel     AS [No Sampel],
    p.Kode_Barang   AS [Kode Barang],
    b.Nama          AS [Nama Barang],
    b.Satuan        AS [Satuan],
    CONVERT(varchar(10), p.Tanggal, 120) AS [Tgl Registrasi],
    p.Id_User       AS [Registrasi Sampel],
    ja.Kode_Analisa   AS [Kode Analisa],
    ja.Jenis_Analisa  AS [Jenis Analisa],
    u.Id_User       AS [Penginput Hasil],
    CASE WHEN u.Flag_String = 'Y' OR u.Hasil = -999999
         THEN u.Nilai_Hasil_String
         ELSE CAST(CAST(ROUND(u.Hasil, 3) AS decimal(18,3)) AS varchar(30))
    END             AS [Nilai Hasil],
    u.Tahapan_Ke    AS [Tahapan],
    CASE u.Flag_Layak WHEN 'Y' THEN 'LAYAK' WHEN 'T' THEN 'TIDAK LAYAK' ELSE '-' END AS [Status Analisa],
    CONVERT(varchar(10), u.Tanggal, 120) AS [Tgl Analisa],
    ISNULL(v.Id_User, '-') AS [Yang Memvalidasi Hasil],
    CASE WHEN v.No_Sampel IS NULL THEN 'BELUM VALIDASI'
         WHEN v.Flag_Ok = 'Y' THEN 'OK' ELSE 'TIDAK OK' END AS [Hasil Sampel]
FROM N_EMI_LAB_Uji_Sampel u
JOIN N_EMI_LAB_PO_Sampel p
      ON p.No_Sampel = u.No_Po_Sampel          -- <== JOIN yang benar
JOIN B b
      ON b.Kode_Barang = p.Kode_Barang
LEFT JOIN N_EMI_LAB_Jenis_Analisa ja
      ON ja.id = u.Id_Jenis_Analisa
LEFT JOIN N_EMI_LAB_Hasil_Uji_Validasi_Final v
      ON v.No_Sampel = p.No_Sampel
WHERE u.Id_User IN ('DHEA', 'ROBY', 'DEA RANIA', 'DEZARA')
  -- AND b.Nama LIKE '%CF%'   <== filter CF DINONAKTIFKAN (produk CF tidak ada di data user ini)
ORDER BY p.Tanggal DESC, p.No_Sampel DESC, ja.Kode_Analisa
