<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use DomainException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PDO;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Removes the dedicated database of a db_per_tenant tenant, optionally backing it up first.
 *
 * The backup is taken and checked before anything is dropped. If the backup fails
 * the database is left untouched.
 */
final class TenantDatabaseRemover
{
    /**
     * @return array{dropped: bool, database: string|null, backup: string|null}
     *
     * @throws DomainException When the database cannot be safely identified, backed up or dropped.
     */
    public function remove(Tenant $tenant, bool $backup = true): array
    {
        if (! $tenant->requiresDedicatedDb()) {
            return ['dropped' => false, 'database' => null, 'backup' => null];
        }

        $database = $this->resolveDatabaseName($tenant);

        if ($tenant->db_driver !== 'pgsql') {
            throw new DomainException("Removing a {$tenant->db_driver} tenant database is not supported.");
        }

        $backupFile = null;

        if ($this->databaseExists($database)) {
            if ($backup) {
                $backupFile = $this->backup($database);
            }

            $this->drop($database);
        }

        return ['dropped' => true, 'database' => $database, 'backup' => $backupFile];
    }

    public function backupDirectory(): string
    {
        return storage_path('app/tenant-database-backups');
    }

    /**
     * Only databases created by the provisioner (tenant_<id>) may ever be dropped,
     * and never the landlord database.
     */
    private function resolveDatabaseName(Tenant $tenant): string
    {
        $database = (string) ($tenant->meta['database'] ?? '');
        $landlordDatabase = (string) Config::get('database.connections.landlord.database');

        if ($database === '' || $database === $landlordDatabase || ! str_starts_with($database, 'tenant_')) {
            throw new DomainException(
                "Refusing to remove database '{$database}' for tenant {$tenant->id}: it is not a dedicated tenant database."
            );
        }

        if ($database !== 'tenant_'.str_replace('-', '_', (string) $tenant->id)) {
            throw new DomainException(
                "Refusing to remove database '{$database}': its name does not match tenant {$tenant->id}."
            );
        }

        return $database;
    }

    private function databaseExists(string $database): bool
    {
        $statement = $this->landlordPdo()->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
        $statement->execute([$database]);

        return $statement->fetchColumn() !== false;
    }

    private function backup(string $database): string
    {
        $directory = $this->backupDirectory();

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new DomainException("Could not create the backup directory {$directory}.");
        }

        $file = $directory.'/'.$database.'-'.now()->format('Ymd-His').'.dump';
        $landlord = Config::get('database.connections.landlord');

        $process = new Process(
            [
                $this->binary('pg_dump'),
                '--host='.($landlord['host'] ?? '127.0.0.1'),
                '--port='.($landlord['port'] ?? 5432),
                '--username='.($landlord['username'] ?? ''),
                '--format=custom',
                '--no-owner',
                '--file='.$file,
                $database,
            ],
            null,
            ['PGPASSWORD' => (string) ($landlord['password'] ?? '')],
            null,
            300
        );

        try {
            $process->run();
        } catch (Throwable $e) {
            throw new DomainException('Backup failed, nothing was removed: '.$e->getMessage(), 0, $e);
        }

        if (! $process->isSuccessful() || ! is_file($file) || filesize($file) === 0) {
            @unlink($file);

            throw new DomainException('Backup failed, nothing was removed: '.mb_trim($process->getErrorOutput()));
        }

        chmod($file, 0600);

        return $file;
    }

    private function drop(string $database): void
    {
        DB::purge('tenant');

        $this->landlordPdo()->exec(sprintf('DROP DATABASE IF EXISTS "%s" WITH (FORCE)', str_replace('"', '""', $database)));
    }

    private function binary(string $name): string
    {
        $configured = (string) Config::get('database.connections.landlord.dump.dump_binary_path', '');

        return $configured !== '' ? mb_rtrim($configured, '/').'/'.$name : $name;
    }

    private function landlordPdo(): PDO
    {
        $landlord = Config::get('database.connections.landlord');

        $pdo = new PDO(
            sprintf(
                'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
                $landlord['host'] ?? '127.0.0.1',
                $landlord['port'] ?? 5432,
                $landlord['database'],
                $landlord['sslmode'] ?? 'prefer'
            ),
            $landlord['username'] ?? null,
            $landlord['password'] ?? null
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }
}
