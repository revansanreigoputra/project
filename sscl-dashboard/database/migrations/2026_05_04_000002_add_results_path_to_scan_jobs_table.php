<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_jobs', function (Blueprint $table) {
            $table->string('results_path')->nullable()->after('results');
        });
    }

    public function down(): void
    {
        Schema::table('scan_jobs', function (Blueprint $table) {
            $table->dropColumn('results_path');
        });
    }
};
