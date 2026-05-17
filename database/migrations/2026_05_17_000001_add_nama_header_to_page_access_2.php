<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('N_EMI_LAB_Page_Access_2', 'Nama_Header')) {
            Schema::table('N_EMI_LAB_Page_Access_2', function (Blueprint $table) {
                $table->string('Nama_Header', 100)->nullable()->default('');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('N_EMI_LAB_Page_Access_2', 'Nama_Header')) {
            Schema::table('N_EMI_LAB_Page_Access_2', function (Blueprint $table) {
                $table->dropColumn('Nama_Header');
            });
        }
    }
};
