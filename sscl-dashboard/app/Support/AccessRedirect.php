<?php

namespace App\Support;

use App\Models\User;

class AccessRedirect
{
    public static function routeFor(User $user): string
    {
        $destinations = [
            'dashboard' => ['dashboard.view'],
            'scan.index' => ['scan.upload', 'scan.run'],
            'hasildeteksi.index' => ['detection.view'],
            'riwayat.index' => ['history.view'],
            'laporan.index' => ['reports.view'],
        ];

        foreach ($destinations as $route => $permissions) {
            if ($user->canAny($permissions)) {
                return $route;
            }
        }

        return 'profile.edit';
    }
}
