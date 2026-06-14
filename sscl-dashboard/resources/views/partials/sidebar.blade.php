@php
  $user = auth()->user();
  $items = collect([
    ['href' => route('dashboard'), 'active' => request()->routeIs('dashboard'), 'label' => 'Dashboard', 'icon' => 'layout-dashboard', 'can' => ['dashboard.view']],
    ['href' => route('scan.index'), 'active' => request()->routeIs('scan.index'), 'label' => 'Upload Data', 'icon' => 'cloud-upload', 'can' => ['scan.upload', 'scan.run']],
    ['href' => route('hasildeteksi.index'), 'active' => request()->routeIs('hasildeteksi.*') || request()->routeIs('laporan.index'), 'label' => 'Hasil Deteksi', 'icon' => 'list-checks', 'can' => ['detection.view', 'reports.view']],
    ['href' => route('riwayat.index'), 'active' => request()->routeIs('riwayat.*'), 'label' => 'Riwayat', 'icon' => 'clock', 'can' => ['history.view']],
    ['href' => route('backup.index'), 'active' => request()->routeIs('backup.*'), 'label' => 'Backup Database', 'icon' => 'database-backup', 'can' => ['database.backup']],
  ])->filter(fn ($item) => $user?->canAny($item['can']))->values();

  $adminItems = collect([
    ['href' => route('admin.users.index'), 'active' => request()->routeIs('admin.users.*'), 'label' => 'Pengguna', 'icon' => 'users-round', 'can' => ['users.manage']],
    ['href' => route('admin.roles.index'), 'active' => request()->routeIs('admin.roles.*'), 'label' => 'Role', 'icon' => 'shield-check', 'can' => ['roles.manage']],
    ['href' => route('admin.permissions.index'), 'active' => request()->routeIs('admin.permissions.*'), 'label' => 'Permission', 'icon' => 'key-round', 'can' => ['permissions.manage']],
  ])->filter(fn ($item) => $user?->canAny($item['can']))->values();
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

      @if ($adminItems->isNotEmpty())
        <div class="px-4 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-[0.16em] text-muted">Admin</div>
        @foreach ($adminItems as $it)
          <x-sidebar-link
            href="{{ $it['href'] }}"
            :active="$it['active']"
          >
            @php($icon = 'lucide-' . $it['icon'])
            <x-dynamic-component :component="$icon" class="h-[18px] w-[18px] transition-transform duration-200 group-hover:scale-110" />
            <span>{{ $it['label'] }}</span>
          </x-sidebar-link>
        @endforeach
      @endif
    </nav>

    {{-- Footer --}}
    <div class="mt-auto px-3 pb-4">
      <button
        type="button"
        @click="$dispatch('open-confirm-dialog', {
          title: 'Konfirmasi Logout',
          message: 'Sesi Anda akan diakhiri. Lanjutkan logout?',
          action: @js(route('logout')),
          method: 'POST',
          icon: 'logout',
          tone: 'danger'
        })"
        class="group relative flex w-full items-center gap-3 overflow-hidden rounded-2xl px-4 py-3 text-left text-sm font-medium text-muted transition-all duration-200 ease-out hover:-translate-y-[1px] hover:bg-[rgba(248,113,113,0.12)] hover:text-[color:var(--danger)] hover:shadow-[0_14px_40px_rgba(248,113,113,0.14)] focus-visible:outline-none focus-visible:ring-2 ring-app"
      >
        <span class="pointer-events-none absolute inset-0 opacity-0 transition-opacity duration-200 group-hover:opacity-100 bg-[linear-gradient(110deg,transparent,rgba(248,113,113,0.14),transparent)]"></span>
        <span class="relative inline-flex items-center gap-3">
          <x-lucide-log-out class="h-[18px] w-[18px] transition-transform duration-200 group-hover:scale-110" />
          Logout
        </span>
      </button>
    </div>
  </div>
</aside>

{{-- Mobile overlay + drawer --}}
<div class="md:hidden fixed inset-0 z-40" x-show="mobileOpen" x-cloak>
  <div class="absolute inset-0 bg-black/40" @click="mobileOpen=false" x-transition.opacity></div>

  <aside
    class="absolute left-3 top-3 bottom-3 flex w-[85%] max-w-xs flex-col rounded-3xl bg-surface shadow-soft"
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

      @if ($adminItems->isNotEmpty())
        <div class="px-4 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-[0.16em] text-muted">Admin</div>
        @foreach ($adminItems as $it)
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
      @endif
    </nav>

    <div class="mt-auto px-3 pb-4">
      <button
        type="button"
        @click="$dispatch('open-confirm-dialog', {
          title: 'Konfirmasi Logout',
          message: 'Sesi Anda akan diakhiri. Lanjutkan logout?',
          action: @js(route('logout')),
          method: 'POST',
          icon: 'logout',
          tone: 'danger'
        })"
        class="group relative flex w-full items-center gap-3 overflow-hidden rounded-2xl px-4 py-3 text-left text-sm font-medium text-muted transition-all duration-200 ease-out hover:-translate-y-[1px] hover:bg-[rgba(248,113,113,0.12)] hover:text-[color:var(--danger)] hover:shadow-[0_14px_40px_rgba(248,113,113,0.14)] focus-visible:outline-none focus-visible:ring-2 ring-app"
      >
        <span class="pointer-events-none absolute inset-0 opacity-0 transition-opacity duration-200 group-hover:opacity-100 bg-[linear-gradient(110deg,transparent,rgba(248,113,113,0.14),transparent)]"></span>
        <span class="relative inline-flex items-center gap-3">
          <x-lucide-log-out class="h-[18px] w-[18px] transition-transform duration-200 group-hover:scale-110" />
          Logout
        </span>
      </button>
    </div>
  </aside>
</div>
