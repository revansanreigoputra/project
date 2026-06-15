<?php

namespace App\Support;

use App\Models\DetectionResult;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DetectionResultTable
{
    public const PER_PAGE_OPTIONS = [10, 25, 50, 100];
    public const SORT_OPTIONS = ['row', 'label', 'confidence'];
    public const DIRECTION_OPTIONS = ['asc', 'desc'];

    public static function rows(DetectionResult $detectionResult): Collection
    {
        if ($detectionResult->results_path && Storage::exists($detectionResult->results_path)) {
            return self::rowsFromStoredCsv($detectionResult);
        }

        return collect($detectionResult->results ?? [])
            ->map(fn (array $row, int $index) => self::normalizeStoredRow($row, $index))
            ->values();
    }

    public static function summary(DetectionResult $detectionResult, ?Collection $rows = null): array
    {
        $baseSummary = $detectionResult->toPredictionArray()['summary'] ?? [];
        $baseLabels = collect($baseSummary['labels'] ?? [])
            ->map(fn ($count) => (int) $count)
            ->filter(fn (int $count) => $count > 0);
        $storedTotal = (int) $detectionResult->total;

        if ($rows === null && $storedTotal > 0 && $baseLabels->sum() === $storedTotal) {
            return self::normalizeSummaryArray($baseSummary, $detectionResult, $storedTotal);
        }

        $rows ??= self::rows($detectionResult);
        $total = (int) ($storedTotal ?: max($rows->count(), $baseLabels->sum()));
        $hasFullRows = $rows->count() > 0 && ($total === 0 || $rows->count() >= $total);

        if (! $hasFullRows) {
            return self::normalizeSummaryArray($baseSummary, $detectionResult, $total);
        }

        $labels = $rows
            ->groupBy(fn (array $row) => (string) ($row['label'] ?? 'Unknown'))
            ->map(fn (Collection $items) => $items->count())
            ->all();
        $normal = (int) ($labels['Normal'] ?? 0);
        $unknown = (int) ($labels['Unknown'] ?? 0);
        $malwareLabels = collect($labels)
            ->reject(fn ($count, string $label) => in_array($label, ['Normal', 'Unknown'], true))
            ->all();
        $malware = (int) array_sum($malwareLabels);
        $averageConfidence = $rows->avg(fn (array $row) => (float) ($row['confidence'] ?? 0));

        return [
            'normal' => $normal,
            'malware' => $malware,
            'unknown' => $unknown,
            'normal_percent' => $total > 0 ? round(($normal / $total) * 100, 2) : 0,
            'malware_percent' => $total > 0 ? round(($malware / $total) * 100, 2) : 0,
            'average_confidence' => $averageConfidence ? round($averageConfidence * 100, 2) : 0,
            'labels' => $labels,
            'malware_labels' => $malwareLabels,
        ];
    }

    public static function make(DetectionResult $detectionResult, array $filters, string $pageName = 'page'): array
    {
        $rows = self::rows($detectionResult);
        $summary = self::summary($detectionResult, $rows);
        $filteredRows = self::sortRows(self::filterRows($rows, $filters), $filters);
        $perPage = self::perPage($filters['per_page'] ?? null);
        $page = LengthAwarePaginator::resolveCurrentPage($pageName);
        $paginator = new LengthAwarePaginator(
            $filteredRows->forPage($page, $perPage)->values(),
            $filteredRows->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
                'pageName' => $pageName,
            ],
        );

        return [
            'rows' => $rows,
            'filtered_rows' => $filteredRows,
            'summary' => $summary,
            'paginator' => $paginator,
            'label_options' => collect($summary['labels'] ?? [])
                ->keys()
                ->whenEmpty(fn ($items) => $rows->pluck('label')->unique()->values())
                ->values(),
        ];
    }

    public static function perPage($value): int
    {
        $perPage = (int) $value;

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 10;
    }

    private static function rowsFromStoredCsv(DetectionResult $detectionResult): Collection
    {
        $contents = Storage::get($detectionResult->results_path);
        $stream = fopen('php://temp', 'r+');

        fwrite($stream, $contents);
        rewind($stream);

        $headers = fgetcsv($stream);

        if ($headers === false) {
            fclose($stream);

            return collect();
        }

        $headers = array_map(fn ($header) => self::cleanHeader((string) $header), $headers);
        $probabilityColumns = collect($headers)
            ->filter(fn (string $header) => str_starts_with($header, 'prob_') && str_ends_with($header, '_percent'))
            ->values()
            ->all();
        $labelLookup = self::labelLookup($detectionResult);
        $rows = collect();

        while (($values = fgetcsv($stream)) !== false) {
            if ($values === [null]) {
                continue;
            }

            $record = [];

            foreach ($headers as $index => $header) {
                $record[$header] = $values[$index] ?? null;
            }

            $label = trim((string) ($record['label'] ?? '')) ?: 'Unknown';
            $labelSlug = Str::slug($label, '_');
            $probabilities = [];

            foreach ($probabilityColumns as $column) {
                $slug = Str::of($column)
                    ->after('prob_')
                    ->beforeLast('_percent')
                    ->toString();
                $probabilityLabel = $slug === $labelSlug
                    ? $label
                    : ($labelLookup[$slug] ?? self::labelFromSlug($slug));
                $probabilities[$probabilityLabel] = self::percentToDecimal($record[$column] ?? 0);
            }

            $rows->push([
                'row' => $record['row_id'] ?? $rows->count() + 1,
                'label' => $label,
                'confidence' => self::percentToDecimal($record['confidence_percent'] ?? 0),
                'probabilities' => $probabilities,
            ]);
        }

        fclose($stream);

        return $rows->values();
    }

    private static function normalizeStoredRow(array $row, int $index): array
    {
        $confidence = (float) ($row['confidence'] ?? 0);

        return [
            'row' => $row['row'] ?? $row['row_id'] ?? $index + 1,
            'label' => $row['label'] ?? 'Unknown',
            'confidence' => $confidence > 1 ? $confidence / 100 : $confidence,
            'probabilities' => collect($row['probabilities'] ?? [])
                ->map(fn ($value) => ((float) $value) > 1 ? ((float) $value) / 100 : (float) $value)
                ->all(),
        ];
    }

    private static function filterRows(Collection $rows, array $filters): Collection
    {
        $search = Str::lower(trim((string) ($filters['search'] ?? '')));
        $label = (string) ($filters['label'] ?? 'all');

        return $rows
            ->when($label !== '' && $label !== 'all', fn (Collection $items) => $items
                ->filter(fn (array $row) => (string) ($row['label'] ?? 'Unknown') === $label))
            ->when($search !== '', fn (Collection $items) => $items
                ->filter(function (array $row) use ($search) {
                    $probabilities = collect($row['probabilities'] ?? [])
                        ->map(fn ($value, $label) => $label.' '.number_format(((float) $value) * 100, 2, ',', '.'))
                        ->implode(' ');
                    $haystack = Str::lower(implode(' ', [
                        $row['row'] ?? '',
                        $row['label'] ?? '',
                        number_format(((float) ($row['confidence'] ?? 0)) * 100, 2, ',', '.'),
                        $probabilities,
                    ]));

                    return str_contains($haystack, $search);
                }))
            ->values();
    }

    private static function sortRows(Collection $rows, array $filters): Collection
    {
        $sort = self::sort($filters['sort'] ?? null);
        $direction = self::direction($filters['direction'] ?? null);

        return $rows
            ->sort(function (array $left, array $right) use ($sort, $direction) {
                $leftValue = self::sortValue($left, $sort);
                $rightValue = self::sortValue($right, $sort);
                $comparison = is_numeric($leftValue) && is_numeric($rightValue)
                    ? ((float) $leftValue <=> (float) $rightValue)
                    : strnatcasecmp((string) $leftValue, (string) $rightValue);

                return $direction === 'desc' ? -$comparison : $comparison;
            })
            ->values();
    }

    public static function sort($value): string
    {
        $sort = (string) $value;

        return in_array($sort, self::SORT_OPTIONS, true) ? $sort : 'row';
    }

    public static function direction($value): string
    {
        $direction = (string) $value;

        return in_array($direction, self::DIRECTION_OPTIONS, true) ? $direction : 'asc';
    }

    private static function sortValue(array $row, string $sort): float|string|int
    {
        return match ($sort) {
            'label' => Str::lower((string) ($row['label'] ?? 'Unknown')),
            'confidence' => (float) ($row['confidence'] ?? 0),
            default => $row['row'] ?? 0,
        };
    }

    private static function normalizeSummaryArray(array $summary, DetectionResult $detectionResult, int $total): array
    {
        $labels = collect($summary['labels'] ?? [])
            ->map(fn ($count) => (int) $count)
            ->filter(fn (int $count) => $count > 0)
            ->all();
        $malwareLabels = collect($labels)
            ->reject(fn ($count, string $label) => in_array($label, ['Normal', 'Unknown'], true))
            ->all();
        $labelsCoverTotal = ($total === 0 && $labels !== []) || ($total > 0 && array_sum($labels) === $total);
        $normal = $labelsCoverTotal
            ? (int) ($labels['Normal'] ?? 0)
            : (int) ($summary['normal'] ?? $detectionResult->normal_count ?? $labels['Normal'] ?? 0);
        $unknown = $labelsCoverTotal
            ? (int) ($labels['Unknown'] ?? 0)
            : (int) ($summary['unknown'] ?? $detectionResult->unknown_count ?? $labels['Unknown'] ?? 0);
        $malware = $labelsCoverTotal
            ? (int) array_sum($malwareLabels)
            : (int) ($summary['malware'] ?? $detectionResult->malware_count ?? array_sum($malwareLabels));

        return [
            'normal' => $normal,
            'malware' => $malware,
            'unknown' => $unknown,
            'normal_percent' => $total > 0 ? round(($normal / $total) * 100, 2) : 0,
            'malware_percent' => $total > 0 ? round(($malware / $total) * 100, 2) : 0,
            'average_confidence' => (float) ($summary['average_confidence'] ?? $detectionResult->average_confidence ?? 0),
            'labels' => $labels,
            'malware_labels' => $malwareLabels,
        ];
    }

    private static function labelLookup(DetectionResult $detectionResult): array
    {
        return collect($detectionResult->summary['labels'] ?? [])
            ->keys()
            ->merge(collect($detectionResult->results ?? [])->pluck('label'))
            ->merge(collect($detectionResult->results ?? [])->flatMap(fn (array $row) => array_keys($row['probabilities'] ?? [])))
            ->filter()
            ->unique()
            ->mapWithKeys(fn (string $label) => [Str::slug($label, '_') => $label])
            ->all();
    }

    private static function cleanHeader(string $header): string
    {
        return trim(str_replace("\xEF\xBB\xBF", '', $header));
    }

    private static function percentToDecimal($value): float
    {
        $normalized = trim(str_replace('%', '', (string) $value));

        if (str_contains($normalized, ',') && ! str_contains($normalized, '.')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return ((float) $normalized) / 100;
    }

    private static function labelFromSlug(string $slug): string
    {
        return Str::of($slug)
            ->replace('_', ' ')
            ->headline()
            ->toString();
    }
}
