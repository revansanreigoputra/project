@extends('layouts.dashboard')
@section('title', 'Laporan')

@section('page')
    @php
        $stateBars = [
            ['label' => 'OTH', 'value' => '14.260', 'height' => 88],
            ['label' => 'S0', 'value' => '6.395', 'height' => 39],
            ['label' => 'S3', 'value' => '1.584', 'height' => 10],
            ['label' => 'SF', 'value' => '849', 'height' => 5],
            ['label' => 'RSTR', 'value' => '53', 'height' => 2],
            ['label' => 'S1', 'value' => '4', 'height' => 1],
        ];

        $analysis = [
            ['category' => 'Benign', 'total' => '1.923', 'percentage' => '8,31%'],
            ['category' => 'Malicious', 'total' => '21.222', 'percentage' => '91,69%'],
            ['category' => 'Total', 'total' => '23.145', 'percentage' => '100%'],
        ];
    @endphp

    <div class="space-y-6">
        <div class="grid gap-6 lg:grid-cols-12">
            <div class="rounded-3xl bg-surface shadow-soft p-6 lg:col-span-7">
                <div class="text-sm font-semibold">Ringkasan Laporan</div>

                <div class="mt-5 grid gap-4 sm:grid-cols-4">
                    <div class="rounded-2xl bg-surface2 border border-app p-4">
                        <div class="text-xs text-muted">Total Data</div>
                        <div class="mt-2 text-2xl font-semibold">23.145</div>
                    </div>

                    <div class="rounded-2xl bg-surface2 border border-app p-4">
                        <div class="text-xs text-muted">Total Benign</div>
                        <div class="mt-2 text-2xl font-semibold">1.923</div>
                        <div class="text-xs text-muted">(8,31%)</div>
                    </div>

                    <div class="rounded-2xl bg-surface2 border border-app p-4">
                        <div class="text-xs text-muted">Total Malicious</div>
                        <div class="mt-2 text-2xl font-semibold">21.222</div>
                        <div class="text-xs text-muted">(91,69%)</div>
                    </div>

                    <div class="rounded-2xl bg-surface2 border border-app p-4">
                        <div class="text-xs text-muted">Protocol Dominan</div>
                        <div class="mt-2 text-2xl font-semibold">tcp</div>
                        <div class="text-xs text-muted">92,16%</div>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl bg-surface shadow-soft p-6 lg:col-span-5">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-sm font-semibold">Distribusi Conn State</div>
                    <div class="text-xs text-muted">23.145 baris</div>
                </div>

                <div class="mt-5 flex h-40 items-end gap-3 border-b border-app pb-4">
                    @foreach ($stateBars as $bar)
                        <div class="flex min-w-0 flex-1 flex-col items-center gap-2">
                            <div class="text-[11px] font-semibold text-muted">{{ $bar['value'] }}</div>
                            <div class="w-full max-w-8 rounded-t-2xl bg-primary-soft overflow-hidden">
                                <div class="w-full rounded-t-2xl bg-primary" style="height: {{ $bar['height'] * 1.2 }}px"></div>
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
                <div class="text-sm font-semibold">Distribusi Protocol</div>

                <div class="mt-6 flex flex-col items-center gap-5 sm:flex-row">
                    <div
                        class="relative h-40 w-40 shrink-0 rounded-full"
                        style="background: conic-gradient(var(--primary) 0 92.16%, var(--accent) 92.16% 100%);"
                    >
                        <div class="absolute inset-8 rounded-full bg-surface"></div>
                    </div>

                    <div class="w-full space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2 text-muted">
                                <span class="h-3 w-3 rounded-full bg-primary"></span>
                                tcp
                            </div>
                            <div class="font-semibold">21.331 (92,16%)</div>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2 text-muted">
                                <span class="h-3 w-3 rounded-full bg-[color:var(--accent)]"></span>
                                udp
                            </div>
                            <div class="font-semibold">1.814 (7,84%)</div>
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
