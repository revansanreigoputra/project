@extends('layouts.dashboard')
@section('title', 'Manajemen Role')

@section('page')
  <div class="space-y-6">
    <div class="overflow-hidden rounded-3xl bg-surface shadow-soft">
      <div class="relative p-6 md:p-7">
        <div class="absolute inset-x-0 top-0 h-1 bg-[linear-gradient(90deg,var(--accent),var(--primary),var(--info))]"></div>
        <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
          <div class="flex items-start gap-4">
            <div class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-[rgba(34,197,94,0.15)] text-[color:var(--accent)]">
              <x-lucide-shield-check class="h-7 w-7" />
            </div>
            <div>
              <div class="text-xs font-semibold uppercase tracking-[0.16em] text-muted">Role & Permission</div>
              <h1 class="mt-1 text-2xl font-semibold leading-tight">Manajemen Role</h1>
              <p class="mt-2 max-w-2xl text-sm leading-6 text-muted">
                Susun role sebagai paket akses. Admin punya kontrol penuh, Petugas fokus ke workflow deteksi.
              </p>
            </div>
          </div>

          <a href="{{ route('admin.roles.create') }}" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-primary px-4 py-3 text-sm font-semibold text-white shadow-[0_16px_36px_rgba(118,127,219,0.28)] transition hover:bg-primary-hover">
            <x-lucide-circle-plus class="h-5 w-5" />
            Tambah Role
          </a>
        </div>
      </div>
    </div>

    <div class="rounded-3xl bg-surface p-5 shadow-soft">
      <form class="grid gap-3 md:grid-cols-[1fr_150px_auto]" method="GET" action="{{ route('admin.roles.index') }}">
        <label class="relative">
          <x-lucide-search class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" />
          <input name="search" value="{{ $filters['search'] }}" placeholder="Cari role..." class="w-full rounded-2xl border border-app bg-surface2 py-3 pl-11 pr-4 text-sm focus:border-primary focus:ring-primary">
        </label>
        <select name="per_page" class="rounded-2xl border border-app bg-surface2 px-4 py-3 text-sm focus:border-primary focus:ring-primary">
          @foreach ($perPageOptions as $option)
            <option value="{{ $option }}" @selected($filters['per_page'] === $option)>{{ $option }}/halaman</option>
          @endforeach
        </select>
        <button class="inline-flex items-center justify-center gap-2 rounded-2xl bg-primary px-4 py-3 text-sm font-semibold text-white hover:bg-primary-hover">
          <x-lucide-filter class="h-4 w-4" />
          Filter
        </button>
      </form>
    </div>

    <div class="grid gap-5 xl:grid-cols-2">
      @forelse ($roles as $role)
        @php($isSystemRole = in_array($role->name, $systemRoles, true))
        <div class="rounded-3xl bg-surface p-5 shadow-soft">
          <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-lg font-semibold">{{ $role->name }}</h2>
                @if ($isSystemRole)
                  <span class="rounded-full bg-primary-soft px-3 py-1 text-xs font-semibold text-primary">Sistem</span>
                @endif
              </div>
              <div class="mt-2 flex flex-wrap gap-2 text-xs text-muted">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-surface2 px-3 py-1">
                  <x-lucide-key-round class="h-3.5 w-3.5" />
                  {{ $role->permissions_count }} permission
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-surface2 px-3 py-1">
                  <x-lucide-users class="h-3.5 w-3.5" />
                  {{ $role->users_count }} pengguna
                </span>
              </div>
            </div>

            <div class="flex items-center gap-2">
              <a href="{{ route('admin.roles.edit', $role) }}" class="grid h-10 w-10 place-items-center rounded-2xl bg-primary-soft text-primary transition hover:-translate-y-[1px]" aria-label="Edit role">
                <x-lucide-pencil class="h-4 w-4" />
              </a>
              <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('Hapus role ini?')">
                @csrf
                @method('DELETE')
                <button @disabled($isSystemRole) type="submit" class="grid h-10 w-10 place-items-center rounded-2xl bg-[rgba(248,113,113,0.14)] text-[color:var(--danger)] transition hover:-translate-y-[1px] disabled:cursor-not-allowed disabled:opacity-45" aria-label="Hapus role">
                  <x-lucide-trash-2 class="h-4 w-4" />
                </button>
              </form>
            </div>
          </div>

          <div class="mt-5 flex flex-wrap gap-2">
            @forelse ($role->permissions->take(8) as $permission)
              <span class="rounded-full bg-surface2 px-3 py-1.5 text-xs font-semibold text-muted">{{ $permission->name }}</span>
            @empty
              <span class="text-sm text-muted">Belum ada permission.</span>
            @endforelse
            @if ($role->permissions_count > 8)
              <span class="rounded-full bg-primary-soft px-3 py-1.5 text-xs font-semibold text-primary">+{{ $role->permissions_count - 8 }}</span>
            @endif
          </div>
        </div>
      @empty
        <div class="rounded-3xl bg-surface p-10 text-center text-muted shadow-soft xl:col-span-2">
          Belum ada role yang cocok dengan filter.
        </div>
      @endforelse
    </div>

    <div class="rounded-3xl bg-surface px-5 py-4 shadow-soft">
      {{ $roles->links() }}
    </div>
  </div>
@endsection
