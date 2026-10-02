-- ============================================================================
-- 03-HAPUS-DUMMY-LAPORAN-TRIAL.sql
-- Tanggal : 02-10-2026
-- Modul   : hapus data dummy dari 01-DUMMY-TRIAL-PRODUKSI.sql dan 02-DUMMY-FORMULATOR.sql
-- Database: HANYA emi_tm_demo.
--
-- Yang dihapus hanya sampel ber-Keterangan "DATA DUMMY LAPORAN" pada split
-- PRD0626-00002-1 (trial produksi) dan PRT0926-00001-1 (formulator), beserta
-- seluruh jejaknya. Master (analisa FLM TRIAL SALT / Durasi Makan, standar,
-- hak konten, barang analisa) dibiarkan. File foto di GCS tidak disentuh.
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;

IF DB_NAME() <> N'emi_tm_demo'
    THROW 50001, N'Skrip dummy hanya untuk database emi_tm_demo. Dibatalkan.', 1;

DECLARE @Penanda varchar(40) = 'DATA DUMMY LAPORAN';

BEGIN TRANSACTION;

-- ---------------------------------------------------------------- trial produksi
DECLARE @Lab TABLE (No_Sampel varchar(30) PRIMARY KEY, No_Split_Po varchar(30), No_Batch int);
INSERT INTO @Lab
SELECT No_Sampel, No_Split_Po, No_Batch FROM N_EMI_LAB_PO_Sampel
WHERE No_Split_Po = 'PRD0626-00002-1' AND CAST(Keterangan AS varchar(max)) LIKE @Penanda + '%';

IF OBJECT_ID('dbo.N_EMI_LAB_Verifikasi_Header') IS NOT NULL
BEGIN
    DELETE r FROM N_EMI_LAB_Verifikasi_Riwayat r JOIN N_EMI_LAB_Verifikasi_Header h ON h.Id_Verifikasi = r.Id_Verifikasi WHERE h.No_Sampel IN (SELECT No_Sampel FROM @Lab);
    DELETE d FROM N_EMI_LAB_Verifikasi_Detail d JOIN N_EMI_LAB_Verifikasi_Header h ON h.Id_Verifikasi = d.Id_Verifikasi WHERE h.No_Sampel IN (SELECT No_Sampel FROM @Lab);
    DELETE FROM N_EMI_LAB_Verifikasi_Header WHERE No_Sampel IN (SELECT No_Sampel FROM @Lab);
END;
DELETE d FROM N_EMI_LAB_Log_Aksi_Detail d JOIN N_EMI_LAB_Log_Aksi l ON l.Id_Log_Aksi = d.Id_Log_Aksi WHERE l.No_Sampel IN (SELECT No_Sampel FROM @Lab);
DELETE FROM N_EMI_LAB_Log_Aksi                        WHERE No_Sampel IN (SELECT No_Sampel FROM @Lab);
DELETE FROM N_EMI_LAB_Hasil_Uji_Approval_Aktivitas    WHERE No_Sampel IN (SELECT No_Sampel FROM @Lab);
DELETE FROM N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final WHERE No_Sampel IN (SELECT No_Sampel FROM @Lab);
DELETE FROM N_EMI_LAB_Hasil_Uji_Validasi_Final        WHERE No_Sampel IN (SELECT No_Sampel FROM @Lab);
DELETE FROM N_EMI_LAB_Uji_Sampel_Resampling_Log       WHERE No_Po_Sampel IN (SELECT No_Sampel FROM @Lab);
DELETE FROM N_EMI_LAB_Berkas_Uji_Lab                  WHERE No_Sampel IN (SELECT No_Sampel FROM @Lab);
DELETE FROM N_EMI_LAB_Activity_Uji_Sampel             WHERE No_Po_Sampel IN (SELECT No_Sampel FROM @Lab);
DELETE FROM N_EMI_LAB_Uji_Sampel_Detail
WHERE No_Faktur_Uji_Sample IN (SELECT No_Faktur FROM N_EMI_LAB_Uji_Sampel WHERE No_Po_Sampel IN (SELECT No_Sampel FROM @Lab));
DELETE FROM N_EMI_LAB_Uji_Sampel                      WHERE No_Po_Sampel IN (SELECT No_Sampel FROM @Lab);
DELETE p FROM N_EMI_LAB_Palatabilitas_Pembanding p
JOIN N_EMI_LAB_Palatabilitas_Session s ON s.Id_Session = p.Id_Session WHERE s.No_Po_Sampel IN (SELECT No_Sampel FROM @Lab);
DELETE FROM N_EMI_LAB_Palatabilitas_Sementara         WHERE No_Po_Sampel IN (SELECT No_Sampel FROM @Lab);
DELETE FROM N_EMI_LAB_Palatabilitas_Session           WHERE No_Po_Sampel IN (SELECT No_Sampel FROM @Lab);
DELETE FROM N_EMI_LAB_PO_Sampel_Multi_QrCode          WHERE No_Po_Sampel IN (SELECT No_Sampel FROM @Lab);
DELETE a FROM N_EMI_LAB_Activity_Produksi_Sampel a JOIN @Lab l ON l.No_Split_Po = a.No_Split_Po AND l.No_Batch = a.No_Batch
WHERE NOT EXISTS (SELECT 1 FROM N_EMI_LAB_PO_Sampel p WHERE p.No_Split_Po = a.No_Split_Po AND p.No_Batch = a.No_Batch
                    AND p.No_Sampel NOT IN (SELECT No_Sampel FROM @Lab));
