<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detection_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('scan_upload_id')->nullable()->constrained('scan_uploads')->nullOnDelete();
            $table->string('file_name')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('normal_count')->default(0);
            $table->unsignedInteger('malware_count')->default(0);
            $table->unsignedInteger('unknown_count')->default(0);
            $table->decimal('normal_percent', 6, 2)->default(0);
            $table->decimal('malware_percent', 6, 2)->default(0);
            $table->decimal('average_confidence', 6, 2)->default(0);
            $table->string('status')->default('Memproses');
            $table->text('error_message')->nullable();
            $table->json('summary')->nullable();
            $table->json('results')->nullable();
            $table->string('results_path')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'processed_at']);
            $table->index(['scan_upload_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detection_results');
    }
};
