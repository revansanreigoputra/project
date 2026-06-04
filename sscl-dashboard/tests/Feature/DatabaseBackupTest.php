<?php

namespace Tests\Feature;

use App\Models\DatabaseBackup;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_petugas_can_create_manual_sql_backup(): void
    {
        Storage::fake('local');
        $petugas = $this->userWithRole(RolePermissionSeeder::PETUGAS_ROLE);

        $response = $this->actingAs($petugas)
            ->postJson(route('backup.store'), [
                'format' => 'sql',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('backup.format', 'sql')
            ->assertJsonPath('backup.status', 'completed');

        $backup = DatabaseBackup::query()->firstOrFail();

        $this->assertSame($petugas->id, $backup->user_id);
        $this->assertSame('manual', $backup->trigger);
        Storage::disk('local')->assertExists($backup->path);
    }

    public function test_admin_cannot_access_database_backup_feature(): void
    {
        $admin = $this->userWithRole(RolePermissionSeeder::ADMIN_ROLE);

        $this->actingAs($admin)
            ->get(route('backup.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->postJson(route('backup.store'), [
                'format' => 'sql',
            ])
            ->assertForbidden();
    }
}
