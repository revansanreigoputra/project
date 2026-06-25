<?php

namespace App\Http\Controllers;

use App\Models\DetectionResult;
use App\Models\ScanUpload;
use App\Services\TrafficDetectionService;
use App\Support\DetectionResultTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ScanJobController extends Controller
{
    // Tampilkan halaman upload scan sekaligus hasil prediksi terakhir yang bisa dilihat user.
    public function index()
    {
        return view('pages.scan.index', [
            'prediction' => $this->latestPredictionFor(request()),
        ]);
    }

    // Simpan file upload dulu lewat AJAX supaya user dapat status sebelum proses deteksi.
    public function upload(Request $request, TrafficDetectionService $trafficDetectionService): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:20480'],
        ]);

        $upload = $trafficDetectionService->storeManualUpload(
            $validated['file'],
            $request->user()?->getAuthIdentifier()
        );

        return response()->json([
            'id' => $upload->id,
            'file_name' => $upload->original_name,
            'status' => $upload->status,
            'message' => 'File tersimpan di database.',
        ]);
    }

    // Validasi input lalu jalankan deteksi dari file baru atau upload yang sudah tersimpan.
    public function store(Request $request, TrafficDetectionService $trafficDetectionService)
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

        $upload = $this->resolveUpload($request, $validated, $trafficDetectionService);
        $trafficDetectionService->detect($upload, $request->user()?->getAuthIdentifier());

        return redirect()->route('scan.index');
    }

    // Ambil prediksi selesai terbaru untuk ditampilkan ulang di halaman scan.
    private function latestPredictionFor(Request $request): ?array
    {
        $detectionResult = DetectionResult::with('scanUpload')
            ->visibleTo($request->user())
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

    // Tentukan file yang dipakai: upload lama milik user atau file baru dari form.
    private function resolveUpload(
        Request $request,
        array $validated,
        TrafficDetectionService $trafficDetectionService
    ): ScanUpload
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

        return $trafficDetectionService->storeManualUpload(
            $validated['file'],
            $request->user()?->getAuthIdentifier()
        );
    }
}
