<?php

use App\Models\DatabaseBackupSetting;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('backup:run-scheduled', function (DatabaseBackupService $backupService) {
    $setting = DatabaseBackupSetting::current();

    if (! $setting->enabled || ! $setting->next_run_at || $setting->next_run_at->isFuture()) {
        $this->info('Tidak ada jadwal backup yang jatuh tempo.');

        return 0;
    }

    $backupService->create($setting->format, null, 'scheduled');
    $backupService->pruneOldBackups($setting->retention_days);

    $setting->forceFill([
        'last_run_at' => now(),
        'next_run_at' => $setting->nextRunAfter(now()),
    ])->save();

    $this->info('Backup terjadwal selesai.');

    return 0;
})->purpose('Run due scheduled database backups');

Schedule::command('backup:run-scheduled')->everyMinute()->withoutOverlapping();
