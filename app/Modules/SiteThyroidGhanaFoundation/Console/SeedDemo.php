<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Console;

use App\Models\AlertRule;
use App\Modules\SiteThyroidGhanaFoundation\Demo\DemoManifest;
use App\Modules\SiteThyroidGhanaFoundation\Demo\DemoSections;
use App\Modules\SiteThyroidGhanaFoundation\Demo\FinanceGuard;
use App\Modules\SiteThyroidGhanaFoundation\Demo\Sections\MembershipTiersSection;
use Throwable;

final class SeedDemo extends DemoCommand
{
    protected $signature = 'tgf:demo-seed {--tenant=thyroid-ghana-foundation} {--confirm : Required on production}';

    protected $description = 'Seed removable demo data for the Thyroid Ghana Foundation presentation';

    public function handle(DemoManifest $manifest, DemoSections $sections, FinanceGuard $guard, MembershipTiersSection $tiers): int
    {
        $tenant = $this->demoTenant();
        if ($tenant === null) {
            return self::FAILURE;
        }

        if ($manifest->startedAt($tenant) !== null) {
            $this->info('Demo data is already seeded. Run tgf:demo-remove first to start again.');

            return self::SUCCESS;
        }

        if (AlertRule::query()->where('tenant_id', $tenant->id)->where('is_active', true)->exists()) {
            $this->error('This tenant has active alert rules, and demo gifts, payments and invoices would notify real staff. Switch them off first.');

            return self::FAILURE;
        }

        $manifest->begin($tenant);
        $manifest->rememberTierIds($tenant, $tiers->ensure($tenant));
        $counts = [];

        foreach ($sections->all() as $section) {
            try {
                $section->seed($tenant);
                $counts[$section->label()] = 'seeded';
                $this->info($section->label().': seeded');
            } catch (Throwable $e) {
                $this->error($section->label().' failed: '.$e->getMessage().'. Run tgf:demo-remove to clean up.');

                return self::FAILURE;
            }
        }

        $manifest->finish($tenant, $counts, $guard->signature($tenant));

        return self::SUCCESS;
    }
}
