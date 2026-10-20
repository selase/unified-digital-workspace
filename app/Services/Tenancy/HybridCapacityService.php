<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\HybridCapacityCounter;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Included capacity for explicitly adopted agreements. GiB means 1024^3 bytes.
 * Bandwidth covers application-delivered public website bodies; external CDN,
 * browser cache hits and direct static files never reach this meter.
 */
final class HybridCapacityService
{
    public function __construct(private readonly BillingAgreementService $agreements) {}

    public function limit(Tenant $tenant, string $meter): ?int
    {
        $agreement = $this->agreements->activeFor($tenant);
        if (! $agreement) {
            return null;
        }

        $value = data_get($agreement->snapshot, 'hybrid.website_plans.'.$agreement->plan_slug.'.'.$meter.'_gib');
        if (! is_numeric($value) || (float) $value < 0) {
            throw new HttpException(503, 'Website capacity agreement is incomplete.');
        }

        return (int) floor((float) $value * 1073741824);
    }

    /** Serialize all reservations and audits for a tenant, including initial counter creation. */
    public function locked(Tenant $tenant, Closure $callback): mixed
    {
        return DB::connection('landlord')->transaction(function () use ($tenant, $callback): mixed {
            Tenant::query()->whereKey($tenant->getKey())->lockForUpdate()->firstOrFail();

            return $callback();
        });
    }

    /** Reserve before sending any body bytes. Failed generation must never consume quota. */
    public function reserveBandwidth(Tenant $tenant, int $bytes): void
    {
        $limit = $this->limit($tenant, 'bandwidth');
        if ($limit === null) {
            return;
        }
        if ($bytes < 0) {
            throw new RuntimeException('Negative capacity reservation.');
        }

        $this->locked($tenant, function () use ($tenant, $bytes, $limit): void {
            $counter = $this->counter($tenant, 'bandwidth');
            if ($bytes > $limit - $counter->bytes) {
                throw new HttpException(429, 'Monthly public website bandwidth capacity exceeded.');
            }
            $counter->update(['bytes' => $counter->bytes + $bytes]);
        });
    }

