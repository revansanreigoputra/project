<?php

namespace App\Mail;

use App\Models\DetectionResult;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DetectionCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    // Siapkan data hasil deteksi yang akan dipakai saat email dirender.
    public function __construct(
        public DetectionResult $detectionResult,
        public array $summary,
        public ?float $durationSeconds = null,
    ) {
        $this->detectionResult->loadMissing(['scanUpload.user', 'user']);
    }

    // Atur subject email berdasarkan nama file hasil deteksi.
    public function envelope(): Envelope
    {
        $fileName = $this->detectionResult->file_name
            ?: $this->detectionResult->scanUpload?->original_name
            ?: 'file';

        return new Envelope(
            subject: 'Ringkasan Hasil Deteksi Malware - '.$fileName,
        );
    }

    // Kirim data ringkasan ke template email notifikasi.
    public function content(): Content
    {
        return new Content(
            view: 'emails.detection-completed',
            with: [
                'fileName' => $this->fileName(),
                'processedAt' => $this->processedAt(),
                'duration' => $this->formattedDuration(),
                'total' => (int) $this->detectionResult->total,
                'normal' => (int) ($this->summary['normal'] ?? $this->detectionResult->normal_count ?? 0),
                'malware' => (int) ($this->summary['malware'] ?? $this->detectionResult->malware_count ?? 0),
                'unknown' => (int) ($this->summary['unknown'] ?? $this->detectionResult->unknown_count ?? 0),
                'normalPercent' => (float) ($this->summary['normal_percent'] ?? $this->detectionResult->normal_percent ?? 0),
                'malwarePercent' => (float) ($this->summary['malware_percent'] ?? $this->detectionResult->malware_percent ?? 0),
                'averageConfidence' => (float) ($this->summary['average_confidence'] ?? $this->detectionResult->average_confidence ?? 0),
                'processStatus' => $this->detectionResult->status,
                'inspectionStatus' => $this->inspectionStatus(),
                'petugasName' => $this->petugasName(),
                'petugasEmail' => $this->petugasEmail(),
                'reviewUrl' => route('hasildeteksi.show', $this->detectionResult),
            ],
        );
    }

    // Ambil nama file utama dari hasil deteksi atau upload sumber.
    private function fileName(): string
    {
        return $this->detectionResult->file_name
            ?: $this->detectionResult->scanUpload?->original_name
            ?: '-';
    }

    // Format waktu proses untuk isi email.
    private function processedAt(): string
    {
        $processedAt = $this->detectionResult->processed_at ?? $this->detectionResult->created_at;

        return $processedAt
            ? $processedAt->copy()->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB'
            : '-';
    }

    // Format durasi proses deteksi menjadi menit dan detik.
    private function formattedDuration(): string
    {
        if ($this->durationSeconds === null) {
            return '-';
        }

        $seconds = max(0, (int) round($this->durationSeconds));
        $minutes = intdiv($seconds, 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes > 0) {
            return $minutes.' menit '.$remainingSeconds.' detik';
        }

        return $remainingSeconds.' detik';
    }

    // Tentukan status inspeksi dari jumlah malware yang ditemukan.
    private function inspectionStatus(): string
    {
        $malware = (int) ($this->summary['malware'] ?? $this->detectionResult->malware_count ?? 0);

        return $malware > 0 ? 'Malware terdeteksi' : 'Tidak ditemukan malware';
    }

    // Ambil nama petugas dari relasi hasil deteksi atau upload.
    private function petugasName(): string
    {
        return $this->detectionResult->user?->name
            ?: $this->detectionResult->scanUpload?->user?->name
            ?: '-';
    }

    // Ambil email petugas dari relasi hasil deteksi atau upload.
    private function petugasEmail(): string
    {
        return $this->detectionResult->user?->email
            ?: $this->detectionResult->scanUpload?->user?->email
            ?: '-';
    }
}
