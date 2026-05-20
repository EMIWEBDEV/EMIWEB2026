<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Alter N_EMI_LIMS_Klasifikasi_Aktivitas_Lab
        if (!Schema::hasColumn('N_EMI_LIMS_Klasifikasi_Aktivitas_Lab', 'Flag_Butuh_Pembanding')) {
            Schema::table('N_EMI_LIMS_Klasifikasi_Aktivitas_Lab', function (Blueprint $table) {
                $table->char('Flag_Butuh_Pembanding', 1)->nullable()->default('T');
            });
        }
        DB::table('N_EMI_LIMS_Klasifikasi_Aktivitas_Lab')
            ->where('Kode_Aktivitas_Lab', 'PLT')
            ->update(['Flag_Butuh_Pembanding' => 'Y']);

        // 2. Create N_EMI_LAB_Palatabilitas_Session
        if (!Schema::hasTable('N_EMI_LAB_Palatabilitas_Session')) {
            DB::statement("
                CREATE TABLE N_EMI_LAB_Palatabilitas_Session (
                    Id_Session             INT           NOT NULL IDENTITY(1,1),
                    Kode_Perusahaan        VARCHAR(3)    NULL,
                    No_Po_Sampel           VARCHAR(30)   NULL,
                    Kode_Aktivitas_Lab     VARCHAR(10)   NULL,
                    Status_Session         CHAR(1)       NULL,
                    Tanggal_Buat           DATETIME      NULL,
                    Jam_Buat               VARCHAR(8)    NULL,
                    Id_User_Buat           VARCHAR(30)   NULL,
                    Kode_Role              VARCHAR(50)   NULL,
                    Tanggal_Final          DATETIME      NULL,
                    Jam_Final              VARCHAR(8)    NULL,
                    Id_User_Final          VARCHAR(30)   NULL,
                    CONSTRAINT PK_Palatabilitas_Session PRIMARY KEY (Id_Session),
                    CONSTRAINT UQ_Palatabilitas_Session UNIQUE (No_Po_Sampel, Kode_Aktivitas_Lab)
                )
            ");
        }

        // 3. Create N_EMI_LAB_Palatabilitas_Pembanding
        if (!Schema::hasTable('N_EMI_LAB_Palatabilitas_Pembanding')) {
            DB::statement("
                CREATE TABLE N_EMI_LAB_Palatabilitas_Pembanding (
                    Id_Pembanding          INT           NOT NULL IDENTITY(1,1),
                    Id_Session             INT           NULL,
                    Kode_Perusahaan        VARCHAR(3)    NULL,
                    Urutan                 INT           NULL,
                    Kode_Barang_Pembanding VARCHAR(30)   NULL,
                    Nama_Pembanding        VARCHAR(255)  NULL,
                    Tanggal                DATETIME      NULL,
                    Jam                    VARCHAR(8)    NULL,
                    Id_User                VARCHAR(30)   NULL,
                    Kode_Role              VARCHAR(50)   NULL,
                    Flag_Aktif             CHAR(1)       DEFAULT 'Y',
                    CONSTRAINT PK_Palatabilitas_Pembanding PRIMARY KEY (Id_Pembanding)
                )
            ");
        }

        // 4. Create N_EMI_LAB_Palatabilitas_Sementara
        if (!Schema::hasTable('N_EMI_LAB_Palatabilitas_Sementara')) {
            DB::statement("
                CREATE TABLE N_EMI_LAB_Palatabilitas_Sementara (
                    No_Urut                INT           NOT NULL IDENTITY(1,1),
                    Id_Session             INT           NULL,
                    Id_Pembanding          INT           NULL,
                    Kode_Perusahaan        VARCHAR(3)    NULL,
                    No_Po_Sampel           VARCHAR(30)   NULL,
                    No_Fak_Sub_Po          VARCHAR(30)   NULL,
                    Id_Jenis_Analisa       INT           NULL,
                    Hasil                  FLOAT         NULL,
                    Flag_Perhitungan       CHAR(1)       NULL,
                    Flag_Multi_QrCode      CHAR(1)       NULL,
                    Nilai_Hasil_String     VARCHAR(225)  NULL,
                    Flag_String            CHAR(1)       NULL,
                    Flag_Foto              CHAR(1)       NULL,
                    Status                 CHAR(1)       NULL,
                    Tanggal                DATETIME      NULL,
                    Jam                    VARCHAR(8)    NULL,
                    Id_User                VARCHAR(30)   NULL,
                    Kode_Role              VARCHAR(50)   NULL,
                    CONSTRAINT PK_Palatabilitas_Sementara PRIMARY KEY (No_Urut)
                )
            ");
        }

        // 5. Alter N_EMI_LAB_Uji_Sampel - tambah Id_Session dan Id_Pembanding
        if (!Schema::hasColumn('N_EMI_LAB_Uji_Sampel', 'Id_Session')) {
            Schema::table('N_EMI_LAB_Uji_Sampel', function (Blueprint $table) {
                $table->integer('Id_Session')->nullable();
                $table->integer('Id_Pembanding')->nullable();
            });
        }

        // 6. Alter N_EMI_LAB_Uji_Sampel_Resampling_Log - tambah Id_Session dan Id_Pembanding
        if (!Schema::hasColumn('N_EMI_LAB_Uji_Sampel_Resampling_Log', 'Id_Session')) {
            Schema::table('N_EMI_LAB_Uji_Sampel_Resampling_Log', function (Blueprint $table) {
                $table->integer('Id_Session')->nullable();
                $table->integer('Id_Pembanding')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('N_EMI_LAB_Uji_Sampel_Resampling_Log', 'Id_Session')) {
            Schema::table('N_EMI_LAB_Uji_Sampel_Resampling_Log', function (Blueprint $table) {
                $table->dropColumn(['Id_Session', 'Id_Pembanding']);
            });
        }
        if (Schema::hasColumn('N_EMI_LAB_Uji_Sampel', 'Id_Session')) {
            Schema::table('N_EMI_LAB_Uji_Sampel', function (Blueprint $table) {
                $table->dropColumn(['Id_Session', 'Id_Pembanding']);
            });
        }
        DB::statement("IF OBJECT_ID('N_EMI_LAB_Palatabilitas_Sementara') IS NOT NULL DROP TABLE N_EMI_LAB_Palatabilitas_Sementara");
        DB::statement("IF OBJECT_ID('N_EMI_LAB_Palatabilitas_Pembanding') IS NOT NULL DROP TABLE N_EMI_LAB_Palatabilitas_Pembanding");
        DB::statement("IF OBJECT_ID('N_EMI_LAB_Palatabilitas_Session') IS NOT NULL DROP TABLE N_EMI_LAB_Palatabilitas_Session");
        if (Schema::hasColumn('N_EMI_LIMS_Klasifikasi_Aktivitas_Lab', 'Flag_Butuh_Pembanding')) {
            Schema::table('N_EMI_LIMS_Klasifikasi_Aktivitas_Lab', function (Blueprint $table) {
                $table->dropColumn('Flag_Butuh_Pembanding');
            });
        }
    }
};
