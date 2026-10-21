<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Tenant;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Backup\Events\BackupManifestWasCreated;
use Throwable;

final class SnapshotTenantMedia
{
    public function handle(BackupManifestWasCreated $event): void
    {
        $directory = dirname($event->manifest->path());
        $applicationFiles = false;
        foreach ($event->manifest->files() as $path) {
            if ($path !== '' && ! str_starts_with($path, $directory.DIRECTORY_SEPARATOR)) {
                $applicationFiles = true;
                break;
            }
        }
        // Database-only manifests contain dumps solely within the job directory.
        if (! $applicationFiles) {
            return;
        }

        $config = (array) config('filesystems.disks.s3');
        foreach (['bucket', 'region', 'key', 'secret'] as $required) {
            if (! is_string($config[$required] ?? null) || mb_trim($config[$required]) === '') {
                throw new RuntimeException('Tenant media backup requires configured shared S3 storage.');
            }
        }

        if (Tenant::query()->where('s3_mode', 'byo')->exists()) {
            throw new RuntimeException('Tenant media backup does not support BYO buckets; backup aborted rather than claiming coverage.');
        }

        $staging = $directory.'/tenant-media';
        if (file_exists($staging)) {
            throw new RuntimeException('Tenant media backup staging directory already exists.');
        }
        $filesystem = new Filesystem;
        $filesystem->makeDirectory($staging, 0700, true);
        $index = ['schema_version' => 1, 'created_at' => now()->toIso8601String(), 'tenants' => []];
        $files = [];
        try {
            foreach (Tenant::query()->orderBy('id')->lazyById() as $tenant) {
                if ($tenant->s3_mode === 'byo') {
                    throw new RuntimeException('Tenant media backup does not support BYO buckets; backup aborted rather than claiming coverage.');
                }
                if (! preg_match('/^[a-z0-9][a-z0-9_-]{0,254}$/D', (string) $tenant->slug)) {
                    throw new RuntimeException('Invalid tenant storage scope.');
                }
                $root = mb_trim((string) ($config['root'] ?? ''), '/');
                $root = ($root !== '' ? $root.'/' : '').'tenants/'.$tenant->slug;
                $disk = Storage::build([...$config, 'driver' => 's3', 'root' => $root, 'throw' => true]);
                $tenantFiles = [];
                foreach ($disk->allFiles() as $key) {
                    $relative = 'tenant-media/'.hash('sha256', (string) $tenant->id).'/'.hash('sha256', $key);
                    $destination = $directory.'/'.$relative;
                    $filesystem->ensureDirectoryExists(dirname($destination), 0700, true);
                    $input = $disk->readStream($key);
                    if (! is_resource($input)) {
                        throw new RuntimeException('Cannot read tenant media object.');
                    }
                    try {
                        $output = fopen($destination, 'xb');
                        if (! is_resource($output)) {
                            throw new RuntimeException('Cannot stage tenant media object.');
                        }
                        chmod($destination, 0600);
                        try {
                            $bytes = stream_copy_to_stream($input, $output);
                        } finally {
                            fclose($output);
                        }
                    } finally {
                        fclose($input);
                    }
                    if ($bytes === false || $bytes !== $disk->size($key)) {
                        throw new RuntimeException('Tenant media object changed or could not be copied completely.');
                    }
                    $checksum = hash_file('sha256', $destination);
                    if ($checksum === false) {
                        throw new RuntimeException('Cannot verify staged tenant media object.');
                    }
                    $tenantFiles[] = ['key' => $key, 'archive_path' => $relative, 'bytes' => $bytes, 'sha256' => $checksum];
                    $files[] = $destination;
                }
                $index['tenants'][] = ['tenant_id' => (string) $tenant->id, 'slug' => $tenant->slug, 'files' => $tenantFiles];
            }
            $indexPath = $staging.'/index.json';
            if (file_put_contents($indexPath, json_encode($index, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), LOCK_EX) === false) {
                throw new RuntimeException('Cannot write tenant media backup index.');
            }
            chmod($indexPath, 0600);
            $event->manifest->addFiles([...$files, $indexPath]);
        } catch (Throwable) {
            $filesystem->deleteDirectory($staging);
            throw new RuntimeException('Tenant media snapshot failed; no complete backup was created. Check S3 access, tenant storage scope and temporary disk capacity.');
        }
    }
}
