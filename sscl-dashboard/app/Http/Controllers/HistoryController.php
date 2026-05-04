<?php

namespace App\Http\Controllers;

use App\Models\ScanJob;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(Request $request)
    {
        $history = ScanJob::with('scanUpload')
            ->forUser($request->user())
            ->latest('processed_at')
            ->latest('id')
            ->limit(25)
            ->get()
            ->map(fn (ScanJob $scanJob) => $scanJob->toHistoryArray());

        return view('pages.riwayat.index', [
            'history' => $history,
        ]);
    }
}
