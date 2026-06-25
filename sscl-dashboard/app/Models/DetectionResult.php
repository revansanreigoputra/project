<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetectionResult extends Model
{
    // Kolom hasil deteksi yang boleh diisi dari service prediksi.
    protected $fillable = [
        'user_id',
        'scan_upload_id',
        'file_name',
        'processed_at',
        'total',
        'normal_count',
        'malware_count',
        'unknown_count',
        'normal_percent',
        'malware_percent',
        'average_confidence',
        'status',
        'error_message',
        'summary',
        'results',
        'results_path',
    ];

    // Casting hasil deteksi supaya angka, tanggal, dan JSON langsung siap dipakai.
    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'total' => 'integer',
            'normal_count' => 'integer',
            'malware_count' => 'integer',
            'unknown_count' => 'integer',
            'normal_percent' => 'float',
            'malware_percent' => 'float',
            'average_confidence' => 'float',
            'summary' => 'array',
            'results' => 'array',
        ];
    }

    // Relasi ke user yang menjalankan proses deteksi.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Relasi ke file upload sumber dari hasil deteksi.
    public function scanUpload(): BelongsTo
    {
        return $this->belongsTo(ScanUpload::class);
    }

    // Scope hasil deteksi milik user tertentu.
    public function scopeForUser(Builder $query, $user): Builder
    {
        return $user
            ? $query->where('user_id', $user->getAuthIdentifier())
            : $query->whereNull('user_id');
    }

    // Scope visibilitas hasil deteksi berdasarkan permission user.
    public function scopeVisibleTo(Builder $query, $user): Builder
    {
        if ($user && $user->can('history.view-any')) {
            return $query;
        }

        return $query->forUser($user);
    }

    // Scope hasil deteksi yang sudah selesai diproses.
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'Selesai');
    }

    // Ubah model hasil deteksi jadi format prediksi untuk view.
    public function toPredictionArray(): array
    {
        $summary = $this->normalizedSummary();

        return [
            'scan_upload_id' => $this->scan_upload_id,
            'file_name' => $this->file_name ?? $this->scanUpload?->original_name ?? '-',
            'source' => $this->scanUpload?->source ?? 'manual',
            'source_label' => $this->scanUpload?->sourceLabel() ?? 'Manual',
            'processed_at' => $this->formattedProcessedAt(),
            'total' => $this->total,
            'summary' => $summary,
            'results' => $this->results ?? [],
        ];
    }

    // Ubah hasil deteksi jadi format ringkas untuk riwayat.
    public function toHistoryArray(): array
    {
        $summary = $this->normalizedSummary();

        return [
            'processed_at' => $this->formattedProcessedAt(),
            'file_name' => $this->file_name ?? $this->scanUpload?->original_name ?? '-',
            'total' => $this->total,
            'normal' => $summary['normal'],
            'malware' => $summary['malware'],
            'unknown' => $summary['unknown'],
            'normal_percent' => $summary['normal_percent'],
            'malware_percent' => $summary['malware_percent'],
            'average_confidence' => $this->average_confidence,
            'labels' => $summary['labels'],
            'malware_labels' => $summary['malware_labels'],
            'status' => $this->status,
        ];
    }

    // Normalisasi summary agar data lama, data baru, dan fallback hitungan tetap konsisten.
    private function normalizedSummary(): array
    {
        $summary = $this->summary ?: [];
        $labels = $summary['labels'] ?? [];
        $normalLabel = (string) ($summary['normal_label'] ?? 'Normal');

        if ($labels === [] && is_array($this->results)) {
            foreach ($this->results as $result) {
                $label = (string) ($result['label'] ?? 'Unknown');
                $labels[$label] = ($labels[$label] ?? 0) + 1;
            }
        }

        if ($labels === []) {
            $normalFallback = $summary['normal'] ?? $summary['benign'] ?? $this->normal_count ?? 0;
            $malwareFallback = $summary['malware'] ?? $summary['malicious'] ?? $this->malware_count ?? 0;
            $unknownFallback = $summary['unknown'] ?? $this->unknown_count ?? 0;

            $labels = array_filter([
                $normalLabel => (int) $normalFallback,
                'Malware' => (int) $malwareFallback,
                'Unknown' => (int) $unknownFallback,
            ], fn (int $count) => $count > 0);
        }

        $normal = (int) ($summary['normal'] ?? $labels[$normalLabel] ?? $this->normal_count ?? 0);
        $unknown = (int) ($summary['unknown'] ?? $labels['Unknown'] ?? $this->unknown_count ?? 0);
        $total = (int) ($this->total ?: array_sum($labels));
        $malwareLabels = collect($labels)
            ->reject(fn ($count, string $label) => in_array($label, [$normalLabel, 'Unknown'], true))
            ->all();
        $malwareFallback = (int) ($this->malware_count ?? 0);
        $malware = array_key_exists('malware', $summary)
            ? (int) $summary['malware']
            : (int) (array_sum($malwareLabels) ?: $malwareFallback);

        return [
            'normal_label' => $normalLabel,
            'normal' => $normal,
            'malware' => $malware,
            'unknown' => $unknown,
            'normal_percent' => $total > 0 ? round(($normal / $total) * 100, 2) : 0,
            'malware_percent' => $total > 0 ? round(($malware / $total) * 100, 2) : 0,
            'average_confidence' => (float) ($summary['average_confidence'] ?? $this->average_confidence ?? 0),
            'labels' => $labels,
            'malware_labels' => $malwareLabels,
            'guardrail_applied_count' => (int) ($summary['guardrail_applied_count'] ?? 0),
            'class_names' => $summary['class_names'] ?? [],
            'feature_columns' => $summary['feature_columns'] ?? [],
            'standardized_columns' => $summary['standardized_columns'] ?? [],
        ];
    }

    // Format waktu proses ke WIB untuk tampilan.
    private function formattedProcessedAt(): string
    {
        $processedAt = $this->processed_at ?? $this->created_at;

        return $processedAt
            ? $processedAt->copy()->timezone('Asia/Jakarta')->format('d/m/Y H:i')
            : '-';
    }
}
