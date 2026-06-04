<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('database_backup_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('frequency')->default('daily');
            $table->time('scheduled_time')->default('02:00:00');
            $table->unsignedTinyInteger('scheduled_weekday')->nullable();
            $table->unsignedTinyInteger('scheduled_month_day')->nullable();
            $table->string('format', 10)->default('zip');
            $table->unsignedSmallInteger('retention_days')->default(30);
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_backup_settings');
    }
};
