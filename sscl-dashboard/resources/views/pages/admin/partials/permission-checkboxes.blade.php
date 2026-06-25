@props(['permissionGroups', 'selectedPermissions' => []])

@php
  // Gabungkan pilihan lama dari validasi gagal dengan permission yang sudah tersimpan.
  $selected = collect(old('permissions', $selectedPermissions instanceof \Illuminate\Support\Collection ? $selectedPermissions->all() : $selectedPermissions));
@endphp

<div class="grid gap-4 lg:grid-cols-2">
  @foreach ($permissionGroups as $module => $permissions)
    <div class="rounded-3xl border border-app bg-surface2/45 p-4">
      <div class="mb-3 flex items-center justify-between gap-3">
        <div class="text-sm font-semibold">{{ $module }}</div>
        <span class="rounded-full bg-primary-soft px-3 py-1 text-xs font-semibold text-primary">
          {{ $permissions->count() }} permission
        </span>
      </div>

      <div class="grid gap-2">
        @foreach ($permissions as $permission)
          <label class="group flex items-center gap-3 rounded-2xl border border-app bg-surface px-3 py-2.5 text-sm transition hover:-translate-y-[1px] hover:shadow-[0_12px_28px_rgba(118,127,219,0.14)]">
            <input
              type="checkbox"
              name="permissions[]"
              value="{{ $permission->name }}"
              class="rounded border-app text-primary focus:ring-primary"
              @checked($selected->contains($permission->name))
            >
            <span class="min-w-0 break-words font-medium text-app">{{ $permission->name }}</span>
          </label>
        @endforeach
      </div>
    </div>
  @endforeach
</div>
