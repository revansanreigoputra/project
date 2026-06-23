<?php

namespace App\Http\Controllers;

use App\Models\DetectionResult;
use App\Support\DetectionResultTable;
use Illuminate\Http\Request;

class DetectionResultController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'label' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'string', 'in:row,label,confidence'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
        ]);

        $baseQuery = DetectionResult::query()
            ->visibleTo($request->user())
            ->completed();
        $detectionResult = (clone $baseQuery)
            ->with(['scanUpload.user', 'user'])
            ->latest('processed_at')
            ->latest('id')
            ->first();
        $prediction = $detectionResult?->toPredictionArray();
        $table = null;

        if ($detectionResult && $prediction) {
            $table = DetectionResultTable::make($detectionResult, [
                'search' => trim((string) ($filters['search'] ?? '')),
                'label' => $filters['label'] ?? 'all',
                'sort' => $filters['sort'] ?? 'row',
                'direction' => $filters['direction'] ?? 'asc',
                'per_page' => $filters['per_page'] ?? 10,
            ]);
            $prediction['summary'] = $table['summary'];
        }

        $recentDetections = (clone $baseQuery)
            ->with(['scanUpload.user', 'user'])
            ->latest('processed_at')
            ->latest('id')
            ->take(8)
            ->get()
            ->map(fn (DetectionResult $result) => $this->detectionSummary($result))
            ->values();
        $completedResults = (clone $baseQuery)->get([
            'id',
            'total',
            'normal_count',
            'malware_count',
            'unknown_count',
            'average_confidence',
        ]);
        $totalRows = (int) $completedResults->sum('total');

        return view('pages.hasildeteksi.index', [
            'detectionResult' => $detectionResult,
            'prediction' => $prediction,
            'resultsPaginator' => $table['paginator'] ?? null,
            'labelOptions' => $table['label_options'] ?? collect(['Normal']),
            'tableFilters' => [
                'search' => trim((string) ($filters['search'] ?? '')),
                'label' => $filters['label'] ?? 'all',
                'sort' => DetectionResultTable::sort($filters['sort'] ?? null),
                'direction' => DetectionResultTable::direction($filters['direction'] ?? null),
                'per_page' => DetectionResultTable::perPage($filters['per_page'] ?? null),
            ],
            'perPageOptions' => DetectionResultTable::PER_PAGE_OPTIONS,
            'recentDetections' => $recentDetections,
            'historySummary' => [
                'total_detections' => $completedResults->count(),
                'total_rows' => $totalRows,
                'normal_rows' => (int) $completedResults->sum('normal_count'),
                'malware_rows' => (int) $completedResults->sum('malware_count'),
                'unknown_rows' => (int) $completedResults->sum('unknown_count'),
                'average_confidence' => round((float) ($completedResults->avg('average_confidence') ?: 0), 2),
                'malware_percent' => $totalRows > 0 ? round(((int) $completedResults->sum('malware_count') / $totalRows) * 100, 2) : 0,
            ],
        ]);
    }

    public function show(Request $request, DetectionResult $detectionResult)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'label' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'string', 'in:row,label,confidence'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
        ]);

        $detectionResult = DetectionResult::with(['scanUpload.user', 'user'])
            ->visibleTo($request->user())
            ->whereKey($detectionResult->getKey())
            ->firstOrFail();
        $table = DetectionResultTable::make($detectionResult, [
            'search' => trim((string) ($filters['search'] ?? '')),
            'label' => $filters['label'] ?? 'all',
            'sort' => $filters['sort'] ?? 'row',
            'direction' => $filters['direction'] ?? 'asc',
            'per_page' => $filters['per_page'] ?? 10,
        ]);
        $prediction = $detectionResult->toPredictionArray();
        $prediction['summary'] = $table['summary'];

        return view('pages.hasildeteksi.show', [
            'detectionResult' => $detectionResult,
            'prediction' => $prediction,
            'resultsPaginator' => $table['paginator'],
            'labelOptions' => $table['label_options'],
            'tableFilters' => [
                'search' => trim((string) ($filters['search'] ?? '')),
                'label' => $filters['label'] ?? 'all',
                'sort' => DetectionResultTable::sort($filters['sort'] ?? null),
                'direction' => DetectionResultTable::direction($filters['direction'] ?? null),
                'per_page' => DetectionResultTable::perPage($filters['per_page'] ?? null),
            ],
            'perPageOptions' => DetectionResultTable::PER_PAGE_OPTIONS,
        ]);
    }

    private function detectionSummary(DetectionResult $result): array
    {
        $summary = DetectionResultTable::summary($result);
        $total = (int) ($result->total ?? 0);
        $normal = (int) ($summary['normal'] ?? 0);
        $malware = (int) ($summary['malware'] ?? 0);

        return [
            'id' => $result->id,
            'file_name' => $result->file_name ?? $result->scanUpload?->original_name ?? '-',
            'source' => $result->scanUpload?->source ?? 'manual',
            'source_label' => $result->scanUpload?->sourceLabel() ?? 'Manual',
            'processed_at' => $this->formatDateTime($result->processed_at ?? $result->created_at),
            'total' => $total,
            'normal' => $normal,
            'malware' => $malware,
            'unknown' => (int) ($summary['unknown'] ?? 0),
            'normal_percent' => $total > 0 ? number_format(($normal / $total) * 100, 2, ',', '.') : '0,00',
            'malware_percent' => $total > 0 ? number_format(($malware / $total) * 100, 2, ',', '.') : '0,00',
            'average_confidence' => number_format((float) ($summary['average_confidence'] ?? $result->average_confidence ?? 0), 2, ',', '.'),
            'status' => $result->status,
            'user' => $result->user?->name ?? $result->scanUpload?->user?->name ?? '-',
            'url' => route('hasildeteksi.show', $result),
            'download_url' => $result->scan_upload_id ? route('riwayat.download', ['scanUpload' => $result->scan_upload_id, 'mode' => 'after']) : null,
        ];
    }

    private function formatDateTime($dateTime): string
    {
        return $dateTime
            ? $dateTime->copy()->timezone('Asia/Jakarta')->format('d/m/Y H:i')
            : '-';
    }
}