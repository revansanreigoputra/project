<?php

namespace App\Http\Controllers;

use App\Models\ScanJob;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ScanJobController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('pages.scan.index', [
            'prediction' => session('last_prediction'),
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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:20480'],
        ]);

        $file = $validated['file'];
        $apiBaseUrl = rtrim((string) config('services.ml_api.url'), '/');
        $apiKey = (string) config('services.ml_api.key');
        $timeout = (int) config('services.ml_api.timeout', 120);

        $handle = fopen($file->getRealPath(), 'r');

        try {
            $response = Http::timeout($timeout)
                ->withHeaders(['X-API-KEY' => $apiKey])
                ->attach('file', $handle, $file->getClientOriginalName())
                ->post($apiBaseUrl . '/predict-file');
        } catch (ConnectionException $exception) {
            return back()->withErrors([
                'file' => 'Flask ML API belum bisa dihubungi. Pastikan service berjalan di ' . $apiBaseUrl . '.',
            ]);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if ($response->failed()) {
            return back()->withErrors([
                'file' => $response->json('message') ?: 'Prediksi gagal diproses oleh Flask ML API.',
            ]);
        }

        $payload = $response->json();
        $results = collect($payload['results'] ?? []);
        $summary = $this->summarizePrediction($results, (int) ($payload['total'] ?? $results->count()));

        $prediction = [
            'file_name' => $file->getClientOriginalName(),
            'total' => (int) ($payload['total'] ?? $results->count()),
            'summary' => $summary,
            'results' => $results->take(100)->values()->all(),
        ];

        session(['last_prediction' => $prediction]);

        return view('pages.scan.index', [
            'prediction' => $prediction,
        ]);
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
