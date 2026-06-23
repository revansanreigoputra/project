<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_uploads', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')->after('status');
            $table->string('source_name')->nullable()->after('source');
            $table->string('external_reference')->nullable()->after('source_name');

            $table->index(['source', 'source_name']);
            $table->unique(['source', 'external_reference']);
        });
    }

    public function down(): void
    {
        Schema::table('scan_uploads', function (Blueprint $table) {
            $table->dropUnique(['source', 'external_reference']);
            $table->dropIndex(['source', 'source_name']);
            $table->dropColumn(['source', 'source_name', 'external_reference']);
        });
    }
};
