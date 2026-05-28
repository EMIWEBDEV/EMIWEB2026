-- ============================================================
-- DDL: N_EMI_LAB_Log_Aksi_Detail
-- Menyimpan detail per-analisa untuk setiap entri audit log.
-- Satu header di N_EMI_LAB_Log_Aksi bisa punya banyak detail
-- (misal: bulk validasi 10 analisa => 1 header + 10 detail).
-- ============================================================

IF NOT EXISTS (
    SELECT 1 FROM sys.tables WHERE name = 'N_EMI_LAB_Log_Aksi_Detail'
)
BEGIN
    CREATE TABLE N_EMI_LAB_Log_Aksi_Detail (
        Id_Log_Aksi_Detail  INT            IDENTITY(1,1) NOT NULL,
        Id_Log_Aksi         INT            NOT NULL,
        Id_Jenis_Analisa    INT            NOT NULL,
        Nama_Jenis_Analisa  NVARCHAR(255)  NULL,
        Flag_Layak          CHAR(1)        NULL,

        CONSTRAINT PK_N_EMI_LAB_Log_Aksi_Detail
            PRIMARY KEY CLUSTERED (Id_Log_Aksi_Detail),

        CONSTRAINT FK_LogAksiDetail_LogAksi
            FOREIGN KEY (Id_Log_Aksi)
            REFERENCES N_EMI_LAB_Log_Aksi (Id_Log_Aksi)
            ON DELETE CASCADE
    );

    CREATE NONCLUSTERED INDEX IX_LogAksiDetail_IdLogAksi
        ON N_EMI_LAB_Log_Aksi_Detail (Id_Log_Aksi);

    PRINT 'Table N_EMI_LAB_Log_Aksi_Detail berhasil dibuat.';
END
ELSE
BEGIN
    PRINT 'Table N_EMI_LAB_Log_Aksi_Detail sudah ada, dilewati.';
END


-- ============================================================
-- Tambah kolom timestamp per-analisa (jika belum ada)
-- Dibutuhkan agar setiap detail menyimpan waktu validasi masing-masing
-- ============================================================
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_EMI_LAB_Log_Aksi_Detail') AND name = 'Tanggal'
)
BEGIN
    ALTER TABLE N_EMI_LAB_Log_Aksi_Detail
        ADD Tanggal NVARCHAR(10)  NULL,
            Jam     NVARCHAR(8)   NULL,
            Id_User NVARCHAR(50)  NULL;
    PRINT 'Kolom Tanggal, Jam, Id_User berhasil ditambahkan ke N_EMI_LAB_Log_Aksi_Detail.';
END
ELSE
BEGIN
    PRINT 'Kolom Tanggal sudah ada, ALTER TABLE dilewati.';
END

-- ============================================================
-- Verifikasi struktur tabel
-- ============================================================
SELECT
    c.name          AS Kolom,
    t.name          AS Tipe,
    c.max_length    AS MaxLength,
    c.is_nullable   AS Nullable,
    c.is_identity   AS IsIdentity
FROM sys.columns c
JOIN sys.types   t ON c.user_type_id = t.user_type_id
WHERE c.object_id = OBJECT_ID('N_EMI_LAB_Log_Aksi_Detail')
ORDER BY c.column_id;
