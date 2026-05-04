@extends('layouts.dashboard')
@section('title', 'Laporan')

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
        $benign = (int) ($summary['benign'] ?? 0);
        $malicious = (int) ($summary['malicious'] ?? 0);
        $benignPercent = (float) ($summary['benign_percent'] ?? 0);
        $maliciousPercent = (float) ($summary['malicious_percent'] ?? 0);
        $avgConfidence = (float) ($summary['average_confidence'] ?? 0);
        $maxLabelCount = max($benign, $malicious, 1);
        $formatNumber = fn ($value) => number_format((float) $value, 0, ',', '.');
        $formatPercent = fn ($value) => number_format((float) $value, 2, ',', '.');

        $labelBars = [
            ['label' => 'Benign', 'value' => $formatNumber($benign), 'height' => max(8, round(($benign / $maxLabelCount) * 88)), 'tone' => 'accent'],
            ['label' => 'Malicious', 'value' => $formatNumber($malicious), 'height' => max(8, round(($malicious / $maxLabelCount) * 88)), 'tone' => 'danger'],
        ];

        $analysis = [
            ['category' => 'Benign', 'total' => $formatNumber($benign), 'percentage' => $formatPercent($benignPercent) . '%'],
            ['category' => 'Malicious', 'total' => $formatNumber($malicious), 'percentage' => $formatPercent($maliciousPercent) . '%'],
            ['category' => 'Total', 'total' => $formatNumber($total), 'percentage' => '100%'],
        ];
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
                        <div class="text-xs text-muted">Total Benign</div>
                        <div class="mt-2 text-2xl font-semibold">{{ $formatNumber($benign) }}</div>
                        <div class="text-xs text-muted">({{ $formatPercent($benignPercent) }}%)</div>
                    </div>

                    <div class="rounded-2xl bg-surface2 border border-app p-4">
                        <div class="text-xs text-muted">Total Malicious</div>
                        <div class="mt-2 text-2xl font-semibold">{{ $formatNumber($malicious) }}</div>
                        <div class="text-xs text-muted">({{ $formatPercent($maliciousPercent) }}%)</div>
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

                <div class="mt-5 overflow-x-auto rounded-2xl border border-app">
                    <table class="w-full text-sm">
                        <thead class="bg-surface2 text-muted">
                            <tr class="border-b border-app">
                                <th class="px-4 py-3 text-left font-medium">Kategori</th>
                                <th class="px-4 py-3 text-left font-medium">Jumlah</th>
                                <th class="px-4 py-3 text-left font-medium">Persentase</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-app">
                            @foreach ($analysis as $item)
                                <tr class="hover:bg-surface2/50">
                                    <td class="px-4 py-3 font-semibold">{{ $item['category'] }}</td>
                                    <td class="px-4 py-3 text-muted">{{ $item['total'] }}</td>
                                    <td class="px-4 py-3 text-muted">{{ $item['percentage'] }}</td>
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
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <button
                type="button"
                class="inline-flex items-center justify-center gap-3 rounded-2xl bg-primary px-6 py-4 text-sm font-semibold text-white hover:opacity-90 transition-opacity">
                <x-lucide-file-down class="h-5 w-5" />
                Unduh PDF
            </button>

            <button
                type="button"
                class="inline-flex items-center justify-center gap-3 rounded-2xl border border-app px-6 py-4 text-sm font-semibold hover:bg-surface2 transition-colors">
                <x-lucide-table class="h-5 w-5" />
                Unduh CSV
            </button>
        </div>
    </div>
@endsection
