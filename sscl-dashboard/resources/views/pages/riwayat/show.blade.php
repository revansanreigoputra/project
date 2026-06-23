@extends('layouts.dashboard')
@section('title', 'Detail Riwayat')

@section('page')
    @php
        $historyItem = $historyItem ?? [];
        $sourcePreview = $sourcePreview ?? [
            'headers' => [],
            'rows' => [],
            'message' => null,
            'is_truncated' => false,
        ];
        $prediction = $prediction ?? null;
        $summary = $prediction['summary'] ?? [];
        $afterResultsPaginator = $afterResultsPaginator ?? null;
        $results = $afterResultsPaginator ? collect($afterResultsPaginator->items()) : collect($prediction['results'] ?? []);
        $afterTableFilters = $afterTableFilters ?? ['search' => '', 'label' => 'all', 'per_page' => 10];
        $perPageOptions = $perPageOptions ?? [10, 25, 50, 100];
        $formatNumber = fn ($value) => number_format((float) $value, 0, ',', '.');
        $formatPercent = fn ($value) => number_format((float) $value, 2, ',', '.');
        $status = $historyItem['status'] ?? '-';
        $sourceLabel = $historyItem['source_label'] ?? $scanUpload->sourceLabel();
        $labelOptions = collect($afterLabelOptions ?? [])->whenEmpty(fn ($items) => collect($summary['labels'] ?? [])->keys())->whenEmpty(fn ($items) => collect(['Normal']));
        $toneForLabel = fn ($label) => $label === 'Normal' ? 'accent' : ($label === 'Unknown' ? 'warning' : 'danger');
        $canManageOperational = auth()->user()?->can('operational.manage');
    @endphp

    <div
        class="space-y-6"
        x-data="{
            deleteModalOpen: false,
            beforeSearch: '',
            afterSearch: '',
            afterLabel: 'all'
        }"
    >
        <div class="rounded-3xl bg-surface shadow-soft p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <a
                        href="{{ route('riwayat.index') }}"
                        class="inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-app transition-colors"
                    >
                        <x-lucide-arrow-left class="h-4 w-4" />
                        Kembali ke Riwayat
                    </a>

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <div class="text-xl font-semibold truncate">{{ $scanUpload->original_name }}</div>
                        <span class="rounded-full bg-surface2 px-3 py-1 text-xs font-semibold text-app">{{ $sourceLabel }}</span>
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted">
                        <span>Upload: {{ $historyItem['uploaded_at'] ?? '-' }}</span>
                        <span class="hidden sm:inline">|</span>
                        <span>Proses: {{ $historyItem['processed_at'] ?? '-' }}</span>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span
                        @class([
                            'px-3 py-2 rounded-xl text-xs font-semibold whitespace-nowrap',
                            'bg-[rgba(34,197,94,0.18)] text-[color:var(--accent)]' => $status === 'Selesai',
                            'bg-[rgba(248,113,113,0.18)] text-[color:var(--danger)]' => $status === 'Gagal',
                            'bg-[rgba(251,191,36,0.18)] text-[color:var(--warning)]' => ! in_array($status, ['Selesai', 'Gagal'], true),
                        ])
                    >
                        {{ $status }}
                    </span>

                    @can('reports.download')
                        <a
                            href="{{ route('riwayat.download', ['scanUpload' => $scanUpload, 'mode' => 'before']) }}"
                            class="inline-flex items-center gap-2 rounded-xl border border-app px-4 py-2 text-sm font-semibold hover:bg-surface2 transition-colors"
                        >
                            <x-lucide-download class="h-4 w-4" />
                            Unduh Asli
                        </a>
                    @endcan

                    @if ($prediction && auth()->user()?->can('reports.download'))
                        <a
                            href="{{ route('riwayat.download', ['scanUpload' => $scanUpload, 'mode' => 'after']) }}"
                            class="inline-flex items-center gap-2 rounded-xl border border-app px-4 py-2 text-sm font-semibold hover:bg-surface2 transition-colors"
                        >
                            <x-lucide-file-down class="h-4 w-4" />
                            Unduh Hasil
                        </a>
                    @endif

                    @if ($canManageOperational)
                        <button
                            type="button"
                            @click="deleteModalOpen = true"
                            class="inline-flex items-center gap-2 rounded-xl border border-[rgba(248,113,113,0.35)] px-4 py-2 text-sm font-semibold text-[color:var(--danger)] hover:bg-[rgba(248,113,113,0.12)] transition-colors"
                        >
                            <x-lucide-trash-2 class="h-4 w-4" />
                            Hapus
                        </button>
                    @endif
                </div>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-4">
                <div class="rounded-2xl bg-surface2 border border-app p-4">
                    <div class="text-xs text-muted">Total Data</div>
                    <div class="mt-1 text-2xl font-semibold">{{ $formatNumber($historyItem['total'] ?? 0) }}</div>
                </div>
                <div class="rounded-2xl bg-surface2 border border-app p-4">
                    <div class="text-xs text-muted">Normal</div>
                    <div class="mt-1 text-2xl font-semibold">{{ $formatNumber($historyItem['normal'] ?? 0) }}</div>
                </div>
                <div class="rounded-2xl bg-surface2 border border-app p-4">
                    <div class="text-xs text-muted">Malware</div>
                    <div class="mt-1 text-2xl font-semibold">{{ $formatNumber($historyItem['malware'] ?? 0) }}</div>
                </div>
                <div class="rounded-2xl bg-surface2 border border-app p-4">
                    <div class="text-xs text-muted">Avg. Confidence</div>
                    <div class="mt-1 text-2xl font-semibold">{{ $formatPercent($historyItem['average_confidence'] ?? 0) }}%</div>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-2">
                <a
                    href="{{ route('riwayat.show', ['scanUpload' => $scanUpload, 'mode' => 'before']) }}"
                    @class([
                        'inline-flex items-center gap-2 rounded-2xl px-5 py-3 text-sm font-semibold transition-colors',
                        'bg-primary text-white' => $mode === 'before',
                        'border border-app hover:bg-surface2' => $mode !== 'before',
                    ])
                >
                    <x-lucide-eye class="h-4 w-4" />
                    Data Sebelum Deteksi
                </a>

                <a
                    href="{{ route('riwayat.show', ['scanUpload' => $scanUpload, 'mode' => 'after']) }}"
                    @class([
                        'inline-flex items-center gap-2 rounded-2xl px-5 py-3 text-sm font-semibold transition-colors',
                        'bg-primary text-white' => $mode === 'after',
                        'border border-app hover:bg-surface2' => $mode !== 'after',
                    ])
                >
                    <x-lucide-list-checks class="h-4 w-4" />
                    Data Sesudah Deteksi
                </a>
            </div>
        </div>

        @if ($mode === 'before')
            <div class="rounded-3xl bg-surface shadow-soft p-6">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="text-sm font-semibold">Data Sebelum Deteksi</div>
                        <div class="mt-1 text-xs text-muted">Preview isi file asli yang tersimpan sebelum diproses model.</div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if ($sourcePreview['is_truncated'])
                            <div class="text-xs font-semibold text-muted">Menampilkan 100 baris pertama</div>
                        @endif

                        <div class="relative min-w-56 flex-1 md:flex-none">
                            <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" />
                            <input
                                type="search"
                                x-model.debounce.150ms="beforeSearch"
                                placeholder="Cari isi tabel"
                                class="w-full rounded-xl border border-app bg-surface2 py-2 pl-9 pr-3 text-sm focus:border-[color:var(--primary)] focus:ring-2 focus:ring-[color:var(--primary-soft)]"
                            >
                        </div>

                        @can('reports.download')
                            <a
                                href="{{ route('riwayat.download', ['scanUpload' => $scanUpload, 'mode' => 'before']) }}"
                                class="inline-flex items-center gap-2 rounded-xl border border-app px-4 py-2 text-sm font-semibold hover:bg-surface2 transition-colors"
                            >
                                <x-lucide-download class="h-4 w-4" />
                                Download
                            </a>
                        @endcan
                    </div>
                </div>

                @if ($sourcePreview['message'])
                    <div class="mt-5 rounded-2xl border border-app bg-surface2 px-5 py-4 text-sm text-muted">
                        {{ $sourcePreview['message'] }}
                    </div>
                @endif

                @if (! empty($sourcePreview['headers']) && ! empty($sourcePreview['rows']))
                    <div class="mt-5 max-h-[460px] overflow-auto rounded-2xl border border-app">
                        <table class="w-full min-w-max text-sm">
                            <thead class="sticky top-0 z-10 bg-surface2 text-muted">
                                <tr class="border-b border-app">
                                    <th class="px-3 py-2.5 text-left font-medium">No</th>
                                    @foreach ($sourcePreview['headers'] as $header)
                                        <th class="px-3 py-2.5 text-left font-medium whitespace-nowrap">{{ $header }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-app">
                                @foreach ($sourcePreview['rows'] as $row)
                                    <tr
                                        class="hover:bg-surface2/50"
                                        data-search="{{ collect($row)->implode(' ') }}"
                                        x-show="!beforeSearch || ($el.dataset.search || '').toLowerCase().includes(beforeSearch.toLowerCase())"
                                    >
                                        <td class="px-3 py-2.5">{{ $loop->iteration }}</td>
                                        @foreach ($row as $value)
                                            <td class="px-3 py-2.5 text-muted whitespace-nowrap">{{ $value }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @else
            <div class="rounded-3xl bg-surface shadow-soft p-6">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="text-sm font-semibold">Data Sesudah Deteksi</div>
                        <div class="mt-1 text-xs text-muted">Hasil prediksi yang tersimpan dari proses deteksi.</div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($prediction)
                            <form method="GET" action="{{ route('riwayat.show', $scanUpload) }}" class="flex flex-wrap items-center gap-2">
                                <input type="hidden" name="mode" value="after">

                                <div class="relative min-w-56 flex-1 md:flex-none">
                                    <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" />
                                    <input
                                        type="search"
                                        name="after_search"
                                        value="{{ $afterTableFilters['search'] }}"
                                        placeholder="Cari row, label, confidence"
                                        class="w-full rounded-xl border border-app bg-surface2 py-2 pl-9 pr-3 text-sm focus:border-[color:var(--primary)] focus:ring-2 focus:ring-[color:var(--primary-soft)]"
                                    >
                                </div>

                                <select
                                    name="after_label"
                                    onchange="this.form.submit()"
                                    class="rounded-xl border border-app bg-surface2 px-3 py-2 text-sm focus:border-[color:var(--primary)] focus:ring-2 focus:ring-[color:var(--primary-soft)]"
                                >
                                    <option value="all">Semua Label</option>
                                    @foreach ($labelOptions as $option)
                                        <option value="{{ $option }}" @selected($afterTableFilters['label'] === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>

                                <select
                                    name="after_per_page"
                                    onchange="this.form.submit()"
                                    class="rounded-xl border border-app bg-surface2 px-3 py-2 text-sm focus:border-[color:var(--primary)] focus:ring-2 focus:ring-[color:var(--primary-soft)]"
                                >
                                    @foreach ($perPageOptions as $option)
                                        <option value="{{ $option }}" @selected((int) $afterTableFilters['per_page'] === (int) $option)>{{ $option }} / halaman</option>
                                    @endforeach
                                </select>

                                <button
                                    type="submit"
                                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white hover:opacity-90 transition-opacity"
                                >
                                    <x-lucide-search class="h-4 w-4" />
                                    Cari
                                </button>

                                @if (($afterTableFilters['search'] ?? '') !== '' || ($afterTableFilters['label'] ?? 'all') !== 'all')
                                    <a
                                        href="{{ route('riwayat.show', ['scanUpload' => $scanUpload, 'mode' => 'after', 'after_per_page' => $afterTableFilters['per_page']]) }}"
                                        class="inline-flex items-center gap-2 rounded-xl border border-app px-4 py-2 text-sm font-semibold hover:bg-surface2 transition-colors"
                                    >
                                        <x-lucide-rotate-ccw class="h-4 w-4" />
                                        Reset
                                    </a>
                                @endif
                            </form>

                            @can('reports.download')
                                <a
                                    href="{{ route('riwayat.download', ['scanUpload' => $scanUpload, 'mode' => 'after']) }}"
                                    class="inline-flex items-center gap-2 rounded-xl border border-app px-4 py-2 text-sm font-semibold hover:bg-surface2 transition-colors"
                                >
                                    <x-lucide-file-down class="h-4 w-4" />
                                    Download
                                </a>
                            @endcan
                        @endif
                    </div>
                </div>

                @if ($prediction)
                    <div class="mt-5 grid gap-4 md:grid-cols-4">
                        <div class="rounded-2xl bg-surface2 border border-app p-4">
                            <div class="text-xs text-muted">Normal</div>
                            <div class="mt-1 text-2xl font-semibold">{{ $formatNumber($summary['normal'] ?? 0) }}</div>
                            <div class="text-xs text-muted">{{ $formatPercent($summary['normal_percent'] ?? 0) }}%</div>
                        </div>
                        <div class="rounded-2xl bg-surface2 border border-app p-4">
                            <div class="text-xs text-muted">Malware</div>
                            <div class="mt-1 text-2xl font-semibold">{{ $formatNumber($summary['malware'] ?? 0) }}</div>
                            <div class="text-xs text-muted">{{ $formatPercent($summary['malware_percent'] ?? 0) }}%</div>
                        </div>
                        <div class="rounded-2xl bg-surface2 border border-app p-4">
                            <div class="text-xs text-muted">Unknown</div>
                            <div class="mt-1 text-2xl font-semibold">{{ $formatNumber($summary['unknown'] ?? 0) }}</div>
                        </div>
                        <div class="rounded-2xl bg-surface2 border border-app p-4">
                            <div class="text-xs text-muted">Avg. Confidence</div>
                            <div class="mt-1 text-2xl font-semibold">{{ $formatPercent($summary['average_confidence'] ?? 0) }}%</div>
                        </div>
                    </div>

                    <div class="mt-5 max-h-[460px] overflow-auto rounded-2xl border border-app">
                        <table class="w-full min-w-[760px] text-sm">
                            <thead class="sticky top-0 z-10 bg-surface2 text-muted">
                                <tr class="border-b border-app">
                                    <th class="px-3 py-2.5 text-left font-medium">Row ID</th>
                                    <th class="px-3 py-2.5 text-left font-medium">Confidence</th>
                                    <th class="px-3 py-2.5 text-left font-medium">Prob. Normal</th>
                                    <th class="px-3 py-2.5 text-left font-medium">Prob. Label</th>
                                    <th class="px-3 py-2.5 text-left font-medium">Label</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-app">
                                @forelse ($results as $result)
                                    @php
                                        $label = $result['label'] ?? 'Unknown';
                                        $tone = $toneForLabel($label);
                                        $probabilities = $result['probabilities'] ?? [];
                                    @endphp
                                    <tr
                                        class="hover:bg-surface2/50"
                                    >
                                        <td class="px-3 py-2.5">{{ $result['row'] ?? $loop->index }}</td>
                                        <td class="px-3 py-2.5 font-semibold">{{ $formatPercent(((float) ($result['confidence'] ?? 0)) * 100) }}%</td>
                                        <td class="px-3 py-2.5 text-muted">{{ $formatPercent(((float) ($probabilities['Normal'] ?? 0)) * 100) }}%</td>
                                        <td class="px-3 py-2.5 text-muted">{{ $formatPercent(((float) ($probabilities[$label] ?? $result['confidence'] ?? 0)) * 100) }}%</td>
                                        <td class="px-3 py-2.5">
                                            <span
                                                @class([
                                                    'px-3 py-1 rounded-full text-xs font-semibold',
                                                    'bg-[rgba(248,113,113,0.18)] text-[color:var(--danger)]' => $tone === 'danger',
                                                    'bg-[rgba(34,197,94,0.18)] text-[color:var(--accent)]' => $tone === 'accent',
                                                    'bg-[rgba(251,191,36,0.18)] text-[color:var(--warning)]' => $tone === 'warning',
                                                ])
                                            >
                                                {{ $label }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="px-4 py-4 text-muted" colspan="5">Hasil deteksi belum memiliki detail baris.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($afterResultsPaginator)
                        <div class="mt-4 flex flex-col gap-3 text-sm text-muted md:flex-row md:items-center md:justify-between">
                            <div>
                                @if ($afterResultsPaginator->total() > 0)
                                    Menampilkan {{ $formatNumber($afterResultsPaginator->firstItem()) }}-{{ $formatNumber($afterResultsPaginator->lastItem()) }}
                                    dari {{ $formatNumber($afterResultsPaginator->total()) }} baris
                                @else
                                    Tidak ada baris yang cocok dengan filter.
                                @endif
                            </div>

                            @if ($afterResultsPaginator->hasPages())
                                @php
                                    $currentPage = $afterResultsPaginator->currentPage();
                                    $lastPage = $afterResultsPaginator->lastPage();
                                    $startPage = max(1, $currentPage - 2);
                                    $endPage = min($lastPage, $currentPage + 2);
                                @endphp
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($afterResultsPaginator->onFirstPage())
                                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-app opacity-40">
                                            <x-lucide-chevron-left class="h-4 w-4" />
                                        </span>
                                    @else
                                        <a href="{{ $afterResultsPaginator->previousPageUrl() }}" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-app hover:bg-surface2 transition-colors">
                                            <x-lucide-chevron-left class="h-4 w-4" />
                                        </a>
                                    @endif

                                    @for ($page = $startPage; $page <= $endPage; $page++)
                                        <a
                                            href="{{ $afterResultsPaginator->url($page) }}"
                                            @class([
                                                'inline-flex h-10 min-w-[2.5rem] items-center justify-center rounded-xl border px-3 font-semibold transition-colors',
                                                'border-transparent bg-primary text-white' => $page === $currentPage,
                                                'border-app hover:bg-surface2' => $page !== $currentPage,
                                            ])
                                        >
                                            {{ $page }}
                                        </a>
                                    @endfor

                                    @if ($afterResultsPaginator->hasMorePages())
                                        <a href="{{ $afterResultsPaginator->nextPageUrl() }}" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-app hover:bg-surface2 transition-colors">
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
                @else
                    <div class="mt-5 rounded-2xl border border-app bg-surface2 px-5 py-4 text-sm text-muted">
                        Data ini belum memiliki hasil deteksi yang selesai.
                    </div>
                @endif
            </div>
        @endif

        @if ($canManageOperational)
            <div
                x-show="deleteModalOpen"
                x-cloak
                class="fixed inset-0 z-50 grid place-items-center bg-black/45 p-4 backdrop-blur-sm"
                x-transition.opacity
            >
            <div
                @click.outside="deleteModalOpen = false"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-3 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 translate-y-2 scale-95"
                class="w-full max-w-md overflow-hidden rounded-3xl border border-app bg-surface shadow-soft"
                role="dialog"
                aria-modal="true"
            >
                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <div class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-[rgba(248,113,113,0.14)] text-[color:var(--danger)]">
                            <x-lucide-trash-2 class="h-6 w-6" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-lg font-semibold">Hapus Riwayat?</div>
                            <div class="mt-2 text-sm leading-6 text-muted">
                                Data asli dan hasil deteksi untuk
                                <span class="font-semibold text-app">{{ $scanUpload->original_name }}</span>
                                akan dihapus permanen.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-app bg-surface2 px-6 py-4">
                    <button
                        type="button"
                        @click="deleteModalOpen = false"
                        class="rounded-xl border border-app px-5 py-2 text-sm font-semibold hover:bg-surface transition-colors"
                    >
                        Batal
                    </button>
                    <form method="POST" action="{{ route('riwayat.destroy', $scanUpload) }}">
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-[color:var(--danger)] px-5 py-2 text-sm font-semibold text-white hover:opacity-90 transition-opacity"
                        >
                            <x-lucide-trash-2 class="h-4 w-4" />
                            Konfirmasi
                        </button>
                    </form>
                </div>
            </div>
            </div>
        @endif
    </div>
@endsection
