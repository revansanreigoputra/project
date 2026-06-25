<?php

use App\Support\AccessRedirect;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

// Konfigurasi dasar aplikasi Laravel: route, middleware, dan handler exception.
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias middleware permission biar route bisa pakai nama singkat.
        $middleware->alias([
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
        // Setelah login, user diarahkan ke halaman pertama yang sesuai role dan permission.
        $middleware->redirectUsersTo(fn (Request $request) => route(AccessRedirect::routeFor($request->user())));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
