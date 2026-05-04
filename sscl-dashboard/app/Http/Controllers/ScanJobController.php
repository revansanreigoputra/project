<?php

namespace App\Http\Controllers;

use App\Models\ScanJob;
use App\Models\ScanUpload;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
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

        $scanJob = ScanJob::create([
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
                $scanJob,
                $upload,
                'File yang sudah diupload tidak ditemukan di storage.'
            );
        }

        try {
            $response = Http::timeout($timeout)
                ->withHeaders(['X-API-KEY' => $apiKey])
                ->attach('file', $handle, $upload->original_name)
                ->post($apiBaseUrl . '/predict-file');
        } catch (ConnectionException $exception) {
            return $this->markScanAsFailed(
                $scanJob,
                $upload,
                'Flask ML API belum bisa dihubungi. Pastikan service berjalan di ' . $apiBaseUrl . '.'
            );
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if ($response->failed()) {
            return $this->markScanAsFailed(
                $scanJob,
                $upload,
                $response->json('message') ?: 'Prediksi gagal diproses oleh Flask ML API.'
            );
        }

        $payload = $response->json();
        $results = collect($payload['results'] ?? []);
        $total = (int) ($payload['total'] ?? $results->count());
        $summary = $this->summarizePrediction($results, $total);

        $scanJob->update([
            'processed_at' => now(),
            'total' => $total,
            'benign_count' => $summary['benign'],
            'malicious_count' => $summary['malicious'],
            'unknown_count' => $summary['unknown'],
            'benign_percent' => $summary['benign_percent'],
            'malicious_percent' => $summary['malicious_percent'],
            'average_confidence' => $summary['average_confidence'],
            'status' => 'Selesai',
            'error_message' => null,
            'summary' => $summary,
            'results' => $results->take(100)->values()->all(),
        ]);

        $upload->update(['status' => 'processed']);

        return redirect()->route('scan.index');
    }

    private function summarizePrediction(Collection $results, int $total): array
    {
        $labels = $results
            ->groupBy(fn (array $result) => $result['label'] ?? 'Unknown')
            ->map(fn (Collection $items) => $items->count())
            ->all();

        $averageConfidence = $results->avg(fn (array $result) => (float) ($result['confidence'] ?? 0));

        return [
            'benign' => $labels['Benign'] ?? 0,
            'malicious' => $labels['Malicious'] ?? 0,
            'unknown' => $labels['Unknown'] ?? 0,
            'benign_percent' => $total > 0 ? round((($labels['Benign'] ?? 0) / $total) * 100, 2) : 0,
            'malicious_percent' => $total > 0 ? round((($labels['Malicious'] ?? 0) / $total) * 100, 2) : 0,
            'average_confidence' => $averageConfidence ? round($averageConfidence * 100, 2) : 0,
        ];
    }

    private function latestPredictionFor(Request $request): ?array
    {
        return ScanJob::with('scanUpload')
            ->forUser($request->user())
            ->completed()
            ->latest('processed_at')
            ->latest('id')
            ->first()
            ?->toPredictionArray();
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

    private function markScanAsFailed(ScanJob $scanJob, ScanUpload $upload, string $message): RedirectResponse
    {
        $scanJob->update([
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
    public function show(ScanJob $scanJob)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ScanJob $scanJob)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ScanJob $scanJob)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ScanJob $scanJob)
    {
        //
    }
}
