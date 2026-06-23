<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScanUpload extends Model
{
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

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function sourceLabel(): string
    {
        if ($this->source === 'api') {
            return $this->source_name ?: 'API UPA-TIK';
        }

        return 'Manual';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scanJobs(): HasMany
    {
        return $this->hasMany(DetectionResult::class);
    }

    public function detectionResults(): HasMany
    {
        return $this->hasMany(DetectionResult::class);
    }

    public function scopeForUser(Builder $query, $user): Builder
    {
        return $user
            ? $query->where('user_id', $user->getAuthIdentifier())
            : $query->whereNull('user_id');
    }

    public function scopeVisibleTo(Builder $query, $user): Builder
    {
        if ($user && $user->can('history.view-any')) {
            return $query;
        }

        return $query->forUser($user);
    }
}
