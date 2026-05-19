@extends('layouts.dashboard')
@section('title', 'Manajemen Permission')

@section('page')
  <div class="space-y-6">
    <div class="overflow-hidden rounded-3xl bg-surface shadow-soft">
      <div class="relative p-6 md:p-7">
        <div class="absolute inset-x-0 top-0 h-1 bg-[linear-gradient(90deg,var(--info),var(--primary),var(--warning))]"></div>
        <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
          <div class="flex items-start gap-4">
            <div class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-primary-soft text-primary">
              <x-lucide-key-round class="h-7 w-7" />
            </div>
            <div>
              <div class="text-xs font-semibold uppercase tracking-[0.16em] text-muted">Role & Permission</div>
              <h1 class="mt-1 text-2xl font-semibold leading-tight">Manajemen Permission</h1>
              <p class="mt-2 max-w-2xl text-sm leading-6 text-muted">
                Permission bawaan mengunci akses route. Permission tambahan bisa dipakai untuk kebutuhan khusus berikutnya.
              </p>
            </div>
          </div>

          <a href="{{ route('admin.permissions.create') }}" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-primary px-4 py-3 text-sm font-semibold text-white shadow-[0_16px_36px_rgba(118,127,219,0.28)] transition hover:bg-primary-hover">
            <x-lucide-circle-plus class="h-5 w-5" />
            Tambah Permission
          </a>
        </div>
      </div>
    </div>

    <div class="rounded-3xl bg-surface p-5 shadow-soft">
      <form class="grid gap-3 lg:grid-cols-[1fr_220px_150px_auto]" method="GET" action="{{ route('admin.permissions.index') }}">
        <label class="relative">
          <x-lucide-search class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" />
          <input name="search" value="{{ $filters['search'] }}" placeholder="Cari permission..." class="w-full rounded-2xl border border-app bg-surface2 py-3 pl-11 pr-4 text-sm focus:border-primary focus:ring-primary">
        </label>
        <select name="module" class="rounded-2xl border border-app bg-surface2 px-4 py-3 text-sm focus:border-primary focus:ring-primary">
          <option value="all">Semua modul</option>
          @foreach ($modules as $module)
            <option value="{{ $module }}" @selected($filters['module'] === $module)>{{ str($module)->headline() }}</option>
          @endforeach
        </select>
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

    <div class="overflow-hidden rounded-3xl bg-surface shadow-soft">
      <div class="overflow-auto">
        <table class="w-full min-w-[860px] text-sm">
          <thead class="bg-surface2 text-muted">
            <tr class="border-b border-app">
              <th class="px-5 py-4 text-left font-semibold">Permission</th>
              <th class="px-5 py-4 text-left font-semibold">Module</th>
              <th class="px-5 py-4 text-left font-semibold">Dipakai Role</th>
              <th class="px-5 py-4 text-left font-semibold">Direct User</th>
              <th class="px-5 py-4 text-right font-semibold">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-app">
            @forelse ($permissions as $permission)
              @php
                $isSystemPermission = in_array($permission->name, $systemPermissions, true);
                $module = str($permission->name)->before('.')->headline();
              @endphp
              <tr class="transition hover:bg-surface2/50">
                <td class="px-5 py-4">
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="font-semibold">{{ $permission->name }}</span>
                    @if ($isSystemPermission)
                      <span class="rounded-full bg-primary-soft px-3 py-1 text-xs font-semibold text-primary">Sistem</span>
                    @endif
                  </div>
                </td>
                <td class="px-5 py-4 text-muted">{{ $module }}</td>
                <td class="px-5 py-4">
                  <div class="flex flex-wrap gap-2">
                    @forelse ($permission->roles as $role)
                      <span class="rounded-full bg-surface2 px-3 py-1 text-xs font-semibold text-muted">{{ $role->name }}</span>
                    @empty
                      <span class="text-muted">-</span>
                    @endforelse
                  </div>
                </td>
                <td class="px-5 py-4 text-muted">{{ $permission->users_count }} pengguna</td>
                <td class="px-5 py-4">
                  <div class="flex items-center justify-end gap-2">
                    <a href="{{ route('admin.permissions.edit', $permission) }}" class="grid h-10 w-10 place-items-center rounded-2xl bg-primary-soft text-primary transition hover:-translate-y-[1px]" aria-label="Edit permission">
                      <x-lucide-pencil class="h-4 w-4" />
                    </a>
                    <form method="POST" action="{{ route('admin.permissions.destroy', $permission) }}" onsubmit="return confirm('Hapus permission ini?')">
                      @csrf
                      @method('DELETE')
                      <button @disabled($isSystemPermission) type="submit" class="grid h-10 w-10 place-items-center rounded-2xl bg-[rgba(248,113,113,0.14)] text-[color:var(--danger)] transition hover:-translate-y-[1px] disabled:cursor-not-allowed disabled:opacity-45" aria-label="Hapus permission">
                        <x-lucide-trash-2 class="h-4 w-4" />
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="px-5 py-10 text-center text-muted">Belum ada permission yang cocok dengan filter.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="border-t border-app px-5 py-4">
        {{ $permissions->links() }}
      </div>
    </div>
  </div>
@endsection
