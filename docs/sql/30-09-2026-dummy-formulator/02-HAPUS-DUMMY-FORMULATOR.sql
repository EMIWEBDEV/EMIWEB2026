-- ============================================================================
-- 02-HAPUS-DUMMY-FORMULATOR.sql
-- Tanggal : 30-09-2026
-- Modul   : FORMULATOR - hapus data dummy dari 01-DUMMY-FORMULATOR.sql
-- Database: HANYA emi_tm_demo.
--
-- Yang dihapus: seluruh data transaksi sampel FT0926-0001..0003 (registrasi,
-- hasil uji, foto, validasi, pra-finalisasi/finalisasi bila sudah dicoba,
-- log aksi). Sampel yang Keterangannya tidak diawali "DATA DUMMY" tidak
-- disentuh: skrip berhenti.
--
-- Master mikrobiologi FLM (TRMAC/TRMYM/TRMEC/TRMSL) beserta rumus, binding,
-- standar, hak konten, dan barang analisanya ikut dihapus hanya bila
-- @Hapus_Master = 1 DAN analisanya tidak dipakai data uji lain. Barang
-- analisa BRG2410001 x AUTOCLAVE untuk analisa lain (Look View, Trial
-- Protein/Ash/Moisture, Palatabilitas) tetap, karena bisa jadi sudah dipakai.
-- File foto di GCS tidak dihapus (milik berkas lab).
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;

IF DB_NAME() <> N'emi_tm_demo'
    THROW 50001, N'Skrip dummy formulator hanya untuk database emi_tm_demo. Dibatalkan.', 1;

DECLARE @Hapus_Master bit = 0;   -- ubah ke 1 untuk ikut menghapus master mikro FLM

DECLARE
    @No_Po       varchar(30) = 'PRT0426-00004',
    @No_Split_Po varchar(30) = 'PRT0426-00004-1';

IF EXISTS (SELECT 1 FROM N_LIMS_PO_Sampel
           WHERE No_Sampel IN ('FT0926-0001', 'FT0926-0002', 'FT0926-0003')
             AND ISNULL(CAST(Keterangan AS varchar(max)), '') NOT LIKE 'DATA DUMMY%')
    THROW 50004, N'Nomor FT0926-0001..0003 dipakai sampel asli. Tidak ada yang dihapus.', 1;

BEGIN TRANSACTION;

DECLARE @Dummy TABLE (No_Sampel varchar(30) PRIMARY KEY);
INSERT INTO @Dummy VALUES ('FT0926-0001'), ('FT0926-0002'), ('FT0926-0003');

