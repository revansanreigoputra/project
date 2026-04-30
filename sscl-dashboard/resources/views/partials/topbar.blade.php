@php
  $map = [
    'dashboard' => 'Dashboard',
    'scan.index' => 'Upload Data',
    'hasildeteksi.index' => 'Hasil Deteksi',
    'riwayat.index' => 'Riwayat',
    'laporan.index' => 'Laporan',
  ];
  $title = $map[Route::currentRouteName()] ?? 'Dashboard';
@endphp

<header class="sticky top-0 md:top-4 z-30" x-data="{ scrolled: false }"
  x-init="
    const onScroll = () => scrolled = window.scrollY > 8;
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  "
>
  <div
    class="rounded-3xl backdrop-blur-xl bg-[color:var(--bg)]/55 transition-[box-shadow,border-color,background-color] duration-200"
    :class="scrolled ? 'border border-app/60 shadow-soft' : 'border border-transparent'"
  >
    <div class="px-3 py-3 md:px-5 md:py-4">
      <div class="flex items-start md:items-center justify-between gap-3">
        {{-- LEFT --}}
        <div class="flex items-start md:items-center gap-3">
          <button
            type="button"
            @click="mobileOpen=true"
            class="md:hidden rounded-2xl bg-surface px-3 py-2 shadow-soft text-muted hover:bg-surface2
                   focus-visible:outline-none focus-visible:ring-2 ring-app"
            aria-label="Open sidebar"
          >
            <x-lucide-menu class="h-5 w-5" />
          </button>

          <div class="min-w-0">
            <div class="text-2xl font-semibold leading-tight">{{ $title }}</div>
          </div>
        </div>

        {{-- RIGHT --}}
        <div class="flex items-center gap-3">
          {{-- Toggle pill --}}
          <button
            type="button"
            @click="toggleTheme()"
            class="rounded-2xl bg-surface shadow-soft p-1 focus-visible:outline-none focus-visible:ring-2 ring-app"
            aria-label="Toggle theme"
          >
            <div class="flex items-center gap-1 rounded-xl bg-surface2 p-1">
              <span class="grid h-8 w-8 place-items-center rounded-xl"
                    :class="theme === 'light' ? 'bg-primary text-white' : 'text-muted'">
                <x-lucide-sun class="h-4 w-4" />
              </span>
              <span class="grid h-8 w-8 place-items-center rounded-xl"
                    :class="theme === 'dark' ? 'bg-primary text-white' : 'text-muted'">
                <x-lucide-moon class="h-4 w-4" />
              </span>
            </div>
          </button>

          {{-- User --}}
          <div class="hidden sm:flex items-center gap-3 rounded-3xl bg-surface px-4 py-2 shadow-soft">
            <div class="text-right leading-tight">
              <div class="text-sm font-semibold">Hey, {{ auth()->user()->name ?? 'Admin' }}</div>
              <div class="text-xs text-muted">Operator</div>
            </div>
            <div class="h-10 w-10 rounded-full bg-surface2 grid place-items-center text-sm font-semibold">
              {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</header>
