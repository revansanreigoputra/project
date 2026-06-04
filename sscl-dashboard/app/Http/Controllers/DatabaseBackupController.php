<?php

namespace App\Http\Controllers;

use App\Models\DatabaseBackup;
use App\Models\DatabaseBackupSetting;
use App\Services\DatabaseBackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class DatabaseBackupController extends Controller
{
    public function index()
    {
        $setting = DatabaseBackupSetting::current();
        $backups = DatabaseBackup::with('user')
            ->latest()
            ->paginate(8);
        $completedBackups = DatabaseBackup::query()->where('status', 'completed');

        return view('pages.backup.index', [
            'backups' => $backups,
            'setting' => $setting,
            'stats' => [
                'total' => (clone $completedBackups)->count(),
                'size' => $this->formatBytes((clone $completedBackups)->sum('size_bytes')),
                'latest' => DatabaseBackup::query()->where('status', 'completed')->latest()->first(),
            ],
            'formats' => DatabaseBackupSetting::FORMATS,
            'frequencies' => DatabaseBackupSetting::FREQUENCIES,
            'weekdays' => [
                1 => 'Senin',
                2 => 'Selasa',
                3 => 'Rabu',
                4 => 'Kamis',
                5 => 'Jumat',
                6 => 'Sabtu',
                0 => 'Minggu',
            ],
        ]);
    }

    public function store(Request $request, DatabaseBackupService $backupService)
    {
        $validated = $request->validate([
            'format' => ['required', Rule::in(DatabaseBackupSetting::FORMATS)],
        ]);

        try {
            $backup = $backupService->create($validated['format'], $request->user(), 'manual');
        } catch (Throwable $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage() ?: 'Backup database gagal diproses.',
                ], 422);
            }

            return back()
                ->with('toast_type', 'danger')
                ->with('toast_title', 'Backup Gagal')
                ->with('toast_message', $exception->getMessage() ?: 'Backup database gagal diproses.');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Backup database berhasil dibuat.',
                'backup' => $this->backupPayload($backup),
            ]);
        }

        return back()
            ->with('toast_type', 'success')
            ->with('toast_title', 'Backup Selesai')
            ->with('toast_message', 'File '.$backup->file_name.' berhasil dibuat.');
    }

    public function updateSchedule(Request $request)
    {
        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'frequency' => ['required', Rule::in(DatabaseBackupSetting::FREQUENCIES)],
            'scheduled_time' => ['required', 'date_format:H:i'],
            'scheduled_weekday' => ['nullable', 'integer', 'between:0,6'],
            'scheduled_month_day' => ['nullable', 'integer', 'between:1,28'],
            'format' => ['required', Rule::in(DatabaseBackupSetting::FORMATS)],
            'retention_days' => ['required', 'integer', 'between:1,365'],
        ]);

        $setting = DatabaseBackupSetting::current();
        $setting->fill([
            'enabled' => $request->boolean('enabled'),
            'frequency' => $validated['frequency'],
            'scheduled_time' => $validated['scheduled_time'],
            'scheduled_weekday' => $validated['frequency'] === 'weekly'
                ? (int) ($validated['scheduled_weekday'] ?? 1)
                : null,
            'scheduled_month_day' => $validated['frequency'] === 'monthly'
                ? (int) ($validated['scheduled_month_day'] ?? 1)
                : null,
            'format' => $validated['format'],
            'retention_days' => (int) $validated['retention_days'],
            'updated_by' => $request->user()?->getAuthIdentifier(),
        ]);
        $setting->next_run_at = $setting->enabled ? $setting->nextRunAfter(now()) : null;
        $setting->save();

        return back()
            ->with('toast_type', 'success')
            ->with('toast_title', 'Jadwal Backup Diperbarui')
            ->with('toast_message', $setting->enabled ? 'Auto-backup aktif.' : 'Auto-backup dinonaktifkan.');
    }

    public function download(DatabaseBackup $backup)
    {
        if ($backup->status !== 'completed' || ! Storage::disk($backup->disk ?: 'local')->exists($backup->path)) {
            return back()
                ->with('toast_type', 'danger')
                ->with('toast_title', 'Download Gagal')
                ->with('toast_message', 'File backup tidak ditemukan di storage.');
        }

        return Storage::disk($backup->disk ?: 'local')->download($backup->path, $backup->file_name);
    }

    private function backupPayload(DatabaseBackup $backup): array
    {
        return [
            'id' => $backup->id,
            'file_name' => $backup->file_name,
            'format' => $backup->format,
            'status' => $backup->status,
            'size' => $backup->sizeForHumans(),
            'completed_at' => $backup->completed_at?->timezone(DatabaseBackupSetting::TIMEZONE)->format('d/m/Y H:i'),
            'download_url' => route('backup.download', $backup),
        ];
    }

    private function formatBytes(int|float $bytes): string
    {
        $bytes = max(0, (float) $bytes);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return number_format($bytes, $index === 0 ? 0 : 2, ',', '.').' '.$units[$index];
    }
}
