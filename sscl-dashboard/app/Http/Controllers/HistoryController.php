<?php

namespace App\Http\Controllers;

use App\Models\DetectionResult;
use App\Models\ScanUpload;
use App\Support\DetectionResultTable;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
            'search' => ['nullable', 'string', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'detection_status' => ['nullable', 'in:all,detected,undetected'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50'],
        ]);

        // Filter tanggal memakai zona lokal supaya input date dari UI cocok dengan hari di Indonesia.
        $startAt = filled($filters['start_date'] ?? null)
            ? Carbon::parse($filters['start_date'], 'Asia/Jakarta')->startOfDay()->utc()
            : null;
        $endAt = filled($filters['end_date'] ?? null)
            ? Carbon::parse($filters['end_date'], 'Asia/Jakarta')->endOfDay()->utc()
            : null;
        $search = trim((string) ($filters['search'] ?? ''));
        $detectionStatus = $filters['detection_status'] ?? 'all';
        $perPage = in_array((int) ($filters['per_page'] ?? 10), [10, 25, 50], true)
            ? (int) ($filters['per_page'] ?? 10)
            : 10;

        // Query ini menjadi sumber tunggal untuk tabel, ringkasan, dan pagination riwayat.
        $query = ScanUpload::with([
            'detectionResults' => fn ($query) => $query
                ->latest('processed_at')
                ->latest('id'),
        ])
            ->visibleTo($request->user())
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('original_name', 'like', '%'.$search.'%')
                        ->orWhere('stored_name', 'like', '%'.$search.'%')
                        ->orWhereHas('detectionResults', function ($resultQuery) use ($search) {
                            $resultQuery
                                ->where('file_name', 'like', '%'.$search.'%')
                                ->orWhere('status', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when($detectionStatus === 'detected', function ($query) {
                $query->whereHas('detectionResults', fn ($resultQuery) => $resultQuery->completed());
            })
            ->when($detectionStatus === 'undetected', function ($query) {
                $query->whereDoesntHave('detectionResults', fn ($resultQuery) => $resultQuery->completed());
            })
            ->when($startAt || $endAt, function ($query) use ($startAt, $endAt) {
                $query->where(function ($dateQuery) use ($startAt, $endAt) {
                    $this->applyDateRange($dateQuery, 'created_at', $startAt, $endAt);
                    $dateQuery->orWhereHas('detectionResults', function ($resultQuery) use ($startAt, $endAt) {
                        $resultQuery->where(function ($resultDateQuery) use ($startAt, $endAt) {
                            $this->applyDateRange($resultDateQuery, 'processed_at', $startAt, $endAt);
                            $resultDateQuery->orWhere(function ($createdAtQuery) use ($startAt, $endAt) {
                                $this->applyDateRange($createdAtQuery, 'created_at', $startAt, $endAt);
                            });
                        });
                    });
                });
            });

        $totalUploads = (clone $query)->count();

        // Pagination server-side menjaga tabel tetap ringan walaupun riwayat upload sudah banyak.
        $history = $query
            ->latest('updated_at')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ScanUpload $scanUpload) => $this->toHistoryArray($scanUpload));

        return view('pages.riwayat.index', [
            'history' => $history,
            'filters' => [
                'search' => $search,
                'start_date' => $filters['start_date'] ?? '',
                'end_date' => $filters['end_date'] ?? '',
                'detection_status' => $detectionStatus,
                'per_page' => $perPage,
            ],
            'totalUploads' => $totalUploads,
            'perPageOptions' => [10, 25, 50],
        ]);
    }

    public function show(Request $request, ScanUpload $scanUpload)
    {
        $this->ensureUploadBelongsToUser($scanUpload, $request);
        $filters = $request->validate([
            'mode' => ['nullable', 'in:before,after'],
            'after_search' => ['nullable', 'string', 'max:100'],
            'after_label' => ['nullable', 'string', 'max:100'],
            'after_per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
        ]);

        $scanUpload->load([
            'detectionResults' => fn ($query) => $query
                ->latest('processed_at')
                ->latest('id'),
        ]);

        $mode = ($filters['mode'] ?? null) === 'after' ? 'after' : 'before';
        $latestDetectionResult = $scanUpload->detectionResults->first();
        $completedDetectionResult = $scanUpload->detectionResults->first(
            fn (DetectionResult $detectionResult) => $detectionResult->status === 'Selesai'
        );
        $prediction = $completedDetectionResult?->toPredictionArray();
        $afterTable = null;

        if ($completedDetectionResult && $prediction && $mode === 'after') {
            $afterTable = DetectionResultTable::make($completedDetectionResult, [
                'search' => trim((string) ($filters['after_search'] ?? '')),
                'label' => $filters['after_label'] ?? 'all',
                'per_page' => $filters['after_per_page'] ?? 10,
            ], 'after_page');
            $prediction['summary'] = $afterTable['summary'];
        } elseif ($completedDetectionResult && $prediction) {
            $prediction['summary'] = DetectionResultTable::summary($completedDetectionResult);
        }

        return view('pages.riwayat.show', [
            'scanUpload' => $scanUpload,
            'latestDetectionResult' => $latestDetectionResult,
            'completedDetectionResult' => $completedDetectionResult,
            'historyItem' => $this->toHistoryArray($scanUpload),
            'mode' => $mode,
            'sourcePreview' => $mode === 'before' ? $this->previewUpload($scanUpload) : null,
            'prediction' => $prediction,
            'afterResultsPaginator' => $afterTable['paginator'] ?? null,
            'afterLabelOptions' => $afterTable['label_options'] ?? collect(['Normal']),
            'afterTableFilters' => [
                'search' => trim((string) ($filters['after_search'] ?? '')),
                'label' => $filters['after_label'] ?? 'all',
                'per_page' => DetectionResultTable::perPage($filters['after_per_page'] ?? null),
            ],
            'perPageOptions' => DetectionResultTable::PER_PAGE_OPTIONS,
        ]);
    }

    public function destroy(Request $request, ScanUpload $scanUpload)
    {
        $this->ensureUploadBelongsToUser($scanUpload, $request);

        $path = $scanUpload->path;
        $fileName = $scanUpload->original_name;
        $resultsPaths = $scanUpload->detectionResults()
            ->whereNotNull('results_path')
            ->pluck('results_path')
            ->all();

        DB::transaction(function () use ($scanUpload) {
            $scanUpload->detectionResults()->delete();
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
        if ($request->user()?->can('history.view-any')) {
            return;
        }

        abort_unless(
            (int) $scanUpload->user_id === (int) $request->user()?->getAuthIdentifier(),
            404
        );
    }

    private function toHistoryArray(ScanUpload $scanUpload): array
    {
        $latestDetectionResult = $scanUpload->detectionResults->first();
        $completedDetectionResult = $scanUpload->detectionResults->first(
            fn (DetectionResult $detectionResult) => $detectionResult->status === 'Selesai'
        );
        $displayDetectionResult = $completedDetectionResult ?? $latestDetectionResult;
        $summary = $displayDetectionResult
            ? DetectionResultTable::summary($displayDetectionResult)
            : [
            'normal' => 0,
            'malware' => 0,
            'unknown' => 0,
            'normal_percent' => 0,
            'malware_percent' => 0,
            'average_confidence' => 0,
            'labels' => [],
            'malware_labels' => [],
        ];

        return [
            'upload_id' => $scanUpload->id,
            'detection_result_id' => $displayDetectionResult?->id,
            'uploaded_at' => $this->formatDateTime($scanUpload->created_at),
            'processed_at' => $this->formatDateTime($displayDetectionResult?->processed_at ?? $displayDetectionResult?->created_at),
            'file_name' => $scanUpload->original_name,
            'size' => $scanUpload->size,
            'total' => $displayDetectionResult?->total ?? 0,
            'normal' => $summary['normal'] ?? 0,
            'malware' => $summary['malware'] ?? 0,
            'unknown' => $summary['unknown'] ?? 0,
            'normal_percent' => $summary['normal_percent'] ?? 0,
            'malware_percent' => $summary['malware_percent'] ?? 0,
            'average_confidence' => $summary['average_confidence'] ?? $displayDetectionResult?->average_confidence ?? 0,
            'labels' => $summary['labels'] ?? [],
            'malware_labels' => $summary['malware_labels'] ?? [],
            'status' => $this->displayStatus($scanUpload, $displayDetectionResult),
            'can_view_after' => (bool) $completedDetectionResult,
            'can_download_before' => (bool) $scanUpload->path,
            'can_download_after' => (bool) $completedDetectionResult,
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
        $detectionResult = $scanUpload->detectionResults()
            ->completed()
            ->latest('processed_at')
            ->latest('id')
            ->first();

        if (! $detectionResult) {
            return back()
                ->with('toast_type', 'danger')
                ->with('toast_title', 'Download Gagal')
                ->with('toast_message', 'Data ini belum memiliki hasil deteksi yang selesai.');
        }

        $fileName = $this->downloadFileName($scanUpload, 'hasil-deteksi', 'csv');

        if ($detectionResult->results_path && Storage::exists($detectionResult->results_path)) {
            return Storage::download($detectionResult->results_path, $fileName, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        $results = collect($detectionResult->results ?? []);

        return response()->streamDownload(function () use ($results) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");
            $probabilityLabels = $this->probabilityLabels($results);

            fputcsv($handle, [
                'row_id',
                'label',
                'confidence_percent',
                ...array_map(fn (string $label) => 'prob_'.Str::slug($label, '_').'_percent', $probabilityLabels),
            ]);

            foreach ($results as $index => $result) {
                fputcsv($handle, [
                    $result['row'] ?? $index,
                    $result['label'] ?? 'Unknown',
                    $this->csvPercent($result['confidence'] ?? 0),
                    ...array_map(
                        fn (string $label) => $this->csvPercent(($result['probabilities'] ?? [])[$label] ?? 0),
                        $probabilityLabels
                    ),
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

    private function probabilityLabels(Collection $results): array
    {
        return $results
            ->flatMap(fn (array $result) => array_keys($result['probabilities'] ?? []))
            ->unique()
            ->sortBy(fn (string $label) => $label === 'Normal' ? '0' : '1'.$label)
            ->values()
            ->all();
    }

    private function displayStatus(ScanUpload $scanUpload, ?DetectionResult $detectionResult): string
    {
        if ($detectionResult) {
            return $detectionResult->status;
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
