<?php

namespace App\Http\Controllers;

use App\Models\DetectionResult;
use App\Support\DetectionResultTable;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
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

        return view('pages.laporan.index', [
            'prediction' => $prediction,
        ]);
    }
}
