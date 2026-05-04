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

    <div
        class="space-y-6"
        x-data="{
            deleteModalOpen: false,
            deleteFormAction: '',
            deleteFileName: '',
            openDeleteModal(action, fileName) {
                this.deleteFormAction = action;
                this.deleteFileName = fileName;
                this.deleteModalOpen = true;
            }
        }"
    >
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
            <div class="overflow-hidden rounded-2xl border border-app">
                <table class="w-full table-fixed text-sm">
                    <thead class="bg-surface2 text-muted">
                        <tr class="border-b border-app">
                            <th class="hidden w-14 px-4 py-3 text-center font-medium sm:table-cell">No</th>
                            <th class="px-4 py-3 text-center font-medium">Data Upload</th>
                            <th class="hidden w-[28%] px-4 py-3 text-left font-medium lg:table-cell">Ringkasan Deteksi</th>
                            <th class="w-28 px-4 py-3 text-left font-medium md:w-36">Status</th>
                            <th class="w-24 px-4 py-3 text-center font-medium md:w-40 ">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-app">
                        @forelse ($history as $item)
                            @php
                                $status = $item['status'] ?? '-';
                            @endphp
                            <tr class="hover:bg-surface2/50">
                                <td class="hidden px-4 py-4 align-top sm:table-cell">{{ $loop->iteration }}</td>
                                <td class="min-w-0 px-4 py-4 align-top">
                                    <div class="min-w-0">
                                        <div class="truncate font-semibold" title="{{ $item['file_name'] }}">{{ $item['file_name'] }}</div>
                                        <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted">
                                            <span>Upload: {{ $item['uploaded_at'] }}</span>
                                            <span class="hidden md:inline">Proses: {{ $item['processed_at'] }}</span>
                                            <span>{{ $formatFileSize($item['size'] ?? 0) }}</span>
                                        </div>
                                        <div class="mt-2 grid gap-1 text-xs text-muted lg:hidden">
                                            <span>Total: <span class="font-semibold text-app">{{ $formatNumber($item['total']) }}</span></span>
                                            <span>Malicious: <span class="font-semibold text-app">{{ $formatNumber($item['malicious']) }} / {{ $formatPercent($item['malicious_percent']) }}%</span></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="hidden px-4 py-4 align-top lg:table-cell">
                                    <div class="grid grid-cols-2 gap-2 text-xs">
                                        <div>
                                            <div class="text-muted">Total</div>
                                            <div class="font-semibold">{{ $formatNumber($item['total']) }}</div>
                                        </div>
                                        <div>
                                            <div class="text-muted">Confidence</div>
                                            <div class="font-semibold">{{ $formatPercent($item['average_confidence']) }}%</div>
                                        </div>
                                        <div>
                                            <div class="text-muted">Benign</div>
                                            <div class="font-semibold">{{ $formatNumber($item['benign']) }}</div>
                                        </div>
                                        <div>
                                            <div class="text-muted">Malicious</div>
                                            <div class="font-semibold">{{ $formatNumber($item['malicious']) }}</div>
                                        </div>
                                    </div>
                                </td>
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
                                <td class="px-4 py-3 align-top">
                                    <div class="flex items-center justify-end gap-2">
                                        <a
                                            href="{{ route('riwayat.show', $item['upload_id']) }}"
                                            class="inline-grid h-9 w-9 place-items-center rounded-xl border border-app text-xs font-semibold hover:bg-surface2 transition-colors md:inline-flex md:w-auto md:px-3 md:gap-1"
                                            title="Lihat detail"
                                        >
                                            <x-lucide-eye class="h-4 w-4" />
                                            <span class="hidden md:inline">Detail</span>
                                        </a>

                                        <button
                                            type="button"
                                            @click="openDeleteModal(@js(route('riwayat.destroy', $item['upload_id'])), @js($item['file_name']))"
                                            class="inline-grid h-9 w-9 place-items-center rounded-xl border border-[rgba(248,113,113,0.35)] text-xs font-semibold text-[color:var(--danger)] hover:bg-[rgba(248,113,113,0.12)] transition-colors md:inline-flex md:w-auto md:px-3 md:gap-1"
                                            title="Hapus data asli dan hasil deteksi"
                                        >
                                            <x-lucide-trash-2 class="h-4 w-4" />
                                            <span class="hidden md:inline">Hapus</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-4 text-muted" colspan="5">Belum ada riwayat upload atau proses deteksi di database.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

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
                                <span class="font-semibold text-app" x-text="deleteFileName"></span>
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
                    <form method="POST" :action="deleteFormAction">
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-[color:var(--danger)] px-5 py-2 text-sm font-semibold text-white hover:opacity-90 transition-opacity"
                        >
                            <x-lucide-trash-2 class="h-4 w-4" />
                            Hapus
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
