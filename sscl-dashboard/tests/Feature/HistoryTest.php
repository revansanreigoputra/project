<?php

namespace Tests\Feature;

use App\Models\ScanJob;
use App\Models\ScanUpload;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_date_filter_uses_uploaded_or_processed_date(): void
    {
        $user = User::factory()->create();

        $oldUpload = $this->createUpload($user, 'old.csv', 'scan-uploads/old.csv');
        $oldUpload->forceFill([
            'created_at' => Carbon::parse('2026-05-03 01:00:00', 'UTC'),
            'updated_at' => Carbon::parse('2026-05-03 01:00:00', 'UTC'),
        ])->save();
        $this->createScanJob($user, $oldUpload, Carbon::parse('2026-05-03 02:00:00', 'UTC'));

        $todayUpload = $this->createUpload($user, 'today.csv', 'scan-uploads/today.csv');
        $todayUpload->forceFill([
            'created_at' => Carbon::parse('2026-05-04 01:00:00', 'UTC'),
            'updated_at' => Carbon::parse('2026-05-04 01:00:00', 'UTC'),
        ])->save();
        $this->createScanJob($user, $todayUpload, Carbon::parse('2026-05-04 02:00:00', 'UTC'));

        $response = $this
            ->actingAs($user)
            ->get(route('riwayat.index', [
                'start_date' => '2026-05-04',
                'end_date' => '2026-05-04',
            ]));

        $response->assertOk();
        $response->assertSee('today.csv');
        $response->assertDontSee('old.csv');
    }

    public function test_history_can_preview_original_uploaded_data(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        Storage::put('scan-uploads/source.csv', "source_ip,label\n10.0.0.1,Benign\n");
        $upload = $this->createUpload($user, 'source.csv', 'scan-uploads/source.csv');

        $response = $this
            ->actingAs($user)
            ->get(route('riwayat.show', ['scanUpload' => $upload, 'mode' => 'before']));

        $response->assertOk();
        $response->assertSee('source_ip');
        $response->assertSee('10.0.0.1');
    }

    public function test_history_delete_removes_upload_jobs_and_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        Storage::put('scan-uploads/delete.csv', "row,label\n1,Benign\n");
        Storage::put('scan-results/delete-results.csv', "row_id,label\n1,Benign\n");
        $upload = $this->createUpload($user, 'delete.csv', 'scan-uploads/delete.csv');
        $scanJob = $this->createScanJob($user, $upload, Carbon::parse('2026-05-04 02:00:00', 'UTC'));
        $scanJob->update(['results_path' => 'scan-results/delete-results.csv']);

        $response = $this
            ->actingAs($user)
            ->delete(route('riwayat.destroy', $upload));

        $response->assertRedirect(route('riwayat.index'));
        $response->assertSessionHas('toast_type', 'success');
        $response->assertSessionHas('toast_title', 'Riwayat Terhapus');
        $this->assertDatabaseMissing('scan_uploads', ['id' => $upload->id]);
        $this->assertDatabaseMissing('scan_jobs', ['id' => $scanJob->id]);
        Storage::assertMissing('scan-uploads/delete.csv');
        Storage::assertMissing('scan-results/delete-results.csv');
    }

    public function test_history_can_download_original_uploaded_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        Storage::put('scan-uploads/source.csv', "source_ip,label\n10.0.0.1,Benign\n");
        $upload = $this->createUpload($user, 'source.csv', 'scan-uploads/source.csv');

        $response = $this
            ->actingAs($user)
            ->get(route('riwayat.download', ['scanUpload' => $upload, 'mode' => 'before']));

        $response->assertOk();
        $response->assertDownload('source.csv');
    }

    public function test_history_can_download_prediction_results_as_csv(): void
    {
        $user = User::factory()->create();
        $upload = $this->createUpload($user, 'traffic sample.csv', 'scan-uploads/traffic-sample.csv');
        $this->createScanJob($user, $upload, Carbon::parse('2026-05-04 02:00:00', 'UTC'));

        $response = $this
            ->actingAs($user)
            ->get(route('riwayat.download', ['scanUpload' => $upload, 'mode' => 'after']));

        $response->assertOk();
        $response->assertDownload('traffic-sample-hasil-deteksi.csv');
        $downloadContent = $response->streamedContent();
        $this->assertStringContainsString('row_id,label,confidence_percent', $downloadContent);
        $this->assertStringContainsString('1,Benign,99.00', $downloadContent);
    }

    public function test_scan_stores_full_prediction_results_for_history_download(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $results = collect(range(1, 105))
            ->map(fn (int $row) => [
                'row' => $row,
                'label' => $row === 105 ? 'Malicious' : 'Benign',
                'confidence' => 0.9,
                'probabilities' => [
                    'Benign' => $row === 105 ? 0.1 : 0.9,
                    'Malicious' => $row === 105 ? 0.9 : 0.1,
                ],
            ])
            ->all();

        Http::fake([
            '*' => Http::response([
                'total' => 105,
                'results' => $results,
            ]),
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('scan.store'), [
                'file' => UploadedFile::fake()->create('full-results.csv', 1, 'text/csv'),
            ]);

        $response->assertRedirect(route('scan.index'));
        $scanJob = ScanJob::query()->firstOrFail();
        $this->assertCount(100, $scanJob->results);
        $this->assertNotNull($scanJob->results_path);
        Storage::assertExists($scanJob->results_path);

        $download = $this
            ->actingAs($user)
            ->get(route('riwayat.download', ['scanUpload' => $scanJob->scan_upload_id, 'mode' => 'after']));

        $download->assertOk();
        $this->assertStringContainsString('105,Malicious,90.00', $download->streamedContent());
    }

    private function createUpload(User $user, string $fileName, string $path): ScanUpload
    {
        return ScanUpload::create([
            'user_id' => $user->id,
            'original_name' => $fileName,
            'stored_name' => basename($path),
            'path' => $path,
            'mime_type' => 'text/csv',
            'extension' => 'csv',
            'size' => 24,
            'status' => 'uploaded',
        ]);
    }

    private function createScanJob(User $user, ScanUpload $upload, Carbon $processedAt): ScanJob
    {
        $scanJob = ScanJob::create([
            'user_id' => $user->id,
            'scan_upload_id' => $upload->id,
            'file_name' => $upload->original_name,
            'processed_at' => $processedAt,
            'total' => 1,
            'benign_count' => 1,
            'malicious_count' => 0,
            'unknown_count' => 0,
            'benign_percent' => 100,
            'malicious_percent' => 0,
            'average_confidence' => 99,
            'status' => 'Selesai',
            'summary' => [
                'benign' => 1,
                'malicious' => 0,
                'unknown' => 0,
                'benign_percent' => 100,
                'malicious_percent' => 0,
                'average_confidence' => 99,
            ],
            'results' => [
                [
                    'row' => 1,
                    'label' => 'Benign',
                    'confidence' => 0.99,
                    'probabilities' => [
                        'Benign' => 0.99,
                        'Malicious' => 0.01,
                    ],
                ],
            ],
        ]);

        $scanJob->forceFill([
            'created_at' => $processedAt,
            'updated_at' => $processedAt,
        ])->save();

        $upload->forceFill([
            'status' => 'processed',
            'updated_at' => $processedAt,
        ])->save();

        return $scanJob;
    }
}
