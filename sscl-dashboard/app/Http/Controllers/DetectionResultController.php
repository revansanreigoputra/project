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
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
        ]);

        $detectionResult = DetectionResult::with('scanUpload')
            ->forUser($request->user())
            ->completed()
            ->latest('processed_at')
            ->latest('id')
            ->first();
        $prediction = $detectionResult?->toPredictionArray();
        $table = null;

        if ($detectionResult && $prediction) {
            $table = DetectionResultTable::make($detectionResult, [
                'search' => trim((string) ($filters['search'] ?? '')),
                'label' => $filters['label'] ?? 'all',
                'per_page' => $filters['per_page'] ?? 10,
            ]);
            $prediction['summary'] = $table['summary'];
        }

        return view('pages.hasildeteksi.index', [
            'prediction' => $prediction,
            'resultsPaginator' => $table['paginator'] ?? null,
            'labelOptions' => $table['label_options'] ?? collect(['Normal']),
            'tableFilters' => [
                'search' => trim((string) ($filters['search'] ?? '')),
                'label' => $filters['label'] ?? 'all',
                'per_page' => DetectionResultTable::perPage($filters['per_page'] ?? null),
            ],
            'perPageOptions' => DetectionResultTable::PER_PAGE_OPTIONS,
        ]);
    }
}
