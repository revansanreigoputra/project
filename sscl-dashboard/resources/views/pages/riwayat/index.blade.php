@extends('layouts.dashboard')
@section('title', 'Riwayat')

@section('page')
    @php
        $history = collect($history ?? []);
        $filters = array_merge([
            'start_date' => '',
            'end_date' => '',
            'detection_status' => 'all',
        ], $filters ?? []);
        $latest = $history->first();
        $formatNumber = fn ($value) => number_format((float) $value, 0, ',', '.');
        $formatPercent = fn ($value) => number_format((float) $value, 2, ',', '.');
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
    @endphp

    <div class="space-y-6">
        <div class="grid gap-6 lg:grid-cols-12">
            <div class="rounded-3xl bg-surface shadow-soft p-6 lg:col-span-8">
                <div class="text-sm font-semibold">Filter Riwayat</div>

                <form method="GET" action="{{ route('riwayat.index') }}" class="mt-4 grid gap-4 md:grid-cols-[1fr_1fr_1fr_auto_auto] md:items-end">
                    <label class="block">
                        <span class="text-xs text-muted">Dari tanggal</span>
                        <input
                            type="date"
                            name="start_date"
                            value="{{ $filters['start_date'] }}"
                            class="mt-2 w-full rounded-2xl border border-app bg-surface2 px-4 py-3 text-sm focus:border-[color:var(--primary)] focus:ring-2 focus:ring-[color:var(--primary-soft)]"
                        >
                    </label>

                    <label class="block">
                        <span class="text-xs text-muted">Sampai tanggal</span>
                        <input
                            type="date"
                            name="end_date"
                            value="{{ $filters['end_date'] }}"
                            class="mt-2 w-full rounded-2xl border border-app bg-surface2 px-4 py-3 text-sm focus:border-[color:var(--primary)] focus:ring-2 focus:ring-[color:var(--primary-soft)]"
                        >
                    </label>

                    <label class="block">
                        <span class="text-xs text-muted">Status data</span>
                        <select
                            name="detection_status"
                            class="mt-2 w-full rounded-2xl border border-app bg-surface2 px-4 py-3 text-sm focus:border-[color:var(--primary)] focus:ring-2 focus:ring-[color:var(--primary-soft)]"
                        >
                            <option value="all" @selected($filters['detection_status'] === 'all')>Semua Data</option>
                            <option value="detected" @selected($filters['detection_status'] === 'detected')>Sudah Dideteksi</option>
                            <option value="undetected" @selected($filters['detection_status'] === 'undetected')>Belum Dideteksi</option>
                        </select>
                    </label>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-2xl bg-primary px-5 py-3 text-sm font-semibold text-white hover:opacity-90 transition-opacity">
                        <x-lucide-filter class="h-4 w-4" />
                        Terapkan
                    </button>

                    <a
                        href="{{ route('riwayat.index') }}"
                        class="inline-flex items-center justify-center gap-2 rounded-2xl border border-app px-5 py-3 text-sm font-semibold hover:bg-surface2 transition-colors">
                        <x-lucide-rotate-ccw class="h-4 w-4" />
                        Reset
                    </a>
                </form>

                @error('end_date')
                    <div class="mt-3 text-xs font-semibold text-[color:var(--danger)]">{{ $message }}</div>
                @enderror
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
                            <span class="font-semibold">{{ $formatNumber($totalUploads ?? $history->count()) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted">Data Terakhir</span>
                            <span class="font-semibold">{{ $formatNumber($latest['total'] ?? 0) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted">Malicious Terakhir</span>
                            <span class="font-semibold">{{ $formatPercent($latest['malicious_percent'] ?? 0) }}%</span>
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
                            <th class="px-4 py-3 text-left font-medium">Waktu Upload</th>
                            <th class="px-4 py-3 text-left font-medium">Waktu Proses</th>
                            <th class="px-4 py-3 text-left font-medium">Nama File</th>
                            <th class="px-4 py-3 text-left font-medium">Ukuran</th>
                            <th class="px-4 py-3 text-left font-medium">Total Data</th>
                            <th class="px-4 py-3 text-left font-medium">Benign</th>
                            <th class="px-4 py-3 text-left font-medium">Malicious</th>
                            <th class="px-4 py-3 text-left font-medium">Avg. Confidence</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                            <th class="px-4 py-3 text-left font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-app">
                        @forelse ($history as $item)
                            @php
                                $status = $item['status'] ?? '-';
                            @endphp
                            <tr class="hover:bg-surface2/50">
                                <td class="px-4 py-3">{{ $loop->iteration }}</td>
                                <td class="px-4 py-3 text-muted whitespace-nowrap">{{ $item['uploaded_at'] }}</td>
                                <td class="px-4 py-3 text-muted whitespace-nowrap">{{ $item['processed_at'] }}</td>
                                <td class="px-4 py-3 text-muted whitespace-nowrap">{{ $item['file_name'] }}</td>
                                <td class="px-4 py-3 text-muted whitespace-nowrap">{{ $formatFileSize($item['size'] ?? 0) }}</td>
                                <td class="px-4 py-3">{{ $formatNumber($item['total']) }}</td>
                                <td class="px-4 py-3 text-muted">{{ $formatNumber($item['benign']) }}</td>
                                <td class="px-4 py-3 text-muted">{{ $formatNumber($item['malicious']) }}</td>
                                <td class="px-4 py-3 text-muted">{{ $formatPercent($item['average_confidence']) }}%</td>
                                <td class="px-4 py-3">
                                    <span
                                        @class([
                                            'px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap',
                                            'bg-[rgba(34,197,94,0.18)] text-[color:var(--accent)]' => $status === 'Selesai',
                                            'bg-[rgba(248,113,113,0.18)] text-[color:var(--danger)]' => $status === 'Gagal',
                                            'bg-[rgba(251,191,36,0.18)] text-[color:var(--warning)]' => ! in_array($status, ['Selesai', 'Gagal'], true),
                                        ])
                                    >
                                        {{ $status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a
                                            href="{{ route('riwayat.show', ['scanUpload' => $item['upload_id'], 'mode' => 'before']) }}"
                                            class="inline-flex items-center gap-1 rounded-xl border border-app px-3 py-2 text-xs font-semibold hover:bg-surface2 transition-colors"
                                            title="Lihat data sebelum deteksi"
                                        >
                                            <x-lucide-eye class="h-4 w-4" />
                                            Sebelum
                                        </a>

                                        <a
                                            href="{{ route('riwayat.download', ['scanUpload' => $item['upload_id'], 'mode' => 'before']) }}"
                                            class="inline-flex items-center gap-1 rounded-xl border border-app px-3 py-2 text-xs font-semibold hover:bg-surface2 transition-colors"
                                            title="Download data sebelum deteksi"
                                        >
                                            <x-lucide-download class="h-4 w-4" />
                                            Asli
                                        </a>

                                        @if ($item['can_view_after'])
                                            <a
                                                href="{{ route('riwayat.show', ['scanUpload' => $item['upload_id'], 'mode' => 'after']) }}"
                                                class="inline-flex items-center gap-1 rounded-xl border border-app px-3 py-2 text-xs font-semibold hover:bg-surface2 transition-colors"
                                                title="Lihat data sesudah deteksi"
                                            >
                                                <x-lucide-list-checks class="h-4 w-4" />
                                                Sesudah
                                            </a>

                                            <a
                                                href="{{ route('riwayat.download', ['scanUpload' => $item['upload_id'], 'mode' => 'after']) }}"
                                                class="inline-flex items-center gap-1 rounded-xl border border-app px-3 py-2 text-xs font-semibold hover:bg-surface2 transition-colors"
                                                title="Download hasil deteksi"
                                            >
                                                <x-lucide-file-down class="h-4 w-4" />
                                                Hasil
                                            </a>
                                        @else
                                            <span
                                                class="inline-flex cursor-not-allowed items-center gap-1 rounded-xl border border-app px-3 py-2 text-xs font-semibold text-muted opacity-60"
                                                title="Data ini belum memiliki hasil deteksi"
                                            >
                                                <x-lucide-list-checks class="h-4 w-4" />
                                                Sesudah
                                            </span>

                                            <span
                                                class="inline-flex cursor-not-allowed items-center gap-1 rounded-xl border border-app px-3 py-2 text-xs font-semibold text-muted opacity-60"
                                                title="Hasil deteksi belum tersedia"
                                            >
                                                <x-lucide-file-down class="h-4 w-4" />
                                                Hasil
                                            </span>
                                        @endif

                                        <form
                                            method="POST"
                                            action="{{ route('riwayat.destroy', $item['upload_id']) }}"
                                            onsubmit="return confirm('Hapus data asli dan hasil deteksi untuk file ini?')"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="inline-flex items-center gap-1 rounded-xl border border-[rgba(248,113,113,0.35)] px-3 py-2 text-xs font-semibold text-[color:var(--danger)] hover:bg-[rgba(248,113,113,0.12)] transition-colors"
                                                title="Hapus data asli dan hasil deteksi"
                                            >
                                                <x-lucide-trash-2 class="h-4 w-4" />
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-4 text-muted" colspan="11">Belum ada riwayat upload atau proses deteksi di database.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
