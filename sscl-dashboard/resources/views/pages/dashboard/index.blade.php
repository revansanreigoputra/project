@extends('layouts.dashboard')
@section('title', 'Dashboard')

@section('page')
  @php
    $stats = array_merge([
      'total_detections' => 0,
      'total_uploads' => 0,
      'total_rows' => 0,
      'normal_rows' => 0,
      'malware_rows' => 0,
      'unknown_rows' => 0,
      'normal_percent' => 0,
      'malware_percent' => 0,
      'average_confidence' => 0,
      'total_users' => 0,
    ], $stats ?? []);
    $trend = collect($trend ?? []);
    $labelStats = collect($labelStats ?? []);
    $recentDetections = collect($recentDetections ?? []);
    $latestActivities = collect($latestActivities ?? []);
    $dashboardDetails = $dashboardDetails ?? [];
    $formatNumber = fn ($value) => number_format((float) $value, 0, ',', '.');
    $formatPercent = fn ($value) => number_format((float) $value, 2, ',', '.');
    $maxTrend = max((int) $trend->max('total'), (int) $trend->max('detections'), 1);
    $maxLabel = max((int) $labelStats->max('count'), 1);
    $cards = [
      [
        'label' => 'Total Deteksi',
        'value' => $formatNumber($stats['total_detections']),
        'meta' => $formatNumber($stats['total_uploads']).' file upload tersimpan',
        'icon' => 'lucide-radar',
        'tone' => 'primary',
        'detail' => 'detections',
      ],
      [
        'label' => 'Malware',
        'value' => $formatNumber($stats['malware_rows']),
        'meta' => $formatPercent($stats['malware_percent']).'% dari seluruh baris',
        'icon' => 'lucide-bug',
        'tone' => 'danger',
        'detail' => 'malware',
      ],
      [
        'label' => 'Non-Malware',
        'value' => $formatNumber($stats['normal_rows']),
        'meta' => $formatPercent($stats['normal_percent']).'% data normal',
        'icon' => 'lucide-shield-check',
        'tone' => 'accent',
        'detail' => 'normal',
      ],
      [
        'label' => 'Pengguna',
        'value' => $formatNumber($stats['total_users']),
        'meta' => 'Akun yang tersimpan',
        'icon' => 'lucide-users-round',
        'tone' => 'info',
        'detail' => 'users',
      ],
      [
        'label' => 'Avg. Confidence',
        'value' => $formatPercent($stats['average_confidence']).'%',
        'meta' => $formatNumber($stats['total_rows']).' baris dianalisis',
        'icon' => 'lucide-gauge',
        'tone' => 'warning',
        'detail' => 'detections',
      ],
    ];
  @endphp

  <div
    class="space-y-6"
    x-data="{
      detailOpen: false,
      activeDetail: { title: '', description: '', items: [] },
      latestActivities: @js($latestActivities->values()),
      activityPage: 1,
      activityPerPage: 4,
      get activityTotalPages() {
        return Math.max(1, Math.ceil(this.latestActivities.length / this.activityPerPage));
      },
      get pagedActivities() {
        const start = (this.activityPage - 1) * this.activityPerPage;
        return this.latestActivities.slice(start, start + this.activityPerPage);
      },
      openDetail(detail) {
        this.activeDetail = {
          title: detail?.title || 'Detail',
          description: detail?.description || '',
          items: detail?.items || [],
        };
        this.detailOpen = true;
      }
    }"
  >
    <section class="overflow-hidden rounded-3xl border border-app bg-surface shadow-soft">
      <div class="relative p-6 md:p-7">
        <div class="absolute inset-x-0 top-0 h-1 bg-[linear-gradient(90deg,var(--primary),var(--accent),var(--info),var(--warning))]"></div>
        <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
          <div class="max-w-3xl">
            <div class="inline-flex items-center gap-2 rounded-full bg-primary-soft px-3 py-1 text-xs font-semibold text-primary">
              <x-lucide-database class="h-3.5 w-3.5" />
              {{ $scopeLabel ?? 'Seluruh sistem' }}
            </div>
            <h1 class="mt-4 text-2xl font-semibold leading-tight md:text-3xl">Ringkasan Data Sistem</h1>
            <p class="mt-2 text-sm leading-6 text-muted">
              Statistik ini merangkum seluruh data yang tersimpan: upload, deteksi selesai, komposisi malware/non-malware, pengguna, serta aktivitas terbaru.
            </p>
          </div>

          <div class="grid min-w-64 gap-3 rounded-3xl border border-app bg-surface2 p-4 text-sm">
            <div class="flex items-center justify-between gap-4">
              <span class="text-muted">Unknown</span>
              <span class="font-semibold">{{ $formatNumber($stats['unknown_rows']) }}</span>
            </div>
            <div class="flex items-center justify-between gap-4">
              <span class="text-muted">Total baris</span>
              <span class="font-semibold">{{ $formatNumber($stats['total_rows']) }}</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
      @foreach ($cards as $card)
        <button
          type="button"
          @click="openDetail(@js($dashboardDetails[$card['detail']] ?? []))"
          class="group rounded-3xl border border-app bg-surface p-5 text-left shadow-soft transition-transform duration-200 hover:-translate-y-1 focus-visible:outline-none focus-visible:ring-2 ring-app"
        >
          <div class="flex items-start justify-between gap-4">
            <div
              @class([
                'grid h-12 w-12 shrink-0 place-items-center rounded-2xl',
                'bg-primary-soft text-primary' => $card['tone'] === 'primary',
                'bg-[rgba(248,113,113,0.15)] text-[color:var(--danger)]' => $card['tone'] === 'danger',
                'bg-[rgba(34,197,94,0.15)] text-[color:var(--accent)]' => $card['tone'] === 'accent',
                'bg-[rgba(56,189,248,0.15)] text-[color:var(--info)]' => $card['tone'] === 'info',
                'bg-[rgba(251,191,36,0.18)] text-[color:var(--warning)]' => $card['tone'] === 'warning',
              ])
            >
              <x-dynamic-component :component="$card['icon']" class="h-6 w-6 transition-transform duration-200 group-hover:scale-110" />
            </div>
            <x-lucide-mouse-pointer-click class="h-4 w-4 text-muted opacity-70" />
          </div>

          <div class="mt-5">
            <div class="text-xs font-semibold text-muted">{{ $card['label'] }}</div>
            <div class="mt-1 break-words text-2xl font-semibold leading-tight">{{ $card['value'] }}</div>
            <div class="mt-2 text-xs leading-5 text-muted">{{ $card['meta'] }}</div>
          </div>
        </button>
      @endforeach
    </section>

    <section class="grid gap-6 xl:grid-cols-12">
      <div class="rounded-3xl border border-app bg-surface p-6 shadow-soft xl:col-span-8">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
          <div>
            <div class="text-sm font-semibold">Grafik Statistik Deteksi</div>
            <div class="mt-1 text-xs text-muted">Tren enam bulan terakhir berdasarkan hasil deteksi selesai.</div>
          </div>
          <div class="flex flex-wrap gap-3 text-xs text-muted">
            <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-[color:var(--accent)]"></span>Normal</span>
            <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-[color:var(--danger)]"></span>Malware</span>
          </div>
        </div>

        <div class="mt-7 flex h-72 items-end gap-3 overflow-x-auto border-b border-app pb-4 md:gap-5">
          @forelse ($trend as $month)
            @php
              $height = max(8, round(((int) $month['total'] / $maxTrend) * 100));
              $normalHeight = (int) $month['total'] > 0 ? round(((int) $month['normal'] / (int) $month['total']) * 100) : 0;
              $malwareHeight = (int) $month['total'] > 0 ? round(((int) $month['malware'] / (int) $month['total']) * 100) : 0;
            @endphp
            <div class="flex min-w-16 flex-1 flex-col items-center gap-3">
              <div class="text-xs font-semibold text-muted">{{ $formatNumber($month['total']) }}</div>
              <div class="flex h-52 w-full max-w-20 items-end overflow-hidden rounded-t-2xl bg-surface2">
                <div class="flex w-full flex-col justify-end overflow-hidden rounded-t-2xl" style="height: {{ $height }}%">
                  <div class="w-full bg-[color:var(--danger)]" style="height: {{ $malwareHeight }}%"></div>
                  <div class="w-full bg-[color:var(--accent)]" style="height: {{ max(0, 100 - $malwareHeight) }}%"></div>
                </div>
              </div>
              <div class="text-xs font-medium text-muted">{{ $month['label'] }}</div>
            </div>
          @empty
            <div class="grid min-h-52 w-full place-items-center rounded-3xl border border-dashed border-app bg-surface2 px-6 text-center">
              <div>
                <x-lucide-chart-column class="mx-auto h-10 w-10 text-muted" />
                <div class="mt-3 text-sm font-semibold">Belum ada statistik</div>
                <div class="mt-1 text-xs text-muted">Grafik akan muncul setelah deteksi selesai tersimpan.</div>
              </div>
            </div>
          @endforelse
        </div>
      </div>

      <div class="rounded-3xl border border-app bg-surface p-6 shadow-soft xl:col-span-4">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="text-sm font-semibold">Distribusi Label</div>
            <div class="mt-1 text-xs text-muted">Label dominan di seluruh data.</div>
          </div>
          <x-lucide-tags class="h-5 w-5 text-primary" />
        </div>

        <div class="mt-6 space-y-4">
          @forelse ($labelStats as $item)
            @php
              $width = max(5, round(((int) $item['count'] / $maxLabel) * 100));
              $labelPayload = [
                'title' => 'Label '.$item['label'],
                'description' => 'Jumlah baris pada label ini.',
                'items' => [
                  [
                    'title' => $item['label'],
                    'meta' => 'Total baris',
                    'value' => $formatNumber($item['count']),
                    'url' => null,
                  ],
                ],
              ];
            @endphp
            <button
              type="button"
              @click="openDetail(@js($labelPayload))"
              class="w-full text-left focus-visible:outline-none focus-visible:ring-2 ring-app"
            >
              <div class="flex items-center justify-between gap-3 text-sm">
                <span class="truncate font-semibold" title="{{ $item['label'] }}">{{ $item['label'] }}</span>
                <span class="text-muted">{{ $formatNumber($item['count']) }}</span>
              </div>
              <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-surface2">
                <div
                  @class([
                    'h-full rounded-full',
                    'bg-[color:var(--accent)]' => $item['tone'] === 'accent',
                    'bg-[color:var(--danger)]' => $item['tone'] === 'danger',
                    'bg-[color:var(--warning)]' => $item['tone'] === 'warning',
                  ])
                  style="width: {{ $width }}%"
                ></div>
              </div>
            </button>
          @empty
            <div class="rounded-3xl border border-dashed border-app bg-surface2 px-5 py-8 text-center">
              <x-lucide-tags class="mx-auto h-10 w-10 text-muted" />
              <div class="mt-3 text-sm font-semibold">Belum ada label</div>
              <div class="mt-1 text-xs leading-5 text-muted">Distribusi label akan muncul setelah data diproses.</div>
            </div>
          @endforelse
        </div>
      </div>
    </section>

    <section class="grid gap-6 xl:grid-cols-12">
      <div class="rounded-3xl border border-app bg-surface p-6 shadow-soft xl:col-span-8">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div>
            <div class="text-sm font-semibold">Deteksi Terbaru</div>
            <div class="mt-1 text-xs text-muted">Klik baris untuk melihat ringkasan cepat.</div>
          </div>
          @can('detection.view')
            <a href="{{ route('hasildeteksi.index') }}" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-app px-4 py-2.5 text-sm font-semibold transition-colors hover:bg-surface2">
              <x-lucide-list-checks class="h-4 w-4" />
              Hasil Deteksi
            </a>
          @endcan
        </div>

        <div class="mt-5 space-y-3 lg:hidden">
          @forelse ($recentDetections as $item)
            @php
              $detailPayload = [
                'title' => 'Detail Deteksi',
                'description' => $item['file_name'],
                'items' => [
                  ['title' => 'Diproses', 'meta' => $item['processed_at'], 'value' => $item['status'], 'url' => $item['url']],
                  ['title' => 'Total Data', 'meta' => ($item['source_label'] ?? 'Manual').' - '.$item['user'], 'value' => $formatNumber($item['total']), 'url' => null],
                  ['title' => 'Malware', 'meta' => $item['malware_percent'].'%', 'value' => $formatNumber($item['malware']), 'url' => null],
                ],
              ];
            @endphp
            <button
              type="button"
              @click="openDetail(@js($detailPayload))"
              class="w-full rounded-2xl border border-app bg-surface2 px-4 py-3 text-left transition-colors hover:bg-surface focus-visible:outline-none focus-visible:ring-2 ring-app"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <div class="truncate text-sm font-semibold" title="{{ $item['file_name'] }}">{{ $item['file_name'] }}</div>
                  <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-muted">
                    <span class="rounded-full bg-surface px-2 py-0.5 font-semibold text-app">{{ $item['source_label'] ?? 'Manual' }}</span>
                    <span>{{ $item['processed_at'] }}</span>
                  </div>
                  <div class="mt-1 truncate text-xs text-muted">{{ $item['user'] }}</div>
                </div>
                <span
                  @class([
                    'shrink-0 rounded-full px-3 py-1 text-xs font-semibold',
                    'bg-[rgba(248,113,113,0.16)] text-[color:var(--danger)]' => (int) $item['malware'] > 0,
                    'bg-[rgba(34,197,94,0.16)] text-[color:var(--accent)]' => (int) $item['malware'] <= 0,
                  ])
                >
                  {{ (int) $item['malware'] > 0 ? 'Malware' : 'Aman' }}
                </span>
              </div>

              <div class="mt-3 grid grid-cols-1 gap-2 text-xs text-muted sm:grid-cols-3">
                <span>Total <span class="font-semibold text-app">{{ $formatNumber($item['total']) }}</span></span>
                <span>Normal <span class="font-semibold text-app">{{ $formatNumber($item['normal']) }}</span></span>
                <span>Malware <span class="font-semibold text-app">{{ $formatNumber($item['malware']) }}</span></span>
              </div>
            </button>
          @empty
            <div class="rounded-3xl border border-dashed border-app bg-surface2 px-5 py-8 text-center">
              <x-lucide-file-text class="mx-auto h-10 w-10 text-muted" />
              <div class="mt-3 text-sm font-semibold">Belum ada hasil deteksi</div>
              <div class="mt-1 text-xs text-muted">Upload data untuk mulai mengisi dashboard.</div>
            </div>
          @endforelse
        </div>

        <div class="mt-5 hidden overflow-x-auto rounded-3xl border border-app lg:block">
          <table class="w-full min-w-[680px] text-sm">
            <thead class="sticky top-0 z-10 bg-surface2 text-muted">
              <tr class="border-b border-app">
                <th class="px-4 py-3 text-left font-medium">File</th>
                <th class="px-4 py-3 text-left font-medium">Waktu</th>
                <th class="px-4 py-3 text-left font-medium">Normal</th>
                <th class="px-4 py-3 text-left font-medium">Malware</th>
                <th class="px-4 py-3 text-left font-medium">Confidence</th>
                <th class="px-4 py-3 text-right font-medium">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-app">
              @forelse ($recentDetections as $item)
                @php
                  $detailPayload = [
                    'title' => 'Detail Deteksi',
                    'description' => $item['file_name'],
                    'items' => [
                      ['title' => 'Diproses', 'meta' => $item['processed_at'], 'value' => $item['status'], 'url' => $item['url']],
                      ['title' => 'Total Data', 'meta' => ($item['source_label'] ?? 'Manual').' - '.$item['user'], 'value' => $formatNumber($item['total']), 'url' => null],
                      ['title' => 'Malware', 'meta' => $item['malware_percent'].'%', 'value' => $formatNumber($item['malware']), 'url' => null],
                    ],
                  ];
                @endphp
                <tr class="cursor-pointer transition-colors hover:bg-surface2/70" @click="openDetail(@js($detailPayload))">
                  <td class="px-4 py-3">
                    <div class="max-w-72 truncate font-semibold" title="{{ $item['file_name'] }}">{{ $item['file_name'] }}</div>
                    <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-muted">
                      <span class="rounded-full bg-surface px-2 py-0.5 font-semibold text-app">{{ $item['source_label'] ?? 'Manual' }}</span>
                      <span>{{ $item['user'] }}</span>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-muted">{{ $item['processed_at'] }}</td>
                  <td class="px-4 py-3">
                    <span class="rounded-full bg-[rgba(34,197,94,0.16)] px-3 py-1 text-xs font-semibold text-[color:var(--accent)]">
                      {{ $formatNumber($item['normal']) }}
                    </span>
                  </td>
                  <td class="px-4 py-3">
                    <span class="rounded-full bg-[rgba(248,113,113,0.16)] px-3 py-1 text-xs font-semibold text-[color:var(--danger)]">
                      {{ $formatNumber($item['malware']) }}
                    </span>
                  </td>
                  <td class="px-4 py-3 font-semibold">{{ $item['average_confidence'] }}%</td>
                  <td class="px-4 py-3 text-right">
                    <a
                      href="{{ $item['url'] }}"
                      @click.stop
                      class="inline-flex items-center gap-2 rounded-xl border border-app px-3 py-2 text-xs font-semibold transition-colors hover:bg-surface"
                    >
                      <x-lucide-eye class="h-4 w-4" />
                      Detail
                    </a>
                  </td>
                </tr>
              @empty
                <tr>
                  <td class="px-6 py-10 text-center text-muted" colspan="6">
                    <x-lucide-file-text class="mx-auto h-10 w-10" />
                    <div class="mt-3 text-sm font-semibold text-app">Belum ada hasil deteksi</div>
                    <div class="mt-1 text-xs">Upload data untuk mulai mengisi dashboard.</div>
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      <div class="rounded-3xl border border-app bg-surface p-6 shadow-soft xl:col-span-4">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="text-sm font-semibold">Aktivitas Terbaru</div>
            <div class="mt-1 text-xs text-muted">Upload dan proses yang terakhir berubah.</div>
          </div>
          <div class="flex items-center gap-2">
            <span class="rounded-full bg-surface2 px-3 py-1 text-xs font-semibold text-muted" x-text="latestActivities.length + ' data'"></span>
            <x-lucide-activity class="h-5 w-5 text-primary" />
          </div>
        </div>

        <div class="mt-5 space-y-2">
          <template x-if="latestActivities.length === 0">
            <div class="rounded-3xl border border-dashed border-app bg-surface2 px-5 py-8 text-center">
              <x-lucide-activity class="mx-auto h-10 w-10 text-muted" />
              <div class="mt-3 text-sm font-semibold">Belum ada aktivitas</div>
              <div class="mt-1 text-xs leading-5 text-muted">Aktivitas akan muncul setelah ada upload atau deteksi.</div>
            </div>
          </template>

          <template x-for="activity in pagedActivities" :key="activity.title + activity.time">
            <a :href="activity.url" class="group block rounded-2xl border border-app bg-surface2 px-4 py-3 transition-colors hover:bg-surface">
              <div class="flex items-start gap-3">
                <span
                  class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full"
                  :class="{
                    'bg-[color:var(--danger)]': activity.tone === 'danger',
                    'bg-[color:var(--accent)]': activity.tone === 'accent',
                    'bg-[color:var(--warning)]': activity.tone === 'warning',
                  }"
                ></span>
                <div class="min-w-0 flex-1">
                  <div class="truncate text-sm font-semibold" :title="activity.title" x-text="activity.title"></div>
                  <div class="mt-1 line-clamp-2 text-xs leading-5 text-muted" x-text="activity.meta"></div>
                  <div class="mt-1 text-xs font-semibold text-muted" x-text="activity.time"></div>
                </div>
                <x-lucide-chevron-right class="h-4 w-4 shrink-0 text-muted transition-transform group-hover:translate-x-0.5" />
              </div>
            </a>
          </template>
        </div>

        <div
          x-show="latestActivities.length > activityPerPage"
          class="mt-5 flex items-center justify-between gap-3 border-t border-app pt-4 text-xs text-muted"
        >
          <span>
            Halaman <span class="font-semibold text-app" x-text="activityPage"></span>/<span x-text="activityTotalPages"></span>
          </span>
          <div class="flex items-center gap-2">
            <button
              type="button"
              @click="activityPage = Math.max(1, activityPage - 1)"
              :disabled="activityPage === 1"
              class="inline-grid h-9 w-9 place-items-center rounded-xl border border-app transition-colors hover:bg-surface2 disabled:cursor-not-allowed disabled:opacity-40"
              aria-label="Aktivitas sebelumnya"
            >
              <x-lucide-chevron-left class="h-4 w-4" />
            </button>
            <button
              type="button"
              @click="activityPage = Math.min(activityTotalPages, activityPage + 1)"
              :disabled="activityPage === activityTotalPages"
              class="inline-grid h-9 w-9 place-items-center rounded-xl border border-app transition-colors hover:bg-surface2 disabled:cursor-not-allowed disabled:opacity-40"
              aria-label="Aktivitas berikutnya"
            >
              <x-lucide-chevron-right class="h-4 w-4" />
            </button>
          </div>
        </div>
      </div>
    </section>

    <div
      x-show="detailOpen"
      x-cloak
      class="fixed inset-0 z-50 grid place-items-center bg-black/45 p-4 backdrop-blur-sm"
      x-transition.opacity
    >
      <div
        @click.outside="detailOpen = false"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-3 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-2 scale-95"
        class="w-full max-w-lg overflow-hidden rounded-3xl border border-app bg-surface shadow-soft"
        role="dialog"
        aria-modal="true"
      >
        <div class="flex items-start justify-between gap-4 border-b border-app p-6">
          <div>
            <div class="text-lg font-semibold" x-text="activeDetail.title"></div>
            <div class="mt-1 text-sm leading-6 text-muted" x-text="activeDetail.description"></div>
          </div>
          <button type="button" @click="detailOpen = false" class="rounded-xl p-2 text-muted transition-colors hover:bg-surface2">
            <x-lucide-x class="h-5 w-5" />
          </button>
        </div>

        <div class="max-h-[60vh] overflow-auto p-6">
          <template x-if="activeDetail.items.length === 0">
            <div class="rounded-2xl border border-dashed border-app bg-surface2 px-5 py-8 text-center text-sm text-muted">
              Tidak ada detail tambahan untuk ditampilkan.
            </div>
          </template>

          <div class="space-y-3">
            <template x-for="(item, index) in activeDetail.items" :key="index">
              <div class="rounded-2xl border border-app bg-surface2 p-4">
                <div class="flex items-start justify-between gap-4">
                  <div class="min-w-0">
                    <div class="truncate text-sm font-semibold" x-text="item.title"></div>
                    <div class="mt-1 text-xs leading-5 text-muted" x-text="item.meta"></div>
                  </div>
                  <div class="shrink-0 rounded-xl bg-surface px-3 py-1 text-xs font-semibold" x-text="item.value"></div>
                </div>
                <a
                  x-show="item.url"
                  :href="item.url"
                  class="mt-3 inline-flex items-center gap-2 rounded-xl border border-app px-3 py-2 text-xs font-semibold transition-colors hover:bg-surface"
                >
                  <x-lucide-eye class="h-4 w-4" />
                  Buka detail
                </a>
              </div>
            </template>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
