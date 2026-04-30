@extends('layouts.dashboard')
@section('title', 'Hasil Deteksi')

@section('page')
    @php
        $results = [
            ['source' => '192.168.1.10', 'destination' => '10.0.0.5', 'protocol' => 'TCP', 'result' => 'Malware', 'confidence' => '96%', 'tone' => 'danger'],
            ['source' => '10.0.0.5', 'destination' => '8.8.8.8', 'protocol' => 'UDP', 'result' => 'Normal', 'confidence' => '92%', 'tone' => 'accent'],
            ['source' => '172.16.5.23', 'destination' => '192.168.1.15', 'protocol' => 'TCP', 'result' => 'Malware', 'confidence' => '95%', 'tone' => 'danger'],
            ['source' => '192.168.1.20', 'destination' => '1.1.1.1', 'protocol' => 'UDP', 'result' => 'Normal', 'confidence' => '90%', 'tone' => 'accent'],
            ['source' => '10.0.0.12', 'destination' => '172.16.5.10', 'protocol' => 'TCP', 'result' => 'Malware', 'confidence' => '97%', 'tone' => 'danger'],
            ['source' => '192.168.1.25', 'destination' => '8.8.4.4', 'protocol' => 'UDP', 'result' => 'Normal', 'confidence' => '91%', 'tone' => 'accent'],
        ];
    @endphp

    <div class="rounded-3xl bg-surface shadow-soft p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="relative flex-1">
                <x-lucide-search class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" />
                <input
                    type="text"
                    placeholder="Cari IP, Protocol, atau Hasil Deteksi..."
                    class="w-full rounded-2xl border border-app bg-surface2 px-12 py-3 text-sm focus:border-[color:var(--primary)] focus:ring-2 focus:ring-[color:var(--primary-soft)]"
                >
            </div>

            <div class="flex flex-wrap gap-3">
                <select class="rounded-2xl border border-app bg-surface2 px-4 py-3 text-sm focus:border-[color:var(--primary)] focus:ring-2 focus:ring-[color:var(--primary-soft)]">
                    <option>Semua Hasil</option>
                    <option>Malware</option>
                    <option>Normal</option>
                </select>

                <select class="rounded-2xl border border-app bg-surface2 px-4 py-3 text-sm focus:border-[color:var(--primary)] focus:ring-2 focus:ring-[color:var(--primary-soft)]">
                    <option>Semua Protocol</option>
                    <option>TCP</option>
                    <option>UDP</option>
                    <option>ICMP</option>
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
                        <th class="px-4 py-3 text-left font-medium">No</th>
                        <th class="px-4 py-3 text-left font-medium">Source IP</th>
                        <th class="px-4 py-3 text-left font-medium">Destination IP</th>
                        <th class="px-4 py-3 text-left font-medium">Protocol</th>
                        <th class="px-4 py-3 text-left font-medium">Hasil Deteksi</th>
                        <th class="px-4 py-3 text-left font-medium">Confidence</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-app">
                    @foreach ($results as $result)
                        <tr class="hover:bg-surface2/50">
                            <td class="px-4 py-3">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3 text-muted">{{ $result['source'] }}</td>
                            <td class="px-4 py-3 text-muted">{{ $result['destination'] }}</td>
                            <td class="px-4 py-3 text-muted">{{ $result['protocol'] }}</td>
                            <td class="px-4 py-3">
                                <span
                                    @class([
                                        'px-3 py-1 rounded-full text-xs font-semibold',
                                        'bg-[rgba(248,113,113,0.18)] text-[color:var(--danger)]' => $result['tone'] === 'danger',
                                        'bg-[rgba(34,197,94,0.18)] text-[color:var(--accent)]' => $result['tone'] === 'accent',
                                    ])
                                >
                                    {{ $result['result'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-semibold">{{ $result['confidence'] }}</td>
                        </tr>
                    @endforeach
                    <tr class="hover:bg-surface2/50">
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
@endsection
