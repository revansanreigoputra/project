@extends('layouts.dashboard')
@section('title', 'Backup Database')

@section('page')
  @php
    // Siapkan label, class status, dan waktu backup dalam zona WIB.
    $formatLabel = fn (string $format) => strtoupper($format);
    $frequencyLabels = [
      'daily' => 'Harian',
      'weekly' => 'Mingguan',
      'monthly' => 'Bulanan',
    ];
    $statusClasses = [
      'completed' => 'bg-[rgba(34,197,94,0.16)] text-[color:var(--accent)]',
      'running' => 'bg-primary-soft text-primary',
      'failed' => 'bg-[rgba(248,113,113,0.16)] text-[color:var(--danger)]',
    ];
    $nextRun = $setting->next_run_at?->timezone(\App\Models\DatabaseBackupSetting::TIMEZONE)->format('d/m/Y H:i');
    $lastRun = $setting->last_run_at?->timezone(\App\Models\DatabaseBackupSetting::TIMEZONE)->format('d/m/Y H:i');
    $latestBackup = $stats['latest'];
  @endphp

  <div class="space-y-6">
    <div class="grid gap-4 md:grid-cols-3">
      <div class="rounded-3xl border border-app bg-surface p-5 shadow-soft">
        <div class="flex items-start justify-between gap-4">
          <div>
            <div class="text-xs font-semibold text-muted">Total Backup</div>
            <div class="mt-2 text-3xl font-semibold">{{ number_format($stats['total'], 0, ',', '.') }}</div>
          </div>
          <div class="grid h-11 w-11 place-items-center rounded-2xl bg-primary-soft text-primary">
            <x-lucide-database-backup class="h-5 w-5" />
          </div>
        </div>
      </div>

      <div class="rounded-3xl border border-app bg-surface p-5 shadow-soft">
        <div class="flex items-start justify-between gap-4">
          <div>
            <div class="text-xs font-semibold text-muted">Ukuran Tersimpan</div>
            <div class="mt-2 text-3xl font-semibold">{{ $stats['size'] }}</div>
          </div>
          <div class="grid h-11 w-11 place-items-center rounded-2xl bg-[rgba(56,189,248,0.16)] text-[color:var(--info)]">
            <x-lucide-hard-drive-download class="h-5 w-5" />
          </div>
        </div>
      </div>

      <div class="rounded-3xl border border-app bg-surface p-5 shadow-soft">
        <div class="flex items-start justify-between gap-4">
          <div>
            <div class="text-xs font-semibold text-muted">Auto-backup</div>
            <div class="mt-2 text-3xl font-semibold">{{ $setting->enabled ? 'Aktif' : 'Nonaktif' }}</div>
            <div class="mt-2 text-xs text-muted">{{ $setting->enabled && $nextRun ? $nextRun.' WIB' : 'Belum terjadwal' }}</div>
          </div>
          <div class="grid h-11 w-11 place-items-center rounded-2xl bg-[rgba(251,191,36,0.18)] text-[color:var(--warning)]">
            <x-lucide-calendar-clock class="h-5 w-5" />
          </div>
        </div>
      </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-12">
      <section
        class="rounded-3xl border border-app bg-surface p-6 shadow-soft xl:col-span-7"
        {{-- Config Alpine untuk menjalankan backup manual lewat request JSON. --}}
        x-data="backupConsole({
          endpoint: @js(route('backup.store')),
          token: @js(csrf_token()),
          initialFormat: @js(old('format', 'zip'))
        })"
      >
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <div class="flex items-center gap-3">
              <div class="grid h-12 w-12 place-items-center rounded-2xl bg-primary-soft text-primary">
                <x-lucide-database-backup class="h-6 w-6" />
              </div>
              <div>
                <div class="text-lg font-semibold">Manual Backup</div>
                <div class="mt-1 text-xs text-muted">Dump database siap unduh dalam format pilihan.</div>
              </div>
            </div>
          </div>

          <div class="inline-flex rounded-2xl bg-surface2 p-1">
            <button
              type="button"
              @click="format = 'zip'"
              class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition-colors"
              :class="format === 'zip' ? 'bg-primary text-white shadow-soft' : 'text-muted hover:bg-surface'"
            >
              <x-lucide-file-archive class="h-4 w-4" />
              ZIP
            </button>
            <button
              type="button"
              @click="format = 'sql'"
              class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition-colors"
              :class="format === 'sql' ? 'bg-primary text-white shadow-soft' : 'text-muted hover:bg-surface'"
            >
              <x-lucide-file-code-2 class="h-4 w-4" />
              SQL
            </button>
          </div>
        </div>

        <div class="mt-8 rounded-3xl border border-app bg-surface2 p-5">
          <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
              <div class="text-sm font-semibold" x-text="status || 'Siap membuat backup baru'"></div>
              <div class="mt-1 text-xs text-muted" x-show="!busy && !latest && !error">File akan tersimpan di storage private aplikasi.</div>
              <div class="mt-1 text-xs text-[color:var(--danger)]" x-show="error" x-text="error"></div>
              <div class="mt-1 text-xs text-[color:var(--accent)]" x-show="latest">
                <span x-text="latest?.file_name"></span>
                <span> • </span>
                <span x-text="latest?.size"></span>
              </div>
            </div>

            <button
              type="button"
              @click="start()"
              :disabled="busy"
              class="inline-flex items-center justify-center gap-2 rounded-2xl bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-70"
            >
              <x-lucide-play class="h-4 w-4" />
              <span x-text="busy ? 'Memproses' : 'Backup Sekarang'"></span>
            </button>
          </div>

          <div class="mt-5" x-show="busy || progress > 0" x-transition>
            <div class="flex items-center justify-between gap-3 text-xs font-semibold text-muted">
              <span>Progress</span>
              <span x-text="progress + '%'"></span>
            </div>
            <div class="mt-2 h-3 overflow-hidden rounded-full bg-app">
              <div class="h-full rounded-full bg-primary transition-all duration-300" :style="`width: ${progress}%`"></div>
            </div>
          </div>

          <div class="mt-5 flex flex-wrap gap-3" x-show="latest" x-transition>
            <a
              :href="latest?.download_url"
              class="inline-flex items-center justify-center gap-2 rounded-2xl border border-app bg-surface px-4 py-2.5 text-sm font-semibold transition-colors hover:bg-app"
            >
              <x-lucide-download class="h-4 w-4" />
              Unduh File
            </a>
            <button
              type="button"
              @click="window.location.reload()"
              class="inline-flex items-center justify-center gap-2 rounded-2xl border border-app bg-surface px-4 py-2.5 text-sm font-semibold transition-colors hover:bg-app"
            >
              <x-lucide-refresh-cw class="h-4 w-4" />
              Refresh Log
            </button>
          </div>
        </div>
      </section>

      <section class="rounded-3xl border border-app bg-surface p-6 shadow-soft xl:col-span-5">
        <div class="flex items-center justify-between gap-4">
          <div>
            <div class="text-lg font-semibold">Jadwal Auto-backup</div>
            <div class="mt-1 text-xs text-muted">Jadwal mengikuti zona waktu WIB.</div>
          </div>
          <div class="grid h-11 w-11 place-items-center rounded-2xl bg-[rgba(251,191,36,0.18)] text-[color:var(--warning)]">
            <x-lucide-calendar-clock class="h-5 w-5" />
          </div>
        </div>

        {{-- State Alpine kecil untuk menampilkan field mingguan/bulanan sesuai frekuensi. --}}
        <form method="POST" action="{{ route('backup.schedule') }}" class="mt-6 space-y-4" x-data="{ frequency: @js(old('frequency', $setting->frequency)) }">
          @csrf
          @method('PATCH')

          <label class="flex items-center justify-between gap-4 rounded-2xl border border-app bg-surface2 px-4 py-3">
            <span>
              <span class="block text-sm font-semibold">Aktifkan jadwal</span>
              <span class="block text-xs text-muted">Status saat ini: {{ $setting->enabled ? 'aktif' : 'nonaktif' }}</span>
            </span>
            <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $setting->enabled)) class="h-5 w-5 rounded border-app text-primary focus:ring-primary">
          </label>

          <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
              <span class="text-xs font-semibold text-muted">Frekuensi</span>
              <select name="frequency" x-model="frequency" class="mt-2 w-full rounded-2xl border border-app bg-surface px-4 py-3 text-sm focus:border-primary focus:ring-primary">
                @foreach ($frequencies as $frequency)
                  <option value="{{ $frequency }}">{{ $frequencyLabels[$frequency] }}</option>
                @endforeach
              </select>
            </label>

            <label class="block">
              <span class="text-xs font-semibold text-muted">Jam</span>
              <input
                type="time"
                name="scheduled_time"
                value="{{ old('scheduled_time', $setting->normalizedTime()) }}"
                class="mt-2 w-full rounded-2xl border border-app bg-surface px-4 py-3 text-sm focus:border-primary focus:ring-primary"
              >
            </label>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <label class="block" x-show="frequency === 'weekly'" x-transition>
              <span class="text-xs font-semibold text-muted">Hari</span>
              <select name="scheduled_weekday" class="mt-2 w-full rounded-2xl border border-app bg-surface px-4 py-3 text-sm focus:border-primary focus:ring-primary">
                @foreach ($weekdays as $value => $label)
                  <option value="{{ $value }}" @selected((int) old('scheduled_weekday', $setting->scheduled_weekday ?? 1) === (int) $value)>{{ $label }}</option>
                @endforeach
              </select>
            </label>

            <label class="block" x-show="frequency === 'monthly'" x-transition>
              <span class="text-xs font-semibold text-muted">Tanggal</span>
              <input
                type="number"
                name="scheduled_month_day"
                min="1"
                max="28"
                value="{{ old('scheduled_month_day', $setting->scheduled_month_day ?? 1) }}"
                class="mt-2 w-full rounded-2xl border border-app bg-surface px-4 py-3 text-sm focus:border-primary focus:ring-primary"
              >
            </label>

            <label class="block">
              <span class="text-xs font-semibold text-muted">Format</span>
              <select name="format" class="mt-2 w-full rounded-2xl border border-app bg-surface px-4 py-3 text-sm focus:border-primary focus:ring-primary">
                @foreach ($formats as $format)
                  <option value="{{ $format }}" @selected(old('format', $setting->format) === $format)>{{ $formatLabel($format) }}</option>
                @endforeach
              </select>
            </label>
          </div>

          <label class="block">
            <span class="text-xs font-semibold text-muted">Retensi Log dan File</span>
            <input
              type="number"
              name="retention_days"
              min="1"
              max="365"
              value="{{ old('retention_days', $setting->retention_days) }}"
              class="mt-2 w-full rounded-2xl border border-app bg-surface px-4 py-3 text-sm focus:border-primary focus:ring-primary"
            >
          </label>

          @if ($errors->any())
            <div class="rounded-2xl border border-[color:var(--danger)]/30 bg-[rgba(248,113,113,0.12)] px-4 py-3 text-sm text-[color:var(--danger)]">
              {{ $errors->first() }}
            </div>
          @endif

          <div class="rounded-2xl border border-app bg-surface2 p-4 text-xs text-muted">
            <div class="flex items-center justify-between gap-3">
              <span>Berikutnya</span>
              <span class="font-semibold text-app">{{ $nextRun ? $nextRun.' WIB' : '-' }}</span>
            </div>
            <div class="mt-2 flex items-center justify-between gap-3">
              <span>Terakhir</span>
              <span class="font-semibold text-app">{{ $lastRun ? $lastRun.' WIB' : '-' }}</span>
            </div>
          </div>

          <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover">
            <x-lucide-save class="h-4 w-4" />
            Simpan Jadwal
          </button>
        </form>
      </section>
    </div>

    <section class="rounded-3xl border border-app bg-surface p-6 shadow-soft">
      <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <div class="text-lg font-semibold">Riwayat Backup</div>
          <div class="mt-1 text-xs text-muted">
            @if ($latestBackup)
              Terakhir: {{ $latestBackup->file_name }} pada {{ $latestBackup->completed_at?->timezone(\App\Models\DatabaseBackupSetting::TIMEZONE)->format('d/m/Y H:i') }} WIB
            @else
              Belum ada backup selesai.
            @endif
          </div>
        </div>
      </div>

      <div class="mt-5 overflow-hidden rounded-3xl border border-app">
        <div class="overflow-auto">
          <table class="w-full min-w-[860px] text-sm">
            <thead class="bg-surface2 text-muted">
              <tr class="border-b border-app">
                <th class="px-4 py-3 text-left font-medium">Tanggal</th>
                <th class="px-4 py-3 text-left font-medium">Nama File</th>
                <th class="px-4 py-3 text-left font-medium">Format</th>
                <th class="px-4 py-3 text-left font-medium">Ukuran</th>
                <th class="px-4 py-3 text-left font-medium">Mode</th>
                <th class="px-4 py-3 text-left font-medium">Status</th>
                <th class="px-4 py-3 text-right font-medium">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-app">
              @forelse ($backups as $backup)
                <tr class="transition-colors hover:bg-surface2">
                  <td class="px-4 py-3 text-muted">
                    {{ ($backup->completed_at ?? $backup->created_at)?->timezone(\App\Models\DatabaseBackupSetting::TIMEZONE)->format('d/m/Y H:i') }} WIB
                  </td>
                  <td class="px-4 py-3">
                    <div class="max-w-xs truncate font-semibold" title="{{ $backup->file_name }}">{{ $backup->file_name }}</div>
                    @if ($backup->error_message)
                      <div class="mt-1 max-w-xs truncate text-xs text-[color:var(--danger)]" title="{{ $backup->error_message }}">{{ $backup->error_message }}</div>
                    @endif
                  </td>
                  <td class="px-4 py-3">
                    <span class="rounded-full bg-surface2 px-3 py-1 text-xs font-semibold">{{ $formatLabel($backup->format) }}</span>
                  </td>
                  <td class="px-4 py-3 text-muted">{{ $backup->sizeForHumans() }}</td>
                  <td class="px-4 py-3 text-muted">{{ $backup->trigger === 'scheduled' ? 'Auto' : 'Manual' }}</td>
                  <td class="px-4 py-3">
                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses[$backup->status] ?? 'bg-surface2 text-muted' }}">
                      {{ ucfirst($backup->status) }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-right">
                    @if ($backup->status === 'completed')
                      <a href="{{ route('backup.download', $backup) }}" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-app px-3 py-2 text-xs font-semibold transition-colors hover:bg-surface2">
                        <x-lucide-download class="h-4 w-4" />
                        Unduh
                      </a>
                    @else
                      <span class="text-xs text-muted">-</span>
                    @endif
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="px-6 py-12 text-center text-muted">
                    <x-lucide-database-backup class="mx-auto h-10 w-10" />
                    <div class="mt-3 text-sm font-semibold text-app">Belum ada log backup</div>
                    <div class="mt-1 text-xs">Jalankan backup manual atau aktifkan jadwal otomatis.</div>
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      <div class="mt-5">
        {{ $backups->links() }}
      </div>
    </section>
  </div>
@endsection
