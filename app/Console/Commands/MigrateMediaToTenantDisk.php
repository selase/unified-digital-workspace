<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantStorageManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class MigrateMediaToTenantDisk extends Command
{
    protected $signature = 'media:migrate-to-tenant-disk
                            {--tenant= : Tenant slug or ID to migrate}
                            {--dry-run : Show what would be migrated without making changes}';

    protected $description = 'Migrate media files from the public disk to the tenant S3 disk';

    public function handle(TenantStorageManager $storageManager, TenantContext $tenantContext): int
    {
        $tenantFilter = $this->option('tenant');

        // Resolve tenant
        $tenant = $tenantFilter
            ? Tenant::where('slug', $tenantFilter)->orWhere('id', $tenantFilter)->first()
            : null;

        if ($tenantFilter && ! $tenant) {
            $this->error("Tenant not found: {$tenantFilter}");

            return self::FAILURE;
        }

        // Use raw DB query to avoid tenant scope / connection issues
        $query = DB::connection('landlord')->table('media')->where('disk', 'public');

        if ($tenant) {
            $query->where('tenant_id', $tenant->id);
        }

        $mediaItems = $query->get();

        if ($mediaItems->isEmpty()) {
            $this->info('No media items on the public disk to migrate.');

            return self::SUCCESS;
        }

        $this->info("Found {$mediaItems->count()} media items to migrate.");

        $sourceDisk = Storage::disk('public');
        $migrated = 0;
        $failed = 0;
        $currentTenantId = null;

        foreach ($mediaItems as $media) {
            if (! $media->tenant_id) {
                $this->warn("  Media #{$media->id} ({$media->title}) has no tenant — skipping.");
                $failed++;

                continue;
            }

            // Only reconfigure disk when tenant changes
            if ($media->tenant_id !== $currentTenantId) {
                $mediaTenant = $tenant ?? Tenant::find($media->tenant_id);

                if (! $mediaTenant) {
                    $this->warn("  Media #{$media->id} — tenant {$media->tenant_id} not found — skipping.");
                    $failed++;

                    continue;
                }

                $tenantContext->setTenant($mediaTenant);
                $storageManager->configure($mediaTenant);
                $currentTenantId = $media->tenant_id;
            }

            if (! $sourceDisk->exists($media->path)) {
                $this->warn("  Media #{$media->id} ({$media->title}) file not found at {$media->path} — skipping.");
                $failed++;

                continue;
            }

            $newPath = $media->path;

            if ($this->option('dry-run')) {
                $this->line("  [DRY RUN] #{$media->id} {$media->title}: public:{$media->path} → tenant:{$newPath}");
                $migrated++;

                continue;
            }

            // Copy file from public disk to tenant disk (S3)
            $tenantDisk = Storage::disk('tenant');
            $fileContents = $sourceDisk->get($media->path);
            $tenantDisk->put($newPath, $fileContents);

            if (! $tenantDisk->exists($newPath)) {
                $this->error("  Failed to upload #{$media->id} ({$media->title}) to tenant disk.");
                $failed++;

                continue;
            }

            // Update the database record
            DB::connection('landlord')->table('media')
                ->where('id', $media->id)
                ->update(['disk' => 'tenant']);

            $this->info("  ✓ #{$media->id} {$media->title} → tenant:{$newPath}");
            $migrated++;
        }

        $this->newLine();
        $this->info("Migration complete: {$migrated} migrated, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
