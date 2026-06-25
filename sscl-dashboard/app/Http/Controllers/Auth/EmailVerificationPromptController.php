<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\AccessRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationPromptController extends Controller
{
    // Tampilkan halaman verifikasi email, atau langsung redirect kalau sudah terverifikasi.
    public function __invoke(Request $request): RedirectResponse|View
    {
        return $request->user()->hasVerifiedEmail()
                    ? redirect()->route(AccessRedirect::routeFor($request->user()))
                    : view('auth.verify-email');
    }
}
