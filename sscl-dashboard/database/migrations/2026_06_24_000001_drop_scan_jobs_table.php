<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('scan_jobs');
    }

    public function down(): void
    {
        Schema::create('scan_jobs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }
};
