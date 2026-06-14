<div
  x-data="confirmDialog()"
  x-show="isOpen"
  x-cloak
  x-transition.opacity
  @open-confirm-dialog.window="open($event.detail)"
  @keydown.escape.window="close()"
  class="fixed inset-0 z-[80] grid place-items-center bg-black/45 p-4 backdrop-blur-sm"
>
  <div
    @click.outside="close()"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 translate-y-3 scale-95"
    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
    x-transition:leave-end="opacity-0 translate-y-2 scale-95"
    class="w-full max-w-md overflow-hidden rounded-3xl border border-app bg-surface shadow-soft"
    role="dialog"
    aria-modal="true"
    :aria-label="dialog.title"
  >
    <div class="p-6">
      <div class="flex items-start gap-4">
        <div
          class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl"
          :class="dialog.tone === 'danger'
            ? 'bg-[rgba(248,113,113,0.14)] text-[color:var(--danger)]'
            : 'bg-primary-soft text-primary'"
        >
          <x-lucide-trash-2 x-show="dialog.icon === 'trash'" class="h-6 w-6" />
          <x-lucide-log-out x-show="dialog.icon === 'logout'" class="h-6 w-6" />
          <x-lucide-alert-triangle x-show="!['trash', 'logout'].includes(dialog.icon)" class="h-6 w-6" />
        </div>

        <div class="min-w-0">
          <div class="text-lg font-semibold" x-text="dialog.title"></div>
          <div class="mt-2 text-sm leading-6 text-muted">
            <span x-text="dialog.message"></span>
            <span
              x-show="dialog.subject"
              class="font-semibold text-app"
              x-text="dialog.subject"
            ></span>
          </div>
        </div>
      </div>
    </div>

    <div class="flex flex-col-reverse gap-3 border-t border-app bg-surface2 px-6 py-4 sm:flex-row sm:justify-end">
      <button
        type="button"
        x-ref="cancelButton"
        @click="close()"
        class="inline-flex items-center justify-center rounded-xl border border-app px-5 py-2.5 text-sm font-semibold transition-colors hover:bg-surface focus-visible:outline-none focus-visible:ring-2 ring-app"
        x-text="dialog.cancelLabel"
      ></button>

      <form method="POST" :action="dialog.action" class="sm:inline-flex">
        @csrf
        <template x-if="dialog.method && dialog.method !== 'POST'">
          <input type="hidden" name="_method" :value="dialog.method">
        </template>
        <button
          type="submit"
          class="inline-flex w-full items-center justify-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold text-white transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
          :class="dialog.tone === 'danger' ? 'bg-[color:var(--danger)]' : 'bg-primary'"
          :disabled="!dialog.action"
        >
          <x-lucide-check class="h-4 w-4" />
          <span x-text="dialog.confirmLabel"></span>
        </button>
      </form>
    </div>
  </div>
</div>
