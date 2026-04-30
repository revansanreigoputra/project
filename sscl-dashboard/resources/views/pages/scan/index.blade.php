@extends('layouts.dashboard')
@section('title', 'Upload Data')

@section('page')
    <div
        class="rounded-3xl bg-surface shadow-soft p-6"
        x-data="{
            fileName: '',
            errorMessage: '',
            isDragging: false,
            chooseFile() {
                this.$refs.csvFile.click();
            },
            setFile(files) {
                const file = files && files.length ? files[0] : null;

                if (!file) {
                    return;
                }

                const valid = file.name.toLowerCase().endsWith('.csv') || file.name.toLowerCase().endsWith('.xlsx');

                if (!valid) {
                    this.fileName = '';
                    this.errorMessage = 'File harus berformat CSV atau XLSX';
                    this.$refs.csvFile.value = '';
                    return;
                }

                this.fileName = file.name;
                this.errorMessage = '';
            },
            dropFile(event) {
                this.isDragging = false;
                const files = event.dataTransfer.files;

                this.setFile(files);

                if (files.length && !this.errorMessage) {
                    const transfer = new DataTransfer();
                    transfer.items.add(files[0]);
                    this.$refs.csvFile.files = transfer.files;
                }
            }
        }"
    >
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
                        <div class="text-sm font-semibold">Upload File CSV / XLSX</div>
                        <div class="text-xs text-muted mt-1" x-show="!fileName && !errorMessage">
                            Seret dan lepas file di sini atau pilih file secara manual
                        </div>
                        <div class="text-xs text-primary mt-1 font-semibold" x-show="fileName" x-text="'File dipilih: ' + fileName"></div>
                        <div class="text-xs text-[color:var(--danger)] mt-1 font-semibold" x-show="errorMessage" x-text="errorMessage"></div>
                    </div>
                </div>

                <input
                    type="file"
                    accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
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
                        type="button"
                        class="px-6 py-2 rounded-xl border border-app text-sm font-semibold hover:bg-surface2 transition-colors">
                        Proses Deteksi
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
                            <div class="font-semibold">: Menunggu</div>
                        </div>

                        <div class="grid grid-cols-[88px_1fr] gap-3">
                            <div class="text-muted">Progress</div>
                            <div class="font-semibold">: 0%</div>
                        </div>

                        <div class="grid grid-cols-[88px_1fr] gap-3">
                            <div></div>
                            <div class="w-full bg-app rounded-full h-2.5">
                                <div class="bg-primary h-2.5 rounded-full" style="width: 0%"></div>
                            </div>
                        </div>

                        <div class="grid grid-cols-[88px_1fr] gap-3">
                            <div class="text-muted">Keterangan</div>
                            <div class="font-semibold">: Dataset contoh 23.145 baris</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Preview Data Table --}}
        <div class="mt-6">
            <div class="text-sm font-semibold mb-3">Preview Data</div>
            <div class="overflow-x-auto rounded-2xl border border-app">
                <table class="w-full text-sm">
                    <thead class="bg-surface2 text-muted">
                        <tr class="border-b border-app">
                            <th class="px-4 py-3 text-left font-medium">No</th>
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
                        <tr class="hover:bg-surface2/50">
                            <td class="px-4 py-3">1</td>
                            <td class="px-4 py-3 text-muted">41040</td>
                            <td class="px-4 py-3 text-muted">80</td>
                            <td class="px-4 py-3 text-muted">tcp</td>
                            <td class="px-4 py-3 text-muted">-</td>
                            <td class="px-4 py-3">3139211</td>
                            <td class="px-4 py-3 text-muted">S0</td>
                            <td class="px-4 py-3">
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-[rgba(34,197,94,0.18)] text-[color:var(--accent)]">
                                    Benign
                                </span>
                            </td>
                        </tr>
                        <tr class="hover:bg-surface2/50">
                            <td class="px-4 py-3">2</td>
                            <td class="px-4 py-3 text-muted">41040</td>
                            <td class="px-4 py-3 text-muted">80</td>
                            <td class="px-4 py-3 text-muted">tcp</td>
                            <td class="px-4 py-3 text-muted">-</td>
                            <td class="px-4 py-3 text-muted">-</td>
                            <td class="px-4 py-3 text-muted">S0</td>
                            <td class="px-4 py-3">
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-[rgba(34,197,94,0.18)] text-[color:var(--accent)]">
                                    Benign
                                </span>
                            </td>
                        </tr>
                        <tr class="hover:bg-surface2/50">
                            <td class="px-4 py-3">3</td>
                            <td class="px-4 py-3 text-muted">41040</td>
                            <td class="px-4 py-3 text-muted">80</td>
                            <td class="px-4 py-3 text-muted">tcp</td>
                            <td class="px-4 py-3 text-muted">http</td>
                            <td class="px-4 py-3">1477656</td>
                            <td class="px-4 py-3 text-muted">SF</td>
                            <td class="px-4 py-3">
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-[rgba(34,197,94,0.18)] text-[color:var(--accent)]">
                                    Benign
                                </span>
                            </td>
                        </tr>
                        <tr class="hover:bg-surface2/50">
                            <td class="px-4 py-3">4</td>
                            <td class="px-4 py-3 text-muted">57094</td>
                            <td class="px-4 py-3 text-muted">6667</td>
                            <td class="px-4 py-3 text-muted">tcp</td>
                            <td class="px-4 py-3 text-muted">irc</td>
                            <td class="px-4 py-3">32999954</td>
                            <td class="px-4 py-3 text-muted">S3</td>
                            <td class="px-4 py-3">
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-[rgba(248,113,113,0.18)] text-[color:var(--danger)]">
                                    Malicious
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
