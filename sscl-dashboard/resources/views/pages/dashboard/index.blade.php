@extends('layouts.dashboard')
@section('title', 'Dashboard')

@section('page')
  @php
    $statCards = [
      ['label' => 'Total Data Trafik', 'value' => '125.430', 'icon' => 'lucide-server', 'tone' => 'primary'],
      ['label' => 'Normal', 'value' => '98.765', 'icon' => 'lucide-shield-check', 'tone' => 'accent'],
      ['label' => 'Malware', 'value' => '26.665', 'icon' => 'lucide-bug', 'tone' => 'danger'],
      ['label' => 'Waktu Penarikan Terakhir', 'value' => '20 Mei 2025 10:15', 'icon' => 'lucide-clock', 'tone' => 'warning'],
    ];

    $detections = [
      ['date' => '14 Mei', 'value' => '6.5K', 'height' => 34],
      ['date' => '15 Mei', 'value' => '12.8K', 'height' => 64],
      ['date' => '16 Mei', 'value' => '16.4K', 'height' => 82],
      ['date' => '17 Mei', 'value' => '10.1K', 'height' => 51],
      ['date' => '18 Mei', 'value' => '17.2K', 'height' => 86],
      ['date' => '19 Mei', 'value' => '13.5K', 'height' => 68],
      ['date' => '20 Mei', 'value' => '11.4K', 'height' => 57],
    ];

    $latestDetections = [
      ['time' => '20 Mei 2025 10:15:32', 'source' => '192.168.1.10', 'result' => 'Malware', 'tone' => 'danger'],
      ['time' => '20 Mei 2025 10:14:33', 'source' => '10.0.0.5', 'result' => 'Normal', 'tone' => 'accent'],
      ['time' => '20 Mei 2025 10:12:45', 'source' => '172.16.5.23', 'result' => 'Malware', 'tone' => 'danger'],
      ['time' => '20 Mei 2025 10:11:02', 'source' => '192.168.1.25', 'result' => 'Normal', 'tone' => 'accent'],
      ['time' => '20 Mei 2025 10:09:33', 'source' => '10.0.0.12', 'result' => 'Malware', 'tone' => 'danger'],
    ];
  @endphp

  <div class="space-y-6">
    {{-- Summary Cards --}}
    <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
      @foreach ($statCards as $card)
        <div class="rounded-3xl bg-surface shadow-soft p-6 flex items-center gap-4">
          <div
            @class([
              'h-14 w-14 shrink-0 rounded-2xl grid place-items-center',
              'bg-primary-soft text-primary' => $card['tone'] === 'primary',
              'bg-[rgba(34,197,94,0.15)] text-[color:var(--accent)]' => $card['tone'] === 'accent',
              'bg-[rgba(248,113,113,0.15)] text-[color:var(--danger)]' => $card['tone'] === 'danger',
              'bg-[rgba(251,191,36,0.18)] text-[color:var(--warning)]' => $card['tone'] === 'warning',
            ])
          >
            <x-dynamic-component :component="$card['icon']" class="h-7 w-7" />
          </div>

          <div class="min-w-0">
            <div class="text-xs font-semibold text-muted">{{ $card['label'] }}</div>
            <div class="mt-1 text-2xl font-semibold leading-tight">{{ $card['value'] }}</div>
          </div>
        </div>
      @endforeach
    </div>

    {{-- Security Status --}}
    {{-- <div class="grid gap-6 lg:grid-cols-12">
      <div class="lg:col-span-4 rounded-3xl bg-surface shadow-soft p-5">
        <div class="flex items-center gap-3">
          <div class="h-11 w-11 rounded-2xl bg-[rgba(251,191,36,0.18)] grid place-items-center">
            <x-lucide-shield-alert class="h-5 w-5 text-[color:var(--warning)]" />
          </div>
          <div>
            <div class="text-xs text-muted">Status Keamanan</div>
            <div class="text-lg font-semibold">Waspada</div>
          </div>
        </div>
      </div>
    </div> --}}

    {{-- Analytics --}}
    <div class="grid gap-6 lg:grid-cols-12">
      <div class="rounded-3xl bg-surface shadow-soft p-6 lg:col-span-3">
        <div class="text-sm font-semibold">Normal vs Malware</div>

        <div class="mt-6 flex flex-col items-center gap-5">
          <div
            class="relative h-44 w-44 rounded-full"
            style="background: conic-gradient(var(--accent) 0 78.75%, var(--danger) 78.75% 100%);"
          >
            <div class="absolute inset-8 rounded-full bg-surface"></div>
          </div>

          <div class="w-full space-y-3 text-sm">
            <div class="flex items-center justify-between gap-3">
              <div class="flex items-center gap-2 text-muted">
                <span class="h-3 w-3 rounded-full bg-[color:var(--accent)]"></span>
                Normal
              </div>
              <div class="font-semibold">78,75%</div>
            </div>
            <div class="flex items-center justify-between gap-3">
              <div class="flex items-center gap-2 text-muted">
                <span class="h-3 w-3 rounded-full bg-[color:var(--danger)]"></span>
                Malware
              </div>
              <div class="font-semibold">21,25%</div>
            </div>
          </div>
        </div>
      </div>

      <div class="rounded-3xl bg-surface shadow-soft p-6 lg:col-span-9">
        <div class="flex items-center justify-between gap-3">
          <div class="text-sm font-semibold">Jumlah Deteksi</div>
          <div class="text-xs text-muted">14-20 Mei 2025</div>
        </div>

        <div class="mt-6 flex h-64 items-end gap-3 border-b border-app pb-4">
          @foreach ($detections as $item)
            <div class="flex min-w-0 flex-1 flex-col items-center gap-3">
              <div class="text-xs font-semibold text-muted">{{ $item['value'] }}</div>
              <div class="w-full max-w-10 rounded-t-2xl bg-primary-soft overflow-hidden">
                <div
                  class="w-full rounded-t-2xl bg-primary"
                  style="height: {{ $item['height'] * 1.8 }}px"
                ></div>
              </div>
              <div class="text-[11px] text-muted whitespace-nowrap">{{ $item['date'] }}</div>
            </div>
          @endforeach
        </div>
      </div>

    </div>

    <div class="rounded-3xl bg-surface shadow-soft p-6">
      <div class="text-sm font-semibold">Deteksi Terbaru</div>

      <div class="mt-5 overflow-x-auto rounded-2xl border border-app">
        <table class="w-full text-sm">
          <thead class="bg-surface2 text-muted">
            <tr class="border-b border-app">
              <th class="px-4 py-3 text-left font-medium">Waktu</th>
              <th class="px-4 py-3 text-left font-medium">Sumber</th>
              <th class="px-4 py-3 text-left font-medium">Hasil</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-app">
            @foreach ($latestDetections as $item)
              <tr class="hover:bg-surface2/50">
                <td class="px-4 py-3 text-muted whitespace-nowrap">{{ $item['time'] }}</td>
                <td class="px-4 py-3 text-muted whitespace-nowrap">{{ $item['source'] }}</td>
                <td class="px-4 py-3">
                  <span
                    @class([
                      'px-3 py-1 rounded-full text-xs font-semibold',
                      'bg-[rgba(248,113,113,0.18)] text-[color:var(--danger)]' => $item['tone'] === 'danger',
                      'bg-[rgba(34,197,94,0.18)] text-[color:var(--accent)]' => $item['tone'] === 'accent',
                    ])
                  >
                    {{ $item['result'] }}
                  </span>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection
