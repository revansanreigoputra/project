@extends('layouts.dashboard')
@section('title', 'Edit Pengguna')

@section('page')
  <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-6">
    @csrf
    @method('PUT')

    <div class="flex flex-col gap-4 rounded-3xl bg-surface p-6 shadow-soft md:flex-row md:items-center md:justify-between">
      <div>
        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-muted">Manajemen Pengguna</div>
        <h1 class="mt-1 text-2xl font-semibold">Edit {{ $user->name }}</h1>
      </div>
      <div class="flex gap-2">
        <a href="{{ route('admin.users.index') }}" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-surface2 px-4 py-3 text-sm font-semibold text-muted transition hover:text-app">
          <x-lucide-arrow-left class="h-4 w-4" />
          Kembali
        </a>
        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-primary px-4 py-3 text-sm font-semibold text-white hover:bg-primary-hover">
          <x-lucide-save class="h-4 w-4" />
          Simpan
        </button>
      </div>
    </div>

    @include('pages.admin.partials.form-errors')

    <div class="grid gap-6 xl:grid-cols-[1fr_1.25fr]">
      <div class="space-y-6 rounded-3xl bg-surface p-6 shadow-soft">
        <div>
          <div class="text-sm font-semibold">Identitas Akun</div>
          <p class="mt-1 text-sm text-muted">Kosongkan password bila tidak ingin mengubahnya.</p>
        </div>

        <div class="space-y-4">
          <label class="block">
            <span class="text-sm font-semibold">Nama</span>
            <input name="name" value="{{ old('name', $user->name) }}" class="mt-2 w-full rounded-2xl border border-app bg-surface2 px-4 py-3 text-sm focus:border-primary focus:ring-primary" required>
          </label>
          <label class="block">
            <span class="text-sm font-semibold">Email</span>
            <input type="email" name="email" value="{{ old('email', $user->email) }}" class="mt-2 w-full rounded-2xl border border-app bg-surface2 px-4 py-3 text-sm focus:border-primary focus:ring-primary" required>
          </label>
          <label class="block">
            <span class="text-sm font-semibold">Password Baru</span>
            <input type="password" name="password" class="mt-2 w-full rounded-2xl border border-app bg-surface2 px-4 py-3 text-sm focus:border-primary focus:ring-primary">
          </label>
          <label class="block">
            <span class="text-sm font-semibold">Konfirmasi Password Baru</span>
            <input type="password" name="password_confirmation" class="mt-2 w-full rounded-2xl border border-app bg-surface2 px-4 py-3 text-sm focus:border-primary focus:ring-primary">
          </label>
        </div>
      </div>

      <div class="space-y-6">
        <div class="rounded-3xl bg-surface p-6 shadow-soft">
          <div class="mb-4 flex items-center gap-3">
            <div class="grid h-10 w-10 place-items-center rounded-2xl bg-primary-soft text-primary">
              <x-lucide-shield class="h-5 w-5" />
            </div>
            <div>
              <div class="text-sm font-semibold">Role</div>
              <div class="text-xs text-muted">Role menentukan akses utama pengguna.</div>
            </div>
          </div>
          @include('pages.admin.partials.role-checkboxes', [
            'roles' => $roles,
            'selectedRoles' => $user->roles->pluck('name'),
          ])
        </div>

        <div class="rounded-3xl bg-surface p-6 shadow-soft">
          <div class="mb-4 flex items-center gap-3">
            <div class="grid h-10 w-10 place-items-center rounded-2xl bg-primary-soft text-primary">
              <x-lucide-key-round class="h-5 w-5" />
            </div>
            <div>
              <div class="text-sm font-semibold">Direct Permission</div>
              <div class="text-xs text-muted">Gunakan bila user butuh akses khusus di luar role.</div>
            </div>
          </div>
          @include('pages.admin.partials.permission-checkboxes', [
            'permissionGroups' => $permissionGroups,
            'selectedPermissions' => $user->permissions->pluck('name'),
          ])
        </div>
      </div>
    </div>
  </form>
@endsection
