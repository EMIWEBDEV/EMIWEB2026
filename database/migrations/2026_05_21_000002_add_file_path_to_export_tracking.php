<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('N_EMI_LAB_Export_Tracking', function (Blueprint $table) {
            $table->string('file_path', 500)->nullable()->after('file_url');
        });
    }

    public function down(): void
    {
        Schema::table('N_EMI_LAB_Export_Tracking', function (Blueprint $table) {
            $table->dropColumn('file_path');
        });
    }
};
