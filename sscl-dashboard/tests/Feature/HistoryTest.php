<?php

namespace Tests\Feature;

use App\Mail\DetectionCompletedMail;
use App\Models\DetectionResult;
use App\Models\ScanUpload;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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
        $this->createDetectionResult($user, $oldUpload, Carbon::parse('2026-05-03 02:00:00', 'UTC'));

        $todayUpload = $this->createUpload($user, 'today.csv', 'scan-uploads/today.csv');
        $todayUpload->forceFill([
            'created_at' => Carbon::parse('2026-05-04 01:00:00', 'UTC'),
            'updated_at' => Carbon::parse('2026-05-04 01:00:00', 'UTC'),
        ])->save();
        $this->createDetectionResult($user, $todayUpload, Carbon::parse('2026-05-04 02:00:00', 'UTC'));

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
        Storage::put('scan-uploads/source.csv', "source_ip,label\n10.0.0.1,Normal\n");
        $upload = $this->createUpload($user, 'source.csv', 'scan-uploads/source.csv');

        $response = $this
            ->actingAs($user)
            ->get(route('riwayat.show', ['scanUpload' => $upload, 'mode' => 'before']));

        $response->assertOk();
        $response->assertSee('source_ip');
        $response->assertSee('10.0.0.1');
    }

    public function test_history_index_uses_bottom_pagination(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 12) as $index) {
            $this->createUpload($user, 'history-'.$index.'.csv', 'scan-uploads/history-'.$index.'.csv');
        }

        $response = $this
            ->actingAs($user)
            ->get(route('riwayat.index', [
                'per_page' => 10,
                'page' => 2,
            ]));

        $response->assertOk();
        $response->assertSeeText('Menampilkan 11-12');
        $response->assertSeeText('dari 12 data');
    }

    public function test_history_delete_removes_upload_jobs_and_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        Storage::put('scan-uploads/delete.csv', "row,label\n1,Normal\n");
        Storage::put('scan-results/delete-results.csv', "row_id,label\n1,Normal\n");
        $upload = $this->createUpload($user, 'delete.csv', 'scan-uploads/delete.csv');
        $detectionResult = $this->createDetectionResult($user, $upload, Carbon::parse('2026-05-04 02:00:00', 'UTC'));
        $detectionResult->update(['results_path' => 'scan-results/delete-results.csv']);

        $response = $this
            ->actingAs($user)
            ->delete(route('riwayat.destroy', $upload));

        $response->assertRedirect(route('riwayat.index'));
        $response->assertSessionHas('toast_type', 'success');
        $response->assertSessionHas('toast_title', 'Riwayat Terhapus');
        $this->assertDatabaseMissing('scan_uploads', ['id' => $upload->id]);
        $this->assertDatabaseMissing('detection_results', ['id' => $detectionResult->id]);
        Storage::assertMissing('scan-uploads/delete.csv');
        Storage::assertMissing('scan-results/delete-results.csv');
    }

    public function test_history_can_download_original_uploaded_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        Storage::put('scan-uploads/source.csv', "source_ip,label\n10.0.0.1,Normal\n");
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
        $this->createDetectionResult($user, $upload, Carbon::parse('2026-05-04 02:00:00', 'UTC'));

        $response = $this
            ->actingAs($user)
            ->get(route('riwayat.download', ['scanUpload' => $upload, 'mode' => 'after']));

        $response->assertOk();
        $response->assertDownload('traffic-sample-hasil-deteksi.csv');
        $downloadContent = $response->streamedContent();
        $this->assertStringContainsString('row_id,label,confidence_percent', $downloadContent);
        $this->assertStringContainsString('1,Normal,99.00', $downloadContent);
    }

    public function test_scan_stores_full_prediction_results_for_history_download(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $results = collect(range(1, 105))
            ->map(fn (int $row) => [
                'row' => $row,
                'label' => $row === 105 ? 'Virus' : 'Normal',
                'confidence' => 0.9,
                'probabilities' => [
                    'Normal' => $row === 105 ? 0.1 : 0.9,
                    'Virus' => $row === 105 ? 0.9 : 0.1,
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
        $detectionResult = DetectionResult::query()->firstOrFail();
        $this->assertCount(100, $detectionResult->results);
        $this->assertNotNull($detectionResult->results_path);
        Storage::assertExists($detectionResult->results_path);

        $download = $this
            ->actingAs($user)
            ->get(route('riwayat.download', ['scanUpload' => $detectionResult->scan_upload_id, 'mode' => 'after']));

        $download->assertOk();
        $this->assertStringContainsString('105,Virus,90.00', $download->streamedContent());
    }

    public function test_scan_summary_uses_full_api_summary_when_results_are_limited(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $results = collect(range(1, 100))
            ->map(fn (int $row) => [
                'row' => $row,
                'label' => 'Buffer Overflow',
                'confidence' => 1,
                'probabilities' => [
                    'Normal' => 0,
                    'Buffer Overflow' => 1,
                ],
            ])
            ->all();

        Http::fake([
            '*' => Http::response([
                'total' => 3912,
                'total_normal' => 0,
                'total_malware' => 3912,
                'summary' => [
                    'Buffer Overflow' => 3912,
                ],
                'results' => $results,
            ]),
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('scan.store'), [
                'file' => UploadedFile::fake()->create('limited-results.csv', 1, 'text/csv'),
            ]);

        $response->assertRedirect(route('scan.index'));
        $detectionResult = DetectionResult::query()->firstOrFail();

        $this->assertSame(3912, $detectionResult->total);
        $this->assertSame(3912, $detectionResult->malware_count);
        $this->assertSame(0, $detectionResult->normal_count);
        $this->assertSame(3912, $detectionResult->summary['labels']['Buffer Overflow']);
    }

    public function test_scan_sends_summary_email_after_detection_finishes(): void
    {
        Storage::fake('local');
        Mail::fake();

        config([
            'services.scan_notifications.admin_email' => 'admin@example.test',
            'services.scan_notifications.send_to_user' => true,
        ]);

        $user = User::factory()->create([
            'email' => 'user@example.test',
        ]);

        Http::fake([
            '*' => Http::response([
                'total' => 3,
                'total_normal' => 2,
                'total_malware' => 1,
                'summary' => [
                    'Normal' => 2,
                    'Trojan' => 1,
                ],
                'results' => [
                    [
                        'row' => 1,
                        'label' => 'Normal',
                        'confidence' => 0.98,
                        'probabilities' => [
                            'Normal' => 0.98,
                            'Trojan' => 0.02,
                        ],
                    ],
                    [
                        'row' => 2,
                        'label' => 'Normal',
                        'confidence' => 0.96,
                        'probabilities' => [
                            'Normal' => 0.96,
                            'Trojan' => 0.04,
                        ],
                    ],
                    [
                        'row' => 3,
                        'label' => 'Trojan',
                        'confidence' => 0.91,
                        'probabilities' => [
                            'Normal' => 0.09,
                            'Trojan' => 0.91,
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('scan.store'), [
                'file' => UploadedFile::fake()->create('email-summary.csv', 1, 'text/csv'),
            ]);

        $response->assertRedirect(route('scan.index'));

        $detectionResult = DetectionResult::query()->firstOrFail();

        Mail::assertSent(DetectionCompletedMail::class, function (DetectionCompletedMail $mail) use ($detectionResult) {
            return $mail->hasTo('user@example.test')
                && $mail->hasTo('admin@example.test')
                && $mail->detectionResult->id === $detectionResult->id
                && $mail->summary['normal'] === 2
                && $mail->summary['malware'] === 1;
        });
    }

    public function test_detection_results_index_reads_full_stored_csv_with_pagination(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $upload = $this->createUpload($user, 'full-results.csv', 'scan-uploads/full-results.csv');
        Storage::put('scan-results/full-results.csv', $this->predictionCsv(12));
        DetectionResult::create([
            'user_id' => $user->id,
            'scan_upload_id' => $upload->id,
            'file_name' => $upload->original_name,
            'processed_at' => Carbon::parse('2026-05-04 02:00:00', 'UTC'),
            'total' => 12,
            'normal_count' => 0,
            'malware_count' => 2,
            'unknown_count' => 0,
            'normal_percent' => 0,
            'malware_percent' => 16.67,
            'average_confidence' => 100,
            'status' => 'Selesai',
            'summary' => [
                'normal' => 0,
                'malware' => 2,
                'unknown' => 0,
                'labels' => [
                    'Buffer Overflow' => 2,
                ],
            ],
            'results' => [
                [
                    'row' => 1,
                    'label' => 'Buffer Overflow',
                    'confidence' => 1,
                    'probabilities' => [
                        'Normal' => 0,
                        'Buffer Overflow' => 1,
                    ],
                ],
            ],
            'results_path' => 'scan-results/full-results.csv',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('hasildeteksi.index', [
                'per_page' => 10,
                'page' => 2,
            ]));

        $response->assertOk();
        $response->assertSeeText('Menampilkan 11-12');
        $response->assertSeeText('dari 12 baris');
        $response->assertSee('11');
        $response->assertSee('12');
        $response->assertSee('91,67%');
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

    private function createDetectionResult(User $user, ScanUpload $upload, Carbon $processedAt): DetectionResult
    {
        $detectionResult = DetectionResult::create([
            'user_id' => $user->id,
            'scan_upload_id' => $upload->id,
            'file_name' => $upload->original_name,
            'processed_at' => $processedAt,
            'total' => 1,
            'normal_count' => 1,
            'malware_count' => 0,
            'unknown_count' => 0,
            'normal_percent' => 100,
            'malware_percent' => 0,
            'average_confidence' => 99,
            'status' => 'Selesai',
            'summary' => [
                'normal' => 1,
                'malware' => 0,
                'unknown' => 0,
                'normal_percent' => 100,
                'malware_percent' => 0,
                'average_confidence' => 99,
                'labels' => [
                    'Normal' => 1,
                ],
                'malware_labels' => [],
            ],
            'results' => [
                [
                    'row' => 1,
                    'label' => 'Normal',
                    'confidence' => 0.99,
                    'probabilities' => [
                        'Normal' => 0.99,
                        'Virus' => 0.01,
                    ],
                ],
            ],
        ]);

        $detectionResult->forceFill([
            'created_at' => $processedAt,
            'updated_at' => $processedAt,
        ])->save();

        $upload->forceFill([
            'status' => 'processed',
            'updated_at' => $processedAt,
        ])->save();

        return $detectionResult;
    }

    private function predictionCsv(int $rows): string
    {
        $csv = "\xEF\xBB\xBFrow_id,label,confidence_percent,prob_normal_percent,prob_buffer_overflow_percent\n";

        foreach (range(1, $rows) as $row) {
            $label = $row === $rows ? 'Normal' : 'Buffer Overflow';
            $normalProbability = $label === 'Normal' ? '100.00' : '0.00';
            $malwareProbability = $label === 'Normal' ? '0.00' : '100.00';
            $csv .= implode(',', [$row, $label, '100.00', $normalProbability, $malwareProbability])."\n";
        }

        return $csv;
    }
}
