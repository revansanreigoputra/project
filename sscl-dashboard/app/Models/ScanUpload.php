<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScanUpload extends Model
{
    // Kolom upload yang boleh diisi dari service penyimpanan file.
    protected $fillable = [
        'user_id',
        'original_name',
        'stored_name',
        'path',
        'mime_type',
        'extension',
        'size',
        'status',
        'source',
        'source_name',
        'external_reference',
    ];

    // Casting ukuran file supaya selalu terbaca sebagai integer.
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    // Label sumber upload untuk tampilan user.
    public function sourceLabel(): string
    {
        if ($this->source === 'api') {
            return 'API UPA-TIK';
        }

        return 'Manual';
    }

    // Relasi ke user pemilik upload.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Relasi ke semua hasil deteksi dari upload ini.
    public function detectionResults(): HasMany
    {
        return $this->hasMany(DetectionResult::class);
    }

    // Scope upload milik user tertentu.
    public function scopeForUser(Builder $query, $user): Builder
    {
        return $user
            ? $query->where('user_id', $user->getAuthIdentifier())
            : $query->whereNull('user_id');
    }

    // Scope visibilitas: user biasa hanya lihat data sendiri, admin akses tertentu bisa lihat semua.
    public function scopeVisibleTo(Builder $query, $user): Builder
    {
        if ($user && $user->can('history.view-any')) {
            return $query;
        }

        return $query->forUser($user);
    }
}
