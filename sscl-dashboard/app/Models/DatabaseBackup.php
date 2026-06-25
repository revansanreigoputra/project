<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseBackup extends Model
{
    // Kolom backup yang diisi saat proses backup berjalan.
    protected $fillable = [
        'user_id',
        'disk',
        'file_name',
        'path',
        'format',
        'size_bytes',
        'status',
        'trigger',
        'started_at',
        'completed_at',
        'error_message',
        'meta',
    ];

    // Casting metadata backup, waktu proses, dan ukuran file.
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    // Relasi ke user yang menjalankan backup manual.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Format ukuran file backup agar enak dibaca di UI.
    public function sizeForHumans(): string
    {
        $bytes = max(0, (int) $this->size_bytes);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return number_format($bytes, $index === 0 ? 0 : 2, ',', '.').' '.$units[$index];
    }
}
