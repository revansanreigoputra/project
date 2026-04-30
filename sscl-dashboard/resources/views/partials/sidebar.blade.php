@php
  $items = [
    ['href' => route('dashboard'), 'active' => request()->routeIs('dashboard'), 'label' => 'Dashboard', 'icon' => 'layout-dashboard'],
    ['href' => route('scan.index'), 'active' => request()->routeIs('scan.index'), 'label' => 'Upload Data', 'icon' => 'cloud-upload'],
    ['href' => route('hasildeteksi.index'), 'active' => request()->routeIs('hasildeteksi.index'), 'label' => 'Hasil Deteksi', 'icon' => 'list-checks'],
    ['href' => route('riwayat.index'), 'active' => request()->routeIs('riwayat.index'), 'label' => 'Riwayat', 'icon' => 'clock'],
    ['href' => route('laporan.index'), 'active' => request()->routeIs('laporan.index'), 'label' => 'Laporan', 'icon' => 'file-text'],
  ];
@endphp

{{-- Desktop --}}
<aside class="hidden md:fixed md:inset-y-0 md:left-0 md:flex md:w-72 md:flex-col">
  <div class="m-4 h-[calc(100vh-32px)] rounded-3xl bg-surface shadow-soft flex flex-col">
    {{-- Brand --}}
    <div class="flex items-center gap-3 px-4 py-4">
      <div class="h-10 w-10 rounded-2xl overflow-hidden shadow-soft bg-transparent">
        <img src="{{ asset('images/logo.png') }}" alt="UPA-TIK logo" class="h-full w-full object-cover">
      </div>
      <div class="leading-tight">
        <div class="text-sm font-semibold tracking-wide">UPA-TIK</div>
        <div class="text-xs text-muted">SSCL Malware Detection</div>
      </div>
    </div>

    {{-- Nav --}}
    <nav class="mt-2 flex flex-col gap-1 px-3">
      @foreach ($items as $it)
        <x-sidebar-link
          href="{{ $it['href'] }}"
          :active="$it['active']"
        >
          @php($icon = 'lucide-' . $it['icon'])
          <x-dynamic-component :component="$icon" class="h-[18px] w-[18px] transition-transform duration-200 group-hover:scale-110" />
          <span>{{ $it['label'] }}</span>
        </x-sidebar-link>
      @endforeach
    </nav>

    {{-- Footer --}}
    <div class="mt-auto px-3 pb-4">
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button
          type="submit"
          class="w-full rounded-xl px-4 py-3 text-left text-sm font-medium text-muted hover:bg-surface2
                 focus-visible:outline-none focus-visible:ring-2 ring-app"
        >
          <span class="inline-flex items-center gap-3">
            <x-lucide-log-out class="h-[18px] w-[18px]" />
            Logout
          </span>
        </button>
      </form>
    </div>
  </div>
</aside>

{{-- Mobile overlay + drawer --}}
<div class="md:hidden fixed inset-0 z-40" x-show="mobileOpen" x-cloak>
  <div class="absolute inset-0 bg-black/40" @click="mobileOpen=false" x-transition.opacity></div>

  <aside
    class="absolute left-3 top-3 bottom-3 w-[85%] max-w-xs rounded-3xl bg-surface shadow-soft"
    x-transition:enter="transition transform duration-200 ease-out"
    x-transition:enter-start="-translate-x-[110%]"
    x-transition:enter-end="translate-x-0"
    x-transition:leave="transition transform duration-200 ease-out"
    x-transition:leave-start="translate-x-0"
    x-transition:leave-end="-translate-x-[110%]"
    role="dialog" aria-modal="true"
  >
    <div class="flex items-center justify-between px-4 py-4">
      <div class="flex items-center gap-3">
        <div class="h-11 w-11 rounded-2xl overflow-hidden shadow-soft bg-transparent">
          <img src="{{ asset('images/logo.png') }}" alt="UPA-TIK logo" class="h-full w-full object-cover">
        </div>
        <div class="leading-tight">
          <div class="text-sm font-semibold tracking-wide">UPA-TIK</div>
          <div class="text-xs text-muted">SSCL Malware Detection</div>
        </div>
      </div>

      <button
        type="button"
        @click="mobileOpen=false"
        class="rounded-xl p-2 text-muted hover:bg-surface2 focus-visible:outline-none focus-visible:ring-2 ring-app"
      >
        <x-lucide-x class="h-5 w-5" />
      </button>
    </div>

    <nav class="mt-2 flex flex-col gap-1 px-3">
      @foreach ($items as $it)
        <x-sidebar-link
          href="{{ $it['href'] }}"
          :active="$it['active']"
          @click="mobileOpen=false"
        >
          @php($icon = 'lucide-' . $it['icon'])
          <x-dynamic-component :component="$icon" class="h-[18px] w-[18px]" />
          <span>{{ $it['label'] }}</span>
        </x-sidebar-link>
      @endforeach
    </nav>

    <div class="mt-auto px-3 pb-4">
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button
          type="submit"
          class="w-full rounded-xl px-4 py-3 text-left text-sm font-medium text-muted hover:bg-surface2
                 focus-visible:outline-none focus-visible:ring-2 ring-app"
        >
          <span class="inline-flex items-center gap-3">
            <x-lucide-log-out class="h-[18px] w-[18px]" />
            Logout
          </span>
        </button>
      </form>
    </div>
  </aside>
</div>
