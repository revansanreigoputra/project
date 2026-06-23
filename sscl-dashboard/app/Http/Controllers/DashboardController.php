<?php

namespace App\Http\Controllers;

use App\Models\DetectionResult;
use App\Models\ScanUpload;
use App\Models\User;
use App\Support\DetectionResultTable;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $canViewAll = $user?->can('dashboard.view-all') || $user?->can('history.view-any');
        $detectionScope = DetectionResult::query()->completed();
        $uploadScope = ScanUpload::query();

        if (! $canViewAll) {
            $detectionScope->forUser($user);
            $uploadScope->forUser($user);
        }

        $detectionResult = (clone $detectionScope)
            ->with(['scanUpload.user', 'user'])
            ->latest('processed_at')
            ->latest('id')
            ->first();
        $prediction = $detectionResult?->toPredictionArray();

        if ($detectionResult && $prediction) {
            $prediction['summary'] = DetectionResultTable::summary($detectionResult);
        }

        $completedResults = (clone $detectionScope)
            ->get([
                'id',
                'user_id',
                'scan_upload_id',
                'file_name',
                'processed_at',
                'total',
                'normal_count',
                'malware_count',
                'unknown_count',
                'average_confidence',
                'summary',
                'created_at',
            ]);
        $totalRows = (int) $completedResults->sum('total');
        $normalRows = (int) $completedResults->sum('normal_count');
        $malwareRows = (int) $completedResults->sum('malware_count');
        $unknownRows = (int) $completedResults->sum('unknown_count');
        $totalDetections = $completedResults->count();
        $averageConfidence = $completedResults->avg('average_confidence') ?: 0;
        $totalUploads = (clone $uploadScope)->count();
        $totalUsers = $canViewAll ? User::query()->count() : 1;

        $recentDetections = (clone $detectionScope)
            ->with(['scanUpload.user', 'user'])
            ->latest('processed_at')
            ->latest('id')
            ->take(8)
            ->get()
            ->map(fn (DetectionResult $result) => $this->detectionDetail($result))
            ->values();

        $latestActivities = (clone $uploadScope)
            ->with([
                'user',
                'detectionResults' => fn ($query) => $query
                    ->latest('processed_at')
                    ->latest('id'),
            ])
            ->latest('updated_at')
            ->latest('id')
            ->take(8)
            ->get()
            ->map(function (ScanUpload $upload) {
                $result = $upload->detectionResults->first();

                return [
                    'title' => $upload->original_name,
                    'meta' => $upload->sourceLabel().' - '.($result?->status ? 'Deteksi '.$result->status : 'Upload '.$this->displayUploadStatus($upload->status))
                        .' oleh '.($upload->user?->name ?? '-'),
                    'time' => $this->formatDateTime($result?->processed_at ?? $upload->updated_at),
                    'tone' => $result?->malware_count > 0 ? 'danger' : ($result?->status === 'Selesai' ? 'accent' : 'warning'),
                    'url' => $result?->status === 'Selesai'
                        ? route('hasildeteksi.show', $result)
                        : route('riwayat.show', $upload),
                ];
            })
            ->values();

        $trend = $this->monthlyTrend($completedResults);
        $labelStats = $this->labelStats($completedResults);
        $dashboardDetails = [
            'detections' => [
                'title' => 'Detail Total Deteksi',
                'description' => 'Deteksi selesai terbaru dalam cakupan dashboard.',
                'items' => $recentDetections->map(fn (array $item) => [
                    'title' => $item['file_name'],
                    'meta' => $item['processed_at'].' - '.$item['source_label'].' - '.$item['total'].' baris',
                    'value' => $item['status'],
                    'url' => $item['url'],
                ])->all(),
            ],
            'malware' => [
                'title' => 'Detail Malware',
                'description' => 'Hasil terbaru yang memiliki baris malware.',
                'items' => $recentDetections
                    ->filter(fn (array $item) => $item['malware'] > 0)
                    ->map(fn (array $item) => [
                        'title' => $item['file_name'],
                        'meta' => $item['processed_at'].' - '.$item['malware'].' malware',
                        'value' => $item['malware_percent'].'%',
                        'url' => $item['url'],
                    ])
                    ->values()
                    ->all(),
            ],
            'normal' => [
                'title' => 'Detail Non-Malware',
                'description' => 'Hasil terbaru dengan data normal terbanyak.',
                'items' => $recentDetections
                    ->sortByDesc('normal')
                    ->map(fn (array $item) => [
                        'title' => $item['file_name'],
                        'meta' => $item['processed_at'].' - '.$item['normal'].' normal',
                        'value' => $item['normal_percent'].'%',
                        'url' => $item['url'],
                    ])
                    ->values()
                    ->all(),
            ],
            'users' => [
                'title' => 'Detail Pengguna',
                'description' => 'Pengguna terbaru yang tersimpan dalam sistem.',
                'items' => ($canViewAll
                    ? User::query()->with('roles')->latest()->take(8)->get()
                    : collect([$user])->filter())
                    ->map(fn (User $user) => [
                        'title' => $user->name,
                        'meta' => $user->email,
                        'value' => $user->roles->pluck('name')->implode(', ') ?: 'Tanpa role',
                        'url' => null,
                    ])
                    ->values()
                    ->all(),
            ],
        ];

        return view('pages.dashboard.index', [
            'prediction' => $prediction,
            'stats' => [
                'total_detections' => $totalDetections,
                'total_uploads' => $totalUploads,
                'total_rows' => $totalRows,
                'normal_rows' => $normalRows,
                'malware_rows' => $malwareRows,
                'unknown_rows' => $unknownRows,
                'normal_percent' => $totalRows > 0 ? round(($normalRows / $totalRows) * 100, 2) : 0,
                'malware_percent' => $totalRows > 0 ? round(($malwareRows / $totalRows) * 100, 2) : 0,
                'average_confidence' => round((float) $averageConfidence, 2),
                'total_users' => $totalUsers,
            ],
            'trend' => $trend,
            'labelStats' => $labelStats,
            'recentDetections' => $recentDetections,
            'latestActivities' => $latestActivities,
            'dashboardDetails' => $dashboardDetails,
            'scopeLabel' => $canViewAll ? 'Seluruh sistem' : 'Data milik Anda',
        ]);
    }

    private function detectionDetail(DetectionResult $result): array
    {
        $summary = DetectionResultTable::summary($result);
        $total = (int) ($result->total ?? 0);
        $normal = (int) ($summary['normal'] ?? 0);
        $malware = (int) ($summary['malware'] ?? 0);

        return [
            'id' => $result->id,
            'file_name' => $result->file_name ?? $result->scanUpload?->original_name ?? '-',
            'source' => $result->scanUpload?->source ?? 'manual',
            'source_label' => $result->scanUpload?->sourceLabel() ?? 'Manual',
            'processed_at' => $this->formatDateTime($result->processed_at ?? $result->created_at),
            'total' => $total,
            'normal' => $normal,
            'malware' => $malware,
            'unknown' => (int) ($summary['unknown'] ?? 0),
            'normal_percent' => $total > 0 ? number_format(($normal / $total) * 100, 2, ',', '.') : '0,00',
            'malware_percent' => $total > 0 ? number_format(($malware / $total) * 100, 2, ',', '.') : '0,00',
            'average_confidence' => number_format((float) ($summary['average_confidence'] ?? $result->average_confidence ?? 0), 2, ',', '.'),
            'status' => $result->status,
            'user' => $result->user?->name ?? $result->scanUpload?->user?->name ?? '-',
            'url' => route('hasildeteksi.show', $result),
        ];
    }

    private function monthlyTrend($completedResults): array
    {
        $months = collect(range(5, 0))
            ->map(fn (int $offset) => now('Asia/Jakarta')->subMonths($offset)->startOfMonth());
        $startAt = $months->first()->copy()->utc();
        $grouped = $completedResults
            ->filter(fn (DetectionResult $result) => $result->processed_at && $result->processed_at->gte($startAt))
            ->groupBy(fn (DetectionResult $result) => $result->processed_at->copy()->timezone('Asia/Jakarta')->format('Y-m'));

        return $months
            ->map(function (Carbon $month) use ($grouped) {
                $key = $month->format('Y-m');
                $items = $grouped->get($key, collect());

                return [
                    'label' => $month->translatedFormat('M'),
                    'detections' => $items->count(),
                    'normal' => (int) $items->sum('normal_count'),
                    'malware' => (int) $items->sum('malware_count'),
                    'total' => (int) $items->sum('total'),
                ];
            })
            ->values()
            ->all();
    }

    private function labelStats($completedResults): array
    {
        $labels = [];

        foreach ($completedResults as $result) {
            $summaryLabels = $result->summary['labels'] ?? [];

            if ($summaryLabels === []) {
                $summaryLabels = [
                    'Normal' => (int) $result->normal_count,
                    'Malware' => (int) $result->malware_count,
                    'Unknown' => (int) $result->unknown_count,
                ];
            }

            foreach ($summaryLabels as $label => $count) {
                $count = (int) $count;

                if ($count <= 0) {
                    continue;
                }

                $labels[$label] = ($labels[$label] ?? 0) + $count;
            }
        }

        arsort($labels);

        return collect($labels)
            ->take(8)
            ->map(fn (int $count, string $label) => [
                'label' => $label,
                'count' => $count,
                'tone' => $label === 'Normal' ? 'accent' : ($label === 'Unknown' ? 'warning' : 'danger'),
            ])
            ->values()
            ->all();
    }

    private function displayUploadStatus(?string $status): string
    {
        return match ($status) {
            'uploaded' => 'tersimpan',
            'processing' => 'diproses',
            'processed' => 'selesai',
            'failed' => 'gagal',
            default => (string) ($status ?: '-'),
        };
    }

    private function formatDateTime($dateTime): string
    {
        return $dateTime
            ? $dateTime->copy()->timezone('Asia/Jakarta')->format('d/m/Y H:i')
            : '-';
    }
}
