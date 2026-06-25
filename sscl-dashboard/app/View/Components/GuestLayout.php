<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class GuestLayout extends Component
{
    // Render layout untuk halaman guest seperti login dan register.
    public function render(): View
    {
        return view('layouts.guest');
    }
}
