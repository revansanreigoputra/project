<?php

use App\Models\DatabaseBackupSetting;
use App\Models\ScanUpload;
use App\Models\User;
use App\Services\DatabaseBackupService;
use App\Services\TrafficDetectionService;
use App\Services\UpaTik\UpaTikApiClient;
use App\Services\UpaTik\UpaTikTrafficMapper;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Validation\ValidationException;

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
        $uploadExists = fn (string $datasetSourceName, string $externalReference): bool => ScanUpload::query()
            ->where('source', 'api')
            ->where('source_name', $datasetSourceName)
            ->where('external_reference', $externalReference)
            ->exists();
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
                $trafficDetectionService->detect($upload, $systemUserId, [
                    'send_email' => false,
                ]);
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
        } catch (\InvalidArgumentException $exception) {
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
                $trafficDetectionService->detect($upload, $systemUser?->id, [
                    'send_email' => false,
                ]);
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

Schedule::command('backup:run-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('traffic:fetch-upa-tik')
    ->daily()
    ->when(fn () => (bool) config('services.upa_tik.enabled', false))
    ->withoutOverlapping();