DELETE FROM N_EMI_LAB_PO_Sampel                       WHERE No_Sampel IN (SELECT No_Sampel FROM @Lab);

-- ---------------------------------------------------------------- formulator
DECLARE @Flm TABLE (No_Sampel varchar(30) PRIMARY KEY, No_Po varchar(30), No_Split_Po varchar(30), No_Batch int);
INSERT INTO @Flm
SELECT No_Sampel, No_Po, No_Split_Po, No_Batch FROM N_LIMS_PO_Sampel
WHERE No_Split_Po = 'PRT0926-00001-1' AND CAST(Keterangan AS varchar(max)) LIKE @Penanda + '%';

DELETE d FROM N_EMI_LAB_Log_Aksi_Detail d JOIN N_EMI_LAB_Log_Aksi l ON l.Id_Log_Aksi = d.Id_Log_Aksi WHERE l.No_Sampel IN (SELECT No_Sampel FROM @Flm);
DELETE FROM N_EMI_LAB_Log_Aksi                              WHERE No_Sampel IN (SELECT No_Sampel FROM @Flm);
DELETE FROM N_EMI_LIMS_Hasil_Uji_Validasi_Final             WHERE No_Sampel IN (SELECT No_Sampel FROM @Flm);
DELETE FROM N_EMI_LIMS_Hasil_Uji_Validasi_Detail_Final      WHERE No_Sampel IN (SELECT No_Sampel FROM @Flm);
DELETE FROM N_EMI_LIMS_Uji_Pra_Final                        WHERE No_Sampel IN (SELECT No_Sampel FROM @Flm);
DELETE k FROM N_EMI_LIMS_Uji_Sampel_Keterangan_Status k JOIN @Flm f ON k.No_Sampel = f.No_Sampel OR k.No_Sampel LIKE f.No_Sampel + '-%';
DELETE FROM N_EMI_LIMS_Uji_Sampel_Resampling_Log            WHERE No_Po_Sampel IN (SELECT No_Sampel FROM @Flm);
DELETE FROM N_EMI_LIMS_Berkas_Uji_Lab                       WHERE No_Sampel IN (SELECT No_Sampel FROM @Flm);
DELETE FROM N_EMI_LIMS_Activity_Uji_Sampel_Parameter_Detail WHERE No_Po_Sampel IN (SELECT No_Sampel FROM @Flm);
DELETE FROM N_EMI_LIMS_Activity_Uji_Sampel_Hasil_Detail     WHERE No_Po_Sampel IN (SELECT No_Sampel FROM @Flm);
DELETE FROM N_EMI_LIMS_Activity_Uji_Sampel                  WHERE No_Po_Sampel IN (SELECT No_Sampel FROM @Flm);
DELETE FROM N_EMI_LIMS_Uji_Sampel_Detail
WHERE No_Faktur_Uji_Sample IN (SELECT No_Faktur FROM N_EMI_LIMS_Uji_Sampel WHERE No_Po_Sampel IN (SELECT No_Sampel FROM @Flm));
DELETE FROM N_EMI_LIMS_Uji_Sampel                           WHERE No_Po_Sampel IN (SELECT No_Sampel FROM @Flm);
DELETE FROM N_LIMS_PO_Sampel_Multi_QrCode                   WHERE No_Po_Sampel IN (SELECT No_Sampel FROM @Flm);
DELETE a FROM N_EMI_LIMS_Activity_Produksi_Sampel a JOIN @Flm f ON f.No_Po = a.No_Po AND f.No_Split_Po = a.No_Split_Po AND f.No_Batch = a.No_Batch
WHERE NOT EXISTS (SELECT 1 FROM N_LIMS_PO_Sampel p WHERE p.No_Split_Po = a.No_Split_Po AND p.No_Batch = a.No_Batch
                    AND p.No_Sampel NOT IN (SELECT No_Sampel FROM @Flm));
DELETE FROM N_LIMS_PO_Sampel                                WHERE No_Sampel IN (SELECT No_Sampel FROM @Flm);

COMMIT TRANSACTION;

SELECT (SELECT COUNT(*) FROM @Lab) AS Sampel_Trial_Produksi_Dihapus, (SELECT COUNT(*) FROM @Flm) AS Sampel_Formulator_Dihapus;
