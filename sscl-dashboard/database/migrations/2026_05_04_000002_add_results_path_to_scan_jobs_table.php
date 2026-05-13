<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Retired: detection data now lives in the detection_results table.
    }

    public function down(): void
    {
        //
    }
};
