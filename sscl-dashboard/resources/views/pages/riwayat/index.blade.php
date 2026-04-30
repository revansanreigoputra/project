@extends('layouts.dashboard')
@section('title', 'Riwayat')

@section('page')
    @php
        $histories = [
            ['date' => '20 Mei 2025 10:00', 'file' => 'trafik_20052025.csv', 'total' => '25.430', 'normal' => '19.200', 'malware' => '6.230'],
            ['date' => '19 Mei 2025 09:45', 'file' => 'trafik_19052025.csv', 'total' => '18.750', 'normal' => '14.900', 'malware' => '3.850'],
            ['date' => '18 Mei 2025 09:30', 'file' => 'trafik_18052025.csv', 'total' => '21.300', 'normal' => '16.800', 'malware' => '4.500'],
            ['date' => '17 Mei 2025 11:20', 'file' => 'trafik_17052025.csv', 'total' => '15.980', 'normal' => '12.600', 'malware' => '3.380'],
            ['date' => '16 Mei 2025 10:10', 'file' => 'trafik_16052025.csv', 'total' => '12.450', 'normal' => '9.850', 'malware' => '2.600'],
        ];
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
                            value="2025-05-01"
                            class="mt-2 w-full rounded-2xl border border-app bg-surface2 px-4 py-3 text-sm focus:border-[color:var(--primary)] focus:ring-2 focus:ring-[color:var(--primary-soft)]"
                        >
                    </label>

                    <label class="block">
                        <span class="text-xs text-muted">Sampai tanggal</span>
                        <input
                            type="date"
                            value="2025-05-20"
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
                            <span class="font-semibold">12</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted">Total Data</span>
                            <span class="font-semibold">125.430</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted">Rata-rata Malware</span>
                            <span class="font-semibold">21,25%</span>
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
                            <th class="px-4 py-3 text-left font-medium">Tanggal</th>
                            <th class="px-4 py-3 text-left font-medium">Nama File</th>
                            <th class="px-4 py-3 text-left font-medium">Total Data</th>
                            <th class="px-4 py-3 text-left font-medium">Normal</th>
                            <th class="px-4 py-3 text-left font-medium">Malware</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                            <th class="px-4 py-3 text-left font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-app">
                        @foreach ($histories as $history)
                            <tr class="hover:bg-surface2/50">
                                <td class="px-4 py-3">{{ $loop->iteration }}</td>
                                <td class="px-4 py-3 text-muted whitespace-nowrap">{{ $history['date'] }}</td>
                                <td class="px-4 py-3 text-muted whitespace-nowrap">{{ $history['file'] }}</td>
                                <td class="px-4 py-3">{{ $history['total'] }}</td>
                                <td class="px-4 py-3 text-muted">{{ $history['normal'] }}</td>
                                <td class="px-4 py-3 text-muted">{{ $history['malware'] }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-[rgba(34,197,94,0.18)] text-[color:var(--accent)]">
                                        Selesai
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <button
                                        type="button"
                                        class="grid h-9 w-9 place-items-center rounded-xl border border-app hover:bg-surface2 transition-colors"
                                        aria-label="Lihat riwayat">
                                        <x-lucide-eye class="h-4 w-4 text-muted" />
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                        <tr class="hover:bg-surface2/50">
                            <td class="px-4 py-3 text-muted">...</td>
                            <td class="px-4 py-3 text-muted">...</td>
                            <td class="px-4 py-3 text-muted">...</td>
                            <td class="px-4 py-3 text-muted">...</td>
                            <td class="px-4 py-3 text-muted">...</td>
                            <td class="px-4 py-3 text-muted">...</td>
                            <td class="px-4 py-3 text-muted">...</td>
                            <td class="px-4 py-3 text-muted">...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
