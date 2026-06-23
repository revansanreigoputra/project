<?php

namespace App\Services\UpaTik;

use InvalidArgumentException;
use Illuminate\Support\Str;

class UpaTikTrafficMapper
{
    public const REQUIRED_COLUMNS = [
        'source_ip',
        'dest_ip',
        'source_port',
        'dest_port',
        'protocol',
        'packet_length',
        'ttl',
        'sent_bytes',
        'received_bytes',
        'severity',
        'count',
    ];

    private const COLUMN_ALIASES = [
        'source_ip' => ['source_ip', 'src_ip', 'ip_source', 'ip_sumber', 'alamat_sumber'],
        'dest_ip' => ['dest_ip', 'dst_ip', 'destination_ip', 'ip_destination', 'ip_tujuan', 'alamat_tujuan'],
        'source_port' => ['source_port', 'src_port', 'sport', 'port_sumber'],
        'dest_port' => ['dest_port', 'dst_port', 'dport', 'port_tujuan', 'destination_port'],
        'protocol' => ['protocol', 'proto', 'protokol'],
        'packet_length' => ['packet_length', 'pckt_len', 'packet_len', 'pkt_len', 'length', 'packet_size'],
        'ttl' => ['ttl', 'time_to_live'],
        'sent_bytes' => ['sent_bytes', 'bytes_sent', 'sent', 'tx_bytes'],
        'received_bytes' => ['received_bytes', 'rcvd_bytes', 'bytes_received', 'recv_bytes', 'rx_bytes'],
        'severity' => ['severity', 'sev', 'risk', 'level'],
        'count' => ['count', 'jumlah', 'total', 'hit_count', 'event_count', 'frequency'],
    ];

    public function mapJsonResponse(array $payload): array
    {
        $datasets = $this->extractDatasets($payload);

        return collect($datasets)
            ->map(fn (array $dataset, int $index) => $this->mapDataset($dataset, $index))
            ->values()
            ->all();
    }

    private function extractDatasets(array $payload): array
    {
        // TODO: Sesuaikan bagian ini setelah dokumentasi response API UPA-TIK tersedia.
        if (isset($payload['datasets']) && is_array($payload['datasets'])) {
            return $payload['datasets'];
        }

        if (isset($payload['items']) && is_array($payload['items'])) {
            return $payload['items'];
        }

        if (isset($payload['data']) && is_array($payload['data'])) {
            if ($this->isListOfRecords($payload['data'])) {
                return [[
                    'external_reference' => $payload['external_reference'] ?? null,
                    'file_name' => $payload['file_name'] ?? null,
                    'records' => $payload['data'],
                ]];
            }

            return [$payload['data']];
        }

        if ($this->isListOfRecords($payload)) {
            return [[
                'external_reference' => null,
                'file_name' => null,
                'records' => $payload,
            ]];
        }

        throw new InvalidArgumentException('Response API UPA-TIK belum bisa dimapping. Sesuaikan UpaTikTrafficMapper setelah format resmi tersedia.');
    }

    private function mapDataset(array $dataset, int $index): array
    {
        // TODO: Sesuaikan key external reference, nama file, dan daftar record sesuai dokumentasi resmi API UPA-TIK.
        $externalReference = $dataset['external_reference']
            ?? $dataset['reference']
            ?? $dataset['id']
            ?? null;

        if (! is_string($externalReference) && ! is_numeric($externalReference)) {
            throw new InvalidArgumentException('Dataset API UPA-TIK tidak memiliki external_reference.');
        }

        $externalReference = trim((string) $externalReference);

        if ($externalReference === '') {
            throw new InvalidArgumentException('Dataset API UPA-TIK memiliki external_reference kosong.');
        }

        $records = $dataset['records']
            ?? $dataset['rows']
            ?? $dataset['data']
            ?? null;

        if (! is_array($records) || ! $this->isListOfRecords($records)) {
            throw new InvalidArgumentException('Dataset API UPA-TIK tidak memiliki daftar record trafik yang valid.');
        }

        return [
            'external_reference' => $externalReference,
            'source_name' => trim((string) ($dataset['source_name'] ?? config('services.upa_tik.source_name', 'UPA-TIK'))) ?: 'UPA-TIK',
            'file_name' => $this->fileName($dataset['file_name'] ?? null, $externalReference, $index),
            'rows' => $this->normalizeRows($records),
        ];
    }

    private function normalizeRows(array $records): array
    {
        return collect($records)
            ->map(fn (array $record, int $index) => $this->normalizeRecord($record, $index))
            ->values()
            ->all();
    }

    private function normalizeRecord(array $record, int $index): array
    {
        $lookup = $this->normalizedLookup($record);
        $normalized = [];
        $missing = [];

        foreach (self::REQUIRED_COLUMNS as $column) {
            $sourceKey = $this->findSourceKey($lookup, $column);

            if ($sourceKey === null || $this->isMissingValue($record[$sourceKey] ?? null)) {
                $missing[] = $column;

                continue;
            }

            $normalized[$column] = $record[$sourceKey];
        }

        if ($missing !== []) {
            throw new InvalidArgumentException(
                'Dataset API UPA-TIK kehilangan atribut wajib pada baris '.($index + 1).': '.implode(', ', $missing).'.'
            );
        }

        return $normalized;
    }

    private function normalizedLookup(array $record): array
    {
        $lookup = [];

        foreach (array_keys($record) as $key) {
            $lookup[$this->normalizeKey((string) $key)] = $key;
        }

        return $lookup;
    }

    private function findSourceKey(array $lookup, string $column): string|int|null
    {
        foreach (self::COLUMN_ALIASES[$column] ?? [$column] as $alias) {
            $normalizedAlias = $this->normalizeKey($alias);

            if (array_key_exists($normalizedAlias, $lookup)) {
                return $lookup[$normalizedAlias];
            }
        }

        return null;
    }

    private function normalizeKey(string $key): string
    {
        return preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($key))) ?: $key;
    }

    private function isMissingValue($value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    private function isListOfRecords(array $value): bool
    {
        return array_is_list($value) && $value !== [] && collect($value)->every(fn ($row) => is_array($row));
    }

    private function fileName($value, string $externalReference, int $index): string
    {
        $fileName = is_string($value) && trim($value) !== ''
            ? basename($value)
            : 'upa-tik-'.$externalReference.'-'.($index + 1).'.csv';

        return Str::endsWith(strtolower($fileName), '.csv') ? $fileName : $fileName.'.csv';
    }
}
