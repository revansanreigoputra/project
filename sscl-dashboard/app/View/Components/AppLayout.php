<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    // Render layout utama untuk halaman user yang sudah login.
    public function render(): View
    {
        return view('layouts.app');
    }
}
