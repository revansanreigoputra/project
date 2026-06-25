<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    // Tempat register service aplikasi kalau nanti butuh binding custom.
    public function register(): void
    {
        //
    }

    // Tempat bootstrapping aplikasi setelah semua service terdaftar.
    public function boot(): void
    {
        //
    }
}
