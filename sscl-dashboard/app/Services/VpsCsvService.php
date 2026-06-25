<?php

namespace App\Services;

use App\Models\DetectionResult;
use App\Models\ScanUpload;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class VpsCsvService
{
    // Upload isi CSV langsung ke disk VPS target.
    public function uploadCsv(string $filename, string $csvContent): bool
    {
        return Storage::disk($this->targetDisk())->put($filename, $csvContent);
    }

    // Hapus CSV di VPS target berdasarkan nama file.
    public function deleteCsv(string $filename): bool
    {
        return Storage::disk($this->targetDisk())->delete($filename);
    }

    // Cek apakah CSV tertentu sudah ada di VPS target.
    public function exists(string $filename): bool
    {
        return Storage::disk($this->targetDisk())->exists($filename);
    }

    // Ambil daftar file CSV dari folder sumber VPS.
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

    // Baca isi CSV dari disk VPS sumber.
    public function readCsv(string $path): string
    {
        $contents = Storage::disk($this->sourceDisk())->get($path);

        if ($contents === null) {
            throw new RuntimeException('CSV VPS tidak bisa dibaca: '.$path);
        }

        return $contents;
    }

    // Buat penanda unik dari isi file supaya proses bisa cek duplikasi.
    public function externalReference(string $contents): string
    {
        return 'vps:'.hash('sha256', $contents);
    }

    // Publish file upload asli dari storage lokal ke VPS target.
    public function publishOriginalUpload(ScanUpload $upload): string
    {
        $targetPath = $this->targetPath(
            (string) config('services.vps_csv.target_uploads_directory', 'scan-uploads'),
            $upload->stored_name ?: basename(str_replace('\\', '/', $upload->path))
        );

        $this->copyFromLocalStorage($upload->path, $targetPath);

        return $targetPath;
    }

    // Publish file hasil deteksi ke VPS target kalau hasil CSV-nya tersedia.
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

    // Hapus CSV sumber setelah selesai diproses jika config mengaktifkannya.
    public function deleteSourceCsv(string $path): bool
    {
        return Storage::disk($this->sourceDisk())->delete($path);
    }

    // Salin file dari storage lokal ke disk VPS target menggunakan stream.
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

    // Bentuk path target yang bersih dari slash ganda dan path folder lokal.
    private function targetPath(string $directory, string $filename): string
    {
        $directory = $this->normalizeDirectory($directory);
        $filename = basename(str_replace('\\', '/', $filename));

        return $directory === '' ? $filename : $directory.'/'.$filename;
    }

    // Normalisasi nama folder VPS supaya konsisten memakai slash biasa.
    private function normalizeDirectory(string $directory): string
    {
        return trim(str_replace('\\', '/', $directory), '/');
    }

    // Ambil nama disk sumber dari config service.
    private function sourceDisk(): string
    {
        return (string) config('services.vps_csv.source_disk', 'vps_dataset');
    }

    // Ambil nama disk target dari config service.
    private function targetDisk(): string
    {
        return (string) config('services.vps_csv.target_disk', 'vps_csv');
    }
}
