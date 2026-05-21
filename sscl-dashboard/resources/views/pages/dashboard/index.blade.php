@extends('layouts.dashboard')
@section('title', 'Dashboard')

@section('page')
  @php
    $prediction = $prediction ?? null;
    $summary = $prediction['summary'] ?? [
      'normal' => 0,
      'malware' => 0,
      'unknown' => 0,
      'normal_percent' => 0,
      'malware_percent' => 0,
      'average_confidence' => 0,
      'labels' => [],
      'malware_labels' => [],
    ];

    $hasPrediction = filled($prediction);
    $total = (int) ($prediction['total'] ?? 0);
    $fileName = $prediction['file_name'] ?? 'Belum ada file diproses';
    $processedAt = $prediction['processed_at'] ?? '-';
    $normal = (int) ($summary['normal'] ?? 0);
    $malware = (int) ($summary['malware'] ?? 0);
    $unknown = (int) ($summary['unknown'] ?? 0);
    $normalPercent = (float) ($summary['normal_percent'] ?? 0);
    $malwarePercent = (float) ($summary['malware_percent'] ?? 0);
    $averageConfidence = (float) ($summary['average_confidence'] ?? 0);

    $formatNumber = fn ($value) => number_format((float) $value, 0, ',', '.');
    $formatPercent = fn ($value) => number_format((float) $value, 2, ',', '.');
    $toneForLabel = fn ($label) => $label === 'Normal' ? 'accent' : ($label === 'Unknown' ? 'warning' : 'danger');

    $labelCounts = collect($summary['labels'] ?? [])
      ->map(fn ($count) => (int) $count)
      ->filter(fn ($count) => $count > 0)
      ->whenEmpty(fn () => collect([
        'Normal' => $normal,
        'Malware' => $malware,
        'Unknown' => $unknown,
      ])->filter(fn ($count) => $count > 0));

    $maxLabelCount = max($labelCounts->max() ?: 0, 1);
    $detections = $labelCounts
      ->sortByDesc(fn ($count) => $count)
      ->take(8)
      ->map(fn ($count, $label) => [
        'label' => $label,
        'value' => $formatNumber($count),
        'height' => max(12, round(((int) $count / $maxLabelCount) * 100)),
        'tone' => $toneForLabel($label),
      ])
      ->values();

    $malwareLabels = collect($summary['malware_labels'] ?? [])
      ->map(fn ($count) => (int) $count)
      ->filter(fn ($count) => $count > 0)
      ->sortDesc()
      ->take(5);

    $latestDetections = collect($prediction['results'] ?? [])->take(8);

    $statCards = [
      [
        'label' => 'Total Data',
        'value' => $formatNumber($total),
        'meta' => $hasPrediction ? 'Baris pada file terakhir' : 'Menunggu upload',
        'icon' => 'lucide-server',
        'tone' => 'primary',
      ],
      [
        'label' => 'Data Normal',
        'value' => $formatNumber($normal),
        'meta' => $formatPercent($normalPercent) . '% dari total',
        'icon' => 'lucide-shield-check',
        'tone' => 'accent',
      ],
      [
        'label' => 'Terdeteksi Malware',
        'value' => $formatNumber($malware),
        'meta' => $formatPercent($malwarePercent) . '% perlu perhatian',
        'icon' => 'lucide-bug',
        'tone' => 'danger',
      ],
      [
        'label' => 'Avg. Confidence',
        'value' => $formatPercent($averageConfidence) . '%',
        'meta' => 'Rata-rata keyakinan model',
        'icon' => 'lucide-list-checks',
        'tone' => 'info',
      ],
      [
        'label' => 'Waktu Proses Terakhir',
        'value' => $processedAt,
        'meta' => $hasPrediction ? 'Deteksi terakhir diproses' : 'Belum ada proses',
        'icon' => 'lucide-clock',
        'tone' => 'warning',
      ],
    ];

    $canViewResults = auth()->user()?->can('detection.view') ?? false;
  @endphp

  <div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
      @foreach ($statCards as $card)
        <div class="group rounded-3xl border border-app bg-surface p-5 shadow-soft transition-transform duration-200 hover:-translate-y-1">
          <div class="flex items-start justify-between gap-4">
            <div
              @class([
                'grid h-12 w-12 shrink-0 place-items-center rounded-2xl',
                'bg-primary-soft text-primary' => $card['tone'] === 'primary',
                'bg-[rgba(34,197,94,0.15)] text-[color:var(--accent)]' => $card['tone'] === 'accent',
                'bg-[rgba(248,113,113,0.15)] text-[color:var(--danger)]' => $card['tone'] === 'danger',
                'bg-[rgba(56,189,248,0.15)] text-[color:var(--info)]' => $card['tone'] === 'info',
                'bg-[rgba(251,191,36,0.18)] text-[color:var(--warning)]' => $card['tone'] === 'warning',
              ])
            >
              <x-dynamic-component :component="$card['icon']" class="h-6 w-6 transition-transform duration-200 group-hover:scale-110" />
            </div>
            {{-- <div class="rounded-full bg-surface2 px-2.5 py-1 text-[11px] font-semibold text-muted">
              Live
            </div> --}}
          </div>

          <div class="mt-5">
            <div class="text-xs font-semibold text-muted">{{ $card['label'] }}</div>
            <div class="mt-1 break-words text-2xl font-semibold leading-tight">{{ $card['value'] }}</div>
            <div class="mt-2 text-xs leading-5 text-muted">{{ $card['meta'] }}</div>
          </div>
        </div>
      @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-12">
      <section class="rounded-3xl border border-app bg-surface p-6 shadow-soft lg:col-span-8">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
          <div>
            <div class="text-sm font-semibold">Distribusi Prediksi</div>
            <div class="mt-1 text-xs text-muted">Label terbanyak dari proses deteksi terakhir.</div>
          </div>
          <div class="max-w-full truncate rounded-2xl bg-surface2 px-3 py-2 text-xs font-semibold text-muted">
            {{ $fileName }}
          </div>
        </div>

        @if ($detections->isNotEmpty())
          <div class="mt-7 flex h-72 items-end gap-3 overflow-x-auto border-b border-app pb-4 md:gap-5">
            @foreach ($detections as $item)
              <div class="flex min-w-16 flex-1 flex-col items-center gap-3">
                <div class="text-xs font-semibold text-muted">{{ $item['value'] }}</div>
                <div class="flex h-52 w-full max-w-20 items-end overflow-hidden rounded-t-2xl bg-surface2">
                  <div
                    @class([
                      'w-full rounded-t-2xl transition-all duration-300',
                      'bg-[color:var(--accent)]' => $item['tone'] === 'accent',
                      'bg-[color:var(--danger)]' => $item['tone'] === 'danger',
                      'bg-[color:var(--warning)]' => $item['tone'] === 'warning',
                    ])
                    style="height: {{ $item['height'] }}%"
                  ></div>
                </div>
                <div class="max-w-24 truncate text-xs font-medium text-muted" title="{{ $item['label'] }}">{{ $item['label'] }}</div>
              </div>
            @endforeach
          </div>
        @else
          <div class="mt-7 grid min-h-72 place-items-center rounded-3xl border border-dashed border-app bg-surface2 px-6 text-center">
            <div>
              <x-lucide-list-checks class="mx-auto h-10 w-10 text-muted" />
              <div class="mt-3 text-sm font-semibold">Belum ada distribusi label</div>
              <div class="mt-1 text-xs text-muted">Chart akan muncul setelah proses deteksi selesai.</div>
            </div>
          </div>
        @endif
      </section>

      <section class="rounded-3xl border border-app bg-surface p-6 shadow-soft lg:col-span-4">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="text-sm font-semibold">Label Berisiko</div>
            <div class="mt-1 text-xs text-muted">Prioritas investigasi</div>
          </div>
          <div class="rounded-2xl bg-[rgba(248,113,113,0.14)] p-2 text-[color:var(--danger)]">
            <x-lucide-bug class="h-5 w-5" />
          </div>
        </div>

        <div class="mt-6 space-y-4">
          @forelse ($malwareLabels as $label => $count)
            @php
              $width = max(6, round(((int) $count / $maxLabelCount) * 100));
            @endphp

            <div>
              <div class="flex items-center justify-between gap-3 text-sm">
                <span class="truncate font-semibold" title="{{ $label }}">{{ $label }}</span>
                <span class="text-muted">{{ $formatNumber($count) }}</span>
              </div>
              <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-surface2">
                <div class="h-full rounded-full bg-[color:var(--danger)]" style="width: {{ $width }}%"></div>
              </div>
            </div>
          @empty
            <div class="rounded-3xl border border-dashed border-app bg-surface2 px-5 py-8 text-center">
              <x-lucide-shield-check class="mx-auto h-10 w-10 text-[color:var(--accent)]" />
              <div class="mt-3 text-sm font-semibold">Tidak ada label malware</div>
              <div class="mt-1 text-xs leading-5 text-muted">Hasil terakhir tidak menemukan kategori malware spesifik.</div>
            </div>
          @endforelse
        </div>

        <div class="mt-6 rounded-3xl border border-app bg-surface2 p-4">
          <div class="flex items-center justify-between gap-3 text-xs text-muted">
            <span>Rata-rata confidence</span>
            <span class="font-semibold text-app">{{ $formatPercent($averageConfidence) }}%</span>
          </div>
          <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-app">
            <div class="h-full rounded-full bg-primary" style="width: {{ max(0, min(100, $averageConfidence)) }}%"></div>
          </div>
        </div>
      </section>
    </div>

    <section class="rounded-3xl border border-app bg-surface p-6 shadow-soft">
      <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <div class="text-sm font-semibold">Hasil Prediksi Terbaru</div>
          <div class="mt-1 text-xs text-muted">{{ $hasPrediction ? 'Menampilkan 8 baris pertama dari proses terakhir.' : 'Belum ada hasil deteksi di database.' }}</div>
        </div>

        @if ($canViewResults)
          <a href="{{ route('hasildeteksi.index') }}" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-app px-4 py-2.5 text-sm font-semibold transition-colors hover:bg-surface2">
            <x-lucide-list-checks class="h-4 w-4" />
            Lihat Semua
          </a>
        @endif
      </div>

      <div class="mt-5 overflow-hidden rounded-3xl border border-app">
        <div class="max-h-[460px] overflow-auto">
          <table class="w-full min-w-[820px] text-sm">
            <thead class="sticky top-0 z-10 bg-surface2 text-muted">
              <tr class="border-b border-app">
                <th class="px-4 py-3 text-left font-medium">Row</th>
                <th class="px-4 py-3 text-left font-medium">Label</th>
                <th class="px-4 py-3 text-left font-medium">Confidence</th>
                <th class="px-4 py-3 text-left font-medium">Prob. Normal</th>
                <th class="px-4 py-3 text-left font-medium">Prob. Label</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-app">
              @forelse ($latestDetections as $result)
                @php
                  $label = $result['label'] ?? 'Unknown';
                  $tone = $toneForLabel($label);
                  $probabilities = $result['probabilities'] ?? [];
                  $confidencePercent = max(0, min(100, ((float) ($result['confidence'] ?? 0)) * 100));
                  $normalProbability = max(0, min(100, ((float) ($probabilities['Normal'] ?? 0)) * 100));
                  $labelProbability = max(0, min(100, ((float) ($probabilities[$label] ?? $result['confidence'] ?? 0)) * 100));
                @endphp
                <tr class="transition-colors hover:bg-surface2">
                  <td class="px-4 py-3 font-semibold">{{ $result['row'] ?? $loop->index }}</td>
                  <td class="px-4 py-3">
                    <span
                      @class([
                        'inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold',
                        'bg-[rgba(248,113,113,0.18)] text-[color:var(--danger)]' => $tone === 'danger',
                        'bg-[rgba(34,197,94,0.18)] text-[color:var(--accent)]' => $tone === 'accent',
                        'bg-[rgba(251,191,36,0.18)] text-[color:var(--warning)]' => $tone === 'warning',
                      ])
                    >
                      <span
                        @class([
                          'h-2 w-2 rounded-full',
                          'bg-[color:var(--danger)]' => $tone === 'danger',
                          'bg-[color:var(--accent)]' => $tone === 'accent',
                          'bg-[color:var(--warning)]' => $tone === 'warning',
                        ])
                      ></span>
                      {{ $label }}
                    </span>
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex min-w-36 items-center gap-3">
                      <div class="h-2 flex-1 overflow-hidden rounded-full bg-surface2">
                        <div class="h-full rounded-full bg-primary" style="width: {{ $confidencePercent }}%"></div>
                      </div>
                      <span class="w-16 text-right font-semibold">{{ $formatPercent($confidencePercent) }}%</span>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-muted">{{ $formatPercent($normalProbability) }}%</td>
                  <td class="px-4 py-3 text-muted">{{ $formatPercent($labelProbability) }}%</td>
                </tr>
              @empty
                <tr>
                  <td class="px-6 py-10 text-center text-muted" colspan="5">
                    <x-lucide-file-text class="mx-auto h-10 w-10" />
                    <div class="mt-3 text-sm font-semibold text-app">Belum ada hasil prediksi</div>
                    <div class="mt-1 text-xs">Upload data untuk mulai menjalankan deteksi malware.</div>
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>
@endsection
