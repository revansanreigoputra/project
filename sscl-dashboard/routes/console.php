<?php

use App\Models\DatabaseBackupSetting;
use App\Models\ScanUpload;
use App\Models\User;
use App\Services\DatabaseBackupService;
use App\Services\TrafficDetectionService;
use App\Services\UpaTik\UpaTikApiClient;
use App\Services\UpaTik\UpaTikTrafficMapper;
use App\Services\VpsCsvService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Validation\ValidationException;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Command buat menjalankan backup database yang sudah jatuh tempo.
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

// Command buat ambil data trafik dari API UPA-TIK lalu langsung jalankan deteksi.
Artisan::command(
    'traffic:fetch-upa-tik',
    function (
        UpaTikApiClient $client,
        UpaTikTrafficMapper $mapper,
        TrafficDetectionService $trafficDetectionService
    ) {
        if (! $client->isEnabled()) {
            $this->info('Integrasi API UPA-TIK belum diaktifkan.');

            return 0;
        }

        $response = $client->fetchTraffic();

        if (! ($response['ok'] ?? false)) {
            $message = $response['message'] ?? 'Pengambilan data UPA-TIK gagal.';
            $status = $response['status'] ?? 'failed';

            if (in_array($status, ['disabled', 'misconfigured'], true)) {
                $this->warn($message);

                return 0;
            }

            $this->error($message);

            return 1;
        }

        $sourceName = (string) config('services.upa_tik.source_name', 'UPA-TIK');
        // Cari user sistem yang akan jadi pemilik data hasil sinkronisasi API.
        $resolveSystemUser = function (): ?User {
            $configuredEmail = trim((string) config('services.upa_tik.system_user_email'));

            if ($configuredEmail !== '') {
                $configuredUser = User::query()->where('email', $configuredEmail)->first();

                if ($configuredUser) {
                    return $configuredUser;
                }

                Log::warning('Configured UPA-TIK system user was not found.', [
                    'email' => $configuredEmail,
                ]);
            }

            return User::query()
                ->whereHas('roles', fn ($query) => $query->where('name', 'Petugas'))
                ->oldest('id')
                ->first();
        };
        // Cek duplikasi supaya dataset API yang sama tidak diproses berkali-kali.
        $uploadExists = fn (string $datasetSourceName, string $externalReference): bool => ScanUpload::query()
            ->where('source', 'api')
            ->where('source_name', $datasetSourceName)
            ->where('external_reference', $externalReference)
            ->exists();
        // Response berupa file langsung disimpan sebagai upload API lalu diprediksi.
        $processFileResponse = function (
            array $fileResponse,
            ?int $systemUserId,
            string $fileSourceName
        ) use ($trafficDetectionService, $uploadExists): int {
            $contents = (string) ($fileResponse['contents'] ?? '');

            if ($contents === '') {
                $this->error('File dari API UPA-TIK kosong.');

                return 1;
            }

            $externalReference = 'sha256:'.hash('sha256', $contents);

            if ($uploadExists($fileSourceName, $externalReference)) {
                $this->line('Lewati file UPA-TIK duplikat: '.$externalReference);

                return 0;
            }

            try {
                $upload = $trafficDetectionService->storeApiFile(
                    $contents,
                    $fileResponse['file_name'] ?? 'upa-tik-traffic.csv',
                    $systemUserId,
                    $externalReference,
                    $fileSourceName
                );
                $trafficDetectionService->detect($upload, $systemUserId);
                $this->info('File UPA-TIK diproses: '.$externalReference);

                return 0;
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())->flatten()->first() ?: $exception->getMessage();
                $this->error('File UPA-TIK gagal diproses: '.$message);

                return 1;
            }
        };
        $systemUser = $resolveSystemUser();

        if (! $systemUser) {
            Log::warning('UPA-TIK data will be stored without a user owner because no Petugas/system user was found.');
        }

        if (($response['type'] ?? null) === 'file') {
            return $processFileResponse($response, $systemUser?->id, $sourceName);
        }

        try {
            $datasets = $mapper->mapJsonResponse($response['payload'] ?? []);
        } catch (InvalidArgumentException $exception) {
            Log::warning('UPA-TIK API payload could not be mapped.', [
                'error' => $exception->getMessage(),
            ]);
            $this->error($exception->getMessage());

            return 1;
        }

        $processed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($datasets as $dataset) {
            $externalReference = $dataset['external_reference'];
            $datasetSourceName = $dataset['source_name'] ?: $sourceName;

            if ($uploadExists($datasetSourceName, $externalReference)) {
                $skipped++;
                $this->line('Lewati data UPA-TIK duplikat: '.$externalReference);

                continue;
            }

            try {
                $upload = $trafficDetectionService->storeApiCsv(
                    $dataset['rows'],
                    $dataset['file_name'],
                    $systemUser?->id,
                    $externalReference,
                    $datasetSourceName
                );
                $trafficDetectionService->detect($upload, $systemUser?->id);
                $processed++;
                $this->info('Data UPA-TIK diproses: '.$externalReference);
            } catch (ValidationException $exception) {
                $failed++;
                $message = collect($exception->errors())->flatten()->first() ?: $exception->getMessage();
                $this->error('Data UPA-TIK gagal diproses '.$externalReference.': '.$message);
            }
        }

        $this->info("Selesai. Diproses: {$processed}, duplikat: {$skipped}, gagal: {$failed}.");

        return $failed > 0 ? 1 : 0;
    }
)->purpose('Fetch traffic data from UPA-TIK API when integration is enabled');

