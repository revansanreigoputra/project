<?php

namespace App\Services;

use App\Models\DatabaseBackup;
use App\Models\DatabaseBackupSetting;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;
use ZipArchive;

class DatabaseBackupService
{
    // Buat backup database manual atau terjadwal dalam format SQL atau ZIP.
    public function create(string $format = 'sql', ?User $user = null, string $trigger = 'manual'): DatabaseBackup
    {
        if (! in_array($format, DatabaseBackupSetting::FORMATS, true)) {
            throw new InvalidArgumentException('Format backup tidak didukung.');
        }

        $connection = DB::connection();
        $databaseName = $connection->getDatabaseName() ?: config('database.default');
        $baseName = Str::slug((string) $databaseName) ?: 'database';
        $timestamp = now(DatabaseBackupSetting::TIMEZONE)->format('Ymd-His');
        $sqlPath = 'backups/'.$baseName.'-'.$timestamp.'.sql';
        $finalPath = $format === 'zip'
            ? 'backups/'.$baseName.'-'.$timestamp.'.zip'
            : $sqlPath;

        Storage::disk('local')->makeDirectory('backups');

        $backup = DatabaseBackup::create([
            'user_id' => $user?->getAuthIdentifier(),
            'disk' => 'local',
            'file_name' => basename($finalPath),
            'path' => $finalPath,
            'format' => $format,
            'size_bytes' => 0,
            'status' => 'running',
            'trigger' => $trigger,
            'started_at' => now(),
            'meta' => [
                'connection' => config('database.default'),
                'driver' => $connection->getDriverName(),
                'database' => $databaseName,
            ],
        ]);

        try {
            $this->dumpToSql($connection, $sqlPath);

            if ($format === 'zip') {
                $this->zipSqlDump($sqlPath, $finalPath);
            }

            $backup->update([
                'file_name' => basename($finalPath),
                'path' => $finalPath,
                'size_bytes' => Storage::disk('local')->size($finalPath),
                'status' => 'completed',
                'completed_at' => now(),
                'error_message' => null,
            ]);

            return $backup->refresh();
        } catch (Throwable $exception) {
            Storage::disk('local')->delete([$sqlPath, $finalPath]);

            $backup->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    // Hapus file backup lama sesuai aturan retensi hari.
    public function pruneOldBackups(int $retentionDays): int
    {
        $cutoff = now()->subDays(max(1, $retentionDays));
        $backups = DatabaseBackup::query()
            ->where('created_at', '<', $cutoff)
            ->where('status', 'completed')
            ->get();

        foreach ($backups as $backup) {
            Storage::disk($backup->disk ?: 'local')->delete($backup->path);
            $backup->delete();
        }

        return $backups->count();
    }

    // Dump database aktif ke file SQL sesuai driver koneksi.
    private function dumpToSql(Connection $connection, string $path): void
    {
        $absolutePath = Storage::disk('local')->path($path);
        $handle = fopen($absolutePath, 'wb');

        if ($handle === false) {
            throw new RuntimeException('File backup tidak bisa dibuat di storage.');
        }

        try {
            $driver = $connection->getDriverName();
            $this->write($handle, '-- SSCL database backup');
            $this->write($handle, '-- Generated at '.now(DatabaseBackupSetting::TIMEZONE)->format('Y-m-d H:i:s T'));
            $this->write($handle, '-- Driver: '.$driver);
            $this->write($handle, '');

            match ($driver) {
                'mysql', 'mariadb' => $this->dumpMysql($connection, $handle),
                'sqlite' => $this->dumpSqlite($connection, $handle),
                default => throw new RuntimeException('Backup SQL belum mendukung driver database '.$driver.'.'),
            };
        } finally {
            fclose($handle);
        }
    }

    // Tulis struktur dan isi tabel MySQL/MariaDB ke file SQL.
    private function dumpMysql(Connection $connection, $handle): void
    {
        $pdo = $connection->getPdo();
        $this->write($handle, 'SET FOREIGN_KEY_CHECKS=0;');
        $this->write($handle, '');

        foreach ($this->mysqlTables($connection) as $table) {
            $quotedTable = $this->quoteIdentifier($table, 'mysql');
            $createRow = (array) $connection->selectOne('SHOW CREATE TABLE '.$quotedTable);
            $createSql = $createRow['Create Table'] ?? array_values($createRow)[1] ?? null;

            if (! is_string($createSql)) {
                continue;
            }

            $this->write($handle, 'DROP TABLE IF EXISTS '.$quotedTable.';');
            $this->write($handle, $createSql.';');
            $this->write($handle, '');
            $this->writeRows($pdo, $handle, $table, 'mysql');
            $this->write($handle, '');
        }

        $this->write($handle, 'SET FOREIGN_KEY_CHECKS=1;');
    }

    // Tulis struktur dan isi tabel SQLite ke file SQL.
    private function dumpSqlite(Connection $connection, $handle): void
    {
        $pdo = $connection->getPdo();
        $tables = $connection->select(
            "SELECT name, sql FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        );

        $this->write($handle, 'PRAGMA foreign_keys=OFF;');
        $this->write($handle, 'BEGIN TRANSACTION;');
        $this->write($handle, '');

        foreach ($tables as $table) {
            $table = (array) $table;
            $name = (string) $table['name'];
            $createSql = (string) $table['sql'];
            $quotedTable = $this->quoteIdentifier($name, 'sqlite');

            $this->write($handle, 'DROP TABLE IF EXISTS '.$quotedTable.';');
            $this->write($handle, $createSql.';');
            $this->write($handle, '');
            $this->writeRows($pdo, $handle, $name, 'sqlite');
            $this->write($handle, '');
        }

        $this->write($handle, 'COMMIT;');
        $this->write($handle, 'PRAGMA foreign_keys=ON;');
    }

    // Ambil daftar tabel utama MySQL, tanpa view.
    private function mysqlTables(Connection $connection): array
    {
        return collect($connection->select('SHOW FULL TABLES'))
            ->map(function ($row) {
                $values = array_values((array) $row);

                return [
                    'name' => $values[0] ?? null,
                    'type' => $values[1] ?? 'BASE TABLE',
                ];
            })
            ->filter(fn (array $table) => $table['name'] && $table['type'] === 'BASE TABLE')
            ->pluck('name')
            ->map(fn ($name) => (string) $name)
            ->values()
            ->all();
    }

    // Tulis semua baris tabel sebagai statement INSERT.
    private function writeRows(PDO $pdo, $handle, string $table, string $driver): void
    {
        $quotedTable = $this->quoteIdentifier($table, $driver);
        $statement = $pdo->query('SELECT * FROM '.$quotedTable);

        if ($statement === false) {
            return;
        }

        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if ($row === []) {
                continue;
            }

            $columns = array_map(fn (string $column) => $this->quoteIdentifier($column, $driver), array_keys($row));
            $values = array_map(fn ($value) => $this->quoteValue($pdo, $value), array_values($row));

            $this->write(
                $handle,
                'INSERT INTO '.$quotedTable.' ('.implode(', ', $columns).') VALUES ('.implode(', ', $values).');'
            );
        }
    }

    // Bungkus dump SQL ke ZIP lalu hapus file SQL sementara.
    private function zipSqlDump(string $sqlPath, string $zipPath): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP ZipArchive belum aktif.');
        }

        $zip = new ZipArchive;
        $zipAbsolutePath = Storage::disk('local')->path($zipPath);
        $sqlAbsolutePath = Storage::disk('local')->path($sqlPath);

        if ($zip->open($zipAbsolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('File ZIP backup tidak bisa dibuat.');
        }

        $zip->addFile($sqlAbsolutePath, basename($sqlPath));
        $zip->close();
        Storage::disk('local')->delete($sqlPath);
    }

    // Quote nama tabel atau kolom sesuai gaya driver database.
    private function quoteIdentifier(string $identifier, string $driver): string
    {
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return '`'.str_replace('`', '``', $identifier).'`';
        }

        return '"'.str_replace('"', '""', $identifier).'"';
    }

    // Quote nilai data supaya aman ditulis ke statement SQL.
    private function quoteValue(PDO $pdo, $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        return $pdo->quote((string) $value);
    }

    // Tulis satu baris ke file backup SQL.
    private function write($handle, string $line): void
    {
        fwrite($handle, $line.PHP_EOL);
    }
}
