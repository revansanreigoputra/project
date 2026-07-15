@extends('layouts.dashboard')
@section('title', 'Hasil Deteksi')

@section('page')
    @php
        // Normalisasi data hasil deteksi supaya view tetap aman saat belum ada prediksi.
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
        $tableFilters = array_merge([
            'search' => '',
            'label' => 'all',
            'sort' => 'row',
            'direction' => 'asc',
            'per_page' => 10,
        ], $tableFilters ?? []);
        $perPageOptions = $perPageOptions ?? [10, 25, 50, 100];
        $normalLabel = $summary['normal_label'] ?? 'Normal';
        $guardrailAppliedCount = (int) ($summary['guardrail_applied_count'] ?? 0);
        $labelOptions = collect($labelOptions ?? [])->whenEmpty(fn ($items) => collect($summary['labels'] ?? [])->keys())->whenEmpty(fn ($items) => collect([$normalLabel]));
        $formatNumber = fn ($value) => number_format((float) $value, 0, ',', '.');
        $formatPercent = fn ($value) => number_format((float) $value, 2, ',', '.');
        $toneForLabel = fn ($label) => $label === $normalLabel ? 'accent' : ($label === 'Unknown' ? 'warning' : 'danger');
        $sortOptions = [
            'row' => 'Row ID',
            'label' => 'Label',
            'confidence' => 'Confidence',
        ];
        $statusForTone = fn ($tone) => $tone === 'accent' ? 'Aman' : ($tone === 'warning' ? 'Unknown' : 'Perlu Review');
        $recommendationForTone = fn ($tone) => $tone === 'accent' ? 'Arsipkan' : ($tone === 'warning' ? 'Validasi manual' : 'Prioritaskan');
        // Ambil probabilitas terbesar untuk ringkasan modal detail baris.
        $topProbability = function ($probabilities) {
            $items = collect($probabilities ?? []);

            if ($items->isEmpty()) {
                return ['label' => '-', 'value' => 0];
            }

            $label = $items->sortDesc()->keys()->first();

            return ['label' => $label, 'value' => (float) $items[$label]];
        };
        $hasActiveTableFilter = ($tableFilters['search'] ?? '') !== ''
            || ($tableFilters['label'] ?? 'all') !== 'all'
            || ($tableFilters['sort'] ?? 'row') !== 'row'
            || ($tableFilters['direction'] ?? 'asc') !== 'asc';
        $latestFile = $prediction['file_name'] ?? 'Belum ada file diproses';
        $latestSourceLabel = $prediction['source_label'] ?? 'Manual';
        $latestProcessedAt = $prediction['processed_at'] ?? '-';
        $latestTotal = (int) ($prediction['total'] ?? 0);
        $latestDownloadUrl = $prediction && ! empty($prediction['scan_upload_id'])
            ? route('riwayat.download', ['scanUpload' => $prediction['scan_upload_id'], 'mode' => 'after'])
            : null;
        // Susun distribusi label untuk grafik hasil terakhir.
        $labelCounts = collect($summary['labels'] ?? [])
            ->map(fn ($count) => (int) $count)
            ->filter(fn ($count) => $count > 0)
            ->whenEmpty(fn () => collect([
                $normalLabel => (int) ($summary['normal'] ?? 0),
                'Malware' => (int) ($summary['malware'] ?? 0),
                'Unknown' => (int) ($summary['unknown'] ?? 0),
            ])->filter(fn ($count) => $count > 0));
        $maxLabelCount = max((int) $labelCounts->max(), 1);
        $latestCards = [
            ['label' => 'Total Data Terakhir', 'value' => $formatNumber($latestTotal), 'meta' => $latestFile, 'icon' => 'lucide-database', 'tone' => 'primary'],
            ['label' => $normalLabel.' Terakhir', 'value' => $formatNumber($summary['normal']), 'meta' => $formatPercent($summary['normal_percent']).'% dari hasil terakhir', 'icon' => 'lucide-shield-check', 'tone' => 'accent'],
            ['label' => 'Malware Terakhir', 'value' => $formatNumber($summary['malware']), 'meta' => $formatPercent($summary['malware_percent']).'% perlu review', 'icon' => 'lucide-bug', 'tone' => 'danger'],
            ['label' => 'Confidence Terakhir', 'value' => $formatPercent($summary['average_confidence']).'%', 'meta' => 'Rata-rata keyakinan model', 'icon' => 'lucide-gauge', 'tone' => 'info'],
        ];
    @endphp

    <div
        class="space-y-4 md:space-y-6"
        {{-- State Alpine untuk modal detail dan pagination riwayat terbaru. --}}
        x-data="{
            rowDetailOpen: false,
            rowDetail: { probabilities: [] },
            detectionModalOpen: false,
            detectionDetail: {},
            recentDetections: @js($recentDetections->values()),
            recentPage: 1,
            recentPerPage: 3,
            recentSource: 'all',
            get filteredRecentDetections() {
                if (this.recentSource === 'all') {
                    return this.recentDetections;
                }

                return this.recentDetections.filter((item) => (item.source || 'manual') === this.recentSource);
            },
            get recentTotalPages() {
                return Math.max(1, Math.ceil(this.filteredRecentDetections.length / this.recentPerPage));
            },
            get pagedRecentDetections() {
                const start = (this.recentPage - 1) * this.recentPerPage;
                return this.filteredRecentDetections.slice(start, start + this.recentPerPage);
            },
            setRecentSource(source) {
                this.recentSource = source;
                this.recentPage = 1;
            },
            openRowDetail(payload) {
                this.rowDetail = payload;
                this.rowDetailOpen = true;
            },
            openDetectionDetail(payload) {
                this.detectionDetail = payload;
                this.detectionModalOpen = true;
            },
        }"
    >
        <section class="overflow-hidden rounded-2xl border border-app bg-surface shadow-soft md:rounded-3xl">
            <div class="relative p-4 md:p-7">
                <div class="absolute inset-x-0 top-0 h-1 bg-[linear-gradient(90deg,var(--primary),var(--accent),var(--danger))]"></div>
                <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                    <div class="min-w-0">
                        <div class="inline-flex items-center gap-2 rounded-full bg-primary-soft px-3 py-1 text-xs font-semibold text-primary">
                            <x-lucide-list-checks class="h-3.5 w-3.5" />
                            Hasil terakhir dan riwayat terbaru
                        </div>
                        <h1 class="mt-3 break-words text-xl font-semibold leading-tight md:mt-4 md:text-3xl">{{ $latestFile }}</h1>
                        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-muted md:text-sm">
                            <span class="rounded-full bg-surface2 px-3 py-1 text-xs font-semibold text-app">{{ $latestSourceLabel }}</span>
                            <span>Diproses {{ $latestProcessedAt }}</span>
                            <span class="hidden sm:inline">|</span>
                            <span>{{ $formatNumber($historySummary['total_detections']) }} deteksi selesai tersimpan</span>
                            <span class="hidden sm:inline">|</span>
                            <span>{{ $formatNumber($historySummary['total_rows']) }} baris historis</span>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @if ($latestDownloadUrl && auth()->user()?->can('reports.download'))
                            <a
                                href="{{ $latestDownloadUrl }}"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-2xl border border-app px-4 py-2.5 text-sm font-semibold transition-colors hover:bg-surface2 sm:w-auto"
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
                    </div>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-3 md:grid-cols-2 md:gap-4 xl:grid-cols-4">
            @foreach ($latestCards as $card)
                <div class="group rounded-2xl border border-app bg-surface p-4 shadow-soft transition-transform duration-200 hover:-translate-y-1 md:rounded-3xl md:p-5">
                    <div
                        @class([
                            'grid h-10 w-10 place-items-center rounded-2xl md:h-12 md:w-12',
                            'bg-primary-soft text-primary' => $card['tone'] === 'primary',
                            'bg-[rgba(34,197,94,0.15)] text-[color:var(--accent)]' => $card['tone'] === 'accent',
                            'bg-[rgba(248,113,113,0.15)] text-[color:var(--danger)]' => $card['tone'] === 'danger',
                            'bg-[rgba(56,189,248,0.15)] text-[color:var(--info)]' => $card['tone'] === 'info',
                        ])
                    >
                        <x-dynamic-component :component="$card['icon']" class="h-5 w-5 transition-transform duration-200 group-hover:scale-110 md:h-6 md:w-6" />
                    </div>
                    <div class="mt-4 md:mt-5">
                        <div class="text-xs font-semibold text-muted">{{ $card['label'] }}</div>
                        <div class="mt-1 break-words text-lg font-semibold leading-tight sm:text-xl md:text-2xl">{{ $card['value'] }}</div>
                        <div class="mt-2 line-clamp-2 text-[11px] leading-4 text-muted md:text-xs md:leading-5" title="{{ $card['meta'] }}">{{ $card['meta'] }}</div>
                    </div>
                </div>
            @endforeach
        </section>

        <section class="grid gap-6 xl:grid-cols-12">
            <div class="rounded-2xl border border-app bg-surface p-4 shadow-soft md:rounded-3xl md:p-6 xl:col-span-7">
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
                    <div class="mt-5 space-y-4 md:hidden">
                        @foreach ($labelCounts->sortDesc()->take(8) as $label => $count)
                            @php
                                // Di layar kecil, distribusi lebih stabil sebagai progress list.
                                $tone = $toneForLabel($label);
                                $width = max(5, round(((int) $count / $maxLabelCount) * 100));
                            @endphp
                            <div>
                                <div class="flex items-center justify-between gap-3 text-xs">
                                    <span class="min-w-0 truncate font-semibold" title="{{ $label }}">{{ $label }}</span>
                                    <span class="shrink-0 font-semibold text-muted">{{ $formatNumber($count) }}</span>
                                </div>
                                <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-surface2">
                                    <div
                                        @class([
                                            'h-full rounded-full',
                                            'bg-[color:var(--accent)]' => $tone === 'accent',
                                            'bg-[color:var(--danger)]' => $tone === 'danger',
                                            'bg-[color:var(--warning)]' => $tone === 'warning',
                                        ])
                                        style="width: {{ $width }}%"
                                    ></div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-7 hidden h-64 items-end gap-5 overflow-x-auto border-b border-app pb-4 md:flex">
                        @foreach ($labelCounts->sortDesc()->take(8) as $label => $count)
                            @php
                                // Hitung tone dan tinggi bar grafik per label.
                                $tone = $toneForLabel($label);
                                $height = max(10, round(((int) $count / $maxLabelCount) * 100));
                            @endphp
                            <div class="flex min-w-20 flex-1 flex-col items-center gap-3">
                                <div class="max-w-20 truncate text-xs font-semibold text-muted">{{ $formatNumber($count) }}</div>
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

            <div class="rounded-2xl border border-app bg-surface p-4 shadow-soft md:rounded-3xl md:p-6 xl:col-span-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold">Riwayat Terbaru</div>
                        <div class="mt-1 text-xs text-muted">Klik item untuk ringkasan cepat.</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-full bg-surface2 px-3 py-1 text-xs font-semibold text-muted" x-text="filteredRecentDetections.length + ' data'"></span>
                        <x-lucide-history class="h-5 w-5 text-primary" />
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button
                        type="button"
                        @click="setRecentSource('all')"
                        class="rounded-xl border px-3 py-1.5 text-xs font-semibold transition-colors"
                        :class="recentSource === 'all' ? 'border-transparent bg-primary text-white' : 'border-app hover:bg-surface2'"
                    >
                        Semua
                    </button>
                    <button
                        type="button"
                        @click="setRecentSource('manual')"
                        class="rounded-xl border px-3 py-1.5 text-xs font-semibold transition-colors"
                        :class="recentSource === 'manual' ? 'border-transparent bg-primary text-white' : 'border-app hover:bg-surface2'"
                    >
                        Manual
                    </button>
                    <button
                        type="button"
                        @click="setRecentSource('api')"
                        class="rounded-xl border px-3 py-1.5 text-xs font-semibold transition-colors"
                        :class="recentSource === 'api' ? 'border-transparent bg-primary text-white' : 'border-app hover:bg-surface2'"
                    >
                        API UPA-TIK
                    </button>
                </div>

                <div class="mt-5 space-y-3">
                    <template x-if="filteredRecentDetections.length === 0">
                        <div class="rounded-3xl border border-dashed border-app bg-surface2 px-5 py-8 text-center">
                            <x-lucide-history class="mx-auto h-10 w-10 text-muted" />
                            <div class="mt-3 text-sm font-semibold">Belum ada riwayat deteksi</div>
                            <div class="mt-1 text-xs leading-5 text-muted">Tidak ada data yang cocok dengan filter sumber.</div>
                        </div>
                    </template>

                    <template x-for="item in pagedRecentDetections" :key="item.id">
                        <button
                            type="button"
                            @click="openDetectionDetail(item)"
                            class="group w-full overflow-hidden rounded-2xl border border-app bg-surface2 px-4 py-3 text-left transition-colors hover:bg-surface focus-visible:outline-none focus-visible:ring-2 ring-app"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="line-clamp-2 break-words text-sm font-semibold leading-5" :title="item.file_name" x-text="item.file_name"></div>
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs leading-5 text-muted">
                                        <span class="rounded-full bg-surface px-2 py-0.5 font-semibold text-app" x-text="item.source_label || 'Manual'"></span>
                                        <span class="min-w-0 break-words" x-text="item.processed_at"></span>
                                        <span class="hidden sm:inline">-</span>
                                        <span class="min-w-0 break-words" x-text="item.user"></span>
                                    </div>
                                </div>
                                <span
                                    class="hidden shrink-0 rounded-full px-3 py-1 text-xs font-semibold sm:inline-flex"
                                    :class="item.malware > 0 ? 'bg-[rgba(248,113,113,0.16)] text-[color:var(--danger)]' : 'bg-[rgba(34,197,94,0.16)] text-[color:var(--accent)]'"
                                    x-text="item.malware > 0 ? 'Malware' : 'Aman'"
                                >
                                </span>
                            </div>
                            <div class="mt-3 grid grid-cols-3 gap-2 text-xs text-muted">
                                <span>Total <span class="font-semibold text-app" x-text="Number(item.total).toLocaleString('id-ID')"></span></span>
                                <span>{{ $normalLabel }} <span class="font-semibold text-app" x-text="Number(item.normal).toLocaleString('id-ID')"></span></span>
                                <span>Malware <span class="font-semibold text-app" x-text="Number(item.malware).toLocaleString('id-ID')"></span></span>
                            </div>
                        </button>
                    </template>
                </div>

                <div
                    x-show="filteredRecentDetections.length > recentPerPage"
                    class="mt-5 flex items-center justify-between gap-3 border-t border-app pt-4 text-xs text-muted"
                >
                    <span>
                        Halaman <span class="font-semibold text-app" x-text="recentPage"></span>/<span x-text="recentTotalPages"></span>
                    </span>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="recentPage = Math.max(1, recentPage - 1)"
                            :disabled="recentPage === 1"
                            class="inline-grid h-9 w-9 place-items-center rounded-xl border border-app transition-colors hover:bg-surface2 disabled:cursor-not-allowed disabled:opacity-40"
                            aria-label="Riwayat sebelumnya"
                        >
                            <x-lucide-chevron-left class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            @click="recentPage = Math.min(recentTotalPages, recentPage + 1)"
                            :disabled="recentPage === recentTotalPages"
                            class="inline-grid h-9 w-9 place-items-center rounded-xl border border-app transition-colors hover:bg-surface2 disabled:cursor-not-allowed disabled:opacity-40"
                            aria-label="Riwayat berikutnya"
                        >
                            <x-lucide-chevron-right class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-app bg-surface p-4 shadow-soft md:rounded-3xl md:p-6">
            <div class="flex flex-col gap-4">
                <div>
                    <div class="text-sm font-semibold">Tabel Detail Hasil Terakhir</div>
                    <div class="mt-1 text-xs text-muted">Klik baris untuk membuka modal detail probabilitas.</div>
                </div>

                <form method="GET" action="{{ route('hasildeteksi.index') }}" class="grid w-full gap-3 md:grid-cols-2 xl:grid-cols-[minmax(220px,1fr)_160px_150px_120px_130px_auto]">
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
                        name="sort"
                        onchange="this.form.submit()"
                        class="rounded-2xl border border-app bg-surface2 px-4 py-3 text-sm focus:border-[color:var(--primary)] focus:ring-2 focus:ring-[color:var(--primary-soft)]"
                    >
                        @foreach ($sortOptions as $value => $label)
                            <option value="{{ $value }}" @selected($tableFilters['sort'] === $value)>Urut: {{ $label }}</option>
                        @endforeach
                    </select>

                    <select
                        name="direction"
                        onchange="this.form.submit()"
                        class="rounded-2xl border border-app bg-surface2 px-4 py-3 text-sm focus:border-[color:var(--primary)] focus:ring-2 focus:ring-[color:var(--primary-soft)]"
                    >
                        <option value="asc" @selected($tableFilters['direction'] === 'asc')>Naik</option>
                        <option value="desc" @selected($tableFilters['direction'] === 'desc')>Turun</option>
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

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap gap-2 text-xs font-semibold text-muted">
                    <span class="rounded-full bg-surface2 px-3 py-1.5">Filter: {{ $hasActiveTableFilter ? 'Aktif' : 'Default' }}</span>
                    @if ($resultsPaginator)
                        <span class="rounded-full bg-surface2 px-3 py-1.5">{{ $formatNumber($resultsPaginator->total()) }} baris cocok</span>
                    @endif
                </div>

                @if ($hasActiveTableFilter)
                    <a
                        href="{{ route('hasildeteksi.index', ['per_page' => $tableFilters['per_page']]) }}"
                        class="inline-flex items-center gap-2 rounded-2xl border border-app px-4 py-2 text-sm font-semibold transition-colors hover:bg-surface2"
                    >
                        <x-lucide-rotate-ccw class="h-4 w-4" />
                        Reset Filter
                    </a>
                @endif
            </div>

            <div class="mt-5 space-y-3 lg:hidden">
                @forelse ($results as $result)
                    @php
                        // Bentuk data satu baris agar bisa dipakai card mobile dan modal detail.
                        $label = $result['label'] ?? 'Unknown';
                        $tone = $toneForLabel($label);
                        $probabilities = collect($result['probabilities'] ?? []);
                        $confidencePercent = max(0, min(100, ((float) ($result['confidence'] ?? 0)) * 100));
                        $normalProbability = max(0, min(100, ((float) ($probabilities[$normalLabel] ?? 0)) * 100));
                        $labelProbability = max(0, min(100, ((float) ($probabilities[$label] ?? $result['confidence'] ?? 0)) * 100));
                        $topPrediction = $topProbability($probabilities);
                        $topPredictionPercent = max(0, min(100, ((float) $topPrediction['value']) * 100));
                        $rowNumber = $resultsPaginator ? (($resultsPaginator->firstItem() ?? 1) + $loop->index) : $loop->iteration;
                        $guardrailApplied = (bool) ($result['blocked_guardrail_applied'] ?? false);
                        $rowPayload = [
                            'no' => $rowNumber,
                            'row' => (string) ($result['row'] ?? $loop->index),
                            'label' => $label,
                            'status' => $statusForTone($tone),
                            'recommendation' => $recommendationForTone($tone),
                            'confidence' => $formatPercent($confidencePercent).'%',
                            'probNormal' => $formatPercent($normalProbability).'%',
                            'probLabel' => $formatPercent($labelProbability).'%',
                            'topLabel' => $topPrediction['label'],
                            'topProbability' => $formatPercent($topPredictionPercent).'%',
                            'guardrailApplied' => $guardrailApplied,
                            'guardrailText' => $guardrailApplied ? 'Deny Guardrail' : '-',
                            'probabilities' => $probabilities
                                ->sortDesc()
                                ->take(4)
                                ->map(fn ($value, $name) => [
                                    'label' => $name,
                                    'value' => $formatPercent(((float) $value) * 100).'%',
                                ])
                                ->values()
                                ->all(),
                        ];
                    @endphp
                    <button
                        type="button"
                        @click="openRowDetail(@js($rowPayload))"
                        class="w-full rounded-2xl border border-app bg-surface2 p-4 text-left focus-visible:outline-none focus-visible:ring-2 ring-app"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-xs font-semibold text-muted">Row {{ $rowPayload['row'] }}</div>
                                <div class="mt-1 flex flex-wrap items-center gap-2">
                                    <span
                                        @class([
                                            'inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold',
                                            'bg-[rgba(248,113,113,0.18)] text-[color:var(--danger)]' => $tone === 'danger',
                                            'bg-[rgba(34,197,94,0.18)] text-[color:var(--accent)]' => $tone === 'accent',
                                            'bg-[rgba(251,191,36,0.18)] text-[color:var(--warning)]' => $tone === 'warning',
                                        ])
                                    >
                                        {{ $label }}
                                    </span>
                                    @if ($guardrailApplied)
                                        <span class="rounded-full bg-[rgba(251,191,36,0.18)] px-3 py-1 text-xs font-semibold text-[color:var(--warning)]">Guardrail</span>
                                    @endif
                                </div>
                            </div>
                            <span class="shrink-0 text-xs font-semibold text-muted">#{{ $rowNumber }}</span>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3 text-xs">
                            <div class="rounded-xl border border-app bg-surface px-3 py-2">
                                <div class="text-muted">Confidence</div>
                                <div class="mt-1 font-semibold">{{ $formatPercent($confidencePercent) }}%</div>
                            </div>
                            <div class="rounded-xl border border-app bg-surface px-3 py-2">
                                <div class="text-muted">Prob. Prediksi</div>
                                <div class="mt-1 font-semibold">{{ $formatPercent($labelProbability) }}%</div>
                            </div>
                            <div class="rounded-xl border border-app bg-surface px-3 py-2">
                                <div class="text-muted">Prob. {{ $normalLabel }}</div>
                                <div class="mt-1 font-semibold">{{ $formatPercent($normalProbability) }}%</div>
                            </div>
                            <div class="rounded-xl border border-app bg-surface px-3 py-2">
                                <div class="text-muted">Status</div>
                                <div class="mt-1 font-semibold">{{ $rowPayload['status'] }}</div>
                            </div>
                        </div>

                        <div class="mt-3 flex items-center justify-between gap-3 text-xs text-muted">
                            <span class="truncate">Top: {{ $topPrediction['label'] }} ({{ $formatPercent($topPredictionPercent) }}%)</span>
                            <span class="font-semibold text-app">Detail</span>
                        </div>
                    </button>
                @empty
                    <div class="rounded-3xl border border-dashed border-app bg-surface2 px-5 py-8 text-center text-muted">
                        <x-lucide-file-text class="mx-auto h-10 w-10" />
                        <div class="mt-3 text-sm font-semibold text-app">Belum ada hasil deteksi</div>
                        <div class="mt-1 text-xs">Upload data untuk mulai menjalankan deteksi malware.</div>
                    </div>
                @endforelse
            </div>

            <div class="mt-5 hidden overflow-auto rounded-3xl border border-app lg:block">
                <table class="w-full min-w-[920px] text-sm">
                    <thead class="sticky top-0 z-10 bg-surface2 text-muted">
                        <tr class="border-b border-app">
                            <th class="px-4 py-3 text-left font-medium">No</th>
                            <th class="px-4 py-3 text-left font-medium">Row ID</th>
                            <th class="px-4 py-3 text-left font-medium">Label</th>
                            <th class="px-4 py-3 text-left font-medium">Confidence</th>
                            <th class="px-4 py-3 text-left font-medium">Prob. {{ $normalLabel }}</th>
                            <th class="px-4 py-3 text-left font-medium">Prob. Prediksi</th>
                            <th class="px-4 py-3 text-left font-medium">Catatan</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-app">
                        @forelse ($results as $result)
                            @php
                                // Bentuk data satu baris agar bisa dipakai tabel dan modal detail.
                                $label = $result['label'] ?? 'Unknown';
                                $tone = $toneForLabel($label);
                                $probabilities = collect($result['probabilities'] ?? []);
                                $confidencePercent = max(0, min(100, ((float) ($result['confidence'] ?? 0)) * 100));
                                $normalProbability = max(0, min(100, ((float) ($probabilities[$normalLabel] ?? 0)) * 100));
                                $labelProbability = max(0, min(100, ((float) ($probabilities[$label] ?? $result['confidence'] ?? 0)) * 100));
                                $topPrediction = $topProbability($probabilities);
                                $topPredictionPercent = max(0, min(100, ((float) $topPrediction['value']) * 100));
                                $rowNumber = $resultsPaginator ? (($resultsPaginator->firstItem() ?? 1) + $loop->index) : $loop->iteration;
                                $guardrailApplied = (bool) ($result['blocked_guardrail_applied'] ?? false);
                                $rowPayload = [
                                    'no' => $rowNumber,
                                    'row' => (string) ($result['row'] ?? $loop->index),
                                    'label' => $label,
                                    'status' => $statusForTone($tone),
                                    'recommendation' => $recommendationForTone($tone),
                                    'confidence' => $formatPercent($confidencePercent).'%',
                                    'probNormal' => $formatPercent($normalProbability).'%',
                                    'probLabel' => $formatPercent($labelProbability).'%',
                                    'topLabel' => $topPrediction['label'],
                                    'topProbability' => $formatPercent($topPredictionPercent).'%',
                                    'guardrailApplied' => $guardrailApplied,
                                    'guardrailText' => $guardrailApplied ? 'Deny Guardrail' : '-',
                                    'probabilities' => $probabilities
                                        ->sortDesc()
                                        ->take(4)
                                        ->map(fn ($value, $name) => [
                                            'label' => $name,
                                            'value' => $formatPercent(((float) $value) * 100).'%',
                                        ])
                                        ->values()
                                        ->all(),
                                ];
                            @endphp
                            <tr class="transition-colors hover:bg-surface2/70">
                                <td class="px-4 py-3 text-muted">{{ $rowNumber }}</td>
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
                                <td class="px-4 py-3">
                                    <div class="font-semibold">{{ $formatPercent($labelProbability) }}%</div>
                                    <div class="mt-1 max-w-36 truncate text-xs text-muted" title="{{ $topPrediction['label'] }}">
                                        Top: {{ $topPrediction['label'] }} ({{ $formatPercent($topPredictionPercent) }}%)
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($guardrailApplied)
                                        <span class="rounded-full bg-[rgba(251,191,36,0.18)] px-3 py-1 text-xs font-semibold text-[color:var(--warning)]">Deny Guardrail</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
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
                                    <div class="mt-1 text-xs text-muted">{{ $rowPayload['recommendation'] }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        type="button"
                                        @click="openRowDetail(@js($rowPayload))"
                                        class="inline-flex items-center justify-center gap-2 rounded-2xl border border-app px-3 py-2 text-xs font-semibold transition-colors hover:bg-surface2"
                                    >
                                        <x-lucide-eye class="h-4 w-4" />
                                        Detail
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-6 py-10 text-center text-muted" colspan="9">
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
                            // Hitung window nomor halaman agar pagination tetap ringkas.
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
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-2xl border border-app bg-surface2 p-4">
                            <div class="text-xs text-muted">Confidence</div>
                            <div class="mt-1 text-lg font-semibold sm:text-xl" x-text="rowDetail.confidence"></div>
                        </div>
                        <div class="rounded-2xl border border-app bg-surface2 p-4">
                            <div class="text-xs text-muted">Prob. {{ $normalLabel }}</div>
                            <div class="mt-1 text-lg font-semibold sm:text-xl" x-text="rowDetail.probNormal"></div>
                        </div>
                        <div class="rounded-2xl border border-app bg-surface2 p-4">
                            <div class="text-xs text-muted">Prob. Label</div>
                            <div class="mt-1 text-lg font-semibold sm:text-xl" x-text="rowDetail.probLabel"></div>
                        </div>
                        <div class="rounded-2xl border border-app bg-surface2 p-4">
                            <div class="text-xs text-muted">Top Prediksi</div>
                            <div class="mt-1 truncate text-lg font-semibold sm:text-xl" x-text="rowDetail.topLabel"></div>
                            <div class="mt-1 text-xs text-muted" x-text="rowDetail.topProbability"></div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-app bg-surface2 p-4 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-muted">Catatan</span>
                            <span class="font-semibold" x-text="rowDetail.guardrailText || '-'"></span>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-muted">Status</span>
                            <span class="font-semibold" x-text="rowDetail.status"></span>
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-4">
                            <span class="text-muted">Rekomendasi</span>
                            <span class="font-semibold" x-text="rowDetail.recommendation"></span>
                        </div>
                    </div>

                    <div>
                        <div class="text-sm font-semibold">Probabilitas Utama</div>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <template x-for="item in rowDetail.probabilities" :key="item.label">
                                <div class="flex items-center justify-between gap-4 rounded-2xl border border-app bg-surface2 px-4 py-3">
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
                            <div class="text-xs text-muted">{{ $normalLabel }}</div>
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
