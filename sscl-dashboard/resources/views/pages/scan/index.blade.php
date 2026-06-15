@extends('layouts.dashboard')
@section('title', 'Upload Data')

@section('page')
    @php
        $currentPrediction = $prediction ?? null;
        $formatNumber = fn ($value) => number_format((float) $value, 0, ',', '.');
        $formatPercent = fn ($value) => number_format((float) $value, 2, ',', '.');
        $toneForLabel = fn ($label) => $label === 'Normal' ? 'accent' : ($label === 'Unknown' ? 'warning' : 'danger');
        $statusForTone = fn ($tone) => $tone === 'accent' ? 'Aman' : ($tone === 'warning' ? 'Unknown' : 'Perlu Review');
        $recommendationForTone = fn ($tone) => $tone === 'accent' ? 'Arsipkan' : ($tone === 'warning' ? 'Validasi manual' : 'Prioritaskan');
        $topProbability = function ($probabilities) {
            $items = collect($probabilities ?? []);

            if ($items->isEmpty()) {
                return ['label' => '-', 'value' => 0];
            }

            $label = $items->sortDesc()->keys()->first();

            return ['label' => $label, 'value' => (float) $items[$label]];
        };
        $previewRows = collect($currentPrediction['results'] ?? [])
            ->take(100)
            ->values()
            ->map(function (array $result, int $index) use (
                $formatPercent,
                $toneForLabel,
                $statusForTone,
                $recommendationForTone,
                $topProbability
            ) {
                $label = $result['label'] ?? 'Unknown';
                $tone = $toneForLabel($label);
                $probabilities = collect($result['probabilities'] ?? []);
                $confidencePercent = max(0, min(100, ((float) ($result['confidence'] ?? 0)) * 100));
                $normalProbability = max(0, min(100, ((float) ($probabilities['Normal'] ?? 0)) * 100));
                $labelProbability = max(0, min(100, ((float) ($probabilities[$label] ?? $result['confidence'] ?? 0)) * 100));
                $topPrediction = $topProbability($probabilities);
                $topPredictionPercent = max(0, min(100, ((float) $topPrediction['value']) * 100));
                $row = (string) ($result['row'] ?? $index + 1);
                $status = $statusForTone($tone);
                $recommendation = $recommendationForTone($tone);

                return [
                    'no' => $index + 1,
                    'row' => $row,
                    'label' => $label,
                    'tone' => $tone,
                    'status' => $status,
                    'recommendation' => $recommendation,
                    'confidence' => round($confidencePercent, 2),
                    'confidence_text' => $formatPercent($confidencePercent).'%',
                    'prob_normal' => round($normalProbability, 2),
                    'prob_normal_text' => $formatPercent($normalProbability).'%',
                    'prob_label' => round($labelProbability, 2),
                    'prob_label_text' => $formatPercent($labelProbability).'%',
                    'top_label' => $topPrediction['label'],
                    'top_probability' => round($topPredictionPercent, 2),
                    'top_probability_text' => $formatPercent($topPredictionPercent).'%',
                    'probabilities' => $probabilities
                        ->sortDesc()
                        ->take(4)
                        ->map(fn ($value, $name) => [
                            'label' => $name,
                            'percent' => round(((float) $value) * 100, 2),
                            'value' => $formatPercent(((float) $value) * 100).'%',
                        ])
                        ->values()
                        ->all(),
                    'search' => implode(' ', [
                        $row,
                        $label,
                        $status,
                        $recommendation,
                        $formatPercent($confidencePercent),
                        $topPrediction['label'],
                    ]),
                ];
            })
            ->values();
        $summary = $currentPrediction['summary'] ?? [];
        $labelOptions = collect($summary['labels'] ?? [])
            ->keys()
            ->whenEmpty(fn ($items) => $previewRows->pluck('label')->unique()->values())
            ->whenEmpty(fn ($items) => collect(['Normal']));
    @endphp

    <div
        class="rounded-3xl bg-surface shadow-soft p-6"
        x-data="scanUploadPage({
            uploadUrl: @js(route('scan.upload')),
            csrfToken: @js(csrf_token()),
            results: @js($previewRows),
        })"
    >
        <form method="POST" action="{{ route('scan.store') }}" enctype="multipart/form-data" @submit="isProcessing = true">
            @csrf
            <input type="hidden" name="scan_upload_id" :value="uploadId || ''">

            <div class="grid gap-6 lg:grid-cols-12">
            {{-- Upload Area --}}
            <div class="lg:col-span-8">
                <div
                    class="min-h-64 rounded-3xl border-2 border-dashed border-app p-8 flex flex-col items-center justify-center bg-surface2/30 cursor-pointer hover:bg-surface2/50 transition-colors"
                    :style="isDragging ? 'border-color: var(--primary); background-color: var(--primary-soft);' : ''"
                    @click="chooseFile()"
                    @dragenter.prevent="isDragging = true"
                    @dragover.prevent="isDragging = true"
                    @dragleave.prevent="isDragging = false"
                    @drop.prevent="dropFile($event)"
                >
                    <div class="text-center">
                        <x-lucide-cloud-upload class="h-14 w-14 mx-auto text-muted mb-3" />
                        <div class="text-sm font-semibold">Upload File CSV / TXT / XLSX / XLS</div>
                        <div class="text-xs text-muted mt-1" x-show="!fileName && !errorMessage">
                            Seret dan lepas file di sini atau pilih file secara manual
                        </div>
                        <div class="text-xs text-primary mt-1 font-semibold" x-show="fileName" x-text="'File dipilih: ' + fileName"></div>
                        <div class="text-xs text-muted mt-1 font-semibold" x-show="isUploading" x-text="uploadMessage"></div>
                        <div class="text-xs text-[color:var(--accent)] mt-1 font-semibold" x-show="uploadId && !isUploading" x-text="uploadMessage"></div>
                        <div class="text-xs text-[color:var(--danger)] mt-1 font-semibold" x-show="errorMessage" x-text="errorMessage"></div>
                        @if (isset($errors) && $errors->has('file'))
                            <div class="text-xs text-[color:var(--danger)] mt-1 font-semibold">{{ $errors->first('file') }}</div>
                        @endif
                    </div>
                </div>

                <input
                    type="file"
                    name="file"
                    :name="uploadId ? null : 'file'"
                    accept=".csv,.txt,.xlsx,.xls,text/csv,text/plain,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
                    class="hidden"
                    x-ref="csvFile"
                    @change="setFile($event.target.files)"
                >

                <div class="mt-5 flex flex-wrap gap-3">
                    <button
                        type="button"
                        @click="chooseFile()"
                        class="px-6 py-2 rounded-xl bg-primary text-white text-sm font-semibold hover:opacity-90 transition-opacity">
                        Pilih File
                    </button>
                    <button
                        type="submit"
                        :disabled="!fileName || isUploading || isProcessing"
                        class="px-6 py-2 rounded-xl border border-app text-sm font-semibold hover:bg-surface2 transition-colors disabled:cursor-not-allowed disabled:opacity-60">
                        <span x-show="!isProcessing">Proses Deteksi</span>
                        <span x-show="isProcessing">Memproses...</span>
                    </button>
                </div>
            </div>

            {{-- Status Section --}}
            <div class="lg:col-span-4">
                <div class="h-full rounded-2xl bg-surface2 p-5 border border-app">
                    <div class="text-sm font-semibold">Status Proses</div>

                    <div class="mt-5 space-y-4 text-sm">
                        <div class="grid grid-cols-[88px_1fr] gap-3">
                            <div class="text-muted">Status</div>
                            <div class="font-semibold">
                                : <span x-show="isUploading">Menyimpan</span><span x-show="!isUploading && isProcessing">Memproses</span><span x-show="!isUploading && !isProcessing">{{ isset($prediction) ? 'Selesai' : 'Menunggu' }}</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-[88px_1fr] gap-3">
                            <div class="text-muted">Progress</div>
                            <div class="font-semibold">
                                : <span x-show="isUploading">25%</span><span x-show="!isUploading && isProcessing">50%</span><span x-show="!isUploading && !isProcessing">{{ isset($prediction) ? '100%' : '0%' }}</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-[88px_1fr] gap-3">
                            <div></div>
                            <div class="w-full bg-app rounded-full h-2.5">
                                <div
                                    class="bg-primary h-2.5 rounded-full transition-all"
                                    :style="isUploading ? 'width: 25%' : (isProcessing ? 'width: 50%' : 'width: {{ isset($prediction) ? '100' : '0' }}%')"
                                ></div>
                            </div>
                        </div>

                        <div class="grid grid-cols-[88px_1fr] gap-3">
                            <div class="text-muted">Keterangan</div>
                            <div class="font-semibold">
                                : <span x-show="isUploading">Menyimpan file upload</span><span x-show="!isUploading && isProcessing">Mengirim file ke Flask ML API</span><span x-show="!isUploading && !isProcessing">{{ isset($prediction) ? 'Prediksi ' . number_format($prediction['total'], 0, ',', '.') . ' baris berhasil' : 'Belum ada proses deteksi' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </form>

        @isset($prediction)
            @php
                $summary = $prediction['summary'] ?? [];
                $labelOptions = collect($summary['labels'] ?? [])->keys()->whenEmpty(fn ($items) => collect(['Normal']));
                $toneForLabel = fn ($label) => $label === 'Normal' ? 'accent' : ($label === 'Unknown' ? 'warning' : 'danger');
            @endphp

            <div class="mt-6 rounded-3xl bg-surface2 border border-app p-5">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="text-sm font-semibold">Hasil Prediksi Model</div>
                        <div class="text-xs text-muted mt-1">{{ $prediction['file_name'] }} - {{ number_format($prediction['total'], 0, ',', '.') }} baris diproses</div>
                    </div>
                    <div class="text-xs text-muted">Menampilkan 100 baris pertama</div>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-4">
                    <div class="rounded-2xl bg-surface border border-app p-4">
                        <div class="text-xs text-muted">Normal</div>
                        <div class="mt-1 text-2xl font-semibold">{{ number_format($summary['normal'] ?? 0, 0, ',', '.') }}</div>
                        <div class="text-xs text-muted">{{ number_format($summary['normal_percent'] ?? 0, 2, ',', '.') }}%</div>
                    </div>
                    <div class="rounded-2xl bg-surface border border-app p-4">
                        <div class="text-xs text-muted">Malware</div>
                        <div class="mt-1 text-2xl font-semibold">{{ number_format($summary['malware'] ?? 0, 0, ',', '.') }}</div>
                        <div class="text-xs text-muted">{{ number_format($summary['malware_percent'] ?? 0, 2, ',', '.') }}%</div>
                    </div>
                    <div class="rounded-2xl bg-surface border border-app p-4">
                        <div class="text-xs text-muted">Rata-rata Confidence</div>
                        <div class="mt-1 text-2xl font-semibold">{{ number_format($prediction['summary']['average_confidence'], 2, ',', '.') }}%</div>
                    </div>
                    <div class="rounded-2xl bg-surface border border-app p-4">
                        <div class="text-xs text-muted">Total Diproses</div>
                        <div class="mt-1 text-2xl font-semibold">{{ number_format($prediction['total'], 0, ',', '.') }}</div>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(220px,1fr)_160px_120px_auto]">
                    <div class="relative">
                        <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" />
                        <input
                            type="search"
                            x-model.debounce.150ms="resultSearch"
                            @input="resetPage()"
                            placeholder="Cari row, label, status, confidence"
                            class="w-full rounded-xl border border-app bg-surface px-10 py-2.5 text-sm focus:border-[color:var(--primary)] focus:ring-2 focus:ring-[color:var(--primary-soft)]"
                        >
                    </div>

                    <select
                        x-model="resultLabel"
                        @change="resetPage()"
                        class="rounded-xl border border-app bg-surface px-4 py-2.5 text-sm focus:border-[color:var(--primary)] focus:ring-2 focus:ring-[color:var(--primary-soft)]"
                    >
                        <option value="all">Semua Label</option>
                        @foreach ($labelOptions as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>

                    <select
                        x-model.number="perPage"
                        @change="resetPage()"
                        class="rounded-xl border border-app bg-surface px-4 py-2.5 text-sm focus:border-[color:var(--primary)] focus:ring-2 focus:ring-[color:var(--primary-soft)]"
                    >
                        <option value="10">10 / halaman</option>
                        <option value="25">25 / halaman</option>
                        <option value="50">50 / halaman</option>
                        <option value="100">100 / halaman</option>
                    </select>

                    <button
                        type="button"
                        @click="resetResultFilters()"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-app px-4 py-2.5 text-sm font-semibold transition-colors hover:bg-surface"
                    >
                        <x-lucide-rotate-ccw class="h-4 w-4" />
                        Reset
                    </button>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs font-semibold text-muted">
                    <div class="flex flex-wrap gap-2">
                        <span class="rounded-full bg-surface px-3 py-1.5">
                            <span x-text="filteredRows.length"></span> dari {{ $formatNumber($previewRows->count()) }} baris pratinjau
                        </span>
                        <span class="rounded-full bg-surface px-3 py-1.5">
                            Filter aktif: <span x-text="activeFilterCount"></span>
                        </span>
                    </div>
                    <div>
                        Menampilkan <span x-text="rangeStart"></span>-<span x-text="rangeEnd"></span>
                    </div>
                </div>

                <div class="mt-4 max-h-[560px] overflow-auto rounded-2xl border border-app">
                    <table class="w-full min-w-[900px] text-sm">
                        <thead class="sticky top-0 z-10 bg-surface text-muted">
                            <tr class="border-b border-app">
                                <th class="px-3 py-2.5 text-left font-medium">No</th>
                                <th class="px-3 py-2.5 text-left font-medium">
                                    <button type="button" @click="toggleSort('row')" class="inline-flex items-center gap-1 hover:text-app">
                                        Row
                                        <x-lucide-arrow-up-down class="h-3.5 w-3.5" />
                                    </button>
                                </th>
                                <th class="px-3 py-2.5 text-left font-medium">
                                    <button type="button" @click="toggleSort('label')" class="inline-flex items-center gap-1 hover:text-app">
                                        Label
                                        <x-lucide-arrow-up-down class="h-3.5 w-3.5" />
                                    </button>
                                </th>
                                <th class="px-3 py-2.5 text-left font-medium">
                                    <button type="button" @click="toggleSort('confidence')" class="inline-flex items-center gap-1 hover:text-app">
                                        Confidence
                                        <x-lucide-arrow-up-down class="h-3.5 w-3.5" />
                                    </button>
                                </th>
                                <th class="px-3 py-2.5 text-left font-medium">Prob. Normal</th>
                                <th class="px-3 py-2.5 text-left font-medium">Prob. Prediksi</th>
                                <th class="px-3 py-2.5 text-left font-medium">Status</th>
                                <th class="px-3 py-2.5 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-app">
                            <template x-for="row in pagedRows" :key="row.no + '-' + row.row">
                                <tr class="transition-colors hover:bg-surface/60">
                                    <td class="px-3 py-2.5 text-muted" x-text="row.no"></td>
                                    <td class="px-3 py-2.5 font-semibold" x-text="row.row"></td>
                                    <td class="px-3 py-2.5">
                                        <span
                                            class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold"
                                            :class="{
                                                'bg-[rgba(248,113,113,0.18)] text-[color:var(--danger)]': row.tone === 'danger',
                                                'bg-[rgba(34,197,94,0.18)] text-[color:var(--accent)]': row.tone === 'accent',
                                                'bg-[rgba(251,191,36,0.18)] text-[color:var(--warning)]': row.tone === 'warning',
                                            }"
                                        >
                                            <span
                                                class="h-2 w-2 rounded-full"
                                                :class="{
                                                    'bg-[color:var(--danger)]': row.tone === 'danger',
                                                    'bg-[color:var(--accent)]': row.tone === 'accent',
                                                    'bg-[color:var(--warning)]': row.tone === 'warning',
                                                }"
                                            ></span>
                                            <span x-text="row.label"></span>
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <div class="flex min-w-36 items-center gap-3">
                                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-app">
                                                <div class="h-full rounded-full bg-primary" :style="`width: ${row.confidence}%`"></div>
                                            </div>
                                            <span class="w-16 text-right font-semibold" x-text="row.confidence_text"></span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5 text-muted" x-text="row.prob_normal_text"></td>
                                    <td class="px-3 py-2.5">
                                        <div class="font-semibold" x-text="row.prob_label_text"></div>
                                        <div class="mt-1 max-w-36 truncate text-xs text-muted">
                                            Top: <span x-text="row.top_label"></span> (<span x-text="row.top_probability_text"></span>)
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <span
                                            class="rounded-full px-3 py-1 text-xs font-semibold"
                                            :class="{
                                                'bg-[rgba(248,113,113,0.16)] text-[color:var(--danger)]': row.tone === 'danger',
                                                'bg-[rgba(34,197,94,0.16)] text-[color:var(--accent)]': row.tone === 'accent',
                                                'bg-[rgba(251,191,36,0.18)] text-[color:var(--warning)]': row.tone === 'warning',
                                            }"
                                            x-text="row.status"
                                        ></span>
                                        <div class="mt-1 text-xs text-muted" x-text="row.recommendation"></div>
                                    </td>
                                    <td class="px-3 py-2.5 text-right">
                                        <button
                                            type="button"
                                            @click="openRowDetail(row)"
                                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-app px-3 py-2 text-xs font-semibold transition-colors hover:bg-surface"
                                        >
                                            <x-lucide-eye class="h-4 w-4" />
                                            Detail
                                        </button>
                                    </td>
                                </tr>
                            </template>

                            <template x-if="filteredRows.length === 0">
                                <tr>
                                    <td class="px-6 py-10 text-center text-muted" colspan="8">
                                        <x-lucide-file-text class="mx-auto h-10 w-10" />
                                        <div class="mt-3 text-sm font-semibold text-app">Tidak ada baris yang cocok</div>
                                        <div class="mt-1 text-xs">Coba reset filter atau turunkan minimal confidence.</div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex flex-col gap-3 text-sm text-muted md:flex-row md:items-center md:justify-between">
                    <div>
                        Halaman <span class="font-semibold text-app" x-text="Math.min(page, totalPages)"></span>/<span x-text="totalPages"></span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            @click="page = Math.max(1, page - 1)"
                            :disabled="page <= 1"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-app transition-colors hover:bg-surface disabled:cursor-not-allowed disabled:opacity-40"
                            aria-label="Halaman sebelumnya"
                        >
                            <x-lucide-chevron-left class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            @click="page = Math.min(totalPages, page + 1)"
                            :disabled="page >= totalPages"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-app transition-colors hover:bg-surface disabled:cursor-not-allowed disabled:opacity-40"
                            aria-label="Halaman berikutnya"
                        >
                            <x-lucide-chevron-right class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        @endisset

        <div
            x-show="rowDetailOpen"
            x-cloak
            class="fixed inset-0 z-50 grid place-items-center bg-black/45 p-4 backdrop-blur-sm"
            x-transition.opacity
        >
            <div
                @click.outside="rowDetailOpen = false"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-3 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 translate-y-2 scale-95"
                class="w-full max-w-lg overflow-hidden rounded-3xl border border-app bg-surface shadow-soft"
                role="dialog"
                aria-modal="true"
            >
                <div class="flex items-start justify-between gap-4 border-b border-app p-6">
                    <div>
                        <div class="text-lg font-semibold">Detail Baris <span x-text="rowDetail.row"></span></div>
                        <div class="mt-1 text-sm text-muted">Label <span class="font-semibold text-app" x-text="rowDetail.label"></span></div>
                    </div>
                    <button type="button" @click="rowDetailOpen = false" class="rounded-xl p-2 text-muted transition-colors hover:bg-surface2">
                        <x-lucide-x class="h-5 w-5" />
                    </button>
                </div>

                <div class="space-y-4 p-6">
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-2xl border border-app bg-surface2 p-4">
                            <div class="text-xs text-muted">Confidence</div>
                            <div class="mt-1 text-lg font-semibold sm:text-xl" x-text="rowDetail.confidence_text"></div>
                        </div>
                        <div class="rounded-2xl border border-app bg-surface2 p-4">
                            <div class="text-xs text-muted">Prob. Normal</div>
                            <div class="mt-1 text-lg font-semibold sm:text-xl" x-text="rowDetail.prob_normal_text"></div>
                        </div>
                        <div class="rounded-2xl border border-app bg-surface2 p-4">
                            <div class="text-xs text-muted">Prob. Label</div>
                            <div class="mt-1 text-lg font-semibold sm:text-xl" x-text="rowDetail.prob_label_text"></div>
                        </div>
                        <div class="rounded-2xl border border-app bg-surface2 p-4">
                            <div class="text-xs text-muted">Top Prediksi</div>
                            <div class="mt-1 truncate text-lg font-semibold sm:text-xl" x-text="rowDetail.top_label"></div>
                            <div class="mt-1 text-xs text-muted" x-text="rowDetail.top_probability_text"></div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-app bg-surface2 p-4 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-muted">Status</span>
                            <span class="font-semibold" x-text="rowDetail.status"></span>
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-4">
                            <span class="text-muted">Rekomendasi</span>
                            <span class="font-semibold" x-text="rowDetail.recommendation"></span>
                        </div>
                    </div>

                    <div>
                        <div class="text-sm font-semibold">Probabilitas Utama</div>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <template x-for="item in rowDetail.probabilities" :key="item.label">
                                <div class="flex items-center justify-between gap-4 rounded-2xl border border-app bg-surface2 px-4 py-3">
                                    <span class="truncate text-sm font-semibold" x-text="item.label"></span>
                                    <span class="text-sm text-muted" x-text="item.value"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
