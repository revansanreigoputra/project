@extends('layouts.dashboard')
@section('title', 'Dashboard')

@section('page')
  @php
    $prediction = session('last_prediction');
    $summary = $prediction['summary'] ?? [
      'benign' => 1923,
      'malicious' => 21222,
      'benign_percent' => 8.31,
      'malicious_percent' => 91.69,
      'average_confidence' => 0,
    ];
    $total = $prediction['total'] ?? 23145;
    $fileName = $prediction['file_name'] ?? 'conn.log.labeled_cleaned.xlsx';
    $processedAt = $prediction['processed_at'] ?? '23/04/2026 20:29';
    $benign = (int) ($summary['benign'] ?? 0);
    $malicious = (int) ($summary['malicious'] ?? 0);
    $benignPercent = (float) ($summary['benign_percent'] ?? 0);
    $maliciousPercent = (float) ($summary['malicious_percent'] ?? 0);
    $maxLabelCount = max($benign, $malicious, 1);
    $formatNumber = fn ($value) => number_format((float) $value, 0, ',', '.');
    $formatPercent = fn ($value) => number_format((float) $value, 2, ',', '.');

    $statCards = [
      ['label' => 'Total Data Diproses', 'value' => $formatNumber($total), 'icon' => 'lucide-server', 'tone' => 'primary'],
      ['label' => 'Benign', 'value' => $formatNumber($benign), 'icon' => 'lucide-shield-check', 'tone' => 'accent'],
      ['label' => 'Malicious', 'value' => $formatNumber($malicious), 'icon' => 'lucide-bug', 'tone' => 'danger'],
      ['label' => 'Waktu Proses Terakhir', 'value' => $processedAt, 'icon' => 'lucide-clock', 'tone' => 'warning'],
    ];

    $detections = [
      ['label' => 'Benign', 'value' => $formatNumber($benign), 'height' => max(8, round(($benign / $maxLabelCount) * 88)), 'tone' => 'accent'],
      ['label' => 'Malicious', 'value' => $formatNumber($malicious), 'height' => max(8, round(($malicious / $maxLabelCount) * 88)), 'tone' => 'danger'],
    ];

    $latestDetections = collect($prediction['results'] ?? [])->take(8);
  @endphp

  <div class="space-y-6">
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

    <div class="grid gap-6 lg:grid-cols-12">
      <div class="rounded-3xl bg-surface shadow-soft p-6 lg:col-span-3">
        <div class="text-sm font-semibold">Benign vs Malicious</div>

        <div class="mt-6 flex flex-col items-center gap-5">
          <div
            class="relative h-44 w-44 rounded-full"
            style="background: conic-gradient(var(--accent) 0 {{ $benignPercent }}%, var(--danger) {{ $benignPercent }}% 100%);"
          >
            <div class="absolute inset-8 rounded-full bg-surface"></div>
          </div>

          <div class="w-full space-y-3 text-sm">
            <div class="flex items-center justify-between gap-3">
              <div class="flex items-center gap-2 text-muted">
                <span class="h-3 w-3 rounded-full bg-[color:var(--accent)]"></span>
                Benign
              </div>
              <div class="font-semibold">{{ $formatPercent($benignPercent) }}%</div>
            </div>
            <div class="flex items-center justify-between gap-3">
              <div class="flex items-center gap-2 text-muted">
                <span class="h-3 w-3 rounded-full bg-[color:var(--danger)]"></span>
                Malicious
              </div>
              <div class="font-semibold">{{ $formatPercent($maliciousPercent) }}%</div>
            </div>
          </div>
        </div>
      </div>

      <div class="rounded-3xl bg-surface shadow-soft p-6 lg:col-span-9">
        <div class="flex items-center justify-between gap-3">
          <div class="text-sm font-semibold">Distribusi Prediksi</div>
          <div class="text-xs text-muted">{{ $fileName }}</div>
        </div>

        <div class="mt-6 flex h-64 items-end gap-6 border-b border-app pb-4">
          @foreach ($detections as $item)
            <div class="flex min-w-0 flex-1 flex-col items-center gap-3">
              <div class="text-xs font-semibold text-muted">{{ $item['value'] }}</div>
              <div class="w-full max-w-20 rounded-t-2xl bg-primary-soft overflow-hidden">
                <div
                  @class([
                    'w-full rounded-t-2xl',
                    'bg-[color:var(--accent)]' => $item['tone'] === 'accent',
                    'bg-[color:var(--danger)]' => $item['tone'] === 'danger',
                  ])
                  style="height: {{ $item['height'] * 1.8 }}px"
                ></div>
              </div>
              <div class="text-xs text-muted whitespace-nowrap">{{ $item['label'] }}</div>
            </div>
          @endforeach
        </div>
      </div>
    </div>

    <div class="rounded-3xl bg-surface shadow-soft p-6">
      <div class="flex flex-col gap-1 md:flex-row md:items-center md:justify-between">
        <div class="text-sm font-semibold">Hasil Prediksi Terbaru</div>
        <div class="text-xs text-muted">{{ $prediction ? 'Dari upload terakhir' : 'Belum ada upload baru, menampilkan ringkasan dataset contoh' }}</div>
      </div>

      <div class="mt-5 overflow-x-auto rounded-2xl border border-app">
        <table class="w-full text-sm">
          <thead class="bg-surface2 text-muted">
            <tr class="border-b border-app">
              <th class="px-4 py-3 text-left font-medium">Row</th>
              <th class="px-4 py-3 text-left font-medium">Label</th>
              <th class="px-4 py-3 text-left font-medium">Confidence</th>
              <th class="px-4 py-3 text-left font-medium">Prob. Benign</th>
              <th class="px-4 py-3 text-left font-medium">Prob. Malicious</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-app">
            @forelse ($latestDetections as $result)
              @php
                $label = $result['label'] ?? 'Unknown';
                $tone = $label === 'Malicious' ? 'danger' : 'accent';
              @endphp
              <tr class="hover:bg-surface2/50">
                <td class="px-4 py-3">{{ $result['row'] ?? $loop->index }}</td>
                <td class="px-4 py-3">
                  <span
                    @class([
                      'px-3 py-1 rounded-full text-xs font-semibold',
                      'bg-[rgba(248,113,113,0.18)] text-[color:var(--danger)]' => $tone === 'danger',
                      'bg-[rgba(34,197,94,0.18)] text-[color:var(--accent)]' => $tone === 'accent',
                    ])
                  >
                    {{ $label }}
                  </span>
                </td>
                <td class="px-4 py-3 font-semibold">{{ $formatPercent(((float) ($result['confidence'] ?? 0)) * 100) }}%</td>
                <td class="px-4 py-3 text-muted">{{ $formatPercent(((float) data_get($result, 'probabilities.Benign', 0)) * 100) }}%</td>
                <td class="px-4 py-3 text-muted">{{ $formatPercent(((float) data_get($result, 'probabilities.Malicious', 0)) * 100) }}%</td>
              </tr>
            @empty
              <tr>
                <td class="px-4 py-4 text-muted" colspan="5">Belum ada hasil prediksi dari Upload Data.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection
