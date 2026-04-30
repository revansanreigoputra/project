@extends('layouts.dashboard')
@section('title', 'Dashboard')

@section('page')
  @php
    $statCards = [
      ['label' => 'Total Data Trafik', 'value' => '23.145', 'icon' => 'lucide-server', 'tone' => 'primary'],
      ['label' => 'Benign', 'value' => '1.923', 'icon' => 'lucide-shield-check', 'tone' => 'accent'],
      ['label' => 'Malicious', 'value' => '21.222', 'icon' => 'lucide-bug', 'tone' => 'danger'],
      ['label' => 'Waktu Penarikan Terakhir', 'value' => '23 Apr 2026 20:29', 'icon' => 'lucide-clock', 'tone' => 'warning'],
    ];

    $detections = [
      ['label' => 'OTH', 'value' => '14.260', 'height' => 88],
      ['label' => 'S0', 'value' => '6.395', 'height' => 39],
      ['label' => 'S3', 'value' => '1.584', 'height' => 10],
      ['label' => 'SF', 'value' => '849', 'height' => 5],
      ['label' => 'RSTR', 'value' => '53', 'height' => 2],
      ['label' => 'S1', 'value' => '4', 'height' => 1],
    ];

    $latestDetections = [
      ['row' => '23139', 'orig' => '57086', 'resp' => '6667', 'proto' => 'tcp', 'service' => 'irc', 'state' => 'S3', 'label' => 'Malicious', 'tone' => 'danger'],
      ['row' => '23140', 'orig' => '57110', 'resp' => '6667', 'proto' => 'tcp', 'service' => 'irc', 'state' => 'S3', 'label' => 'Malicious', 'tone' => 'danger'],
      ['row' => '23141', 'orig' => '57092', 'resp' => '6667', 'proto' => 'tcp', 'service' => 'irc', 'state' => 'S3', 'label' => 'Malicious', 'tone' => 'danger'],
      ['row' => '23142', 'orig' => '123', 'resp' => '123', 'proto' => 'udp', 'service' => '-', 'state' => 'S0', 'label' => 'Benign', 'tone' => 'accent'],
      ['row' => '23143', 'orig' => '123', 'resp' => '123', 'proto' => 'udp', 'service' => '-', 'state' => 'S0', 'label' => 'Benign', 'tone' => 'accent'],
      ['row' => '23144', 'orig' => '123', 'resp' => '123', 'proto' => 'udp', 'service' => '-', 'state' => 'S0', 'label' => 'Benign', 'tone' => 'accent'],
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

    {{-- Analytics --}}
    <div class="grid gap-6 lg:grid-cols-12">
      <div class="rounded-3xl bg-surface shadow-soft p-6 lg:col-span-3">
        <div class="text-sm font-semibold">Benign vs Malicious</div>

        <div class="mt-6 flex flex-col items-center gap-5">
          <div
            class="relative h-44 w-44 rounded-full"
            style="background: conic-gradient(var(--accent) 0 8.31%, var(--danger) 8.31% 100%);"
          >
            <div class="absolute inset-8 rounded-full bg-surface"></div>
          </div>

          <div class="w-full space-y-3 text-sm">
            <div class="flex items-center justify-between gap-3">
              <div class="flex items-center gap-2 text-muted">
                <span class="h-3 w-3 rounded-full bg-[color:var(--accent)]"></span>
                Benign
              </div>
              <div class="font-semibold">8,31%</div>
            </div>
            <div class="flex items-center justify-between gap-3">
              <div class="flex items-center gap-2 text-muted">
                <span class="h-3 w-3 rounded-full bg-[color:var(--danger)]"></span>
                Malicious
              </div>
              <div class="font-semibold">91,69%</div>
            </div>
          </div>
        </div>
      </div>

      <div class="rounded-3xl bg-surface shadow-soft p-6 lg:col-span-9">
        <div class="flex items-center justify-between gap-3">
          <div class="text-sm font-semibold">Distribusi Conn State</div>
          <div class="text-xs text-muted">conn.log.labeled_cleaned.xlsx</div>
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
              <div class="text-[11px] text-muted whitespace-nowrap">{{ $item['label'] }}</div>
            </div>
          @endforeach
        </div>
      </div>
    </div>

    <div class="rounded-3xl bg-surface shadow-soft p-6">
      <div class="text-sm font-semibold">Data Terakhir Dataset</div>

      <div class="mt-5 overflow-x-auto rounded-2xl border border-app">
        <table class="w-full text-sm">
          <thead class="bg-surface2 text-muted">
            <tr class="border-b border-app">
              <th class="px-4 py-3 text-left font-medium">Row ID</th>
              <th class="px-4 py-3 text-left font-medium">Orig Port</th>
              <th class="px-4 py-3 text-left font-medium">Resp Port</th>
              <th class="px-4 py-3 text-left font-medium">Proto</th>
              <th class="px-4 py-3 text-left font-medium">Service</th>
              <th class="px-4 py-3 text-left font-medium">Conn State</th>
              <th class="px-4 py-3 text-left font-medium">Label</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-app">
            @foreach ($latestDetections as $item)
              <tr class="hover:bg-surface2/50">
                <td class="px-4 py-3">{{ $item['row'] }}</td>
                <td class="px-4 py-3 text-muted">{{ $item['orig'] }}</td>
                <td class="px-4 py-3 text-muted">{{ $item['resp'] }}</td>
                <td class="px-4 py-3 text-muted">{{ $item['proto'] }}</td>
                <td class="px-4 py-3 text-muted">{{ $item['service'] }}</td>
                <td class="px-4 py-3 text-muted">{{ $item['state'] }}</td>
                <td class="px-4 py-3">
                  <span
                    @class([
                      'px-3 py-1 rounded-full text-xs font-semibold',
                      'bg-[rgba(248,113,113,0.18)] text-[color:var(--danger)]' => $item['tone'] === 'danger',
                      'bg-[rgba(34,197,94,0.18)] text-[color:var(--accent)]' => $item['tone'] === 'accent',
                    ])
                  >
                    {{ $item['label'] }}
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
