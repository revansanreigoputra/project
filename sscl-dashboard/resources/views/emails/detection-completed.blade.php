<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Ringkasan Hasil Deteksi Malware</title>
</head>
<body style="margin: 0; background: #f4f7fb; color: #111827; font-family: Arial, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #f4f7fb; padding: 28px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellspacing="0" cellpadding="0" style="width: 640px; max-width: 94%; background: #ffffff; border: 1px solid #dbe3ef;">
                    <tr>
                        <td style="padding: 28px 32px 18px;">
                            <p style="margin: 0 0 8px; color: #2563eb; font-size: 13px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;">
                                Deteksi selesai
                            </p>
                            <h1 style="margin: 0; color: #0f172a; font-size: 24px; line-height: 1.35;">
                                Ringkasan Hasil Deteksi Malware
                            </h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 32px 20px;">
                            <p style="margin: 0; color: #475569; font-size: 15px; line-height: 1.7;">
                                Proses deteksi untuk file <strong>{{ $fileName }}</strong> sudah selesai. Berikut ringkasan hasil pemeriksaan yang tersimpan di sistem.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 32px 24px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse: collapse; border: 1px solid #e2e8f0;">
                                <tr>
                                    <td style="padding: 12px 14px; width: 52%; border-bottom: 1px solid #e2e8f0; color: #64748b; font-size: 14px;">Total data diperiksa</td>
                                    <td style="padding: 12px 14px; border-bottom: 1px solid #e2e8f0; color: #0f172a; font-size: 14px; font-weight: 700;">{{ number_format($total, 0, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 14px; border-bottom: 1px solid #e2e8f0; color: #64748b; font-size: 14px;">Data normal</td>
                                    <td style="padding: 12px 14px; border-bottom: 1px solid #e2e8f0; color: #0f172a; font-size: 14px; font-weight: 700;">{{ number_format($normal, 0, ',', '.') }} ({{ number_format($normalPercent, 2, ',', '.') }}%)</td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 14px; border-bottom: 1px solid #e2e8f0; color: #64748b; font-size: 14px;">Malware terdeteksi</td>
                                    <td style="padding: 12px 14px; border-bottom: 1px solid #e2e8f0; color: #b91c1c; font-size: 14px; font-weight: 700;">{{ number_format($malware, 0, ',', '.') }} ({{ number_format($malwarePercent, 2, ',', '.') }}%)</td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 14px; border-bottom: 1px solid #e2e8f0; color: #64748b; font-size: 14px;">Data tidak diketahui</td>
                                    <td style="padding: 12px 14px; border-bottom: 1px solid #e2e8f0; color: #0f172a; font-size: 14px; font-weight: 700;">{{ number_format($unknown, 0, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 14px; border-bottom: 1px solid #e2e8f0; color: #64748b; font-size: 14px;">Rata-rata confidence</td>
                                    <td style="padding: 12px 14px; border-bottom: 1px solid #e2e8f0; color: #0f172a; font-size: 14px; font-weight: 700;">{{ number_format($averageConfidence, 2, ',', '.') }}%</td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 14px; border-bottom: 1px solid #e2e8f0; color: #64748b; font-size: 14px;">Waktu proses deteksi</td>
                                    <td style="padding: 12px 14px; border-bottom: 1px solid #e2e8f0; color: #0f172a; font-size: 14px; font-weight: 700;">{{ $processedAt }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 14px; border-bottom: 1px solid #e2e8f0; color: #64748b; font-size: 14px;">Durasi proses</td>
                                    <td style="padding: 12px 14px; border-bottom: 1px solid #e2e8f0; color: #0f172a; font-size: 14px; font-weight: 700;">{{ $duration }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 14px; border-bottom: 1px solid #e2e8f0; color: #64748b; font-size: 14px;">Status proses</td>
                                    <td style="padding: 12px 14px; border-bottom: 1px solid #e2e8f0; color: #0f172a; font-size: 14px; font-weight: 700;">{{ $processStatus }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 14px; color: #64748b; font-size: 14px;">Status hasil pemeriksaan</td>
                                    <td style="padding: 12px 14px; color: {{ $malware > 0 ? '#b91c1c' : '#047857' }}; font-size: 14px; font-weight: 700;">{{ $inspectionStatus }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 32px 30px;">
                            <p style="margin: 0; color: #64748b; font-size: 13px; line-height: 1.6;">
                                Email ini dikirim otomatis oleh sistem setelah proses deteksi selesai.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
