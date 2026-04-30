@extends('layouts.dashboard')
@section('title', 'Hasil Deteksi')

@section('page')
    @php
        $results = [
            ['row' => '0', 'orig' => '41040', 'resp' => '80', 'proto' => 'tcp', 'service' => '-', 'duration' => '3139211', 'state' => 'S0', 'label' => 'Benign', 'tone' => 'accent'],
            ['row' => '3', 'orig' => '41040', 'resp' => '80', 'proto' => 'tcp', 'service' => 'http', 'duration' => '1477656', 'state' => 'SF', 'label' => 'Benign', 'tone' => 'accent'],
            ['row' => '23137', 'orig' => '57094', 'resp' => '6667', 'proto' => 'tcp', 'service' => 'irc', 'duration' => '32999954', 'state' => 'S3', 'label' => 'Malicious', 'tone' => 'danger'],
            ['row' => '23138', 'orig' => '57100', 'resp' => '6667', 'proto' => 'tcp', 'service' => 'irc', 'duration' => '32839989', 'state' => 'S3', 'label' => 'Malicious', 'tone' => 'danger'],
            ['row' => '23142', 'orig' => '123', 'resp' => '123', 'proto' => 'udp', 'service' => '-', 'duration' => '-', 'state' => 'S0', 'label' => 'Benign', 'tone' => 'accent'],
            ['row' => '23144', 'orig' => '123', 'resp' => '123', 'proto' => 'udp', 'service' => '-', 'duration' => '-', 'state' => 'S0', 'label' => 'Benign', 'tone' => 'accent'],
        ];
    @endphp

    <div class="rounded-3xl bg-surface shadow-soft p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="relative flex-1">
                <x-lucide-search class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" />
                <input
                    type="text"
                    placeholder="Cari row, port, proto, service, atau label..."
                    class="w-full rounded-2xl border border-app bg-surface2 px-12 py-3 text-sm focus:border-[color:var(--primary)] focus:ring-2 focus:ring-[color:var(--primary-soft)]"
                >
            </div>

            <div class="flex flex-wrap gap-3">
                <select class="rounded-2xl border border-app bg-surface2 px-4 py-3 text-sm focus:border-[color:var(--primary)] focus:ring-2 focus:ring-[color:var(--primary-soft)]">
                    <option>Semua Label</option>
                    <option>Benign</option>
                    <option>Malicious</option>
                </select>

                <select class="rounded-2xl border border-app bg-surface2 px-4 py-3 text-sm focus:border-[color:var(--primary)] focus:ring-2 focus:ring-[color:var(--primary-soft)]">
                    <option>Semua Proto</option>
                    <option>tcp</option>
                    <option>udp</option>
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
                        <th class="px-4 py-3 text-left font-medium">Orig Port</th>
                        <th class="px-4 py-3 text-left font-medium">Resp Port</th>
                        <th class="px-4 py-3 text-left font-medium">Proto</th>
                        <th class="px-4 py-3 text-left font-medium">Service</th>
                        <th class="px-4 py-3 text-left font-medium">Duration</th>
                        <th class="px-4 py-3 text-left font-medium">Conn State</th>
                        <th class="px-4 py-3 text-left font-medium">Label</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-app">
                    @foreach ($results as $result)
                        <tr class="hover:bg-surface2/50">
                            <td class="px-4 py-3">{{ $result['row'] }}</td>
                            <td class="px-4 py-3 text-muted">{{ $result['orig'] }}</td>
                            <td class="px-4 py-3 text-muted">{{ $result['resp'] }}</td>
                            <td class="px-4 py-3 text-muted">{{ $result['proto'] }}</td>
                            <td class="px-4 py-3 text-muted">{{ $result['service'] }}</td>
                            <td class="px-4 py-3 text-muted">{{ $result['duration'] }}</td>
                            <td class="px-4 py-3 text-muted">{{ $result['state'] }}</td>
                            <td class="px-4 py-3">
                                <span
                                    @class([
                                        'px-3 py-1 rounded-full text-xs font-semibold',
                                        'bg-[rgba(248,113,113,0.18)] text-[color:var(--danger)]' => $result['tone'] === 'danger',
                                        'bg-[rgba(34,197,94,0.18)] text-[color:var(--accent)]' => $result['tone'] === 'accent',
                                    ])
                                >
                                    {{ $result['label'] }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
