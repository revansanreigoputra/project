<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ScanJobController;
// use App\Http\Controllers\TrafficLogController;
// use App\Http\Controllers\MlModelController;
// use App\Http\Controllers\DatasetController;
// use App\Http\Controllers\SettingController;

Route::get('/', fn () => redirect()->route('dashboard'))
    ->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('scan', ScanJobController::class)->only(['index', 'store']);
    Route::view('/hasil-deteksi', 'pages.hasildeteksi.index')->name('hasildeteksi.index');
    Route::view('/riwayat', 'pages.riwayat.index')->name('riwayat.index');
    Route::view('/laporan', 'pages.laporan.index')->name('laporan.index');
    // Route::resource('logs', TrafficLogController::class)->only(['index']);
    // Route::resource('model', MlModelController::class)->only(['index']);
    // Route::resource('datasets', DatasetController::class)->only(['index']);
    // Route::resource('settings', SettingController::class)->only(['index']);
});

require __DIR__.'/auth.php';
