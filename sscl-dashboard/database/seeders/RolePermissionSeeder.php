<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    // Role bawaan yang dipakai untuk membagi akses admin dan petugas.
    public const ADMIN_ROLE = 'Admin';

    public const PETUGAS_ROLE = 'Petugas';

    // Daftar permission yang dipakai route dan fitur utama aplikasi.
    public const PERMISSIONS = [
        'dashboard.view',
        'dashboard.view-all',
        'users.manage',
        'roles.manage',
        'permissions.manage',
        'scan.upload',
        'scan.run',
        'detection.view',
        'history.view',
        'history.view-any',
        'reports.view',
        'reports.download',
        'operational.manage',
        'database.backup',
    ];

    // Permission khusus admin untuk kelola user, role, dan permission.
    public const ADMIN_PERMISSIONS = [
        'users.manage',
        'roles.manage',
        'permissions.manage',
    ];

    // Permission petugas untuk operasional scan, riwayat, laporan, dan backup.
    public const PETUGAS_PERMISSIONS = [
        'dashboard.view',
        'dashboard.view-all',
        'scan.upload',
        'scan.run',
        'detection.view',
        'history.view',
        'history.view-any',
        'reports.view',
        'reports.download',
        'operational.manage',
        'database.backup',
    ];

    // Seed role, permission, dan akun default supaya aplikasi langsung bisa dipakai.
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $admin = Role::firstOrCreate([
            'name' => self::ADMIN_ROLE,
            'guard_name' => 'web',
        ]);
        $admin->syncPermissions(self::ADMIN_PERMISSIONS);

        $petugas = Role::firstOrCreate([
            'name' => self::PETUGAS_ROLE,
            'guard_name' => 'web',
        ]);
        $petugas->syncPermissions(self::PETUGAS_PERMISSIONS);

        $adminUser = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin SSCL',
                'password' => Hash::make('password'),
            ]
        );
        $adminUser->syncRoles([$admin]);
        $adminUser->syncPermissions([]);

        $petugasUser = User::firstOrCreate(
            ['email' => 'petugas@example.com'],
            [
                'name' => 'Petugas SSCL',
                'password' => Hash::make('password'),
            ]
        );
        $petugasUser->syncRoles([$petugas]);
        $petugasUser->syncPermissions([]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
