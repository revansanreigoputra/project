<?php

namespace Tests\Feature;

use App\Mail\DetectionCompletedMail;
use App\Models\DetectionResult;
use App\Models\ScanUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UpaTikIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_does_not_call_api_when_integration_is_disabled(): void
    {
        Http::fake();
        config(['services.upa_tik.enabled' => false]);

        $this->artisan('traffic:fetch-upa-tik')
            ->expectsOutput('Integrasi API UPA-TIK belum diaktifkan.')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_command_does_not_call_api_when_configuration_is_incomplete(): void
    {
        Http::fake();
        config([
            'services.upa_tik.enabled' => true,
            'services.upa_tik.base_url' => null,
            'services.upa_tik.endpoint' => null,
            'services.upa_tik.token' => null,
        ]);

        $this->artisan('traffic:fetch-upa-tik')
            ->expectsOutput('Konfigurasi API UPA-TIK belum lengkap: UPA_TIK_API_BASE_URL, UPA_TIK_API_ENDPOINT, UPA_TIK_API_TOKEN.')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_valid_json_payload_is_stored_as_api_upload_and_processed_by_shared_detection_service(): void
    {
        $this->useWritableLocalDisk();
        Mail::fake();
        $user = $this->userWithRole(attributes: ['email' => 'system-upatik@example.test']);
        $this->configureUpaTik($user);

        Http::fake([
            'https://upatik.test/*' => Http::response($this->upaTikPayload('traffic-001'), 200, [
                'Content-Type' => 'application/json',
            ]),
            'http://ml.test/*' => Http::response($this->mlPayload(), 200),
        ]);

        $this->artisan('traffic:fetch-upa-tik')
            ->expectsOutput('Data UPA-TIK diproses: traffic-001')
            ->assertExitCode(0);

        $upload = ScanUpload::query()->firstOrFail();
        $this->assertSame('api', $upload->source);
        $this->assertSame('UPA-TIK', $upload->source_name);
        $this->assertSame('traffic-001', $upload->external_reference);
        $this->assertStringStartsWith('scan-uploads/api/', $upload->path);
        $this->assertSame('processed', $upload->status);
        Storage::assertExists($upload->path);

        $detectionResult = DetectionResult::query()->firstOrFail();
        $this->assertSame('Selesai', $detectionResult->status);
        $this->assertSame($upload->id, $detectionResult->scan_upload_id);
        $this->assertNotNull($detectionResult->results_path);
        Storage::assertExists($detectionResult->results_path);
        Mail::assertNotSent(DetectionCompletedMail::class);
    }

    public function test_duplicate_external_reference_is_not_processed_again(): void
    {
        $this->useWritableLocalDisk();
        $user = $this->userWithRole(attributes: ['email' => 'system-upatik@example.test']);
        $this->configureUpaTik($user);

        ScanUpload::create([
            'user_id' => $user->id,
            'original_name' => 'existing.csv',
            'stored_name' => 'existing.csv',
            'path' => 'scan-uploads/api/2026/06/existing.csv',
            'mime_type' => 'text/csv',
            'extension' => 'csv',
            'size' => 24,
            'status' => 'processed',
            'source' => 'api',
            'source_name' => 'UPA-TIK',
            'external_reference' => 'traffic-duplicate',
        ]);

        Http::fake([
            'https://upatik.test/*' => Http::response($this->upaTikPayload('traffic-duplicate'), 200, [
                'Content-Type' => 'application/json',
            ]),
            'http://ml.test/*' => Http::response($this->mlPayload(), 200),
        ]);

        $this->artisan('traffic:fetch-upa-tik')
            ->expectsOutput('Lewati data UPA-TIK duplikat: traffic-duplicate')
            ->assertExitCode(0);

        $this->assertSame(1, ScanUpload::query()->count());
        Http::assertSentCount(1);
    }

    public function test_model_failure_marks_api_detection_as_failed(): void
    {
        $this->useWritableLocalDisk();
        $user = $this->userWithRole(attributes: ['email' => 'system-upatik@example.test']);
        $this->configureUpaTik($user);

        Http::fake([
            'https://upatik.test/*' => Http::response($this->upaTikPayload('traffic-failed'), 200, [
                'Content-Type' => 'application/json',
            ]),
            'http://ml.test/*' => Http::response(['message' => 'Model error'], 500),
        ]);

        $this->artisan('traffic:fetch-upa-tik')
            ->expectsOutput('Data UPA-TIK gagal diproses traffic-failed: Model error')
            ->assertExitCode(1);

        $upload = ScanUpload::query()->firstOrFail();
        $this->assertSame('failed', $upload->status);

        $detectionResult = DetectionResult::query()->firstOrFail();
        $this->assertSame('Gagal', $detectionResult->status);
        $this->assertSame('Model error', $detectionResult->error_message);
    }

    public function test_manual_upload_still_uses_manual_source_and_existing_detection_flow(): void
    {
        $this->useWritableLocalDisk();
        Mail::fake();
        $user = $this->userWithRole();
        config(['services.ml_api.url' => 'http://ml.test']);

        Http::fake([
            'http://ml.test/*' => Http::response($this->mlPayload(), 200),
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('scan.store'), [
                'file' => UploadedFile::fake()->create('manual.csv', 1, 'text/csv'),
            ]);

        $response->assertRedirect(route('scan.index'));

        $upload = ScanUpload::query()->firstOrFail();
        $this->assertSame('manual', $upload->source);
        $this->assertNull($upload->source_name);
        $this->assertNull($upload->external_reference);
        $this->assertSame('processed', $upload->status);
        $this->assertSame('Selesai', DetectionResult::query()->firstOrFail()->status);
    }

    private function configureUpaTik(User $user): void
    {
        config([
            'services.upa_tik.enabled' => true,
            'services.upa_tik.base_url' => 'https://upatik.test',
            'services.upa_tik.endpoint' => '/traffic',
            'services.upa_tik.token' => 'secret-token',
            'services.upa_tik.timeout' => 30,
            'services.upa_tik.system_user_email' => $user->email,
            'services.upa_tik.source_name' => 'UPA-TIK',
            'services.ml_api.url' => 'http://ml.test',
            'services.ml_api.key' => 'ml-secret',
            'services.ml_api.timeout' => 120,
        ]);
    }

    private function upaTikPayload(string $externalReference): array
    {
        return [
            'datasets' => [
                [
                    'external_reference' => $externalReference,
                    'file_name' => $externalReference.'.csv',
                    'records' => [
                        [
                            'src_ip' => '10.10.1.10',
                            'dst_ip' => '10.10.1.20',
                            'src_port' => 44321,
                            'dst_port' => 443,
                            'protocol' => 'TCP',
                            'packet_length' => 512,
                            'ttl' => 64,
                            'sent_bytes' => 1024,
                            'received_bytes' => 2048,
                            'severity' => 'low',
                            'count' => 1,
                        ],
                    ],
                ],
            ],
        ];
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
