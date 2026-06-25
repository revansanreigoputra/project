@extends('layouts.dashboard')
@section('title', 'Review Hasil Deteksi')

@section('page')
    @php
        $prediction = $prediction ?? [];
        $summary = $prediction['summary'] ?? [];
        $resultsPaginator = $resultsPaginator ?? null;
        $results = $resultsPaginator ? collect($resultsPaginator->items()) : collect($prediction['results'] ?? []);
        $tableFilters = array_merge([
            'search' => '',
            'label' => 'all',
            'sort' => 'row',
            'direction' => 'asc',
            'per_page' => 10,
        ], $tableFilters ?? []);
        $perPageOptions = $perPageOptions ?? [10, 25, 50, 100];
        $responsibleUser = $detectionResult->user ?? $detectionResult->scanUpload?->user;
        $formatNumber = fn ($value) => number_format((float) $value, 0, ',', '.');
        $formatPercent = fn ($value) => number_format((float) $value, 2, ',', '.');
        $formatDateTime = fn ($value) => $value ? $value->copy()->timezone('Asia/Jakarta')->format('d/m/Y H:i') . ' WIB' : '-';
        $formatFileSize = function ($bytes) {
            $bytes = (int) $bytes;

            if ($bytes >= 1048576) {
                return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
            }

            if ($bytes >= 1024) {
                return number_format($bytes / 1024, 2, ',', '.') . ' KB';
            }

            return number_format($bytes, 0, ',', '.') . ' B';
        };
        $normalLabel = $summary['normal_label'] ?? 'Normal';
        $guardrailAppliedCount = (int) ($summary['guardrail_applied_count'] ?? 0);
        $toneForLabel = fn ($label) => $label === $normalLabel ? 'accent' : ($label === 'Unknown' ? 'warning' : 'danger');
        $sortOptions = [
            'row' => 'Row ID',
            'label' => 'Label',
            'confidence' => 'Confidence',
        ];
        $statusForTone = fn ($tone) => $tone === 'accent' ? 'Aman' : ($tone === 'warning' ? 'Unknown' : 'Perlu Review');
        $recommendationForTone = fn ($tone) => $tone === 'accent' ? 'Arsipkan' : ($tone === 'warning' ? 'Validasi manual' : 'Prioritaskan');
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

        $total = (int) ($detectionResult->total ?? 0);
        $normal = (int) ($summary['normal'] ?? 0);
        $malware = (int) ($summary['malware'] ?? 0);
        $unknown = (int) ($summary['unknown'] ?? 0);
        $normalPercent = (float) ($summary['normal_percent'] ?? 0);
        $malwarePercent = (float) ($summary['malware_percent'] ?? 0);
        $averageConfidence = (float) ($summary['average_confidence'] ?? $detectionResult->average_confidence ?? 0);
        $isDetected = $malware > 0;
        $inspectionStatus = $isDetected ? 'Malware Terdeteksi' : ($unknown > 0 ? 'Perlu Verifikasi' : 'Tidak Ditemukan Malware');
        $inspectionTone = $isDetected ? 'danger' : ($unknown > 0 ? 'warning' : 'accent');
        $processedAt = $formatDateTime($detectionResult->processed_at ?? $detectionResult->created_at);
        $uploadedAt = $formatDateTime($detectionResult->scanUpload?->created_at);
        $sourceLabel = $detectionResult->scanUpload?->sourceLabel() ?? 'Manual';
        $labelOptions = collect($labelOptions ?? [])->whenEmpty(fn ($items) => collect($summary['labels'] ?? [])->keys())->whenEmpty(fn ($items) => collect([$normalLabel]));
        $labelCounts = collect($summary['labels'] ?? [])
            ->map(fn ($count) => (int) $count)
            ->filter(fn ($count) => $count > 0)
            ->whenEmpty(fn () => collect([
                $normalLabel => $normal,
                'Malware' => $malware,
                'Unknown' => $unknown,
            ])->filter(fn ($count) => $count > 0));
        $maxLabelCount = max($labelCounts->max() ?: 0, 1);
        $chartLabels = $labelCounts->sortDesc()->take(8);
        $malwareLabels = collect($summary['malware_labels'] ?? [])
            ->map(fn ($count) => (int) $count)
            ->filter(fn ($count) => $count > 0)
            ->sortDesc()
            ->take(6);
        $normalWidth = max(0, min(100, $normalPercent));
        $malwareWidth = max(0, min(100, $malwarePercent));
        $unknownWidth = max(0, min(100, 100 - $normalWidth - $malwareWidth));
    @endphp

    <div
        class="space-y-6"
        x-data="{
            rowDetailOpen: false,
            rowDetail: { probabilities: [] },
            openRowDetail(payload) {
                this.rowDetail = payload;
                this.rowDetailOpen = true;
            },
        }"
    >
        <section class="rounded-3xl border border-app bg-surface p-6 shadow-soft">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                <div class="min-w-0">
                    <a
                        href="{{ route('hasildeteksi.index') }}"
                        class="inline-flex items-center gap-2 text-sm font-semibold text-muted transition-colors hover:text-app"
                    >
                        <x-lucide-arrow-left class="h-4 w-4" />
                        Kembali ke Hasil Deteksi
                    </a>

                    <div class="mt-5 flex flex-col gap-4 lg:flex-row lg:items-center">
                        <div
                            @class([
                                'grid h-16 w-16 shrink-0 place-items-center rounded-3xl',
                                'bg-[rgba(248,113,113,0.16)] text-[color:var(--danger)]' => $inspectionTone === 'danger',
                                'bg-[rgba(251,191,36,0.18)] text-[color:var(--warning)]' => $inspectionTone === 'warning',
                                'bg-[rgba(34,197,94,0.16)] text-[color:var(--accent)]' => $inspectionTone === 'accent',
                            ])
                        >
                            @if ($inspectionTone === 'danger')
                                <x-lucide-shield-alert class="h-8 w-8" />
                            @elseif ($inspectionTone === 'warning')
                                <x-lucide-circle-help class="h-8 w-8" />
                            @else
                                <x-lucide-shield-check class="h-8 w-8" />
                            @endif
                        </div>

                        <div class="min-w-0">
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-muted">Review hasil deteksi</div>
                            <h1 class="mt-2 break-words text-2xl font-semibold leading-tight md:text-3xl">
                                {{ $detectionResult->file_name ?? $detectionResult->scanUpload?->original_name ?? '-' }}
                            </h1>
                            <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-muted">
                                <span class="rounded-full bg-surface2 px-3 py-1 text-xs font-semibold text-app">{{ $sourceLabel }}</span>
                                <span>ID Hasil #{{ $detectionResult->id }}</span>
                                <span class="hidden sm:inline">|</span>
                                <span>Diproses {{ $processedAt }}</span>
                                <span class="hidden sm:inline">|</span>
                                <span>{{ $formatFileSize($detectionResult->scanUpload?->size ?? 0) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span
                        @class([
                            'inline-flex items-center gap-2 rounded-2xl px-4 py-2 text-sm font-semibold',
                            'bg-[rgba(248,113,113,0.16)] text-[color:var(--danger)]' => $inspectionTone === 'danger',
                            'bg-[rgba(251,191,36,0.18)] text-[color:var(--warning)]' => $inspectionTone === 'warning',
                            'bg-[rgba(34,197,94,0.16)] text-[color:var(--accent)]' => $inspectionTone === 'accent',
                        ])
                    >
                        <span
                            @class([
                                'h-2.5 w-2.5 rounded-full',
                                'bg-[color:var(--danger)]' => $inspectionTone === 'danger',
                                'bg-[color:var(--warning)]' => $inspectionTone === 'warning',
                                'bg-[color:var(--accent)]' => $inspectionTone === 'accent',
                            ])
                        ></span>
                        {{ $inspectionStatus }}
                    </span>

                    <span
                        @class([
                            'inline-flex items-center gap-2 rounded-2xl px-4 py-2 text-sm font-semibold',
                            'bg-[rgba(34,197,94,0.16)] text-[color:var(--accent)]' => $detectionResult->status === 'Selesai',
                            'bg-[rgba(248,113,113,0.16)] text-[color:var(--danger)]' => $detectionResult->status === 'Gagal',
                            'bg-[rgba(251,191,36,0.18)] text-[color:var(--warning)]' => ! in_array($detectionResult->status, ['Selesai', 'Gagal'], true),
                        ])
                    >
                        <x-lucide-activity class="h-4 w-4" />
                        {{ $detectionResult->status }}
                    </span>

                    @if ($detectionResult->scanUpload && auth()->user()?->can('history.view'))
                        <a
                            href="{{ route('riwayat.show', ['scanUpload' => $detectionResult->scanUpload, 'mode' => 'after']) }}"
                            class="inline-flex items-center gap-2 rounded-2xl border border-app px-4 py-2 text-sm font-semibold transition-colors hover:bg-surface2"
                        >
                            <x-lucide-clock class="h-4 w-4" />
                            Riwayat
                        </a>
                    @endif

                    @if ($detectionResult->scan_upload_id && auth()->user()?->can('reports.download'))
                        <a
                            href="{{ route('riwayat.download', ['scanUpload' => $detectionResult->scan_upload_id, 'mode' => 'after']) }}"
                            class="inline-flex items-center gap-2 rounded-2xl border border-app px-4 py-2 text-sm font-semibold transition-colors hover:bg-surface2"
                        >
                            <x-lucide-download class="h-4 w-4" />
                            Export
                        </a>
                    @endif
                </div>
            </div>

            @if (filled($detectionResult->error_message))
                <div class="mt-5 rounded-2xl border border-[rgba(248,113,113,0.35)] bg-[rgba(248,113,113,0.12)] px-5 py-4 text-sm text-[color:var(--danger)]">
                    {{ $detectionResult->error_message }}
                </div>
            @endif
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-3xl border border-app bg-surface p-5 shadow-soft">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs font-semibold text-muted">Total Data</div>
                    <x-lucide-database class="h-5 w-5 text-primary" />
                </div>
                <div class="mt-4 text-3xl font-semibold">{{ $formatNumber($total) }}</div>
                <div class="mt-1 text-xs text-muted">Baris diperiksa model</div>
            </div>

            <div class="rounded-3xl border border-app bg-surface p-5 shadow-soft">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs font-semibold text-muted">{{ $normalLabel }}</div>
                    <x-lucide-shield-check class="h-5 w-5 text-[color:var(--accent)]" />
                </div>
                <div class="mt-4 text-3xl font-semibold">{{ $formatNumber($normal) }}</div>
                <div class="mt-1 text-xs text-muted">{{ $formatPercent($normalPercent) }}% dari total</div>
            </div>

            <div class="rounded-3xl border border-app bg-surface p-5 shadow-soft">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs font-semibold text-muted">Malware</div>
                    <x-lucide-bug class="h-5 w-5 text-[color:var(--danger)]" />
                </div>
                <div class="mt-4 text-3xl font-semibold">{{ $formatNumber($malware) }}</div>
                <div class="mt-1 text-xs text-muted">{{ $formatPercent($malwarePercent) }}% perlu review</div>
            </div>

            <div class="rounded-3xl border border-app bg-surface p-5 shadow-soft">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs font-semibold text-muted">Unknown</div>
                    <x-lucide-circle-help class="h-5 w-5 text-[color:var(--warning)]" />
                </div>
                <div class="mt-4 text-3xl font-semibold">{{ $formatNumber($unknown) }}</div>
                <div class="mt-1 text-xs text-muted">Data belum terklasifikasi</div>
            </div>

            <div class="rounded-3xl border border-app bg-surface p-5 shadow-soft">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs font-semibold text-muted">Confidence</div>
                    <x-lucide-gauge class="h-5 w-5 text-[color:var(--info)]" />
                </div>
                <div class="mt-4 text-3xl font-semibold">{{ $formatPercent($averageConfidence) }}%</div>
                <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-surface2">
                    <div class="h-full rounded-full bg-[color:var(--info)]" style="width: {{ max(0, min(100, $averageConfidence)) }}%"></div>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-12">
            <div class="rounded-3xl border border-app bg-surface p-6 shadow-soft xl:col-span-8">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="text-sm font-semibold">Distribusi Label</div>
                        <div class="mt-1 text-xs text-muted">Komposisi label pada hasil deteksi ini.</div>
                    </div>
                    <div class="rounded-2xl bg-surface2 px-3 py-2 text-xs font-semibold text-muted">
                        {{ $formatNumber($labelCounts->sum()) }} data terklasifikasi
                    </div>
                </div>

                <div class="mt-6 overflow-hidden rounded-3xl border border-app bg-surface2">
                    <div class="flex h-4 w-full">
                        <div class="bg-[color:var(--accent)]" style="width: {{ $normalWidth }}%"></div>
                        <div class="bg-[color:var(--danger)]" style="width: {{ $malwareWidth }}%"></div>
                        <div class="bg-[color:var(--warning)]" style="width: {{ $unknownWidth }}%"></div>
                    </div>
                    <div class="grid gap-3 p-4 text-xs text-muted md:grid-cols-3">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full bg-[color:var(--accent)]"></span>
                            {{ $normalLabel }} {{ $formatPercent($normalPercent) }}%
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full bg-[color:var(--danger)]"></span>
                            Malware {{ $formatPercent($malwarePercent) }}%
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full bg-[color:var(--warning)]"></span>
                            Unknown {{ $formatNumber($unknown) }}
                        </div>
                    </div>
                </div>

                @if ($chartLabels->isNotEmpty())
                    <div class="mt-7 flex h-72 items-end gap-3 overflow-x-auto border-b border-app pb-4 md:gap-5">
                        @foreach ($chartLabels as $label => $count)
                            @php
                                $tone = $toneForLabel($label);
                                $height = max(10, round(((int) $count / $maxLabelCount) * 100));
                            @endphp
                            <div class="flex min-w-20 flex-1 flex-col items-center gap-3">
                                <div class="text-xs font-semibold text-muted">{{ $formatNumber($count) }}</div>
                                <div class="flex h-52 w-full max-w-24 items-end overflow-hidden rounded-t-2xl bg-surface2">
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
                            <x-lucide-list-checks class="mx-auto h-10 w-10 text-muted" />
                            <div class="mt-3 text-sm font-semibold">Belum ada distribusi label</div>
                            <div class="mt-1 text-xs text-muted">Data grafik akan muncul setelah hasil deteksi tersimpan.</div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="space-y-6 xl:col-span-4">
                <div class="rounded-3xl border border-app bg-surface p-6 shadow-soft">
                    <div class="text-sm font-semibold">Data Utama</div>
                    <div class="mt-5 space-y-4 text-sm">
                        <div class="flex items-start justify-between gap-4">
                            <span class="text-muted">Nama file</span>
                            <span class="max-w-56 truncate text-right font-semibold" title="{{ $detectionResult->file_name ?? $detectionResult->scanUpload?->original_name ?? '-' }}">
                                {{ $detectionResult->file_name ?? $detectionResult->scanUpload?->original_name ?? '-' }}
                            </span>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <span class="text-muted">Sumber data</span>
                            <span class="text-right font-semibold">{{ $sourceLabel }}</span>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <span class="text-muted">Waktu upload</span>
                            <span class="text-right font-semibold">{{ $uploadedAt }}</span>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <span class="text-muted">Waktu deteksi</span>
                            <span class="text-right font-semibold">{{ $processedAt }}</span>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <span class="text-muted">Status proses</span>
                            <span class="text-right font-semibold">{{ $detectionResult->status }}</span>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <span class="text-muted">Petugas</span>
                            <span class="max-w-56 truncate text-right font-semibold" title="{{ $responsibleUser?->email ?? '-' }}">
                                {{ $responsibleUser?->name ?? '-' }}
                            </span>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <span class="text-muted">Email petugas</span>
                            <span class="max-w-56 truncate text-right font-semibold" title="{{ $responsibleUser?->email ?? '-' }}">
                                {{ $responsibleUser?->email ?? '-' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-app bg-surface p-6 shadow-soft">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-sm font-semibold">Label Berisiko</div>
                            <div class="mt-1 text-xs text-muted">Kategori malware terbanyak.</div>
                        </div>
                        <div class="rounded-2xl bg-[rgba(248,113,113,0.14)] p-2 text-[color:var(--danger)]">
                            <x-lucide-bug class="h-5 w-5" />
                        </div>
                    </div>

                    <div class="mt-5 space-y-4">
                        @forelse ($malwareLabels as $label => $count)
                            @php
                                $width = max(5, round(((int) $count / $maxLabelCount) * 100));
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
                                <div class="mt-1 text-xs leading-5 text-muted">Hasil ini tidak memiliki kategori malware spesifik.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-app bg-surface p-6 shadow-soft">
            <div class="flex flex-col gap-4">
                <div>
                    <div class="text-sm font-semibold">Tabel Detail Hasil</div>
                    <div class="mt-1 text-xs text-muted">Baris hasil prediksi lengkap yang tersimpan untuk proses ini.</div>
                </div>

                <form method="GET" action="{{ route('hasildeteksi.show', $detectionResult) }}" class="grid w-full gap-3 md:grid-cols-2 xl:grid-cols-[minmax(220px,1fr)_160px_150px_120px_130px_auto]">
                    <div class="relative">
                        <x-lucide-search class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" />
                        <input
                            type="search"
                            name="search"
                            value="{{ $tableFilters['search'] }}"
                            placeholder="Cari row, label, confidence"
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
                        href="{{ route('hasildeteksi.show', ['detectionResult' => $detectionResult, 'per_page' => $tableFilters['per_page']]) }}"
                        class="inline-flex items-center gap-2 rounded-2xl border border-app px-4 py-2 text-sm font-semibold transition-colors hover:bg-surface2"
                    >
                        <x-lucide-rotate-ccw class="h-4 w-4" />
                        Reset Filter
                    </a>
                @endif
            </div>

            <div class="mt-5 overflow-auto rounded-3xl border border-app">
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
                                $label = $result['label'] ?? 'Unknown';
                                $tone = $toneForLabel($label);
                                $probabilities = $result['probabilities'] ?? [];
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
                                    'probabilities' => collect($probabilities)
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
                                    <div class="mt-3 text-sm font-semibold text-app">Detail baris belum tersedia</div>
                                    <div class="mt-1 text-xs">Hasil deteksi ini belum memiliki data baris yang bisa ditampilkan.</div>
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
    </div>
@endsection
