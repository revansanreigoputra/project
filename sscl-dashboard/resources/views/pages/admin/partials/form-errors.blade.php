@if ($errors->any())
  <div class="rounded-3xl border border-[color:var(--danger)]/30 bg-[rgba(248,113,113,0.10)] p-4 text-sm text-[color:var(--danger)]">
    <div class="mb-2 font-semibold">Periksa kembali input berikut:</div>
    <ul class="list-disc space-y-1 pl-5">
      @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif
