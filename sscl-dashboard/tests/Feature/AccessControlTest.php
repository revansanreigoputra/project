<?php

namespace Tests\Feature;

use App\Models\ScanUpload;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_access_management_pages(): void
    {
        $admin = $this->userWithRole(RolePermissionSeeder::ADMIN_ROLE);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Manajemen Pengguna');

        $this->actingAs($admin)
            ->get(route('admin.roles.index'))
            ->assertOk()
            ->assertSee('Manajemen Role');

        $this->actingAs($admin)
            ->get(route('admin.permissions.index'))
            ->assertOk()
            ->assertSee('Manajemen Permission');
    }

    public function test_petugas_cannot_open_access_management_pages(): void
    {
        $petugas = $this->userWithRole();

        $this->actingAs($petugas)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_admin_cannot_open_operational_pages(): void
    {
        $admin = $this->userWithRole(RolePermissionSeeder::ADMIN_ROLE);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('riwayat.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('backup.index'))
            ->assertForbidden();
    }

    public function test_petugas_can_open_dashboard_and_backup_pages(): void
    {
        $petugas = $this->userWithRole();

        $this->actingAs($petugas)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard');

        $this->actingAs($petugas)
            ->get(route('backup.index'))
            ->assertOk()
            ->assertSee('Manual Backup');
    }

    public function test_petugas_can_manage_operational_history(): void
    {
        $petugas = $this->userWithRole();
        $upload = $this->createUpload($petugas, 'protected.csv');

        $this->actingAs($petugas)
            ->delete(route('riwayat.destroy', $upload))
            ->assertRedirect(route('riwayat.index'));

        $this->assertDatabaseMissing('scan_uploads', [
            'id' => $upload->id,
        ]);
    }

    private function createUpload(User $user, string $fileName): ScanUpload
    {
        return ScanUpload::create([
            'user_id' => $user->id,
            'original_name' => $fileName,
            'stored_name' => $fileName,
            'path' => 'scan-uploads/'.$fileName,
            'mime_type' => 'text/csv',
            'extension' => 'csv',
            'size' => 24,
            'status' => 'uploaded',
        ]);
    }
}
