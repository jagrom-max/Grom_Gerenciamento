<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use RuntimeException;

class SqliteDatabaseBackup
{
    public static function backup(string $reason): ?string
    {
        if (config('database.default') !== 'sqlite') {
            return null;
        }

        $database = (string) config('database.connections.sqlite.database');

        if ($database === '' || $database === ':memory:' || ! is_file($database)) {
            return null;
        }

        $backupDir = storage_path('app/backups/sqlite');
        File::ensureDirectoryExists($backupDir);

        $safeReason = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($reason)) ?: 'backup';
        $safeReason = trim($safeReason, '-');
        $filename = now()->format('Ymd_His') . '_' . $safeReason . '_' . basename($database);
        $target = $backupDir . DIRECTORY_SEPARATOR . $filename;

        if (! copy($database, $target)) {
            throw new RuntimeException('Nao foi possivel criar backup local do banco antes da operacao critica.');
        }

        return $target;
    }
}
