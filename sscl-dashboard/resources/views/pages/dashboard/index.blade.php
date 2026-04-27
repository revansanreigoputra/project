@extends('layouts.dashboard')
@section('title', 'Dashboard')

@section('page')
  <div class="grid gap-6 lg:grid-cols-12">
    {{-- LEFT --}}
    <div class="lg:col-span-8 space-y-6">
      {{-- Stat cards --}}
      <div class="grid gap-6 md:grid-cols-3">
        <div class="rounded-3xl bg-surface shadow-soft p-6 flex items-center justify-between">
          <div class="space-y-2">
            <div class="h-12 w-12 rounded-2xl grid place-items-center bg-primary-soft">
              <x-lucide-activity class="h-6 w-6 text-primary" />
            </div>
            <div class="text-sm font-semibold">Total Traffic</div>
            <div class="text-4xl font-semibold">18,420</div>
            <div class="text-sm text-muted">Last 24 Hours</div>
          </div>
          <x-progress-ring value="81" />
        </div>

        <div class="rounded-3xl bg-surface shadow-soft p-6 flex items-center justify-between">
          <div class="space-y-2">
            <div class="h-12 w-12 rounded-2xl grid place-items-center bg-[rgba(248,113,113,0.15)]">
              <x-lucide-shield-alert class="h-6 w-6 text-[color:var(--danger)]" />
            </div>
            <div class="text-sm font-semibold">Suspicious Alerts</div>
            <div class="text-4xl font-semibold">214</div>
            <div class="text-sm text-muted">Last 24 Hours</div>
          </div>
          <x-progress-ring value="62" />
        </div>

        <div class="rounded-3xl bg-surface shadow-soft p-6 flex items-center justify-between">
          <div class="space-y-2">
            <div class="h-12 w-12 rounded-2xl grid place-items-center bg-[rgba(34,197,94,0.15)]">
              <x-lucide-bug class="h-6 w-6 text-[color:var(--accent)]" />
            </div>
            <div class="text-sm font-semibold">Detected Malware</div>
            <div class="text-4xl font-semibold">37</div>
            <div class="text-sm text-muted">Last 24 Hours</div>
          </div>
          <x-progress-ring value="44" />
        </div>
      </div>

      {{-- Recent Traffic --}}
      <div class="rounded-3xl bg-surface shadow-soft p-6">
        <div class="text-xl font-semibold">Recent Traffic</div>

        <div class="mt-5 overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-muted">
              <tr class="border-b border-app">
                <th class="py-3 text-left font-medium">Flow Name</th>
                <th class="py-3 text-left font-medium">Flow ID</th>
                <th class="py-3 text-left font-medium">Protocol</th>
                <th class="py-3 text-left font-medium">Status</th>
                <th class="py-3 text-right font-medium"> </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[color:var(--border)]">
              <tr>
                <td class="py-4">Flow to 8.8.8.8</td>
                <td class="py-4 text-muted">85631</td>
                <td class="py-4 text-muted">TCP</td>
                <td class="py-4">
                  <span class="px-3 py-1 rounded-full text-xs font-semibold bg-[rgba(248,113,113,0.18)] text-[color:var(--danger)]">
                    Malware
                  </span>
                </td>
                <td class="py-4 text-right">
                  <a href="#" class="text-primary hover:underline">Details</a>
                </td>
              </tr>

              <tr>
                <td class="py-4">DNS query 1.1.1.1</td>
                <td class="py-4 text-muted">36378</td>
                <td class="py-4 text-muted">UDP</td>
                <td class="py-4">
                  <span class="px-3 py-1 rounded-full text-xs font-semibold bg-[rgba(34,197,94,0.18)] text-[color:var(--accent)]">
                    Normal
                  </span>
                </td>
                <td class="py-4 text-right">
                  <a href="#" class="text-primary hover:underline">Details</a>
                </td>
              </tr>

              <tr>
                <td class="py-4">C2-like traffic</td>
                <td class="py-4 text-muted">49347</td>
                <td class="py-4 text-muted">TCP</td>
                <td class="py-4">
                  <span class="px-3 py-1 rounded-full text-xs font-semibold bg-[rgba(251,191,36,0.20)] text-[color:var(--warning)]">
                    Suspicious
                  </span>
                </td>
                <td class="py-4 text-right">
                  <a href="#" class="text-primary hover:underline">Details</a>
                </td>
              </tr>

              <tr>
                <td class="py-4">HTTP download</td>
                <td class="py-4 text-muted">96996</td>
                <td class="py-4 text-muted">TCP</td>
                <td class="py-4">
                  <span class="px-3 py-1 rounded-full text-xs font-semibold bg-[rgba(34,197,94,0.18)] text-[color:var(--accent)]">
                    Normal
                  </span>
                </td>
                <td class="py-4 text-right">
                  <a href="#" class="text-primary hover:underline">Details</a>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- RIGHT --}}
    <div class="lg:col-span-4 space-y-6">
      {{-- Recent Updates --}}
      <div class="rounded-3xl bg-surface shadow-soft p-6">
        <div class="text-xl font-semibold">Recent Updates</div>

        <div class="mt-5 space-y-5">
          <div class="flex gap-3">
            <div class="h-10 w-10 rounded-full bg-surface2 grid place-items-center font-semibold">S</div>
            <div>
              <div class="text-sm font-semibold">SOC Agent</div>
              <div class="text-sm text-muted">Malware classification on outbound flow.</div>
              <div class="text-xs text-muted mt-1">2 Minutes Ago</div>
            </div>
          </div>

          <div class="flex gap-3">
            <div class="h-10 w-10 rounded-full bg-surface2 grid place-items-center font-semibold">S</div>
            <div>
              <div class="text-sm font-semibold">Sensor</div>
              <div class="text-sm text-muted">High anomaly score detected on TCP traffic.</div>
              <div class="text-xs text-muted mt-1">5 Minutes Ago</div>
            </div>
          </div>

          <div class="flex gap-3">
            <div class="h-10 w-10 rounded-full bg-surface2 grid place-items-center font-semibold">M</div>
            <div>
              <div class="text-sm font-semibold">Model</div>
              <div class="text-sm text-muted">Encoder loaded: simclr-encoder-v1.</div>
              <div class="text-xs text-muted mt-1">6 Minutes Ago</div>
            </div>
          </div>
        </div>
      </div>

      {{-- Detection Analytics --}}
      <div class="rounded-3xl bg-surface shadow-soft p-6">
        <div class="text-xl font-semibold">Detection Analytics</div>

        <div class="mt-5 space-y-4">
          <div class="flex items-center justify-between rounded-3xl bg-surface2 border border-app p-4">
            <div class="flex items-center gap-3">
              <div class="h-12 w-12 rounded-2xl bg-primary-soft grid place-items-center">
                <x-lucide-radar class="h-6 w-6 text-primary" />
              </div>
              <div>
                <div class="text-sm font-semibold">REALTIME SCANS</div>
                <div class="text-sm text-muted">Last 24 Hours</div>
              </div>
            </div>
            <div class="text-right">
              <div class="text-sm text-muted">+39%</div>
              <div class="text-xl font-semibold">3849</div>
            </div>
          </div>

          <div class="flex items-center justify-between rounded-3xl bg-surface2 border border-app p-4">
            <div class="flex items-center gap-3">
              <div class="h-12 w-12 rounded-2xl bg-[rgba(248,113,113,0.15)] grid place-items-center">
                <x-lucide-layers class="h-6 w-6 text-[color:var(--danger)]" />
              </div>
              <div>
                <div class="text-sm font-semibold">BATCH JOBS</div>
                <div class="text-sm text-muted">Last 24 Hours</div>
              </div>
            </div>
            <div class="text-right">
              <div class="text-sm text-muted">−17%</div>
              <div class="text-xl font-semibold">1100</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection