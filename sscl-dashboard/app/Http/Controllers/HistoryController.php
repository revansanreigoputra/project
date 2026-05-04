<?php

namespace App\Http\Controllers;

use App\Models\ScanJob;
use App\Models\ScanUpload;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleXMLElement;
use ZipArchive;

class HistoryController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'detection_status' => ['nullable', 'in:all,detected,undetected'],
        ]);

        $startAt = filled($filters['start_date'] ?? null)
            ? Carbon::parse($filters['start_date'], 'Asia/Jakarta')->startOfDay()->utc()
            : null;
        $endAt = filled($filters['end_date'] ?? null)
            ? Carbon::parse($filters['end_date'], 'Asia/Jakarta')->endOfDay()->utc()
            : null;
        $detectionStatus = $filters['detection_status'] ?? 'all';

        $query = ScanUpload::with([
            'scanJobs' => fn ($query) => $query
                ->latest('processed_at')
                ->latest('id'),
        ])
            ->forUser($request->user())
            ->when($detectionStatus === 'detected', function ($query) {
                $query->whereHas('scanJobs', fn ($jobQuery) => $jobQuery->completed());
            })
            ->when($detectionStatus === 'undetected', function ($query) {
                $query->whereDoesntHave('scanJobs', fn ($jobQuery) => $jobQuery->completed());
            })
            ->when($startAt || $endAt, function ($query) use ($startAt, $endAt) {
                $query->where(function ($dateQuery) use ($startAt, $endAt) {
                    $this->applyDateRange($dateQuery, 'created_at', $startAt, $endAt);
                    $dateQuery->orWhereHas('scanJobs', function ($jobQuery) use ($startAt, $endAt) {
                        $jobQuery->where(function ($jobDateQuery) use ($startAt, $endAt) {
                            $this->applyDateRange($jobDateQuery, 'processed_at', $startAt, $endAt);
                            $jobDateQuery->orWhere(function ($createdAtQuery) use ($startAt, $endAt) {
                                $this->applyDateRange($createdAtQuery, 'created_at', $startAt, $endAt);
                            });
                        });
                    });
                });
            });

        $totalUploads = (clone $query)->count();

        $history = $query
            ->latest('updated_at')
            ->latest('id')
            ->limit(25)
            ->get()
            ->map(fn (ScanUpload $scanUpload) => $this->toHistoryArray($scanUpload));

        return view('pages.riwayat.index', [
            'history' => $history,
            'filters' => [
                'start_date' => $filters['start_date'] ?? '',
                'end_date' => $filters['end_date'] ?? '',
                'detection_status' => $detectionStatus,
            ],
            'totalUploads' => $totalUploads,
        ]);
    }

    public function show(Request $request, ScanUpload $scanUpload)
    {
        $this->ensureUploadBelongsToUser($scanUpload, $request);

        $scanUpload->load([
            'scanJobs' => fn ($query) => $query
                ->latest('processed_at')
                ->latest('id'),
        ]);

        $mode = $request->query('mode') === 'after' ? 'after' : 'before';
        $latestScanJob = $scanUpload->scanJobs->first();
        $completedScanJob = $scanUpload->scanJobs->first(
            fn (ScanJob $scanJob) => $scanJob->status === 'Selesai'
        );

        return view('pages.riwayat.show', [
            'scanUpload' => $scanUpload,
            'latestScanJob' => $latestScanJob,
            'completedScanJob' => $completedScanJob,
            'historyItem' => $this->toHistoryArray($scanUpload),
            'mode' => $mode,
            'sourcePreview' => $mode === 'before' ? $this->previewUpload($scanUpload) : null,
            'prediction' => $completedScanJob?->toPredictionArray(),
        ]);
    }

    public function destroy(Request $request, ScanUpload $scanUpload)
    {
        $this->ensureUploadBelongsToUser($scanUpload, $request);

        $path = $scanUpload->path;
        $fileName = $scanUpload->original_name;
        $resultsPaths = $scanUpload->scanJobs()
            ->whereNotNull('results_path')
            ->pluck('results_path')
            ->all();

        DB::transaction(function () use ($scanUpload) {
            $scanUpload->scanJobs()->delete();
            $scanUpload->delete();
        });

        if ($path && Storage::exists($path)) {
            Storage::delete($path);
        }

        foreach ($resultsPaths as $resultsPath) {
            if ($resultsPath && Storage::exists($resultsPath)) {
                Storage::delete($resultsPath);
            }
        }

        return redirect()
            ->route('riwayat.index')
            ->with('toast_type', 'success')
            ->with('toast_title', 'Riwayat Terhapus')
            ->with('toast_message', 'Data "'.$fileName.'" berhasil dihapus sampai file asli dan hasil deteksinya.');
    }

    public function download(Request $request, ScanUpload $scanUpload, string $mode)
    {
        $this->ensureUploadBelongsToUser($scanUpload, $request);

        if ($mode === 'before') {
            return $this->downloadOriginalUpload($scanUpload);
        }

        return $this->downloadPrediction($scanUpload);
    }

    private function applyDateRange($query, string $column, ?Carbon $startAt, ?Carbon $endAt): void
    {
        if ($startAt && $endAt) {
            $query->whereBetween($column, [$startAt, $endAt]);

            return;
        }

        if ($startAt) {
            $query->where($column, '>=', $startAt);

            return;
        }

        if ($endAt) {
            $query->where($column, '<=', $endAt);
        }
    }

    private function ensureUploadBelongsToUser(ScanUpload $scanUpload, Request $request): void
    {
        abort_unless(
            (int) $scanUpload->user_id === (int) $request->user()?->getAuthIdentifier(),
            404
        );
    }

    private function toHistoryArray(ScanUpload $scanUpload): array
    {
        $latestScanJob = $scanUpload->scanJobs->first();
        $completedScanJob = $scanUpload->scanJobs->first(
            fn (ScanJob $scanJob) => $scanJob->status === 'Selesai'
        );
        $displayScanJob = $completedScanJob ?? $latestScanJob;

        return [
            'upload_id' => $scanUpload->id,
            'scan_job_id' => $displayScanJob?->id,
            'uploaded_at' => $this->formatDateTime($scanUpload->created_at),
            'processed_at' => $this->formatDateTime($displayScanJob?->processed_at ?? $displayScanJob?->created_at),
            'file_name' => $scanUpload->original_name,
            'size' => $scanUpload->size,
            'total' => $displayScanJob?->total ?? 0,
            'benign' => $displayScanJob?->benign_count ?? 0,
            'malicious' => $displayScanJob?->malicious_count ?? 0,
            'benign_percent' => $displayScanJob?->benign_percent ?? 0,
            'malicious_percent' => $displayScanJob?->malicious_percent ?? 0,
            'average_confidence' => $displayScanJob?->average_confidence ?? 0,
            'status' => $this->displayStatus($scanUpload, $displayScanJob),
            'can_view_after' => (bool) $completedScanJob,
            'can_download_before' => (bool) $scanUpload->path,
            'can_download_after' => (bool) $completedScanJob,
        ];
    }

    private function downloadOriginalUpload(ScanUpload $scanUpload)
    {
        if (! $scanUpload->path || ! Storage::exists($scanUpload->path)) {
            return back()
                ->with('toast_type', 'danger')
                ->with('toast_title', 'Download Gagal')
                ->with('toast_message', 'File asli tidak ditemukan di storage.');
        }

        return Storage::download($scanUpload->path, $scanUpload->original_name);
    }

    private function downloadPrediction(ScanUpload $scanUpload)
    {
        $scanJob = $scanUpload->scanJobs()
            ->completed()
            ->latest('processed_at')
            ->latest('id')
            ->first();

        if (! $scanJob) {
            return back()
                ->with('toast_type', 'danger')
                ->with('toast_title', 'Download Gagal')
                ->with('toast_message', 'Data ini belum memiliki hasil deteksi yang selesai.');
        }

        $fileName = $this->downloadFileName($scanUpload, 'hasil-deteksi', 'csv');

        if ($scanJob->results_path && Storage::exists($scanJob->results_path)) {
            return Storage::download($scanJob->results_path, $fileName, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        $results = collect($scanJob->results ?? []);

        return response()->streamDownload(function () use ($results) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'row_id',
                'label',
                'confidence_percent',
                'prob_benign_percent',
                'prob_malicious_percent',
            ]);

            foreach ($results as $index => $result) {
                fputcsv($handle, [
                    $result['row'] ?? $index,
                    $result['label'] ?? 'Unknown',
                    $this->csvPercent($result['confidence'] ?? 0),
                    $this->csvPercent(data_get($result, 'probabilities.Benign', 0)),
                    $this->csvPercent(data_get($result, 'probabilities.Malicious', 0)),
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function downloadFileName(ScanUpload $scanUpload, string $suffix, string $extension): string
    {
        $baseName = pathinfo($scanUpload->original_name, PATHINFO_FILENAME) ?: 'riwayat';
        $slug = Str::slug($baseName) ?: 'riwayat';

        return $slug.'-'.$suffix.'.'.$extension;
    }

    private function csvPercent($value): string
    {
        return number_format(((float) $value) * 100, 2, '.', '');
    }

    private function displayStatus(ScanUpload $scanUpload, ?ScanJob $scanJob): string
    {
        if ($scanJob) {
            return $scanJob->status;
        }

        return match ($scanUpload->status) {
            'uploaded' => 'Belum Deteksi',
            'processing' => 'Memproses',
            'processed' => 'Selesai',
            'failed' => 'Gagal',
            default => ucfirst((string) $scanUpload->status),
        };
    }

    private function formatDateTime($dateTime): string
    {
        return $dateTime
            ? $dateTime->copy()->timezone('Asia/Jakarta')->format('d/m/Y H:i')
            : '-';
    }

    private function previewUpload(ScanUpload $scanUpload, int $limit = 100): array
    {
        if (! $scanUpload->path || ! Storage::exists($scanUpload->path)) {
            return $this->emptyPreview('File asli tidak ditemukan di storage.');
        }

        $extension = strtolower($scanUpload->extension ?: pathinfo($scanUpload->original_name, PATHINFO_EXTENSION));

        try {
            if (in_array($extension, ['csv', 'txt'], true)) {
                return $this->previewDelimitedUpload($scanUpload, $limit);
            }

            if ($extension === 'xlsx') {
                return $this->previewXlsxUpload($scanUpload, $limit);
            }
        } catch (\Throwable $exception) {
            return $this->emptyPreview('Data asli belum bisa dipreview. Pastikan format file valid.');
        }

        return $this->emptyPreview('Preview data asli untuk format .'.($extension ?: '-').' belum didukung.');
    }

    private function previewDelimitedUpload(ScanUpload $scanUpload, int $limit): array
    {
        $contents = Storage::get($scanUpload->path);

        if (trim($contents) === '') {
            return $this->emptyPreview('File asli kosong.');
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);

        $delimiter = $this->detectDelimiter($contents);
        $rows = [];
        $readLimit = $limit + 2;

        while (($row = fgetcsv($stream, 0, $delimiter)) !== false && count($rows) < $readLimit) {
            if ($row === [null]) {
                continue;
            }

            $rows[] = array_map(fn ($value) => trim((string) $value), $row);
        }

        fclose($stream);

        return $this->rowsToPreview($rows, $limit);
    }

    private function previewXlsxUpload(ScanUpload $scanUpload, int $limit): array
    {
        if (! class_exists(ZipArchive::class)) {
            return $this->emptyPreview('Preview XLSX membutuhkan ekstensi ZipArchive di PHP.');
        }

        $zip = new ZipArchive;

        if ($zip->open(Storage::path($scanUpload->path)) !== true) {
            return $this->emptyPreview('File XLSX tidak bisa dibuka.');
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

        if ($sheetXml === false) {
            $zip->close();

            return $this->emptyPreview('Sheet pertama pada file XLSX tidak ditemukan.');
        }

        $sharedStrings = $this->loadXlsxSharedStrings($zip);
        $sheet = simplexml_load_string($sheetXml);

        if (! $sheet instanceof SimpleXMLElement) {
            $zip->close();

            return $this->emptyPreview('Sheet XLSX tidak bisa dibaca.');
        }

        $sheet->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $xlsxRows = [];

        foreach (($sheet->xpath('//x:sheetData/x:row') ?: []) as $rowNode) {
            if (count($xlsxRows) >= $limit + 2) {
                break;
            }

            $rowNode->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $cells = [];

            foreach (($rowNode->xpath('./x:c') ?: []) as $cellNode) {
                $columnIndex = $this->xlsxColumnIndex((string) $cellNode['r']);
                $cells[$columnIndex] = $this->xlsxCellValue($cellNode, $sharedStrings);
            }

            if ($cells === []) {
                continue;
            }

            ksort($cells);
            $width = max(array_keys($cells)) + 1;
            $xlsxRows[] = array_map(
                fn ($index) => $cells[$index] ?? '',
                range(0, $width - 1)
            );
        }

        $zip->close();

        return $this->rowsToPreview($xlsxRows, $limit);
    }

    private function loadXlsxSharedStrings(ZipArchive $zip): array
    {
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');

        if ($sharedStringsXml === false) {
            return [];
        }

        $xml = simplexml_load_string($sharedStringsXml);

        if (! $xml instanceof SimpleXMLElement) {
            return [];
        }

        $xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        return collect($xml->xpath('//x:si') ?: [])
            ->map(function (SimpleXMLElement $node) {
                $node->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

                return collect($node->xpath('.//x:t') ?: [])
                    ->map(fn (SimpleXMLElement $textNode) => (string) $textNode)
                    ->implode('');
            })
            ->all();
    }

    private function xlsxCellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $cell->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $type = (string) $cell['t'];
        $valueNode = ($cell->xpath('./x:v') ?: [null])[0];
        $value = $valueNode instanceof SimpleXMLElement ? (string) $valueNode : '';

        if ($type === 's') {
            return $sharedStrings[(int) $value] ?? '';
        }

        if ($type === 'inlineStr') {
            return collect($cell->xpath('.//x:t') ?: [])
                ->map(fn (SimpleXMLElement $textNode) => (string) $textNode)
                ->implode('');
        }

        return $value;
    }

    private function xlsxColumnIndex(string $cellReference): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($cellReference));
        $index = 0;

        foreach (str_split($letters ?: 'A') as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    private function detectDelimiter(string $contents): string
    {
        $firstLine = preg_split("/\r\n|\n|\r/", $contents, 2)[0] ?? '';
        $counts = [
            ',' => substr_count($firstLine, ','),
            ';' => substr_count($firstLine, ';'),
            "\t" => substr_count($firstLine, "\t"),
        ];

        arsort($counts);

        return (int) reset($counts) > 0 ? (string) key($counts) : ',';
    }

    private function rowsToPreview(array $rows, int $limit): array
    {
        if ($rows === []) {
            return $this->emptyPreview('File asli tidak berisi data yang bisa dipreview.');
        }

        $headers = array_shift($rows);
        $width = max(count($headers), ...array_map(fn ($row) => count($row), $rows ?: [[]]));
        $headers = $this->normalizeHeaders($headers, $width);
        $isTruncated = count($rows) > $limit;

        $dataRows = collect($rows)
            ->take($limit)
            ->map(function (array $row) use ($width) {
                return array_map(
                    fn ($index) => $row[$index] ?? '',
                    range(0, max(0, $width - 1))
                );
            })
            ->values()
            ->all();

        return [
            'headers' => $headers,
            'rows' => $dataRows,
            'message' => $dataRows === [] ? 'File asli hanya berisi header.' : null,
            'is_truncated' => $isTruncated,
        ];
    }

    private function normalizeHeaders(array $headers, int $width): array
    {
        return array_map(
            fn ($index) => trim((string) ($headers[$index] ?? '')) ?: 'Kolom '.($index + 1),
            range(0, max(0, $width - 1))
        );
    }

    private function emptyPreview(string $message): array
    {
        return [
            'headers' => [],
            'rows' => [],
            'message' => $message,
            'is_truncated' => false,
        ];
    }
}
