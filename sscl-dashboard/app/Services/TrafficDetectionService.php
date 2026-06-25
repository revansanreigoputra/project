<?php

namespace App\Services;

use App\Mail\DetectionCompletedMail;
use App\Models\DetectionResult;
use App\Models\ScanUpload;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class TrafficDetectionService
{
    // Simpan upload manual dari form scan lalu catat metadatanya ke database.
    public function storeManualUpload(UploadedFile $file, ?int $userId): ScanUpload
    {
        $path = $file->store('scan-uploads');

        if (! $path) {
            throw ValidationException::withMessages([
                'file' => 'File gagal disimpan ke storage.',
            ]);
        }

        return $this->createUploadRecord($path, [
            'user_id' => $userId,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'extension' => $file->getClientOriginalExtension(),
            'size' => $file->getSize() ?: 0,
            'status' => 'uploaded',
            'source' => 'manual',
            'source_name' => null,
            'external_reference' => null,
        ]);
    }

    // Simpan dataset API yang sudah dimapping menjadi CSV lokal.
    public function storeApiCsv(
        array $rows,
        string $originalName,
        ?int $userId,
        string $externalReference,
        string $sourceName = 'UPA-TIK'
    ): ScanUpload {
        $path = $this->apiStoragePath($originalName, 'csv');
        $stream = fopen('php://temp', 'r+');

        if ($rows === []) {
            fclose($stream);

            throw ValidationException::withMessages([
                'file' => 'Dataset API UPA-TIK kosong setelah mapping.',
            ]);
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, array_keys($rows[0]));

        foreach ($rows as $row) {
            fputcsv($stream, array_values($row));
        }

        rewind($stream);
        $stored = Storage::put($path, $stream);
        fclose($stream);

        if (! $stored) {
            throw ValidationException::withMessages([
                'file' => 'Dataset API UPA-TIK gagal disimpan ke storage.',
            ]);
        }

        return $this->createUploadRecord($path, [
            'user_id' => $userId,
            'original_name' => $originalName,
            'mime_type' => 'text/csv',
            'extension' => 'csv',
            'size' => Storage::size($path),
            'status' => 'uploaded',
            'source' => 'api',
            'source_name' => $sourceName,
            'external_reference' => $externalReference,
        ]);
    }

    // Simpan file mentah dari API UPA-TIK tanpa mapping tambahan.
    public function storeApiFile(
        string $contents,
        string $originalName,
        ?int $userId,
        string $externalReference,
        string $sourceName = 'UPA-TIK'
    ): ScanUpload {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION) ?: 'csv';
        $path = $this->apiStoragePath($originalName, $extension);

        if (! Storage::put($path, $contents)) {
            throw ValidationException::withMessages([
                'file' => 'File API UPA-TIK gagal disimpan ke storage.',
            ]);
        }

        return $this->createUploadRecord($path, [
            'user_id' => $userId,
            'original_name' => $originalName,
            'mime_type' => $this->mimeTypeForExtension($extension),
            'extension' => $extension,
            'size' => Storage::size($path),
            'status' => 'uploaded',
            'source' => 'api',
            'source_name' => $sourceName,
            'external_reference' => $externalReference,
        ]);
    }

    // Kirim file ke Flask ML API, simpan hasil prediksi, lalu kirim notifikasi jika aktif.
    public function detect(ScanUpload $upload, ?int $userId = null, array $options = []): DetectionResult
    {
        $processStartedAt = microtime(true);
        $apiBaseUrl = rtrim((string) config('services.ml_api.url'), '/');
        $apiKey = (string) config('services.ml_api.key');
        $timeout = (int) config('services.ml_api.timeout', 120);
        $sendEmail = (bool) ($options['send_email'] ?? true);
        $detectionUserId = $userId ?? $upload->user_id;

        $detectionResult = DetectionResult::create([
            'user_id' => $detectionUserId,
            'scan_upload_id' => $upload->id,
            'file_name' => $upload->original_name,
            'processed_at' => now(),
            'status' => 'Memproses',
        ]);

        $upload->update(['status' => 'processing']);

        try {
            $handle = Storage::readStream($upload->path);
        } catch (Throwable) {
            $handle = false;
        }

        if ($handle === false) {
            $this->failDetection(
                $detectionResult,
                $upload,
                'File yang sudah diupload tidak ditemukan di storage.'
            );
        }

        try {
            $response = Http::timeout($timeout)
                ->withHeaders(['X-API-KEY' => $apiKey])
                ->attach('file', $handle, $upload->original_name)
                ->post($apiBaseUrl.'/predict-file');
        } catch (ConnectionException) {
            $this->failDetection(
                $detectionResult,
                $upload,
                'Flask ML API belum bisa dihubungi. Pastikan service berjalan di '.$apiBaseUrl.'.'
            );
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if ($response->failed()) {
            $this->failDetection(
                $detectionResult,
                $upload,
                $response->json('message') ?: 'Prediksi gagal diproses oleh Flask ML API.'
            );
        }

        $payload = $response->json();
        $results = collect($payload['results'] ?? []);
        $total = (int) ($payload['total'] ?? $results->count());
        $summary = $this->summarizePrediction($results, $total, $payload);

        try {
            $resultsPath = $this->storePredictionResults($detectionResult, $upload, $results, $summary);
        } catch (Throwable) {
            $this->failDetection(
                $detectionResult,
                $upload,
                'Hasil deteksi gagal disimpan ke storage.'
            );
        }

        $detectionResult->update([
            'processed_at' => now(),
            'total' => $total,
            'normal_count' => $summary['normal'],
            'malware_count' => $summary['malware'],
            'unknown_count' => $summary['unknown'],
            'normal_percent' => $summary['normal_percent'],
            'malware_percent' => $summary['malware_percent'],
            'average_confidence' => $summary['average_confidence'],
            'status' => 'Selesai',
            'error_message' => null,
            'summary' => $summary,
            'results' => $results->take(100)->values()->all(),
            'results_path' => $resultsPath,
        ]);

        $upload->update(['status' => 'processed']);

        if ($sendEmail) {
            $this->sendDetectionCompletedEmail(
                $detectionResult->fresh(['scanUpload', 'user']),
                $summary,
                microtime(true) - $processStartedAt
            );
        }

        return $detectionResult->fresh(['scanUpload', 'user']);
    }

    // Buat record upload dari path storage dan metadata file.
    private function createUploadRecord(string $path, array $attributes): ScanUpload
    {
        return ScanUpload::create([
            ...$attributes,
            'stored_name' => basename($path),
            'path' => $path,
        ]);
    }

    // Bentuk path storage khusus upload dari API agar terpisah dari upload manual.
    private function apiStoragePath(string $originalName, string $extension): string
    {
        $baseName = pathinfo($originalName, PATHINFO_FILENAME) ?: 'upa-tik-traffic';
        $slug = Str::slug($baseName) ?: 'upa-tik-traffic';
        $safeExtension = trim(strtolower($extension), '.') ?: 'csv';

        return 'scan-uploads/api/'.now('Asia/Jakarta')->format('Y/m').'/'.Str::uuid().'-'.$slug.'.'.$safeExtension;
    }

    // Tentukan MIME type berdasarkan ekstensi file yang masuk dari API.
    private function mimeTypeForExtension(string $extension): string
    {
        return match (strtolower($extension)) {
            'csv' => 'text/csv',
            'txt' => 'text/plain',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            default => 'application/octet-stream',
        };
    }

    // Hitung ringkasan normal, malware, unknown, confidence, dan guardrail dari payload prediksi.
    private function summarizePrediction(Collection $results, int $total, array $payload = []): array
    {
        $normalLabel = (string) ($payload['normal_label'] ?? 'Normal');
        $resultLabels = $results
            ->groupBy(fn (array $result) => $result['label'] ?? 'Unknown')
            ->map(fn (Collection $items) => $items->count())
            ->all();
        $payloadLabels = collect($payload['summary'] ?? [])
            ->mapWithKeys(fn ($count, $label) => [(string) $label => (int) $count])
            ->filter(fn (int $count) => $count > 0)
            ->all();
        $labels = $payloadLabels !== [] && (array_sum($payloadLabels) >= $total || $results->count() !== $total)
            ? $payloadLabels
            : $resultLabels;

        $averageConfidence = $results->avg(fn (array $result) => (float) ($result['confidence'] ?? 0));
        $normal = (int) ($payload['total_normal'] ?? $labels[$normalLabel] ?? 0);
        $unknown = (int) ($labels['Unknown'] ?? 0);
        $malwareLabels = collect($labels)
            ->reject(fn ($count, string $label) => in_array($label, [$normalLabel, 'Unknown'], true))
            ->all();
        $malwareFromLabels = (int) array_sum($malwareLabels);
        $payloadMalware = array_key_exists('total_malware', $payload) ? (int) $payload['total_malware'] : null;
        $malware = $payloadMalware !== null && ($normal + $malwareFromLabels + $unknown) !== $total
            ? $payloadMalware
            : $malwareFromLabels;
        $guardrailAppliedCount = (int) ($payload['guardrail_applied_count'] ?? $results
            ->filter(fn (array $result) => (bool) ($result['blocked_guardrail_applied'] ?? false))
            ->count());

        return [
            'normal_label' => $normalLabel,
            'normal' => $normal,
            'malware' => $malware,
            'unknown' => $unknown,
            'normal_percent' => $total > 0 ? round(($normal / $total) * 100, 2) : 0,
            'malware_percent' => $total > 0 ? round(($malware / $total) * 100, 2) : 0,
            'average_confidence' => $averageConfidence ? round($averageConfidence * 100, 2) : 0,
            'labels' => $labels,
            'malware_labels' => $malwareLabels,
            'guardrail_applied_count' => $guardrailAppliedCount,
            'class_names' => $payload['class_names'] ?? [],
            'feature_columns' => $payload['feature_columns'] ?? [],
            'standardized_columns' => $payload['standardized_columns'] ?? [],
        ];
    }

    // Export seluruh hasil prediksi ke CSV agar bisa didownload tanpa membebani kolom JSON.
    private function storePredictionResults(DetectionResult $detectionResult, ScanUpload $upload, Collection $results, array $summary = []): string
    {
        $path = 'scan-results/'.$detectionResult->id.'-'.$this->downloadFileName($upload, 'hasil-deteksi', 'csv');
        $stream = fopen('php://temp', 'r+');

        fwrite($stream, "\xEF\xBB\xBF");
        $probabilityLabels = $this->probabilityLabels($results, (string) ($summary['normal_label'] ?? 'Normal'));

        fputcsv($stream, [
            'row_id',
            'label',
            'confidence_percent',
            'blocked_guardrail_applied',
            ...array_map(fn (string $label) => 'prob_'.Str::slug($label, '_').'_percent', $probabilityLabels),
        ]);

        foreach ($results as $index => $result) {
            fputcsv($stream, [
                $result['row'] ?? $index,
                $result['label'] ?? 'Unknown',
                $this->csvPercent($result['confidence'] ?? 0),
                (bool) ($result['blocked_guardrail_applied'] ?? false) ? '1' : '0',
                ...array_map(
                    fn (string $label) => $this->csvPercent(($result['probabilities'] ?? [])[$label] ?? 0),
                    $probabilityLabels
                ),
            ]);
        }

        rewind($stream);
        $stored = Storage::put($path, $stream);
        fclose($stream);

        if (! $stored) {
            throw ValidationException::withMessages([
                'file' => 'Hasil deteksi gagal disimpan ke storage.',
            ]);
        }

        return $path;
    }

    // Buat nama file hasil deteksi yang aman untuk download.
    private function downloadFileName(ScanUpload $upload, string $suffix, string $extension): string
    {
        $baseName = pathinfo($upload->original_name, PATHINFO_FILENAME) ?: 'riwayat';
        $slug = Str::slug($baseName) ?: 'riwayat';

        return $slug.'-'.$suffix.'.'.$extension;
    }

    // Ubah probabilitas desimal dari model menjadi persen untuk CSV.
    private function csvPercent($value): string
    {
        return number_format(((float) $value) * 100, 2, '.', '');
    }

    // Kumpulkan label probabilitas agar kolom export CSV stabil.
    private function probabilityLabels(Collection $results, string $normalLabel = 'Normal'): array
    {
        $labels = $results
            ->flatMap(fn (array $result) => array_keys($result['probabilities'] ?? []))
            ->unique()
            ->values();

        return $labels
            ->sortBy(fn (string $label) => $label === $normalLabel ? '0' : '1'.$label)
            ->values()
            ->all();
    }

    // Tandai proses gagal di database lalu kembalikan error validasi ke pemanggil.
    private function failDetection(DetectionResult $detectionResult, ScanUpload $upload, string $message): void
    {
        $detectionResult->update([
            'processed_at' => now(),
            'status' => 'Gagal',
            'error_message' => $message,
        ]);

        $upload->update(['status' => 'failed']);

        throw ValidationException::withMessages([
            'file' => $message,
        ]);
    }

    // Kirim email ringkasan hasil deteksi ke Petugas yang punya akses melihat data terkait.
    private function sendDetectionCompletedEmail(?DetectionResult $detectionResult, array $summary, float $durationSeconds): void
    {
        if (! $detectionResult) {
            return;
        }

        $detectionResult->loadMissing(['scanUpload.user', 'user']);
        $recipients = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'Petugas'))
            ->get()
            ->filter(fn (User $user) => $this->canReceiveDetectionCompletedEmail($user, $detectionResult))
            ->pluck('email');
        $recipients = $recipients
            ->filter(fn ($email) => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        try {
            Mail::to($recipients->all())->send(
                new DetectionCompletedMail($detectionResult, $summary, $durationSeconds)
            );
        } catch (Throwable $exception) {
            Log::warning('Detection completion email could not be sent.', [
                'detection_result_id' => $detectionResult->id,
                'recipients' => $recipients->all(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    // Pastikan penerima email memang Petugas dan boleh melihat hasil deteksi ini.
    private function canReceiveDetectionCompletedEmail(User $user, DetectionResult $detectionResult): bool
    {
        if (! $user->hasRole('Petugas') || ! $user->canAny(['detection.view', 'reports.view'])) {
            return false;
        }

        if ($user->can('history.view-any')) {
            return true;
        }

        $userId = (int) $user->getAuthIdentifier();

        return $userId === (int) $detectionResult->user_id
            || $userId === (int) $detectionResult->scanUpload?->user_id;
    }
}
