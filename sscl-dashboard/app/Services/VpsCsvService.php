<?php

namespace App\Services;

use App\Models\DetectionResult;
use App\Models\ScanUpload;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class VpsCsvService
{
    public function uploadCsv(string $filename, string $csvContent): bool
    {
        return Storage::disk($this->targetDisk())->put($filename, $csvContent);
    }

    public function deleteCsv(string $filename): bool
    {
        return Storage::disk($this->targetDisk())->delete($filename);
    }

    public function exists(string $filename): bool
    {
        return Storage::disk($this->targetDisk())->exists($filename);
    }

    public function csvFiles(?int $limit = null): array
    {
        $directory = $this->normalizeDirectory(
            (string) config('services.vps_csv.source_directory', '')
        );
        $files = Storage::disk($this->sourceDisk())->files($directory ?: null);

        $csvFiles = collect($files)
            ->filter(fn (string $path) => Str::endsWith(strtolower($path), '.csv'))
            ->sort()
            ->values();

        if ($limit && $limit > 0) {
            $csvFiles = $csvFiles->take($limit);
        }

        return $csvFiles->all();
    }

    public function readCsv(string $path): string
    {
        $contents = Storage::disk($this->sourceDisk())->get($path);

        if ($contents === null) {
            throw new RuntimeException('CSV VPS tidak bisa dibaca: '.$path);
        }

        return $contents;
    }

    public function externalReference(string $contents): string
    {
        return 'vps:'.hash('sha256', $contents);
    }

    public function publishOriginalUpload(ScanUpload $upload): string
    {
        $targetPath = $this->targetPath(
            (string) config('services.vps_csv.target_uploads_directory', 'scan-uploads'),
            $upload->stored_name ?: basename(str_replace('\\', '/', $upload->path))
        );

        $this->copyFromLocalStorage($upload->path, $targetPath);

        return $targetPath;
    }

    public function publishDetectionResult(DetectionResult $detectionResult): ?string
    {
        if (! $detectionResult->results_path) {
            return null;
        }

        $targetPath = $this->targetPath(
            (string) config('services.vps_csv.target_results_directory', 'scan-results'),
            basename(str_replace('\\', '/', $detectionResult->results_path))
        );

        $this->copyFromLocalStorage($detectionResult->results_path, $targetPath);

        return $targetPath;
    }

    public function deleteSourceCsv(string $path): bool
    {
        return Storage::disk($this->sourceDisk())->delete($path);
    }

    private function copyFromLocalStorage(string $sourcePath, string $targetPath): void
    {
        $stream = Storage::readStream($sourcePath);

        if ($stream === false) {
            throw new RuntimeException('File lokal tidak ditemukan: '.$sourcePath);
        }

        try {
            $stored = Storage::disk($this->targetDisk())->put($targetPath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $stored) {
            throw new RuntimeException('File gagal disimpan ke VPS target: '.$targetPath);
        }
    }

    private function targetPath(string $directory, string $filename): string
    {
        $directory = $this->normalizeDirectory($directory);
        $filename = basename(str_replace('\\', '/', $filename));

        return $directory === '' ? $filename : $directory.'/'.$filename;
    }

    private function normalizeDirectory(string $directory): string
    {
        return trim(str_replace('\\', '/', $directory), '/');
    }

    private function sourceDisk(): string
    {
        return (string) config('services.vps_csv.source_disk', 'vps_dataset');
    }

    private function targetDisk(): string
    {
        return (string) config('services.vps_csv.target_disk', 'vps_csv');
    }
}
