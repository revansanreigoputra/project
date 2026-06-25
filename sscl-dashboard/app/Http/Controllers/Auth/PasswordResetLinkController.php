<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class PasswordResetLinkController extends Controller
{
    // Tampilkan form permintaan link reset password.
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Kirim link reset password ke email user.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Kirim email reset dan tangani error SMTP supaya user dapat pesan yang jelas.
        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );
        } catch (Throwable $exception) {
            Log::warning('Password reset email could not be sent.', [
                'email' => $request->input('email'),
                'error' => $exception->getMessage(),
            ]);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Email reset password belum bisa dikirim. Periksa konfigurasi SMTP aplikasi.']);
        }

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
