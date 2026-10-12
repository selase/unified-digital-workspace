<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Console\Commands;

use App\Models\Tenant;
use App\Modules\CmsCore\Services\CmsSitemapService;
use Illuminate\Console\Command;

final class GenerateSitemapCommand extends Command
{
    protected $signature = 'cms:generate-sitemap {--tenant= : Only clear the sitemap cache for this tenant id}';

    protected $description = 'Clear cached sitemaps so they are rebuilt from current content on the next request';

    public function handle(): int
    {
        $query = Tenant::query();

        if ($this->option('tenant')) {
            $query->whereKey((string) $this->option('tenant'));
        }

        $count = 0;

        foreach ($query->pluck('id') as $tenantId) {
            CmsSitemapService::forget((string) $tenantId);
            $count++;
        }

        $this->info("Cleared cached sitemaps for {$count} tenant(s).");

        return self::SUCCESS;
    }
}
