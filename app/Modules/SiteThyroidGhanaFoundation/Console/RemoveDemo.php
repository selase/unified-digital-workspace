<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Console;

use App\Modules\SiteThyroidGhanaFoundation\Demo\DemoManifest;
use App\Modules\SiteThyroidGhanaFoundation\Demo\DemoRemovalBlocked;
use App\Modules\SiteThyroidGhanaFoundation\Demo\DemoSections;
use App\Modules\SiteThyroidGhanaFoundation\Demo\FinanceGuard;
use App\Modules\SiteThyroidGhanaFoundation\Demo\Sections\MembershipTiersSection;

final class RemoveDemo extends DemoCommand
{
    protected $signature = 'tgf:demo-remove {--tenant=thyroid-ghana-foundation} {--confirm : Required on production} {--with-tiers : Also remove the proposed membership tiers nobody has joined}';

    protected $description = 'Remove the Thyroid Ghana Foundation demo data created by tgf:demo-seed';

    public function handle(DemoManifest $manifest, DemoSections $sections, FinanceGuard $guard, MembershipTiersSection $tiers): int
    {
        $tenant = $this->demoTenant();
        if ($tenant === null) {
            return self::FAILURE;
        }

        $since = $manifest->startedAt($tenant);
        if ($since === null && ! $this->option('with-tiers')) {
            $this->info('No demo data is recorded for this tenant. Nothing to remove.');

            return self::SUCCESS;
        }

        if ($since !== null) {
            try {
                $guard->assertUntouched($tenant, $since, $manifest->financeRows($tenant));
            } catch (DemoRemovalBlocked $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            foreach (array_reverse($sections->all()) as $section) {
                $this->info($section->label().': '.$section->remove($tenant, $since).' rows removed');
            }

            $manifest->restoreCounters($tenant);
            $manifest->clear($tenant);
        }

        if ($this->option('with-tiers')) {
            $this->info('Membership tiers: '.$tiers->removeUnused($tenant, $manifest->proposedTierIds($tenant)).' removed');
            $manifest->forgetTierIds($tenant);
        }

        return self::SUCCESS;
    }
}