    /**
     * Explicit adoption/reconciliation audit. Only the tenant-scoped disk is
     * authoritative; shared public disks cannot establish tenant ownership.
     * All CMS writers must use writeFile so this scan is not needed per request.
     */
    public function auditStorage(Tenant $tenant): int
    {
        if (app(TenantContext::class)->getTenant()?->id !== $tenant->id) {
            app(TenantDatabaseManager::class)->configure($tenant);
        }

        return $this->locked($tenant, function () use ($tenant): int {
            app(TenantStorageManager::class)->configure($tenant);
            $foreignDisk = \App\Modules\CmsCore\Models\Media::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)->where('disk', '!=', 'tenant')->exists()
                || \App\Modules\CmsCore\Models\MediaVariant::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)->where('disk', '!=', 'tenant')->exists();
            if ($foreignDisk) {
                throw new HttpException(503, 'Migrate shared-disk website files to tenant storage before auditing.');
            }
            $disk = Storage::disk('tenant');
            $bytes = 0;
            $paths = array_merge($disk->allFiles('cms'), $disk->allFiles('forms'),
                \App\Modules\CmsCore\Models\Media::withoutGlobalScopes()->where('tenant_id', $tenant->id)
                    ->where('disk', 'tenant')->pluck('path')->all(),
                \App\Modules\CmsCore\Models\MediaVariant::withoutGlobalScopes()->where('tenant_id', $tenant->id)
                    ->where('disk', 'tenant')->pluck('path')->all());
            foreach (array_unique($paths) as $path) {
                if ($disk->exists($path)) {
                    $bytes += $disk->size($path);
                }
            }
            $this->counter($tenant, 'files')->update(['bytes' => $bytes, 'audited_at' => now()]);

            return $bytes;
        });
    }

    /** Reserve the exact encoded size before writing; restore files and quota on failure. */
    public function writeFile(Tenant $tenant, string $diskName, string $path, string $contents): void
    {
        $limit = $this->limit($tenant, 'files');
        $disk = Storage::disk($diskName);
        if ($limit === null) {
            if (! $disk->put($path, $contents)) {
                throw new RuntimeException('Unable to write website file.');
            }

            return;
        }
        if ($diskName !== 'tenant') {
            throw new HttpException(503, 'Adopted websites require tenant-scoped storage.');
        }

        $this->assertActiveStorageTenant($tenant);
        if ((! str_starts_with($path, 'cms/') && ! str_starts_with($path, 'forms/'))
            || in_array('..', explode('/', $path), true)) {
            throw new HttpException(503, 'Website writes require an audited CMS or Forms storage path.');
        }
        $disk = Storage::disk($diskName);
        $this->locked($tenant, function () use ($tenant, $disk, $path, $contents, $limit): void {
            $counter = $this->counter($tenant, 'files');
            if (! $this->auditIsCurrent($tenant, $counter)) {
                throw new HttpException(503, 'Website storage must be audited before writing files.');
            }
            $old = $disk->exists($path) ? $disk->get($path) : null;
            $delta = mb_strlen($contents, '8bit') - ($old === null ? 0 : mb_strlen($old, '8bit'));
            if ($delta > 0 && $delta > $limit - $counter->bytes) {
                throw new HttpException(413, 'Website file capacity exceeded.');
            }
            try {
                if (! $disk->put($path, $contents)) {
                    throw new RuntimeException('Unable to write website file.');
                }
                $counter->update(['bytes' => max(0, $counter->bytes + $delta)]);
            } catch (Throwable $exception) {
                $old === null ? $disk->delete($path) : $disk->put($path, $old);
                throw $exception;
            }
            if (app()->bound('request') && request()->attributes->get('hybrid.database_mutation', false)) {
                $callbacks = request()->attributes->get('hybrid.file_rollback', []);
                $callbacks[] = function () use ($disk, $path, $old): void {
                    $old === null ? $disk->delete($path) : $disk->put($path, $old);
                };
                request()->attributes->set('hybrid.file_rollback', $callbacks);
            }
        });
    }

    /** A delete remains available over quota and releases only physically removed bytes. */
    public function deleteFile(Tenant $tenant, string $diskName, string $path): bool
    {
        if ($this->limit($tenant, 'files') === null) {
            return Storage::disk($diskName)->delete($path);
        }
        if ($diskName !== 'tenant') {
            throw new HttpException(503, 'Website storage ownership cannot be established.');
        }

        $this->assertActiveStorageTenant($tenant);

        return $this->locked($tenant, function () use ($tenant, $diskName, $path): bool {
            $disk = Storage::disk($diskName);
            $old = $disk->exists($path) ? $disk->get($path) : null;
            $bytes = $old === null ? 0 : mb_strlen($old, '8bit');
            if (! $disk->delete($path)) {
                return false;
            }
            $counter = $this->counter($tenant, 'files');
            $counter->update(['bytes' => max(0, $counter->bytes - $bytes)]);
            if ($old !== null && request()->attributes->get('hybrid.database_mutation', false)) {
                $callbacks = request()->attributes->get('hybrid.file_rollback', []);
                $callbacks[] = fn (): bool => $disk->put($path, $old);
                request()->attributes->set('hybrid.file_rollback', $callbacks);
            }

            return true;
        });
    }

    /**
     * Validate registration against tenant-owned bytes rather than client metadata.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function mediaAttributes(Tenant $tenant, array $attributes, ?\App\Modules\CmsCore\Models\Media $media = null): array
    {
        $limit = $this->limit($tenant, 'files');
        if ($limit === null) {
            return $attributes;
        }
        $this->assertActiveStorageTenant($tenant);
        $diskName = (string) ($attributes['disk'] ?? ($media !== null ? $media->disk : ''));
        $path = (string) ($attributes['path'] ?? ($media !== null ? $media->path : ''));
        $segments = explode('/', $path);
        if ($diskName !== 'tenant' || ! in_array($segments[0], ['cms', 'forms'], true)
            || count($segments) < 2 || array_intersect($segments, ['', '.', '..']) !== []
            || preg_match('/[\\\\%\x00-\x1f]/', $path)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['path' => 'Media must reference a tenant-owned CMS or Forms file without traversal.']);
        }
        $disk = Storage::disk('tenant');
        if (! $disk->exists($path)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['path' => 'The tenant media file does not exist.']);
        }
        if (config('filesystems.disks.tenant.driver') === 'local') {
            $root = realpath($disk->path(''));
            $resolved = realpath($disk->path($path));
            if ($root === false || $resolved === false || ! str_starts_with($resolved, $root.DIRECTORY_SEPARATOR)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['path' => 'Media must remain inside tenant storage.']);
            }
        }
        if (! $this->storageAudited($tenant)) {
            throw new HttpException(503, 'Website storage must be audited before registering files.');
        }
        if ($media === null || $media->disk !== $diskName || $media->path !== $path) {
            if ($this->auditStorage($tenant) > $limit) {
                throw new HttpException(413, 'Website file capacity exceeded.');
            }
        }
        $attributes['size_bytes'] = $disk->size($path);
        $attributes['checksum_sha256'] = hash('sha256', (string) $disk->get($path));

        return $attributes;
    }

    /** Delete original and cached variants together so quota can actually recover. */
    public function deleteMediaFiles(Tenant $tenant, \App\Modules\CmsCore\Models\Media $media): void
    {
        foreach ($media->variants()->get() as $variant) {
            if (! $this->deleteFile($tenant, $variant->disk, $variant->path)) {
                throw new RuntimeException('Unable to delete website media variant.');
            }
            $variant->delete();
        }
        if (! $this->deleteFile($tenant, $media->disk, $media->path)) {
            throw new RuntimeException('Unable to delete website media.');
        }
    }

    public function storageAudited(Tenant $tenant): bool
    {
        $counter = HybridCapacityCounter::query()->where('tenant_id', $tenant->id)
            ->where('meter', 'files')->where('period', 'lifetime')->first();

        return $counter !== null && $this->auditIsCurrent($tenant, $counter);
    }

    public function auditDatabase(Tenant $tenant): int
    {
        if (app(TenantContext::class)->getTenant()?->id !== $tenant->id) {
            app(TenantDatabaseManager::class)->configure($tenant);
        }

        return $this->locked($tenant, function () use ($tenant): int {
            $bytes = $this->databaseBytes($tenant);
            $this->counter($tenant, 'database')->update(['bytes' => $bytes, 'audited_at' => now()]);

            return $bytes;
        });
    }

    /**
     * Logical CMS and Forms tenant row bytes, never pg_database_size/shared table size.
     * PostgreSQL/MySQL sum octet lengths of stored column values; SQLite sums BLOB
     * lengths. These are logical bytes, excluding indexes, row/page overhead
     * and free space, which cannot be attributed to one shared tenant.
     */
    public function databaseBytes(Tenant $tenant): int
    {
        $connection = DB::connection('tenant');
        $schema = $connection->getSchemaBuilder();
        $grammar = $connection->getQueryGrammar();
        $bytes = 0;
        foreach ([
            'post_types', 'posts', 'categories', 'tags', 'media', 'media_variants',
            'menus', 'settings', 'post_revisions', 'post_meta', 'category_post',
            'post_tag', 'media_post', 'menu_items', 'form_definitions',
            'form_fields', 'form_submissions', 'poll_votes',
        ] as $table) {
            if (! $schema->hasTable($table)) {
                continue;
            }
            $ownership = [
                'post_revisions' => ['post_id', 'posts'],
                'post_meta' => ['post_id', 'posts'],
                'category_post' => ['post_id', 'posts'],
                'post_tag' => ['post_id', 'posts'],
                'media_post' => ['post_id', 'posts'],
                'menu_items' => ['menu_id', 'menus'],
            ][$table] ?? null;
            if ($ownership !== null) {
                [$foreignKey, $parent] = $ownership;
                $query = $connection->table($table)->whereIn($foreignKey,
                    $connection->table($parent)->select('id')->where('tenant_id', $tenant->id));
            } elseif ($schema->hasColumn($table, 'tenant_id')) {
                $query = $connection->table($table)->where('tenant_id', $tenant->id);
            } else {
                continue;
            }
            $expression = match ($connection->getDriverName()) {
                'pgsql' => implode(' + ', array_map(
                    fn (string $column): string => 'COALESCE(octet_length(CAST('.$grammar->wrap($column).' AS TEXT)), 0)',
                    $schema->getColumnListing($table),
                )),
                'mysql' => implode(' + ', array_map(
                    fn (string $column): string => 'COALESCE(OCTET_LENGTH(CAST('.$grammar->wrap($column).' AS BINARY)), 0)',
                    $schema->getColumnListing($table),
                )),
                'sqlite' => implode(' + ', array_map(
                    fn (string $column): string => 'COALESCE(length(CAST('.$grammar->wrap($column).' AS BLOB)), 0)',
                    $schema->getColumnListing($table),
                )),
                default => throw new HttpException(503, 'Tenant database capacity measurement is unavailable.'),
            };
            $bytes += (int) $query->selectRaw('COALESCE(SUM('.$expression.'), 0) AS capacity_bytes')->value('capacity_bytes');
        }

        return $bytes;
    }

    /** Roll back database growth beyond quota, while allowing reductions and recovery. */
    public function databaseMutation(Tenant $tenant, Closure $callback): mixed
    {
        $limit = $this->limit($tenant, 'database');
        if ($limit === null) {
            return $callback();
        }

        request()->attributes->set('hybrid.database_mutation', true);
        request()->attributes->set('hybrid.file_rollback', []);
        try {
            return $this->locked($tenant, function () use ($tenant, $callback, $limit): mixed {
                try {
                    return DB::connection('tenant')->transaction(function () use ($tenant, $callback, $limit): mixed {
                        $before = $this->databaseBytes($tenant);
                        $result = $callback();
                        if ($result instanceof \Symfony\Component\HttpFoundation\Response && $result->getStatusCode() >= 400) {
                            throw new HttpResponseException($result);
                        }
                        $after = $this->databaseBytes($tenant);
                        if ($after > $limit && $after > $before) {
                            throw new HttpException(413, 'Website database capacity exceeded. Delete content to recover capacity.');
                        }
                        $this->counter($tenant, 'database')->update(['bytes' => $after, 'audited_at' => now()]);

                        return $result;
                    });
                } catch (Throwable $exception) {
                    foreach (array_reverse(request()->attributes->get('hybrid.file_rollback', [])) as $rollback) {
                        $rollback();
                    }
                    throw $exception;
                }
            });
        } finally {
            request()->attributes->remove('hybrid.file_rollback');
            request()->attributes->remove('hybrid.database_mutation');
        }
    }

    /** A pre-transition audit cannot account for subsequent unmetered legacy writes. */
    private function auditIsCurrent(Tenant $tenant, HybridCapacityCounter $counter): bool
    {
        if (! $counter->audited_at) {
            return false;
        }
        $agreement = $this->agreements->activeFor($tenant);
        if (! $agreement) {
            return true;
        }

        return ! $counter->audited_at->lt($agreement->effective_from)
            && ! $counter->audited_at->lt($agreement->created_at);
    }

    private function assertActiveStorageTenant(Tenant $tenant): void
    {
        if (app(TenantContext::class)->getTenant()?->id !== $tenant->id) {
            throw new HttpException(503, 'Activate the tenant storage context before writing website files.');
        }
    }

    private function counter(Tenant $tenant, string $meter): HybridCapacityCounter
    {
        return HybridCapacityCounter::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'meter' => $meter,
            'period' => $meter === 'bandwidth' ? now()->format('Y-m') : 'lifetime',
        ], ['bytes' => 0]);
    }
}
