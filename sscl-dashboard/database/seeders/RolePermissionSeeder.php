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
    public const ADMIN_ROLE = 'Admin';

    public const PETUGAS_ROLE = 'Petugas';

    public const PERMISSIONS = [
        'dashboard.view',
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
    ];

    public const PETUGAS_PERMISSIONS = [
        'scan.upload',
        'scan.run',
        'detection.view',
        'history.view',
        'reports.view',
        'reports.download',
    ];

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
        $admin->syncPermissions(self::PERMISSIONS);

        $petugas = Role::firstOrCreate([
            'name' => self::PETUGAS_ROLE,
            'guard_name' => 'web',
        ]);
        $petugas->syncPermissions(self::PETUGAS_PERMISSIONS);

        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin SSCL',
                'password' => Hash::make('password'),
            ]
        )->assignRole($admin);

        User::firstOrCreate(
            ['email' => 'petugas@example.com'],
            [
                'name' => 'Petugas SSCL',
                'password' => Hash::make('password'),
            ]
        )->assignRole($petugas);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
