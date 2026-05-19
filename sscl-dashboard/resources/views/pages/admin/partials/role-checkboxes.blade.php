@props(['roles', 'selectedRoles' => []])

@php
  $selected = collect(old('roles', $selectedRoles instanceof \Illuminate\Support\Collection ? $selectedRoles->all() : $selectedRoles));
@endphp

<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
  @foreach ($roles as $role)
    <label class="group flex items-center gap-3 rounded-2xl border border-app bg-surface2/45 px-4 py-3 text-sm transition hover:-translate-y-[1px] hover:shadow-[0_12px_28px_rgba(118,127,219,0.14)]">
      <input
        type="checkbox"
        name="roles[]"
        value="{{ $role->name }}"
        class="rounded border-app text-primary focus:ring-primary"
        @checked($selected->contains($role->name))
      >
      <span class="font-semibold">{{ $role->name }}</span>
      <span class="ml-auto rounded-full bg-primary-soft px-2.5 py-1 text-[11px] font-semibold text-primary">
        {{ $role->permissions_count ?? $role->permissions()->count() }}
      </span>
    </label>
  @endforeach
</div>
