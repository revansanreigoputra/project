@extends('layouts.dashboard')
@section('title', 'Laporan')

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
        ];
        $total = $prediction['total'] ?? 0;
        $fileName = $prediction['file_name'] ?? 'Belum ada file diproses';
        $normal = (int) ($summary['normal'] ?? 0);
        $malware = (int) ($summary['malware'] ?? 0);
        $normalPercent = (float) ($summary['normal_percent'] ?? 0);
        $malwarePercent = (float) ($summary['malware_percent'] ?? 0);
        $avgConfidence = (float) ($summary['average_confidence'] ?? 0);
        $formatNumber = fn ($value) => number_format((float) $value, 0, ',', '.');
        $formatPercent = fn ($value) => number_format((float) $value, 2, ',', '.');
        $toneForLabel = fn ($label) => $label === 'Normal' ? 'accent' : ($label === 'Unknown' ? 'warning' : 'danger');
        $labelCounts = collect($summary['labels'] ?? [])->whenEmpty(fn ($items) => collect([
            'Normal' => $normal,
            'Malware' => $malware,
        ])->filter());
        $maxLabelCount = max($labelCounts->max() ?: 0, 1);

        $labelBars = $labelCounts
            ->sortByDesc(fn ($count, $label) => $label === 'Normal' ? PHP_INT_MAX : $count)
            ->take(7)
            ->map(fn ($count, $label) => [
                'label' => $label,
                'value' => $formatNumber($count),
                'height' => max(8, round(((int) $count / $maxLabelCount) * 88)),
                'tone' => $toneForLabel($label),
            ])
            ->values();

        $analysis = $labelCounts
            ->map(fn ($count, $label) => [
                'category' => $label,
                'total' => $formatNumber($count),
                'percentage' => $total > 0 ? $formatPercent(((int) $count / $total) * 100) . '%' : '0%',
            ])
            ->values()
            ->push(['category' => 'Total', 'total' => $formatNumber($total), 'percentage' => $total > 0 ? '100%' : '0%']);
    @endphp

    <div class="space-y-6">
        <div class="grid gap-6 lg:grid-cols-12">
            <div class="rounded-3xl bg-surface shadow-soft p-6 lg:col-span-7">
                <div class="flex flex-col gap-1 md:flex-row md:items-center md:justify-between">
                    <div class="text-sm font-semibold">Ringkasan Laporan</div>
                    <div class="text-xs text-muted">{{ $fileName }}</div>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-4">
                    <div class="rounded-2xl bg-surface2 border border-app p-4">
                        <div class="text-xs text-muted">Total Data</div>
                        <div class="mt-2 text-2xl font-semibold">{{ $formatNumber($total) }}</div>
                    </div>

                    <div class="rounded-2xl bg-surface2 border border-app p-4">
                        <div class="text-xs text-muted">Total Normal</div>
                        <div class="mt-2 text-2xl font-semibold">{{ $formatNumber($normal) }}</div>
                        <div class="text-xs text-muted">({{ $formatPercent($normalPercent) }}%)</div>
                    </div>

                    <div class="rounded-2xl bg-surface2 border border-app p-4">
                        <div class="text-xs text-muted">Total Malware</div>
                        <div class="mt-2 text-2xl font-semibold">{{ $formatNumber($malware) }}</div>
                        <div class="text-xs text-muted">({{ $formatPercent($malwarePercent) }}%)</div>
                    </div>

                    <div class="rounded-2xl bg-surface2 border border-app p-4">
                        <div class="text-xs text-muted">Avg. Confidence</div>
                        <div class="mt-2 text-2xl font-semibold">{{ $formatPercent($avgConfidence) }}%</div>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl bg-surface shadow-soft p-6 lg:col-span-5">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-sm font-semibold">Distribusi Prediksi</div>
                    <div class="text-xs text-muted">{{ $formatNumber($total) }} baris</div>
                </div>

                <div class="mt-5 flex h-40 items-end gap-6 border-b border-app pb-4">
                    @foreach ($labelBars as $bar)
                        <div class="flex min-w-0 flex-1 flex-col items-center gap-2">
                            <div class="text-[11px] font-semibold text-muted">{{ $bar['value'] }}</div>
                            <div class="w-full max-w-16 rounded-t-2xl bg-primary-soft overflow-hidden">
                                <div
                                    @class([
                                        'w-full rounded-t-2xl',
                                        'bg-[color:var(--accent)]' => $bar['tone'] === 'accent',
                                        'bg-[color:var(--danger)]' => $bar['tone'] === 'danger',
                                        'bg-[color:var(--warning)]' => $bar['tone'] === 'warning',
                                    ])
                                    style="height: {{ $bar['height'] * 1.2 }}px"
                                ></div>
                            </div>
                            <div class="text-[11px] text-muted whitespace-nowrap">{{ $bar['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-12">
            <div class="rounded-3xl bg-surface shadow-soft p-6 lg:col-span-7">
                <div class="text-sm font-semibold">Ringkasan Hasil Analisis</div>

                <div class="mt-5 max-h-[280px] overflow-auto rounded-2xl border border-app">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-surface2 text-muted">
                            <tr class="border-b border-app">
                                <th class="px-3 py-2.5 text-left font-medium">Kategori</th>
                                <th class="px-3 py-2.5 text-left font-medium">Jumlah</th>
                                <th class="px-3 py-2.5 text-left font-medium">Persentase</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-app">
                            @foreach ($analysis as $item)
                                <tr class="hover:bg-surface2/50">
                                    <td class="px-3 py-2.5 font-semibold">{{ $item['category'] }}</td>
                                    <td class="px-3 py-2.5 text-muted">{{ $item['total'] }}</td>
                                    <td class="px-3 py-2.5 text-muted">{{ $item['percentage'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-3xl bg-surface shadow-soft p-6 lg:col-span-5">
                <div class="text-sm font-semibold">Rasio Label</div>

                <div class="mt-6 flex flex-col items-center gap-5 sm:flex-row">
                    <div
                        class="relative h-40 w-40 shrink-0 rounded-full"
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
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <button
                type="button"
                disabled
                class="inline-flex cursor-not-allowed items-center justify-center gap-3 rounded-2xl bg-primary px-6 py-4 text-sm font-semibold text-white opacity-60">
                <x-lucide-file-down class="h-5 w-5" />
                Unduh PDF
            </button>

            @if ($prediction && ! empty($prediction['scan_upload_id']) && auth()->user()?->can('reports.download'))
                <a
                    href="{{ route('riwayat.download', ['scanUpload' => $prediction['scan_upload_id'], 'mode' => 'after']) }}"
                    class="inline-flex items-center justify-center gap-3 rounded-2xl border border-app px-6 py-4 text-sm font-semibold hover:bg-surface2 transition-colors">
                    <x-lucide-table class="h-5 w-5" />
                    Unduh CSV
                </a>
            @elseif (auth()->user()?->can('reports.download'))
                <button
                    type="button"
                    disabled
                    class="inline-flex cursor-not-allowed items-center justify-center gap-3 rounded-2xl border border-app px-6 py-4 text-sm font-semibold opacity-60">
                    <x-lucide-table class="h-5 w-5" />
                    Unduh CSV
                </button>
            @endif
        </div>
    </div>
@endsection
