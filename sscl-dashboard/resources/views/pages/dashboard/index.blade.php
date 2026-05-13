@extends('layouts.dashboard')
@section('title', 'Dashboard')

@section('page')
  @php
    $prediction = $prediction ?? null;
    $summary = $prediction['summary'] ?? [
      'normal' => 0,
      'malware' => 0,
      'normal_percent' => 0,
      'malware_percent' => 0,
      'average_confidence' => 0,
      'labels' => [],
      'malware_labels' => [],
    ];
    $total = $prediction['total'] ?? 0;
    $fileName = $prediction['file_name'] ?? 'Belum ada file diproses';
    $processedAt = $prediction['processed_at'] ?? '-';
    $normal = (int) ($summary['normal'] ?? 0);
    $malware = (int) ($summary['malware'] ?? 0);
    $normalPercent = (float) ($summary['normal_percent'] ?? 0);
    $malwarePercent = (float) ($summary['malware_percent'] ?? 0);
    $formatNumber = fn ($value) => number_format((float) $value, 0, ',', '.');
    $formatPercent = fn ($value) => number_format((float) $value, 2, ',', '.');
    $toneForLabel = fn ($label) => $label === 'Normal' ? 'accent' : ($label === 'Unknown' ? 'warning' : 'danger');
    $labelCounts = collect($summary['labels'] ?? [])->whenEmpty(fn ($items) => collect([
      'Normal' => $normal,
      'Malware' => $malware,
    ])->filter());
    $maxLabelCount = max($labelCounts->max() ?: 0, 1);

    $statCards = [
      ['label' => 'Total Data Diproses', 'value' => $formatNumber($total), 'icon' => 'lucide-server', 'tone' => 'primary'],
      ['label' => 'Normal', 'value' => $formatNumber($normal), 'icon' => 'lucide-shield-check', 'tone' => 'accent'],
      ['label' => 'Total Malware', 'value' => $formatNumber($malware), 'icon' => 'lucide-bug', 'tone' => 'danger'],
      ['label' => 'Waktu Proses Terakhir', 'value' => $processedAt, 'icon' => 'lucide-clock', 'tone' => 'warning'],
    ];

    $detections = $labelCounts
      ->sortByDesc(fn ($count, $label) => $label === 'Normal' ? PHP_INT_MAX : $count)
      ->take(7)
      ->map(fn ($count, $label) => [
        'label' => $label,
        'value' => $formatNumber($count),
        'height' => max(8, round(((int) $count / $maxLabelCount) * 88)),
        'tone' => $toneForLabel($label),
      ])
      ->values();

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
        <div class="text-sm font-semibold">Normal vs Malware</div>

        <div class="mt-6 flex flex-col items-center gap-5">
          <div
            class="relative h-44 w-44 rounded-full"
            style="background: conic-gradient(var(--accent) 0 {{ $normalPercent }}%, var(--danger) {{ $normalPercent }}% 100%);"
          >
            <div class="absolute inset-8 rounded-full bg-surface"></div>
          </div>

          <div class="w-full space-y-3 text-sm">
            <div class="flex items-center justify-between gap-3">
              <div class="flex items-center gap-2 text-muted">
                <span class="h-3 w-3 rounded-full bg-[color:var(--accent)]"></span>
                Normal
              </div>
              <div class="font-semibold">{{ $formatPercent($normalPercent) }}%</div>
            </div>
            <div class="flex items-center justify-between gap-3">
              <div class="flex items-center gap-2 text-muted">
                <span class="h-3 w-3 rounded-full bg-[color:var(--danger)]"></span>
                Malware
              </div>
              <div class="font-semibold">{{ $formatPercent($malwarePercent) }}%</div>
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
                    'bg-[color:var(--warning)]' => $item['tone'] === 'warning',
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
        <div class="text-xs text-muted">{{ $prediction ? 'Dari proses deteksi terakhir' : 'Belum ada hasil deteksi di database' }}</div>
      </div>

      <div class="mt-5 max-h-[420px] overflow-auto rounded-2xl border border-app">
        <table class="w-full min-w-[760px] text-sm">
          <thead class="sticky top-0 z-10 bg-surface2 text-muted">
            <tr class="border-b border-app">
              <th class="px-3 py-2.5 text-left font-medium">Row</th>
              <th class="px-3 py-2.5 text-left font-medium">Label</th>
              <th class="px-3 py-2.5 text-left font-medium">Confidence</th>
              <th class="px-3 py-2.5 text-left font-medium">Prob. Normal</th>
              <th class="px-3 py-2.5 text-left font-medium">Prob. Label</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-app">
            @forelse ($latestDetections as $result)
              @php
                $label = $result['label'] ?? 'Unknown';
                $tone = $toneForLabel($label);
                $probabilities = $result['probabilities'] ?? [];
              @endphp
              <tr class="hover:bg-surface2/50">
                <td class="px-3 py-2.5">{{ $result['row'] ?? $loop->index }}</td>
                <td class="px-3 py-2.5">
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
                <td class="px-3 py-2.5 font-semibold">{{ $formatPercent(((float) ($result['confidence'] ?? 0)) * 100) }}%</td>
                <td class="px-3 py-2.5 text-muted">{{ $formatPercent(((float) ($probabilities['Normal'] ?? 0)) * 100) }}%</td>
                <td class="px-3 py-2.5 text-muted">{{ $formatPercent(((float) ($probabilities[$label] ?? $result['confidence'] ?? 0)) * 100) }}%</td>
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
