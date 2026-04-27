@props(['active' => false])

@php
  $base = "group relative flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium
           transition-all duration-200 ease-out focus-visible:outline-none focus-visible:ring-2 ring-app
           overflow-hidden";

  $activeClass = "bg-primary-soft text-primary
                  before:absolute before:left-0 before:top-2 before:bottom-2 before:w-1
                  before:rounded-r before:bg-primary before:content-['']
                  shadow-[0_10px_30px_rgba(118,127,219,0.20)]";

  $inactiveClass = "text-muted hover:text-app hover:-translate-y-[1px]
                    hover:shadow-[0_14px_40px_rgba(118,127,219,0.16)]
                    hover:bg-surface2/60";
@endphp

<a {{ $attributes->class([$base, $active ? $activeClass : $inactiveClass]) }}>
  {{-- Glow gradient on hover --}}
  <span
    class="pointer-events-none absolute -inset-x-6 -inset-y-10 opacity-0
           bg-[radial-gradient(circle_at_20%_30%,rgba(118,127,219,0.35),transparent_55%)]
           transition-opacity duration-200 group-hover:opacity-100"
  ></span>

  {{-- Light sweep --}}
  <span
    class="pointer-events-none absolute inset-0 opacity-0
           bg-[linear-gradient(110deg,transparent,rgba(118,127,219,0.18),transparent)]
           -translate-x-full transition-all duration-300
           group-hover:opacity-100 group-hover:translate-x-full"
  ></span>

  <span class="relative flex items-center gap-3">
    {{ $slot }}
  </span>
</a>