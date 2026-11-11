<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Console;

use App\Modules\SiteThyroidGhanaFoundation\Demo\DemoManifest;
use App\Modules\SiteThyroidGhanaFoundation\Demo\DemoRemovalBlocked;
use App\Modules\SiteThyroidGhanaFoundation\Demo\DemoSections;
use App\Modules\SiteThyroidGhanaFoundation\Demo\FinanceGuard;

final class RemoveDemo extends DemoCommand
{
    protected $signature = 'tgf:demo-remove {--tenant=thyroid-ghana-foundation} {--confirm : Required on production}';

    protected $description = 'Remove the Thyroid Ghana Foundation demo data created by tgf:demo-seed';

    public function handle(DemoManifest $manifest, DemoSections $sections, FinanceGuard $guard): int
    {
        $tenant = $this->demoTenant();
        if ($tenant === null) {
            return self::FAILURE;
        }

        $since = $manifest->startedAt($tenant);
        if ($since === null) {
            $this->info('No demo data is recorded for this tenant. Nothing to remove.');

            return self::SUCCESS;
        }

        try {
            $guard->assertNoRealRows($tenant, $since);
        } catch (DemoRemovalBlocked $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach (array_reverse($sections->all()) as $section) {
            $this->info($section->label().': '.$section->remove($tenant, $since).' rows removed');
        }

        $manifest->restoreCounters($tenant);
        $manifest->clear($tenant);

        return self::SUCCESS;
    }
}
