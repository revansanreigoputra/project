<?php

namespace App\Http\Controllers;

use App\Models\ScanJob;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $prediction = ScanJob::with('scanUpload')
            ->forUser($request->user())
            ->completed()
            ->latest('processed_at')
            ->latest('id')
            ->first()
            ?->toPredictionArray();

        return view('pages.dashboard.index', [
            'prediction' => $prediction,
        ]);
    }
}