// Command buat ambil file CSV dari folder VPS dan menjalankan deteksi otomatis.
Artisan::command(
    'traffic:fetch-vps-csv {--limit= : Jumlah maksimal file CSV yang diproses dalam satu run} {--dry-run : Tampilkan file CSV tanpa memprosesnya}',
    function (
        VpsCsvService $vpsCsvService,
        TrafficDetectionService $trafficDetectionService
    ) {
        if (! (bool) config('services.vps_csv.enabled', false)) {
            $this->info('Integrasi CSV VPS belum diaktifkan.');

            return 0;
        }

        $configuredLimit = $this->option('limit');
        $limit = is_numeric($configuredLimit)
            ? max(0, (int) $configuredLimit)
            : max(0, (int) config('services.vps_csv.max_files', 10));

        try {
            $files = $vpsCsvService->csvFiles($limit);
        } catch (Throwable $exception) {
            Log::warning('VPS CSV source directory could not be read.', [
                'error' => $exception->getMessage(),
            ]);
            $this->error('Folder CSV VPS belum bisa dibaca: '.$exception->getMessage());

            return 1;
        }

        if ($files === []) {
            $this->info('Tidak ada file CSV VPS yang perlu diproses.');

            return 0;
        }

        if ($this->option('dry-run')) {
            foreach ($files as $path) {
                $this->line($path);
            }

            return 0;
        }

        $sourceName = trim((string) config('services.vps_csv.source_name', 'VPS Syslog Dataset'))
            ?: 'VPS Syslog Dataset';
        // Cari user sistem yang dipakai sebagai pemilik upload CSV VPS.
        $systemUser = (function (): ?User {
            $configuredEmail = trim((string) config('services.vps_csv.system_user_email'));

            if ($configuredEmail !== '') {
                $configuredUser = User::query()->where('email', $configuredEmail)->first();

                if ($configuredUser) {
                    return $configuredUser;
                }

                Log::warning('Configured VPS CSV system user was not found.', [
                    'email' => $configuredEmail,
                ]);
            }

            return User::query()
                ->whereHas('roles', fn ($query) => $query->where('name', 'Petugas'))
                ->oldest('id')
                ->first();
        })();
        // Cek hash file agar CSV VPS yang sama tidak diproses ulang.
        $uploadExists = fn (string $externalReference): bool => ScanUpload::query()
            ->where('source', 'api')
            ->where('external_reference', $externalReference)
            ->exists();

        if (! $systemUser) {
            Log::warning('VPS CSV data will be stored without a user owner because no Petugas/system user was found.');
        }

        $processed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($files as $path) {
            try {
                $contents = $vpsCsvService->readCsv($path);
            } catch (Throwable $exception) {
                $failed++;
                $this->error('CSV VPS gagal dibaca '.$path.': '.$exception->getMessage());

                continue;
            }

            if (trim($contents) === '') {
                $failed++;
                $this->error('CSV VPS kosong: '.$path);

                continue;
            }

            $externalReference = $vpsCsvService->externalReference($contents);

            if ($uploadExists($externalReference)) {
                $skipped++;
                $this->line('Lewati CSV VPS duplikat: '.$path);

                continue;
            }

            try {
                $upload = $trafficDetectionService->storeApiFile(
                    $contents,
                    basename(str_replace('\\', '/', $path)),
                    $systemUser?->id,
                    $externalReference,
                    $sourceName
                );
                $detectionResult = $trafficDetectionService->detect($upload, $systemUser?->id, [
                    'send_email' => false,
                ]);
                $publishedUploadPath = $vpsCsvService->publishOriginalUpload($upload);
                $publishedResultPath = $vpsCsvService->publishDetectionResult($detectionResult);

                if ((bool) config('services.vps_csv.delete_after_processing', false)) {
                    $vpsCsvService->deleteSourceCsv($path);
                }

                $processed++;
                $this->info('CSV VPS diproses: '.$path);
                $this->line('Input tersimpan di VPS target: '.$publishedUploadPath);

                if ($publishedResultPath) {
                    $this->line('Hasil tersimpan di VPS target: '.$publishedResultPath);
                }
            } catch (ValidationException $exception) {
                $failed++;
                $message = collect($exception->errors())->flatten()->first() ?: $exception->getMessage();
                $this->error('CSV VPS gagal diproses '.$path.': '.$message);
            } catch (Throwable $exception) {
                $failed++;
                Log::warning('VPS CSV processing failed unexpectedly.', [
                    'path' => $path,
                    'error' => $exception->getMessage(),
                ]);
                $this->error('CSV VPS gagal diproses '.$path.': '.$exception->getMessage());
            }
        }

        $this->info("Selesai. Diproses: {$processed}, duplikat: {$skipped}, gagal: {$failed}.");

        return $failed > 0 ? 1 : 0;
    }
)->purpose('Fetch CSV files from a VPS directory and run traffic detection');

