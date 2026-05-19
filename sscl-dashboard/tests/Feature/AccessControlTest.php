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

    public function test_admin_can_monitor_all_scan_history(): void
    {
        $admin = $this->userWithRole(RolePermissionSeeder::ADMIN_ROLE);
        $petugas = $this->userWithRole();

        $this->createUpload($petugas, 'petugas-traffic.csv');

        $this->actingAs($admin)
            ->get(route('riwayat.index'))
            ->assertOk()
            ->assertSee('petugas-traffic.csv');
    }

    public function test_petugas_cannot_delete_history(): void
    {
        $petugas = $this->userWithRole();
        $upload = $this->createUpload($petugas, 'protected.csv');

        $this->actingAs($petugas)
            ->delete(route('riwayat.destroy', $upload))
            ->assertForbidden();
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