DELETE d FROM N_EMI_LAB_Log_Aksi_Detail d
JOIN N_EMI_LAB_Log_Aksi l ON l.Id_Log_Aksi = d.Id_Log_Aksi
WHERE l.No_Sampel IN (SELECT No_Sampel FROM @Dummy);
DELETE FROM N_EMI_LAB_Log_Aksi                           WHERE No_Sampel    IN (SELECT No_Sampel FROM @Dummy);
DELETE FROM N_EMI_LIMS_Hasil_Uji_Validasi_Final          WHERE No_Sampel    IN (SELECT No_Sampel FROM @Dummy);
DELETE FROM N_EMI_LIMS_Hasil_Uji_Validasi_Detail_Final   WHERE No_Sampel    IN (SELECT No_Sampel FROM @Dummy);
DELETE FROM N_EMI_LIMS_Uji_Pra_Final                     WHERE No_Sampel    IN (SELECT No_Sampel FROM @Dummy);
DELETE FROM N_EMI_LIMS_Uji_Sampel_Keterangan_Status      WHERE No_Sampel    IN (SELECT No_Sampel FROM @Dummy);
DELETE FROM N_EMI_LIMS_Uji_Sampel_Resampling_Log         WHERE No_Po_Sampel IN (SELECT No_Sampel FROM @Dummy);
DELETE FROM N_EMI_LIMS_Berkas_Uji_Lab                    WHERE No_Sampel    IN (SELECT No_Sampel FROM @Dummy);
DELETE FROM N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail WHERE No_Po_Sampel IN (SELECT No_Sampel FROM @Dummy);
DELETE FROM N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail  WHERE No_Po_Sampel IN (SELECT No_Sampel FROM @Dummy);
DELETE FROM N_EMI_LIMS_Activity_Uji_Sampel               WHERE No_Po_Sampel IN (SELECT No_Sampel FROM @Dummy);
DELETE FROM N_EMI_LIMS_Uji_Sampel_Detail
WHERE No_Faktur_Uji_Sample IN (SELECT No_Faktur FROM N_EMI_LIMS_Uji_Sampel WHERE No_Po_Sampel IN (SELECT No_Sampel FROM @Dummy));
DELETE FROM N_EMI_LIMS_Uji_Sampel                        WHERE No_Po_Sampel IN (SELECT No_Sampel FROM @Dummy);
DELETE FROM N_LIMS_PO_Sampel_Multi_QrCode                WHERE No_Po_Sampel IN (SELECT No_Sampel FROM @Dummy);
DELETE a FROM N_EMI_LIMS_Activity_Produksi_Sampel a
WHERE a.No_Po = @No_Po AND a.No_Split_Po = @No_Split_Po AND a.No_Batch IN (2, 3, 4)
  AND NOT EXISTS (SELECT 1 FROM N_LIMS_PO_Sampel p
                  WHERE p.No_Split_Po = a.No_Split_Po AND p.No_Batch = a.No_Batch
                    AND p.No_Sampel NOT IN (SELECT No_Sampel FROM @Dummy));
DELETE FROM N_LIMS_PO_Sampel                             WHERE No_Sampel    IN (SELECT No_Sampel FROM @Dummy);

IF @Hapus_Master = 1
BEGIN
    DECLARE @Mikro TABLE (Id int PRIMARY KEY);
    INSERT INTO @Mikro (Id)
    SELECT j.id FROM N_EMI_LAB_Jenis_Analisa j
    WHERE j.Kode_Role = 'FLM' AND j.Kode_Analisa IN ('TRMAC', 'TRMYM', 'TRMEC', 'TRMSL')
      AND NOT EXISTS (SELECT 1 FROM N_EMI_LIMS_Uji_Sampel u WHERE u.Id_Jenis_Analisa = j.id)
      AND NOT EXISTS (SELECT 1 FROM N_EMI_LAB_Uji_Sampel u WHERE u.Id_Jenis_Analisa = j.id);

    DELETE FROM N_EMI_LAB_Barang_Analisa                  WHERE Id_Jenis_Analisa IN (SELECT Id FROM @Mikro);
    DELETE FROM N_EMI_LAB_Role_Konten_Access              WHERE Id_Jenis_Analisa IN (SELECT Id FROM @Mikro);
    DELETE FROM N_EMI_LAB_Standar_Rentang_Non_Perhitungan WHERE Id_Jenis_Analisa IN (SELECT Id FROM @Mikro);
    DELETE FROM N_EMI_LAB_Standar_Rentang                 WHERE Id_Jenis_Analisa IN (SELECT Id FROM @Mikro);
    DELETE FROM N_EMI_LAB_Binding_Jenis_Analisa           WHERE Id_Jenis_Analisa IN (SELECT Id FROM @Mikro);
    DELETE FROM N_EMI_LAB_Perhitungan                     WHERE Id_Jenis_Analisa IN (SELECT Id FROM @Mikro);
    DELETE FROM N_EMI_LAB_Jenis_Analisa                   WHERE id               IN (SELECT Id FROM @Mikro);
END;

COMMIT TRANSACTION;

SELECT
    (SELECT COUNT(*) FROM N_LIMS_PO_Sampel WHERE No_Sampel IN ('FT0926-0001', 'FT0926-0002', 'FT0926-0003')) AS Sisa_Sampel,
    (SELECT COUNT(*) FROM N_EMI_LAB_Jenis_Analisa WHERE Kode_Role = 'FLM' AND Kode_Analisa IN ('TRMAC', 'TRMYM', 'TRMEC', 'TRMSL')) AS Sisa_Master_Mikro;
