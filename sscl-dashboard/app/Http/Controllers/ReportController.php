<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ReportController extends Controller
{
    // Halaman laporan sementara diarahkan ke hasil deteksi dengan query filter yang sama.
    public function index(Request $request)
    {
        return redirect()->route('hasildeteksi.index', $request->query());
    }
}
