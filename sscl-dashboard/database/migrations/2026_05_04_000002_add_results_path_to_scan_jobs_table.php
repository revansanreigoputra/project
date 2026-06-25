<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    // Migration lama ini sengaja kosong karena data deteksi sudah pindah ke tabel detection_results.
    public function up(): void
    {
        //
    }

    // Rollback juga kosong karena migration ini tidak lagi membuat perubahan schema.
    public function down(): void
    {
        //
    }
};
