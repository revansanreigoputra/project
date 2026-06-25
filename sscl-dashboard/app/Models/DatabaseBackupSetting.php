<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseBackupSetting extends Model
{
    // Opsi format, frekuensi, dan timezone utama untuk fitur backup database.
    public const FORMATS = ['sql', 'zip'];

    public const FREQUENCIES = ['daily', 'weekly', 'monthly'];

    public const TIMEZONE = 'Asia/Jakarta';

    // Kolom setting backup yang bisa diubah dari halaman admin/petugas.
    protected $fillable = [
        'enabled',
        'frequency',
        'scheduled_time',
        'scheduled_weekday',
        'scheduled_month_day',
        'format',
        'retention_days',
        'next_run_at',
        'last_run_at',
        'updated_by',
    ];

    // Casting setting jadwal supaya boolean, angka, dan tanggal terbaca tepat.
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'scheduled_weekday' => 'integer',
            'scheduled_month_day' => 'integer',
            'retention_days' => 'integer',
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
        ];
    }

    // Ambil setting backup aktif, atau buat default pertama kali.
    public static function current(): self
    {
        return static::query()->first()
            ?? static::query()->create([
                'enabled' => false,
                'frequency' => 'daily',
                'scheduled_time' => '02:00:00',
                'scheduled_weekday' => 1,
                'scheduled_month_day' => 1,
                'format' => 'zip',
                'retention_days' => 30,
            ]);
    }

    // Relasi ke user terakhir yang mengubah setting backup.
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Hitung jadwal backup berikutnya dari waktu acuan yang diberikan.
    public function nextRunAfter(?CarbonInterface $after = null)
    {
        $base = ($after ? $after->copy() : now())->timezone(self::TIMEZONE);
        $time = $this->normalizedTime();
        [$hour, $minute] = array_map('intval', explode(':', $time));
        $candidate = $base->copy()->setTime($hour, $minute);

        if ($this->frequency === 'weekly') {
            $weekday = $this->scheduled_weekday ?? 1;
            $candidate = $base->copy()->setTime($hour, $minute);
            $daysUntilWeekday = ($weekday - $candidate->dayOfWeek + 7) % 7;
            $candidate->addDays($daysUntilWeekday);
        }

        if ($this->frequency === 'monthly') {
            $day = min(28, max(1, (int) ($this->scheduled_month_day ?? 1)));
            $candidate = $base->copy()->day($day)->setTime($hour, $minute);
        }

        while ($candidate->lessThanOrEqualTo($base)) {
            match ($this->frequency) {
                'weekly' => $candidate->addWeek(),
                'monthly' => $candidate->addMonthNoOverflow(),
                default => $candidate->addDay(),
            };
        }

        return $candidate->utc();
    }

    // Normalisasi jam jadwal menjadi format HH:MM.
    public function normalizedTime(): string
    {
        $value = (string) $this->scheduled_time;

        if (preg_match('/^\d{2}:\d{2}/', $value, $matches)) {
            return $matches[0];
        }

        return '02:00';
    }
}
