@extends('layouts.auth')

@section('title', 'Register')

@php
  $features = [
    'Akses dashboard operator',
    'Kelola traffic logs & dataset',
    'Tampilan konsisten (light/dark)',
  ];
@endphp

@section('page')
  <div class="h-[100dvh] w-full bg-app relative overflow-hidden flex items-center justify-center p-4 sm:p-6">
    {{-- Background Blobs --}}
    <div class="pointer-events-none absolute -top-24 -left-24 h-72 w-72 rounded-full bg-primary-soft blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-24 -right-24 h-80 w-80 rounded-full bg-primary-soft blur-3xl"></div>

    {{-- Main Card --}}
    <div class="w-full max-w-4xl h-full max-h-[680px] flex flex-row bg-surface border border-app rounded-3xl shadow-soft overflow-hidden z-10">
      
      {{-- LEFT PANEL --}}
      <div class="hidden lg:flex w-1/2 flex-col justify-between bg-primary-soft p-8 relative overflow-hidden">
        <div class="flex items-center gap-3">
          <div class="h-11 w-11 rounded-2xl border border-app shadow-soft grid place-items-center overflow-hidden">
            <img src="{{ asset('images/logo.png') }}" alt="UPA-TIK" class="h-full w-full object-cover">
          </div>
          <div class="leading-tight">
            <div class="text-sm font-semibold tracking-wide">UPA-TIK</div>
            <div class="text-xs text-muted">SSCL Malware Detection</div>
          </div>
        </div>

        <div>
          <h1 class="text-3xl font-semibold leading-tight">
            Buat akun <span class="text-primary">Operator</span>
          </h1>
          <p class="mt-3 text-muted max-w-sm">
            Daftarkan akun untuk mengakses dashboard monitoring malware.
          </p>

          {{-- <div class="mt-6 space-y-3">
            @foreach ($features as $feature)
              <div class="flex items-center gap-3">
                <span class="h-2 w-2 rounded-full bg-primary"></span>
                <span class="text-sm text-muted">{{ $feature }}</span>
              </div>
            @endforeach
          </div> --}}
        </div>

        <div class="text-xs text-muted">
          © {{ date('Y') }} UPA-TIK • Internal System
        </div>
      </div>

      {{-- RIGHT PANEL --}}
      <div class="w-full lg:w-1/2 flex flex-col p-6 sm:p-8 h-full overflow-y-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
        
        {{-- Header (Brand Mobile & Theme Toggle) --}}
        <div class="flex items-center justify-between shrink-0">
          <div class="flex items-center gap-3 lg:hidden">
            <div class="h-10 w-10 rounded-2xl bg-surface border border-app shadow-soft grid place-items-center overflow-hidden">
              <img src="{{ asset('images/logo.png') }}" alt="UPA-TIK" class="h-full w-full object-cover">
            </div>
            <div class="leading-tight">
              <div class="text-sm font-semibold tracking-wide">UPA-TIK</div>
            </div>
          </div>

          <button type="button" @click="toggleTheme()" class="rounded-2xl bg-surface shadow-soft p-1 focus-visible:outline-none focus-visible:ring-2 ring-app ml-auto" aria-label="Toggle theme">
            <div class="flex items-center gap-1 rounded-xl bg-surface2 p-1">
              <span class="grid h-8 w-8 place-items-center rounded-xl transition-colors" :class="theme === 'light' ? 'bg-primary text-white' : 'text-muted'">
                <x-lucide-sun class="h-4 w-4" />
              </span>
              <span class="grid h-8 w-8 place-items-center rounded-xl transition-colors" :class="theme === 'dark' ? 'bg-primary text-white' : 'text-muted'">
                <x-lucide-moon class="h-4 w-4" />
              </span>
            </div>
          </button>
        </div>

        {{-- Title & Error --}}
        <div class="mt-4 sm:mt-6 shrink-0">
          <h2 class="text-2xl font-semibold">Register</h2>
          @if ($errors->any())
            <p class="mt-1 text-sm font-medium text-[color:var(--danger)] leading-snug">
              {{ $errors->first() }}
            </p>
          @else
            <p class="mt-1 text-sm text-muted leading-snug">
              Buat akun baru untuk sistem.
            </p>
          @endif
        </div>

        {{-- Form Area --}}
        <form method="POST" action="{{ route('register') }}" class="mt-5 flex flex-col gap-3.5 flex-1">
          @csrf

          {{-- NAME --}}
          <div class="flex flex-col gap-1.5 shrink-0">
            <label class="text-sm font-medium">Name</label>
            <div class="flex items-center rounded-2xl bg-surface2 border px-4 py-2.5 transition-[border-color,box-shadow] duration-200 @error('name') border-[color:var(--danger)] ring-1 ring-[color:var(--danger)] @else border-app focus-within:border-[color:var(--primary)] focus-within:ring-2 focus-within:ring-inset focus-within:ring-[color:var(--primary-soft)] @enderror">
              <input name="name" type="text" value="{{ old('name') }}" required autofocus placeholder="Nama lengkap" class="w-full bg-transparent border-0 ring-0 outline-none focus:ring-0 focus:border-0 text-sm text-app placeholder:text-muted" />
            </div>
          </div>

          {{-- EMAIL --}}
          <div class="flex flex-col gap-1.5 shrink-0">
            <label class="text-sm font-medium">Email</label>
            <div class="flex items-center rounded-2xl bg-surface2 border px-4 py-2.5 transition-[border-color,box-shadow] duration-200 @error('email') border-[color:var(--danger)] ring-1 ring-[color:var(--danger)] @else border-app focus-within:border-[color:var(--primary)] focus-within:ring-2 focus-within:ring-inset focus-within:ring-[color:var(--primary-soft)] @enderror">
              <input name="email" type="email" value="{{ old('email') }}" autocomplete="email" required placeholder="admin@upa-tik.go.id" class="w-full bg-transparent border-0 ring-0 outline-none focus:ring-0 focus:border-0 text-sm text-app placeholder:text-muted" />
            </div>
          </div>

          {{-- PASSWORD --}}
          <div x-data="{ show:false }" class="flex flex-col gap-1.5 shrink-0">
            <label class="text-sm font-medium">Password</label>
            <div class="flex items-center gap-2 rounded-2xl bg-surface2 border px-4 py-2.5 transition-[border-color,box-shadow] duration-200 @error('password') border-[color:var(--danger)] ring-1 ring-[color:var(--danger)] @else border-app focus-within:border-[color:var(--primary)] focus-within:ring-2 focus-within:ring-inset focus-within:ring-[color:var(--primary-soft)] @enderror">
              <input name="password" :type="show ? 'text' : 'password'" autocomplete="new-password" required placeholder="••••••••" class="w-full bg-transparent border-0 ring-0 outline-none focus:ring-0 focus:border-0 text-sm text-app placeholder:text-muted" />
              <button type="button" @click="show = !show" class="shrink-0 text-sm text-muted hover:text-app transition">
                <span x-text="show ? 'Hide' : 'Show'"></span>
              </button>
            </div>
          </div>

          {{-- CONFIRM PASSWORD --}}
          <div x-data="{ show:false }" class="flex flex-col gap-1.5 shrink-0">
            <label class="text-sm font-medium">Confirm Password</label>
            <div class="flex items-center gap-2 rounded-2xl bg-surface2 border px-4 py-2.5 transition-[border-color,box-shadow] duration-200 border-app focus-within:border-[color:var(--primary)] focus-within:ring-2 focus-within:ring-inset focus-within:ring-[color:var(--primary-soft)]">
              <input name="password_confirmation" :type="show ? 'text' : 'password'" autocomplete="new-password" required placeholder="••••••••" class="w-full bg-transparent border-0 ring-0 outline-none focus:ring-0 focus:border-0 text-sm text-app placeholder:text-muted" />
              <button type="button" @click="show = !show" class="shrink-0 text-sm text-muted hover:text-app transition">
                <span x-text="show ? 'Hide' : 'Show'"></span>
              </button>
            </div>
          </div>

          <button type="submit" class="w-full rounded-2xl bg-primary hover:bg-primary-hover text-white py-3.5 font-semibold transition mt-2 shrink-0">
            Register
          </button>

          <div class="text-center text-sm text-muted mt-auto pt-2 shrink-0">
            Sudah punya akun?
            <a href="{{ route('login') }}" class="text-primary hover:underline font-medium">
              Sign in
            </a>
          </div>
        </form>
        
      </div>
    </div>
  </div>
@endsection