@props(['value' => 50])

@php
  $v = max(0, min(100, (int)$value));
  $circ = 264; // kira-kira keliling untuk r ~ 42
  $offset = $circ - ($circ * $v / 100);
@endphp

<div class="relative h-20 w-20">
  <svg viewBox="0 0 100 100" class="-rotate-90 h-20 w-20">
    <circle cx="50" cy="50" r="42" fill="none" stroke="var(--ring-track)" stroke-width="10" />
    <circle
      cx="50" cy="50" r="42"
      fill="none"
      stroke="var(--primary)"
      stroke-width="10"
      stroke-linecap="round"
      style="stroke-dasharray: {{ $circ }}; stroke-dashoffset: {{ $offset }};"
    />
  </svg>

  <div class="absolute inset-0 grid place-items-center text-sm font-semibold">
    {{ $v }}%
  </div>
</div>