// Scheduler menjalankan command otomatis sesuai config masing-masing fitur.
Schedule::command('backup:run-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('traffic:fetch-upa-tik')
    ->daily()
    ->when(fn () => (bool) config('services.upa_tik.enabled', false))
    ->withoutOverlapping();

$vpsCsvSchedule = Schedule::command('traffic:fetch-vps-csv')
    ->when(fn () => (bool) config('services.vps_csv.enabled', false))
    ->timezone((string) config('services.vps_csv.schedule_timezone', 'Asia/Jakarta'))
    ->withoutOverlapping();

$vpsCsvScheduleTime = (string) config('services.vps_csv.schedule_time', '00:00');

// Kalau format jam tidak valid, pakai jadwal aman default tengah malam.
if (! preg_match('/^\d{2}:\d{2}$/', $vpsCsvScheduleTime)) {
    $vpsCsvScheduleTime = '00:00';
}

// Pilih frekuensi scheduler CSV VPS dari config env.
match ((string) config('services.vps_csv.schedule', 'daily')) {
    'every_minute' => $vpsCsvSchedule->everyMinute(),
    'every_five_minutes' => $vpsCsvSchedule->everyFiveMinutes(),
    'every_ten_minutes' => $vpsCsvSchedule->everyTenMinutes(),
    'every_fifteen_minutes' => $vpsCsvSchedule->everyFifteenMinutes(),
    'every_thirty_minutes' => $vpsCsvSchedule->everyThirtyMinutes(),
    'hourly' => $vpsCsvSchedule->hourly(),
    'daily_at' => $vpsCsvSchedule->dailyAt($vpsCsvScheduleTime),
    default => $vpsCsvSchedule->daily(),
};
