@extends('layouts.dashboard')
@section('title', 'Manajemen Role')

@section('page')
  <div 
    class="space-y-6"
    {{-- State Alpine untuk modal hapus role. --}}
    x-data="{
      deleteModalOpen: false,
      deleteFormAction: '',
      deleteRoleName: '',
      openDeleteModal(action, name) {
        this.deleteFormAction = action;
        this.deleteRoleName = name;
        this.deleteModalOpen = true;
      }
    }"
  >
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
        @php
          // Tandai role bawaan agar tidak bisa dihapus dari UI.
          $isSystemRole = in_array($role->name, $systemRoles, true);
        @endphp
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
              
              {{-- Tombol Hapus Diubah Menjadi Trigger Modal --}}
              <button 
                type="button" 
                @disabled($isSystemRole) 
                @click="openDeleteModal(@js(route('admin.roles.destroy', $role)), @js($role->name))"
                class="grid h-10 w-10 place-items-center rounded-2xl bg-[rgba(248,113,113,0.14)] text-[color:var(--danger)] transition hover:-translate-y-[1px] disabled:cursor-not-allowed disabled:opacity-45" 
                aria-label="Hapus role"
              >
                <x-lucide-trash-2 class="h-4 w-4" />
              </button>
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

    {{-- Modal Konfirmasi Hapus Role --}}
    <div
      x-show="deleteModalOpen"
      x-cloak
      class="fixed inset-0 z-50 grid place-items-center bg-black/45 p-4 backdrop-blur-sm"
      x-transition.opacity
    >
      <div
        @click.outside="deleteModalOpen = false"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-3 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-2 scale-95"
        class="w-full max-w-md overflow-hidden rounded-3xl border border-app bg-surface shadow-soft"
        role="dialog"
        aria-modal="true"
      >
        <div class="p-6">
          <div class="flex items-start gap-4">
            <div class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-[rgba(248,113,113,0.14)] text-[color:var(--danger)]">
              <x-lucide-shield-alert class="h-6 w-6" />
            </div>
            <div class="min-w-0">
              <div class="text-lg font-semibold">Hapus Role?</div>
              <div class="mt-2 text-sm leading-6 text-muted">
                Role <span class="font-semibold text-app" x-text="deleteRoleName"></span> akan dihapus secara permanen dari sistem.
              </div>
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-3 border-t border-app bg-surface2 px-6 py-4">
          <button
            type="button"
            @click="deleteModalOpen = false"
            class="rounded-xl border border-app px-5 py-2 text-sm font-semibold hover:bg-surface transition-colors"
          >
            Batal
          </button>
          <form method="POST" :action="deleteFormAction">
            @csrf
            @method('DELETE')
            <button
              type="submit"
              class="inline-flex items-center gap-2 rounded-xl bg-[color:var(--danger)] px-5 py-2 text-sm font-semibold text-white hover:opacity-90 transition-opacity"
            >
              <x-lucide-trash-2 class="h-4 w-4" />
              Konfirmasi
            </button>
          </form>
        </div>
      </div>
    </div>

  </div>
@endsection
