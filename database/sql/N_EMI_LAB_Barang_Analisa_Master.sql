CREATE TABLE N_EMI_LAB_Barang_Analisa_Master (
    id INT IDENTITY(1,1) NOT NULL,
    Id_User VARCHAR(50) NOT NULL,
    Id_Jenis_Analisa INT NOT NULL,
    Id_Master_Mesin INT NULL,
    Kode_Role VARCHAR(20) NULL,
    Kode_Perusahaan VARCHAR(20) NULL DEFAULT '001',
    Flag_Aktif CHAR(1) NOT NULL DEFAULT 'Y',
    Tanggal DATE NULL,
    Jam TIME NULL,
    Id_User_Menginput VARCHAR(50) NULL,
    CONSTRAINT PK_N_EMI_LAB_Barang_Analisa_Master PRIMARY KEY (id)
);

CREATE UNIQUE INDEX UQ_Barang_Analisa_Master_Rule
    ON N_EMI_LAB_Barang_Analisa_Master (Id_User, Id_Jenis_Analisa, Id_Master_Mesin, Kode_Role);

CREATE NONCLUSTERED INDEX IX_Barang_Analisa_Master_User
    ON N_EMI_LAB_Barang_Analisa_Master (Id_User, Flag_Aktif)
    INCLUDE (Id_Jenis_Analisa, Id_Master_Mesin, Kode_Role);

CREATE TABLE N_EMI_LAB_Barang_Analisa_Sinkron (
    Kode_Barang VARCHAR(50) NOT NULL,
    Kode_Perusahaan VARCHAR(20) NULL DEFAULT '001',
    Tanggal DATE NULL,
    Jam TIME NULL,
    Id_User_Menginput VARCHAR(50) NULL,
    CONSTRAINT PK_N_EMI_LAB_Barang_Analisa_Sinkron PRIMARY KEY (Kode_Barang)
);
