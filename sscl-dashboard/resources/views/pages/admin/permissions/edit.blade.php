@extends('layouts.dashboard')
@section('title', 'Edit Permission')

@section('page')
  <form method="POST" action="{{ route('admin.permissions.update', $permission) }}" class="space-y-6">
    @csrf
    @method('PUT')

    <div class="flex flex-col gap-4 rounded-3xl bg-surface p-6 shadow-soft md:flex-row md:items-center md:justify-between">
      <div>
        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-muted">Role & Permission</div>
        <h1 class="mt-1 text-2xl font-semibold">Edit {{ $permission->name }}</h1>
      </div>
      <div class="flex gap-2">
        <a href="{{ route('admin.permissions.index') }}" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-surface2 px-4 py-3 text-sm font-semibold text-muted transition hover:text-app">
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

    <div class="grid gap-6 xl:grid-cols-[420px_1fr]">
      <div class="rounded-3xl bg-surface p-6 shadow-soft">
        <div class="mb-5">
          <div class="text-sm font-semibold">Detail Permission</div>
          <p class="mt-1 text-sm text-muted">
            @if ($isSystemPermission)
              Nama permission sistem dikunci karena dipakai oleh middleware route.
            @else
              Permission kustom boleh diganti selama belum dipakai di kode route.
            @endif
          </p>
        </div>
        <label class="block">
          <span class="text-sm font-semibold">Nama Permission</span>
          @if ($isSystemPermission)
            <input type="hidden" name="name" value="{{ old('name', $permission->name) }}">
            <input value="{{ old('name', $permission->name) }}" disabled class="mt-2 w-full rounded-2xl border border-app bg-surface2 px-4 py-3 text-sm opacity-70" required>
          @else
            <input name="name" value="{{ old('name', $permission->name) }}" class="mt-2 w-full rounded-2xl border border-app bg-surface2 px-4 py-3 text-sm focus:border-primary focus:ring-primary" required>
          @endif
        </label>
      </div>

      <div class="rounded-3xl bg-surface p-6 shadow-soft">
        <div class="mb-4 flex items-center gap-3">
          <div class="grid h-10 w-10 place-items-center rounded-2xl bg-primary-soft text-primary">
            <x-lucide-shield class="h-5 w-5" />
          </div>
          <div>
            <div class="text-sm font-semibold">Role yang Memiliki Permission</div>
            <div class="text-xs text-muted">Perubahan langsung memengaruhi semua user di role terkait.</div>
          </div>
        </div>
        @include('pages.admin.partials.role-checkboxes', [
          'roles' => $roles,
          'selectedRoles' => $permission->roles->pluck('name'),
        ])
      </div>
    </div>
  </form>
@endsection
