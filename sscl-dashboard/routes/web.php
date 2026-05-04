<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DetectionResultController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScanJobController;
// use App\Http\Controllers\TrafficLogController;
// use App\Http\Controllers\MlModelController;
// use App\Http\Controllers\DatasetController;
// use App\Http\Controllers\SettingController;

Route::get('/', fn () => redirect()->route('dashboard'))
    ->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::post('/scan/upload', [ScanJobController::class, 'upload'])->name('scan.upload');
    Route::resource('scan', ScanJobController::class)->only(['index', 'store']);
    Route::get('/hasil-deteksi', [DetectionResultController::class, 'index'])->name('hasildeteksi.index');
    Route::get('/riwayat', [HistoryController::class, 'index'])->name('riwayat.index');
    Route::get('/laporan', [ReportController::class, 'index'])->name('laporan.index');
    // Route::resource('logs', TrafficLogController::class)->only(['index']);
    // Route::resource('model', MlModelController::class)->only(['index']);
    // Route::resource('datasets', DatasetController::class)->only(['index']);
    // Route::resource('settings', SettingController::class)->only(['index']);
});

require __DIR__.'/auth.php';
