<?php

use App\Http\Controllers\Admin\PermissionManagementController;
use App\Http\Controllers\Admin\RoleManagementController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabaseBackupController;
use App\Http\Controllers\DetectionResultController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScanJobController;
use App\Support\AccessRedirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// use App\Http\Controllers\TrafficLogController;
// use App\Http\Controllers\MlModelController;
// use App\Http\Controllers\DatasetController;
// use App\Http\Controllers\SettingController;

// Route awal: user login diarahkan ke halaman sesuai role, guest masuk ke form login.
Route::get('/', function (Request $request) {
    return $request->user()
        ? redirect()->route(AccessRedirect::routeFor($request->user()))
        : view('auth.login');
})->name('home');

// Semua halaman utama aplikasi wajib login dulu.
Route::middleware('auth')->group(function () {
    // Route operasional untuk Petugas: dashboard, scan, hasil deteksi, riwayat, laporan, dan backup.
    Route::middleware('role:Petugas')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->middleware('permission:dashboard.view')
            ->name('dashboard');

        Route::post('/scan/upload', [ScanJobController::class, 'upload'])
            ->middleware('permission:scan.upload')
            ->name('scan.upload');
        Route::resource('scan', ScanJobController::class)
            ->only(['index'])
            ->middleware('permission:scan.upload|scan.run');
        Route::resource('scan', ScanJobController::class)
            ->only(['store'])
            ->middleware('permission:scan.run');
        Route::get('/hasil-deteksi', [DetectionResultController::class, 'index'])
            ->middleware('permission:detection.view|reports.view')
            ->name('hasildeteksi.index');
        Route::get('/hasil-deteksi/{detectionResult}', [DetectionResultController::class, 'show'])
            ->middleware('permission:detection.view|reports.view')
            ->name('hasildeteksi.show');
        Route::get('/riwayat', [HistoryController::class, 'index'])
            ->middleware('permission:history.view')
            ->name('riwayat.index');
        Route::get('/riwayat/{scanUpload}', [HistoryController::class, 'show'])
            ->middleware('permission:history.view')
            ->name('riwayat.show');
        Route::get('/riwayat/{scanUpload}/download/{mode}', [HistoryController::class, 'download'])
            ->middleware('permission:reports.download')
            ->whereIn('mode', ['before', 'after'])
            ->name('riwayat.download');
        Route::delete('/riwayat/{scanUpload}', [HistoryController::class, 'destroy'])
            ->middleware('permission:operational.manage')
            ->name('riwayat.destroy');
        Route::get('/laporan', [ReportController::class, 'index'])
            ->middleware('permission:reports.view|detection.view')
            ->name('laporan.index');

        Route::get('/backup', [DatabaseBackupController::class, 'index'])
            ->middleware('permission:database.backup')
            ->name('backup.index');
        Route::post('/backup', [DatabaseBackupController::class, 'store'])
            ->middleware('permission:database.backup')
            ->name('backup.store');
        Route::patch('/backup/schedule', [DatabaseBackupController::class, 'updateSchedule'])
            ->middleware('permission:database.backup')
            ->name('backup.schedule');
        Route::get('/backup/{backup}/download', [DatabaseBackupController::class, 'download'])
            ->middleware('permission:database.backup')
            ->name('backup.download');
    });

    // Route admin buat kelola user, role, dan permission sistem.
    Route::prefix('admin')->name('admin.')->middleware('role:Admin')->group(function () {
        Route::resource('users', UserManagementController::class)
            ->middleware('permission:users.manage')
            ->except(['show']);
        Route::resource('roles', RoleManagementController::class)
            ->middleware('permission:roles.manage')
            ->except(['show']);
        Route::resource('permissions', PermissionManagementController::class)
            ->middleware('permission:permissions.manage')
            ->except(['show']);
    });

    // Route profile dipakai semua user yang sudah login.
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    // Route::resource('logs', TrafficLogController::class)->only(['index']);
    // Route::resource('model', MlModelController::class)->only(['index']);
    // Route::resource('datasets', DatasetController::class)->only(['index']);
    // Route::resource('settings', SettingController::class)->only(['index']);
});

// Pisahkan route autentikasi supaya web.php tetap fokus ke fitur utama.
require __DIR__.'/auth.php';

// use Illuminate\Support\Facades\Route;
// use Illuminate\Support\Facades\Storage;

// Route::get('/test-upload-vps', function () {
//     Storage::disk('vps_csv')->put('test_laravel.csv', "nama,nilai\nEgi,90\n");

//     return 'Berhasil upload ke VPS';
// });
