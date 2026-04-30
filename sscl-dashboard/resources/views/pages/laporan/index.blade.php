@extends('layouts.dashboard')
@section('title', 'Laporan')

@section('page')
    @php
        $trendBars = [
            ['date' => '14 Mei', 'height' => 46],
            ['date' => '15 Mei', 'height' => 66],
            ['date' => '16 Mei', 'height' => 43],
            ['date' => '17 Mei', 'height' => 62],
            ['date' => '18 Mei', 'height' => 39],
            ['date' => '19 Mei', 'height' => 61],
            ['date' => '20 Mei', 'height' => 45],
        ];

        $analysis = [
            ['category' => 'Normal', 'total' => '98.765', 'percentage' => '78,75%'],
            ['category' => 'Malware', 'total' => '26.665', 'percentage' => '21,25%'],
            ['category' => 'Total', 'total' => '125.430', 'percentage' => '100%'],
        ];
    @endphp

    <div class="space-y-6">
        <div class="grid gap-6 lg:grid-cols-12">
            <div class="rounded-3xl bg-surface shadow-soft p-6 lg:col-span-7">
                <div class="text-sm font-semibold">Ringkasan Laporan</div>

                <div class="mt-5 grid gap-4 sm:grid-cols-4">
                    <div class="rounded-2xl bg-surface2 border border-app p-4">
                        <div class="text-xs text-muted">Total Data</div>
                        <div class="mt-2 text-2xl font-semibold">125.430</div>
                    </div>

                    <div class="rounded-2xl bg-surface2 border border-app p-4">
                        <div class="text-xs text-muted">Total Normal</div>
                        <div class="mt-2 text-2xl font-semibold">98.765</div>
                        <div class="text-xs text-muted">(78,75%)</div>
                    </div>

                    <div class="rounded-2xl bg-surface2 border border-app p-4">
                        <div class="text-xs text-muted">Total Malware</div>
                        <div class="mt-2 text-2xl font-semibold">26.665</div>
                        <div class="text-xs text-muted">(21,25%)</div>
                    </div>

                    <div class="rounded-2xl bg-surface2 border border-app p-4">
                        <div class="text-xs text-muted">Rata-rata Confidence</div>
                        <div class="mt-2 text-2xl font-semibold">93,4%</div>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl bg-surface shadow-soft p-6 lg:col-span-5">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-sm font-semibold">Tren Deteksi Harian</div>
                    <div class="text-xs text-muted">14-20 Mei</div>
                </div>

                <div class="mt-5 flex h-40 items-end gap-3 border-b border-app pb-4">
                    @foreach ($trendBars as $bar)
                        <div class="flex min-w-0 flex-1 flex-col items-center gap-2">
                            <div class="w-full max-w-8 rounded-t-2xl bg-primary-soft overflow-hidden">
                                <div class="w-full rounded-t-2xl bg-primary" style="height: {{ $bar['height'] * 1.4 }}px"></div>
                            </div>
                            <div class="text-[11px] text-muted whitespace-nowrap">{{ $bar['date'] }}</div>
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
                <div class="text-sm font-semibold">Distribusi Protocol</div>

                <div class="mt-6 flex flex-col items-center gap-5 sm:flex-row">
                    <div
                        class="relative h-40 w-40 shrink-0 rounded-full"
                        style="background: conic-gradient(var(--primary) 0 60%, var(--accent) 60% 90%, var(--warning) 90% 100%);"
                    >
                        <div class="absolute inset-8 rounded-full bg-surface"></div>
                    </div>

                    <div class="w-full space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2 text-muted">
                                <span class="h-3 w-3 rounded-full bg-primary"></span>
                                TCP
                            </div>
                            <div class="font-semibold">60%</div>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2 text-muted">
                                <span class="h-3 w-3 rounded-full bg-[color:var(--accent)]"></span>
                                UDP
                            </div>
                            <div class="font-semibold">30%</div>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2 text-muted">
                                <span class="h-3 w-3 rounded-full bg-[color:var(--warning)]"></span>
                                ICMP
                            </div>
                            <div class="font-semibold">10%</div>
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
