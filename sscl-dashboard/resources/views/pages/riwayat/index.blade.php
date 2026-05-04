@extends('layouts.dashboard')
@section('title', 'Riwayat')

@section('page')
    @php
        $history = session('scan_history', []);

        if (empty($history)) {
            $history = [[
                'processed_at' => '23/04/2026 20:29',
                'file_name' => 'conn.log.labeled_cleaned.xlsx',
                'total' => 23145,
                'benign' => 1923,
                'malicious' => 21222,
                'benign_percent' => 8.31,
                'malicious_percent' => 91.69,
                'average_confidence' => 0,
                'status' => 'Selesai',
            ]];
        }

        $latest = $history[0];
        $formatNumber = fn ($value) => number_format((float) $value, 0, ',', '.');
        $formatPercent = fn ($value) => number_format((float) $value, 2, ',', '.');
    @endphp

    <div class="space-y-6">
        <div class="grid gap-6 lg:grid-cols-12">
            <div class="rounded-3xl bg-surface shadow-soft p-6 lg:col-span-8">
                <div class="text-sm font-semibold">Filter Tanggal</div>

                <div class="mt-4 grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
                    <label class="block">
                        <span class="text-xs text-muted">Dari tanggal</span>
                        <input
                            type="date"
                            value="{{ now('Asia/Jakarta')->format('Y-m-d') }}"
                            class="mt-2 w-full rounded-2xl border border-app bg-surface2 px-4 py-3 text-sm focus:border-[color:var(--primary)] focus:ring-2 focus:ring-[color:var(--primary-soft)]"
                        >
                    </label>

                    <label class="block">
                        <span class="text-xs text-muted">Sampai tanggal</span>
                        <input
                            type="date"
                            value="{{ now('Asia/Jakarta')->format('Y-m-d') }}"
                            class="mt-2 w-full rounded-2xl border border-app bg-surface2 px-4 py-3 text-sm focus:border-[color:var(--primary)] focus:ring-2 focus:ring-[color:var(--primary-soft)]"
                        >
                    </label>

                    <button
                        type="button"
                        class="rounded-2xl bg-primary px-6 py-3 text-sm font-semibold text-white hover:opacity-90 transition-opacity">
                        Terapkan
                    </button>
                </div>
            </div>

            <div class="rounded-3xl bg-surface shadow-soft p-6 lg:col-span-4">
                <div class="text-sm font-semibold">Ringkasan</div>

                <div class="mt-4 flex items-center gap-4">
                    <div class="h-14 w-14 rounded-2xl bg-primary-soft grid place-items-center">
                        <x-lucide-file-text class="h-7 w-7 text-primary" />
                    </div>

                    <div class="flex-1 space-y-2 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted">Total Upload</span>
                            <span class="font-semibold">{{ count($history) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted">Data Terakhir</span>
                            <span class="font-semibold">{{ $formatNumber($latest['total']) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted">Malicious Terakhir</span>
                            <span class="font-semibold">{{ $formatPercent($latest['malicious_percent']) }}%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-3xl bg-surface shadow-soft p-6">
            <div class="overflow-x-auto rounded-2xl border border-app">
                <table class="w-full text-sm">
                    <thead class="bg-surface2 text-muted">
                        <tr class="border-b border-app">
                            <th class="px-4 py-3 text-left font-medium">No</th>
                            <th class="px-4 py-3 text-left font-medium">Waktu Proses</th>
                            <th class="px-4 py-3 text-left font-medium">Nama File</th>
                            <th class="px-4 py-3 text-left font-medium">Total Data</th>
                            <th class="px-4 py-3 text-left font-medium">Benign</th>
                            <th class="px-4 py-3 text-left font-medium">Malicious</th>
                            <th class="px-4 py-3 text-left font-medium">Avg. Confidence</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-app">
                        @foreach ($history as $item)
                            <tr class="hover:bg-surface2/50">
                                <td class="px-4 py-3">{{ $loop->iteration }}</td>
                                <td class="px-4 py-3 text-muted whitespace-nowrap">{{ $item['processed_at'] }}</td>
                                <td class="px-4 py-3 text-muted whitespace-nowrap">{{ $item['file_name'] }}</td>
                                <td class="px-4 py-3">{{ $formatNumber($item['total']) }}</td>
                                <td class="px-4 py-3 text-muted">{{ $formatNumber($item['benign']) }}</td>
                                <td class="px-4 py-3 text-muted">{{ $formatNumber($item['malicious']) }}</td>
                                <td class="px-4 py-3 text-muted">{{ $formatPercent($item['average_confidence']) }}%</td>
                                <td class="px-4 py-3">
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-[rgba(34,197,94,0.18)] text-[color:var(--accent)]">
                                        {{ $item['status'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
