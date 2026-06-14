@extends('layouts.dashboard')
@section('title', 'Hasil Deteksi')

@section('page')
    @php
        $prediction = $prediction ?? null;
        $summary = array_merge([
            'normal' => 0,
            'malware' => 0,
            'unknown' => 0,
            'normal_percent' => 0,
            'malware_percent' => 0,
            'average_confidence' => 0,
            'labels' => [],
            'malware_labels' => [],
        ], $prediction['summary'] ?? []);
        $historySummary = array_merge([
            'total_detections' => 0,
            'total_rows' => 0,
            'normal_rows' => 0,
            'malware_rows' => 0,
            'unknown_rows' => 0,
            'average_confidence' => 0,
            'malware_percent' => 0,
        ], $historySummary ?? []);
        $resultsPaginator = $resultsPaginator ?? null;
        $results = $resultsPaginator ? collect($resultsPaginator->items()) : collect($prediction['results'] ?? []);
        $recentDetections = collect($recentDetections ?? []);
        $tableFilters = $tableFilters ?? ['search' => '', 'label' => 'all', 'per_page' => 10];
        $perPageOptions = $perPageOptions ?? [10, 25, 50, 100];
        $labelOptions = collect($labelOptions ?? [])->whenEmpty(fn ($items) => collect($summary['labels'] ?? [])->keys())->whenEmpty(fn ($items) => collect(['Normal']));
        $formatNumber = fn ($value) => number_format((float) $value, 0, ',', '.');
        $formatPercent = fn ($value) => number_format((float) $value, 2, ',', '.');
        $toneForLabel = fn ($label) => $label === 'Normal' ? 'accent' : ($label === 'Unknown' ? 'warning' : 'danger');
        $latestFile = $prediction['file_name'] ?? 'Belum ada file diproses';
        $latestProcessedAt = $prediction['processed_at'] ?? '-';
        $latestTotal = (int) ($prediction['total'] ?? 0);
        $latestDownloadUrl = $prediction && ! empty($prediction['scan_upload_id'])
            ? route('riwayat.download', ['scanUpload' => $prediction['scan_upload_id'], 'mode' => 'after'])
            : null;
        $labelCounts = collect($summary['labels'] ?? [])
            ->map(fn ($count) => (int) $count)
            ->filter(fn ($count) => $count > 0)
            ->whenEmpty(fn () => collect([
                'Normal' => (int) ($summary['normal'] ?? 0),
                'Malware' => (int) ($summary['malware'] ?? 0),
                'Unknown' => (int) ($summary['unknown'] ?? 0),
            ])->filter(fn ($count) => $count > 0));
        $maxLabelCount = max((int) $labelCounts->max(), 1);
        $latestCards = [
            ['label' => 'Total Data Terakhir', 'value' => $formatNumber($latestTotal), 'meta' => $latestFile, 'icon' => 'lucide-database', 'tone' => 'primary'],
            ['label' => 'Normal Terakhir', 'value' => $formatNumber($summary['normal']), 'meta' => $formatPercent($summary['normal_percent']).'% dari hasil terakhir', 'icon' => 'lucide-shield-check', 'tone' => 'accent'],
            ['label' => 'Malware Terakhir', 'value' => $formatNumber($summary['malware']), 'meta' => $formatPercent($summary['malware_percent']).'% perlu review', 'icon' => 'lucide-bug', 'tone' => 'danger'],
            ['label' => 'Confidence Terakhir', 'value' => $formatPercent($summary['average_confidence']).'%', 'meta' => 'Rata-rata keyakinan model', 'icon' => 'lucide-gauge', 'tone' => 'info'],
        ];
    @endphp

    <div
        class="space-y-6"
        x-data="{
            rowDetailOpen: false,
            rowDetail: { probabilities: [] },
            detectionModalOpen: false,
            detectionDetail: {},
            openRowDetail(payload) {
                this.rowDetail = payload;
                this.rowDetailOpen = true;
            },
            openDetectionDetail(payload) {
                this.detectionDetail = payload;
                this.detectionModalOpen = true;
            },
            printReport() {
                window.print();
            }
        }"
    >
        <section class="overflow-hidden rounded-3xl border border-app bg-surface shadow-soft">
            <div class="relative p-6 md:p-7">
                <div class="absolute inset-x-0 top-0 h-1 bg-[linear-gradient(90deg,var(--primary),var(--accent),var(--danger))]"></div>
                <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                    <div class="min-w-0">
                        <div class="inline-flex items-center gap-2 rounded-full bg-primary-soft px-3 py-1 text-xs font-semibold text-primary">
                            <x-lucide-list-checks class="h-3.5 w-3.5" />
                            Hasil terakhir dan riwayat terbaru
                        </div>
                        <h1 class="mt-4 break-words text-2xl font-semibold leading-tight md:text-3xl">{{ $latestFile }}</h1>
                        <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-muted">
                            <span>Diproses {{ $latestProcessedAt }}</span>
                            <span class="hidden sm:inline">|</span>
                            <span>{{ $formatNumber($historySummary['total_detections']) }} deteksi selesai tersimpan</span>
                            <span class="hidden sm:inline">|</span>
                            <span>{{ $formatNumber($historySummary['total_rows']) }} baris historis</span>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 print:hidden">
                        @if ($latestDownloadUrl && auth()->user()?->can('reports.download'))
                            <a
                                href="{{ $latestDownloadUrl }}"
                                class="inline-flex items-center gap-2 rounded-2xl border border-app px-4 py-2.5 text-sm font-semibold transition-colors hover:bg-surface2"
                            >
                                <x-lucide-download class="h-4 w-4" />
                                Unduh CSV
                            </a>
                        @elseif (auth()->user()?->can('reports.download'))
                            <button type="button" disabled class="inline-flex cursor-not-allowed items-center gap-2 rounded-2xl border border-app px-4 py-2.5 text-sm font-semibold opacity-60">
                                <x-lucide-download class="h-4 w-4" />
                                Unduh CSV
                            </button>
                        @endif

                        <button
                            type="button"
                            @click="printReport()"
                            class="inline-flex items-center gap-2 rounded-2xl bg-primary px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-hover"
                        >
                            <x-lucide-printer class="h-4 w-4" />
                            Cetak Laporan
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($latestCards as $card)
                <div class="group rounded-3xl border border-app bg-surface p-5 shadow-soft transition-transform duration-200 hover:-translate-y-1">
                    <div
                        @class([
                            'grid h-12 w-12 place-items-center rounded-2xl',
                            'bg-primary-soft text-primary' => $card['tone'] === 'primary',
                            'bg-[rgba(34,197,94,0.15)] text-[color:var(--accent)]' => $card['tone'] === 'accent',
                            'bg-[rgba(248,113,113,0.15)] text-[color:var(--danger)]' => $card['tone'] === 'danger',
                            'bg-[rgba(56,189,248,0.15)] text-[color:var(--info)]' => $card['tone'] === 'info',
                        ])
                    >
                        <x-dynamic-component :component="$card['icon']" class="h-6 w-6 transition-transform duration-200 group-hover:scale-110" />
                    </div>
                    <div class="mt-5">
                        <div class="text-xs font-semibold text-muted">{{ $card['label'] }}</div>
                        <div class="mt-1 break-words text-2xl font-semibold leading-tight">{{ $card['value'] }}</div>
                        <div class="mt-2 truncate text-xs leading-5 text-muted" title="{{ $card['meta'] }}">{{ $card['meta'] }}</div>
                    </div>
                </div>
            @endforeach
        </section>

        <section class="grid gap-6 xl:grid-cols-12">
            <div class="rounded-3xl border border-app bg-surface p-6 shadow-soft xl:col-span-7">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold">Distribusi Hasil Terakhir</div>
                        <div class="mt-1 text-xs text-muted">Komposisi label pada deteksi terakhir.</div>
                    </div>
                    <div class="rounded-2xl bg-surface2 px-3 py-2 text-xs font-semibold text-muted">
                        {{ $formatNumber($labelCounts->sum()) }} baris
                    </div>
                </div>

                @if ($labelCounts->isNotEmpty())
                    <div class="mt-7 flex h-64 items-end gap-3 overflow-x-auto border-b border-app pb-4 md:gap-5">
                        @foreach ($labelCounts->sortDesc()->take(8) as $label => $count)
                            @php
                                $tone = $toneForLabel($label);
                                $height = max(10, round(((int) $count / $maxLabelCount) * 100));
                            @endphp
                            <div class="flex min-w-20 flex-1 flex-col items-center gap-3">
                                <div class="text-xs font-semibold text-muted">{{ $formatNumber($count) }}</div>
                                <div class="flex h-44 w-full max-w-24 items-end overflow-hidden rounded-t-2xl bg-surface2">
                                    <div
                                        @class([
                                            'w-full rounded-t-2xl transition-all duration-300',
                                            'bg-[color:var(--accent)]' => $tone === 'accent',
                                            'bg-[color:var(--danger)]' => $tone === 'danger',
                                            'bg-[color:var(--warning)]' => $tone === 'warning',
                                        ])
                                        style="height: {{ $height }}%"
                                    ></div>
                                </div>
                                <div class="max-w-24 truncate text-xs font-medium text-muted" title="{{ $label }}">{{ $label }}</div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mt-6 grid min-h-52 place-items-center rounded-3xl border border-dashed border-app bg-surface2 px-6 text-center">
                        <div>
                            <x-lucide-chart-column class="mx-auto h-10 w-10 text-muted" />
                            <div class="mt-3 text-sm font-semibold">Belum ada distribusi label</div>
                            <div class="mt-1 text-xs text-muted">Data akan muncul setelah deteksi selesai.</div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="rounded-3xl border border-app bg-surface p-6 shadow-soft xl:col-span-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold">Riwayat Terbaru</div>
                        <div class="mt-1 text-xs text-muted">Klik item untuk ringkasan cepat.</div>
                    </div>
                    <x-lucide-history class="h-5 w-5 text-primary" />
                </div>

                <div class="mt-5 space-y-3">
                    @forelse ($recentDetections as $item)
                        <button
                            type="button"
                            @click="openDetectionDetail(@js($item))"
                            class="group w-full rounded-2xl border border-app bg-surface2 px-4 py-3 text-left transition-colors hover:bg-surface focus-visible:outline-none focus-visible:ring-2 ring-app"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold" title="{{ $item['file_name'] }}">{{ $item['file_name'] }}</div>
                                    <div class="mt-1 text-xs leading-5 text-muted">{{ $item['processed_at'] }} - {{ $item['user'] }}</div>
                                </div>
                                <span
                                    @class([
                                        'shrink-0 rounded-full px-3 py-1 text-xs font-semibold',
                                        'bg-[rgba(248,113,113,0.16)] text-[color:var(--danger)]' => $item['malware'] > 0,
                                        'bg-[rgba(34,197,94,0.16)] text-[color:var(--accent)]' => $item['malware'] <= 0,
                                    ])
                                >
                                    {{ $item['malware'] > 0 ? 'Malware' : 'Aman' }}
                                </span>
                            </div>
                            <div class="mt-3 grid grid-cols-3 gap-2 text-xs text-muted">
                                <span>Total <span class="font-semibold text-app">{{ $formatNumber($item['total']) }}</span></span>
                                <span>Normal <span class="font-semibold text-app">{{ $formatNumber($item['normal']) }}</span></span>
                                <span>Malware <span class="font-semibold text-app">{{ $formatNumber($item['malware']) }}</span></span>
                            </div>
                        </button>
                    @empty
                        <div class="rounded-3xl border border-dashed border-app bg-surface2 px-5 py-8 text-center">
                            <x-lucide-history class="mx-auto h-10 w-10 text-muted" />
                            <div class="mt-3 text-sm font-semibold">Belum ada riwayat deteksi</div>
                            <div class="mt-1 text-xs leading-5 text-muted">Riwayat terbaru muncul setelah file diproses.</div>
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-app bg-surface p-6 shadow-soft">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <div class="text-sm font-semibold">Tabel Detail Hasil Terakhir</div>
                    <div class="mt-1 text-xs text-muted">Klik baris untuk membuka modal detail probabilitas.</div>
                </div>

                <form method="GET" action="{{ route('hasildeteksi.index') }}" class="grid flex-1 gap-3 md:grid-cols-[1fr_180px_140px_auto] xl:max-w-4xl">
                    <div class="relative">
                        <x-lucide-search class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" />
                        <input
                            type="search"
                            name="search"
                            value="{{ $tableFilters['search'] }}"
                            placeholder="Cari row, label, atau confidence..."
                            class="w-full rounded-2xl border border-app bg-surface2 px-12 py-3 text-sm focus:border-[color:var(--primary)] focus:ring-2 focus:ring-[color:var(--primary-soft)]"
                        >
                    </div>

                    <select
                        name="label"
                        onchange="this.form.submit()"
                        class="rounded-2xl border border-app bg-surface2 px-4 py-3 text-sm focus:border-[color:var(--primary)] focus:ring-2 focus:ring-[color:var(--primary-soft)]"
                    >
                        <option value="all">Semua Label</option>
                        @foreach ($labelOptions as $option)
                            <option value="{{ $option }}" @selected($tableFilters['label'] === $option)>{{ $option }}</option>
                        @endforeach
                    </select>

                    <select
                        name="per_page"
                        onchange="this.form.submit()"
                        class="rounded-2xl border border-app bg-surface2 px-4 py-3 text-sm focus:border-[color:var(--primary)] focus:ring-2 focus:ring-[color:var(--primary-soft)]"
                    >
                        @foreach ($perPageOptions as $option)
                            <option value="{{ $option }}" @selected((int) $tableFilters['per_page'] === (int) $option)>{{ $option }} / halaman</option>
                        @endforeach
                    </select>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-2xl bg-primary px-5 py-3 text-sm font-semibold text-white transition-opacity hover:opacity-90"
                    >
                        <x-lucide-search class="h-4 w-4" />
                        Cari
                    </button>
                </form>
            </div>

            @if (($tableFilters['search'] ?? '') !== '' || ($tableFilters['label'] ?? 'all') !== 'all')
                <div class="mt-4">
                    <a
                        href="{{ route('hasildeteksi.index', ['per_page' => $tableFilters['per_page']]) }}"
                        class="inline-flex items-center gap-2 rounded-2xl border border-app px-4 py-2 text-sm font-semibold transition-colors hover:bg-surface2"
                    >
                        <x-lucide-rotate-ccw class="h-4 w-4" />
                        Reset Filter
                    </a>
                </div>
            @endif

            <div class="mt-5 overflow-auto rounded-3xl border border-app">
                <table class="w-full min-w-[860px] text-sm">
                    <thead class="sticky top-0 z-10 bg-surface2 text-muted">
                        <tr class="border-b border-app">
                            <th class="px-4 py-3 text-left font-medium">Row ID</th>
                            <th class="px-4 py-3 text-left font-medium">Label</th>
                            <th class="px-4 py-3 text-left font-medium">Confidence</th>
                            <th class="px-4 py-3 text-left font-medium">Prob. Normal</th>
                            <th class="px-4 py-3 text-left font-medium">Prob. Label</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-app">
                        @forelse ($results as $result)
                            @php
                                $label = $result['label'] ?? 'Unknown';
                                $tone = $toneForLabel($label);
                                $probabilities = collect($result['probabilities'] ?? []);
                                $confidencePercent = max(0, min(100, ((float) ($result['confidence'] ?? 0)) * 100));
                                $normalProbability = max(0, min(100, ((float) ($probabilities['Normal'] ?? 0)) * 100));
                                $labelProbability = max(0, min(100, ((float) ($probabilities[$label] ?? $result['confidence'] ?? 0)) * 100));
                                $rowPayload = [
                                    'row' => (string) ($result['row'] ?? $loop->index),
                                    'label' => $label,
                                    'status' => $tone === 'accent' ? 'Aman' : ($tone === 'warning' ? 'Unknown' : 'Perlu Review'),
                                    'confidence' => $formatPercent($confidencePercent).'%',
                                    'probNormal' => $formatPercent($normalProbability).'%',
                                    'probLabel' => $formatPercent($labelProbability).'%',
                                    'probabilities' => $probabilities
                                        ->map(fn ($value, $name) => [
                                            'label' => $name,
                                            'value' => $formatPercent(((float) $value) * 100).'%',
                                        ])
                                        ->values()
                                        ->all(),
                                ];
                            @endphp
                            <tr class="cursor-pointer transition-colors hover:bg-surface2/70" @click="openRowDetail(@js($rowPayload))">
                                <td class="px-4 py-3 font-semibold">{{ $rowPayload['row'] }}</td>
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
                                <td class="px-4 py-3">
                                    <span
                                        @class([
                                            'rounded-full px-3 py-1 text-xs font-semibold',
                                            'bg-[rgba(248,113,113,0.16)] text-[color:var(--danger)]' => $tone === 'danger',
                                            'bg-[rgba(34,197,94,0.16)] text-[color:var(--accent)]' => $tone === 'accent',
                                            'bg-[rgba(251,191,36,0.18)] text-[color:var(--warning)]' => $tone === 'warning',
                                        ])
                                    >
                                        {{ $rowPayload['status'] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-6 py-10 text-center text-muted" colspan="6">
                                    <x-lucide-file-text class="mx-auto h-10 w-10" />
                                    <div class="mt-3 text-sm font-semibold text-app">Belum ada hasil deteksi</div>
                                    <div class="mt-1 text-xs">Upload data untuk mulai menjalankan deteksi malware.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($resultsPaginator)
                <div class="mt-4 flex flex-col gap-3 text-sm text-muted md:flex-row md:items-center md:justify-between">
                    <div>
                        @if ($resultsPaginator->total() > 0)
                            Menampilkan {{ $formatNumber($resultsPaginator->firstItem()) }}-{{ $formatNumber($resultsPaginator->lastItem()) }}
                            dari {{ $formatNumber($resultsPaginator->total()) }} baris
                        @else
                            Tidak ada baris yang cocok dengan filter.
                        @endif
                    </div>

                    @if ($resultsPaginator->hasPages())
                        @php
                            $currentPage = $resultsPaginator->currentPage();
                            $lastPage = $resultsPaginator->lastPage();
                            $startPage = max(1, $currentPage - 2);
                            $endPage = min($lastPage, $currentPage + 2);
                        @endphp
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($resultsPaginator->onFirstPage())
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-app opacity-40">
                                    <x-lucide-chevron-left class="h-4 w-4" />
                                </span>
                            @else
                                <a href="{{ $resultsPaginator->previousPageUrl() }}" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-app transition-colors hover:bg-surface2">
                                    <x-lucide-chevron-left class="h-4 w-4" />
                                </a>
                            @endif

                            @for ($page = $startPage; $page <= $endPage; $page++)
                                <a
                                    href="{{ $resultsPaginator->url($page) }}"
                                    @class([
                                        'inline-flex h-10 min-w-[2.5rem] items-center justify-center rounded-xl border px-3 font-semibold transition-colors',
                                        'border-transparent bg-primary text-white' => $page === $currentPage,
                                        'border-app hover:bg-surface2' => $page !== $currentPage,
                                    ])
                                >
                                    {{ $page }}
                                </a>
                            @endfor

                            @if ($resultsPaginator->hasMorePages())
                                <a href="{{ $resultsPaginator->nextPageUrl() }}" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-app transition-colors hover:bg-surface2">
                                    <x-lucide-chevron-right class="h-4 w-4" />
                                </a>
                            @else
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-app opacity-40">
                                    <x-lucide-chevron-right class="h-4 w-4" />
                                </span>
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        </section>

        <div
            x-show="rowDetailOpen"
            x-cloak
            class="fixed inset-0 z-50 grid place-items-center bg-black/45 p-4 backdrop-blur-sm"
            x-transition.opacity
        >
            <div
                @click.outside="rowDetailOpen = false"
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
                        <div class="text-lg font-semibold">Detail Baris <span x-text="rowDetail.row"></span></div>
                        <div class="mt-1 text-sm text-muted">Label <span class="font-semibold text-app" x-text="rowDetail.label"></span></div>
                    </div>
                    <button type="button" @click="rowDetailOpen = false" class="rounded-xl p-2 text-muted transition-colors hover:bg-surface2">
                        <x-lucide-x class="h-5 w-5" />
                    </button>
                </div>

                <div class="space-y-4 p-6">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-app bg-surface2 p-4">
                            <div class="text-xs text-muted">Confidence</div>
                            <div class="mt-1 text-xl font-semibold" x-text="rowDetail.confidence"></div>
                        </div>
                        <div class="rounded-2xl border border-app bg-surface2 p-4">
                            <div class="text-xs text-muted">Prob. Normal</div>
                            <div class="mt-1 text-xl font-semibold" x-text="rowDetail.probNormal"></div>
                        </div>
                        <div class="rounded-2xl border border-app bg-surface2 p-4">
                            <div class="text-xs text-muted">Prob. Label</div>
                            <div class="mt-1 text-xl font-semibold" x-text="rowDetail.probLabel"></div>
                        </div>
                    </div>

                    <div>
                        <div class="text-sm font-semibold">Probabilitas Semua Label</div>
                        <div class="mt-3 max-h-64 overflow-auto rounded-2xl border border-app">
                            <template x-for="item in rowDetail.probabilities" :key="item.label">
                                <div class="flex items-center justify-between gap-4 border-b border-app px-4 py-3 last:border-b-0">
                                    <span class="truncate text-sm font-semibold" x-text="item.label"></span>
                                    <span class="text-sm text-muted" x-text="item.value"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            x-show="detectionModalOpen"
            x-cloak
            class="fixed inset-0 z-50 grid place-items-center bg-black/45 p-4 backdrop-blur-sm"
            x-transition.opacity
        >
            <div
                @click.outside="detectionModalOpen = false"
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
                    <div class="min-w-0">
                        <div class="truncate text-lg font-semibold" x-text="detectionDetail.file_name"></div>
                        <div class="mt-1 text-sm text-muted" x-text="detectionDetail.processed_at"></div>
                    </div>
                    <button type="button" @click="detectionModalOpen = false" class="rounded-xl p-2 text-muted transition-colors hover:bg-surface2">
                        <x-lucide-x class="h-5 w-5" />
                    </button>
                </div>

                <div class="space-y-4 p-6">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-app bg-surface2 p-4">
                            <div class="text-xs text-muted">Total</div>
                            <div class="mt-1 text-xl font-semibold" x-text="detectionDetail.total"></div>
                        </div>
                        <div class="rounded-2xl border border-app bg-surface2 p-4">
                            <div class="text-xs text-muted">Normal</div>
                            <div class="mt-1 text-xl font-semibold" x-text="detectionDetail.normal"></div>
                        </div>
                        <div class="rounded-2xl border border-app bg-surface2 p-4">
                            <div class="text-xs text-muted">Malware</div>
                            <div class="mt-1 text-xl font-semibold" x-text="detectionDetail.malware"></div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-app bg-surface2 p-4 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-muted">Petugas</span>
                            <span class="font-semibold" x-text="detectionDetail.user"></span>
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-4">
                            <span class="text-muted">Confidence</span>
                            <span class="font-semibold"><span x-text="detectionDetail.average_confidence"></span>%</span>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        @can('reports.download')
                            <a
                                x-show="detectionDetail.download_url"
                                :href="detectionDetail.download_url"
                                class="inline-flex items-center gap-2 rounded-xl border border-app px-4 py-2 text-sm font-semibold transition-colors hover:bg-surface2"
                            >
                                <x-lucide-download class="h-4 w-4" />
                                Unduh
                            </a>
                        @endcan
                        <a
                            :href="detectionDetail.url"
                            class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-primary-hover"
                        >
                            <x-lucide-eye class="h-4 w-4" />
                            Buka Detail
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
