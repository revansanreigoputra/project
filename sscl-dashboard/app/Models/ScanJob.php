<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanJob extends Model
{
    protected $fillable = [
        'user_id',
        'scan_upload_id',
        'file_name',
        'processed_at',
        'total',
        'benign_count',
        'malicious_count',
        'unknown_count',
        'benign_percent',
        'malicious_percent',
        'average_confidence',
        'status',
        'error_message',
        'summary',
        'results',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'total' => 'integer',
            'benign_count' => 'integer',
            'malicious_count' => 'integer',
            'unknown_count' => 'integer',
            'benign_percent' => 'float',
            'malicious_percent' => 'float',
            'average_confidence' => 'float',
            'summary' => 'array',
            'results' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scanUpload(): BelongsTo
    {
        return $this->belongsTo(ScanUpload::class);
    }

    public function scopeForUser(Builder $query, $user): Builder
    {
        return $user
            ? $query->where('user_id', $user->getAuthIdentifier())
            : $query->whereNull('user_id');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'Selesai');
    }

    public function toPredictionArray(): array
    {
        $summary = $this->summary ?: [
            'benign' => $this->benign_count,
            'malicious' => $this->malicious_count,
            'unknown' => $this->unknown_count,
            'benign_percent' => $this->benign_percent,
            'malicious_percent' => $this->malicious_percent,
            'average_confidence' => $this->average_confidence,
        ];

        return [
            'file_name' => $this->file_name ?? $this->scanUpload?->original_name ?? '-',
            'processed_at' => $this->formattedProcessedAt(),
            'total' => $this->total,
            'summary' => $summary,
            'results' => $this->results ?? [],
        ];
    }

    public function toHistoryArray(): array
    {
        return [
            'processed_at' => $this->formattedProcessedAt(),
            'file_name' => $this->file_name ?? $this->scanUpload?->original_name ?? '-',
            'total' => $this->total,
            'benign' => $this->benign_count,
            'malicious' => $this->malicious_count,
            'benign_percent' => $this->benign_percent,
            'malicious_percent' => $this->malicious_percent,
            'average_confidence' => $this->average_confidence,
            'status' => $this->status,
        ];
    }

    private function formattedProcessedAt(): string
    {
        $processedAt = $this->processed_at ?? $this->created_at;

        return $processedAt
            ? $processedAt->copy()->timezone('Asia/Jakarta')->format('d/m/Y H:i')
            : '-';
    }
}
