<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_jobs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('scan_upload_id')->nullable()->constrained('scan_uploads')->nullOnDelete();
            $table->string('file_name')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('benign_count')->default(0);
            $table->unsignedInteger('malicious_count')->default(0);
            $table->unsignedInteger('unknown_count')->default(0);
            $table->decimal('benign_percent', 6, 2)->default(0);
            $table->decimal('malicious_percent', 6, 2)->default(0);
            $table->decimal('average_confidence', 6, 2)->default(0);
            $table->string('status')->default('Memproses');
            $table->text('error_message')->nullable();
            $table->json('summary')->nullable();
            $table->json('results')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('scan_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scan_upload_id');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn([
                'file_name',
                'processed_at',
                'total',
                'benign_count',
                'malicious_count',
                'unknown_count',
                'benign_percent',
                'malicious_percent',
                'average_confidence',
                'status',
                'error_message',
                'summary',
                'results',
            ]);
        });
    }
};
