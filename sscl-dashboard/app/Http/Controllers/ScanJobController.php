<?php

namespace App\Http\Controllers;

use App\Models\DetectionResult;
use App\Models\ScanUpload;
use App\Support\DetectionResultTable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ScanJobController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('pages.scan.index', [
            'prediction' => $this->latestPredictionFor(request()),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:20480'],
        ]);

        $upload = $this->persistUpload($validated['file'], $request->user()?->getAuthIdentifier());

        return response()->json([
            'id' => $upload->id,
            'file_name' => $upload->original_name,
            'status' => $upload->status,
            'message' => 'File tersimpan di database.',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'scan_upload_id' => ['nullable', 'integer', 'exists:scan_uploads,id'],
            'file' => ['nullable', 'file', 'mimes:csv,txt,xlsx,xls', 'max:20480'],
        ]);

        if (! $request->filled('scan_upload_id') && ! $request->hasFile('file')) {
            throw ValidationException::withMessages([
                'file' => 'Pilih file terlebih dahulu sebelum proses deteksi.',
            ]);
        }

        $upload = $this->resolveUpload($request, $validated);
        $apiBaseUrl = rtrim((string) config('services.ml_api.url'), '/');
        $apiKey = (string) config('services.ml_api.key');
        $timeout = (int) config('services.ml_api.timeout', 120);

        $detectionResult = DetectionResult::create([
            'user_id' => $request->user()?->getAuthIdentifier(),
            'scan_upload_id' => $upload->id,
            'file_name' => $upload->original_name,
            'processed_at' => now(),
            'status' => 'Memproses',
        ]);

        $upload->update(['status' => 'processing']);

        try {
            $handle = Storage::readStream($upload->path);
        } catch (Throwable $exception) {
            $handle = false;
        }

        if ($handle === false) {
            return $this->markScanAsFailed(
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
        } catch (ConnectionException $exception) {
            return $this->markScanAsFailed(
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
            return $this->markScanAsFailed(
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
            $resultsPath = $this->storePredictionResults($detectionResult, $upload, $results);
        } catch (Throwable $exception) {
            return $this->markScanAsFailed(
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

        return redirect()->route('scan.index');
    }

    private function summarizePrediction(Collection $results, int $total, array $payload = []): array
    {
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
        $normal = (int) ($payload['total_normal'] ?? $labels['Normal'] ?? 0);
        $unknown = (int) ($labels['Unknown'] ?? 0);
        $malwareLabels = collect($labels)
            ->reject(fn ($count, string $label) => in_array($label, ['Normal', 'Unknown'], true))
            ->all();
        $malwareFromLabels = (int) array_sum($malwareLabels);
        $payloadMalware = array_key_exists('total_malware', $payload) ? (int) $payload['total_malware'] : null;
        $malware = $payloadMalware !== null && ($normal + $malwareFromLabels + $unknown) !== $total
            ? $payloadMalware
            : $malwareFromLabels;

        return [
            'normal' => $normal,
            'malware' => $malware,
            'unknown' => $unknown,
            'normal_percent' => $total > 0 ? round(($normal / $total) * 100, 2) : 0,
            'malware_percent' => $total > 0 ? round(($malware / $total) * 100, 2) : 0,
            'average_confidence' => $averageConfidence ? round($averageConfidence * 100, 2) : 0,
            'labels' => $labels,
            'malware_labels' => $malwareLabels,
        ];
    }

    private function storePredictionResults(DetectionResult $detectionResult, ScanUpload $upload, Collection $results): string
    {
        $path = 'scan-results/'.$detectionResult->id.'-'.$this->downloadFileName($upload, 'hasil-deteksi', 'csv');
        $stream = fopen('php://temp', 'r+');

        fwrite($stream, "\xEF\xBB\xBF");
        $probabilityLabels = $this->probabilityLabels($results);

        fputcsv($stream, [
            'row_id',
            'label',
            'confidence_percent',
            ...array_map(fn (string $label) => 'prob_'.Str::slug($label, '_').'_percent', $probabilityLabels),
        ]);

        foreach ($results as $index => $result) {
            fputcsv($stream, [
                $result['row'] ?? $index,
                $result['label'] ?? 'Unknown',
                $this->csvPercent($result['confidence'] ?? 0),
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

    private function downloadFileName(ScanUpload $upload, string $suffix, string $extension): string
    {
        $baseName = pathinfo($upload->original_name, PATHINFO_FILENAME) ?: 'riwayat';
        $slug = Str::slug($baseName) ?: 'riwayat';

        return $slug.'-'.$suffix.'.'.$extension;
    }

    private function csvPercent($value): string
    {
        return number_format(((float) $value) * 100, 2, '.', '');
    }

    private function probabilityLabels(Collection $results): array
    {
        $labels = $results
            ->flatMap(fn (array $result) => array_keys($result['probabilities'] ?? []))
            ->unique()
            ->values();

        return $labels
            ->sortBy(fn (string $label) => $label === 'Normal' ? '0' : '1'.$label)
            ->values()
            ->all();
    }

    private function latestPredictionFor(Request $request): ?array
    {
        $detectionResult = DetectionResult::with('scanUpload')
            ->forUser($request->user())
            ->completed()
            ->latest('processed_at')
            ->latest('id')
            ->first();
        $prediction = $detectionResult?->toPredictionArray();

        if ($detectionResult && $prediction) {
            $prediction['summary'] = DetectionResultTable::summary($detectionResult);
        }

        return $prediction;
    }

    private function persistUpload(UploadedFile $file, ?int $userId): ScanUpload
    {
        $path = $file->store('scan-uploads');

        if (! $path) {
            throw ValidationException::withMessages([
                'file' => 'File gagal disimpan ke storage.',
            ]);
        }

        return ScanUpload::create([
            'user_id' => $userId,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => basename($path),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'extension' => $file->getClientOriginalExtension(),
            'size' => $file->getSize() ?: 0,
            'status' => 'uploaded',
        ]);
    }

    private function resolveUpload(Request $request, array $validated): ScanUpload
    {
        if ($request->filled('scan_upload_id')) {
            $upload = ScanUpload::whereKey($validated['scan_upload_id'])
                ->where('user_id', $request->user()?->getAuthIdentifier())
                ->first();

            if (! $upload) {
                throw ValidationException::withMessages([
                    'file' => 'Data upload tidak ditemukan untuk pengguna ini.',
                ]);
            }

            return $upload;
        }

        return $this->persistUpload($validated['file'], $request->user()?->getAuthIdentifier());
    }

    private function markScanAsFailed(DetectionResult $detectionResult, ScanUpload $upload, string $message): RedirectResponse
    {
        $detectionResult->update([
            'processed_at' => now(),
            'status' => 'Gagal',
            'error_message' => $message,
        ]);

        $upload->update(['status' => 'failed']);

        return back()->withErrors([
            'file' => $message,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(DetectionResult $detectionResult)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DetectionResult $detectionResult)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DetectionResult $detectionResult)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DetectionResult $detectionResult)
    {
        //
    }
}
