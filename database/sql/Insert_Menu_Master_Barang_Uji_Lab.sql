DECLARE @Id_User VARCHAR(50) = 'ISI_USERID_DISINI';
DECLARE @Urutan INT = 99;

INSERT INTO N_EMI_LAB_Menus (Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Nama_Header, Sub_Header, Sub_Sub_Header)
VALUES ('001', 'Master Barang Uji Lab', 'ri-flask-line', '/barang-uji-master', 'LABORATORIUM', 'Master Data', NULL);

DECLARE @Id_Menu INT = CAST(SCOPE_IDENTITY() AS INT);

INSERT INTO N_EMI_LAB_Page_Access_2 (Kode_Perusahaan, Id_Menu, Id_User, Urutan_Menu)
VALUES ('001', @Id_Menu, @Id_User, @Urutan);

DECLARE @Id_Page_Access INT = CAST(SCOPE_IDENTITY() AS INT);

INSERT INTO N_EMI_LAB_Role_Menu_Access (Id_Page_Access, Id_Aksi, Flag_Diizinkan)
SELECT @Id_Page_Access, Id_Klasifikasi_Actions, 'Y'
FROM N_EMI_LAB_Klasifikasi_Aksi
WHERE Nama_Aksi IN ('VIEW', 'CREATE', 'EDIT', 'DELETE');

SELECT @Id_Menu AS Id_Menu_Baru, @Id_Page_Access AS Id_Page_Access_Baru;
