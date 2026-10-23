<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use App\Modules\CmsCore\Models\Media;
use Illuminate\Filesystem\FilesystemAdapter;
use Throwable;

final class LocalMediaIntegrityAudit
{
    /** @return iterable<array<string, mixed>>|null */
    public function recordsForTenant(string $tenantId): ?iterable
    {
        $tenant = Tenant::query()->whereKey($tenantId)->first();
        if (! $tenant || $tenant->isolation_mode !== 'shared') {
            return null;
        }

        return Media::on('landlord')->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('disk', 'tenant')
            ->whereNull('deleted_at')->select(['id', 'tenant_id', 'disk', 'path', 'size_bytes', 'checksum_sha256'])
            ->lazyById(200)->map(fn (Media $media): array => $media->getAttributes());
    }

    /**
     * The caller supplies a local disk rooted at one tenant's copied originals.
     * Counts are independent: a record can have both size and checksum failures.
     *
     * @param  iterable<array<string, mixed>>  $records
     * @return array<string, int>
     */
    public function inspect(iterable $records, string $tenantId, FilesystemAdapter $disk): array
    {
        $counts = array_fill_keys(['scanned', 'verified', 'missing_source', 'size_mismatch', 'checksum_mismatch', 'checksum_unavailable', 'size_review_candidate', 'scope_rejected', 'read_failed'], 0);
        $config = $disk->getConfig();
        $root = ($config['driver'] ?? null) === 'local'
            ? realpath((string) ($config['root'] ?? '')) : false;

        foreach ($records as $record) {
            $counts['scanned']++;
            $path = $record['path'] ?? null;
            if ($root === false || $tenantId === '' || (string) ($record['tenant_id'] ?? '') !== $tenantId
                || ($record['disk'] ?? null) !== 'tenant' || ! is_string($path)
                || $path === '' || preg_match('/[\\\\:\x00-\x1f\x7f]/', $path)
                || str_starts_with($path, '/') || array_intersect(explode('/', $path), ['.', '..', '']) !== []) {
                $counts['scope_rejected']++;

                continue;
            }

            $stream = null;
            try {
                $absolute = $root.DIRECTORY_SEPARATOR.$path;
                $segments = explode('/', $path);
                $cursor = $root;
                foreach ($segments as $segment) {
                    $cursor .= DIRECTORY_SEPARATOR.$segment;
                    if (is_link($cursor)) {
                        $counts['scope_rejected']++;

                        continue 2;
                    }
                }
                $resolved = realpath($absolute);
                if ($resolved === false) {
                    $counts['missing_source']++;

                    continue;
                }
                if (! str_starts_with($resolved, $root.DIRECTORY_SEPARATOR) || ! is_file($resolved)) {
                    $counts['scope_rejected']++;

                    continue;
                }

                $stream = $disk->readStream($path);
                if (! is_resource($stream)) {
                    $counts['read_failed']++;

                    continue;
                }
                $hash = hash_init('sha256');
                $bytes = 0;
                while (! feof($stream)) {
                    $chunk = fread($stream, 1024 * 1024);
                    if ($chunk === false || ($chunk === '' && ! feof($stream))) {
                        $counts['read_failed']++;

                        continue 2;
                    }
                    $bytes += mb_strlen($chunk, '8bit');
                    hash_update($hash, $chunk);
                }
                $checksum = hash_final($hash);
                $expected = $record['checksum_sha256'] ?? null;
                $hasChecksum = is_string($expected) && preg_match('/\A[a-fA-F0-9]{64}\z/', $expected) === 1;
                $checksumMatches = $hasChecksum && hash_equals(mb_strtolower($expected), $checksum);
                $sizeMatches = isset($record['size_bytes']) && (int) $record['size_bytes'] === $bytes;
                if (! $sizeMatches) {
                    $counts['size_mismatch']++;
                }
                if (! $hasChecksum) {
                    $counts['checksum_unavailable']++;
                } elseif (! $checksumMatches) {
                    $counts['checksum_mismatch']++;
                }
                if ($checksumMatches) {
                    $counts[$sizeMatches ? 'verified' : 'size_review_candidate']++;
                }
            } catch (Throwable) {
                $counts['read_failed']++;
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }

        return $counts;
    }
}
