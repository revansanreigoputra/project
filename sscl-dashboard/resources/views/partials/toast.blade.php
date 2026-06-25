@php
    // Ambil pesan flash session untuk toast global.
    $toastMessage = session('toast_message') ?? session('status');
    $toastTitle = session('toast_title') ?? 'Berhasil';
    $toastType = session('toast_type') ?? 'success';
@endphp

@if ($toastMessage)
    <div
        x-data="{ show: true, progress: 100 }"
        x-init="
            // Jalankan progress bar sampai toast otomatis tertutup.
            const duration = 4200;
            const startedAt = Date.now();
            const timer = setInterval(() => {
                progress = Math.max(0, 100 - (((Date.now() - startedAt) / duration) * 100));

                if (progress <= 0) {
                    clearInterval(timer);
                    show = false;
                }
            }, 40);
        "
        x-show="show"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-3 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-2 scale-95"
        class="fixed right-4 top-24 z-50 w-[calc(100vw-32px)] max-w-sm overflow-hidden rounded-2xl border border-app bg-surface/95 shadow-soft backdrop-blur-xl"
        role="status"
    >
        <div @class([
            'absolute inset-y-0 left-0 w-1',
            'bg-[color:var(--accent)]' => $toastType === 'success',
            'bg-[color:var(--danger)]' => $toastType === 'danger',
            'bg-[color:var(--warning)]' => ! in_array($toastType, ['success', 'danger'], true),
        ])></div>

        <div class="flex gap-3 p-4 pl-5">
            <div @class([
                'grid h-11 w-11 shrink-0 place-items-center rounded-2xl',
                'bg-[rgba(34,197,94,0.14)] text-[color:var(--accent)]' => $toastType === 'success',
                'bg-[rgba(248,113,113,0.14)] text-[color:var(--danger)]' => $toastType === 'danger',
                'bg-[rgba(251,191,36,0.14)] text-[color:var(--warning)]' => ! in_array($toastType, ['success', 'danger'], true),
            ])>
                @if ($toastType === 'danger')
                    <x-lucide-circle-alert class="h-5 w-5" />
                @else
                    <x-lucide-circle-check class="h-5 w-5" />
                @endif
            </div>

            <div class="min-w-0 flex-1">
                <div class="text-sm font-semibold">{{ $toastTitle }}</div>
                <div class="mt-1 text-xs leading-5 text-muted">{{ $toastMessage }}</div>
            </div>

            <button
                type="button"
                @click="show = false"
                class="grid h-8 w-8 shrink-0 place-items-center rounded-xl text-muted hover:bg-surface2 transition-colors"
                aria-label="Tutup notifikasi"
            >
                <x-lucide-x class="h-4 w-4" />
            </button>
        </div>

        <div class="h-1 bg-surface2">
            <div
                @class([
                    'h-full transition-[width] duration-75',
                    'bg-[color:var(--accent)]' => $toastType === 'success',
                    'bg-[color:var(--danger)]' => $toastType === 'danger',
                    'bg-[color:var(--warning)]' => ! in_array($toastType, ['success', 'danger'], true),
                ])
                :style="`width: ${progress}%`"
            ></div>
        </div>
    </div>
@endif
