@extends('layouts.dashboard')
@section('title', 'Hasil Deteksi')

@section('page')
    @php
        $prediction = $prediction ?? null;
        $summary = $prediction['summary'] ?? [];
        $resultsPaginator = $resultsPaginator ?? null;
        $results = $resultsPaginator ? collect($resultsPaginator->items()) : collect($prediction['results'] ?? []);
        $tableFilters = $tableFilters ?? ['search' => '', 'label' => 'all', 'per_page' => 10];
        $perPageOptions = $perPageOptions ?? [10, 25, 50, 100];
        $labelOptions = collect($labelOptions ?? [])->whenEmpty(fn ($items) => collect($summary['labels'] ?? [])->keys())->whenEmpty(fn ($items) => collect(['Normal']));
        $toneForLabel = fn ($label) => $label === 'Normal' ? 'accent' : ($label === 'Unknown' ? 'warning' : 'danger');
    @endphp

    <div class="rounded-3xl bg-surface shadow-soft p-6">
        @if ($prediction)
            <div class="mb-5 grid gap-4 md:grid-cols-4">
                <div class="rounded-2xl bg-surface2 border border-app p-4">
                    <div class="text-xs text-muted">File</div>
                    <div class="mt-1 text-sm font-semibold truncate">{{ $prediction['file_name'] }}</div>
                </div>
                <div class="rounded-2xl bg-surface2 border border-app p-4">
                    <div class="text-xs text-muted">Total Diproses</div>
                    <div class="mt-1 text-2xl font-semibold">{{ number_format($prediction['total'], 0, ',', '.') }}</div>
                </div>
                <div class="rounded-2xl bg-surface2 border border-app p-4">
                    <div class="text-xs text-muted">Normal</div>
                    <div class="mt-1 text-2xl font-semibold">{{ number_format($summary['normal'] ?? 0, 0, ',', '.') }}</div>
                    <div class="text-xs text-muted">{{ number_format($summary['normal_percent'] ?? 0, 2, ',', '.') }}%</div>
                </div>
                <div class="rounded-2xl bg-surface2 border border-app p-4">
                    <div class="text-xs text-muted">Malware</div>
                    <div class="mt-1 text-2xl font-semibold">{{ number_format($summary['malware'] ?? 0, 0, ',', '.') }}</div>
                    <div class="text-xs text-muted">{{ number_format($summary['malware_percent'] ?? 0, 2, ',', '.') }}%</div>
                </div>
            </div>
        @endif

        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <form method="GET" action="{{ route('hasildeteksi.index') }}" class="grid flex-1 gap-3 md:grid-cols-[1fr_180px_140px_auto]">
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
                    class="inline-flex items-center justify-center gap-2 rounded-2xl bg-primary px-5 py-3 text-sm font-semibold text-white hover:opacity-90 transition-opacity">
                    <x-lucide-search class="h-4 w-4" />
                    Cari
                </button>
            </form>

            <div class="flex flex-wrap gap-3">
                @if (($tableFilters['search'] ?? '') !== '' || ($tableFilters['label'] ?? 'all') !== 'all')
                    <a
                        href="{{ route('hasildeteksi.index', ['per_page' => $tableFilters['per_page']]) }}"
                        class="inline-flex items-center gap-2 rounded-2xl border border-app px-5 py-3 text-sm font-semibold hover:bg-surface2 transition-colors">
                        <x-lucide-rotate-ccw class="h-4 w-4" />
                        Reset
                    </a>
                @endif

                @if ($prediction && ! empty($prediction['scan_upload_id']) && auth()->user()?->can('reports.download'))
                    <a
                        href="{{ route('riwayat.download', ['scanUpload' => $prediction['scan_upload_id'], 'mode' => 'after']) }}"
                        class="inline-flex items-center gap-2 rounded-2xl border border-app px-5 py-3 text-sm font-semibold hover:bg-surface2 transition-colors">
                        <x-lucide-download class="h-4 w-4" />
                        Export
                    </a>
                @elseif (auth()->user()?->can('reports.download'))
                    <button
                        type="button"
                        disabled
                        class="inline-flex cursor-not-allowed items-center gap-2 rounded-2xl border border-app px-5 py-3 text-sm font-semibold opacity-60">
                        <x-lucide-download class="h-4 w-4" />
                        Export
                    </button>
                @endif
            </div>
        </div>

        <div class="mt-6 overflow-auto rounded-2xl border border-app">
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
                            <td class="px-3 py-2.5 font-semibold">{{ number_format(((float) ($result['confidence'] ?? 0)) * 100, 2, ',', '.') }}%</td>
                            <td class="px-3 py-2.5 text-muted">{{ number_format(((float) ($probabilities['Normal'] ?? 0)) * 100, 2, ',', '.') }}%</td>
                            <td class="px-3 py-2.5 text-muted">{{ number_format(((float) ($probabilities[$label] ?? $result['confidence'] ?? 0)) * 100, 2, ',', '.') }}%</td>
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
                            <td class="px-4 py-4 text-muted" colspan="5">Belum ada hasil deteksi di database.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($resultsPaginator)
            <div class="mt-4 flex flex-col gap-3 text-sm text-muted md:flex-row md:items-center md:justify-between">
                <div>
                    @if ($resultsPaginator->total() > 0)
                        Menampilkan {{ number_format($resultsPaginator->firstItem(), 0, ',', '.') }}-{{ number_format($resultsPaginator->lastItem(), 0, ',', '.') }}
                        dari {{ number_format($resultsPaginator->total(), 0, ',', '.') }} baris
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
                            <a href="{{ $resultsPaginator->previousPageUrl() }}" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-app hover:bg-surface2 transition-colors">
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
                            <a href="{{ $resultsPaginator->nextPageUrl() }}" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-app hover:bg-surface2 transition-colors">
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
    </div>
@endsection
