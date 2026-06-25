<?php

namespace App\Support;

use App\Models\User;

class AccessRedirect
{
    // Tentukan route tujuan pertama setelah login berdasarkan role dan permission user.
    public static function routeFor(User $user): string
    {
        if ($user->hasRole('Admin')) {
            $adminDestinations = [
                'admin.users.index' => ['users.manage'],
                'admin.roles.index' => ['roles.manage'],
                'admin.permissions.index' => ['permissions.manage'],
            ];

            foreach ($adminDestinations as $route => $permissions) {
                if ($user->canAny($permissions)) {
                    return $route;
                }
            }
        }

        if (! $user->hasRole('Petugas')) {
            return 'profile.edit';
        }

        $destinations = [
            'dashboard' => ['dashboard.view'],
            'scan.index' => ['scan.upload', 'scan.run'],
            'hasildeteksi.index' => ['detection.view', 'reports.view'],
            'riwayat.index' => ['history.view'],
            'backup.index' => ['database.backup'],
        ];

        foreach ($destinations as $route => $permissions) {
            if ($user->canAny($permissions)) {
                return $route;
            }
        }

        return 'profile.edit';
    }
}
