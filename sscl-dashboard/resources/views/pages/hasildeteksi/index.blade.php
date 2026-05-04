@extends('layouts.dashboard')
@section('title', 'Hasil Deteksi')

@section('page')
    @php
        $prediction = $prediction ?? null;
        $results = collect($prediction['results'] ?? [])->take(100);
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
                    <div class="text-xs text-muted">Benign</div>
                    <div class="mt-1 text-2xl font-semibold">{{ number_format($prediction['summary']['benign'], 0, ',', '.') }}</div>
                </div>
                <div class="rounded-2xl bg-surface2 border border-app p-4">
                    <div class="text-xs text-muted">Malicious</div>
                    <div class="mt-1 text-2xl font-semibold">{{ number_format($prediction['summary']['malicious'], 0, ',', '.') }}</div>
                </div>
            </div>
        @endif

        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="relative flex-1">
                <x-lucide-search class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" />
                <input
                    type="text"
                    placeholder="Cari row, label, atau confidence..."
                    class="w-full rounded-2xl border border-app bg-surface2 px-12 py-3 text-sm focus:border-[color:var(--primary)] focus:ring-2 focus:ring-[color:var(--primary-soft)]"
                >
            </div>

            <div class="flex flex-wrap gap-3">
                <select class="rounded-2xl border border-app bg-surface2 px-4 py-3 text-sm focus:border-[color:var(--primary)] focus:ring-2 focus:ring-[color:var(--primary-soft)]">
                    <option>Semua Label</option>
                    <option>Benign</option>
                    <option>Malicious</option>
                </select>

                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-2xl border border-app px-5 py-3 text-sm font-semibold hover:bg-surface2 transition-colors">
                    <x-lucide-download class="h-4 w-4" />
                    Export
                </button>
            </div>
        </div>

        <div class="mt-6 overflow-x-auto rounded-2xl border border-app">
            <table class="w-full text-sm">
                <thead class="bg-surface2 text-muted">
                    <tr class="border-b border-app">
                        <th class="px-4 py-3 text-left font-medium">Row ID</th>
                        <th class="px-4 py-3 text-left font-medium">Confidence</th>
                        <th class="px-4 py-3 text-left font-medium">Prob. Benign</th>
                        <th class="px-4 py-3 text-left font-medium">Prob. Malicious</th>
                        <th class="px-4 py-3 text-left font-medium">Label</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-app">
                    @forelse ($results as $result)
                        @php
                            $label = $result['label'] ?? 'Unknown';
                            $tone = $label === 'Malicious' ? 'danger' : 'accent';
                        @endphp
                        <tr class="hover:bg-surface2/50">
                            <td class="px-4 py-3">{{ $result['row'] ?? $loop->index }}</td>
                            <td class="px-4 py-3 font-semibold">{{ number_format(((float) ($result['confidence'] ?? 0)) * 100, 2, ',', '.') }}%</td>
                            <td class="px-4 py-3 text-muted">{{ number_format(((float) data_get($result, 'probabilities.Benign', 0)) * 100, 2, ',', '.') }}%</td>
                            <td class="px-4 py-3 text-muted">{{ number_format(((float) data_get($result, 'probabilities.Malicious', 0)) * 100, 2, ',', '.') }}%</td>
                            <td class="px-4 py-3">
                                <span
                                    @class([
                                        'px-3 py-1 rounded-full text-xs font-semibold',
                                        'bg-[rgba(248,113,113,0.18)] text-[color:var(--danger)]' => $tone === 'danger',
                                        'bg-[rgba(34,197,94,0.18)] text-[color:var(--accent)]' => $tone === 'accent',
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
    </div>
@endsection
