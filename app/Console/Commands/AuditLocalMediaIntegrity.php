<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Tenancy\LocalMediaIntegrityAudit;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class AuditLocalMediaIntegrity extends Command
{
    protected $signature = 'media:audit-local-integrity
                            {--tenant= : Exact tenant ID (required)}
                            {--root= : Absolute local directory containing only this tenant\'s copied originals (required)}';

    protected $description = 'Read-only local CMS original-media audit; aggregate counts only, no repairs or remote storage';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Local/testing environments only. Nothing audited.');

            return self::FAILURE;
        }
        $tenantId = $this->option('tenant');
        $root = $this->option('root');
        if (! is_string($tenantId) || $tenantId === '' || ! is_string($root)
            || ! str_starts_with($root, '/') || ! is_dir($root) || is_link($root)) {
            $this->error('Explicit tenant ID and existing absolute local tenant root are required.');

            return self::FAILURE;
        }
        $connection = config('database.connections.landlord', []);
        if (! $this->isLocalSnapshot($connection)) {
            $this->error('A local SQLite or loopback PostgreSQL landlord snapshot is required. Remote databases and database URLs are refused.');

            return self::FAILURE;
        }

        try {
            $audit = app(LocalMediaIntegrityAudit::class);
            $records = $audit->recordsForTenant($tenantId);
            if ($records === null) {
                $this->error('Tenant not found in the local shared snapshot; nothing audited.');

                return self::FAILURE;
            }

            $disk = Storage::build(['driver' => 'local', 'root' => realpath($root), 'throw' => true]);
            if (! $disk instanceof FilesystemAdapter) {
                $this->error('Local filesystem adapter required; nothing audited.');

                return self::FAILURE;
            }

            $counts = $audit->inspect($records, $tenantId, $disk);
            $this->line(json_encode($counts, JSON_THROW_ON_ERROR));
            $this->line('Read-only originals audit. Missing sources require verified backup recovery; checksum failures require source investigation.');
            $this->line('Size review candidates match stored SHA-256; no metadata was changed. Variants and orphan objects are not audited.');

            return $counts['verified'] === $counts['scanned'] ? self::SUCCESS : self::FAILURE;
        } catch (Throwable) {
            $this->error('Local audit failed; no repairs performed. Details suppressed to protect source data.');

            return self::FAILURE;
        }
    }

    /** @param array<string, mixed> $connection */
    private function isLocalSnapshot(array $connection): bool
    {
        if (! empty($connection['url'])) {
            return false;
        }
        if (($connection['driver'] ?? null) === 'sqlite') {
            return true;
        }

        return ($connection['driver'] ?? null) === 'pgsql'
            && in_array($connection['host'] ?? null, ['localhost', '127.0.0.1', '::1'], true)
            && is_string($connection['database'] ?? null)
            && mb_trim($connection['database']) !== '';
    }
}
