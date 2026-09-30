<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add Sub_Header and Sub_Sub_Header per-user columns (Nama_Header already exists from prior migration)
        Schema::table('N_EMI_LAB_Page_Access_2', function (Blueprint $table) {
            if (!Schema::hasColumn('N_EMI_LAB_Page_Access_2', 'Sub_Header')) {
                $table->string('Sub_Header', 100)->nullable()->default('');
            }
            if (!Schema::hasColumn('N_EMI_LAB_Page_Access_2', 'Sub_Sub_Header')) {
                $table->string('Sub_Sub_Header', 100)->nullable()->default('');
            }
        });

        // Fix corrupted Nama_Header in N_EMI_LAB_Menus caused by batchSavePageAccess
        // writing per-user grouping back to the global menu table.
        DB::table('N_EMI_LAB_Menus')
            ->where('Id_Menu', 11)
            ->update(['Nama_Header' => 'Master Data']);

        // Also fix Kriteria Kelayakan Analisa (21) which should stay Dashboard — no change needed.
        // Reset any other menus that should be Master Data but got changed to Dashboard:
        // Barang Uji Laboratorium (11) was the only confirmed corruption; others remain as-is.
    }

    public function down(): void
    {
        Schema::table('N_EMI_LAB_Page_Access_2', function (Blueprint $table) {
            if (Schema::hasColumn('N_EMI_LAB_Page_Access_2', 'Sub_Header')) {
                $table->dropColumn('Sub_Header');
            }
            if (Schema::hasColumn('N_EMI_LAB_Page_Access_2', 'Sub_Sub_Header')) {
                $table->dropColumn('Sub_Sub_Header');
            }
        });

        // Revert data fix (restore what the bug wrote — purposely left blank since the original was 'Master Data')
    }
};
