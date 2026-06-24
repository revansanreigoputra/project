<?php

namespace Tests\Feature;

use App\Models\DetectionResult;
use App\Models\ScanUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class VpsCsvIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_processes_vps_csv_and_publishes_files_to_target_vps_disk(): void
    {
        $this->useWritableLocalDisk();
        $user = $this->userWithRole(attributes: ['email' => 'system-vps@example.test']);
        $this->configureVpsCsv($user);
        $this->prepareVpsDisks();
        Storage::disk('vps_dataset')->put('syslog-001.csv', $this->csvContents());

        Http::fake([
            'http://ml.test/*' => Http::response($this->mlPayload(), 200),
        ]);

        $this->artisan('traffic:fetch-vps-csv')
            ->expectsOutput('CSV VPS diproses: syslog-001.csv')
            ->assertExitCode(0);

        $upload = ScanUpload::query()->firstOrFail();
        $this->assertSame('api', $upload->source);
        $this->assertSame('VPS Syslog Dataset', $upload->source_name);
        $this->assertStringStartsWith('vps:', $upload->external_reference);
        $this->assertSame('processed', $upload->status);
        Storage::assertExists($upload->path);

        $detectionResult = DetectionResult::query()->firstOrFail();
        $this->assertSame('Selesai', $detectionResult->status);
        $this->assertSame($upload->id, $detectionResult->scan_upload_id);
        $this->assertNotNull($detectionResult->results_path);
        Storage::assertExists($detectionResult->results_path);
        Storage::disk('vps_csv')->assertExists('scan-uploads/'.$upload->stored_name);
        Storage::disk('vps_csv')->assertExists(
            'scan-results/'.basename(str_replace('\\', '/', $detectionResult->results_path))
        );
    }

    public function test_duplicate_vps_csv_hash_is_not_processed_again(): void
    {
        $this->useWritableLocalDisk();
        $user = $this->userWithRole(attributes: ['email' => 'system-vps@example.test']);
        $this->configureVpsCsv($user);
        $this->prepareVpsDisks();

        $contents = $this->csvContents();
        $externalReference = 'vps:'.hash('sha256', $contents);
        Storage::disk('vps_dataset')->put('syslog-duplicate.csv', $contents);
        ScanUpload::create([
            'user_id' => $user->id,
            'original_name' => 'syslog-duplicate.csv',
            'stored_name' => 'syslog-duplicate.csv',
            'path' => 'scan-uploads/api/2026/06/syslog-duplicate.csv',
            'mime_type' => 'text/csv',
            'extension' => 'csv',
            'size' => strlen($contents),
            'status' => 'processed',
            'source' => 'api',
            'source_name' => 'VPS Syslog Dataset',
            'external_reference' => $externalReference,
        ]);

        Http::fake();

        $this->artisan('traffic:fetch-vps-csv')
            ->expectsOutput('Lewati CSV VPS duplikat: syslog-duplicate.csv')
            ->assertExitCode(0);

        $this->assertSame(1, ScanUpload::query()->count());
        Http::assertNothingSent();
    }

    private function configureVpsCsv(User $user): void
    {
        config([
            'services.vps_csv.enabled' => true,
            'services.vps_csv.source_disk' => 'vps_dataset',
            'services.vps_csv.target_disk' => 'vps_csv',
            'services.vps_csv.source_directory' => '',
            'services.vps_csv.target_uploads_directory' => 'scan-uploads',
            'services.vps_csv.target_results_directory' => 'scan-results',
            'services.vps_csv.system_user_email' => $user->email,
            'services.vps_csv.source_name' => 'VPS Syslog Dataset',
            'services.vps_csv.max_files' => 10,
            'services.vps_csv.delete_after_processing' => false,
            'services.ml_api.url' => 'http://ml.test',
            'services.ml_api.key' => 'ml-secret',
            'services.ml_api.timeout' => 120,
        ]);
    }

    private function prepareVpsDisks(): void
    {
        config([
            'filesystems.disks.vps_dataset' => [
                'driver' => 'local',
                'root' => storage_path('app/testing-local/vps-dataset-'.Str::uuid()),
                'throw' => false,
            ],
            'filesystems.disks.vps_csv' => [
                'driver' => 'local',
                'root' => storage_path('app/testing-local/vps-csv-'.Str::uuid()),
                'throw' => false,
            ],
        ]);

        Storage::forgetDisk(['vps_dataset', 'vps_csv']);
    }

    private function csvContents(): string
    {
        return implode("\n", [
            'source_ip,dest_ip,source_port,dest_port,protocol,packet_length,ttl,sent_bytes,received_bytes,severity,count',
            '10.10.1.10,10.10.1.20,44321,443,TCP,512,64,1024,2048,low,1',
            '',
        ]);
    }

    private function mlPayload(): array
    {
        return [
            'total' => 1,
            'total_normal' => 1,
            'total_malware' => 0,
            'summary' => [
                'Normal' => 1,
            ],
            'results' => [
                [
                    'row' => 1,
                    'label' => 'Normal',
                    'confidence' => 0.99,
                    'probabilities' => [
                        'Normal' => 0.99,
                        'Trojan' => 0.01,
                    ],
                ],
            ],
        ];
    }
}
