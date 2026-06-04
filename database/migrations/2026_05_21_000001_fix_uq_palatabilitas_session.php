<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the wrong unique constraint (on No_Faktur_Uji_Sampel, Kode_Aktivitas_Lab)
        // and recreate it correctly on (No_Po_Sampel, Kode_Aktivitas_Lab).
        DB::statement("
            IF EXISTS (
                SELECT 1 FROM sys.key_constraints
                WHERE name = 'UQ_Palatabilitas_Session'
                  AND parent_object_id = OBJECT_ID('N_EMI_LAB_Palatabilitas_Session')
            )
            ALTER TABLE N_EMI_LAB_Palatabilitas_Session
                DROP CONSTRAINT UQ_Palatabilitas_Session
        ");

        DB::statement("
            IF NOT EXISTS (
                SELECT 1 FROM sys.indexes
                WHERE name = 'UQ_Palatabilitas_Session'
                  AND object_id = OBJECT_ID('N_EMI_LAB_Palatabilitas_Session')
            )
            ALTER TABLE N_EMI_LAB_Palatabilitas_Session
                ADD CONSTRAINT UQ_Palatabilitas_Session
                    UNIQUE (No_Po_Sampel, Kode_Aktivitas_Lab)
        ");
    }

    public function down(): void
    {
        DB::statement("
            IF EXISTS (
                SELECT 1 FROM sys.key_constraints
                WHERE name = 'UQ_Palatabilitas_Session'
                  AND parent_object_id = OBJECT_ID('N_EMI_LAB_Palatabilitas_Session')
            )
            ALTER TABLE N_EMI_LAB_Palatabilitas_Session
                DROP CONSTRAINT UQ_Palatabilitas_Session
        ");

        DB::statement("
            ALTER TABLE N_EMI_LAB_Palatabilitas_Session
                ADD CONSTRAINT UQ_Palatabilitas_Session
                    UNIQUE (No_Faktur_Uji_Sampel, Kode_Aktivitas_Lab)
        ");
    }
};
