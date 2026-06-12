@extends('layouts.dashboard')
@section('title', 'Manajemen Pengguna')

@section('page')
  @php
    $totalUsers = $users->total();
    $roleBadges = $roles->map(fn ($role) => $role->name)->take(4);
  @endphp

  <div
    class="space-y-6"
    x-data="{
      deleteModalOpen: false,
      deleteFormAction: '',
      deleteUserName: '',
      deleteUserEmail: '',
      openDeleteModal(action, name, email) {
        this.deleteFormAction = action;
        this.deleteUserName = name;
        this.deleteUserEmail = email;
        this.deleteModalOpen = true;
      }
    }"
  >
    <div class="overflow-hidden rounded-3xl bg-surface shadow-soft">
      <div class="relative p-6 md:p-7">
        <div class="absolute inset-x-0 top-0 h-1 bg-[linear-gradient(90deg,var(--primary),var(--accent),var(--info))]"></div>
        <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
          <div class="flex items-start gap-4">
            <div class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-primary-soft text-primary">
              <x-lucide-users-round class="h-7 w-7" />
            </div>
            <div>
              <div class="text-xs font-semibold uppercase tracking-[0.16em] text-muted">Admin Area</div>
              <h1 class="mt-1 text-2xl font-semibold leading-tight">Manajemen Pengguna</h1>
              <p class="mt-2 max-w-2xl text-sm leading-6 text-muted">
                Kelola akun, role, dan permission langsung per pengguna untuk menjaga akses sistem tetap presisi.
              </p>
            </div>
          </div>

          <a
            href="{{ route('admin.users.create') }}"
            class="inline-flex items-center justify-center gap-2 rounded-2xl bg-primary px-4 py-3 text-sm font-semibold text-white shadow-[0_16px_36px_rgba(118,127,219,0.28)] transition hover:bg-primary-hover"
          >
            <x-lucide-user-plus class="h-5 w-5" />
            Tambah Pengguna
          </a>
        </div>
      </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
      <div class="rounded-3xl bg-surface p-5 shadow-soft">
        <div class="text-xs font-semibold text-muted">Total Pengguna</div>
        <div class="mt-2 text-3xl font-semibold">{{ number_format($totalUsers, 0, ',', '.') }}</div>
      </div>
      <div class="rounded-3xl bg-surface p-5 shadow-soft">
        <div class="text-xs font-semibold text-muted">Role Aktif</div>
        <div class="mt-3 flex flex-wrap gap-2">
          @forelse ($roleBadges as $role)
            <span class="rounded-full bg-primary-soft px-3 py-1 text-xs font-semibold text-primary">{{ $role }}</span>
          @empty
            <span class="text-sm text-muted">Belum ada role.</span>
          @endforelse
        </div>
      </div>
      {{-- <div class="rounded-3xl bg-surface p-5 shadow-soft">
        <div class="text-xs font-semibold text-muted">Mode Akses</div>
        <div class="mt-2 flex items-center gap-2 text-sm font-semibold">
          <x-lucide-shield-check class="h-5 w-5 text-[color:var(--accent)]" />
          RBAC Spatie Aktif
        </div>
      </div> --}}
    </div>

    <div class="rounded-3xl bg-surface p-5 shadow-soft">
      <form class="grid gap-3 lg:grid-cols-[1fr_220px_150px_auto]" method="GET" action="{{ route('admin.users.index') }}">
        <label class="relative">
          <x-lucide-search class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" />
          <input
            type="search"
            name="search"
            value="{{ $filters['search'] }}"
            placeholder="Cari nama atau email..."
            class="w-full rounded-2xl border border-app bg-surface2 py-3 pl-11 pr-4 text-sm focus:border-primary focus:ring-primary"
          >
        </label>

        <select name="role" class="rounded-2xl border border-app bg-surface2 px-4 py-3 text-sm focus:border-primary focus:ring-primary">
          <option value="all">Semua role</option>
          @foreach ($roles as $role)
            <option value="{{ $role->name }}" @selected($filters['role'] === $role->name)>{{ $role->name }}</option>
          @endforeach
        </select>

        <select name="per_page" class="rounded-2xl border border-app bg-surface2 px-4 py-3 text-sm focus:border-primary focus:ring-primary">
          @foreach ($perPageOptions as $option)
            <option value="{{ $option }}" @selected($filters['per_page'] === $option)>{{ $option }}/halaman</option>
          @endforeach
        </select>

        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-primary px-4 py-3 text-sm font-semibold text-white hover:bg-primary-hover">
          <x-lucide-filter class="h-4 w-4" />
          Filter
        </button>
      </form>
    </div>

    <div class="overflow-hidden rounded-3xl bg-surface shadow-soft">
      <div class="overflow-auto">
        <table class="w-full min-w-[920px] text-sm">
          <thead class="bg-surface2 text-muted">
            <tr class="border-b border-app">
              <th class="px-5 py-4 text-left font-semibold">Pengguna</th>
              <th class="px-5 py-4 text-left font-semibold">Role</th>
              <th class="px-5 py-4 text-left font-semibold">Direct Permission</th>
              <th class="px-5 py-4 text-left font-semibold">Dibuat</th>
              <th class="px-5 py-4 text-right font-semibold">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-app">
            @forelse ($users as $user)
              <tr class="transition hover:bg-surface2/50">
                <td class="px-5 py-4">
                  <div class="flex items-center gap-3">
                    <div class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-primary-soft font-semibold text-primary">
                      {{ strtoupper(substr($user->name, 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                      <div class="font-semibold">{{ $user->name }}</div>
                      <div class="truncate text-xs text-muted">{{ $user->email }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-5 py-4">
                  <div class="flex flex-wrap gap-2">
                    @forelse ($user->roles as $role)
                      <span class="rounded-full bg-primary-soft px-3 py-1 text-xs font-semibold text-primary">{{ $role->name }}</span>
                    @empty
                      <span class="rounded-full bg-surface2 px-3 py-1 text-xs font-semibold text-muted">Tanpa role</span>
                    @endforelse
                  </div>
                </td>
                <td class="px-5 py-4">
                  <span class="inline-flex items-center gap-2 rounded-full bg-surface2 px-3 py-1 text-xs font-semibold text-muted">
                    <x-lucide-key-round class="h-3.5 w-3.5" />
                    {{ $user->permissions_count }} permission
                  </span>
                </td>
                <td class="px-5 py-4 text-muted">{{ $user->created_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}</td>
                <td class="px-5 py-4">
                  <div class="flex items-center justify-end gap-2">
                    <a href="{{ route('admin.users.edit', $user) }}" class="grid h-10 w-10 place-items-center rounded-2xl bg-primary-soft text-primary transition hover:-translate-y-[1px]" aria-label="Edit pengguna">
                      <x-lucide-pencil class="h-4 w-4" />
                    </a>
                    <button
                      type="button"
                      @click="openDeleteModal(@js(route('admin.users.destroy', $user)), @js($user->name), @js($user->email))"
                      class="grid h-10 w-10 place-items-center rounded-2xl bg-[rgba(248,113,113,0.14)] text-[color:var(--danger)] transition hover:-translate-y-[1px]"
                      aria-label="Hapus pengguna"
                    >
                      <x-lucide-trash-2 class="h-4 w-4" />
                    </button>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="px-5 py-10 text-center text-muted">Belum ada pengguna yang cocok dengan filter.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="border-t border-app px-5 py-4">
        {{ $users->links() }}
      </div>
    </div>

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
              <x-lucide-user-x class="h-6 w-6" />
            </div>
            <div class="min-w-0">
              <div class="text-lg font-semibold">Hapus Pengguna?</div>
              <div class="mt-2 text-sm leading-6 text-muted">
                Akun
                <span class="font-semibold text-app" x-text="deleteUserName"></span>
                <span class="text-muted" x-show="deleteUserEmail">(<span x-text="deleteUserEmail"></span>)</span>
                akan dihapus dari sistem.
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
              Hapus
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
