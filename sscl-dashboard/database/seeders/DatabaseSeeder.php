<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    // Jalankan seeder role dan permission sebagai data awal aplikasi.
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
    }
}
